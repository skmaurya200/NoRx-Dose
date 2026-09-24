<?php

use App\Http\Controllers\APIs\Storefront\ChatController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Manager\ActivityLogController;
use App\Http\Controllers\Manager\BlogController;
use App\Http\Controllers\Manager\ChatController as ManagerChatController;
use App\Http\Controllers\Manager\CouponController;
use App\Http\Controllers\Manager\ManagerQueryController;
use App\Http\Controllers\Manager\OrderController;
use App\Http\Controllers\Manager\PageController;
use App\Http\Controllers\Manager\PresenceController;
use App\Http\Controllers\Manager\ProductCategoryController;
use App\Http\Controllers\Manager\ProductController;
use App\Http\Controllers\Manager\ReviewController;
use App\Http\Controllers\Manager\SettingController;
use App\Http\Controllers\Manager\UserController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\SeoController;
use App\Http\Middleware\DecryptManagerQuery;
use App\Http\Middleware\ProtectChat;
use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Support\Facades\Route;

// JSON endpoints in the web stack deliberately: guest ownership and CSRF are
// mandatory even when the caller omits Origin/Referer (unlike stateful API detection).
Route::prefix('api/chat')->name('api.chat.')->middleware([ProtectChat::class, 'throttle:chat'])
    ->controller(ChatController::class)->group(function () {
        Route::post('session', 'session')->block(90, 1)->name('session');
        Route::post('message', 'message')->middleware('throttle:chat.messages')->block(90, 1)->name('message');
        Route::get('{sessionUuid}/messages', 'history')->whereUuid('sessionUuid')->block(90, 1)->name('history');
        Route::get('{sessionUuid}/products/{messageUuid}', 'products')
            ->whereUuid(['sessionUuid', 'messageUuid'])->block(90, 1)->name('products');
        Route::post('contact', 'contact')->middleware('throttle:chat.contacts')->block(90, 1)->name('contact');
    });

Route::controller(HomeController::class)->group(function () {
    Route::get('/', 'index')->name('home');
    Route::get('/shop', 'shop')->name('shop');
    Route::get('/search', 'search')->name('search');
    // The box posts here so the term never reaches the address bar in
    // the open; this seals it and redirects to the GET above.
    Route::post('/search', 'searchSubmit')->name('search.submit');
    Route::get('/all-products', 'allProducts')->name('all-products');
    // Slug-addressed, so every product has its own shareable URL.
    Route::get('/product/{slug}', 'product')->name('product');
    Route::get('/reviews', 'reviews')->name('reviews');
    Route::get('/shipping-policy', 'shippingPolicy')->name('shipping-policy');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/about', 'about')->name('about');
    Route::get('/blogs', 'blogs')->name('blogs');
    Route::get('/cart', 'cart')->name('cart');
    Route::get('/checkout', 'checkout')->name('checkout');

    // Addressed by the order's unguessable token, never by its id - the page
    // shows a full billing address.
    Route::get('/order/{order}', 'orderConfirmation')->name('order.confirmation');
});

/*
| Crawler files. Served by routes rather than static files so they follow the
| database and the configured APP_URL - a stale sitemap is worse than none.
*/
Route::controller(SeoController::class)->group(function () {
    Route::get('/robots.txt', 'robots')->name('robots');
    Route::get('/sitemap.xml', 'sitemap')->name('sitemap');
    // Declared before /blogs/{slug}, or "feed.xml" resolves as a post slug.
    Route::get('/blogs/feed.xml', 'feed')->name('blogs.feed');
});

Route::get('/blogs/{slug}', [HomeController::class, 'blogDetails'])->name('blog-details');

Route::prefix('manager')
    ->name('manager.')
    ->controller(ManagerController::class)
    ->group(function () {
        // The sign-in screen itself. Posting happens against the API
        // (POST /api/manager/auth/login), so there is one code path for both
        // the browser panel and any other client.
        Route::get('login', 'showLogin')
            ->middleware([DecryptManagerQuery::class, 'admin.guest'])
            ->name('login');

        // The verification code screen between the password and the panel.
        // The code is posted to POST /api/manager/auth/otp/verify.
        Route::get('otp', 'showOtp')
            ->middleware([DecryptManagerQuery::class, 'admin.guest'])
            ->name('otp');
    });

