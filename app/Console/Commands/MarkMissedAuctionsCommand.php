<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Auction\Enums\AuctionStatus;
use App\Domain\Auction\Models\Auction;
use App\Domain\Auction\Services\AuctionStateService;
use Illuminate\Console\Command;

class MarkMissedAuctionsCommand extends Command
{
    protected $signature = 'auctions:mark-missed';

    protected $description = 'Mark scheduled auctions as missed if seller fails to go live within the grace window';

    public function handle(AuctionStateService $auctionStateService): int
    {
        $graceSeconds = (int) config('auction.grace_window_seconds', 600);
        $cutoff = now()->subSeconds($graceSeconds);

        $expiredAuctions = Auction::where('status', AuctionStatus::Scheduled)
            ->where('scheduled_start_at', '<=', $cutoff)
            ->get();

        $count = 0;

        foreach ($expiredAuctions as $auction) {
            $auctionStateService->markMissed($auction);
            $count++;
        }

        $this->info("Marked {$count} auction(s) as missed and issued seller strike(s).");

        return self::SUCCESS;
    }
}
