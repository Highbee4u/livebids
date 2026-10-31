<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Welfare\Notifications\NotificationDispatcher;

class NotificationService
{
    public function __construct(
        protected NotificationDispatcher $dispatcher
    ) {}

    /**
     * Send auction starting soon reminder to user respecting daily notification caps.
     */
    public function sendAuctionReminder(Reminder $reminder): bool
    {
        $user = $reminder->user;
        $auction = $reminder->auction;

        if (! $user || ! $auction) {
            return false;
        }

        if (! $this->canSendNotification($user)) {
            return false;
        }

        $title = "Auction Starting Soon: {$auction->title}";
        $body = "The auction for {$auction->title} starts at {$auction->scheduled_start_at->toTimeString()}. Get ready to bid!";

        try {
            // Push notification if device token available
            if ($user->fcm_token) {
                $this->dispatcher->sendPush(
                    [$user->fcm_token],
                    $title,
                    $body,
                    ['auction_id' => (string) $auction->id, 'type' => 'auction_reminder']
                );
            } elseif ($user->phone) {
                // Fallback to SMS
                $this->dispatcher->sendSmsNow(
                    $user->phone,
                    "LiveBids: {$body}",
                    'auction_reminder'
                );
            }

            $this->incrementNotificationCount($user);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Check if user is within the daily notification cap.
     */
    public function canSendNotification(User $user): bool
    {
        $maxDaily = config('auction.daily_notification_cap', 5);
        $cacheKey = $this->dailyCountKey($user);

        $currentCount = (int) Cache::get($cacheKey, 0);

        return $currentCount < $maxDaily;
    }

    /**
     * Increment notification count for the day.
     */
    public function incrementNotificationCount(User $user): void
    {
        $cacheKey = $this->dailyCountKey($user);
        $secondsUntilMidnight = now()->endOfDay()->diffInSeconds(now());

        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, 1, max(60, $secondsUntilMidnight));
        } else {
            Cache::increment($cacheKey);
        }
    }

    protected function dailyCountKey(User $user): string
    {
        $today = now()->toDateString();

        return "user_notification_cap:{$user->id}:{$today}";
    }
}