Route::prefix('manager')
    ->name('manager.')
    ->middleware(['auth:admin', 'admin.active', 'admin.track', DecryptManagerQuery::class])
    ->controller(ManagerController::class)
    ->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/analytics', 'analytics')->name('analytics');
        Route::get('/customers', 'customers')->name('customers');
        Route::get('/shipping', 'shipping')->name('shipping');
        Route::get('/help', 'help')->name('help');
        Route::get('/search', 'search')->name('search');
        Route::post('/query', ManagerQueryController::class)->name('query');
    });

/*
| Catalogue screens. These render HTML only - every create, update and delete
| is posted by the browser to the matching APIs\* controller, so the rules and
| the validation messages have a single implementation.
*/
Route::prefix('manager')
    ->name('manager.')
    ->middleware(['auth:admin', 'admin.active', 'admin.track', DecryptManagerQuery::class])
    ->group(function () {

        /*
        | User Management and the activity log. Admin role only: the can:
        | middleware answers a Manager or a User with 403 even when they type
        | the URL, and the API behind each write checks the policy again.
        */
        Route::controller(UserController::class)
            ->prefix('users')
            ->name('users.')
            ->group(function () {
                Route::get('/', 'index')->can('viewAny', Admin::class)->name('index');
                Route::get('create', 'create')->can('create', Admin::class)->name('create');
                Route::get('{account}/edit', 'edit')->whereNumber('account')
                    ->can('update', 'account')->name('edit');
            });

        Route::get('activity-logs', [ActivityLogController::class, 'index'])
            ->can('viewAny', ActivityLog::class)
            ->name('activity-logs.index');

        // Who is signed in now, and each employee's page trail. Admin only.
        Route::controller(PresenceController::class)
            ->prefix('employees-online')
            ->name('presence.')
            ->middleware('can:viewPresence,'.Admin::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('{account}', 'show')->whereNumber('account')->name('show');
            });

        Route::controller(ProductCategoryController::class)
            ->prefix('categories')
            ->name('categories.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                // Before {category}, or "create" is read as a model key.
                Route::get('create', 'create')->name('create');
                Route::get('{category}/edit', 'edit')->whereNumber('category')->name('edit');
            });

        Route::controller(ProductController::class)
            ->prefix('products')
            ->name('products.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::get('{product}', 'show')->whereNumber('product')->name('show');
                Route::get('{product}/edit', 'edit')->whereNumber('product')->name('edit');
            });

        Route::controller(CouponController::class)
            ->prefix('coupons')
            ->name('coupons.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::get('{coupon}/edit', 'edit')->whereNumber('coupon')->name('edit');
            });

        Route::controller(ReviewController::class)
            ->prefix('reviews')
            ->name('reviews.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::get('{review}/edit', 'edit')->whereNumber('review')->name('edit');
            });

        Route::controller(ManagerChatController::class)
            ->prefix('chats')
            ->name('chats.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('{session}', 'show')->whereNumber('session')->name('show');
            });

        Route::controller(OrderController::class)
            ->prefix('orders')
            ->name('orders.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('{order}', 'show')->whereNumber('order')->name('show');
            });

        Route::get('settings', [SettingController::class, 'index'])->name('settings');

        Route::controller(PageController::class)
            ->prefix('pages')
            ->name('pages.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                // The key is a schema key, not a model - the controller 404s
                // on anything the schema does not name.
                Route::get('{page}', 'edit')->name('edit');
            });

        Route::controller(BlogController::class)
            ->prefix('blog')
            ->name('blog.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                // Both static segments are declared before {post}, or
                // "create" and "categories" resolve as post ids.
                Route::get('create', 'create')->name('create');

                Route::prefix('categories')->name('categories.')->group(function () {
                    Route::get('/', 'categories')->name('index');
                    Route::get('create', 'createCategory')->name('create');
                    Route::get('{category}/edit', 'editCategory')
                        ->whereNumber('category')->name('edit');
                });

                // Bound on the id, like the API - the slug is the storefront's
                // key and a draft has no storefront URL.
                Route::get('{post:id}/edit', 'edit')->whereNumber('post')->name('edit');
            });
    });
