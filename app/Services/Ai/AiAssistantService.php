<?php

namespace App\Services\Ai;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\Site;
use App\Models\Subcontractor;
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

        if (Str::contains($normalized, ['reminder', 'ingatkan', 'buatkan reminder', 'kirim reminder'])) {
            return 'generate_subcontractor_reminder';
        }

        if (Str::contains($normalized, ['summary', 'summarize', 'ringkas', 'rangkum', 'recap', 'rekap', 'rekapan'])
            && Str::contains($normalized, ['assignment', 'survey', 'pln', 'construction', 'bast', 'outstanding'])) {
            return 'summarize_assignment_operations';
        }

        if (Str::contains($normalized, ['outstanding', 'tunggakan', 'belum selesai'])
            && Str::contains($normalized, ['subcon', 'subkon', 'subcontractor', 'vendor', 'user'])) {
            return 'summarize_assignment_operations';
        }

        if (Str::contains($normalized, ['berapa lokasi', 'berapa site', 'berapa assignment', 'how many location', 'how many site', 'how many assign', 'how many', 'jumlah lokasi', 'jumlah site', 'jumlah assignment', 'ada berapa'])
            && Str::contains($normalized, ['lokasi', 'location', 'site', 'assignment', 'assign'])) {
            return 'query_entity_stats';
        }

        if (Str::contains($normalized, ['user', 'users', 'pengguna', 'siapa saja', 'daftar user', 'daftar pengguna', 'akun', 'admin', 'superadmin', 'super admin'])) {
            return 'list_users';
        }

        if (Str::contains($normalized, ['briefing', 'brief', 'kabar proyek', 'kondisi proyek', 'health', 'health briefing'])) {
            return 'project_health_briefing';
        }

        if (Str::contains($normalized, ['gap', 'workflow gap', 'gap workflow', 'inkonsisten', 'inconsistent', 'missing field', 'data kurang', 'belum lengkap', 'tidak lengkap'])) {
            return 'detect_workflow_gaps';
        }

        if (Str::contains($normalized, ['arti', 'maksud', 'alur', 'workflow', 'flow', 'status', 'document', 'verified', 'reported', 'field wajib', 'wajib diisi', 'required field', 'siapa yang input', 'siapa input'])) {
            return 'workflow_knowledge';
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
            'workflow_knowledge' => $this->workflowKnowledge(),
            'resolve_entity_context' => $this->resolveEntityContext($context['query'] ?? '', $context),
            'query_entity_stats' => $this->queryEntityStats($this->statsFiltersFromContext($context), $context),
            'summarize_assignment_operations' => $this->summarizeAssignmentOperations($this->operationFiltersFromContext($context), $context),
            'generate_subcontractor_reminder' => $this->generateSubcontractorReminder($context['query'] ?? '', $context),
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
    public function fallbackAnswer(string $toolName, array $toolPayload, string $language = 'en', bool $includeProviderPrefix = true): string
    {
        if ($language === 'id') {
            return $this->fallbackAnswerInIndonesian($toolName, $toolPayload, $includeProviderPrefix);
        }

        $prefix = $includeProviderPrefix
            ? 'AI provider is not configured or reachable, so this is a local NexPM summary. '
            : '';

        return $prefix.match ($toolName) {
            'list_users' => sprintf(
                'Users found: %d. Role split: %s.',
                (int) $toolPayload['total_users_returned'],
                $this->formatCounts($toolPayload['role_counts'] ?? [])
            ),
            'general_help' => 'I can help with project risks, late assignments, subcontractor blockers, report readiness, and PM priority actions.',
            'workflow_knowledge' => 'Workflow knowledge: survey completion moves eligible survey assignments to DOCUMENT, DOCUMENT assignments are ready for report preparation, VERIFIED means admin review is complete, and REPORTED means the assignment has been included in a generated report.',
            'resolve_entity_context' => sprintf(
                'Entity lookup: %d project matches, %d site matches, %d subcontractor matches, %d subcontractor user matches, %d main contractor matches, and %d machine type matches found.',
                count($toolPayload['projects'] ?? []),
                count($toolPayload['sites'] ?? []),
                count($toolPayload['subcontractors'] ?? []),
                count($toolPayload['subcontractor_users'] ?? []),
                count($toolPayload['main_contractors'] ?? []),
                count($toolPayload['machine_types'] ?? []),
            ),
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
            'query_entity_stats' => sprintf(
                'Entity stats: %d %s found%s.',
                (int) ($toolPayload['total_count'] ?? 0),
                $toolPayload['count_target'] ?? 'records',
                isset($toolPayload['filter_project']) ? ' for project '.$toolPayload['filter_project'] : ''
            ),
            'summarize_assignment_operations' => sprintf(
                'Assignment operations recap: %d assignments found. Status split: %s. Activity split: %s.',
                (int) ($toolPayload['total_count'] ?? 0),
                $this->formatCounts($toolPayload['status_breakdown'] ?? []),
                $this->formatCounts($toolPayload['activity_breakdown'] ?? []),
            ),
            'generate_subcontractor_reminder' => sprintf(
                'Subcontractor reminder: %s has %d outstanding assignments across %d projects.',
                $toolPayload['subcontractor'] ?? 'Unknown',
                (int) ($toolPayload['outstanding_count'] ?? 0),
                (int) ($toolPayload['projects_count'] ?? 0),
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
    public function summarizeSubcontractorBlockers(array $context = [], ?string $subcontractorName = null): array
    {
        if ($subcontractorName !== null) {
            return $this->subcontractorProfile($context, $subcontractorName);
        }

        $assignments = $this->riskCandidateAssignments($context);

        $subcontractorIds = $assignments
            ->pluck('subcontractor_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Total assignment counts per subcontractor across ALL statuses (not just blocked)
        $totalCounts = DB::table('assignments')
            ->whereIn('subcontractor_id', $subcontractorIds)
            ->selectRaw('subcontractor_id, count(*) as total, status')
            ->groupBy('subcontractor_id', 'status')
            ->get()
            ->groupBy('subcontractor_id')
            ->map(fn ($rows) => [
                'total' => $rows->sum('total'),
                'all_status_counts' => $rows->pluck('total', 'status')->all(),
            ]);

        $subcontractors = $assignments
            ->groupBy(fn (Assignment $assignment): string => (string) ($assignment->subcontractor?->id ?? 0))
            ->map(function (Collection $subconAssignments) use ($totalCounts): array {
                /** @var Assignment $first */
                $first = $subconAssignments->first();
                $subconId = (string) ($first->subcontractor?->id ?? 0);
                $totals = $totalCounts->get($subconId);

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
                    'total_assignments' => $totals['total'] ?? $subconAssignments->count(),
                    'blocked_assignments' => $subconAssignments->count(),
                    'oldest_age_days' => (int) $subconAssignments->max(fn (Assignment $assignment): int => (int) $assignment->updated_at->diffInDays(now())),
                    'blocked_status_counts' => $this->countAssignmentsBy($subconAssignments, 'status'),
                    'all_status_counts' => $totals['all_status_counts'] ?? [],
                    'activity_counts' => $this->countAssignmentsBy($subconAssignments, 'activity_type'),
                    'top_blocked_assignments' => $topAssignments,
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
    private function subcontractorProfile(array $context, string $subcontractorName): array
    {
        $assignments = Assignment::query()
            ->with(['site.project', 'subcontractor'])
            ->tap(fn ($query) => $this->applyAssignmentContext($query, $context))
            ->whereHas('subcontractor', fn ($q) => $q->where('name', 'LIKE', "%{$subcontractorName}%"))
            ->latest('updated_at')
            ->get();

        if ($assignments->isEmpty()) {
            return [
                'subcontractor' => $subcontractorName,
                'error' => "No assignments found for subcontractor matching '{$subcontractorName}'.",
            ];
        }

        $first = $assignments->first();

        return [
            'subcontractor' => $first->subcontractor?->name,
            'total_assignments' => $assignments->count(),
            'status_counts' => $this->countAssignmentsBy($assignments, 'status'),
            'activity_counts' => $this->countAssignmentsBy($assignments, 'activity_type'),
            'assignments' => $assignments->map(fn (Assignment $a): array => [
                'id' => $a->id,
                'site_code' => $a->site?->site_code,
                'site_name' => $a->site?->location_name,
                'project' => $a->site?->project?->name,
                'activity_type' => $a->activity_type->value,
                'status' => $a->status->value,
                'age_days' => (int) $a->updated_at->diffInDays(now()),
                'url' => route('admin.assignments.show', $a),
            ])->values()->all(),
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
     * @return array<string, mixed>
     */
    public function workflowKnowledge(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'sources' => ['NexPM workflow rules', 'Application implementation'],
            'status_meanings' => [
                'PENDING' => 'Assignment belum lengkap atau belum siap masuk review/dokumen.',
                'REVISION' => 'Assignment dikembalikan untuk diperbaiki sebelum bisa lanjut.',
                'DOCUMENT' => 'Data assignment sudah lengkap untuk tahap dokumen dan bisa dipertimbangkan dalam kesiapan laporan.',
                'VERIFIED' => 'Admin sudah memverifikasi assignment, tetapi belum tentu sudah masuk report.',
                'REPORTED' => 'Assignment sudah masuk report yang digenerate.',
                'DROP' => 'Assignment tidak dilanjutkan dalam workflow aktif.',
            ],
            'required_data' => [
                'survey' => [
                    'power_kva pada site',
                    'data survey yang ditandai lengkap oleh AssignmentSurveyData::isComplete()',
                    'foto pendukung sesuai form survey',
                ],
                'construction' => [
                    'cons_wo_number dari admin sebelum pekerjaan konstruksi bisa berjalan normal',
                    'tanggal mulai dan tanggal selesai aktual',
                    'machine_serial_number',
                    'foto machine serial number',
                    'catatan progres dan foto konstruksi bila diminta workflow',
                ],
                'bast' => [
                    'sim_provider',
                    'nomor_simcard',
                    'commissioning_date',
                    'foto BAST',
                ],
            ],
            'operating_rules' => [
                'Super admin/admin dapat mengisi dan memperbaiki data assignment dari sisi admin.',
                'Subcontractor diarahkan untuk melihat assignment yang relevan; akses tulis harus mengikuti policy dan route yang aktif.',
                'Survey yang lengkap tetapi status belum DOCUMENT dianggap workflow gap.',
                'Construction tanpa WO number dianggap blocker karena assignment terkunci.',
                'VERIFIED tetapi belum REPORTED dianggap perlu ditindaklanjuti ke proses report.',
            ],
            'suggested_questions' => [
                'Cek gap workflow',
                'Apa yang siap dibuat laporan?',
                'Assignment mana yang telat?',
                'Apa prioritas tindakan saya hari ini?',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function resolveEntityContext(string $query, array $context = []): array
    {
        $normalized = Str::lower($query);
        $searchTerm = $this->entitySearchTerm($normalized);

        $projects = Project::query()
            ->select(['id', 'name'])
            ->when($searchTerm !== '', fn ($query) => $query->where('name', 'like', "%{$searchTerm}%"))
            ->limit(8)
            ->get()
            ->filter(fn (Project $project): bool => $searchTerm !== '' || Str::contains($normalized, Str::lower($project->name)))
            ->map(fn (Project $project): array => [
                'type' => 'project',
                'id' => $project->id,
                'label' => $project->name,
                'context' => ['type' => 'project', 'id' => $project->id, 'project_id' => $project->id, 'label' => $project->name],
            ])
            ->values();

        $sites = Site::query()
            ->select(['id', 'project_id', 'site_code', 'location_name'])
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                $query->where(function ($query) use ($searchTerm): void {
                    $query->where('site_code', 'like', "%{$searchTerm}%")
                        ->orWhere('location_name', 'like', "%{$searchTerm}%");
                });
            })
            ->limit(8)
            ->get()
            ->filter(fn (Site $site): bool => $searchTerm !== ''
                || Str::contains($normalized, Str::lower((string) $site->site_code))
                || Str::contains($normalized, Str::lower((string) $site->location_name)))
            ->map(fn (Site $site): array => [
                'type' => 'site',
                'id' => $site->id,
                'label' => trim($site->site_code.' '.$site->location_name),
                'context' => ['type' => 'site', 'id' => $site->id, 'site_id' => $site->id, 'project_id' => $site->project_id, 'label' => trim($site->site_code.' '.$site->location_name)],
            ])
            ->values();

        $subcontractors = Subcontractor::query()
            ->select(['id', 'name', 'code'])
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                $query->where(function ($query) use ($searchTerm): void {
                    $query->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('code', 'like', "%{$searchTerm}%");
                });
            })
            ->limit(8)
            ->get()
            ->filter(fn (Subcontractor $subcontractor): bool => $searchTerm !== ''
                || Str::contains($normalized, Str::lower($subcontractor->name))
                || Str::contains($normalized, Str::lower((string) $subcontractor->code)))
            ->map(fn (Subcontractor $subcontractor): array => [
                'type' => 'subcontractor',
                'id' => $subcontractor->id,
                'label' => $subcontractor->name,
                'context' => ['type' => 'subcontractor', 'id' => $subcontractor->id, 'label' => $subcontractor->name],
            ])
            ->values();

        $subcontractorUsers = User::query()
            ->select(['id', 'name', 'subcontractor_id'])
            ->with('subcontractor:id,name')
            ->whereNotNull('subcontractor_id')
            ->when($searchTerm !== '', fn ($query) => $query->where('name', 'like', "%{$searchTerm}%"))
            ->limit(8)
            ->get()
            ->filter(fn (User $user): bool => $searchTerm !== '' || Str::contains($normalized, Str::lower($user->name)))
            ->map(fn (User $user): array => [
                'type' => 'subcontractor_user',
                'id' => $user->id,
                'label' => $user->name,
                'subcontractor' => $user->subcontractor?->name,
                'context' => ['type' => 'subcontractor_user', 'id' => $user->id, 'subcontractor_id' => $user->subcontractor_id, 'label' => $user->name],
            ])
            ->values();

        $mainContractors = DB::table('main_contractors')
            ->select(['id', 'name'])
            ->when($searchTerm !== '', fn ($query) => $query->where('name', 'like', "%{$searchTerm}%"))
            ->limit(8)
            ->get()
            ->filter(fn ($mainContractor): bool => $searchTerm !== '' || Str::contains($normalized, Str::lower((string) $mainContractor->name)))
            ->map(fn ($mainContractor): array => [
                'type' => 'main_contractor',
                'id' => (int) $mainContractor->id,
                'label' => $mainContractor->name,
                'context' => ['type' => 'main_contractor', 'id' => (int) $mainContractor->id, 'label' => $mainContractor->name],
            ])
            ->values();

        $machineTypes = DB::table('machine_types')
            ->select(['id', 'name'])
            ->when($searchTerm !== '', fn ($query) => $query->where('name', 'like', "%{$searchTerm}%"))
            ->limit(8)
            ->get()
            ->filter(fn ($machineType): bool => $searchTerm !== '' || Str::contains($normalized, Str::lower((string) $machineType->name)))
            ->map(fn ($machineType): array => [
                'type' => 'machine_type',
                'id' => (int) $machineType->id,
                'label' => $machineType->name,
                'context' => ['type' => 'machine_type', 'id' => (int) $machineType->id, 'label' => $machineType->name],
            ])
            ->values();

        $matches = $projects
            ->concat($sites)
            ->concat($subcontractors)
            ->concat($subcontractorUsers)
            ->concat($mainContractors)
            ->concat($machineTypes)
            ->values();

        return [
            'generated_at' => now()->toIso8601String(),
            'query' => $query,
            'search_term' => $searchTerm,
            'current_context' => $this->normalizedContext($context),
            'match_count' => $matches->count(),
            'needs_clarification' => $matches->count() > 1,
            'projects' => $projects->all(),
            'sites' => $sites->all(),
            'subcontractors' => $subcontractors->all(),
            'subcontractor_users' => $subcontractorUsers->all(),
            'main_contractors' => $mainContractors->all(),
            'machine_types' => $machineTypes->all(),
            'suggestions' => $matches
                ->take(5)
                ->map(fn (array $match): string => "Bahas {$match['type']} {$match['label']}")
                ->all(),
        ];
    }

    /**
     * Count sites or assignments for a named entity with optional activity/status filters.
     *
     * @param  array{count_target?: string, project_name?: string, main_contractor_name?: string, subcontractor_name?: string, subcontractor_user_name?: string, machine_type_name?: string, activity_type?: string, status?: string}  $filters
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function queryEntityStats(array $filters, array $context): array
    {
        $countTarget = $filters['count_target'] ?? 'assignments';
        $projectName = isset($filters['project_name']) ? trim((string) $filters['project_name']) : null;
        $mainContractorName = isset($filters['main_contractor_name']) ? trim((string) $filters['main_contractor_name']) : null;
        $subcontractorName = isset($filters['subcontractor_name']) ? trim((string) $filters['subcontractor_name']) : null;
        $subcontractorUserName = isset($filters['subcontractor_user_name']) ? trim((string) $filters['subcontractor_user_name']) : null;
        $machineTypeName = isset($filters['machine_type_name']) ? trim((string) $filters['machine_type_name']) : null;
        $activityType = $this->normalizeActivityType($filters['activity_type'] ?? null);
        $status = $this->normalizeAssignmentStatus($filters['status'] ?? null);

        if ($countTarget === 'sites') {
            return $this->countSitesForEntity($projectName, $mainContractorName, $machineTypeName, $context);
        }

        return $this->assignmentOperationsPayload([
            'intent' => 'assignment_recap',
            'project_name' => $projectName,
            'main_contractor_name' => $mainContractorName,
            'subcontractor_name' => $subcontractorName,
            'subcontractor_user_name' => $subcontractorUserName,
            'machine_type_name' => $machineTypeName,
            'activity_type' => $activityType,
            'status' => $status,
            'include_items' => false,
        ], $context) + ['count_target' => 'assignments'];
    }

    /** @param  array<string, mixed>  $context */
    private function countSitesForEntity(?string $projectName, ?string $mainContractorName, ?string $machineTypeName, array $context): array
    {
        $resolved = $this->resolveOperationFilters([
            'project_name' => $projectName,
            'main_contractor_name' => $mainContractorName,
            'machine_type_name' => $machineTypeName,
        ]);

        if ($resolved['needs_clarification']) {
            return [
                'count_target' => 'sites',
                'total_count' => 0,
                'filters' => $resolved['filters'],
                'matched_entities' => $resolved['matched_entities'],
                'needs_clarification' => true,
                'clarification_suggestions' => $resolved['clarification_suggestions'],
                'generated_at' => now()->toIso8601String(),
            ];
        }

        $query = DB::table('sites')
            ->join('projects', 'sites.project_id', '=', 'projects.id')
            ->leftJoin('main_contractors', 'main_contractors.id', '=', 'projects.main_contractor_id')
            ->leftJoin('machine_types', 'machine_types.id', '=', 'sites.machine_type_id');

        $this->applyResolvedSiteFilters($query, $resolved);

        if ($projectId = $this->contextProjectId($context)) {
            $query->where('sites.project_id', $projectId);
        }

        $total = $query->count();

        return [
            'count_target' => 'sites',
            'filter_project' => $resolved['filters']['project_name'] ?? $projectName,
            'filter_main_contractor' => $resolved['filters']['main_contractor_name'] ?? $mainContractorName,
            'filter_machine_type' => $resolved['filters']['machine_type_name'] ?? $machineTypeName,
            'total_count' => $total,
            'filters' => $resolved['filters'],
            'matched_entities' => $resolved['matched_entities'],
            'needs_clarification' => false,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array{intent?: string, project_name?: ?string, main_contractor_name?: ?string, subcontractor_name?: ?string, subcontractor_user_name?: ?string, machine_type_name?: ?string, activity_type?: ?string, status?: ?string}  $filters
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function summarizeAssignmentOperations(array $filters, array $context): array
    {
        $intent = (string) ($filters['intent'] ?? 'assignment_recap');
        $activityType = $this->normalizeActivityType($filters['activity_type'] ?? null);

        if ($intent === 'survey_recap' && $activityType === null) {
            $activityType = ActivityType::Survey->value;
        }

        return $this->assignmentOperationsPayload([
            'intent' => $intent,
            'project_name' => isset($filters['project_name']) ? trim((string) $filters['project_name']) : null,
            'main_contractor_name' => isset($filters['main_contractor_name']) ? trim((string) $filters['main_contractor_name']) : null,
            'subcontractor_name' => isset($filters['subcontractor_name']) ? trim((string) $filters['subcontractor_name']) : null,
            'subcontractor_user_name' => isset($filters['subcontractor_user_name']) ? trim((string) $filters['subcontractor_user_name']) : null,
            'machine_type_name' => isset($filters['machine_type_name']) ? trim((string) $filters['machine_type_name']) : null,
            'activity_type' => $activityType,
            'status' => $this->normalizeAssignmentStatus($filters['status'] ?? null),
            'include_items' => true,
        ], $context);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function assignmentOperationsPayload(array $filters, array $context): array
    {
        $resolved = $this->resolveOperationFilters($filters);

        if ($resolved['needs_clarification']) {
            return [
                'generated_at' => now()->toIso8601String(),
                'intent' => $filters['intent'] ?? 'assignment_recap',
                'total_count' => 0,
                'filters' => $resolved['filters'],
                'matched_entities' => $resolved['matched_entities'],
                'needs_clarification' => true,
                'clarification_suggestions' => $resolved['clarification_suggestions'],
                'status_breakdown' => [],
                'activity_breakdown' => [],
                'project_breakdown' => [],
                'subcontractor_breakdown' => [],
                'machine_type_breakdown' => [],
                'items' => [],
            ];
        }

        $baseQuery = fn () => $this->assignmentOperationsQuery($resolved, $filters, $context);
        $total = $baseQuery()->count();

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'intent' => $filters['intent'] ?? 'assignment_recap',
            'total_count' => $total,
            'filters' => $resolved['filters'],
            'matched_entities' => $resolved['matched_entities'],
            'needs_clarification' => false,
            'status_breakdown' => $this->breakdown($baseQuery(), 'assignments.status'),
            'activity_breakdown' => $this->breakdown($baseQuery(), 'assignments.activity_type'),
            'project_breakdown' => $this->namedBreakdown($baseQuery(), 'projects.name'),
            'subcontractor_breakdown' => $this->namedBreakdown($baseQuery(), 'subcontractors.name'),
            'machine_type_breakdown' => $this->namedBreakdown($baseQuery(), 'machine_types.name'),
        ];

        if (($filters['include_items'] ?? false) === true) {
            $payload['items'] = $baseQuery()
                ->select([
                    'assignments.id',
                    'assignments.activity_type',
                    'assignments.status',
                    'assignments.updated_at',
                    'sites.site_code',
                    'sites.location_name',
                    'projects.name as project_name',
                    'subcontractors.name as subcontractor_name',
                    'machine_types.name as machine_type_name',
                ])
                ->orderBy('assignments.updated_at')
                ->limit(50)
                ->get()
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'site_code' => $row->site_code,
                    'location' => $row->location_name,
                    'project' => $row->project_name,
                    'activity_type' => $row->activity_type,
                    'status' => $row->status,
                    'subcontractor' => $row->subcontractor_name,
                    'machine_type' => $row->machine_type_name,
                    'age_days' => (int) now()->diffInDays($row->updated_at),
                    'url' => route('admin.assignments.show', $row->id),
                ])
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $context
     */
    private function assignmentOperationsQuery(array $resolved, array $filters, array $context)
    {
        $query = DB::table('assignments')
            ->join('sites', 'assignments.site_id', '=', 'sites.id')
            ->join('projects', 'sites.project_id', '=', 'projects.id')
            ->leftJoin('main_contractors', 'main_contractors.id', '=', 'projects.main_contractor_id')
            ->leftJoin('subcontractors', 'subcontractors.id', '=', 'assignments.subcontractor_id')
            ->leftJoin('machine_types', 'machine_types.id', '=', 'sites.machine_type_id');

        $this->applyResolvedAssignmentFilters($query, $resolved);

        if ($activityType = $filters['activity_type'] ?? null) {
            $query->where('assignments.activity_type', $activityType);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('assignments.status', $status);
        }

        if (($filters['intent'] ?? null) === 'outstanding') {
            $query->whereNotIn('assignments.status', [
                AssignmentStatus::Verified->value,
                AssignmentStatus::Reported->value,
                AssignmentStatus::Drop->value,
            ]);
        }

        if ($assignmentId = $this->contextAssignmentId($context)) {
            $query->where('assignments.id', $assignmentId);
        } elseif ($siteId = $this->contextSiteId($context)) {
            $query->where('assignments.site_id', $siteId);
        } elseif ($projectId = $this->contextProjectId($context)) {
            $query->where('sites.project_id', $projectId);
        }

        return $query;
    }

    /** @param  array<string, mixed>  $resolved */
    private function applyResolvedAssignmentFilters($query, array $resolved): void
    {
        $this->applyResolvedSiteFilters($query, $resolved);

        if ($subcontractor = $resolved['selected']['subcontractor'] ?? null) {
            $query->where('assignments.subcontractor_id', $subcontractor['id']);
        }

        if ($subcontractorUser = $resolved['selected']['subcontractor_user'] ?? null) {
            $query->where('assignments.subcontractor_id', $subcontractorUser['subcontractor_id']);
        }
    }

    /** @param  array<string, mixed>  $resolved */
    private function applyResolvedSiteFilters($query, array $resolved): void
    {
        if ($project = $resolved['selected']['project'] ?? null) {
            $query->where('sites.project_id', $project['id']);
        }

        if ($mainContractor = $resolved['selected']['main_contractor'] ?? null) {
            $query->where('projects.main_contractor_id', $mainContractor['id']);
        }

        if ($machineType = $resolved['selected']['machine_type'] ?? null) {
            $query->where('sites.machine_type_id', $machineType['id']);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function resolveOperationFilters(array $filters): array
    {
        $project = $this->resolveNamedEntity('projects', 'project', $filters['project_name'] ?? null, ['name']);
        $mainContractor = $this->resolveNamedEntity('main_contractors', 'main_contractor', $filters['main_contractor_name'] ?? null, ['name']);
        $subcontractor = $this->resolveNamedEntity('subcontractors', 'subcontractor', $filters['subcontractor_name'] ?? null, ['name', 'code']);
        $subcontractorUser = $this->resolveSubcontractorUser($filters['subcontractor_user_name'] ?? null);
        $machineType = $this->resolveNamedEntity('machine_types', 'machine_type', $filters['machine_type_name'] ?? null, ['name']);

        if (($subcontractor['selected'] ?? null) === null && filled($filters['subcontractor_name'] ?? null)) {
            $fallbackUser = $this->resolveSubcontractorUser($filters['subcontractor_name']);

            if (($fallbackUser['selected'] ?? null) !== null) {
                $subcontractorUser = $fallbackUser;
                $subcontractor = $this->emptyResolvedEntity('subcontractor', $filters['subcontractor_name']);
            }
        }

        $entities = [
            'project' => $project,
            'main_contractor' => $mainContractor,
            'subcontractor' => $subcontractor,
            'subcontractor_user' => $subcontractorUser,
            'machine_type' => $machineType,
        ];

        $matchedEntities = collect($entities)
            ->map(fn (array $entity): array => $entity['matches'])
            ->all();

        $selected = collect($entities)
            ->map(fn (array $entity) => $entity['selected'])
            ->filter()
            ->all();

        $needsClarification = collect($entities)->contains(fn (array $entity): bool => $entity['needs_clarification']);

        return [
            'filters' => [
                'project_name' => $project['selected']['name'] ?? $filters['project_name'] ?? null,
                'main_contractor_name' => $mainContractor['selected']['name'] ?? $filters['main_contractor_name'] ?? null,
                'subcontractor_name' => $subcontractor['selected']['name'] ?? $filters['subcontractor_name'] ?? null,
                'subcontractor_user_name' => $subcontractorUser['selected']['name'] ?? $filters['subcontractor_user_name'] ?? null,
                'machine_type_name' => $machineType['selected']['name'] ?? $filters['machine_type_name'] ?? null,
                'activity_type' => $filters['activity_type'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'matched_entities' => $matchedEntities,
            'selected' => $selected,
            'needs_clarification' => $needsClarification,
            'clarification_suggestions' => collect($matchedEntities)
                ->flatten(1)
                ->take(8)
                ->map(fn (array $match): string => "{$match['type']}: {$match['name']}")
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function resolveNamedEntity(string $table, string $type, mixed $term, array $columns): array
    {
        $term = trim((string) ($term ?? ''));

        if ($term === '') {
            return $this->emptyResolvedEntity($type, null);
        }

        $matches = DB::table($table)
            ->select(['id', DB::raw($columns[0].' as name')])
            ->where(function ($query) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'LIKE', "%{$term}%");
                }
            })
            ->orderBy($columns[0])
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'type' => $type,
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->values();

        if ($matches->isEmpty()) {
            $normalizedTerm = $this->normalizeEntityName($term);
            $matches = DB::table($table)
                ->select(['id', DB::raw($columns[0].' as name')])
                ->orderBy($columns[0])
                ->limit(100)
                ->get()
                ->filter(fn ($row): bool => Str::contains($this->normalizeEntityName((string) $row->name), $normalizedTerm))
                ->take(8)
                ->map(fn ($row): array => [
                    'type' => $type,
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                ])
                ->values();
        }

        $selected = $this->selectUnambiguousMatch($matches, $term);

        return [
            'input' => $term,
            'matches' => $matches->all(),
            'selected' => $selected,
            'needs_clarification' => $matches->count() > 1 && $selected === null,
        ];
    }

    /** @return array<string, mixed> */
    private function resolveSubcontractorUser(mixed $term): array
    {
        $term = trim((string) ($term ?? ''));

        if ($term === '') {
            return $this->emptyResolvedEntity('subcontractor_user', null);
        }

        $matches = DB::table('users')
            ->join('subcontractors', 'subcontractors.id', '=', 'users.subcontractor_id')
            ->where('users.name', 'LIKE', "%{$term}%")
            ->select([
                'users.id',
                'users.name',
                'users.subcontractor_id',
                'subcontractors.name as subcontractor_name',
            ])
            ->orderBy('users.name')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'type' => 'subcontractor_user',
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'subcontractor_id' => (int) $row->subcontractor_id,
                'subcontractor_name' => (string) $row->subcontractor_name,
            ])
            ->values();

        if ($matches->isEmpty()) {
            $normalizedTerm = $this->normalizeEntityName($term);
            $matches = DB::table('users')
                ->join('subcontractors', 'subcontractors.id', '=', 'users.subcontractor_id')
                ->whereNotNull('users.subcontractor_id')
                ->select([
                    'users.id',
                    'users.name',
                    'users.subcontractor_id',
                    'subcontractors.name as subcontractor_name',
                ])
                ->orderBy('users.name')
                ->limit(100)
                ->get()
                ->filter(fn ($row): bool => Str::contains($this->normalizeEntityName((string) $row->name), $normalizedTerm))
                ->take(8)
                ->map(fn ($row): array => [
                    'type' => 'subcontractor_user',
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'subcontractor_id' => (int) $row->subcontractor_id,
                    'subcontractor_name' => (string) $row->subcontractor_name,
                ])
                ->values();
        }

        $selected = $this->selectUnambiguousMatch($matches, $term);

        return [
            'input' => $term,
            'matches' => $matches->all(),
            'selected' => $selected,
            'needs_clarification' => $matches->count() > 1 && $selected === null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $matches
     * @return array<string, mixed>|null
     */
    private function selectUnambiguousMatch(Collection $matches, string $term): ?array
    {
        if ($matches->count() === 1) {
            return $matches->first();
        }

        $exact = $matches->filter(fn (array $match): bool => Str::lower($match['name']) === Str::lower($term));

        if ($exact->count() === 1) {
            return $exact->first();
        }

        $normalizedTerm = $this->normalizeEntityName($term);
        $normalized = $matches->filter(fn (array $match): bool => $this->normalizeEntityName($match['name']) === $normalizedTerm);

        return $normalized->count() === 1 ? $normalized->first() : null;
    }

    private function normalizeEntityName(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->replaceMatches('/\b(pt|cv|tbk|ltd|inc|company|co)\b/u', '')
            ->replaceMatches('/[^a-z0-9]+/u', '')
            ->toString();
    }

    /** @return array<string, mixed> */
    private function emptyResolvedEntity(string $type, ?string $input): array
    {
        return [
            'input' => $input,
            'matches' => [],
            'selected' => null,
            'needs_clarification' => false,
            'type' => $type,
        ];
    }

    /** @return array<string, int> */
    private function breakdown($query, string $column): array
    {
        return $query
            ->select($column, DB::raw('COUNT(*) as total'))
            ->groupBy($column)
            ->orderBy($column)
            ->pluck('total', $column)
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /** @return array<string, int> */
    private function namedBreakdown($query, string $column): array
    {
        return $query
            ->select($column, DB::raw('COUNT(*) as total'))
            ->whereNotNull($column)
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit(20)
            ->pluck('total', $column)
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * Generate outstanding assignment data for a specific subcontractor (for reminder use).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function generateSubcontractorReminder(string $subcontractorName, array $context): array
    {
        $subcon = DB::table('subcontractors')
            ->where('name', 'LIKE', "%{$subcontractorName}%")
            ->first(['id', 'name', 'phone']);

        if ($subcon === null) {
            return [
                'error' => "Tidak ada subkontraktor yang cocok dengan nama '{$subcontractorName}'.",
                'subcontractor_name' => $subcontractorName,
            ];
        }

        $terminalStatuses = [
            AssignmentStatus::Verified->value,
            AssignmentStatus::Reported->value,
            AssignmentStatus::Drop->value,
        ];

        $outstanding = DB::table('assignments')
            ->join('sites', 'assignments.site_id', '=', 'sites.id')
            ->join('projects', 'sites.project_id', '=', 'projects.id')
            ->where('assignments.subcontractor_id', $subcon->id)
            ->whereNotIn('assignments.status', $terminalStatuses)
            ->select([
                'assignments.id',
                'assignments.activity_type',
                'assignments.status',
                'assignments.updated_at',
                'sites.site_code',
                'sites.location_name',
                'projects.name as project_name',
            ])
            ->orderBy('assignments.updated_at')
            ->limit(50)
            ->get();

        $grouped = $outstanding
            ->groupBy('project_name')
            ->map(fn ($items, $projectName) => [
                'project' => $projectName,
                'items' => $items->map(fn ($row) => [
                    'id' => $row->id,
                    'site_code' => $row->site_code,
                    'site_name' => $row->location_name,
                    'activity_type' => $row->activity_type,
                    'status' => $row->status,
                    'age_days' => (int) now()->diffInDays($row->updated_at),
                    'url' => route('admin.assignments.show', $row->id),
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'subcontractor' => $subcon->name,
            'subcontractor_id' => $subcon->id,
            'subcontractor_phone' => $subcon->phone,
            'outstanding_count' => $outstanding->count(),
            'projects_count' => count($grouped),
            'grouped_by_project' => $grouped,
            'status_counts' => $outstanding->groupBy('status')->map->count()->all(),
            'activity_counts' => $outstanding->groupBy('activity_type')->map->count()->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

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
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function decorateToolPayload(string $toolName, array $payload, array $context = []): array
    {
        return array_merge($payload, [
            'sources' => $payload['sources'] ?? $this->toolSources($toolName),
            'follow_up_suggestions' => $payload['follow_up_suggestions'] ?? $this->followUpSuggestions($toolName),
            'record_links' => $payload['record_links'] ?? $this->recordLinks($payload),
            'assistant_context' => $payload['assistant_context'] ?? $this->normalizedContext($context),
        ]);
    }

    /**
     * @return list<string>
     */
    private function toolSources(string $toolName): array
    {
        return match ($toolName) {
            'workflow_knowledge' => ['NexPM workflow rules', 'Application implementation'],
            'query_database' => ['Live database query'],
            'resolve_entity_context' => ['Projects', 'Sites', 'Subcontractors', 'Subcontractor users', 'Main contractors', 'Machine types'],
            'query_entity_stats' => ['Assignments', 'Sites', 'Projects', 'Subcontractors', 'Subcontractor users', 'Main contractors', 'Machine types'],
            'summarize_assignment_operations' => ['Assignments', 'Sites', 'Projects', 'Subcontractors', 'Subcontractor users', 'Main contractors', 'Machine types'],
            'generate_subcontractor_reminder' => ['Assignments', 'Subcontractors', 'Sites', 'Projects'],
            'list_users' => ['Users'],
            default => ['Assignments', 'Sites', 'Projects'],
        };
    }

    /**
     * @return list<string>
     */
    private function followUpSuggestions(string $toolName): array
    {
        return match ($toolName) {
            'workflow_knowledge' => [
                'Cek gap workflow',
                'Apa yang siap dibuat laporan?',
                'Assignment mana yang telat?',
            ],
            'check_report_readiness' => [
                'Cek gap workflow untuk assignment siap laporan',
                'Apa prioritas tindakan saya hari ini?',
            ],
            'detect_workflow_gaps' => [
                'Apa prioritas tindakan saya hari ini?',
                'Assignment mana yang paling urgent?',
            ],
            'summarize_subcontractor_blockers' => [
                'Subcon mana yang perlu follow up dulu?',
                'Apa assignment paling lama?',
            ],
            'summarize_project_risks', 'project_health_briefing' => [
                'Apa risiko terbesar saat ini?',
                'Apa prioritas tindakan saya hari ini?',
                'Apa yang siap dibuat laporan?',
            ],
            'resolve_entity_context' => [
                'Bahas project yang paling berisiko',
                'Cek assignment telat untuk project itu',
            ],
            'query_entity_stats' => [
                'Buatkan reminder untuk subkon tersebut',
                'Apa assignment yang masih pending di project ini?',
                'Cek blocker subkon ini',
            ],
            'summarize_assignment_operations' => [
                'Tampilkan outstanding untuk subkon ini',
                'Breakdown berdasarkan status',
                'Breakdown berdasarkan machine type',
            ],
            'generate_subcontractor_reminder' => [
                'Berapa total assignment subkon ini?',
                'Cek blocker subkon ini',
                'Apa prioritas tindakan saya hari ini?',
            ],
            default => [
                'Briefing proyek hari ini',
                'Cek gap workflow',
                'Apa prioritas tindakan saya hari ini?',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{label: string, url: string}>
     */
    private function recordLinks(array $payload): array
    {
        $links = collect($this->extractRecordLinks($payload))
            ->filter(fn (array $link): bool => filled($link['url'] ?? null))
            ->unique('url')
            ->take(5)
            ->values()
            ->all();

        return $links;
    }

    /**
     * @param  mixed  $value
     * @return list<array{label: string, url: string}>
     */
    private function extractRecordLinks($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $links = [];

        if (isset($value['url']) && is_string($value['url'])) {
            $label = $value['site_code'] ?? $value['site_name'] ?? $value['project'] ?? $value['label'] ?? ('Assignment #'.($value['id'] ?? $value['assignment_id'] ?? ''));
            $links[] = ['label' => (string) $label, 'url' => $value['url']];
        }

        foreach ($value as $child) {
            $links = array_merge($links, $this->extractRecordLinks($child));
        }

        return $links;
    }

    private function entitySearchTerm(string $normalized): string
    {
        if (! preg_match('/(?:project|proyek|site|lokasi|subcon|subkon|subcontractor|main con|main contractor|user|machine type|mesin)\s+([a-z0-9][a-z0-9\s._-]{1,80})/i', $normalized, $matches)) {
            return '';
        }

        $term = preg_replace('/\b(lambat|telat|terlambat|bermasalah|stuck|macet|ini|itu|kenapa|mengapa|apa|status|risiko|risk|blocker|progress|progres)\b.*$/i', '', $matches[1]) ?? '';

        return trim($term);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function statsFiltersFromContext(array $context): array
    {
        $query = (string) ($context['query'] ?? '');

        return array_merge($this->operationFiltersFromContext($context), [
            'count_target' => Str::contains(Str::lower($query), ['lokasi', 'location', 'locations', 'site', 'sites'])
                && ! Str::contains(Str::lower($query), ['assignment', 'assignments'])
                    ? 'sites'
                    : 'assignments',
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function operationFiltersFromContext(array $context): array
    {
        $query = (string) ($context['query'] ?? '');
        $normalized = Str::lower($query);
        $subcontractorTerm = $this->extractNamedPhrase($query, '(?:subcon|subkon|subcontractor|vendor)');

        return [
            'intent' => $this->inferAssignmentOperationIntent($normalized),
            'project_name' => $this->extractNamedPhrase($query, '(?:project|proyek)'),
            'main_contractor_name' => $this->extractNamedPhrase($query, '(?:main\s*con|main\s*contractor|kontraktor\s*utama)'),
            'subcontractor_name' => Str::contains($normalized, ['user', 'pengguna']) ? null : $subcontractorTerm,
            'subcontractor_user_name' => Str::contains($normalized, ['user', 'pengguna']) ? ($this->extractNamedPhrase($query, '(?:user|pengguna)') ?? $subcontractorTerm) : null,
            'machine_type_name' => $this->extractNamedPhrase($query, '(?:machine\s*type|tipe\s*mesin|mesin)'),
            'activity_type' => $this->inferActivityType($normalized),
            'status' => $this->inferStatus($normalized),
        ];
    }

    private function inferAssignmentOperationIntent(string $normalized): string
    {
        if (Str::contains($normalized, ['outstanding', 'tunggakan', 'belum selesai'])) {
            return 'outstanding';
        }

        if (Str::contains($normalized, ['survey']) && Str::contains($normalized, ['summary', 'summarize', 'ringkas', 'rangkum', 'recap', 'rekap'])) {
            return 'survey_recap';
        }

        return 'assignment_recap';
    }

    private function inferActivityType(string $normalized): ?string
    {
        return match (true) {
            Str::contains($normalized, ['pln']) => ActivityType::PlnConnection->value,
            Str::contains($normalized, ['survey', 'survei']) => ActivityType::Survey->value,
            Str::contains($normalized, ['construction', 'konstruksi']) => ActivityType::Construction->value,
            Str::contains($normalized, ['bast']) => ActivityType::Bast->value,
            default => null,
        };
    }

    private function inferStatus(string $normalized): ?string
    {
        if (preg_match('/status\s+([a-z_]+)/i', $normalized, $matches)) {
            return $this->normalizeAssignmentStatus($matches[1]);
        }

        $activityLikeStatuses = [
            AssignmentStatus::Survey,
            AssignmentStatus::Construction,
        ];

        foreach (AssignmentStatus::cases() as $status) {
            if (in_array($status, $activityLikeStatuses, true)) {
                continue;
            }

            if (Str::contains($normalized, Str::lower($status->value))) {
                return $status->value;
            }
        }

        return null;
    }

    private function normalizeActivityType(mixed $value): ?string
    {
        $normalized = strtoupper(trim((string) ($value ?? '')));

        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'PLN' => ActivityType::PlnConnection->value,
            'SURVEI' => ActivityType::Survey->value,
            default => ActivityType::tryFrom($normalized)?->value ?? $normalized,
        };
    }

    private function normalizeAssignmentStatus(mixed $value): ?string
    {
        $normalized = strtoupper(trim((string) ($value ?? '')));

        if ($normalized === '') {
            return null;
        }

        return AssignmentStatus::tryFrom($normalized)?->value ?? $normalized;
    }

    private function extractNamedPhrase(string $query, string $keywordPattern): ?string
    {
        if (! preg_match('/'.$keywordPattern.'\s+([^,?.]+)/i', $query, $matches)) {
            return null;
        }

        $term = preg_replace('/\b(how many|berapa|ada berapa|jumlah|assignment|assignments|lokasi|locations|site|sites|status|pending|survey|pln|construction|bast|outstanding|summary|summarize|ringkas|rangkum|recap|rekap|untuk|by|for|di|in)\b.*$/i', '', $matches[1]) ?? '';
        $term = trim($term);

        return $term === '' ? null : $term;
    }

    /**
     * @param  array<string, mixed>  $toolPayload
     */
    private function fallbackAnswerInIndonesian(string $toolName, array $toolPayload, bool $includeProviderPrefix = true): string
    {
        $prefix = $includeProviderPrefix
            ? 'AI provider belum dikonfigurasi atau tidak dapat dihubungi, jadi ini ringkasan lokal NexPM. '
            : '';

        return $prefix.match ($toolName) {
            'list_users' => sprintf(
                'Ditemukan %d user. Pembagian role: %s.',
                (int) $toolPayload['total_users_returned'],
                $this->formatCounts($toolPayload['role_counts'] ?? [], 'id')
            ),
            'general_help' => 'Saya bisa membantu melihat risiko proyek, assignment telat, blocker subcon, kesiapan laporan, dan prioritas tindakan PM hari ini.',
            'workflow_knowledge' => 'Ringkasan workflow: PENDING berarti data belum siap, REVISION perlu diperbaiki, DOCUMENT berarti data lengkap untuk tahap dokumen/kesiapan laporan, VERIFIED sudah direview admin, dan REPORTED sudah masuk report. Survey lengkap tetapi belum DOCUMENT adalah gap yang perlu dicek.',
            'resolve_entity_context' => sprintf(
                'Pencarian konteks menemukan %d project, %d site, %d subcon, %d user subcon, %d main contractor, dan %d tipe mesin. Jika hasilnya lebih dari satu, pilih nama yang paling spesifik.',
                count($toolPayload['projects'] ?? []),
                count($toolPayload['sites'] ?? []),
                count($toolPayload['subcontractors'] ?? []),
                count($toolPayload['subcontractor_users'] ?? []),
                count($toolPayload['main_contractors'] ?? []),
                count($toolPayload['machine_types'] ?? []),
            ),
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
            'query_entity_stats' => sprintf(
                'Statistik entitas: ditemukan %d %s%s.',
                (int) ($toolPayload['total_count'] ?? 0),
                $toolPayload['count_target'] === 'sites' ? 'lokasi' : 'assignment',
                isset($toolPayload['filter_project']) ? ' untuk project '.$toolPayload['filter_project'] : ''
            ),
            'summarize_assignment_operations' => sprintf(
                'Rekap operasional assignment: ditemukan %d assignment. Pembagian status: %s. Pembagian activity: %s.',
                (int) ($toolPayload['total_count'] ?? 0),
                $this->formatCounts($toolPayload['status_breakdown'] ?? [], 'id'),
                $this->formatCounts($toolPayload['activity_breakdown'] ?? [], 'id'),
            ),
            'generate_subcontractor_reminder' => sprintf(
                'Reminder subkon: %s memiliki %d assignment outstanding di %d proyek.',
                $toolPayload['subcontractor'] ?? 'Tidak diketahui',
                (int) ($toolPayload['outstanding_count'] ?? 0),
                (int) ($toolPayload['projects_count'] ?? 0),
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
