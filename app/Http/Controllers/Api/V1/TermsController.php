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

        return $this->successResponse(
            TermsResource::collection($terms),
            'Active terms and conditions retrieved successfully.'
        );
    }

    /**
     * Get the latest active terms of a specific type.
     */
    public function show(string $type): JsonResponse
    {
        $terms = $this->termsService->getActiveTermsByType($type);

        if (! $terms) {
            return $this->errorResponse(
                "No active terms found for type: {$type}",
                404,
                'TERMS_NOT_FOUND'
            );
        }

        return $this->successResponse(
            new TermsResource($terms),
            'Terms and conditions retrieved successfully.'
        );
    }

    /**
     * View version history of a specific terms type.
     */
    public function versions(string $type): JsonResponse
    {
        $history = $this->termsService->getVersionHistory($type);

        return $this->successResponse(
            TermsResource::collection($history),
            'Terms version history retrieved successfully.'
        );
    }

    /**
     * Get all active required terms the authenticated user has NOT yet accepted.
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type');

        $pending = $this->termsService->getPendingTermsForUser((int) $user->id, $type);

        return $this->successResponse(
            TermsResource::collection($pending),
            'Pending terms and conditions retrieved successfully.'
        );
    }

    /**
     * View authenticated user's terms acceptance history.
     */
    public function myAcceptances(Request $request): JsonResponse
    {
        $user = $request->user();
        $acceptances = $this->termsService->getUserAcceptances((int) $user->id);

        return $this->successResponse(
            UserTermsAcceptanceResource::collection($acceptances),
            'User terms acceptances retrieved successfully.'
        );
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

        return $this->successResponse(
            new UserTermsAcceptanceResource($acceptance->load('terms')),
            'Terms and conditions accepted successfully.'
        );
    }
}
