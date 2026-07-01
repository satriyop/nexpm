<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

class AiQueryPlanner
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{tool: string, intent: string, confidence: float, route: string, entities: list<string>, reason: string}
     */
    public function plan(string $message, array $context = []): array
    {
        $normalized = Str::lower($message);
        $entities = $this->detectedEntities($normalized);
        $mode = (string) ($context['mode'] ?? 'standard');
        $providerAvailable = (bool) ($context['ai_provider_available'] ?? false);

        if ($this->containsAny($normalized, ['reminder', 'ingatkan', 'buatkan reminder', 'kirim reminder'])) {
            return $this->tool('generate_subcontractor_reminder', 'reminder', 0.95, 'curated', $entities, 'Reminder language maps directly to subcontractor reminder.');
        }

        if ($this->isAssignmentRecapQuestion($normalized)) {
            return $this->tool('summarize_assignment_operations', 'assignment_recap', 0.92, 'curated', $entities, 'Assignment recap/summary question maps to assignment operations.');
        }

        if ($this->containsAny($normalized, ['outstanding', 'tunggakan', 'belum selesai'])) {
            return $this->tool('summarize_assignment_operations', 'outstanding', 0.92, 'curated', $entities, 'Outstanding work maps to assignment operations.');
        }

        if ($this->isCountQuestion($normalized)) {
            return $this->tool('query_entity_stats', 'count', 0.9, 'curated', $entities, 'Count question maps to entity statistics.');
        }

        if ($this->isMachineTypeCountQuestion($normalized)) {
            return $this->tool('query_entity_stats', 'machine_type_count', 0.9, 'curated', $entities, 'Machine type count question maps to entity statistics.');
        }

        if ($this->containsAny($normalized, ['user', 'users', 'pengguna', 'siapa saja', 'daftar user', 'daftar pengguna', 'akun', 'admin', 'superadmin', 'super admin'])) {
            return $this->tool('list_users', 'list_users', 0.88, 'curated', $entities, 'User listing question maps to user summaries.');
        }

        if ($this->containsAny($normalized, ['briefing', 'brief', 'kabar proyek', 'kondisi proyek', 'health', 'health briefing'])) {
            return $this->tool('project_health_briefing', 'project_health', 0.9, 'curated', $entities, 'Briefing language maps to project health.');
        }

        if ($this->containsAny($normalized, ['gap', 'workflow gap', 'gap workflow', 'inkonsisten', 'inconsistent', 'missing field', 'data kurang', 'belum lengkap', 'tidak lengkap'])) {
            return $this->tool('detect_workflow_gaps', 'workflow_gaps', 0.9, 'curated', $entities, 'Workflow gap language maps to workflow gap detection.');
        }

        if ($this->containsAny($normalized, ['arti', 'maksud', 'alur', 'workflow', 'flow', 'status', 'document', 'verified', 'reported', 'field wajib', 'wajib diisi', 'required field', 'siapa yang input', 'siapa input'])) {
            return $this->tool('workflow_knowledge', 'workflow_knowledge', 0.87, 'curated', $entities, 'Workflow/status/process question maps to workflow knowledge.');
        }

        if ($this->hasRecordContext($context) && $this->containsAny($normalized, ['halaman ini', 'record ini', 'assignment ini', 'site ini', 'project ini', 'proyek ini', 'apa masalah', 'masalahnya', 'statusnya', 'apa status'])) {
            return $this->tool('contextual_page_summary', 'context_summary', 0.9, 'curated', $entities, 'Question references the current record/page context.');
        }

        if ($this->containsAny($normalized, ['risiko proyek', 'risiko terbesar', 'project risk', 'project mana', 'proyek mana', 'paling lambat', 'lambat', 'project lambat', 'proyek lambat'])) {
            return $this->tool('summarize_project_risks', 'project_risk', 0.88, 'curated', $entities, 'Project risk language maps to project risk summary.');
        }

        if ($this->containsAny($normalized, ['prioritas', 'priority', 'tindakan', 'action', 'next action', 'apa yang harus'])) {
            return $this->tool('summarize_priority_actions', 'priority_actions', 0.88, 'curated', $entities, 'Priority/action language maps to priority actions.');
        }

        if ($this->containsAny($normalized, ['subcon', 'sub contractor', 'subcontractor', 'vendor', 'blocker subcon', 'paling banyak blocker'])) {
            return $this->tool('summarize_subcontractor_blockers', 'subcontractor_blockers', 0.84, 'curated', $entities, 'Subcontractor blocker language maps to subcontractor blocker summary.');
        }

        if ($this->containsAny($normalized, ['report', 'readiness', 'generate', 'verified', 'laporan', 'siap report', 'siap laporan', 'verifikasi'])) {
            return $this->tool('check_report_readiness', 'report_readiness', 0.84, 'curated', $entities, 'Report/readiness language maps to report readiness.');
        }

        if ($this->looksLikeNamedEntityQuestion($message, $normalized)) {
            return $this->tool('resolve_entity_context', 'entity_resolution', 0.66, 'entity_prepass', $entities, 'Question appears to name a specific entity but does not specify count, recap, or status detail.');
        }

        if ($this->containsAny($normalized, ['dashboard', 'overview', 'summary', 'summarize', 'progress', 'progres', 'ringkas', 'rangkum'])) {
            return $this->tool('summarize_dashboard', 'dashboard_summary', 0.78, 'curated', $entities, 'Broad dashboard/progress summary maps to dashboard summary.');
        }

        if ($this->containsAny($normalized, ['blocked', 'stuck', 'late', 'risk', 'risky', 'pending', 'revision', 'telat', 'terlambat', 'macet', 'bermasalah'])) {
            return $this->tool('find_blocked_assignments', 'blocked_assignments', 0.78, 'curated', $entities, 'Blocked/late/risky assignment language maps to blocked assignment scan.');
        }

        if ($this->shouldResolveUnknownDomainQuestion($normalized, $context)) {
            return $this->tool('resolve_entity_context', 'entity_resolution', 0.62, 'entity_prepass', $entities, 'Domain question is recognizable but intent is not specific enough.');
        }

        if ($mode === 'full' && $providerAvailable && $this->isDatabaseRelated($normalized, $entities)) {
            return $this->tool('query_database', 'ad_hoc_database_query', 0.55, 'full_mode_sql', $entities, 'Full mode can answer database-related questions through guarded read-only SQL.');
        }

        return $this->tool('general_help', 'help', 0.25, 'help', $entities, 'No confident tool match.');
    }

    /**
     * @param  list<string>  $entities
     * @return array{tool: string, intent: string, confidence: float, route: string, entities: list<string>, reason: string}
     */
    private function tool(string $tool, string $intent, float $confidence, string $route, array $entities, string $reason): array
    {
        return compact('tool', 'intent', 'confidence', 'route', 'entities', 'reason');
    }

    private function isAssignmentRecapQuestion(string $normalized): bool
    {
        return $this->containsAny($normalized, ['summary', 'summarize', 'ringkas', 'rangkum', 'recap', 'rekap', 'rekapan'])
            && $this->containsAny($normalized, ['assignment', 'survey', 'pln', 'construction', 'bast', 'outstanding']);
    }

    private function isCountQuestion(string $normalized): bool
    {
        return $this->containsAny($normalized, ['berapa lokasi', 'berapa site', 'berapa assignment', 'berapa', 'how many location', 'how many site', 'how many assign', 'how many', 'jumlah lokasi', 'jumlah site', 'jumlah assignment', 'jumlah', 'ada berapa'])
            && $this->containsAny($normalized, ['lokasi', 'location', 'site', 'assignment', 'assign', 'pln', 'survey', 'survei', 'construction', 'konstruksi', 'bast', 'subcon', 'subkon', 'main contractor', 'main con']);
    }

    private function isMachineTypeCountQuestion(string $normalized): bool
    {
        return ($this->containsAny($normalized, ['machine type', 'tipe mesin']) && $this->containsAny($normalized, ['bss', 'evcs']))
            || ($this->containsAny($normalized, ['machine type', 'tipe mesin', 'mesin', 'bss', 'evcs'])
                && $this->containsAny($normalized, ['berapa', 'jumlah', 'lokasi', 'location', 'site', 'masing-masing', 'masing masing']));
    }

    /** @param array<string, mixed> $context */
    private function shouldResolveUnknownDomainQuestion(string $normalized, array $context): bool
    {
        if ($this->hasRecordContext($context)) {
            return $this->containsAny($normalized, [
                'ini',
                'record',
                'halaman',
                'cek',
                'lihat',
                'review',
                'bahas',
                'status',
                'masalah',
            ]);
        }

        return $this->isDatabaseRelated($normalized, $this->detectedEntities($normalized))
            && $this->containsAny($normalized, [
                'apa',
                'which',
                'mana',
                'cek',
                'lihat',
                'review',
                'bahas',
                'tentang',
                'about',
                'detail',
                'rincian',
                'kenapa',
                'mengapa',
                'bagaimana',
                'gimana',
            ]);
    }

    /** @param list<string> $entities */
    private function isDatabaseRelated(string $normalized, array $entities): bool
    {
        return $entities !== []
            || $this->containsAny($normalized, [
                'project',
                'proyek',
                'site',
                'lokasi',
                'assignment',
                'assign',
                'subcon',
                'subkon',
                'subcontractor',
                'vendor',
                'main con',
                'main contractor',
                'machine type',
                'tipe mesin',
                'mesin',
                'bss',
                'evcs',
                'report',
                'laporan',
                'workflow',
                'client',
                'clients',
                'financial',
                'financials',
                'audit',
            ]);
    }

    private function looksLikeNamedEntityQuestion(string $message, string $normalized): bool
    {
        if (! $this->containsAny($normalized, ['progress', 'progres', 'gimana', 'bagaimana', 'status', 'bahas', 'review', 'cek'])) {
            return false;
        }

        return preg_match('/\b[A-Z][a-zA-Z0-9]+(?:\s+[A-Z][a-zA-Z0-9]+)+\b/', $message) === 1;
    }

    /**
     * @return list<string>
     */
    private function detectedEntities(string $normalized): array
    {
        $entities = [];

        foreach ([
            'project' => ['project', 'proyek'],
            'site' => ['site', 'lokasi'],
            'assignment' => ['assignment', 'assign'],
            'subcontractor' => ['subcon', 'subkon', 'subcontractor', 'vendor'],
            'main_contractor' => ['main con', 'main contractor'],
            'machine_type' => ['machine type', 'tipe mesin', 'mesin', 'bss', 'evcs'],
            'report' => ['report', 'laporan'],
            'workflow' => ['workflow', 'status'],
            'client' => ['client', 'clients'],
            'financial' => ['financial', 'financials'],
            'audit' => ['audit'],
        ] as $entity => $needles) {
            if ($this->containsAny($normalized, $needles)) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /** @param array<string, mixed> $context */
    private function hasRecordContext(array $context): bool
    {
        return filled($context['assignment_id'] ?? null)
            || filled($context['site_id'] ?? null)
            || filled($context['project_id'] ?? null)
            || in_array($context['type'] ?? null, ['assignment', 'site', 'project'], true);
    }

    /** @param list<string> $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        return Str::contains($haystack, $needles);
    }
}
