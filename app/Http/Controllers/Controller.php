<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;

abstract class Controller
{
    protected function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    protected function ensureCanAccessMainContractor(?int $mainContractorId): void
    {
        $user = $this->currentUser();

        abort_unless(
            $user->isGlobalAdmin()
            || ($mainContractorId !== null && $user->main_contractor_id === $mainContractorId),
            403,
            'Unauthorized tenant access.'
        );
    }

    protected function ensureCanAccessProject(Project $project): void
    {
        $this->ensureCanAccessMainContractor($project->main_contractor_id);
    }

    protected function ensureCanAccessSite(Site $site): void
    {
        $site->loadMissing('project');

        $this->ensureCanAccessProject($site->project);
    }

    protected function ensureCanAccessAssignment(Assignment $assignment): void
    {
        $assignment->loadMissing('site.project');

        $this->ensureCanAccessSite($assignment->site);
    }

    protected function ensureCanAccessReport(Report $report): void
    {
        $user = $this->currentUser();

        if ($user->isGlobalAdmin()) {
            return;
        }

        abort_if(
            $report->assignments()
                ->whereDoesntHave('site.project', fn ($query) => $query->where('main_contractor_id', $user->main_contractor_id))
                ->exists(),
            403,
            'Unauthorized tenant access.'
        );
    }
}
