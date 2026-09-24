<div class="topbar">
    <button class="burger" id="burgerBtn" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">
        <i class="bi bi-list"></i>
    </button>

    <form class="search-wrap" role="search" action="{{ route('manager.query') }}" method="POST">
        @csrf
        <input type="hidden" name="target" value="manager.search">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ request('q') }}"
               placeholder="Search orders, products, customers..." aria-label="Search the admin panel">
    </form>

    <div class="topbar-actions">
        <button class="icon-btn" aria-label="Messages">
            <i class="bi bi-envelope"></i>
            @if (! empty($unreadMessages)) <span class="dot"></span> @endif
        </button>
        <button class="icon-btn" aria-label="Notifications">
            <i class="bi bi-bell"></i>
            @if (! empty($unreadNotifications)) <span class="dot"></span> @endif
        </button>

        <div class="dropdown">
            <div class="topbar-profile" data-bs-toggle="dropdown" role="button"
                 aria-expanded="false" tabindex="0">
                <div class="avatar">
                    @if (! empty($adminAvatar))
                        <img src="{{ $adminAvatar }}" alt="" width="38" height="38" style="border-radius:50%;object-fit:cover;">
                    @else
                        {{ $adminInitial ?? 'A' }}
                    @endif
                </div>
                <div class="tp-text">
                    <div class="tp-name">{{ $adminName ?? 'Admin' }}</div>
                    <div class="tp-role">{{ $adminRole ?? '' }}</div>
                </div>
                <i class="bi bi-chevron-down ms-1" style="font-size:11px;"></i>
            </div>

            <ul class="dropdown-menu dropdown-menu-end profile-menu">
                <li>
                    <a class="dropdown-item" href="{{ route('manager.settings') }}">
                        <i class="bi bi-person-gear"></i> My account
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('home') }}" target="_blank" rel="noopener">
                        <i class="bi bi-shop"></i> View storefront
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button type="button" class="dropdown-item" data-logout>
                        <i class="bi bi-box-arrow-right"></i> Sign out
                    </button>
                </li>
                {{-- Admin only. The API refuses anyone else as well. --}}
                @can('signOutEverywhere', \App\Models\Admin::class)
                    <li>
                        <button type="button" class="dropdown-item text-danger" data-logout-all>
                            <i class="bi bi-shield-exclamation"></i> Sign out everywhere
                        </button>
                    </li>
                @endcan
            </ul>
        </div>
    </div>
</div>
