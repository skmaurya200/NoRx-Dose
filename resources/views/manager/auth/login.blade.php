{{--
    Admin sign-in. Standalone - it deliberately does not extend the manager
    layout, because that layout renders the sidebar and topbar for a signed-in
    admin.

    The form posts to POST /api/manager/auth/login. Nothing here stores a token:
    a browser sign-in ends up in the admin session cookie, which JavaScript
    cannot read. When the browser is not trusted, the API answers with the
    verification code screen to go to instead of the dashboard.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>Sign in &mdash; NoRx Dose Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link rel="stylesheet" href="{{ asset('css/manager/login.css') }}">
</head>
<body>

<div class="split-shell">

    {{-- LEFT: brand panel --}}
    @include('manager.auth.partials.brand-panel')

    {{-- RIGHT: sign-in form --}}
    <div class="right-panel">
        <div class="form-wrap">

            <div class="mobile-brand">
                <div class="mark"><i class="bi bi-stars"></i></div>
                <div class="name">Aurum <span>Wellness</span></div>
            </div>

            <h2>Welcome back</h2>
            <p class="sub">Sign in to the admin panel to continue</p>

            <div class="form-alert form-alert--error" id="formAlert" role="alert" aria-live="polite">
                <i class="bi bi-exclamation-circle"></i>
                <span id="formAlertText"></span>
            </div>

            <form id="loginForm" novalidate>
                @csrf

                <label class="field-label" for="login">Username</label>
                <div class="field-wrap">
                    <i class="bi bi-person field-icon"></i>
                    <input type="text" id="login" name="login"
                           autocomplete="username" autocapitalize="none" spellcheck="false"
                           placeholder="your.username" required
                           aria-describedby="loginError">
                </div>
                <p class="field-error" id="loginError"></p>

                <label class="field-label" for="password">Password</label>
                <div class="field-wrap">
                    <i class="bi bi-lock field-icon"></i>
                    <input type="password" id="password" name="password"
                           autocomplete="current-password"
                           placeholder="Enter your password" required
                           aria-describedby="passwordError">
                    <button type="button" class="toggle-eye" id="toggleEye"
                            aria-label="Show password" aria-pressed="false">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <p class="field-error" id="passwordError"></p>

                <div class="row-between">
                    <label class="remember">
                        <input type="checkbox" id="remember" name="remember" value="1"> Remember me
                    </label>
                </div>

                <button type="submit" class="btn-teal" id="submitBtn">
                    <span class="spinner" aria-hidden="true"></span>
                    <span id="submitLabel">Sign in</span>
                </button>

                <div class="signup-line">
                    Accounts are created by an administrator. An account without a username can still sign in with its email or phone number.
                </div>
            </form>

        </div>
    </div>

</div>

<script src="{{ asset('js/manager/login.js') }}"></script>
</body>
</html>
