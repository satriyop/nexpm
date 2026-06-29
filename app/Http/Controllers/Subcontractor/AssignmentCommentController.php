<?php

namespace App\Http\Controllers\Subcontractor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssignmentCommentRequest;
use App\Models\Assignment;
use Illuminate\Http\RedirectResponse;

class AssignmentCommentController extends Controller
{
    public function store(StoreAssignmentCommentRequest $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureBelongsToCurrentSubcontractor($assignment);

        $assignment->comments()->create([
            'user_id' => $this->currentUser()->id,
            'body' => $request->validated('body'),
        ]);

        return back()->with('success', 'Note added.');
    }

    private function ensureBelongsToCurrentSubcontractor(Assignment $assignment): void
    {
        $user = $this->currentUser();

        abort_unless(
            $user->subcontractor_id !== null && $assignment->subcontractor_id === $user->subcontractor_id,
            403,
            'Assignment does not belong to your sub-contractor.'
        );
    }
}
