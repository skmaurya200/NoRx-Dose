{{--
    Sign-in verification. Shown after a correct password on a browser that is
    not trusted yet. Same standalone shell as the sign-in screen.

    The code is never on this page, in its source or in its script: it was
    emailed, and the page only knows how many seconds are left - counted on
    the server and merely displayed here.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>Verify sign-in &mdash; NoRx Dose Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link rel="stylesheet" href="{{ asset('css/manager/login.css') }}">
</head>
<body>

<div class="split-shell">

    @include('manager.auth.partials.brand-panel')

    <div class="right-panel">
        <div class="form-wrap">

            <div class="mobile-brand">
                <div class="mark"><i class="bi bi-stars"></i></div>
                <div class="name">Aurum <span>Wellness</span></div>
            </div>

            <h2>Verify it&rsquo;s you</h2>
            <p class="sub">
                We sent a 6-digit verification code to
                @if ($sentTo === [])
                    the security email address.
                @else
                    <b>{{ implode(', ', $sentTo) }}</b>.
                @endif
                It expires in {{ $expiresMinutes }} {{ Str::plural('minute', $expiresMinutes) }}.
            </p>

            <div class="form-alert form-alert--error" id="formAlert" role="alert" aria-live="polite">
                <i class="bi bi-exclamation-circle"></i>
                <span id="formAlertText"></span>
            </div>

            <div class="form-alert form-alert--success" id="formNotice" role="status" aria-live="polite">
                <i class="bi bi-check-circle"></i>
                <span id="formNoticeText"></span>
            </div>

            <form id="otpForm" novalidate
                  data-expires-in="{{ $expiresIn }}"
                  data-resend-in="{{ $resendIn }}"
                  data-resends-left="{{ $resendsLeft }}">
                @csrf

                <label class="field-label" for="code">Verification code</label>
                <div class="field-wrap">
                    <i class="bi bi-shield-lock field-icon"></i>
                    <input type="text" id="code" name="code"
                           inputmode="numeric" pattern="[0-9]*" maxlength="6"
                           autocomplete="one-time-code" autocapitalize="none" spellcheck="false"
                           placeholder="123456" required autofocus
                           aria-describedby="codeError otpExpiry">
                </div>
                <p class="field-error" id="codeError"></p>

                <div class="row-between">
                    <span class="remember" id="otpExpiry">
                        <i class="bi bi-clock"></i>&nbsp;<span id="otpCountdown">Code expires soon</span>
                    </span>
                    <button type="button" class="forgot-link link-btn" id="resendBtn">
                        Resend code
                    </button>
                </div>

                <button type="submit" class="btn-teal" id="submitBtn">
                    <span class="spinner" aria-hidden="true"></span>
                    <span id="submitLabel">Verify and sign in</span>
                </button>

                <div class="signup-line">
                    <button type="button" class="forgot-link link-btn" id="cancelBtn">
                        <i class="bi bi-arrow-left"></i> Back to sign in
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>

<script src="{{ asset('js/manager/otp.js') }}"></script>
</body>
</html>
