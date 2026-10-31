<?php

declare(strict_types=1);

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\Models\KycRecord;
use App\Models\User;

interface IdentityServiceInterface
{
    public function submitNinLiveness(
        User $user,
        string $nin,
        ?string $selfieBase64 = null,
        array $consentData = [],
        ?string $dateOfBirth = null
    ): KycRecord;
}
