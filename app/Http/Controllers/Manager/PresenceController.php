<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminSession;
use App\Services\Audit\PresenceService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Employees signed in to the panel right now, and the pages each has opened.
 * Admin role only - the routes carry can:viewPresence.
 */
class PresenceController extends Controller
{
    /**
     * How many of an employee's most recent page views the detail screen
     * lists. The full, paginated trail is one click away in the activity log.
     */
    private const TRAIL = 50;

    public function __construct(private readonly PresenceService $presence) {}

    /**
     * GET /manager/employees-online
     */
    public function index(): View
    {
        $sessions = $this->presence->onlineEmployeeSessions();

        return view('manager.presence.index', [
            'sessions' => $sessions,
            'onlineCount' => $sessions->unique('admin_id')->count(),
            'activeCount' => $sessions->filter(fn (AdminSession $session) => $session->isActive())->unique('admin_id')->count(),
            'pagesToday' => $this->presence->pageViewsToday(),
        ]);
    }

    /**
     * GET /manager/employees-online/{account}
     */
    public function show(Admin $account): View
    {
        $pageViews = ActivityLog::query()
            ->where('admin_id', $account->id)
            ->where('action', ActivityLog::PAGE_VIEWED);

        return view('manager.presence.show', [
            'account' => $account,
            'sessions' => AdminSession::query()
                ->where('admin_id', $account->id)
                ->latest('logged_in_at')
                ->limit(10)
                ->get(),
            'online' => AdminSession::query()->online()->where('admin_id', $account->id)->exists(),
            'pagesToday' => (clone $pageViews)->where('created_at', '>=', Carbon::today())->count(),
            'pagesWeek' => (clone $pageViews)->where('created_at', '>=', Carbon::today()->subDays(6))->count(),
            'trail' => (clone $pageViews)->latest('id')->limit(self::TRAIL)->get(),
            'trailLimit' => self::TRAIL,
        ]);
    }
}
