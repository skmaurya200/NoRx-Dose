<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\Setting\UpdatePasswordRequest;
use App\Http\Requests\APIs\Setting\UpdateSettingsRequest;
use App\Models\Admin;
use App\Services\Settings\SettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Settings module API.
 *
 * Two writes, deliberately separate: the site's own details, and the operator's
 * password. Putting a credential change on the same form as the footer address
 * would mean asking for the current password to edit a phone number.
 */
class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    /**
     * POST /api/manager/settings
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->settings->save(
            $request->fields(),
            $request->images(),
            $request->removeImages(),
        );

        return ApiResponse::success(null, 'Settings saved.');
    }

    /**
     * POST /api/manager/settings/password
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $this->settings->changePassword($admin, $request->validated('password'));

        return ApiResponse::success(
            null,
            'Password changed. Any other devices you were signed in on have been signed out.',
        );
    }
}
