<?php

declare(strict_types=1);

namespace App\Domain\Terms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTermsAcceptance extends Model
{
    use HasFactory;

    protected $table = 'user_terms_acceptances';

    protected $fillable = [
        'user_id',
        'terms_id',
        'ip_address',
        'user_agent',
        'accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * The accepting user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The terms version accepted.
     */
    public function terms(): BelongsTo
    {
        return $this->belongsTo(TermsAndCondition::class, 'terms_id');
    }
}
