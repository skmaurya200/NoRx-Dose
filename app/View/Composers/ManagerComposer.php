<?php

namespace App\View\Composers;

use App\Models\Admin;
use App\Models\ChatSession;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The sidebar and topbar render on every manager screen and read the same
 * handful of values. Binding them here means each manager controller does not
 * have to remember to pass them - a class that forgot would render a panel with
 * an empty profile block.
 */
class ManagerComposer
{
    public function compose(View $view): void
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        $view->with([
            'admin' => $admin,
            'adminName' => $admin?->name ?? 'Admin',
            'adminRole' => $admin ? Str::headline($admin->role) : '',
            'adminInitial' => $admin?->initial() ?? 'A',
            'adminAvatar' => $admin?->avatarUrl(),

            // The counts the sidebar badges show. Null rather than 0 when
            // there is nothing waiting: a badge reading "0" is noise.
            'pendingOrders' => Order::query()->where('status', 'pending')->count() ?: null,
            'pendingReviews' => Review::query()->pending()->count() ?: null,
            'unreadChats' => ChatSession::query()->where('unread_count', '>', 0)->count() ?: null,

            // Placeholders until the notifications module lands.
            'unreadMessages' => true,
            'unreadNotifications' => true,
        ]);
    }
}
