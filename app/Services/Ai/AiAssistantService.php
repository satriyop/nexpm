<?php

namespace App\Services\Ai;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiAssistantService
{
    /**
     * Select a tool based on keyword matching (used for local fallback).
     *
     * @param  array<string, mixed>  $context
     */
    public function selectTool(string $message, array $context): string
    {
        $normalized = Str::lower($message);

        if (Str::contains($normalized, ['user', 'users', 'pengguna', 'siapa saja', 'daftar user', 'daftar pengguna', 'akun', 'admin', 'superadmin', 'super admin'])) {
            return 'list_users';
        }

        if (Str::contains($normalized, ['briefing', 'brief', 'kabar proyek', 'kondisi proyek', 'health', 'health briefing'])) {
            return 'project_health_briefing';
        }

        if (Str::contains($normalized, ['gap', 'workflow gap', 'gap workflow', 'inkonsisten', 'inconsistent', 'missing field', 'data kurang', 'belum lengkap', 'tidak lengkap'])) {
            return 'detect_workflow_gaps';
        }

        if ($this->hasRecordContext($context) && Str::contains($normalized, ['halaman ini', 'record ini', 'assignment ini', 'site ini', 'project ini', 'proyek ini', 'apa masalah', 'masalahnya', 'statusnya', 'apa status'])) {
            return 'contextual_page_summary';
        }

        if (Str::contains($normalized, ['risiko proyek', 'risiko terbesar', 'project risk', 'project mana', 'proyek mana', 'paling lambat', 'lambat', 'project lambat', 'proyek lambat'])) {
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

    /**
     * Run a tool by name and return its payload (used for local fallback).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function runTool(string $toolName, array $context): array
    {
        return match ($toolName) {
            'list_users' => $this->listUsers(),
            'contextual_page_summary' => $this->contextualPageSummary($context),
            'detect_workflow_gaps' => $this->detectWorkflowGaps($context),
            'project_health_briefing' => $this->projectHealthBriefing($context),
            'summarize_priority_actions' => $this->summarizePriorityActions($context),
            'summarize_project_risks' => $this->summarizeProjectRisks($context),
            'summarize_subcontractor_blockers' => $this->summarizeSubcontractorBlockers($context),
            'check_report_readiness' => $this->checkReportReadiness($context),
            'summarize_dashboard' => $this->summarizeDashboard($context),
            'general_help' => $this->generalHelp(),
            default => $this->findBlockedAssignments($context),
        };
    }

    /**
     * Generate a local fallback answer when the AI provider is unavailable.
     *
     * @param  array<string, mixed>  $toolPayload
     */
    public function fallbackAnswer(string $toolName, array $toolPayload, string $language = 'en'): string
    {
        if ($language === 'id') {
            return $this->fallbackAnswerInIndonesian($toolName, $toolPayload);
        }

        $prefix = 'AI provider is not configured or reachable, so this is a local NexPM summary. ';

        return $prefix.match ($toolName) {
            'list_users' => sprintf(
                'Users found: %d. Role split: %s.',
                (int) $toolPayload['total_users_returned'],
                $this->formatCounts($toolPayload['role_counts'] ?? [])
            ),
            'general_help' => 'I can help with project risks, late assignments, subcontractor blockers, report readiness, and PM priority actions.',
            'contextual_page_summary' => sprintf(
                'Context summary: %d workflow gaps found for this page.',
                count($toolPayload['gaps'] ?? [])
            ),
            'detect_workflow_gaps' => sprintf(
                'Workflow gap scan: %d gaps found. Gap split: %s.',
                (int) $toolPayload['total_gaps'],
                $this->formatCounts($toolPayload['gap_type_counts'] ?? [])
            ),
            'project_health_briefing' => sprintf(
                'Project health briefing: %d risky assignments, %d report-ready assignments, and %d workflow gaps need attention.',
                (int) data_get($toolPayload, 'project_risks.total_risky_assignments', 0),
                (int) data_get($toolPayload, 'report_readiness.ready_assignment_count', 0),
                (int) data_get($toolPayload, 'workflow_gaps.total_gaps', 0),
            ),
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
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function findBlockedAssignments(array $context = []): array
    {
        $slowThreshold = now()->subDays(7);
        $assignments = Assignment::query()
            ->with(['site.project', 'subcontractor', 'constructionData'])
            ->tap(fn ($query) => $this->applyAssignmentContext($query, $context))
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
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function summarizeDashboard(array $context = []): array
    {
        $statusCounts = Assignment::query()
            ->tap(fn ($query) => $this->applyAssignmentContext($query, $context))
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->status->value => (int) $row->total])
            ->all();

        $activityMatrix = Assignment::query()
            ->tap(fn ($query) => $this->applyAssignmentContext($query, $context))
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
            ->when($this->contextAssignmentId($context), fn ($query, int $id) => $query->where('assignments.id', $id))
            ->when($this->contextSiteId($context), fn ($query, int $id) => $query->where('sites.id', $id))
            ->when($this->contextProjectId($context), fn ($query, int $id) => $query->where('projects.id', $id))
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
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function checkReportReadiness(array $context = []): array
    {
        $readyStatuses = AssignmentStatus::verifiableStatuses();
        $assignments = Assignment::query()
            ->with(['site.project', 'subcontractor'])
            ->tap(fn ($query) => $this->applyAssignmentContext($query, $context))
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
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function summarizeProjectRisks(array $context = []): array
    {
        $assignments = $this->riskCandidateAssignments($context);

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
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function summarizeSubcontractorBlockers(array $context = []): array
    {
        $assignments = $this->riskCandidateAssignments($context);

        $subcontractors = $assignments
            ->groupBy(fn (Assignment $assignment): string => (string) ($assignment->subcontractor?->id ?? 0))
            ->map(function (Collection $subconAssignments): array {
                /** @var Assignment $first */
                $first = $subconAssignments->first();

                $topAssignments = $subconAssignments
                    ->sortByDesc(fn (Assignment $a): int => $this->assignmentRiskScore($a))
                    ->take(15)
                    ->map(fn (Assignment $a): array => [
                        'id' => $a->id,
                        'site_code' => $a->site?->site_code,
                        'site_name' => $a->site?->location_name,
                        'project' => $a->site?->project?->name,
                        'activity_type' => $a->activity_type->value,
                        'status' => $a->status->value,
                        'age_days' => (int) $a->updated_at->diffInDays(now()),
                        'url' => route('admin.assignments.show', $a),
                    ])
                    ->values()
                    ->all();

                return [
                    'subcontractor_id' => $first->subcontractor?->id,
                    'subcontractor' => $first->subcontractor?->name ?? 'Tanpa subcon',
                    'blocker_score' => $this->riskScore($subconAssignments),
                    'blocked_assignments' => $subconAssignments->count(),
                    'oldest_age_days' => (int) $subconAssignments->max(fn (Assignment $assignment): int => (int) $assignment->updated_at->diffInDays(now())),
                    'status_counts' => $this->countAssignmentsBy($subconAssignments, 'status'),
                    'activity_counts' => $this->countAssignmentsBy($subconAssignments, 'activity_type'),
                    'top_assignments' => $topAssignments,
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
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function summarizePriorityActions(array $context = []): array
    {
        $riskAssignments = $this->riskCandidateAssignments($context)
            ->sortByDesc(fn (Assignment $assignment): int => $this->assignmentRiskScore($assignment))
            ->take(8)
            ->values();

        $reportReady = Assignment::query()
            ->with(['site.project', 'subcontractor'])
            ->tap(fn ($query) => $this->applyAssignmentContext($query, $context))
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
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function projectHealthBriefing(array $context = []): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'context' => $this->normalizedContext($context),
            'project_risks' => $this->summarizeProjectRisks($context),
            'subcontractor_blockers' => $this->summarizeSubcontractorBlockers($context),
            'report_readiness' => $this->checkReportReadiness($context),
            'priority_actions' => $this->summarizePriorityActions($context),
            'workflow_gaps' => $this->detectWorkflowGaps($context),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function contextualPageSummary(array $context): array
    {
        if ($assignmentId = $this->contextAssignmentId($context)) {
            $assignment = Assignment::query()
                ->with([
                    'site.project',
                    'subcontractor',
                    'surveyData',
                    'plnData',
                    'constructionData',
                    'bastData.bastPhotos',
                ])
                ->find($assignmentId);

            return [
                'generated_at' => now()->toIso8601String(),
                'context' => $this->normalizedContext($context),
                'assignment' => $assignment ? $this->assignmentSummary($assignment) : null,
                'gaps' => $assignment ? $this->assignmentWorkflowGaps($assignment) : [],
                'next_action' => $assignment ? $this->recommendedAction($assignment) : 'Assignment tidak ditemukan.',
            ];
        }

        if ($siteId = $this->contextSiteId($context)) {
            $assignments = Assignment::query()
                ->with(['site.project', 'subcontractor', 'surveyData', 'plnData', 'constructionData', 'bastData.bastPhotos'])
                ->where('site_id', $siteId)
                ->get();

            return [
                'generated_at' => now()->toIso8601String(),
                'context' => $this->normalizedContext($context),
                'assignment_count' => $assignments->count(),
                'status_counts' => $this->countAssignmentsBy($assignments, 'status'),
                'activity_counts' => $this->countAssignmentsBy($assignments, 'activity_type'),
                'gaps' => $this->workflowGapsForAssignments($assignments)->take(20)->values()->all(),
                'priority_actions' => $assignments
                    ->sortByDesc(fn (Assignment $assignment): int => $this->assignmentRiskScore($assignment))
                    ->take(5)
                    ->map(fn (Assignment $assignment): array => [
                        'assignment' => $this->assignmentSummary($assignment),
                        'recommended_action' => $this->recommendedAction($assignment),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $this->projectHealthBriefing($context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function detectWorkflowGaps(array $context = []): array
    {
        $assignments = Assignment::query()
            ->with(['site.project', 'subcontractor', 'surveyData', 'plnData', 'constructionData', 'bastData.bastPhotos'])
            ->tap(fn ($query) => $this->applyAssignmentContext($query, $context))
            ->whereNotIn('status', [AssignmentStatus::Drop, AssignmentStatus::Reported])
            ->latest('updated_at')
            ->limit(300)
            ->get();

        $gaps = $this->workflowGapsForAssignments($assignments);

        return [
            'generated_at' => now()->toIso8601String(),
            'context' => $this->normalizedContext($context),
            'total_gaps' => $gaps->count(),
            'gap_type_counts' => $gaps->countBy('type')->sortKeys()->all(),
            'items' => $gaps->take(50)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listUsers(): array
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
    public function generalHelp(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'supported_tools' => [
                'project_health_briefing',
                'detect_workflow_gaps',
                'contextual_page_summary',
                'find_blocked_assignments',
                'summarize_project_risks',
                'summarize_subcontractor_blockers',
                'summarize_priority_actions',
                'summarize_dashboard',
                'check_report_readiness',
            ],
            'examples' => [
                'Briefing proyek hari ini',
                'Cek gap workflow',
                'Assignment ini apa masalahnya?',
                'Apa prioritas tindakan saya hari ini?',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return Collection<int, Assignment>
     */
    public function riskCandidateAssignments(array $context = []): Collection
    {
        $slowThreshold = now()->subDays(7);

        return Assignment::query()
            ->with(['site.project', 'subcontractor', 'constructionData'])
            ->tap(fn ($query) => $this->applyAssignmentContext($query, $context))
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

    public function assignmentRiskScore(Assignment $assignment): int
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

    public function recommendedAction(Assignment $assignment): string
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

    /**
     * @param  Collection<int, Assignment>  $assignments
     * @return Collection<int, array<string, mixed>>
     */
    public function workflowGapsForAssignments(Collection $assignments): Collection
    {
        return $assignments
            ->flatMap(fn (Assignment $assignment): array => $this->assignmentWorkflowGaps($assignment))
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function assignmentWorkflowGaps(Assignment $assignment): array
    {
        $gaps = [];

        if (
            $assignment->activity_type === ActivityType::Survey
            && ($assignment->surveyData?->isComplete() ?? false)
            && $assignment->status !== AssignmentStatus::Document
            && ! in_array($assignment->status, AssignmentStatus::adminLocked(), true)
        ) {
            $gaps[] = $this->gapItem($assignment, 'survey_complete_status_mismatch', 'Survey lengkap tetapi status belum DOCUMENT.', 'Sinkronkan status survey menjadi DOCUMENT atau cek observer status.');
        }

        if ($assignment->activity_type === ActivityType::Construction && $assignment->isLocked()) {
            $gaps[] = $this->gapItem($assignment, 'construction_missing_wo', 'Construction terkunci karena WO number belum diisi.', 'Lengkapi WO number dari admin agar subcon bisa lanjut.');
        }

        if ($assignment->activity_type === ActivityType::Bast) {
            $missing = $this->missingBastFields($assignment);

            if ($missing !== []) {
                $gaps[] = $this->gapItem($assignment, 'bast_missing_data', 'BAST belum lengkap: '.implode(', ', $missing).'.', 'Lengkapi data dan foto BAST sebelum submit/verifikasi.');
            }
        }

        if ($assignment->status === AssignmentStatus::Verified) {
            $gaps[] = $this->gapItem($assignment, 'verified_not_reported', 'Assignment sudah VERIFIED tetapi belum masuk report.', 'Masukkan assignment ke proses generate report.');
        }

        if (
            in_array($assignment->status, [AssignmentStatus::Pending, AssignmentStatus::Revision], true)
            && $assignment->updated_at->lte(now()->subDays(7))
        ) {
            $gaps[] = $this->gapItem($assignment, 'stale_pending_revision', 'Assignment pending/revision lebih dari 7 hari.', 'Follow up PIC/subcon dan update progress terbaru.');
        }

        if ($assignment->activity_type === ActivityType::Survey && blank($assignment->site?->power_kva)) {
            $gaps[] = $this->gapItem($assignment, 'site_missing_power', 'Data power_kva site kosong.', 'Lengkapi daya site agar dokumen survey/report tidak tertahan.');
        }

        return $gaps;
    }

    /**
     * @return array<string, mixed>
     */
    private function gapItem(Assignment $assignment, string $type, string $reason, string $recommendedAction): array
    {
        return [
            'type' => $type,
            'reason' => $reason,
            'recommended_action' => $recommendedAction,
            'assignment' => $this->assignmentSummary($assignment),
        ];
    }

    /**
     * @return list<string>
     */
    private function missingBastFields(Assignment $assignment): array
    {
        $bast = $assignment->bastData;
        $missing = [];

        foreach (['sim_provider', 'nomor_simcard', 'commissioning_date'] as $field) {
            if (blank($bast?->{$field})) {
                $missing[] = $field;
            }
        }

        if (($bast?->bastPhotos?->count() ?? 0) === 0) {
            $missing[] = 'bast_photos';
        }

        return $missing;
    }

    /**
     * @return array<string, mixed>
     */
    public function assignmentSummary(Assignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'project' => $assignment->site?->project?->name,
            'site_id' => $assignment->site_id,
            'site_code' => $assignment->site?->site_code,
            'site_name' => $assignment->site?->location_name,
            'subcontractor' => $assignment->subcontractor?->name,
            'activity_type' => $assignment->activity_type->value,
            'status' => $assignment->status->value,
            'age_days' => (int) $assignment->updated_at->diffInDays(now()),
            'url' => route('admin.assignments.show', $assignment),
        ];
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
    public function countAssignmentsBy(Collection $assignments, string $key): array
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

    /**
     * @param  mixed  $query
     * @param  array<string, mixed>  $context
     */
    public function applyAssignmentContext($query, array $context): void
    {
        if ($assignmentId = $this->contextAssignmentId($context)) {
            $query->where('assignments.id', $assignmentId);

            return;
        }

        if ($siteId = $this->contextSiteId($context)) {
            $query->where('site_id', $siteId);

            return;
        }

        if ($projectId = $this->contextProjectId($context)) {
            $query->whereHas('site', fn ($query) => $query->where('project_id', $projectId));
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function hasRecordContext(array $context): bool
    {
        return $this->contextAssignmentId($context) !== null
            || $this->contextSiteId($context) !== null
            || $this->contextProjectId($context) !== null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function contextAssignmentId(array $context): ?int
    {
        if ($id = $this->contextInteger($context, 'assignment_id')) {
            return $id;
        }

        return ($context['type'] ?? null) === 'assignment' ? $this->contextInteger($context, 'id') : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function contextSiteId(array $context): ?int
    {
        if ($id = $this->contextInteger($context, 'site_id')) {
            return $id;
        }

        return ($context['type'] ?? null) === 'site' ? $this->contextInteger($context, 'id') : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function contextProjectId(array $context): ?int
    {
        if ($id = $this->contextInteger($context, 'project_id')) {
            return $id;
        }

        return ($context['type'] ?? null) === 'project' ? $this->contextInteger($context, 'id') : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextInteger(array $context, string $key): ?int
    {
        $value = $context[$key] ?? null;

        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function normalizedContext(array $context): array
    {
        return [
            'type' => $context['type'] ?? 'page',
            'id' => $context['id'] ?? null,
            'project_id' => $this->contextProjectId($context),
            'site_id' => $this->contextSiteId($context),
            'assignment_id' => $this->contextAssignmentId($context),
            'label' => $context['label'] ?? null,
            'url' => $context['url'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $toolPayload
     */
    private function fallbackAnswerInIndonesian(string $toolName, array $toolPayload): string
    {
        $prefix = 'AI provider belum dikonfigurasi atau tidak dapat dihubungi, jadi ini ringkasan lokal NexPM. ';

        return $prefix.match ($toolName) {
            'list_users' => sprintf(
                'Ditemukan %d user. Pembagian role: %s.',
                (int) $toolPayload['total_users_returned'],
                $this->formatCounts($toolPayload['role_counts'] ?? [], 'id')
            ),
            'general_help' => 'Saya bisa membantu melihat risiko proyek, assignment telat, blocker subcon, kesiapan laporan, dan prioritas tindakan PM hari ini.',
            'contextual_page_summary' => sprintf(
                'Ringkasan konteks: ditemukan %d gap workflow untuk halaman ini.',
                count($toolPayload['gaps'] ?? [])
            ),
            'detect_workflow_gaps' => sprintf(
                'Pemeriksaan gap workflow: ditemukan %d gap. Pembagian gap: %s.',
                (int) $toolPayload['total_gaps'],
                $this->formatCounts($toolPayload['gap_type_counts'] ?? [], 'id')
            ),
            'project_health_briefing' => sprintf(
                'Briefing proyek: ada %d assignment berisiko, %d assignment siap laporan, dan %d gap workflow yang perlu perhatian.',
                (int) data_get($toolPayload, 'project_risks.total_risky_assignments', 0),
                (int) data_get($toolPayload, 'report_readiness.ready_assignment_count', 0),
                (int) data_get($toolPayload, 'workflow_gaps.total_gaps', 0),
            ),
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
