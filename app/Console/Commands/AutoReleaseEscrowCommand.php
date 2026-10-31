<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderService;
use Illuminate\Console\Command;

class AutoReleaseEscrowCommand extends Command
{
    protected $signature = 'orders:auto-release-escrow';

    protected $description = 'Automatically release escrow funds to sellers when the inspection deadline has passed without dispute';

    public function handle(OrderService $orderService): int
    {
        $eligibleOrders = Order::where('status', OrderStatus::AwaitingHandover)
            ->where('deadline_at', '<=', now())
            ->whereDoesntHave('disputes', function ($query) {
                $query->where('status', 'open');
            })
            ->get();

        $released = 0;

        foreach ($eligibleOrders as $order) {
            if ($orderService->autoReleaseEscrow($order)) {
                $released++;
            }
        }

        $this->info("Auto-released escrow for {$released} order(s).");

        return self::SUCCESS;
    }
}
