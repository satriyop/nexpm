<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Report;
use App\Models\User;
use App\Services\BastReportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ReportController extends Controller
{
    public function index(): Response
    {
        $verifiedBase = fn () => Assignment::query()
            ->where('status', AssignmentStatus::Verified)
            ->whereHas('site.project', fn ($q) => $q->whereScopedToMainContractor())
            ->with(['site.siteType', 'subcontractor'])
            ->latest('verified_at');

        $recentReports = DB::table('reports')
            ->select([
                'reports.id',
                'reports.name',
                'reports.report_type',
                'reports.created_at',
                DB::raw('COUNT(report_assignments.assignment_id) as assignments_count'),
            ])
            ->leftJoin('report_assignments', 'reports.id', '=', 'report_assignments.report_id')
            ->groupBy('reports.id', 'reports.name', 'reports.report_type', 'reports.created_at')
            ->orderByDesc('reports.created_at')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'report_type' => $row->report_type,
                'assignments_count' => (int) $row->assignments_count,
                'created_at' => $row->created_at,
            ]);

        return Inertia::render('admin/reports/Index', [
            'ssrAssignments' => $verifiedBase()->where('activity_type', ActivityType::Survey)->get(),
            'bastAssignments' => $verifiedBase()->where('activity_type', ActivityType::Bast)->get(),
            'recentReports' => $recentReports,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'report_type' => ['required', 'string', 'in:SSR,BAST,DAILY'],
            'assignment_ids' => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', 'exists:assignments,id'],
        ]);

        $assignmentIds = $validated['assignment_ids'];

        $verifiedCount = Assignment::whereIn('id', $assignmentIds)
            ->where('status', AssignmentStatus::Verified)
            ->count();

        abort_unless($verifiedCount === count($assignmentIds), 422, 'All selected assignments must have VERIFIED status.');

        /** @var User $user */
        $user = auth()->user();

        $typeLabel = match ($validated['report_type']) {
            'SSR' => 'SSR Report',
            'BAST' => 'BAST Report',
            'DAILY' => 'Daily Report',
            default => 'Report',
        };

        $report = Report::create([
            'name' => $typeLabel.' '.now()->format('Y-m-d H:i'),
            'report_type' => $validated['report_type'],
            'exported_by' => $user->id,
        ]);

        $report->assignments()->attach($assignmentIds);

        Assignment::whereIn('id', $assignmentIds)->each(fn (Assignment $a) => $a->markReported());

        return redirect()->route('admin.reports.index')->with('success', $typeLabel.' generated successfully.');
    }

    public function download(Report $report, BastReportExportService $bastService): StreamedResponse
    {
        return match ($report->report_type) {
            'BAST', 'BAST_EVCS', 'BAST_BSS' => $this->downloadBast($report, $bastService),
            'SSR' => $this->downloadSsr($report),
            default => $this->downloadDaily($report),
        };
    }

    private function downloadBast(Report $report, BastReportExportService $bastService): StreamedResponse
    {
        $assignments = $report->assignments()
            ->where('activity_type', ActivityType::Bast)
            ->with(['site', 'bastData.bastPhotos'])
            ->get();

        abort_if($assignments->isEmpty(), 404, 'No BAST assignments in this report.');

        if ($assignments->count() === 1) {
            $assignment = $assignments->first();
            $spreadsheet = $bastService->generate($assignment);
            $filename = sprintf('COMM-TEST-%s-%s.xlsx', $assignment->site->site_code, now()->format('Ymd'));

            return response()->stream(function () use ($spreadsheet) {
                (new Xlsx($spreadsheet))->save('php://output');
            }, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        $zipFilename = sprintf('BAST-Report-%s-%s.zip', $report->id, now()->format('Ymd'));
        $tmpPath = sys_get_temp_dir().'/'.$zipFilename;

        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($assignments as $assignment) {
            $spreadsheet = $bastService->generate($assignment);
            $xlsxName = sprintf('COMM-TEST-%s-%s.xlsx', $assignment->site->site_code, now()->format('Ymd'));

            ob_start();
            (new Xlsx($spreadsheet))->save('php://output');
            $zip->addFromString($xlsxName, (string) ob_get_clean());
        }

        $zip->close();

        return response()->streamDownload(function () use ($tmpPath) {
            readfile($tmpPath);
            @unlink($tmpPath);
        }, $zipFilename, ['Content-Type' => 'application/zip']);
    }

    private function downloadSsr(Report $report): StreamedResponse
    {
        $assignments = $report->assignments()
            ->where('activity_type', ActivityType::Survey)
            ->with(['site', 'subcontractor', 'surveyData'])
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Site Survey Report');

        $headers = [
            'Site Code', 'Location', 'City', 'Province',
            'Subcontractor', 'Surveyor', 'PIC Location', 'PIC Phone',
            'Charger Type', 'SS Schedule Date', 'Cable Pulling Type',
            'Power (kVA)', 'PLN Network Type', 'Parking Slot', 'Verified At',
        ];

        foreach ($headers as $col => $header) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach ($assignments as $row => $a) {
            $s = $a->surveyData;
            $rowNum = $row + 2;
            $values = [
                $a->site?->site_code,
                $a->site?->location_name,
                $a->site?->city,
                $a->site?->province,
                $a->subcontractor?->name,
                $s?->surveyor_name,
                $s?->pic_location_name,
                $s?->pic_location_phone,
                $s?->charger_type,
                $s?->ss_schedule_date,
                $s?->cable_pulling_type,
                $s?->power_kva,
                $s?->pln_network_type,
                $s?->parking_slot,
                $a->verified_at?->format('Y-m-d H:i'),
            ];

            foreach ($values as $col => $value) {
                $sheet->getCellByColumnAndRow($col + 1, $rowNum)->setValue($value);
            }
        }

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $filename = sprintf('SSR-Report-%s-%s.xlsx', $report->id, now()->format('Ymd'));

        return response()->stream(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function downloadDaily(Report $report): StreamedResponse
    {
        $assignments = $report->assignments()
            ->with([
                'site.project.mainContractor',
                'site.siteType',
                'subcontractor',
                'surveyData',
                'plnData',
                'constructionData',
                'bastData',
            ])
            ->get();

        $siteGroups = $assignments->groupBy('site_id');

        $bssGroups = $siteGroups->filter(function (Collection $group) {
            $siteTypeName = $group->first()?->site?->siteType?->name;

            return $siteTypeName !== 'EVCS';
        });

        $evcsGroups = $siteGroups->filter(function (Collection $group) {
            return $group->first()?->site?->siteType?->name === 'EVCS';
        });

        $spreadsheet = new Spreadsheet;
        $bssSheet = $spreadsheet->getActiveSheet();
        $this->buildDailySheet($bssSheet, 'EPC Report pemasangan-BSS', $bssGroups);

        $evcsSheet = $spreadsheet->createSheet();
        $this->buildDailySheet($evcsSheet, 'EPC Report pemasangan-EVCS', $evcsGroups);

        $filename = sprintf('Daily-Monitoring-%s-%s.xlsx', $report->id, now()->format('Ymd'));

        return response()->stream(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function buildDailySheet(Worksheet $sheet, string $title, Collection $siteGroups): void
    {
        $sheet->setTitle($title);

        $headers = [
            'No', 'EPC Name', 'Charging Type', 'Project Status', 'PLN Status',
            'Project / Location Name', 'Address', 'Google Map URL', 'Province', 'City',
            'BD PIC', 'SS WO Number', 'SS Date (Schedule w/ Landlord)', 'SS Report Submission Date',
            'Cable Length (kWh to Panel)', 'Cable Length (Panel to Charger)',
            'Cable Pulling Type', 'Power (Daya kVA)', 'SSR URL', 'Parking Slot',
            'Charging Station Count', 'Setup Approval Date', 'Cons WO Number',
            'Cons Actual Start Date', 'Cons Actual Done Date', 'NIDI SLO Date Acquired',
            'BPUJL Date Acquired', 'NIDI SLO / BPUJL URL', 'SIK URL', 'Type Rate',
            'kWh Meter PLN Installation Date', 'Machine SN (Serial Number)',
            'ID PELANGGAN (ID PLN)', 'Nomor SIM Card', 'Go LIVE Date (PLN Bypass)',
            'Go LIVE Date (PLN)', 'Latest Remark / Notes',
            'Tanggal Pengajuan Invoice (DP)', 'DP 35% Date',
            'Tanggal Pengajuan Invoice (60%)', '60% Payment Date',
            'Tanggal Pengajuan Invoice (5%)', '5% Payment Date', 'Invoice URL',
        ];

        foreach ($headers as $col => $header) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $rowNum = 2;
        $counter = 1;

        foreach ($siteGroups as $group) {
            /** @var Collection $group */
            $firstAssignment = $group->first();
            $site = $firstAssignment?->site;

            $survey = $group->firstWhere('activity_type', ActivityType::Survey);
            $pln = $group->firstWhere('activity_type', ActivityType::PlnConnection);
            $construction = $group->firstWhere('activity_type', ActivityType::Construction);
            $bast = $group->firstWhere('activity_type', ActivityType::Bast);

            $values = [
                $counter,
                $site?->project?->mainContractor?->name ?? '—',
                $site?->siteType?->name ?? '—',
                $construction?->constructionData?->project_status ?? '—',
                $pln?->plnData?->pln_status ?? '—',
                ($site?->location_name ?? '').' ['.($site?->site_code ?? '').']',
                $site?->address ?? '',
                $site?->google_map_url ?? '',
                $site?->province ?? '',
                $site?->city ?? '',
                $site?->bd_pic ?? '',
                $site?->ss_wo_number ?? '',
                $survey?->surveyData?->ss_schedule_date?->format('d-M-y') ?? '',
                $site?->ss_report_submission_date?->format('d-M-y') ?? '',
                $site?->cable_length_to_panel ?? '',
                $site?->cable_length_panel_to_charger ?? '',
                $survey?->surveyData?->cable_pulling_type ?? '',
                $survey?->surveyData?->power_kva ?? '',
                $site?->ssr_url ?? '',
                $survey?->surveyData?->parking_slot ?? '',
                $site?->charging_station_count ?? '',
                $construction?->constructionData?->setup_approval_date?->format('d-M-y') ?? '',
                $construction?->constructionData?->cons_wo_number ?? '',
                $construction?->constructionData?->cons_actual_start_date?->format('d-M-y') ?? '',
                $construction?->constructionData?->cons_actual_done_date?->format('d-M-y') ?? '',
                $pln?->plnData?->nidi_slo_date_acquired?->format('d-M-y') ?? '',
                $site?->bpujl_date_acquired?->format('d-M-y') ?? '',
                $site?->nidi_slo_bpujl_url ?? '',
                $site?->sik_url ?? '',
                $pln?->plnData?->type_rate ?? '',
                $pln?->plnData?->kwh_meter_installation_date?->format('d-M-y') ?? '',
                $construction?->constructionData?->machine_serial_number ?? '',
                $pln?->plnData?->id_pelanggan ?? '',
                $bast?->bastData?->nomor_simcard ?? '',
                $bast?->bastData?->go_live_date_pln_pass?->format('d-M-y') ?? '',
                $bast?->bastData?->go_live_date_pln?->format('d-M-y') ?? '',
                $site?->latest_remark ?? '',
                $site?->invoice_submission_date?->format('d-M-y') ?? '',
                $site?->dp_35_date?->format('d-M-y') ?? '',
                $site?->invoice_60_submission_date?->format('d-M-y') ?? '',
                $site?->payment_60_date?->format('d-M-y') ?? '',
                $site?->invoice_5_submission_date?->format('d-M-y') ?? '',
                $site?->payment_5_date?->format('d-M-y') ?? '',
                $site?->invoice_url ?? '',
            ];

            foreach ($values as $col => $value) {
                $sheet->getCellByColumnAndRow($col + 1, $rowNum)->setValue($value);
            }

            $rowNum++;
            $counter++;
        }

        $sheet->freezePane('A2');

        foreach (range(1, 44) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
    }
}
