<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Terms\Models\TermsAndCondition;
use App\Domain\Terms\Services\TermsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateTermsRequest;
use App\Http\Requests\Admin\UpdateTermsRequest;
use App\Http\Resources\AdminTermsDetailResource;
use App\Http\Resources\TermsResource;
use App\Http\Resources\UserTermsAcceptanceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTermsController extends Controller
{
    public function __construct(
        protected TermsService $termsService
    ) {}

    /**
     * Paginated list of terms with optional filtering by type, is_active, is_required.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TermsAndCondition::query()->with('creator:id,name');

        if ($request->filled('type')) {
            $query->ofType($request->query('type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('is_required')) {
            $query->where('is_required', filter_var($request->query('is_required'), FILTER_VALIDATE_BOOLEAN));
        }

        $terms = $query->orderByDesc('id')->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => TermsResource::collection($terms),
            'pagination' => [
                'current_page' => $terms->currentPage(),
                'last_page' => $terms->lastPage(),
                'per_page' => $terms->perPage(),
                'total' => $terms->total(),
            ],
        ]);
    }

    /**
     * Create a new terms draft or new version.
     */
    public function store(CreateTermsRequest $request): JsonResponse
    {
        $admin = $request->user();

        $terms = $this->termsService->createTerms(
            data: $request->validated(),
            adminId: (int) $admin->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Terms document created successfully.',
            'data' => new TermsResource($terms),
        ], 201);
    }

    /**
     * Show terms document with acceptance statistics.
     */
    public function show(int $id): JsonResponse
    {
        $metrics = $this->termsService->getAcceptanceMetrics($id);

        return response()->json([
            'status' => 'success',
            'data' => new AdminTermsDetailResource($metrics),
        ]);
    }

    /**
     * Update terms metadata or content.
     */
    public function update(int $id, UpdateTermsRequest $request): JsonResponse
    {
        $terms = $this->termsService->updateTerms($id, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Terms document updated successfully.',
            'data' => new TermsResource($terms),
        ]);
    }

    /**
     * Publish and activate a terms version, retiring older versions of the same type.
     */
    public function publish(int $id): JsonResponse
    {
        $terms = $this->termsService->publishVersion($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Terms version published and activated successfully.',
            'data' => new TermsResource($terms),
        ]);
    }

    /**
     * Toggle mandatory user acceptance enforcement.
     */
    public function enforce(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'is_required' => ['required', 'boolean'],
        ]);

        $terms = $this->termsService->toggleEnforcement($id, (bool) $request->input('is_required'));

        return response()->json([
            'status' => 'success',
            'message' => 'Terms enforcement status updated successfully.',
            'data' => new TermsResource($terms),
        ]);
    }

    /**
     * Paginated acceptance audit log for a specific terms version.
     */
    public function auditLog(int $id, Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $logs = $this->termsService->getAuditLog($id, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => UserTermsAcceptanceResource::collection($logs),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
