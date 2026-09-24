<?php

namespace App\Mail;

use App\Models\Admin;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/**
 * The sign-in verification code, sent to the security recipients.
 *
 * Sent immediately rather than queued: a code that waits for a worker may have
 * expired by the time it arrives, and a queued job would keep the code in the
 * jobs table. Nothing secret beyond the code itself goes in - no password, no
 * token, no session detail.
 */
class LoginOtpMail extends Mailable
{
    public function __construct(
        public readonly Admin $account,
        public readonly string $code,
        public readonly int $expiresMinutes,
        public readonly Carbon $requestedAt,
        public readonly ?string $ipAddress,
        public readonly string $siteName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->siteName} sign-in verification code",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.auth.login-otp',
        );
    }
}
