<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auction\Models\Auction;
use App\Domain\Auction\Services\AuctionStateService;
use App\Domain\Notification\Models\Reminder;
use App\Domain\Video\Services\VideoTokenService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAuctionRequest;
use App\Http\Requests\SelectWinnerRequest;
use App\Http\Resources\AuctionResource;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuctionController extends Controller
{
    public function __construct(
        protected AuctionStateService $auctionStateService,
        protected VideoTokenService $videoTokenService
    ) {}

    /**
     * Create and schedule a new live auction listing.
     */
    public function create(CreateAuctionRequest $request): JsonResponse
    {
        $auction = $this->auctionStateService->createAuction(
            seller: $request->user(),
            data: $request->validated()
        );

        return response()->json([
            'message' => 'Auction created and scheduled successfully.',
            'auction' => new AuctionResource($auction->load(['seller', 'category'])),
        ], 201);
    }

    /**
     * Get auction details.
     */
    public function show(int $id): JsonResponse
    {
        $auction = Auction::with(['seller', 'category'])->findOrFail($id);

        return response()->json([
            'auction' => new AuctionResource($auction),
        ]);
    }

    /**
     * Seller begins the live video stream.
     */
    public function startLive(int $id, Request $request): JsonResponse
    {
        $auction = Auction::findOrFail($id);

        $videoInfo = $this->auctionStateService->startLive($auction, $request->user());

        return response()->json([
            'message' => 'Live session started.',
            'auction' => new AuctionResource($auction->fresh(['seller', 'category'])),
            'video' => $videoInfo,
        ]);
    }

    /**
     * Viewer joins the live video stream.
     */
    public function joinLive(int $id, Request $request): JsonResponse
    {
        $auction = Auction::findOrFail($id);

        $videoInfo = $this->videoTokenService->getSubscriberToken($auction, $request->user());

        return response()->json([
            'message' => 'Joined live session.',
            'auction' => new AuctionResource($auction->load(['seller', 'category'])),
            'video' => $videoInfo,
        ]);
    }

    /**
     * Seller picks winning bid, closing auction instantly.
     */
    public function selectWinner(int $id, SelectWinnerRequest $request): JsonResponse
    {
        $auction = Auction::findOrFail($id);

        $order = $this->auctionStateService->selectWinner(
            auction: $auction,
            seller: $request->user(),
            bidId: $request->integer('bid_id')
        );

        return response()->json([
            'message' => 'Winner selected! Auction marked as sold and escrow created.',
            'order' => new OrderResource($order->load(['auction', 'buyer', 'seller'])),
        ]);
    }

    /**
     * Seller ends the live session unsold.
     */
    public function endUnsold(int $id, Request $request): JsonResponse
    {
        $auction = Auction::findOrFail($id);

        $this->auctionStateService->endUnsold($auction, $request->user());

        return response()->json([
            'message' => 'Auction ended as unsold. All bids have been released.',
            'auction' => new AuctionResource($auction->fresh(['seller', 'category'])),
        ]);
    }

    /**
     * Set or remove an auction countdown reminder.
     */
    public function toggleReminder(int $id, Request $request): JsonResponse
    {
        $auction = Auction::findOrFail($id);
        $user = $request->user();

        $existing = Reminder::where('user_id', $user->id)
            ->where('auction_id', $auction->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'message' => 'Reminder cancelled.',
                'has_reminder' => false,
            ]);
        }

        Reminder::create([
            'user_id' => $user->id,
            'auction_id' => $auction->id,
        ]);

        return response()->json([
            'message' => 'Reminder set! We will notify you before the auction starts.',
            'has_reminder' => true,
        ]);
    }
}
