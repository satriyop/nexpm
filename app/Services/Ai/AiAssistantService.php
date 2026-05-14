<?php

namespace App\Services\Ai;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AiAssistantService
{
    public function __construct(private readonly DeepSeekClient $client) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array{answer: string, tool_name: string, tool_payload: array<string, mixed>, usage: array<string, mixed>}
     */
    public function answer(User $user, string $message, array $context = []): array
    {
        abort_unless($user->isSuperAdmin(), 403);

        $toolName = $this->selectTool($message);
        $language = $this->detectLanguage($message);
        $toolPayload = $this->runTool($toolName);
        $prompt = $this->buildUserPrompt($message, $toolName, $context);

        try {
            $completion = $this->client->complete($this->systemPrompt(), $prompt, $toolPayload);
            $answer = $completion['content'] !== ''
                ? $completion['content']
                : $this->fallbackAnswer($toolName, $toolPayload, language: $language);

            return [
                'answer' => $answer,
                'tool_name' => $toolName,
                'tool_payload' => $toolPayload,
                'usage' => $completion['usage'],
            ];
        } catch (Throwable $exception) {
            return [
                'answer' => $this->fallbackAnswer($toolName, $toolPayload, true, $language),
                'tool_name' => $toolName,
                'tool_payload' => array_merge($toolPayload, [
                    'ai_provider_error' => $exception->getMessage(),
                ]),
                'usage' => [],
            ];
        }
    }

    private function selectTool(string $message): string
    {
        $normalized = Str::lower($message);

        if (Str::contains($normalized, ['user', 'users', 'pengguna', 'siapa saja', 'daftar user', 'daftar pengguna', 'akun', 'admin', 'superadmin', 'super admin'])) {
            return 'list_users';
        }

        if (Str::contains($normalized, ['risiko proyek', 'project risk', 'project mana', 'proyek mana', 'paling lambat', 'lambat', 'project lambat', 'proyek lambat'])) {
            return 'summarize_project_risks';
        }

        if (Str::contains($normalized, ['prioritas', 'priority', 'tindakan', 'action', 'next action', 'apa yang harus'])) {
            return 'summarize_priority_actions';
        }

        if (Str::contains($normalized, ['subcon', 'sub contractor', 'subcontractor', 'vendor', 'blocker subcon', 'paling banyak blocker'])) {
            return 'summarize_subcontractor_blockers';
        }

        if (Str::contains($normalized, ['report', 'readiness', 'generate', 'verified', 'laporan', 'siap report', 'siap laporan', 'verifikasi'])) {
            return 'check_report_readiness';
        }

        if (Str::contains($normalized, ['dashboard', 'overview', 'summary', 'summarize', 'progress', 'progres', 'ringkas', 'rangkum'])) {
            return 'summarize_dashboard';
        }

        if (Str::contains($normalized, ['blocked', 'stuck', 'late', 'risk', 'risky', 'pending', 'revision', 'telat', 'terlambat', 'macet', 'bermasalah'])) {
            return 'find_blocked_assignments';
        }

        return 'general_help';
    }

    private function detectLanguage(string $message): string
    {
        $normalized = Str::lower($message);

        if (Str::contains($normalized, [
            'apa',
            'siapa',
            'saja',
            'yang',
            'temukan',
            'daftar',
            'pengguna',
            'telat',
            'terlambat',
            'ringkas',
            'rangkum',
            'progres',
            'laporan',
            'siap',
            'bisa',
            'kamu',
            'bantu',
            'sudah',
            'belum',
        ])) {
            return 'id';
        }

        return 'en';
    }

