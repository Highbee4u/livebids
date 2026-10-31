<?php

declare(strict_types=1);

namespace App\Domain\Reputation\Services;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Reputation\Models\Review;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReputationService
{
    /**
     * Submit a post-order review and recalculate trust score.
     *
     * @throws BusinessRuleException
     */
    public function submitReview(Order $order, User $reviewer, int $rating, ?string $comment = null): Review
    {
        if ($order->status !== OrderStatus::Completed) {
            throw new BusinessRuleException('Reviews can only be submitted after an order is successfully completed.', 'ORDER_NOT_COMPLETED', 422);
        }

        if ($reviewer->id !== $order->buyer_id && $reviewer->id !== $order->seller_id) {
            throw new BusinessRuleException('Only participants in this transaction can leave a review.', 'FORBIDDEN', 403);
        }

        $revieweeId = ($reviewer->id === $order->buyer_id) ? $order->seller_id : $order->buyer_id;

        // Check if reviewer already reviewed this order
        $existing = Review::where('order_id', $order->id)
            ->where('reviewer_id', $reviewer->id)
            ->exists();

        if ($existing) {
            throw new BusinessRuleException('You have already submitted a review for this order.', 'REVIEW_ALREADY_EXISTS', 409);
        }

        return DB::transaction(function () use ($order, $reviewer, $revieweeId, $rating, $comment) {
            $review = Review::create([
                'order_id' => $order->id,
                'reviewer_id' => $reviewer->id,
                'reviewee_id' => $revieweeId,
                'rating' => $rating,
                'comment' => $comment,
                'created_at' => now(),
            ]);

            // Recalculate reviewee average rating
            $targetUser = User::findOrFail($revieweeId);
            $avgRating = Review::where('reviewee_id', $revieweeId)->avg('rating') ?? 5.0;
            $count = Review::where('reviewee_id', $revieweeId)->count();

            $targetUser->update([
                'rating' => round($avgRating, 2),
                'review_count' => $count,
            ]);

            return $review;
        });
    }
}
