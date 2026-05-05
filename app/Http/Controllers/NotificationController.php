<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function markRead(string $id): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $user->notifications()->findOrFail($id)->markAsRead();

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $user->unreadNotifications->markAsRead();

        return back();
    }
}
