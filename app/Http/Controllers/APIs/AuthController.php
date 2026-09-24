<?php

namespace App\Http\Controllers\APIs;

use App\Exceptions\Auth\AccountDisabledException;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\TooManyAttemptsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\Auth\LoginRequest;
use App\Http\Requests\APIs\Auth\VerifyOtpRequest;
use App\Http\Resources\APIs\AdminResource;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Services\Audit\ActivityLogService;
use App\Services\Audit\PresenceService;
use App\Services\Auth\AdminAuthService;
use App\Services\Auth\LoginOtpService;
use App\Services\Auth\PanelSessionService;
use App\Services\Auth\TrustedDeviceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth module API.
 *
 * Two credential styles are supported from one endpoint:
 *
 *  - Browser (no device_name): a session is started on the "admin" guard and
 *    Sanctum authenticates later calls from the session cookie. Nothing secret
 *    reaches JavaScript, so an XSS cannot walk off with a token.
 *  - API client (device_name given): a Sanctum bearer token is returned.
 *
 * Both go through the same two steps. The password is always checked; then,
 * unless the caller presents a trusted-device token issued to this account in
 * the last 24 hours, a one-time code is emailed and no session or token exists
 * until it has been entered.
 *
 * Every failure path is raised as an ApiException by the auth services, so
 * this controller holds no security decisions of its own.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AdminAuthService $auth,
        private readonly LoginOtpService $otp,
        private readonly TrustedDeviceService $devices,
        private readonly ActivityLogService $activity,
        private readonly PresenceService $presence,
    ) {}

    /**
     * POST /api/manager/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->credentials();

        $admin = $this->auth->attempt(
            $credentials['login'],
            $credentials['password'],
            (string) $request->ip(),
        );

        $this->auth->rehashIfNeeded($admin, $credentials['password']);

        // No session means the caller is not a same-origin browser (Sanctum
        // only starts one for a stateful request), so there is nowhere to keep
        // a challenge. Checked before a code is generated, so a stateless
        // caller cannot use this endpoint to fill the security mailbox.
        if (! $request->wantsToken() && ! $request->hasSession()) {
            return $this->statelessBrowserError();
        }

        $deviceName = $request->wantsToken() ? $request->deviceName() : null;

        if ($this->devices->isTrusted($admin, $this->devices->tokenFrom($request))) {
            return $this->completeSignIn($request, $admin, $deviceName, $request->boolean('remember'), otpRequired: false);
        }

        $challenge = $this->otp->issue($admin, $request);
        $message = 'A verification code has been sent to the security email address.';

        if ($deviceName !== null) {
            return ApiResponse::success([
                'otp_required' => true,
                'challenge_token' => $challenge,
                'expires_in' => $this->otp->expiresMinutes() * 60,
            ], $message);
        }

        $request->session()->put(LoginOtpService::SESSION_KEY, [
            'challenge' => $challenge,
            'remember' => $request->boolean('remember'),
        ]);

        return ApiResponse::success([
            'otp_required' => true,
            'redirect' => route('manager.otp'),
        ], $message);
    }

    /**
     * POST /api/manager/auth/otp/verify
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $pending = $this->pendingFromSession($request);
        $challenge = $request->challenge() ?? ($pending['challenge'] ?? null);

        if (! is_string($challenge)) {
            throw new InvalidOtpException;
        }

        try {
            $admin = $this->otp->verify($challenge, $request->code());
        } catch (TooManyAttemptsException|AccountDisabledException $exception) {
            // The challenge is dead either way; the browser has to start over.
            $this->forgetPending($request);

            throw $exception;
        }

        $this->forgetPending($request);

        return $this->completeSignIn(
            $request,
            $admin,
            $request->wantsToken() ? $request->deviceName() : null,
            (bool) ($pending['remember'] ?? false),
            otpRequired: true,
            deviceToken: $this->devices->trust($admin, $request),
        );
    }

    /**
     * POST /api/manager/auth/otp/resend
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $challenge = $this->challengeFrom($request);

        if ($challenge === null) {
            throw new InvalidOtpException;
        }

        $otp = $this->otp->resend($challenge, $request);

        return ApiResponse::success([
            'expires_in' => max(0, (int) Carbon::now()->diffInSeconds($otp->expires_at)),
            'resend_available_in' => (int) config('admin.otp.resend_cooldown_seconds'),
        ], 'A new verification code has been sent. The previous code no longer works.');
    }

    /**
     * POST /api/manager/auth/otp/cancel
     *
     * The "back" button: the pending code stops working immediately rather
     * than lingering until it expires.
     */
    public function cancelOtp(Request $request): JsonResponse
    {
        $this->otp->cancel($this->challengeFrom($request));
        $this->forgetPending($request);

        return ApiResponse::success(['redirect' => route('manager.login')], 'Sign-in cancelled.');
    }

    /**
     * POST /api/manager/auth/logout
     *
     * Ends only the credential that made this call: the current bearer token,
     * or the current session. Other devices stay signed in, and this browser
     * stays trusted until its 24 hours are up - the password is still asked
     * for next time.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $this->activity->record(ActivityLog::LOGOUT, 'Signed out.', $admin);

        $token = $admin->currentAccessToken();

        if ($token !== null && method_exists($token, 'delete')) {
            // Token client: revoke this token only.
            $token->delete();

            return ApiResponse::success(null, 'Signed out.');
        }

        $this->presence->end($request);
        $this->destroySession($request);

        return ApiResponse::success(null, 'Signed out.');
    }

    /**
     * POST /api/manager/auth/logout-all
     *
     * The Admin's "sign out everywhere": every employee (Manager and User) is
     * signed out of the panel, along with every other session and API token of
     * the Admin's own, and then this session ends too. Admin role only; a
     * Manager or User gets 403 whether or not the button is on their screen.
     */
    public function logoutAll(Request $request, PanelSessionService $panelSessions): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        Gate::forUser($admin)->authorize('signOutEverywhere', Admin::class);

        $employees = $panelSessions->signOutEveryone($admin);

        if ($request->hasSession()) {
            $this->destroySession($request);
        }

        return ApiResponse::success(
            ['employees_signed_out' => $employees],
            "Signed out everywhere. {$employees} ".Str::plural('employee', $employees).' signed out of the panel.',
        );
    }

    /**
     * GET /api/manager/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new AdminResource($request->user()),
            'Authenticated.',
        );
    }

    /**
     * The last step of either path: turn a verified account into a session or
     * a token, and record how it got here.
     */
    private function completeSignIn(
        Request $request,
        Admin $admin,
        ?string $deviceName,
        bool $remember,
        bool $otpRequired,
        ?string $deviceToken = null,
    ): JsonResponse {
        if ($deviceName === null && ! $request->hasSession()) {
            return $this->statelessBrowserError();
        }

        $this->activity->record(ActivityLog::LOGIN_SUCCESS, $otpRequired
            ? 'Signed in with password and verification code.'
            : 'Signed in with password on a trusted device.', $admin, properties: [
                'login_method' => $otpRequired ? 'password_otp' : 'password_trusted_device',
                'otp_required' => $otpRequired,
                'channel' => $deviceName === null ? 'session' : 'token',
            ]);

        if ($deviceName !== null) {
            return $this->tokenResponse($admin, $deviceName, $deviceToken);
        }

        return $this->sessionResponse($request, $admin, $remember, $deviceToken);
    }

    /**
     * Issue a bearer token for a non-browser client.
     */
    private function tokenResponse(Admin $admin, string $deviceName, ?string $deviceToken): JsonResponse
    {
        // One live token per device name, so re-authenticating from the same
        // device does not leave a trail of usable tokens behind.
        $admin->tokens()->where('name', $deviceName)->delete();

        // Passed explicitly rather than relying on sanctum.expiration alone:
        // that config is only consulted when a token is validated, so the row
        // (and this response) would otherwise report no expiry at all.
        $minutes = (int) config('sanctum.expiration');

        $token = $admin->createToken(
            $deviceName,
            (array) config('admin.token.abilities'),
            $minutes > 0 ? now()->addMinutes($minutes) : null,
        );

        return ApiResponse::success(array_filter([
            'admin' => new AdminResource($admin),
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            // Handed over once, after a code was entered. Sent back as
            // trusted_device_token, it skips the code for 24 hours.
            'trusted_device_token' => $deviceToken,
        ], fn (mixed $value) => $value !== null), 'Signed in.', Response::HTTP_OK);
    }

    /**
     * Start a session for the browser panel.
     */
    private function sessionResponse(Request $request, Admin $admin, bool $remember, ?string $deviceToken): JsonResponse
    {
        Auth::guard('admin')->login($admin, $remember);

        // New session id on privilege change, so a session fixed before login
        // cannot be reused afterwards.
        $request->session()->regenerate();

        // After the regenerate, so the row follows the session id that lives on.
        $this->presence->start($admin, $request);

        $response = ApiResponse::success([
            'admin' => new AdminResource($admin),
            'redirect' => route('manager.dashboard'),
        ], 'Signed in.');

        if ($deviceToken !== null) {
            $response->withCookie($this->devices->cookie($deviceToken, $request));
        }

        return $response;
    }

    private function statelessBrowserError(): JsonResponse
    {
        return ApiResponse::error(
            'Send device_name to receive an API token, or sign in from the admin panel.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['device_name' => ['The device name field is required for API clients.']],
        );
    }

    /**
     * @return array{challenge?: string, remember?: bool}
     */
    private function pendingFromSession(Request $request): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        $pending = $request->session()->get(LoginOtpService::SESSION_KEY);

        return is_array($pending) ? $pending : [];
    }

    private function challengeFrom(Request $request): ?string
    {
        $challenge = $request->input('challenge_token') ?? ($this->pendingFromSession($request)['challenge'] ?? null);

        return is_string($challenge) && $challenge !== '' ? $challenge : null;
    }

    private function forgetPending(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget(LoginOtpService::SESSION_KEY);
        }
    }

    private function destroySession(Request $request): void
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
