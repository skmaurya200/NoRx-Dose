{{--
    Shared shell for the admin sections that have a route and a place in the nav
    but no screen designed yet. Keeping them real pages means every sidebar link
    resolves and the active state works; replace one at a time with its own view.
--}}
@extends('manager.components.layout')

@section('title', $heading)

@section('content')

    <div class="welcome-banner">
        <div>
            <h1 class="font-serif">{{ $heading }}</h1>
            <p>{{ $blurb }}</p>
        </div>
        <a href="{{ route('manager.dashboard') }}" class="btn-outline-cream">Back to dashboard</a>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Nothing here yet</h2>
                <div class="panel-sub">This section is routed and wired up, but its screen has not been built.</div>
            </div>
        </div>

        <p class="text-muted small mb-3">
            When you are ready, add <code>resources/views/manager/{{ $slug }}.blade.php</code>,
            extend <code>manager.components.layout</code>, and point
            <code>ManagerController::{{ $method }}()</code> at it instead of this one.
        </p>

        <a href="{{ route('manager.dashboard') }}" class="btn-gold">Back to dashboard</a>
    </div>

@endsection
