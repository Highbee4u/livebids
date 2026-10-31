<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auction\Models\Auction;
use App\Domain\Auction\Services\BiddingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceBidRequest;
use App\Http\Resources\BidResource;
use Illuminate\Http\JsonResponse;

class BidController extends Controller
{
    public function __construct(
        protected BiddingService $biddingService
    ) {}

    /**
     * Place or raise a bid on a live auction.
     */
    public function placeBid(int $id, PlaceBidRequest $request): JsonResponse
    {
        $auction = Auction::findOrFail($id);

        $bid = $this->biddingService->placeBid(
            auction: $auction,
            bidder: $request->user(),
            amountKobo: $request->integer('amount_kobo'),
            clientRequestId: $request->input('client_request_id')
        );

        return response()->json([
            'message' => 'Bid placed successfully!',
            'bid' => new BidResource($bid->load('bidder')),
            'leaderboard' => $this->biddingService->getLeaderboardSnapshot($auction->id),
        ], 201);
    }

    /**
     * Get live leaderboard snapshot for an auction.
     */
    public function leaderboard(int $id): JsonResponse
    {
        $snapshot = $this->biddingService->getLeaderboardSnapshot($id);

        return response()->json([
            'auction_id' => $id,
            'leaderboard' => $snapshot,
        ]);
    }
}
