<?php

declare(strict_types=1);

namespace App\Domain\Terms\Models;

use App\Domain\Terms\Enums\TermsType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TermsAndCondition extends Model
{
    use HasFactory;

    protected $table = 'terms_and_conditions';

    protected $fillable = [
        'title',
        'slug',
        'type',
        'version',
        'content',
        'summary_of_changes',
        'is_active',
        'is_required',
        'effective_date',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TermsType::class,
            'is_active' => 'boolean',
            'is_required' => 'boolean',
            'effective_date' => 'datetime',
        ];
    }

    /**
     * Acceptances for this terms version.
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(UserTermsAcceptance::class, 'terms_id');
    }

    /**
     * Admin creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope query to only active terms.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query to terms of a specific type.
     */
    public function scopeOfType(Builder $query, TermsType|string $type): Builder
    {
        $val = $type instanceof TermsType ? $type->value : $type;

        return $query->where('type', $val);
    }

    /**
     * Scope query to effective terms (effective_date is null or in the past).
     */
    public function scopeEffective(Builder $query): Builder
    {
        return $query->where(function (Builder $sub): void {
            $sub->whereNull('effective_date')
                ->orWhere('effective_date', '<=', now());
        });
    }

    /**
     * Scope query to required terms.
     */
    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    /**
     * Determine whether these terms are currently effective.
     */
    public function isEffective(): bool
    {
        return $this->effective_date === null || $this->effective_date->isPast();
    }
}
