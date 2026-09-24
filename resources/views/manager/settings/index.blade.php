{{--
    Site settings.

    The tabs and every input are generated from
    App\Support\Settings\SettingSchema, and each field type is drawn by the
    Pages module's partials - the two modules store the same shape, so they
    share the same form furniture rather than growing a second set of it.

    Two forms, not one, and the tab strip sits outside both. The site's details
    save with no questions asked; the password asks for the current one, and
    putting them together would mean typing a password to edit a phone number.
    A form inside a form is not valid HTML, so they are siblings and the tabs
    reach into each of them by id.
--}}
@extends('manager.components.layout')

@section('title', 'Settings')

@section('content')

    <div class="page-head page-head--tight">
        <div>
            <h1 class="font-serif">Settings</h1>
            <p class="ph-sub">
                The name, the logo and the contact details the whole storefront reads.
                Clear a field to put its original wording back.
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('home') }}" class="btn-ghost" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right"></i> View site
            </a>
            <a href="{{ route('manager.pages.index') }}" class="btn-ghost">
                <i class="bi bi-file-earmark-text"></i> Page content
            </a>
        </div>
    </div>

    <div class="form-tabs" role="tablist" aria-label="Setting groups">
        @foreach ($groups as $key => $group)
            <button type="button" role="tab"
                    @class(['form-tab', 'active' => $loop->first])
                    data-tab-target="tab-{{ $key }}"
                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                    aria-controls="tab-{{ $key }}">
                <i class="bi {{ $group['icon'] ?? 'bi-square' }}"></i> {{ $group['name'] }}
                <span class="tab-dot" aria-hidden="true"></span>
            </button>
        @endforeach

        <button type="button" role="tab" class="form-tab"
                data-tab-target="tab-security"
                aria-selected="false" aria-controls="tab-security">
            <i class="bi bi-shield-lock"></i> Password
            <span class="tab-dot" aria-hidden="true"></span>
        </button>
    </div>

    <form
        data-api-form="{{ route('api.manager.settings.update') }}"
        data-method="POST"
        data-success="Settings saved."
        novalidate
    >
        @csrf

        @foreach ($groups as $key => $group)
            <div @class(['tab-pane-custom', 'active' => $loop->first]) id="tab-{{ $key }}" role="tabpanel">
                <div class="row g-3">
                    <div class="col-lg-9">
                        <div class="form-card">
                            <h2 class="form-card-title">{{ $group['name'] }}</h2>

                            @if (! empty($group['description']))
                                <p class="form-card-sub">{{ $group['description'] }}</p>
                            @endif

                            <div class="row g-3">
                                @foreach ($group['fields'] as $itemKey => $field)
                                    @php
                                        $path = $key.'.'.$itemKey;
                                        $input = \App\Support\Settings\SettingSchema::inputName($path);
                                        $type = $field['type'] ?? 'text';
                                    @endphp

                                    @include('manager.pages.fields.'.($type === 'image' ? 'image' : 'text'), [
                                        'path' => $path,
                                        'input' => $input,
                                        'field' => $field,
                                        'saved' => $content->saved($path) ?? '',
                                    ])
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="form-card">
                            <h2 class="form-card-title">This group</h2>
                            <dl class="kv kv--tight">
                                <div class="kv-row"><dt>Applies to</dt><dd>The whole site</dd></div>
                                <div class="kv-row"><dt>Fields</dt><dd>{{ count($group['fields']) }}</dd></div>
                            </dl>
                            <p class="field-hint mb-0">
                                Every tab except Password saves together, so you can edit
                                several before pressing Save once.
                            </p>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-gold">
                                <span class="spinner" aria-hidden="true"></span>
                                Save settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </form>

    <form
        data-api-form="{{ route('api.manager.settings.password') }}"
        data-method="POST"
        id="passwordForm"
        novalidate
    >
        @csrf

        <div class="tab-pane-custom" id="tab-security" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-9">
                    <div class="form-card">
                        <h2 class="form-card-title">Change your password</h2>
                        <p class="form-card-sub">
                            This changes the password for <b>{{ $adminName }}</b> only. Saving it signs
                            out every other device you are signed in on.
                        </p>

                        <div class="field">
                            <label class="field-label" for="current_password">
                                Current password <span class="req">*</span>
                            </label>
                            <input type="password" class="field-input" id="current_password"
                                   name="current_password" autocomplete="current-password" required
                                   aria-describedby="current_password-error">
                            <p class="field-error" id="current_password-error" data-error-for="current_password"></p>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="field mb-0">
                                    <label class="field-label" for="password">
                                        New password <span class="req">*</span>
                                    </label>
                                    <input type="password" class="field-input" id="password" name="password"
                                           autocomplete="new-password" required aria-describedby="password-error">
                                    <p class="field-error" id="password-error" data-error-for="password"></p>
                                    <p class="field-hint">At least 8 characters, with a letter and a number.</p>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="field mb-0">
                                    <label class="field-label" for="password_confirmation">
                                        Repeat it <span class="req">*</span>
                                    </label>
                                    <input type="password" class="field-input" id="password_confirmation"
                                           name="password_confirmation" autocomplete="new-password" required
                                           aria-describedby="password_confirmation-error">
                                    <p class="field-error" id="password_confirmation-error"
                                       data-error-for="password_confirmation"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="form-card">
                        <h2 class="form-card-title">Signed in as</h2>
                        <dl class="kv kv--tight">
                            <div class="kv-row"><dt>Name</dt><dd>{{ $adminName }}</dd></div>
                            @if (! empty($admin))
                                <div class="kv-row"><dt>Email</dt><dd>{{ $admin->email }}</dd></div>
                                <div class="kv-row"><dt>Role</dt><dd>{{ $adminRole }}</dd></div>
                            @endif
                        </dl>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-gold">
                            <span class="spinner" aria-hidden="true"></span>
                            Change password
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
    <script>
        /* Emptied once the change goes through, so the old password is not
           left sitting in three boxes on a screen somebody walks past. */
        document.getElementById('passwordForm')
            .addEventListener('aurum:saved', function (event) {
                event.currentTarget.reset();
            });
    </script>
@endpush
