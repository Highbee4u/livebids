<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Auction\Enums\AuctionStatus;
use App\Domain\Notification\Models\Reminder;
use App\Domain\Notification\Services\NotificationService;
use Illuminate\Console\Command;

class SendAuctionRemindersCommand extends Command
{
    protected $signature = 'auctions:send-reminders';

    protected $description = 'Dispatch reminders for auctions starting within the configured lead times';

    public function handle(NotificationService $notificationService): int
    {
        $leadMinutes = (int) (config('auction.reminder_lead_minutes')[0] ?? 15);
        $threshold = now()->addMinutes($leadMinutes);

        $reminders = Reminder::with(['auction', 'user'])
            ->whereNull('notified_at')
            ->whereHas('auction', function ($query) use ($threshold) {
                $query->where('status', AuctionStatus::Scheduled)
                    ->where('scheduled_start_at', '<=', $threshold)
                    ->where('scheduled_start_at', '>=', now()->subMinutes(5));
            })
            ->get();

        $dispatched = 0;

        foreach ($reminders as $reminder) {
            $notificationService->sendAuctionReminder($reminder);
            $reminder->update(['notified_at' => now()]);
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} auction reminder(s).");

        return self::SUCCESS;
    }
}
