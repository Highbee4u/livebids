<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Identity\Models\KycRecord;
use App\Http\Controllers\Controller;
use App\Http\Resources\KycRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminKycController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * List KYC records pending review or verified.
     */
    public function index(Request $request): JsonResponse
    {
        $records = KycRecord::with('user:id,name,phone,kyc_status')
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'kyc_records' => KycRecordResource::collection($records),
        ]);
    }

    /**
     * View persisted NIN provider photo securely (Admin only).
     * Decodes and streams the private disk file with full audit trail logging.
     */
    public function viewNinPhoto(int $id, Request $request): StreamedResponse|JsonResponse
    {
        $record = KycRecord::findOrFail($id);

        if (empty($record->nin_photo_path) || ! Storage::disk('local')->exists($record->nin_photo_path)) {
            return response()->json(['message' => 'NIN photo not found or not stored for this record.'], 404);
        }

        // Log admin access for privacy and audit compliance
        $this->auditLogService->log(
            admin: $request->user(),
            action: 'viewed_nin_photo',
            targetType: 'KycRecord',
            targetId: (string) $record->id,
            ip: $request->ip()
        );

        return Storage::disk('local')->response(
            $record->nin_photo_path,
            "nin_photo_{$record->user_id}.jpg",
            ['Content-Type' => 'image/jpeg']
        );
    }
}
