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
        $toolPayload = $this->runTool($toolName);
        $prompt = $this->buildUserPrompt($message, $toolName, $context);

        try {
            $completion = $this->client->complete($this->systemPrompt(), $prompt, $toolPayload);
            $answer = $completion['content'] !== ''
                ? $completion['content']
                : $this->fallbackAnswer($toolName, $toolPayload);

            return [
                'answer' => $answer,
                'tool_name' => $toolName,
                'tool_payload' => $toolPayload,
                'usage' => $completion['usage'],
            ];
        } catch (Throwable $exception) {
            return [
                'answer' => $this->fallbackAnswer($toolName, $toolPayload),
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

        if (Str::contains($normalized, ['report', 'readiness', 'generate', 'verified'])) {
            return 'check_report_readiness';
        }

        if (Str::contains($normalized, ['dashboard', 'overview', 'summary', 'summarize', 'progress'])) {
            return 'summarize_dashboard';
        }

        return 'find_blocked_assignments';
    }

    /**
     * @return array<string, mixed>
     */
    private function runTool(string $toolName): array
    {
        return match ($toolName) {
            'check_report_readiness' => $this->checkReportReadiness(),
            'summarize_dashboard' => $this->summarizeDashboard(),
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

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are NexPM's read-only operations assistant for super admins.
Use only the supplied application data. Do not claim that you changed records, sent messages, generated reports, or updated workflow state.
Answer concisely with concrete counts, risks, and next actions. If the data is insufficient, say what is missing.
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
    private function fallbackAnswer(string $toolName, array $toolPayload): string
    {
        return match ($toolName) {
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
     * @param  mixed  $counts
     */
    private function formatCounts($counts): string
    {
        if (! is_array($counts) || $counts === []) {
            return 'none';
        }

        return collect($counts)
            ->map(fn (int $count, string $label): string => "{$label}: {$count}")
            ->implode(', ');
    }
}
