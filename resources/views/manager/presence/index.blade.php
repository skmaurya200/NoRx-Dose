{{--
    Employees signed in right now - one row per browser - and the pages each
    employee has opened today. Admin only.
--}}
@extends('manager.components.layout')

@section('title', 'Employees online')

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">Employees signed in now</h1>
            <p class="ph-sub">
                Managers and users with an open panel session. A session counts until its owner signs out,
                or until it has been idle for {{ config('session.lifetime') }} minutes.
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ \App\Support\ManagerQuery::url('manager.activity-logs.index', ['action' => \App\Models\ActivityLog::PAGE_VIEWED]) }}" class="btn-ghost">
                <i class="bi bi-clock-history"></i> All page views
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => 'Signed in now', 'value' => $onlineCount, 'icon' => 'bi-person-check', 'tone' => 'icon-dark'],
            ['label' => 'Active in the last 5 minutes', 'value' => $activeCount, 'icon' => 'bi-lightning-charge', 'tone' => 'icon-gold'],
            ['label' => 'Pages opened today', 'value' => $pagesToday->sum('pages'), 'icon' => 'bi-file-earmark-text', 'tone' => 'icon-dark'],
        ] as $card)
            <div class="col-sm-4">
                <div class="stat-card">
                    <div class="stat-icon {{ $card['tone'] }}"><i class="bi {{ $card['icon'] }}"></i></div>
                    <div class="stat-label">{{ $card['label'] }}</div>
                    <div class="stat-value">{{ number_format($card['value']) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="table-wrap mb-3">
        <table class="data-table" @if ($sessions->isEmpty()) hidden @endif>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Role</th>
                    <th>Contact</th>
                    <th>Signed in</th>
                    <th>Last active</th>
                    <th class="num">Pages this session</th>
                    <th>Current page</th>
                    <th>IP / browser</th>
                    <th class="actions">Details</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sessions as $session)
                    @php $account = $session->admin; @endphp
                    <tr>
                        <td class="cell-primary">
                            <div class="min-w-0">
                                <a href="{{ route('manager.presence.show', $account) }}" class="cell-title">{{ $account->name }}</a>
                                <span class="cell-meta">{{ $account->username ? '@'.$account->username : $account->email }}</span>
                            </div>
                        </td>
                        <td data-label="Role"><span class="badge-status badge-draft">{{ $account->roleLabel() }}</span></td>
                        <td data-label="Contact">
                            <span class="cell-meta">{{ $account->email }}</span>
                            @if ($account->phone)<span class="cell-meta">{{ $account->phone }}</span>@endif
                        </td>
                        <td data-label="Signed in">
                            {{ $session->logged_in_at->format('j M, H:i') }}
                            <span class="cell-meta">{{ $session->logged_in_at->diffForHumans() }}</span>
                        </td>
                        <td data-label="Last active">
                            <span class="badge-status {{ $session->isActive() ? 'badge-active' : 'badge-muted' }}">
                                {{ $session->isActive() ? 'Active' : 'Idle' }}
                            </span>
                            <span class="cell-meta">{{ $session->last_seen_at->diffForHumans() }}</span>
                        </td>
                        <td class="num" data-label="Pages this session">{{ number_format($session->page_views) }}</td>
                        <td data-label="Current page">
                            {{ $session->last_page ?? '—' }}
                            @if ($session->last_url)<span class="cell-meta">{{ $session->last_url }}</span>@endif
                        </td>
                        <td data-label="IP / browser">
                            <span class="cell-meta">{{ $session->ip_address ?? '—' }}</span>
                            <span class="cell-meta" title="{{ $session->user_agent }}">{{ Str::limit((string) $session->user_agent, 40) }}</span>
                        </td>
                        <td class="actions" data-label="Details">
                            <a href="{{ route('manager.presence.show', $account) }}" class="row-btn" title="View details" aria-label="View details for {{ $account->name }}">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="empty-state" @if ($sessions->isNotEmpty()) hidden @endif>
            <div class="es-icon"><i class="bi bi-person-dash"></i></div>
            <h3>No employees are signed in</h3>
            <p>Managers and users appear here while they have the panel open.</p>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Pages opened today, by employee</h2>
                <div class="panel-sub">Every panel page each manager and user has opened since midnight</div>
            </div>
        </div>

        @if ($pagesToday->isEmpty())
            <p class="cell-meta mb-0">No employee has opened a panel page today.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Employee</th><th>Role</th><th class="num">Pages today</th><th class="actions">Details</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($pagesToday as $row)
                            <tr>
                                <td class="cell-primary">{{ $row->username ?? 'Account #'.$row->admin_id }}</td>
                                <td data-label="Role">{{ \App\Models\Admin::roles()[$row->role] ?? Str::headline((string) $row->role) }}</td>
                                <td class="num" data-label="Pages today">{{ number_format($row->pages) }}</td>
                                <td class="actions" data-label="Details">
                                    <a href="{{ route('manager.presence.show', $row->admin_id) }}" class="row-btn" title="View details" aria-label="View details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@endsection
