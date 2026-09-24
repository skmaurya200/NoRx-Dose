<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Order;
use App\Services\Audit\PresenceService;
use App\Services\Auth\LoginOtpService;
use App\Services\Commerce\AttributionService;
use App\Services\Reporting\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ManagerController extends Controller
{
    /**
     * GET /manager/login - the sign-in screen. The form posts to the auth API.
     */
    public function showLogin(): View
    {
        return view('manager.auth.login');
    }

    /**
     * GET /manager/otp - the verification code screen.
     *
     * Reachable only with a live challenge in the session, which only a correct
     * password puts there. The page is told how long is left, never the code.
     */
    public function showOtp(Request $request, LoginOtpService $otp): View|RedirectResponse
    {
        $pending = $otp->pending($request->session()->get(LoginOtpService::SESSION_KEY.'.challenge'));

        if ($pending === null) {
            $request->session()->forget(LoginOtpService::SESSION_KEY);

            return redirect()->route('manager.login');
        }

        $now = Carbon::now();
        $resendAt = $pending->last_sent_at?->copy()->addSeconds((int) config('admin.otp.resend_cooldown_seconds'));

        return view('manager.auth.otp', [
            'expiresIn' => max(0, (int) $now->diffInSeconds($pending->expires_at)),
            'expiresMinutes' => $otp->expiresMinutes(),
            'sentTo' => $otp->maskedRecipients(),
            'resendIn' => $resendAt !== null && $resendAt->isFuture() ? (int) ceil($now->diffInSeconds($resendAt)) : 0,
            'resendsLeft' => max(0, (int) config('admin.otp.max_resends') - ($pending->send_count - 1)),
        ]);
    }

    /**
     * GET /manager
     *
     * Every figure comes from the orders table, so the dashboard tells the
     * truth about a shop with two orders as readily as one with two thousand.
     */
    public function dashboard(DashboardService $dashboard, PresenceService $presence): View
    {
        $hour = (int) now()->format('G');

        return view('manager.dashboard', [
            // Admin only. Null for everyone else, and the card is not drawn.
            'onlineEmployees' => Gate::allows('viewPresence', Admin::class)
                ? $presence->onlineEmployeeCount()
                : null,
            'greeting' => match (true) {
                $hour < 12 => 'Good morning',
                $hour < 17 => 'Good afternoon',
                default => 'Good evening',
            },
            'today' => now()->format('l, j F'),
            'orderCount' => $dashboard->orderCount(),
            'stats' => $dashboard->stats(),
            'sales' => $dashboard->salesChart(),
            'orderStatus' => $dashboard->orderStatus(),
            'recentOrders' => $dashboard->recentOrders(),
            'topProducts' => $dashboard->topProducts(),
            // Replaces the invented reviews the panel used to show. There is
            // no reviews table to read, and where an order came from is a
            // question the dashboard can actually answer.
            'channels' => $dashboard->topChannels(),
            'lowStock' => $dashboard->lowStock(),
            'searches' => $dashboard->topSearches(),
        ]);
    }

    public function analytics(Request $request, AttributionService $attribution): View
    {
        $filters = [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            // Which end of the journey gets the credit. Last touch by default:
            // it answers "what closed the sale", which is the usual question.
            'touch' => $request->query('touch') === 'first' ? 'first' : 'last',
        ];

        $touch = $filters['touch'];

        $channels = $attribution->report(['source', 'medium'], $touch, $filters);

        return view('manager.analytics', [
            'filters' => $filters,
            'channels' => $channels,
            'sources' => $attribution->report(['source'], $touch, $filters),
            'mediums' => $attribution->report(['medium'], $touch, $filters),
            // Untagged traffic has no campaign, and a row of blanks is not a
            // campaign report.
            'campaigns' => $attribution->report(['campaign', 'source', 'medium'], $touch, $filters)
                ->filter(fn ($row) => filled($row->campaign))
                ->values(),
            'totals' => [
                'orders' => $channels->sum('orders'),
                'revenue' => $channels->sum('revenue'),
            ],
            'untracked' => Order::query()
                ->whereDoesntHave('attribution')
                ->where('status', '!=', 'cancelled')
                ->count(),
        ]);
    }

    public function customers(): View
    {
        return $this->section('Customers', 'Customer accounts, order history and lifetime value.', 'customers');
    }

    public function shipping(): View
    {
        return $this->section('Shipping', 'Zones, rates and carrier settings.', 'shipping');
    }

    public function help(): View
    {
        return $this->section('Help & support', 'Documentation and a way to reach the team.', 'help');
    }

    public function search(Request $request): View
    {
        $term = trim((string) $request->query('q'));

        return $this->section(
            'Search',
            $term === ''
                ? 'Type something into the search box to look across orders, products and customers.'
                : sprintf('Results for "%s" across orders, products and customers.', $term),
            'search',
        );
    }

    /**
     * Render a section that is routed and in the nav but has no screen yet.
     */
    private function section(string $heading, string $blurb, string $method): View
    {
        return view('manager.section', [
            'heading' => $heading,
            'blurb' => $blurb,
            'method' => $method,
            'slug' => $method,
        ]);
    }
}
