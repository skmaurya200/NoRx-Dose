<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Who is signed in to the panel right now, and what they have been opening.
 *
 * A row per browser session is started at sign-in, touched as the account
 * uses the panel and closed at sign-out. "Signed in" therefore means a session
 * that has not signed out and has been seen within the session lifetime - the
 * point at which the session expires on the server anyway.
 *
 * Tracking must never break the page it is tracking, so every write here is
 * reported and swallowed on failure.
 */
class PresenceService
{
    /**
     * Seconds between last-seen writes for requests that are not page views,
     * so the chat inbox polling every few seconds is not a write every time.
     */
    private const TOUCH_INTERVAL = 60;

    public function __construct(private readonly ActivityLogService $activity) {}

    /**
     * A fresh sign-in. Called after the session id has been regenerated.
     */
    public function start(Admin $admin, Request $request): void
    {
        $this->quietly(function () use ($admin, $request) {
            $now = Carbon::now();

            AdminSession::query()->updateOrCreate(
                ['session_hash' => $this->hash($request)],
                [
                    'admin_id' => $admin->getKey(),
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                    'logged_in_at' => $now,
                    'last_seen_at' => $now,
                    'logged_out_at' => null,
                    'page_views' => 0,
                ],
            );
        });
    }

    /**
     * A panel page was opened: counted on the session, and written to the
     * activity log so the trail can be read back page by page.
     */
    public function pageViewed(Admin $admin, Request $request, string $page): void
    {
        $this->quietly(function () use ($admin, $request, $page) {
            $session = $this->current($admin, $request);
            $path = Str::limit('/'.ltrim($request->path(), '/'), 500, '');

            $session->forceFill([
                'last_seen_at' => Carbon::now(),
                'page_views' => $session->page_views + 1,
                'last_page' => Str::limit($page, 191, ''),
                'last_url' => $path,
                'ip_address' => $request->ip(),
            ])->save();

            $this->activity->record(
                ActivityLog::PAGE_VIEWED,
                'Viewed page: '.$page,
                $admin,
                properties: ['route' => $request->route()?->getName(), 'session_page' => $session->page_views],
            );
        });
    }

    /**
     * Any other signed-in request - a save, a poll - keeps the session
     * current without counting as a page.
     */
    public function touch(Admin $admin, Request $request): void
    {
        $this->quietly(function () use ($admin, $request) {
            $session = $this->current($admin, $request);

            if ($session->wasRecentlyCreated
                || $session->last_seen_at->lt(Carbon::now()->subSeconds(self::TOUCH_INTERVAL))) {
                $session->forceFill(['last_seen_at' => Carbon::now()])->save();
            }
        });
    }

    /**
     * This browser signed out.
     */
    public function end(Request $request): void
    {
        $this->quietly(fn () => AdminSession::query()
            ->where('session_hash', $this->hash($request))
            ->whereNull('logged_out_at')
            ->update(['logged_out_at' => Carbon::now()]));
    }

    /**
     * Every browser for this account signed out - sign out everywhere, a
     * deactivation, a deletion.
     */
    public function endAll(Admin $admin): void
    {
        $this->quietly(fn () => AdminSession::query()
            ->where('admin_id', $admin->getKey())
            ->whereNull('logged_out_at')
            ->update(['logged_out_at' => Carbon::now()]));
    }

    /**
     * Employees - every panel account that is not an Admin - signed in right
     * now, one row per browser, most recently active first.
     *
     * @return Collection<int, AdminSession>
     */
    public function onlineEmployeeSessions(): Collection
    {
        return AdminSession::query()
            ->online()
            ->whereHas('admin', fn ($query) => $query
                ->where('role', '!=', Admin::ROLE_ADMIN)
                ->where('is_active', true))
            ->with('admin')
            ->latest('last_seen_at')
            ->get();
    }

    /**
     * How many different employees are signed in - two browsers are still one
     * person.
     */
    public function onlineEmployeeCount(): int
    {
        return AdminSession::query()
            ->online()
            ->whereHas('admin', fn ($query) => $query
                ->where('role', '!=', Admin::ROLE_ADMIN)
                ->where('is_active', true))
            ->distinct()
            ->count('admin_id');
    }

    /**
     * Pages each employee has opened since the start of today, busiest first.
     *
     * @return Collection<int, object{admin_id: int, username: string|null, role: string|null, pages: int}>
     */
    public function pageViewsToday(): Collection
    {
        return ActivityLog::query()
            ->where('action', ActivityLog::PAGE_VIEWED)
            ->where('created_at', '>=', Carbon::today())
            ->whereNotNull('admin_id')
            ->where('role', '!=', Admin::ROLE_ADMIN)
            ->selectRaw('admin_id, MAX(username) as username, MAX(role) as role, COUNT(*) as pages')
            ->groupBy('admin_id')
            ->orderByDesc('pages')
            ->limit(50)
            ->get()
            ->map(fn (ActivityLog $row) => (object) [
                'admin_id' => (int) $row->admin_id,
                'username' => $row->username,
                'role' => $row->role,
                'pages' => (int) $row->pages,
            ]);
    }

    /**
     * The session row for this browser, created when it predates tracking (a
     * sign-in restored from a remember-me cookie, say).
     */
    private function current(Admin $admin, Request $request): AdminSession
    {
        $now = Carbon::now();

        /** @var AdminSession $session */
        $session = AdminSession::query()->firstOrCreate(
            ['session_hash' => $this->hash($request)],
            [
                'admin_id' => $admin->getKey(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'logged_in_at' => $now,
                'last_seen_at' => $now,
            ],
        );

        // A session id that somehow changed hands is not carried over.
        if ((int) $session->admin_id !== (int) $admin->getKey() || $session->logged_out_at !== null) {
            $session->forceFill([
                'admin_id' => $admin->getKey(),
                'logged_in_at' => $now,
                'last_seen_at' => $now,
                'logged_out_at' => null,
                'page_views' => 0,
            ])->save();
        }

        return $session;
    }

    private function hash(Request $request): string
    {
        return hash('sha256', $request->hasSession() ? $request->session()->getId() : '');
    }

    private function quietly(callable $write): void
    {
        try {
            $write();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
