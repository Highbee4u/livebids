<?php

declare(strict_types=1);

namespace App\Domain\Terms\Services;

use App\Domain\Terms\Enums\TermsType;
use App\Domain\Terms\Models\TermsAndCondition;
use App\Domain\Terms\Models\UserTermsAcceptance;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TermsService
{
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Get all active and effective terms (one per type).
     *
     * @return Collection<int, TermsAndCondition>
     */
    public function getAllActiveTerms(): Collection
    {
        $data = Cache::remember('terms:all:active', self::CACHE_TTL, function (): array {
            return TermsAndCondition::active()
                ->effective()
                ->orderBy('type')
                ->latest('id')
                ->get()
                ->unique('type')
                ->values()
                ->toArray();
        });

        return TermsAndCondition::hydrate($data);
    }

    /**
     * Get active terms for a specific type.
     */
    public function getActiveTermsByType(TermsType|string $type): ?TermsAndCondition
    {
        $typeVal = $type instanceof TermsType ? $type->value : $type;
        $cacheKey = "terms:type:{$typeVal}:active";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($typeVal): ?array {
            $terms = TermsAndCondition::active()
                ->ofType($typeVal)
                ->effective()
                ->latest('id')
                ->first();

            return $terms ? $terms->toArray() : null;
        });

        if (! $data) {
            return null;
        }

        return TermsAndCondition::hydrate([$data])->first();
    }

    /**
     * Get all versions of a specific terms type (ordered by latest).
     *
     * @return Collection<int, TermsAndCondition>
     */
    public function getVersionHistory(TermsType|string $type): Collection
    {
        $typeVal = $type instanceof TermsType ? $type->value : $type;

        return TermsAndCondition::ofType($typeVal)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Find terms by ID.
     */
    public function findTermsById(int $termsId): ?TermsAndCondition
    {
        return TermsAndCondition::find($termsId);
    }

    /**
     * Record user consent/acceptance for a specific terms version.
     *
     * @throws BusinessRuleException
     */
    public function acceptTerms(int $userId, int $termsId, ?string $ipAddress = null, ?string $userAgent = null): UserTermsAcceptance
    {
        $terms = $this->findTermsById($termsId);

        if (! $terms) {
            throw new BusinessRuleException('Terms and conditions not found.', 'TERMS_NOT_FOUND', 404);
        }

        if (! $terms->is_active) {
            throw new BusinessRuleException('These terms and conditions are not active or have been superseded.', 'TERMS_NOT_ACTIVE', 422);
        }

        return UserTermsAcceptance::updateOrCreate(
            [
                'user_id' => $userId,
                'terms_id' => $termsId,
            ],
            [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? Str::limit($userAgent, 255, '') : null,
                'accepted_at' => now(),
            ]
        );
    }

    /**
     * Check if a user has accepted a specific terms version.
     */
    public function hasAcceptedTerms(int $userId, int $termsId): bool
    {
        return UserTermsAcceptance::where('user_id', $userId)
            ->where('terms_id', $termsId)
            ->exists();
    }

    /**
     * Check if a user has accepted the latest active terms of a given type.
     */
    public function hasAcceptedLatestTermsOfType(int $userId, TermsType|string $type): bool
    {
        $terms = $this->getActiveTermsByType($type);

        if (! $terms || ! $terms->is_required) {
            return true;
        }

        return $this->hasAcceptedTerms($userId, $terms->id);
    }

    /**
     * Get all active required terms that the user has NOT accepted yet.
     *
     * @return Collection<int, TermsAndCondition>
     */
    public function getPendingTermsForUser(int $userId, ?string $type = null): Collection
    {
        $activeTerms = $this->getAllActiveTerms();

        if ($type) {
            $typeVal = $type instanceof TermsType ? $type->value : $type;
            $activeTerms = $activeTerms->where('type', $typeVal);
        }

        // Filter only mandatory terms where user lacks acceptance
        $unaccepted = $activeTerms->filter(function (TermsAndCondition $terms) use ($userId): bool {
            if (! $terms->is_required) {
                return false;
            }

            return ! $this->hasAcceptedTerms($userId, $terms->id);
        })->values();

        return new Collection($unaccepted->all());
    }

    /**
     * Get user's complete terms acceptance history.
     *
     * @return Collection<int, UserTermsAcceptance>
     */
    public function getUserAcceptances(int $userId): Collection
    {
        return UserTermsAcceptance::with('terms')
            ->where('user_id', $userId)
            ->latest('accepted_at')
            ->get();
    }

    /**
     * Admin: Create a new terms draft.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTerms(array $data, ?int $adminId = null): TermsAndCondition
    {
        $typeVal = $data['type'] instanceof TermsType ? $data['type']->value : $data['type'];
        $slug = Str::slug("{$typeVal}-v{$data['version']}-".Str::random(6));

        $terms = TermsAndCondition::create([
            'title' => $data['title'],
            'slug' => $slug,
            'type' => $typeVal,
            'version' => $data['version'],
            'content' => $data['content'],
            'summary_of_changes' => $data['summary_of_changes'] ?? null,
            'is_active' => $data['is_active'] ?? false,
            'is_required' => $data['is_required'] ?? true,
            'effective_date' => $data['effective_date'] ?? now(),
            'created_by' => $adminId,
        ]);

        if ($terms->is_active) {
            $this->archiveOlderVersions($terms);
            $this->clearCache();
        }

        return $terms;
    }

    /**
     * Admin: Update draft terms.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws BusinessRuleException
     */
    public function updateTerms(int $termsId, array $data): TermsAndCondition
    {
        $terms = $this->findTermsById($termsId);

        if (! $terms) {
            throw new BusinessRuleException('Terms not found.', 'TERMS_NOT_FOUND', 404);
        }

        $terms->update(array_filter([
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? null,
            'summary_of_changes' => $data['summary_of_changes'] ?? null,
            'is_required' => array_key_exists('is_required', $data) ? (bool) $data['is_required'] : null,
            'effective_date' => $data['effective_date'] ?? null,
        ], fn ($val) => $val !== null));

        $this->clearCache();

        return $terms->fresh();
    }

    /**
     * Admin: Publish and activate a terms version, archiving previous active ones.
     *
     * @throws BusinessRuleException
     */
    public function publishVersion(int $termsId): TermsAndCondition
    {
        $terms = $this->findTermsById($termsId);

        if (! $terms) {
            throw new BusinessRuleException('Terms not found.', 'TERMS_NOT_FOUND', 404);
        }

        DB::transaction(function () use ($terms): void {
            $this->archiveOlderVersions($terms);

            $terms->update([
                'is_active' => true,
                'effective_date' => $terms->effective_date ?? now(),
            ]);
        });

        $this->clearCache();

        return $terms->fresh();
    }

    /**
     * Admin: Toggle mandatory enforcement flag.
     *
     * @throws BusinessRuleException
     */
    public function toggleEnforcement(int $termsId, bool $isRequired): TermsAndCondition
    {
        $terms = $this->findTermsById($termsId);

        if (! $terms) {
            throw new BusinessRuleException('Terms not found.', 'TERMS_NOT_FOUND', 404);
        }

        $terms->update(['is_required' => $isRequired]);
        $this->clearCache();

        return $terms->fresh();
    }

    /**
     * Admin: Get acceptance compliance statistics for a terms version.
     *
     * @return array{terms: TermsAndCondition, total_accepted: int, total_users: int, acceptance_percentage: float}
     *
     * @throws BusinessRuleException
     */
    public function getAcceptanceMetrics(int $termsId): array
    {
        $terms = $this->findTermsById($termsId);

        if (! $terms) {
            throw new BusinessRuleException('Terms not found.', 'TERMS_NOT_FOUND', 404);
        }

        $totalUsers = User::count();
        $totalAccepted = UserTermsAcceptance::where('terms_id', $termsId)->count();
        $percentage = $totalUsers > 0 ? round(($totalAccepted / $totalUsers) * 100, 2) : 0.0;

        return [
            'terms' => $terms,
            'total_accepted' => $totalAccepted,
            'total_users' => $totalUsers,
            'acceptance_percentage' => $percentage,
        ];
    }

    /**
     * Admin: Paginated acceptance audit log.
     */
    public function getAuditLog(int $termsId, int $perPage = 20): LengthAwarePaginator
    {
        return UserTermsAcceptance::with('user:id,name,phone,email')
            ->where('terms_id', $termsId)
            ->latest('accepted_at')
            ->paginate($perPage);
    }

    /**
     * Deactivate older versions of the same terms type.
     */
    protected function archiveOlderVersions(TermsAndCondition $current): void
    {
        TermsAndCondition::where('type', $current->type instanceof TermsType ? $current->type->value : $current->type)
            ->where('id', '!=', $current->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * Flush all cached terms.
     */
    public function clearCache(): void
    {
        Cache::forget('terms:all:active');

        foreach (TermsType::values() as $type) {
            Cache::forget("terms:type:{$type}:active");
        }
    }
}
