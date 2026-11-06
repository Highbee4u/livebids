<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Terms\Services\TermsService;
use App\Http\Resources\TermsResource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTermsAcceptedMiddleware
{
    public function __construct(
        protected TermsService $termsService
    ) {}

    /**
     * Handle an incoming request.
     *
     * Usage:
     *   middleware('terms.accepted')               -> Checks all active required terms
     *   middleware('terms.accepted:bidding_escrow') -> Checks required terms of type 'bidding_escrow'
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $type = null): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // 1. Contextual / Progressive check for a specific terms type
        if ($type) {
            $requiredTerm = $this->termsService->getActiveTermsByType($type);

            if ($requiredTerm && $requiredTerm->is_required && ! $this->termsService->hasAcceptedTerms((int) $user->id, $requiredTerm->id)) {
                return response()->json([
                    'message' => "You must review and accept the {$requiredTerm->title} before proceeding.",
                    'error_code' => 'TERMS_ACCEPTANCE_REQUIRED',
                    'required_term' => new TermsResource($requiredTerm),
                ], 403);
            }

            return $next($request);
        }

        // 2. Global check for all pending mandatory terms
        $pending = $this->termsService->getPendingTermsForUser((int) $user->id);

        if ($pending->isNotEmpty()) {
            return response()->json([
                'message' => 'You must accept the updated terms and conditions before proceeding.',
                'error_code' => 'TERMS_ACCEPTANCE_REQUIRED',
                'pending_terms' => TermsResource::collection($pending),
            ], 403);
        }

        return $next($request);
    }
}
