<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sign-in throttling
    |--------------------------------------------------------------------------
    |
    | Two independent brakes. The rate limiter is keyed on identifier + IP and
    | stops a fast flood; the lockout is stored on the account itself and
    | survives an IP change, so rotating proxies do not reset the budget.
    |
    */

    'login' => [
        'max_attempts' => (int) env('ADMIN_LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('ADMIN_LOGIN_DECAY_SECONDS', 60),
    ],

    'lockout' => [
        'threshold' => (int) env('ADMIN_LOCKOUT_THRESHOLD', 8),
        'minutes' => (int) env('ADMIN_LOCKOUT_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sign-in verification code
    |--------------------------------------------------------------------------
    |
    | After a correct password on a browser that is not trusted, a six-digit
    | code is emailed to the security recipients below and must be entered
    | before a session or token is issued.
    |
    | recipients: comma-separated OTP_SECURITY_EMAIL. Left empty, the code goes
    | to every active Admin account's own email address instead.
    |
    */

    'otp' => [
        'recipients' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('OTP_SECURITY_EMAIL', '')),
        ))),
        'expires_minutes' => (int) env('ADMIN_OTP_EXPIRES_MINUTES', 10),
        // Wrong codes allowed against one challenge before it is burned and
        // the password has to be entered again.
        'max_attempts' => (int) env('ADMIN_OTP_MAX_ATTEMPTS', 5),
        // Re-sends allowed on one challenge, and the wait between them.
        'max_resends' => (int) env('ADMIN_OTP_MAX_RESENDS', 3),
        'resend_cooldown_seconds' => (int) env('ADMIN_OTP_RESEND_COOLDOWN', 60),
        // Fresh challenges one account may start per window, so a correct
        // password cannot be used to flood the security mailbox.
        'issue_limit' => (int) env('ADMIN_OTP_ISSUE_LIMIT', 5),
        'issue_window_seconds' => (int) env('ADMIN_OTP_ISSUE_WINDOW', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | New order emails
    |--------------------------------------------------------------------------
    |
    | Every order placed on the storefront is summarised in an email to these
    | addresses (comma-separated ADMIN_ORDER_EMAIL). Left empty, it goes to
    | every active Admin account's own email address.
    |
    */

    'orders' => [
        'notify' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ADMIN_ORDER_EMAIL', '')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted devices
    |--------------------------------------------------------------------------
    |
    | A browser that completes the code step skips it - never the password -
    | for this many hours. The cookie carries a random token whose hash is
    | stored server-side; the expiry is decided by the server's clock.
    |
    */

    'trusted_device' => [
        'cookie' => 'aurum_trusted_device',
        'hours' => (int) env('ADMIN_TRUSTED_DEVICE_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | API tokens
    |--------------------------------------------------------------------------
    |
    | Abilities granted to a freshly issued admin token. Kept narrow on purpose
    | - widen per module rather than handing out a wildcard.
    |
    */

    'token' => [
        'abilities' => ['admin'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | This project serves uploads straight from public/ rather than the storage
    | disk, so paths here are relative to public_path(). Every module that
    | accepts a file must validate mime and size, and must never trust the
    | client-supplied filename.
    |
    */

    'uploads' => [
        'avatars' => [
            'path' => 'uploads/admins',
            'max_kb' => 2048,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],

        'categories' => [
            'path' => 'uploads/categories',
            'max_kb' => 2048,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],

        'products' => [
            'path' => 'uploads/products',
            'max_kb' => 4096,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],

        // Imagery placed on the fixed pages from the Pages module - the home
        // hero runs full bleed, so this is the most generous ceiling here.
        'pages' => [
            'path' => 'uploads/pages',
            'max_kb' => 6144,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],

        // The logo, the browser icon and the default share image. Small
        // files by nature, so the ceiling is the tightest here.
        //
        // No SVG: it is markup, it can carry a script, and it would be served
        // from the site's own origin. A raster logo costs nothing here.
        'settings' => [
            'path' => 'uploads/settings',
            'max_kb' => 2048,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],

        // Cover images for journal posts. Wider than a product shot and shown
        // at up to 1200px, so the ceiling is a little higher.
        'blog' => [
            'path' => 'uploads/blog',
            'max_kb' => 4096,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalogue
    |--------------------------------------------------------------------------
    */

    'catalogue' => [
        // Page size for the panel's list screens. Capped so a client cannot
        // ask for the whole table with ?per_page=100000.
        'per_page' => 15,
        'max_per_page' => 100,

        // Ceiling on the gallery, so one product cannot fill the disk.
        'max_gallery_images' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Journal
    |--------------------------------------------------------------------------
    */

    'blog' => [
        // Posts per page on the public listing.
        'per_page' => 9,

        // Ceiling on the "short version" list under a post.
        'max_takeaways' => 8,

        // Words a reader gets through in a minute, for the "6 min read" line.
        // 200 is the usual figure for non-technical prose.
        'words_per_minute' => 200,
    ],

];
