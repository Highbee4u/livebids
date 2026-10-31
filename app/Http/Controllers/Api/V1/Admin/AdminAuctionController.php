<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Auction\Models\Auction;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Video\Services\VideoTokenService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuctionController extends Controller
{
    public function __construct(
        protected VideoTokenService $videoTokenService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Emergency kill-switch: terminate a rogue or policy-violating live video stream immediately.
     */
    public function killLiveStream(int $id, Request $request): JsonResponse
    {
        $auction = Auction::findOrFail($id);

        $killed = $this->videoTokenService->killStream($auction, $request->user());

        $this->auditLogService->log(
            admin: $request->user(),
            action: 'killed_live_stream',
            targetType: 'Auction',
            targetId: (string) $auction->id,
            after: ['is_killed' => true]
        );

        return response()->json([
            'message' => 'Live stream terminated immediately.',
            'killed' => $killed,
        ]);
    }
}
