<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Services\Audit\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The activity log screen. Paginated and filtered server-side; deletion is
 * posted to App\Http\Controllers\APIs\ActivityLogController.
 */
class ActivityLogController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    /**
     * GET /manager/activity-logs
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'admin_id', 'role', 'action', 'status', 'ip', 'from', 'to', 'per_page']);

        return view('manager.activity-logs.index', [
            'logs' => $this->activity->paginate($filters),
            'filters' => $filters,
            'actions' => $this->activity->actions(),
            'accounts' => Admin::query()->withTrashed()->orderBy('name')->get(['id', 'name', 'username']),
            'roles' => Admin::roles(),
            'statuses' => ActivityLog::statuses(),
        ]);
    }
}
