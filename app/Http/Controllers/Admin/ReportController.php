<?php

namespace App\Http\Controllers\Admin;

use App\ActivityFields\BastFields;
use App\ActivityFields\ConstructionFields;
use App\ActivityFields\PlnFields;
use App\ActivityFields\SurveyFields;
use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Assignment;
use App\Models\AssignmentSurveyData;
use App\Models\Report;
use App\Models\User;
use App\Services\BastReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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

        $reportIds = Report::query()
            ->whereHas('assignments.site.project', fn ($query) => $query->whereScopedToMainContractor())
            ->orderByDesc('created_at')
            ->limit(15)
            ->pluck('id');

        $recentReports = DB::table('reports')
            ->select([
                'reports.id',
                'reports.name',
                'reports.report_type',
                'reports.created_at',
                DB::raw('COUNT(report_assignments.assignment_id) as assignments_count'),
            ])
            ->leftJoin('report_assignments', 'reports.id', '=', 'report_assignments.report_id')
            ->whereIn('reports.id', $reportIds)
            ->groupBy('reports.id', 'reports.name', 'reports.report_type', 'reports.created_at')
            ->orderByDesc('reports.created_at')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'report_type' => $row->report_type,
                'assignments_count' => (int) $row->assignments_count,
                'created_at' => $row->created_at,
                'sites' => [],
            ]);

        $reportSites = DB::table('report_assignments')
            ->join('assignments', 'report_assignments.assignment_id', '=', 'assignments.id')
            ->join('sites', 'assignments.site_id', '=', 'sites.id')
            ->whereIn('report_assignments.report_id', $reportIds)
            ->select('report_assignments.report_id', 'sites.site_code', 'sites.location_name', 'assignments.activity_type')
            ->orderBy('sites.site_code')
            ->get()
            ->groupBy('report_id');

        $recentReports = $recentReports->map(fn ($r) => [
            ...$r,
            'sites' => ($reportSites[$r['id']] ?? collect())
                ->map(fn ($s) => [
                    'site_code' => $s->site_code,
                    'location_name' => $s->location_name,
                    'activity_type' => $s->activity_type,
                ])
                ->values()
                ->all(),
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
            'assignment_ids' => ['required_unless:report_type,DAILY', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', 'exists:assignments,id'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        if ($validated['report_type'] === 'DAILY') {
            $assignments = Assignment::query()
                ->where('status', '!=', AssignmentStatus::Drop)
                ->whereHas('site.project', fn ($query) => $query->whereScopedToMainContractor())
                ->get();

            $report = Report::create([
                'name' => 'Daily Report '.now()->format('Y-m-d H:i'),
                'report_type' => 'DAILY',
                'exported_by' => $user->id,
            ]);

            $report->assignments()->attach($assignments->modelKeys());

            return redirect()->route('admin.reports.index')
                ->with('success', 'Daily Report generated successfully.')
                ->with('download_url', route('admin.reports.download', $report));
        }

        $assignmentIds = $validated['assignment_ids'];

        $assignments = Assignment::query()
            ->whereIn('id', $assignmentIds)
            ->whereHas('site.project', fn ($query) => $query->whereScopedToMainContractor())
            ->get();

        abort_unless($assignments->count() === count($assignmentIds), 403, 'One or more assignments are outside your tenant.');

        $verifiedCount = $assignments
            ->where('status', AssignmentStatus::Verified)
            ->count();

        abort_unless($verifiedCount === count($assignmentIds), 422, 'All selected assignments must have VERIFIED status.');

        $allowedActivityType = match ($validated['report_type']) {
            'SSR' => ActivityType::Survey,
            'BAST' => ActivityType::Bast,
            default => null,
        };

        abort_if(
            $allowedActivityType !== null
            && $assignments->contains(fn (Assignment $assignment) => $assignment->activity_type !== $allowedActivityType),
            422,
            'Selected assignments do not match the requested report type.'
        );

        $typeLabel = match ($validated['report_type']) {
            'SSR' => 'SSR Report',
            'BAST' => 'BAST Report',
            default => 'Report',
        };

        $report = Report::create([
            'name' => $typeLabel.' '.now()->format('Y-m-d H:i'),
            'report_type' => $validated['report_type'],
            'exported_by' => $user->id,
        ]);

        $report->assignments()->attach($assignments->modelKeys());

        $assignments->each(fn (Assignment $assignment) => $assignment->markReported());

        if ($validated['report_type'] === 'SSR') {
            $surveyAssignmentIds = $assignments
                ->where('activity_type', ActivityType::Survey)
                ->modelKeys();
            AssignmentSurveyData::whereIn('assignment_id', $surveyAssignmentIds)
                ->update(['ss_report_submission_date' => today()]);
        }

        return redirect()->route('admin.reports.index')
            ->with('success', $typeLabel.' generated successfully.')
            ->with('download_url', route('admin.reports.download', $report));
    }

    public function download(Report $report, BastReportExportService $bastService): StreamedResponse
    {
        $this->ensureCanAccessReport($report);

        return match ($report->report_type) {
            'BAST', 'BAST_EVCS', 'BAST_BSS' => $this->downloadBast($report, $bastService),
            'SSR' => $this->downloadSsr($report),
            default => $this->downloadDaily($report),
        };
    }

    public function regenerate(Report $report): RedirectResponse
    {
        $this->ensureCanAccessReport($report);

        $assignmentIds = $report->assignments()->pluck('assignments.id')->toArray();

        $typeLabel = match ($report->report_type) {
            'SSR' => 'SSR Report',
            'BAST', 'BAST_EVCS', 'BAST_BSS' => 'BAST Report',
            default => 'Daily Report',
        };

        /** @var User $user */
        $user = auth()->user();

        $newReport = Report::create([
            'name' => $typeLabel.' '.now()->format('Y-m-d H:i'),
            'report_type' => $report->report_type,
            'exported_by' => $user->id,
        ]);

        $newReport->assignments()->attach($assignmentIds);

        return redirect()->route('admin.reports.index')
            ->with('success', $typeLabel.' regenerated successfully.')
            ->with('download_url', route('admin.reports.download', $newReport));
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
            ->with(['site.project.mainContractor', 'site.project.client', 'subcontractor', 'surveyData'])
            ->get();

        abort_if($assignments->isEmpty(), 404, 'No survey assignments in this report.');

        if ($assignments->count() === 1) {
            $assignment = $assignments->first();
            $pdf = $this->buildSsrPdf($assignment);
            $filename = sprintf('SSR-%s-%s.pdf', $assignment->site->site_code, now()->format('Ymd'));

            return response()->stream(function () use ($pdf) {
                echo $pdf->output();
            }, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        $zipFilename = sprintf('SSR-Report-%s-%s.zip', $report->id, now()->format('Ymd'));
        $tmpPath = sys_get_temp_dir().'/'.$zipFilename;

        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($assignments as $assignment) {
            $pdf = $this->buildSsrPdf($assignment);
            $pdfName = sprintf('SSR-%s-%s.pdf', $assignment->site->site_code, now()->format('Ymd'));
            $zip->addFromString($pdfName, $pdf->output());
        }

        $zip->close();

        return response()->streamDownload(function () use ($tmpPath) {
            readfile($tmpPath);
            @unlink($tmpPath);
        }, $zipFilename, ['Content-Type' => 'application/zip']);
    }

    private function buildSsrPdf(Assignment $assignment): \Barryvdh\DomPDF\PDF
    {
        $survey = $assignment->surveyData;
        $site = $assignment->site;

        $photoFields = [
            'photo_overall_site', 'photo_parking_evcs', 'photo_access_route',
            'photo_pln_network', 'photo_satellite_gmaps',
        ];

        $photos = collect($photoFields)->map(function (string $field) use ($survey) {
            $path = $survey?->{$field};

            return $path ? self::imageToDataUri(storage_path('app/public/'.$path)) : null;
        })->all();

        $mockupPath = null;
        if ($survey?->file_mockup_3d) {
            $abs = storage_path('app/public/'.$survey->file_mockup_3d);
            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $mockupPath = self::imageToDataUri($abs);
            }
        }

        $sitePlanPath = $survey?->file_site_plan
            ? self::imageToDataUri(storage_path('app/public/'.$survey->file_site_plan))
            : null;

        $baSurveyPath = $survey?->file_ba_survey
            ? self::imageToDataUri(storage_path('app/public/'.$survey->file_ba_survey))
            : null;

        // Left side of PDF header = Client (commissioning organisation, e.g. vGreen)
        $clientName = $site->project?->client?->name ?? 'CLIENT';
        $clientLogoPath = $site->project?->client?->logo;
        $clientLogoAbs = $clientLogoPath
            ? self::imageToDataUri(storage_path('app/public/'.$clientLogoPath))
            : null;

        // Right side of PDF header = Main Contractor / EPC
        $contractorName = $site->project?->mainContractor?->name ?? '';
        $contractorLogoPath = $site->project?->mainContractor?->logo;
        $contractorLogoAbs = $contractorLogoPath
            ? self::imageToDataUri(storage_path('app/public/'.$contractorLogoPath))
            : null;

        $pdfFooterNote = AppSetting::get('pdf.footer_note', '');

        $data = compact(
            'assignment', 'survey', 'site', 'photos', 'mockupPath', 'sitePlanPath', 'baSurveyPath',
            'clientName', 'clientLogoAbs', 'contractorName', 'contractorLogoAbs', 'pdfFooterNote'
        );

        return Pdf::loadView('pdf.site-survey-report', $data)->setPaper('a4', 'portrait');
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

        // Extra columns auto-appended from the field registry for any new fields.
        // Existing daily fields are tagged 'daily' (already hardcoded below);
        // only 'daily_extra' fields are auto-appended here to avoid duplication.
        $extraSurveyFields = SurveyFields::forReport('daily_extra');
        $extraPlnFields = PlnFields::forReport('daily_extra');
        $extraConstructionFields = ConstructionFields::forReport('daily_extra');
        $extraBastFields = BastFields::forReport('daily_extra');

        $hardcodedHeaders = [
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
            'Approved Budget',
            'Tanggal Pengajuan Invoice (DP)', 'DP 35% Date', 'DP 35% Inv Number', 'DP 35% DPP Amount',
            'Tanggal Pengajuan Invoice (60%)', '60% Payment Date', 'Invoice 60% Inv Number', 'Invoice 60% DPP Amount',
            'Tanggal Pengajuan Invoice (5%)', '5% Payment Date', 'Invoice 5% Inv Number', 'Invoice 5% DPP Amount', 'Invoice URL',
        ];

        $extraHeaders = array_merge(
            array_map(fn (array $f) => $f['report_label'] ?? $f['label'], $extraSurveyFields),
            array_map(fn (array $f) => $f['report_label'] ?? $f['label'], $extraPlnFields),
            array_map(fn (array $f) => $f['report_label'] ?? $f['label'], $extraConstructionFields),
            array_map(fn (array $f) => $f['report_label'] ?? $f['label'], $extraBastFields),
        );

        $headers = array_merge($hardcodedHeaders, $extraHeaders);

        foreach ($headers as $col => $header) {
            $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($col + 1).'1');
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

            $hardcodedValues = [
                $counter,
                $site?->project?->mainContractor?->name ?? '—',
                $site?->siteType?->name ?? '—',
                $construction?->constructionData?->project_status ?? '—',
                $pln?->plnData?->pln_status ?? '—',
                $site?->location_name ?? '',
                $site?->address ?? '',
                $site?->google_map_url ?? '',
                $site?->province ?? '',
                $site?->city ?? '',
                $site?->bd_pic ?? '',
                $site?->ss_wo_number ?? '',
                $survey?->surveyData?->ss_schedule_date?->format('d-M-y') ?? '',
                $survey?->surveyData?->ss_report_submission_date?->format('d-M-y') ?? '',
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
                $pln?->plnData?->bpujl_acquired_date?->format('d-M-y') ?? '',
                $site?->nidi_slo_bpujl_url ?? '',
                $site?->sik_url ?? '',
                $pln?->plnData?->type_rate ?? '',
                $pln?->plnData?->kwh_meter_installation_date?->format('d-M-y') ?? '',
                $construction?->constructionData?->machine_serial_number ?? '',
                $pln?->plnData?->id_pelanggan ?? '',
                $bast?->bastData?->nomor_simcard ?? '',
                $construction?->constructionData?->go_live_date_pln_pass?->format('d-M-y') ?? '',
                $construction?->constructionData?->go_live_date_pln?->format('d-M-y') ?? '',
                $site?->latest_remark ?? '',
                $site?->approved_budget ?? '',
                $site?->invoice_submission_date?->format('d-M-y') ?? '',
                $site?->dp_35_date?->format('d-M-y') ?? '',
                $site?->dp_35_inv_number ?? '',
                $site?->dp_35_dpp_amount ?? '',
                $site?->invoice_60_submission_date?->format('d-M-y') ?? '',
                $site?->payment_60_date?->format('d-M-y') ?? '',
                $site?->invoice_60_inv_number ?? '',
                $site?->invoice_60_dpp_amount ?? '',
                $site?->invoice_5_submission_date?->format('d-M-y') ?? '',
                $site?->payment_5_date?->format('d-M-y') ?? '',
                $site?->invoice_5_inv_number ?? '',
                $site?->invoice_5_dpp_amount ?? '',
                $site?->invoice_url ?? '',
            ];

            $extraValues = array_merge(
                array_map(fn (array $f) => $survey?->surveyData?->{$f['key']}, $extraSurveyFields),
                array_map(fn (array $f) => $pln?->plnData?->{$f['key']}, $extraPlnFields),
                array_map(fn (array $f) => $construction?->constructionData?->{$f['key']}, $extraConstructionFields),
                array_map(fn (array $f) => $bast?->bastData?->{$f['key']}, $extraBastFields),
            );

            $values = array_merge($hardcodedValues, $extraValues);

            foreach ($values as $col => $value) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($col + 1).$rowNum)->setValue($value);
            }

            $rowNum++;
            $counter++;
        }

        $sheet->freezePane('A2');

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
    }

    private static function imageToDataUri(string $absPath): ?string
    {
        if (! file_exists($absPath)) {
            return null;
        }
        $mime = mime_content_type($absPath) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($absPath));
    }
}
