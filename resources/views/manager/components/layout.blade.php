{{--
    Manager (admin panel) shell. Deliberately separate from
    components/baselayout.blade.php: the storefront and the admin panel share a
    brand but no stylesheet, fonts or scripts.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Dashboard') &mdash; NoRx Dose Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <link rel="stylesheet" href="{{ asset('css/manager/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/manager/catalogue.css') }}">

    @stack('styles')
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app-shell">

    @include('manager.components.adminsidebar')

    <div class="main">

        @include('manager.components.topbar')

        <div class="content">
            @yield('content')
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

{{-- Chart.js is only worth its weight on pages that actually draw a chart --}}
@stack('vendor')

{{-- Toasts and the API client load before admin.js, which reports through them --}}
<script src="{{ asset('js/manager/toast.js') }}"></script>
<script src="{{ asset('js/manager/api.js') }}"></script>
<script src="{{ asset('js/manager/admin.js') }}"></script>

@stack('scripts')

</body>
</html>
