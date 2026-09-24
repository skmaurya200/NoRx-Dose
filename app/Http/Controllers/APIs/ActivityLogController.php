<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\ActivityLog\DeleteActivityLogsRequest;
use App\Models\ActivityLog;
use App\Services\Audit\ActivityLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Activity log module API - deletion only; the list is server-rendered.
 */
class ActivityLogController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    /**
     * DELETE /api/manager/activity-logs/{activityLog}
     */
    public function destroy(Request $request, ActivityLog $activityLog): JsonResponse
    {
        Gate::authorize('delete', $activityLog);

        $this->activity->delete([$activityLog->id], $request->user());

        return ApiResponse::success(null, 'Log entry deleted.');
    }

    /**
     * POST /api/manager/activity-logs/bulk-delete
     */
    public function bulkDestroy(DeleteActivityLogsRequest $request): JsonResponse
    {
        $deleted = $this->activity->delete($request->ids(), $request->user());

        return ApiResponse::success(['deleted' => $deleted], $deleted === 1
            ? '1 log entry deleted.'
            : "{$deleted} log entries deleted.");
    }
}
