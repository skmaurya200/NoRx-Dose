<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Auth\TrustedDeviceService;
use App\Services\Users\UserManagementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The User Management screens: list and add/edit form.
 *
 * HTML only - every write is posted by the browser to
 * App\Http\Controllers\APIs\UserController. The routes are behind can:
 * middleware, so only the Admin role reaches any of this.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    /**
     * GET /manager/users
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'role', 'status', 'per_page']);

        return view('manager.users.index', [
            'accounts' => $this->users->paginate($filters),
            'filters' => $filters,
            'roles' => Admin::roles(),
        ]);
    }

    /**
     * GET /manager/users/create
     */
    public function create(): View
    {
        return view('manager.users.form', [
            'account' => new Admin(['is_active' => true]),
            'isEdit' => false,
            'trustedDevices' => 0,
        ]);
    }

    /**
     * GET /manager/users/{account}/edit
     */
    public function edit(Admin $account, TrustedDeviceService $devices): View
    {
        return view('manager.users.form', [
            'account' => $account,
            'isEdit' => true,
            'trustedDevices' => $devices->activeCount($account),
        ]);
    }
}
