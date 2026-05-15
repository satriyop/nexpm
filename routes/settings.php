<?php

use App\Http\Controllers\Settings\AiAssistantSettingsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/ai', [AiAssistantSettingsController::class, 'edit'])->name('ai-settings.edit');
    Route::patch('settings/ai', [AiAssistantSettingsController::class, 'update'])->name('ai-settings.update');
    Route::delete('settings/ai/conversations/{conversation}', [AiAssistantSettingsController::class, 'destroyConversation'])->name('ai-settings.conversations.destroy');
});
