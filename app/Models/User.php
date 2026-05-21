<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use App\Models\Concerns\ScopedToMainContractor;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'role', 'main_contractor_id', 'subcontractor_id', 'ai_preferences'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, ScopedToMainContractor, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => Role::class,
            'ai_preferences' => 'array',
        ];
    }

    /** @return array{mode: string, max_rows: int} */
    public function getAiPreferences(): array
    {
        return array_merge(['mode' => 'standard', 'max_rows' => 500], $this->ai_preferences ?? []);
    }

    /**
     * @return BelongsTo<MainContractor, $this>
     */
    public function mainContractor(): BelongsTo
    {
        return $this->belongsTo(MainContractor::class);
    }

    /**
     * @return BelongsTo<Subcontractor, $this>
     */
    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isSubcontractor(): bool
    {
        return $this->role === Role::Subcontractor;
    }

    public function isDrafter(): bool
    {
        return $this->role === Role::Drafter;
    }

    public function isProjectManager(): bool
    {
        return $this->role === Role::ProjectManager;
    }

    public function isGlobalAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->isProjectManager();
    }
}
