@php
    use App\Support\Content\PageSchema;

    // Navigation is chrome, so it lives with the markup. Anything that is data —
    // the pending-orders badge — is shared by ManagerController instead.
    //
    // An item may carry `children`, which renders as a disclosure under the
    // parent. Only Pages uses it: the storefront's fixed pages are a list that
    // grows with the site, and burying them behind a second click would make
    // the common case slower than the rare one.
    $sections = [
        'Overview' => [
            ['route' => 'manager.dashboard', 'icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard'],
            ['route' => 'manager.analytics', 'icon' => 'bi-graph-up-arrow', 'label' => 'Analytics'],
        ],
        'Store' => [
            ['route' => 'manager.orders.index', 'icon' => 'bi-bag-check', 'label' => 'Orders', 'match' => 'manager.orders.*', 'badge' => $pendingOrders ?? null],
            // match => the pattern the active state uses, so /products/create
            // and /products/12/edit keep the parent link highlighted.
            ['route' => 'manager.categories.index', 'icon' => 'bi-diagram-3', 'label' => 'Categories', 'match' => 'manager.categories.*'],
            ['route' => 'manager.products.index',   'icon' => 'bi-box-seam',  'label' => 'Products',   'match' => 'manager.products.*'],
            ['route' => 'manager.customers', 'icon' => 'bi-people',    'label' => 'Customers'],
            ['route' => 'manager.reviews.index', 'icon' => 'bi-star', 'label' => 'Reviews', 'match' => 'manager.reviews.*', 'badge' => $pendingReviews ?? null],
            ['route' => 'manager.chats.index', 'icon' => 'bi-chat-dots', 'label' => 'Conversations', 'match' => 'manager.chats.*', 'badge' => $unreadChats ?? null],
            ['route' => 'manager.coupons.index', 'icon' => 'bi-tag',   'label' => 'Discounts', 'match' => 'manager.coupons.*'],
            ['route' => 'manager.shipping',  'icon' => 'bi-truck',     'label' => 'Shipping'],
        ],
        'Content' => [
            [
                'route' => 'manager.pages.index',
                'icon' => 'bi-file-earmark-text',
                'label' => 'Pages',
                'match' => 'manager.pages.*',
                // One child per storefront page, straight from the schema, so
                // adding a page to the module adds it to the menu.
                'children' => collect(PageSchema::all())
                    ->map(fn (array $page, string $key) => [
                        'route' => 'manager.pages.edit',
                        'params' => $key,
                        'label' => $page['name'],
                        'active' => request()->routeIs('manager.pages.edit')
                            && request()->route('page') === $key,
                    ])
                    ->values()
                    ->all(),
            ],
            // Listed rather than 'manager.blog.*', which would also light up
            // while the categories screen below is the one being used.
            ['route' => 'manager.blog.index', 'icon' => 'bi-journal-text', 'label' => 'Journal',
             'match' => ['manager.blog.index', 'manager.blog.create', 'manager.blog.edit']],
            // Its own entry rather than a child of Journal: the sidebar is one
            // level deep everywhere else, and categories are edited on their own.
            ['route' => 'manager.blog.categories.index', 'icon' => 'bi-tags', 'label' => 'Journal categories', 'match' => 'manager.blog.categories.*'],
        ],
        'System' => [
            // `can` hides an entry the signed-in role may not open. Hiding is
            // only cosmetic - the routes and the API enforce the same policy.
            ['route' => 'manager.users.index', 'icon' => 'bi-person-badge', 'label' => 'Users', 'match' => 'manager.users.*',
             'can' => ['viewAny', \App\Models\Admin::class]],
            ['route' => 'manager.activity-logs.index', 'icon' => 'bi-clock-history', 'label' => 'Activity logs', 'match' => 'manager.activity-logs.*',
             'can' => ['viewAny', \App\Models\ActivityLog::class]],
            ['route' => 'manager.settings', 'icon' => 'bi-gear',            'label' => 'Settings'],
            ['route' => 'manager.help',     'icon' => 'bi-question-circle', 'label' => 'Help & support'],
        ],
    ];

    $sections = array_map(
        fn (array $items) => array_values(array_filter(
            $items,
            fn (array $item) => ! isset($item['can']) || (auth('admin')->user()?->can(...$item['can']) ?? false),
        )),
        $sections,
    );
@endphp

<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-mark"><i class="bi bi-stars"></i></div>
        <div>
            <div class="brand-name">Aurum <span>Wellness</span></div>
            <div class="brand-sub">Admin Panel</div>
        </div>
    </div>

    <nav class="nav-scroll" aria-label="Admin sections">
        @foreach ($sections as $label => $items)
            <div class="nav-section-label" @if ($loop->first) style="padding-top:0;" @endif>{{ $label }}</div>
            <ul class="side-nav">
                @foreach ($items as $item)
                    @php
                        $isActive = request()->routeIs($item['match'] ?? $item['route']);
                        $children = $item['children'] ?? [];
                    @endphp
                    <li>
                        <a href="{{ route($item['route']) }}"
                           @class(['side-link', 'active' => $isActive])
                           @if ($isActive) aria-current="page" @endif>
                            <i class="bi {{ $item['icon'] }}"></i> {{ $item['label'] }}
                            @if (! empty($item['badge']))
                                <span class="side-badge">{{ $item['badge'] }}</span>
                            @endif
                            @if ($children)
                                <i class="bi bi-chevron-down side-caret" aria-hidden="true"></i>
                            @endif
                        </a>

                        @if ($children)
                            {{-- Open whenever the parent section is in use, so
                                 moving between pages does not collapse the list
                                 you are moving through. --}}
                            <ul @class(['side-sub', 'is-open' => $isActive])>
                                @foreach ($children as $child)
                                    <li>
                                        <a href="{{ route($child['route'], $child['params'] ?? []) }}"
                                           @class(['side-sublink', 'active' => $child['active'] ?? false])
                                           @if ($child['active'] ?? false) aria-current="page" @endif>
                                            {{ $child['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endforeach
    </nav>

    <div class="sidebar-foot">
        <div class="avatar">
            @if (! empty($adminAvatar))
                <img src="{{ $adminAvatar }}" alt="" width="36" height="36" style="border-radius:50%;object-fit:cover;">
            @else
                {{ $adminInitial ?? 'A' }}
            @endif
        </div>
        <div>
            <div class="name">{{ $adminName ?? 'Admin' }}</div>
            <div class="role">{{ $adminRole ?? '' }}</div>
        </div>
        <button type="button" class="ms-auto btn-logout" data-logout
                title="Sign out" aria-label="Sign out">
            <i class="bi bi-box-arrow-right"></i>
        </button>
    </div>
</aside>
