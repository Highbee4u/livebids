<?php

declare(strict_types=1);

namespace App\Domain\Auction\Enums;

enum AuctionStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case InDecisionWindow = 'decision_window';
    case Sold = 'sold';
    case Unsold = 'unsold';
    case Missed = 'missed';
    case Cancelled = 'cancelled';
}
