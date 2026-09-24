{{--
    Add / edit a user. Add User has exactly five fields and always creates the
    User role. Edit adds the status switch (where the policy allows it) and an
    optional new password.

    Browser hints are a convenience - StoreUserRequest / UpdateUserRequest
    decide, and their errors are painted under each input. No password is ever
    rendered back into this page.
--}}
@extends('manager.components.layout')

@section('title', $isEdit ? 'Edit user' : 'Add user')

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">{{ $isEdit ? 'Edit user' : 'Add user' }}</h1>
            <p class="ph-sub">
                {{ $isEdit
                    ? 'Changing the password signs this account out of every trusted browser.'
                    : 'The new account gets the User role and signs in with its username, password and a verification code.' }}
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.users.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Back to users
            </a>
        </div>
    </div>

    <form
        data-api-form="{{ $isEdit
            ? route('api.manager.users.update', $account)
            : route('api.manager.users.store') }}"
        data-method="POST"
        data-redirect="{{ route('manager.users.index') }}"
        data-success="{{ $isEdit ? 'User updated.' : 'User created.' }}"
        autocomplete="off"
        novalidate
    >
        @csrf

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="form-card">
                    <h2 class="form-card-title">Account</h2>
                    <p class="form-card-sub">All five fields are required.</p>

                    <div class="field">
                        <label class="field-label" for="name">Name <span class="req">*</span></label>
                        <input type="text" class="field-input" id="name" name="name"
                               value="{{ $account->name }}" maxlength="120" required
                               aria-describedby="name-error">
                        <p class="field-error" id="name-error" data-error-for="name"></p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="username">Username <span class="req">*</span></label>
                        <input type="text" class="field-input" id="username" name="username"
                               value="{{ $account->username }}" maxlength="50" required
                               autocapitalize="none" spellcheck="false" autocomplete="off"
                               aria-describedby="username-error">
                        <p class="field-error" id="username-error" data-error-for="username"></p>
                        <p class="field-hint">Letters, numbers, dots, dashes and underscores. Stored lower-case.</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="email">Email <span class="req">*</span></label>
                        <input type="email" class="field-input" id="email" name="email"
                               value="{{ $account->email }}" maxlength="191" required
                               autocapitalize="none" spellcheck="false"
                               aria-describedby="email-error">
                        <p class="field-error" id="email-error" data-error-for="email"></p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="mobile_no">Mobile No <span class="req">*</span></label>
                        <input type="tel" class="field-input" id="mobile_no" name="mobile_no"
                               value="{{ $account->phone }}" maxlength="20" required inputmode="tel"
                               placeholder="e.g. 919876543210"
                               aria-describedby="mobile_no-error">
                        <p class="field-error" id="mobile_no-error" data-error-for="mobile_no"></p>
                        <p class="field-hint">10 to 15 digits including the country code. Spaces and dashes are removed.</p>
                    </div>

                    <div class="field mb-0">
                        <label class="field-label" for="password">
                            Password
                            @if ($isEdit)
                                <span class="field-opt">Leave blank to keep the current password</span>
                            @else
                                <span class="req">*</span>
                            @endif
                        </label>
                        <input type="password" class="field-input" id="password" name="password"
                               maxlength="255" autocomplete="new-password" @unless ($isEdit) required @endunless
                               aria-describedby="password-error">
                        <p class="field-error" id="password-error" data-error-for="password"></p>
                        <p class="field-hint">At least 8 characters with upper- and lower-case letters, a number and a symbol.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                @if ($isEdit)
                    <div class="form-card">
                        <h2 class="form-card-title">Access</h2>

                        <dl class="kv kv--tight">
                            <div class="kv-row"><dt>Role</dt><dd>{{ $account->roleLabel() }}</dd></div>
                            <div class="kv-row"><dt>Created</dt><dd>{{ $account->created_at?->format('j M Y') }}</dd></div>
                            <div class="kv-row"><dt>Last sign-in</dt><dd>{{ $account->last_login_at?->format('j M Y, H:i') ?? 'Never' }}</dd></div>
                        </dl>

                        @can('changeStatus', $account)
                            <input type="hidden" name="status_present" value="1">
                            <div class="switch-row">
                                <div class="sr-text">
                                    <div class="sr-title">Active</div>
                                    <div class="sr-sub">Off stops sign-in at once and ends open sessions</div>
                                </div>
                                <input type="checkbox" class="switch" name="is_active" value="1"
                                       @checked($account->is_active) aria-label="Active">
                            </div>
                            <p class="field-error" data-error-for="is_active"></p>
                        @else
                            <p class="field-hint mb-0">
                                {{ $admin?->is($account) ? 'You cannot deactivate your own account.' : 'Admin accounts cannot be deactivated here.' }}
                            </p>
                        @endcan
                    </div>

                    <div class="form-card">
                        <h2 class="form-card-title">Trusted devices</h2>
                        <p class="form-card-sub">
                            {{ $trustedDevices }} {{ Str::plural('browser', $trustedDevices) }} currently skip the
                            verification code for this account. The password is always required.
                        </p>

                        @can('revokeDevices', $account)
                            <button type="button" class="btn-ghost" @disabled($trustedDevices === 0)
                                    data-delete="{{ route('api.manager.users.trusted-devices.destroy', $account) }}"
                                    data-confirm-title="Revoke trusted devices?"
                                    data-confirm-message="Every browser will need a new verification code at its next sign-in.">
                                <i class="bi bi-shield-x"></i> Revoke all
                            </button>
                        @endcan
                    </div>
                @endif

                <div class="form-actions">
                    <a href="{{ route('manager.users.index') }}" class="btn-ghost">Cancel</a>
                    <button type="submit" class="btn-gold">
                        <span class="spinner" aria-hidden="true"></span>
                        {{ $isEdit ? 'Save changes' : 'Create User' }}
                    </button>
                </div>
            </div>
        </div>
    </form>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