    /**
     * @return array<string, mixed>
     */
    private function runTool(string $toolName): array
    {
        return match ($toolName) {
            'list_users' => $this->listUsers(),
            'summarize_priority_actions' => $this->summarizePriorityActions(),
            'summarize_project_risks' => $this->summarizeProjectRisks(),
            'summarize_subcontractor_blockers' => $this->summarizeSubcontractorBlockers(),
            'check_report_readiness' => $this->checkReportReadiness(),
            'summarize_dashboard' => $this->summarizeDashboard(),
            'general_help' => $this->generalHelp(),
            default => $this->findBlockedAssignments(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function findBlockedAssignments(): array
    {
        $slowThreshold = now()->subDays(7);
        $assignments = Assignment::query()
            ->with(['site.project', 'subcontractor', 'constructionData'])
            ->whereNotIn('status', [
                AssignmentStatus::Drop,
                AssignmentStatus::Verified,
                AssignmentStatus::Reported,
                ...AssignmentStatus::verifiableStatuses(),
            ])
            ->where(function ($query) use ($slowThreshold): void {
                $query
                    ->where('status', AssignmentStatus::Revision)
                    ->orWhere('status', AssignmentStatus::Pending)
                    ->orWhere('updated_at', '<=', $slowThreshold)
                    ->orWhere(function ($query): void {
                        $query
                            ->where('activity_type', ActivityType::Construction)
                            ->where(function ($query): void {
                                $query
                                    ->whereDoesntHave('constructionData')
                                    ->orWhereHas('constructionData', fn ($query) => $query
                                        ->whereNull('cons_wo_number')
                                        ->orWhere('cons_wo_number', ''));
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where('activity_type', ActivityType::Survey)
                            ->whereHas('site', fn ($query) => $query
                                ->whereNull('power_kva')
                                ->orWhere('power_kva', ''));
                    });
            })
            ->latest('updated_at')
            ->limit(30)
            ->get();

        $items = $assignments->map(fn (Assignment $assignment): array => [
            'id' => $assignment->id,
            'site_code' => $assignment->site?->site_code,
            'site_name' => $assignment->site?->location_name,
            'project' => $assignment->site?->project?->name,
            'subcontractor' => $assignment->subcontractor?->name,
            'activity_type' => $assignment->activity_type->value,
            'status' => $assignment->status->value,
            'age_days' => (int) $assignment->updated_at->diffInDays(now()),
            'risk_reason' => $this->assignmentRiskReason($assignment),
            'url' => route('admin.assignments.show', $assignment),
        ])->values();

        return [
            'generated_at' => now()->toIso8601String(),
            'total_risky_assignments' => $items->count(),
            'status_counts' => $this->countBy($items, 'status'),
            'activity_counts' => $this->countBy($items, 'activity_type'),
            'items' => $items->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeDashboard(): array
    {
        $statusCounts = Assignment::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->status->value => (int) $row->total])
            ->all();

        $activityMatrix = Assignment::query()
            ->select('activity_type', 'status', DB::raw('COUNT(*) as total'))
            ->groupBy('activity_type', 'status')
            ->get()
            ->groupBy(fn ($row): string => $row->activity_type->value)
            ->map(fn (Collection $rows): array => $rows
                ->mapWithKeys(fn ($row): array => [$row->status->value => (int) $row->total])
                ->all())
            ->all();

        $projectCounts = DB::table('assignments')
            ->join('sites', 'sites.id', '=', 'assignments.site_id')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->select('projects.id', 'projects.name', DB::raw('COUNT(assignments.id) as assignments_count'))
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('assignments_count')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'assignments_count' => (int) $row->assignments_count,
            ])
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'total_assignments' => array_sum($statusCounts),
            'status_counts' => $statusCounts,
            'activity_matrix' => $activityMatrix,
            'top_projects' => $projectCounts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkReportReadiness(): array
    {
        $readyStatuses = AssignmentStatus::verifiableStatuses();
        $assignments = Assignment::query()
            ->with(['site.project', 'subcontractor'])
            ->whereIn('status', $readyStatuses)
            ->latest('updated_at')
            ->limit(30)
            ->get();

        $items = $assignments->map(fn (Assignment $assignment): array => [
            'id' => $assignment->id,
            'site_code' => $assignment->site?->site_code,
            'site_name' => $assignment->site?->location_name,
            'project' => $assignment->site?->project?->name,
            'subcontractor' => $assignment->subcontractor?->name,
            'activity_type' => $assignment->activity_type->value,
            'status' => $assignment->status->value,
            'url' => route('admin.assignments.show', $assignment),
        ])->values();

        return [
            'generated_at' => now()->toIso8601String(),
            'ready_assignment_count' => $items->count(),
            'status_counts' => $this->countBy($items, 'status'),
            'activity_counts' => $this->countBy($items, 'activity_type'),
            'items' => $items->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeProjectRisks(): array
    {
        $assignments = $this->riskCandidateAssignments();

        $projects = $assignments
            ->groupBy(fn (Assignment $assignment): string => (string) ($assignment->site?->project?->id ?? 0))
            ->map(function (Collection $projectAssignments): array {
                /** @var Assignment $first */
                $first = $projectAssignments->first();

                return [
                    'project_id' => $first->site?->project?->id,
                    'project' => $first->site?->project?->name ?? 'Tanpa project',
                    'risk_score' => $this->riskScore($projectAssignments),
                    'risky_assignments' => $projectAssignments->count(),
                    'oldest_age_days' => (int) $projectAssignments->max(fn (Assignment $assignment): int => (int) $assignment->updated_at->diffInDays(now())),
                    'status_counts' => $this->countAssignmentsBy($projectAssignments, 'status'),
                    'activity_counts' => $this->countAssignmentsBy($projectAssignments, 'activity_type'),
                ];
            })
            ->sortByDesc('risk_score')
            ->take(10)
            ->values();

        return [
            'generated_at' => now()->toIso8601String(),
            'total_projects_with_risk' => $projects->count(),
            'total_risky_assignments' => $assignments->count(),
            'projects' => $projects->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeSubcontractorBlockers(): array
    {
        $assignments = $this->riskCandidateAssignments();

        $subcontractors = $assignments
            ->groupBy(fn (Assignment $assignment): string => (string) ($assignment->subcontractor?->id ?? 0))
            ->map(function (Collection $subconAssignments): array {
                /** @var Assignment $first */
                $first = $subconAssignments->first();

                return [
                    'subcontractor_id' => $first->subcontractor?->id,
                    'subcontractor' => $first->subcontractor?->name ?? 'Tanpa subcon',
                    'blocker_score' => $this->riskScore($subconAssignments),
                    'blocked_assignments' => $subconAssignments->count(),
                    'oldest_age_days' => (int) $subconAssignments->max(fn (Assignment $assignment): int => (int) $assignment->updated_at->diffInDays(now())),
                    'status_counts' => $this->countAssignmentsBy($subconAssignments, 'status'),
                    'activity_counts' => $this->countAssignmentsBy($subconAssignments, 'activity_type'),
                ];
            })
            ->sortByDesc('blocker_score')
            ->take(10)
            ->values();

        return [
            'generated_at' => now()->toIso8601String(),
            'total_subcontractors_with_blockers' => $subcontractors->count(),
            'total_blocked_assignments' => $assignments->count(),
            'subcontractors' => $subcontractors->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizePriorityActions(): array
    {
        $riskAssignments = $this->riskCandidateAssignments()
            ->sortByDesc(fn (Assignment $assignment): int => $this->assignmentRiskScore($assignment))
            ->take(8)
            ->values();

        $reportReady = Assignment::query()
            ->with(['site.project', 'subcontractor'])
            ->whereIn('status', AssignmentStatus::verifiableStatuses())
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $actions = $riskAssignments
            ->map(fn (Assignment $assignment): array => [
                'priority' => $this->assignmentRiskScore($assignment),
                'type' => 'resolve_blocker',
                'assignment_id' => $assignment->id,
                'project' => $assignment->site?->project?->name,
                'site_code' => $assignment->site?->site_code,
                'subcontractor' => $assignment->subcontractor?->name,
                'activity_type' => $assignment->activity_type->value,
                'status' => $assignment->status->value,
                'age_days' => (int) $assignment->updated_at->diffInDays(now()),
                'recommended_action' => $this->recommendedAction($assignment),
                'url' => route('admin.assignments.show', $assignment),
            ]);

        if ($reportReady->isNotEmpty()) {
            $actions->push([
                'priority' => 30,
                'type' => 'prepare_report',
                'assignment_count' => $reportReady->count(),
                'recommended_action' => 'Review assignment siap laporan dan generate report bila dokumen sudah valid.',
                'items' => $reportReady->map(fn (Assignment $assignment): array => [
                    'assignment_id' => $assignment->id,
                    'project' => $assignment->site?->project?->name,
                    'site_code' => $assignment->site?->site_code,
                    'activity_type' => $assignment->activity_type->value,
                    'status' => $assignment->status->value,
                    'url' => route('admin.assignments.show', $assignment),
                ])->values()->all(),
            ]);
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'total_priority_actions' => $actions->count(),
            'risk_action_count' => $riskAssignments->count(),
            'report_ready_count' => $reportReady->count(),
            'actions' => $actions->sortByDesc('priority')->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listUsers(): array
    {
        $users = User::query()
            ->with(['mainContractor:id,name', 'subcontractor:id,name'])
            ->orderBy('role')
            ->orderBy('name')
            ->limit(50)
            ->get();

        $items = $users->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'main_contractor' => $user->mainContractor?->name,
            'subcontractor' => $user->subcontractor?->name,
        ])->values();

        return [
            'generated_at' => now()->toIso8601String(),
            'total_users_returned' => $items->count(),
            'role_counts' => $this->countBy($items, 'role'),
            'items' => $items->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generalHelp(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'supported_tools' => [
                'find_blocked_assignments',
                'summarize_project_risks',
                'summarize_subcontractor_blockers',
                'summarize_priority_actions',
                'summarize_dashboard',
                'check_report_readiness',
            ],
            'examples' => [
                'Apa risiko proyek hari ini?',
                'Project mana yang progress-nya paling lambat?',
                'Subcon mana yang paling banyak blocker?',
                'Apa prioritas tindakan saya hari ini?',
            ],
        ];
    }

    /**
     * @return Collection<int, Assignment>
     */
    private function riskCandidateAssignments(): Collection
    {
        $slowThreshold = now()->subDays(7);

        return Assignment::query()
            ->with(['site.project', 'subcontractor', 'constructionData'])
            ->whereNotIn('status', [
                AssignmentStatus::Drop,
                AssignmentStatus::Verified,
                AssignmentStatus::Reported,
                ...AssignmentStatus::verifiableStatuses(),
            ])
            ->where(function ($query) use ($slowThreshold): void {
                $query
                    ->where('status', AssignmentStatus::Revision)
                    ->orWhere('status', AssignmentStatus::Pending)
                    ->orWhere('updated_at', '<=', $slowThreshold)
                    ->orWhere(function ($query): void {
                        $query
                            ->where('activity_type', ActivityType::Construction)
                            ->where(function ($query): void {
                                $query
                                    ->whereDoesntHave('constructionData')
                                    ->orWhereHas('constructionData', fn ($query) => $query
                                        ->whereNull('cons_wo_number')
                                        ->orWhere('cons_wo_number', ''));
                            });
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where('activity_type', ActivityType::Survey)
                            ->whereHas('site', fn ($query) => $query
                                ->whereNull('power_kva')
                                ->orWhere('power_kva', ''));
                    });
            })
            ->latest('updated_at')
            ->limit(200)
            ->get();
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     */
    private function riskScore(Collection $assignments): int
    {
        return (int) $assignments->sum(fn (Assignment $assignment): int => $this->assignmentRiskScore($assignment));
    }

    private function assignmentRiskScore(Assignment $assignment): int
    {
        $score = 10 + min(30, (int) $assignment->updated_at->diffInDays(now()));

        if ($assignment->status === AssignmentStatus::Revision) {
            $score += 25;
        }

        if ($assignment->status === AssignmentStatus::Pending) {
            $score += 10;
        }

        if ($assignment->activity_type === ActivityType::Construction && $assignment->isLocked()) {
            $score += 20;
        }

        if ($assignment->activity_type === ActivityType::Survey && blank($assignment->site?->power_kva)) {
            $score += 15;
        }

        return $score;
    }

    private function recommendedAction(Assignment $assignment): string
    {
        if ($assignment->status === AssignmentStatus::Revision) {
            return 'Follow up revisi dengan PIC terkait dan pastikan komentar revisi ditutup.';
        }

        if ($assignment->activity_type === ActivityType::Construction && $assignment->isLocked()) {
            return 'Lengkapi WO number agar subcon bisa melanjutkan pekerjaan konstruksi.';
        }

        if ($assignment->activity_type === ActivityType::Survey && blank($assignment->site?->power_kva)) {
            return 'Lengkapi data daya site agar dokumen survey bisa selesai.';
        }

        if ($assignment->status === AssignmentStatus::Pending) {
            return 'Follow up owner pekerjaan karena assignment masih pending.';
        }

        return 'Cek update terbaru dan minta PIC memberikan progress.';
    }

    private function assignmentRiskReason(Assignment $assignment): string
    {
        if ($assignment->status === AssignmentStatus::Revision) {
            return 'Needs revision response before review can continue.';
        }

        if ($assignment->status === AssignmentStatus::Pending) {
            return 'Still pending and not yet completed by the assigned party.';
        }

        if ($assignment->activity_type === ActivityType::Construction && $assignment->isLocked()) {
            return 'Construction is locked until the WO prerequisite is filled.';
        }

        if ($assignment->activity_type === ActivityType::Survey && blank($assignment->site?->power_kva)) {
            return 'Survey site power is missing, which blocks a complete survey document.';
        }

        return 'No progress update for more than 7 days.';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, int>
     */
    private function countBy(Collection $items, string $key): array
    {
        return $items
            ->countBy(fn (array $item): string => (string) $item[$key])
            ->sortKeys()
            ->all();
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     * @return array<string, int>
     */
    private function countAssignmentsBy(Collection $assignments, string $key): array
    {
        return $assignments
            ->countBy(fn (Assignment $assignment): string => match ($key) {
                'activity_type' => $assignment->activity_type->value,
                'status' => $assignment->status->value,
                default => '',
            })
            ->sortKeys()
            ->all();
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are NexPM's read-only project management assistant for super admins.
Use only the supplied application data. Do not claim that you changed records, sent messages, generated reports, or updated workflow state.
Answer concisely with concrete project risks, blockers, counts, and recommended next actions. If the data is insufficient, say what is missing.
Reply in the same language as the user's question.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildUserPrompt(string $message, string $toolName, array $context): string
    {
        return 'User question: '.$message.PHP_EOL
            .'Selected read-only tool: '.$toolName.PHP_EOL
            .'UI context: '.json_encode($context, JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $toolPayload
     */
    private function fallbackAnswer(
        string $toolName,
        array $toolPayload,
        bool $providerUnavailable = false,
        string $language = 'en',
    ): string {
        if ($language === 'id') {
            return $this->fallbackAnswerInIndonesian($toolName, $toolPayload, $providerUnavailable);
        }

        $prefix = $providerUnavailable
            ? 'AI provider is not configured or reachable, so this is a local NexPM summary. '
            : '';

        return $prefix.match ($toolName) {
            'list_users' => sprintf(
                'Users found: %d. Role split: %s.',
                (int) $toolPayload['total_users_returned'],
                $this->formatCounts($toolPayload['role_counts'] ?? [])
            ),
            'general_help' => 'I can help with project risks, late assignments, subcontractor blockers, report readiness, and PM priority actions.',
            'summarize_priority_actions' => sprintf(
                'Priority actions: %d items need attention. Blocker actions: %d. Report-ready items: %d.',
                (int) $toolPayload['total_priority_actions'],
                (int) $toolPayload['risk_action_count'],
                (int) $toolPayload['report_ready_count'],
            ),
            'summarize_project_risks' => sprintf(
                'Project risk summary: %d projects have risk across %d assignments.',
                (int) $toolPayload['total_projects_with_risk'],
                (int) $toolPayload['total_risky_assignments'],
            ),
            'summarize_subcontractor_blockers' => sprintf(
                'Subcontractor blockers: %d subcontractors have blockers across %d assignments.',
                (int) $toolPayload['total_subcontractors_with_blockers'],
                (int) $toolPayload['total_blocked_assignments'],
            ),
            'check_report_readiness' => sprintf(
                'Report readiness: %d assignments are currently in report-ready statuses. Top activity split: %s.',
                (int) $toolPayload['ready_assignment_count'],
                $this->formatCounts($toolPayload['activity_counts'] ?? [])
            ),
            'summarize_dashboard' => sprintf(
                'Dashboard summary: %d assignments found. Status split: %s.',
                (int) $toolPayload['total_assignments'],
                $this->formatCounts($toolPayload['status_counts'] ?? [])
            ),
            default => sprintf(
                'Blocked assignment scan: %d risky assignments found. Status split: %s.',
                (int) $toolPayload['total_risky_assignments'],
                $this->formatCounts($toolPayload['status_counts'] ?? [])
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $toolPayload
     */
    private function fallbackAnswerInIndonesian(string $toolName, array $toolPayload, bool $providerUnavailable): string
    {
        $prefix = $providerUnavailable
            ? 'AI provider belum dikonfigurasi atau tidak dapat dihubungi, jadi ini ringkasan lokal NexPM. '
            : '';

        return $prefix.match ($toolName) {
            'list_users' => sprintf(
                'Ditemukan %d user. Pembagian role: %s.',
                (int) $toolPayload['total_users_returned'],
                $this->formatCounts($toolPayload['role_counts'] ?? [], 'id')
            ),
            'general_help' => 'Saya bisa membantu melihat risiko proyek, assignment telat, blocker subcon, kesiapan laporan, dan prioritas tindakan PM hari ini.',
            'summarize_priority_actions' => sprintf(
                'Prioritas tindakan: ada %d item yang perlu perhatian. Tindakan blocker: %d. Assignment siap laporan: %d.',
                (int) $toolPayload['total_priority_actions'],
                (int) $toolPayload['risk_action_count'],
                (int) $toolPayload['report_ready_count'],
            ),
            'summarize_project_risks' => sprintf(
                'Ringkasan risiko proyek: %d project memiliki risiko dari total %d assignment berisiko.',
                (int) $toolPayload['total_projects_with_risk'],
                (int) $toolPayload['total_risky_assignments'],
            ),
            'summarize_subcontractor_blockers' => sprintf(
                'Blocker subcon: %d subcon memiliki blocker dari total %d assignment.',
                (int) $toolPayload['total_subcontractors_with_blockers'],
                (int) $toolPayload['total_blocked_assignments'],
            ),
            'check_report_readiness' => sprintf(
                'Kesiapan laporan: %d assignment sedang berada pada status siap laporan. Pembagian activity: %s.',
                (int) $toolPayload['ready_assignment_count'],
                $this->formatCounts($toolPayload['activity_counts'] ?? [], 'id')
            ),
            'summarize_dashboard' => sprintf(
                'Ringkasan dashboard: ditemukan %d assignment. Pembagian status: %s.',
                (int) $toolPayload['total_assignments'],
                $this->formatCounts($toolPayload['status_counts'] ?? [], 'id')
            ),
            default => sprintf(
                'Pemeriksaan assignment bermasalah: ditemukan %d assignment berisiko. Pembagian status: %s.',
                (int) $toolPayload['total_risky_assignments'],
                $this->formatCounts($toolPayload['status_counts'] ?? [], 'id')
            ),
        };
    }

    /**
     * @param  mixed  $counts
     */
    private function formatCounts($counts, string $language = 'en'): string
    {
        if (! is_array($counts) || $counts === []) {
            return $language === 'id' ? 'tidak ada' : 'none';
        }

        return collect($counts)
            ->map(fn (int $count, string $label): string => "{$label}: {$count}")
            ->implode(', ');
    }
}
