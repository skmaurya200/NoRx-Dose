<?php

namespace Tests\Support;

use App\Mail\LoginOtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/**
 * Reads the verification code out of the faked mailer, the only place the
 * application ever puts it.
 */
trait CompletesSignInCode
{
    /**
     * The code in the most recent LoginOtpMail. Mail::fake() must be active.
     */
    protected function sentCode(): string
    {
        $code = null;

        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return (string) $code;
    }

    /**
     * Password, then code, for an API client. Returns the verify response.
     *
     * @param  array<string, mixed>  $credentials
     */
    protected function signInForToken(array $credentials): TestResponse
    {
        Mail::fake();

        $challenge = $this->postJson('/api/manager/auth/login', $credentials)
            ->assertOk()
            ->assertJsonPath('data.otp_required', true)
            ->json('data.challenge_token');

        return $this->postJson('/api/manager/auth/otp/verify', [
            'challenge_token' => $challenge,
            'code' => $this->sentCode(),
            'device_name' => $credentials['device_name'],
        ]);
    }
}
