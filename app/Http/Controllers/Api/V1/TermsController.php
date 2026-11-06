<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Terms\Services\TermsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptTermsRequest;
use App\Http\Resources\TermsResource;
use App\Http\Resources\UserTermsAcceptanceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TermsController extends Controller
{
    public function __construct(
        protected TermsService $termsService
    ) {}

    /**
     * List all latest active terms across categories.
     */
    public function index(): JsonResponse
    {
        $terms = $this->termsService->getAllActiveTerms();

        return response()->json([
            'status' => 'success',
            'data' => TermsResource::collection($terms),
        ]);
    }

    /**
     * Get the latest active terms of a specific type.
     */
    public function show(string $type): JsonResponse
    {
        $terms = $this->termsService->getActiveTermsByType($type);

        if (! $terms) {
            return response()->json([
                'status' => 'error',
                'message' => "No active terms found for type: {$type}",
                'error_code' => 'TERMS_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => new TermsResource($terms),
        ]);
    }

    /**
     * View version history of a specific terms type.
     */
    public function versions(string $type): JsonResponse
    {
        $history = $this->termsService->getVersionHistory($type);

        return response()->json([
            'status' => 'success',
            'data' => TermsResource::collection($history),
        ]);
    }

    /**
     * Get all active required terms the authenticated user has NOT yet accepted.
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type');

        $pending = $this->termsService->getPendingTermsForUser((int) $user->id, $type);

        return response()->json([
            'status' => 'success',
            'data' => TermsResource::collection($pending),
        ]);
    }

    /**
     * View authenticated user's terms acceptance history.
     */
    public function myAcceptances(Request $request): JsonResponse
    {
        $user = $request->user();
        $acceptances = $this->termsService->getUserAcceptances((int) $user->id);

        return response()->json([
            'status' => 'success',
            'data' => UserTermsAcceptanceResource::collection($acceptances),
        ]);
    }

    /**
     * Record user consent/acceptance of a terms version.
     */
    public function accept(AcceptTermsRequest $request): JsonResponse
    {
        $user = $request->user();

        $acceptance = $this->termsService->acceptTerms(
            userId: (int) $user->id,
            termsId: (int) $request->input('terms_id'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Terms and conditions accepted successfully.',
            'data' => new UserTermsAcceptanceResource($acceptance->load('terms')),
        ]);
    }
}
