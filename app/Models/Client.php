<?php

namespace App\Models;

use App\Models\Concerns\ScopedToMainContractor;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'phone', 'email', 'pic', 'logo'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, ScopedToMainContractor;

    protected $appends = ['logo_url'];

    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo ? Storage::url($this->logo) : null);
    }

    /**
     * @return BelongsToMany<MainContractor, $this>
     */
    public function mainContractors(): BelongsToMany
    {
        return $this->belongsToMany(MainContractor::class, 'client_main_contractor');
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
