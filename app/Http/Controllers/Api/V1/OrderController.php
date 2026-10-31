<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderMessage;
use App\Domain\Order\Services\OrderService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmHandoverRequest;
use App\Http\Requests\SendOrderMessageRequest;
use App\Http\Requests\SubmitArrangementRequest;
use App\Http\Resources\OrderMessageResource;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * List user's purchases and sales.
     */
    public function myOrders(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $orders = Order::with(['auction', 'buyer', 'seller'])
            ->where(function ($q) use ($userId) {
                $q->where('buyer_id', $userId)
                    ->orWhere('seller_id', $userId);
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'orders' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Get order details.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $order = Order::with(['auction', 'buyer', 'seller'])
            ->where(function ($q) use ($userId) {
                $q->where('buyer_id', $userId)->orWhere('seller_id', $userId);
            })
            ->findOrFail($id);

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    /**
     * Seller enters the 6-digit handover code to trigger payout.
     */
    public function confirmHandover(int $id, ConfirmHandoverRequest $request): JsonResponse
    {
        $order = Order::with(['buyer', 'seller'])->findOrFail($id);

        $settled = $this->orderService->confirmHandoverWithCode(
            order: $order,
            seller: $request->user(),
            code: $request->input('code')
        );

        return response()->json([
            'message' => 'Handover code verified! Payout released to seller wallet.',
            'order' => new OrderResource($settled->load(['auction', 'buyer', 'seller'])),
        ]);
    }

    /**
     * Buyer explicitly marks item as received.
     */
    public function markReceived(int $id, Request $request): JsonResponse
    {
        $order = Order::with(['buyer', 'seller'])->findOrFail($id);

        $settled = $this->orderService->buyerMarkReceived(
            order: $order,
            buyer: $request->user()
        );

        return response()->json([
            'message' => 'Item marked as received. Escrow released to seller.',
            'order' => new OrderResource($settled->load(['auction', 'buyer', 'seller'])),
        ]);
    }

    /**
     * Submit pickup/delivery arrangement details.
     */
    public function submitArrangement(int $id, SubmitArrangementRequest $request): JsonResponse
    {
        $order = Order::findOrFail($id);

        $updated = $this->orderService->submitArrangement(
            order: $order,
            user: $request->user(),
            type: $request->input('type'),
            details: [
                'location' => $request->input('location'),
                'scheduled_time' => $request->input('scheduled_time'),
                'notes' => $request->input('notes'),
            ]
        );

        return response()->json([
            'message' => 'Arrangement details updated.',
            'order' => new OrderResource($updated->load(['auction', 'buyer', 'seller'])),
        ]);
    }

    /**
     * Fetch order chat messages.
     */
    public function messages(int $id, Request $request): JsonResponse
    {
        $order = Order::findOrFail($id);
        $userId = $request->user()->id;

        if ($userId !== $order->buyer_id && $userId !== $order->seller_id) {
            abort(403);
        }

        $messages = OrderMessage::with('sender:id,name')
            ->where('order_id', $order->id)
            ->oldest('id')
            ->get();

        return response()->json([
            'messages' => OrderMessageResource::collection($messages),
        ]);
    }

    /**
     * Send order chat message.
     */
    public function sendMessage(int $id, SendOrderMessageRequest $request): JsonResponse
    {
        $order = Order::findOrFail($id);

        $message = $this->orderService->sendMessage(
            order: $order,
            sender: $request->user(),
            body: $request->input('body'),
            attachments: $request->input('attachments')
        );

        return response()->json([
            'message' => new OrderMessageResource($message->load('sender:id,name')),
        ], 201);
    }
}
