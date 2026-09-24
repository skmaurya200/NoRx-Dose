<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\User\StoreUserRequest;
use App\Http\Requests\APIs\User\UpdateUserRequest;
use App\Http\Resources\APIs\AdminResource;
use App\Models\Admin;
use App\Services\Users\UserManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * User Management module API. Every action is authorised by AdminPolicy, so a
 * Manager or User calling these endpoints directly gets a 403.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    /**
     * POST /api/manager/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $account = $this->users->create($request->payload(), $request->user());

        return ApiResponse::success(new AdminResource($account), 'User created.', Response::HTTP_CREATED);
    }

    /**
     * POST /api/manager/users/{account}
     */
    public function update(UpdateUserRequest $request, Admin $account): JsonResponse
    {
        $account = $this->users->update($account, $request->payload(), $request->user());

        return ApiResponse::success(new AdminResource($account), 'User updated.');
    }

    /**
     * PATCH /api/manager/users/{account}/toggle
     */
    public function toggle(Request $request, Admin $account): JsonResponse
    {
        Gate::authorize('changeStatus', $account);

        $account = $this->users->setActive($account, ! $account->is_active, $request->user());

        return ApiResponse::success([
            'id' => $account->id,
            'is_active' => $account->is_active,
            'status_label' => $account->is_active ? 'Active' : 'Inactive',
        ], $account->is_active ? 'User activated.' : 'User deactivated. They can no longer sign in.');
    }

    /**
     * DELETE /api/manager/users/{account}
     */
    public function destroy(Request $request, Admin $account): JsonResponse
    {
        Gate::authorize('delete', $account);

        $this->users->delete($account, $request->user());

        return ApiResponse::success(null, 'User deleted.');
    }

    /**
     * DELETE /api/manager/users/{account}/trusted-devices
     */
    public function revokeDevices(Request $request, Admin $account): JsonResponse
    {
        Gate::authorize('revokeDevices', $account);

        $revoked = $this->users->revokeDevices($account, $request->user());

        return ApiResponse::success(
            ['revoked' => $revoked],
            'Trusted devices revoked. The next sign-in will ask for a verification code.',
        );
    }
}
