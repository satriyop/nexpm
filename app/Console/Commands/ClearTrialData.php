<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClearTrialData extends Command
{
    protected $signature = 'app:clear-trial-data
                            {--dry-run : Preview what will be deleted without making any changes}
                            {--force  : Skip the confirmation prompt (for CI/scripts)}';

    protected $description = 'Remove all trial site/assignment data while preserving users, projects, and configuration.';

    /**
     * Storage directories that hold trial-uploaded files.
     * logos/ is intentionally excluded — contractor and client logos are kept.
     */
    private const UPLOAD_DIRS = ['survey', 'pln', 'construction', 'bast', 'legacy-reports'];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->newLine();
        $this->line($isDryRun
            ? '  <fg=cyan>DRY RUN — no changes will be made</>'
            : '  <fg=yellow>This will permanently delete trial data from the database and storage.</>');
        $this->newLine();

        // ── Gather counts ────────────────────────────────────────────────────
        $counts = [
            'assignment_bast_photos' => DB::table('assignment_bast_photos')->count(),
            'assignment_construction_photos' => DB::table('assignment_construction_photos')->count(),
            'site_photos' => DB::table('site_photos')->count(),
            'assignment_bast_data' => DB::table('assignment_bast_data')->count(),
            'assignment_construction_data' => DB::table('assignment_construction_data')->count(),
            'assignment_survey_data' => DB::table('assignment_survey_data')->count(),
            'assignment_pln_data' => DB::table('assignment_pln_data')->count(),
            'report_assignments' => DB::table('report_assignments')->count(),
            'reports' => DB::table('reports')->count(),
            'assignment_audit_logs' => DB::table('assignment_audit_logs')->count(),
            'notifications' => DB::table('notifications')->count(),
            'assignments' => DB::table('assignments')->count(),
            'sites' => DB::table('sites')->count(),
        ];

        $storageFiles = collect(self::UPLOAD_DIRS)
            ->mapWithKeys(fn (string $dir) => [
                $dir => count(Storage::disk('public')->allFiles($dir)),
            ]);

        // ── Preview table ────────────────────────────────────────────────────
        $rows = array_merge(
            collect($counts)
                ->map(fn ($n, $table) => [$table, number_format($n)])
                ->values()
                ->all(),
            $storageFiles
                ->map(fn ($n, $dir) => ["storage/public/{$dir}/", number_format($n).' files'])
                ->values()
                ->all(),
        );

        $this->table(['Table / Directory', 'Rows / Files'], $rows);

        $this->newLine();
        $this->line('  <fg=green>Preserved:</> users · main_contractors · clients · projects');
        $this->line('             subcontractors · site_types · machine_types · app_settings · logos');
        $this->newLine();

        if ($isDryRun) {
            $this->info('  Dry run complete. No changes made.');

            return self::SUCCESS;
        }

        // ── Confirm ──────────────────────────────────────────────────────────
        if (! $this->option('force')) {
            $answer = $this->ask('Type "yes" to proceed');

            if ($answer !== 'yes') {
                $this->info('  Aborted.');

                return self::FAILURE;
            }
        }

        // ── Delete DB rows in FK-safe order (wrapped in a transaction) ────────
        $this->info('  Deleting database rows…');

        DB::transaction(function () {
            // Children first, then parents
            DB::table('assignment_bast_photos')->delete();
            DB::table('assignment_construction_photos')->delete();
            DB::table('site_photos')->delete();
            DB::table('assignment_bast_data')->delete();
            DB::table('assignment_construction_data')->delete();
            DB::table('assignment_survey_data')->delete();
            DB::table('assignment_pln_data')->delete();
            DB::table('assignment_legacy_reports')->delete();
            DB::table('report_assignments')->delete();
            DB::table('reports')->delete();
            DB::table('assignment_audit_logs')->delete();
            DB::table('notifications')->delete();
            DB::table('assignments')->delete();
            DB::table('sites')->delete();
        });

        // ── Delete storage files only after DB commit succeeds ────────────────
        $this->info('  Deleting uploaded files…');

        foreach (self::UPLOAD_DIRS as $dir) {
            $files = Storage::disk('public')->allFiles($dir);

            foreach ($files as $file) {
                Storage::disk('public')->delete($file);
            }

            $this->line("    Cleared {$dir}/ (".count($files).' files)');
        }

        $this->newLine();
        $this->info('  ✓ Trial data cleared. Schema and all configuration untouched.');
        $this->line('  The database is ready for production import.');
        $this->newLine();

        return self::SUCCESS;
    }
}
