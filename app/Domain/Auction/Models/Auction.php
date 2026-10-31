<?php

declare(strict_types=1);

namespace App\Domain\Auction\Models;

use App\Domain\Auction\Enums\AuctionCondition;
use App\Domain\Auction\Enums\AuctionStatus;
use App\Domain\Notification\Models\Reminder;
use App\Domain\Video\Models\LiveSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Auction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'seller_id',
        'category_id',
        'title',
        'description',
        'condition',
        'teaser_video_url',
        'thumbnail_url',
        'starting_bid',
        'reserve_price',
        'pickup_area',
        'delivery_options',
        'scheduled_start_at',
        'live_duration_seconds',
        'status',
        'live_started_at',
        'live_ended_at',
        'winning_bid_id',
        'winning_amount',
        'extensions_count',
        'decision_window_ends_at',
        'skipped_higher_bid',
    ];

    protected function casts(): array
    {
        return [
            'condition' => AuctionCondition::class,
            'status' => AuctionStatus::class,
            'starting_bid' => 'integer',
            'reserve_price' => 'integer',
            'winning_amount' => 'integer',
            'extensions_count' => 'integer',
            'delivery_options' => 'array',
            'scheduled_start_at' => 'datetime',
            'live_started_at' => 'datetime',
            'live_ended_at' => 'datetime',
            'decision_window_ends_at' => 'datetime',
            'skipped_higher_bid' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function winningBid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'winning_bid_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(AuctionMedia::class);
    }

    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    public function latestLiveSession(): HasOne
    {
        return $this->hasOne(LiveSession::class)->latestOfMany();
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AuctionEvent::class);
    }

    public function isLive(): bool
    {
        return $this->status === AuctionStatus::Live;
    }

    public function isScheduled(): bool
    {
        return $this->status === AuctionStatus::Scheduled;
    }

    public function isSold(): bool
    {
        return $this->status === AuctionStatus::Sold;
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', AuctionStatus::Scheduled)
            ->where('scheduled_start_at', '>=', now())
            ->orderBy('scheduled_start_at');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', AuctionStatus::Live)
            ->orderBy('live_started_at', 'desc');
    }

    public function getHighestBid(): ?Bid
    {
        return $this->bids()->orderByDesc('amount')->first();
    }
}
