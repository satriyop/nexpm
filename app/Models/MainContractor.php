<?php

namespace App\Models;

use Database\Factories\MainContractorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'phone', 'email', 'pic', 'logo'])]
class MainContractor extends Model
{
    /** @use HasFactory<MainContractorFactory> */
    use HasFactory;

    protected $appends = ['logo_url'];

    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo ? Storage::url($this->logo) : null);
    }

    /**
     * @return HasMany<Client, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<Subcontractor, $this>
     */
    public function subcontractors(): HasMany
    {
        return $this->hasMany(Subcontractor::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
