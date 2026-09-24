{{--
    The activity log. Paginated server-side and filtered through sealed manager
    queries. Single and bulk deletion post to the API after a confirmation.
    Every value on this page came from a request somebody made - a user agent,
    an attempted username - so all of it is printed escaped.
--}}
@extends('manager.components.layout')

@section('title', 'Activity logs')

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">Activity logs</h1>
            <p class="ph-sub">
                {{ number_format($logs->total()) }} {{ Str::plural('entry', $logs->total()) }} &middot;
                sign-ins, verification codes, account changes and panel operations
            </p>
        </div>
        <div class="ph-actions">
            <button type="button" class="btn-danger-soft" data-bulk-delete
                    data-endpoint="{{ route('api.manager.activity-logs.bulk-destroy') }}" disabled>
                <i class="bi bi-trash"></i> Delete selected
            </button>
        </div>
    </div>

    <form class="filter-bar" method="POST" action="{{ route('manager.query') }}" data-filter-form
          data-filter-reset-url="{{ route('manager.activity-logs.index') }}">
        @csrf
        <input type="hidden" name="target" value="manager.activity-logs.index">

        <div class="field fb-search">
            <label class="field-label" for="filterSearch">Search</label>
            <input type="search" class="field-input" id="filterSearch" name="search"
                   value="{{ $filters['search'] ?? '' }}" placeholder="Description, username, action or IP…">
        </div>

        <div class="field">
            <label class="field-label" for="filterUser">User</label>
            <select class="field-select" id="filterUser" name="admin_id">
                <option value="">All users</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected((string) ($filters['admin_id'] ?? '') === (string) $account->id)>
                        {{ $account->name }}{{ $account->username ? ' (@'.$account->username.')' : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterRole">Role</label>
            <select class="field-select" id="filterRole" name="role">
                <option value="">All roles</option>
                @foreach ($roles as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterAction">Action</label>
            <select class="field-select" id="filterAction" name="action">
                <option value="">All actions</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterStatus">Status</label>
            <select class="field-select" id="filterStatus" name="status">
                <option value="">All</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterIp">IP address</label>
            <input type="text" class="field-input" id="filterIp" name="ip" maxlength="45"
                   value="{{ $filters['ip'] ?? '' }}" placeholder="e.g. 203.0.113.7">
        </div>

        <div class="field">
            <label class="field-label" for="filterFrom">From</label>
            <input type="date" class="field-input" id="filterFrom" name="from" value="{{ $filters['from'] ?? '' }}">
        </div>

        <div class="field">
            <label class="field-label" for="filterTo">To</label>
            <input type="date" class="field-input" id="filterTo" name="to" value="{{ $filters['to'] ?? '' }}">
        </div>

        <div class="fb-actions">
            <button type="submit" class="btn-ghost"><i class="bi bi-funnel"></i> Filter</button>
            <button type="button" class="btn-ghost" data-filter-reset>Clear</button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" data-list-table @if ($logs->isEmpty()) hidden @endif>
            <thead>
                <tr>
                    <th style="width:36px;">
                        <input type="checkbox" class="form-check-input" data-select-all aria-label="Select all entries on this page">
                    </th>
                    <th>Date / time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP address</th>
                    <th>Status</th>
                    <th>Browser</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr data-row>
                        <td>
                            <input type="checkbox" class="form-check-input" value="{{ $log->id }}" data-select-row
                                   aria-label="Select entry {{ $log->id }}">
                        </td>

                        <td data-label="Date / time">
                            <span class="cell-meta" title="{{ $log->created_at?->toIso8601String() }}">
                                {{ $log->created_at?->format('j M Y, H:i:s') }}
                            </span>
                        </td>

                        <td class="cell-primary" data-label="User">
                            <div class="min-w-0">
                                <span class="cell-title">{{ $log->username ?? 'Unknown' }}</span>
                                <span class="cell-meta">
                                    {{ $log->role ? ($roles[$log->role] ?? Str::headline($log->role)) : 'Not signed in' }}
                                </span>
                            </div>
                        </td>

                        <td data-label="Action"><code>{{ $log->action }}</code></td>

                        <td data-label="Description">{{ $log->description }}</td>

                        <td data-label="IP address"><span class="cell-meta">{{ $log->ip_address ?? '—' }}</span></td>

                        <td data-label="Status">
                            <span class="badge-status {{ $log->status === 'success' ? 'badge-active' : 'badge-danger' }}">
                                {{ $statuses[$log->status] ?? Str::headline($log->status) }}
                            </span>
                        </td>

                        <td data-label="Browser">
                            <span class="cell-meta" title="{{ $log->user_agent }}">{{ Str::limit((string) $log->user_agent, 48) ?: '—' }}</span>
                        </td>

                        <td class="actions" data-label="Actions">
                            <button type="button" class="row-btn row-btn--danger"
                                    data-delete="{{ route('api.manager.activity-logs.destroy', $log) }}"
                                    data-confirm-title="Delete this log entry?"
                                    data-confirm-message="The {{ $log->action }} entry from {{ $log->created_at?->format('j M Y, H:i') }} will be removed permanently."
                                    title="Delete" aria-label="Delete entry">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="empty-state" data-list-empty @if ($logs->isNotEmpty()) hidden @endif>
            <div class="es-icon"><i class="bi bi-clock-history"></i></div>
            <h3>No log entries</h3>
            <p>Nothing matches these filters.</p>
        </div>

        @if ($logs->hasPages())
            <div class="table-foot">
                <span>Showing {{ $logs->firstItem() }}&ndash;{{ $logs->lastItem() }} of {{ number_format($logs->total()) }}</span>
                {{ $logs->onEachSide(1)->links('manager.partials.pagination') }}
            </div>
        @endif
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
    <script src="{{ asset('js/manager/activity-logs.js') }}"></script>
@endpush
