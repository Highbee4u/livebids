<?php

declare(strict_types=1);

namespace App\Domain\Video\Models;

use App\Domain\Auction\Models\Auction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'room_name',
        'provider',
        'recording_url',
        'peak_viewers',
        'started_at',
        'ended_at',
        'is_killed',
        'killed_by',
    ];

    protected function casts(): array
    {
        return [
            'peak_viewers' => 'integer',
            'is_killed' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function killer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'killed_by');
    }
}
