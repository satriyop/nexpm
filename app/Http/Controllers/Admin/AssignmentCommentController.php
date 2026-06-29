<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssignmentCommentRequest;
use App\Models\Assignment;
use Illuminate\Http\RedirectResponse;

class AssignmentCommentController extends Controller
{
    public function store(StoreAssignmentCommentRequest $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);

        $assignment->comments()->create([
            'user_id' => $this->currentUser()->id,
            'body' => $request->validated('body'),
        ]);

        return back()->with('success', 'Note added.');
    }
}
