{{--
    User Management. Server-rendered so filters and pagination are sealed
    links; activate/deactivate and delete post to the API, which checks
    AdminPolicy again - the buttons below are only hidden, not trusted.
--}}
@extends('manager.components.layout')

@section('title', 'Users')

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">Users</h1>
            <p class="ph-sub">
                {{ $accounts->total() }} {{ Str::plural('account', $accounts->total()) }} &middot;
                every account signs in with a username, a password and an emailed verification code
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.activity-logs.index') }}" class="btn-ghost">
                <i class="bi bi-clock-history"></i> Activity logs
            </a>
            <a href="{{ route('manager.users.create') }}" class="btn-gold">
                <i class="bi bi-plus-lg"></i> Add user
            </a>
        </div>
    </div>

    <form class="filter-bar" method="POST" action="{{ route('manager.query') }}" data-filter-form
          data-filter-reset-url="{{ route('manager.users.index') }}">
        @csrf
        <input type="hidden" name="target" value="manager.users.index">
        <div class="field fb-search">
            <label class="field-label" for="filterSearch">Search</label>
            <input type="search" class="field-input" id="filterSearch" name="search"
                   value="{{ $filters['search'] ?? '' }}" placeholder="Name, username, email or mobile…">
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
            <label class="field-label" for="filterStatus">Status</label>
            <select class="field-select" id="filterStatus" name="status">
                <option value="">All</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>
        </div>

        <div class="fb-actions">
            <button type="submit" class="btn-ghost"><i class="bi bi-funnel"></i> Filter</button>
            <button type="button" class="btn-ghost" data-filter-reset>Clear</button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" data-list-table @if ($accounts->isEmpty()) hidden @endif>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Mobile No</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($accounts as $account)
                    <tr data-row>
                        <td class="cell-primary">
                            <div class="min-w-0">
                                <a href="{{ route('manager.users.edit', $account) }}" class="cell-title">{{ $account->name }}</a>
                                <span class="cell-meta">
                                    {{ $account->username ? '@'.$account->username : 'No username yet' }}
                                    @if ($admin?->is($account)) &middot; you @endif
                                </span>
                            </div>
                        </td>

                        <td data-label="Email">{{ $account->email }}</td>

                        <td data-label="Mobile No">{{ $account->phone ?: '—' }}</td>

                        <td data-label="Role">
                            <span @class(['badge-status', 'badge-info' => $account->isAdmin(), 'badge-draft' => ! $account->isAdmin()])>
                                {{ $account->roleLabel() }}
                            </span>
                        </td>

                        <td data-label="Status">
                            <span class="badge-status {{ $account->is_active ? 'badge-active' : 'badge-muted' }}" data-status-badge>
                                {{ $account->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>

                        <td data-label="Created">
                            <span class="cell-meta">{{ $account->created_at?->format('j M Y') }}</span>
                        </td>

                        <td class="actions" data-label="Actions">
                            @can('changeStatus', $account)
                                <button type="button" class="row-btn"
                                        data-toggle-active="{{ route('api.manager.users.toggle', $account) }}"
                                        data-on-label="Active" data-off-label="Inactive"
                                        data-on-icon="bi-toggle-on" data-off-icon="bi-toggle-off"
                                        data-on-title="Deactivate this user" data-off-title="Activate this user"
                                        title="{{ $account->is_active ? 'Deactivate this user' : 'Activate this user' }}"
                                        aria-label="Toggle user status">
                                    <i class="bi {{ $account->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                </button>
                            @endcan

                            <a href="{{ route('manager.users.edit', $account) }}"
                               class="row-btn" title="Edit" aria-label="Edit user">
                                <i class="bi bi-pencil"></i>
                            </a>

                            @can('delete', $account)
                                <button type="button" class="row-btn row-btn--danger"
                                        data-delete="{{ route('api.manager.users.destroy', $account) }}"
                                        data-confirm-title="Delete this user?"
                                        data-confirm-message="{{ $account->name }} will be signed out everywhere and can no longer sign in. Their activity log entries are kept."
                                        title="Delete" aria-label="Delete user">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="empty-state" data-list-empty @if ($accounts->isNotEmpty()) hidden @endif>
            <div class="es-icon"><i class="bi bi-person-badge"></i></div>
            <h3>No accounts match</h3>
            <p>Clear the filters, or add a user who can sign in to the panel.</p>
            <a href="{{ route('manager.users.create') }}" class="btn-gold">
                <i class="bi bi-plus-lg"></i> Add user
            </a>
        </div>

        @if ($accounts->hasPages())
            <div class="table-foot">
                <span>Showing {{ $accounts->firstItem() }}&ndash;{{ $accounts->lastItem() }} of {{ $accounts->total() }}</span>
                {{ $accounts->onEachSide(1)->links('manager.partials.pagination') }}
            </div>
        @endif
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
