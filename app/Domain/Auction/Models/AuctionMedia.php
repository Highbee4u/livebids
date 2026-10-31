<?php

declare(strict_types=1);

namespace App\Domain\Auction\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'file_path',
        'media_type',
        'mime_type',
        'file_size',
        'status',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }
}
