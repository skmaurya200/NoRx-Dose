{{--
    One employee: who they are, their recent sessions and the pages they have
    opened, most recent first. Admin only.
--}}
@extends('manager.components.layout')

@section('title', $account->name)

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">{{ $account->name }}</h1>
            <p class="ph-sub">
                {{ $account->roleLabel() }}
                &middot;
                @if ($online)
                    <span class="badge-status badge-active">Signed in now</span>
                @else
                    <span class="badge-status badge-muted">Not signed in</span>
                @endif
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.presence.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Employees online
            </a>
            <a href="{{ \App\Support\ManagerQuery::url('manager.activity-logs.index', ['admin_id' => $account->id]) }}" class="btn-ghost">
                <i class="bi bi-clock-history"></i> All activity
            </a>
            @can('update', $account)
                <a href="{{ route('manager.users.edit', $account) }}" class="btn-gold">
                    <i class="bi bi-pencil"></i> Edit user
                </a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="form-card h-100">
                <h2 class="form-card-title">Employee</h2>
                <dl class="kv kv--tight">
                    <div class="kv-row"><dt>Username</dt><dd>{{ $account->username ? '@'.$account->username : '—' }}</dd></div>
                    <div class="kv-row"><dt>Email</dt><dd>{{ $account->email }}</dd></div>
                    <div class="kv-row"><dt>Mobile</dt><dd>{{ $account->phone ?: '—' }}</dd></div>
                    <div class="kv-row"><dt>Role</dt><dd>{{ $account->roleLabel() }}</dd></div>
                    <div class="kv-row"><dt>Status</dt><dd>{{ $account->is_active ? 'Active' : 'Inactive' }}</dd></div>
                    <div class="kv-row"><dt>Last sign-in</dt><dd>{{ $account->last_login_at?->format('j M Y, H:i') ?? 'Never' }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="row g-3">
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-icon icon-dark"><i class="bi bi-file-earmark-text"></i></div>
                        <div class="stat-label">Pages opened today</div>
                        <div class="stat-value">{{ number_format($pagesToday) }}</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-icon icon-gold"><i class="bi bi-calendar-week"></i></div>
                        <div class="stat-label">Pages opened in 7 days</div>
                        <div class="stat-value">{{ number_format($pagesWeek) }}</div>
                    </div>
                </div>
            </div>

            <div class="table-wrap mt-3">
                <table class="data-table" @if ($sessions->isEmpty()) hidden @endif>
                    <thead>
                        <tr><th>Signed in</th><th>Last active</th><th>Signed out</th><th class="num">Pages</th><th>IP / browser</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            <tr>
                                <td data-label="Signed in">{{ $session->logged_in_at->format('j M Y, H:i') }}</td>
                                <td data-label="Last active"><span class="cell-meta">{{ $session->last_seen_at->diffForHumans() }}</span></td>
                                <td data-label="Signed out">
                                    {{ $session->logged_out_at?->format('j M Y, H:i') ?? ($session->last_seen_at->lt(now()->subMinutes((int) config('session.lifetime'))) ? 'Expired' : 'Still signed in') }}
                                </td>
                                <td class="num" data-label="Pages">{{ number_format($session->page_views) }}</td>
                                <td data-label="IP / browser">
                                    <span class="cell-meta">{{ $session->ip_address ?? '—' }}</span>
                                    <span class="cell-meta" title="{{ $session->user_agent }}">{{ Str::limit((string) $session->user_agent, 40) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="empty-state" @if ($sessions->isNotEmpty()) hidden @endif>
                    <h3>No sessions recorded yet</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Pages opened</h2>
                <div class="panel-sub">The latest {{ $trailLimit }}, most recent first. The full history is under All activity.</div>
            </div>
        </div>

        @if ($trail->isEmpty())
            <p class="cell-meta mb-0">No panel pages opened yet.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>When</th><th>Page</th><th>Address</th><th>IP</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($trail as $view)
                            <tr>
                                <td data-label="When">
                                    {{ $view->created_at?->format('j M, H:i:s') }}
                                    <span class="cell-meta">{{ $view->created_at?->diffForHumans() }}</span>
                                </td>
                                <td data-label="Page">{{ Str::after($view->description, 'Viewed page: ') }}</td>
                                <td data-label="Address"><span class="cell-meta">{{ $view->url }}</span></td>
                                <td data-label="IP"><span class="cell-meta">{{ $view->ip_address ?? '—' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@endsection
