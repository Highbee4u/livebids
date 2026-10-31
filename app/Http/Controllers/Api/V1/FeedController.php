<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auction\Models\Auction;
use App\Domain\Auction\Models\Category;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuctionResource;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    /**
     * Get upcoming auctions feed with vertical swipe teasers and countdowns.
     */
    public function upcoming(Request $request): JsonResponse
    {
        $query = Auction::with(['seller', 'category'])
            ->upcoming();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        $auctions = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'feed' => AuctionResource::collection($auctions),
            'pagination' => [
                'current_page' => $auctions->currentPage(),
                'last_page' => $auctions->lastPage(),
                'total' => $auctions->total(),
            ],
        ]);
    }

    /**
     * Get rail of auctions that are currently streaming live.
     */
    public function live(): JsonResponse
    {
        $liveAuctions = Auction::with(['seller', 'category'])
            ->live()
            ->get();

        return response()->json([
            'live_auctions' => AuctionResource::collection($liveAuctions),
        ]);
    }

    /**
     * List active product categories.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'categories' => CategoryResource::collection($categories),
        ]);
    }
}
