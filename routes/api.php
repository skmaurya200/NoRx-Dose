<?php

use App\Http\Controllers\APIs\ActivityLogController;
use App\Http\Controllers\APIs\AuthController;
use App\Http\Controllers\APIs\BlogCategoryController;
use App\Http\Controllers\APIs\BlogMediaController;
use App\Http\Controllers\APIs\BlogPostController;
use App\Http\Controllers\APIs\ChatInboxController;
use App\Http\Controllers\APIs\CouponController;
use App\Http\Controllers\APIs\OrderController;
use App\Http\Controllers\APIs\PageContentController;
use App\Http\Controllers\APIs\ProductCategoryController;
use App\Http\Controllers\APIs\ProductController;
use App\Http\Controllers\APIs\ProductDetailController;
use App\Http\Controllers\APIs\ReviewController;
use App\Http\Controllers\APIs\SettingController;
use App\Http\Controllers\APIs\Storefront\CheckoutController;
use App\Http\Controllers\APIs\Storefront\CouponController as StorefrontCouponController;
use App\Http\Controllers\APIs\Storefront\ReviewController as StorefrontReviewController;
use App\Http\Controllers\APIs\Storefront\SearchController as StorefrontSearchController;
use App\Http\Controllers\APIs\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Manager (back-office) API
|--------------------------------------------------------------------------
|
| Every back-office module gets its own controller under
| App\Http\Controllers\APIs and its own group below.
|
| Convention for new modules:
|   - guest endpoints go in the "auth" group and must carry a throttle
|   - everything else goes inside the auth:sanctum + admin group
|
*/

Route::prefix('manager')->name('api.manager.')->group(function () {

    Route::prefix('auth')->name('auth.')->group(function () {

        // Unauthenticated. The service applies its own identifier+IP throttle;
        // this one is a coarser per-IP brake in front of it, so a spray across
        // many identifiers is capped too.
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:20,1')
            ->name('login');

        // The one-time code step. Each has its own per-IP bucket (the third
        // throttle argument) on top of the per-challenge attempt and re-send
        // limits LoginOtpService enforces, so neither can be brute-forced.
        Route::prefix('otp')->name('otp.')->group(function () {
            Route::post('verify', [AuthController::class, 'verifyOtp'])
                ->middleware('throttle:10,1,otp-verify')
                ->name('verify');
            Route::post('resend', [AuthController::class, 'resendOtp'])
                ->middleware('throttle:6,1,otp-resend')
                ->name('resend');
            Route::post('cancel', [AuthController::class, 'cancelOtp'])
                ->middleware('throttle:20,1,otp-cancel')
                ->name('cancel');
        });

        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Catalogue
    |----------------------------------------------------------------------
    |
    | A write throttle sits on the whole group. It is generous enough that no
    | real operator will ever see it, but it caps what a stolen token can do
    | in a minute - including how many files it can push into public/.
    |
    */
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {

        /* ---------------------------------------------------- categories */
        Route::prefix('categories')->name('categories.')->group(function () {
            // Declared before the {category} routes: otherwise "options" is
            // captured as a category id and never reaches this action.
            Route::get('options', [ProductCategoryController::class, 'options'])->name('options');

            Route::get('/', [ProductCategoryController::class, 'index'])->name('index');
            Route::get('{category}', [ProductCategoryController::class, 'show'])
                ->whereNumber('category')->name('show');

            Route::middleware('throttle:60,1')->group(function () {
                Route::post('/', [ProductCategoryController::class, 'store'])->name('store');

                // POST rather than PUT: multipart carries the image, and PHP
                // does not populate $_FILES on a PUT body.
                Route::post('{category}', [ProductCategoryController::class, 'update'])
                    ->whereNumber('category')->name('update');

                Route::patch('{category}/toggle', [ProductCategoryController::class, 'toggle'])
                    ->whereNumber('category')->name('toggle');

                Route::delete('{category}', [ProductCategoryController::class, 'destroy'])
                    ->whereNumber('category')->name('destroy');
            });
        });

        /* ------------------------------------------------------ products */
        Route::prefix('products')->name('products.')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('{product}', [ProductController::class, 'show'])
                ->whereNumber('product')->name('show');

            Route::middleware('throttle:60,1')->group(function () {
                Route::post('/', [ProductController::class, 'store'])->name('store');
                Route::post('{product}', [ProductController::class, 'update'])
                    ->whereNumber('product')->name('update');
                Route::patch('{product}/status', [ProductController::class, 'updateStatus'])
                    ->whereNumber('product')->name('status');
                Route::delete('{product}', [ProductController::class, 'destroy'])
                    ->whereNumber('product')->name('destroy');
            });
        });

        /* ---------------------------------------------------- coupons */
        Route::prefix('coupons')->name('coupons.')->group(function () {
            Route::get('/', [CouponController::class, 'index'])->name('index');
            Route::get('{coupon}', [CouponController::class, 'show'])
                ->whereNumber('coupon')->name('show');

            Route::middleware('throttle:60,1')->group(function () {
                Route::post('/', [CouponController::class, 'store'])->name('store');
                Route::post('{coupon}', [CouponController::class, 'update'])
                    ->whereNumber('coupon')->name('update');
                Route::patch('{coupon}/toggle', [CouponController::class, 'toggle'])
                    ->whereNumber('coupon')->name('toggle');
                Route::delete('{coupon}', [CouponController::class, 'destroy'])
                    ->whereNumber('coupon')->name('destroy');
            });
        });

        /* ------------------------------------------------------ settings */
        Route::prefix('settings')->name('settings.')->middleware('throttle:60,1')->group(function () {
            Route::post('/', [SettingController::class, 'update'])->name('update');

            // Tighter than the rest: it is a credential change, and the rule
            // on it checks the current password, which makes it guessable.
            Route::post('password', [SettingController::class, 'updatePassword'])
                ->middleware('throttle:10,1')
                ->name('password');
        });

        /* --------------------------------------------------------- users */
        // Admin role only - AdminPolicy authorises every action, so reaching
        // the URL is not enough for a Manager or a User.
        Route::prefix('users')->name('users.')->middleware('throttle:60,1')->group(function () {
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::post('{account}', [UserController::class, 'update'])
                ->whereNumber('account')->name('update');
            Route::patch('{account}/toggle', [UserController::class, 'toggle'])
                ->whereNumber('account')->name('toggle');
            Route::delete('{account}/trusted-devices', [UserController::class, 'revokeDevices'])
                ->whereNumber('account')->name('trusted-devices.destroy');
            Route::delete('{account}', [UserController::class, 'destroy'])
                ->whereNumber('account')->name('destroy');
        });

        /* ------------------------------------------------- activity logs */
        Route::prefix('activity-logs')->name('activity-logs.')->middleware('throttle:60,1')->group(function () {
            Route::post('bulk-delete', [ActivityLogController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::delete('{activityLog}', [ActivityLogController::class, 'destroy'])
                ->whereNumber('activityLog')->name('destroy');
        });

        /* --------------------------------------------------------- pages */
        Route::post('pages/{page}', [PageContentController::class, 'update'])
            ->middleware('throttle:60,1')
            ->name('pages.update');

        /* -------------------------------------------------------- orders */
        // {order:id}: Order resolves on its public token for the storefront
        // confirmation page, and the panel addresses orders by id.
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('{order:id}', [OrderController::class, 'show'])
                ->whereNumber('order')->name('show');

            Route::middleware('throttle:60,1')->group(function () {
                Route::patch('{order:id}/status', [OrderController::class, 'updateStatus'])
                    ->whereNumber('order')->name('status');
                Route::patch('{order:id}/payment-status', [OrderController::class, 'updatePaymentStatus'])
                    ->whereNumber('order')->name('payment-status');

                // Soft delete, and the way back from it. The order keeps its
                // row: a refund or a tax return may still need it.
                Route::delete('{order:id}', [OrderController::class, 'destroy'])
                    ->whereNumber('order')->name('destroy');
                Route::patch('{order}/restore', [OrderController::class, 'restore'])
                    ->whereNumber('order')->name('restore');
            });
        });

        /* --------------------------------------------------------- chats */
        Route::prefix('chats/{session}')->name('chats.')->whereNumber('session')->group(function () {
            // Polled by an open conversation screen, so it is read-only and
            // throttled more generously than the writes below it.
            Route::get('messages', [ChatInboxController::class, 'messages'])
                ->middleware('throttle:240,1')
                ->name('messages');

            Route::middleware('throttle:60,1')->group(function () {
                Route::post('reply', [ChatInboxController::class, 'reply'])->name('reply');
                Route::patch('mode', [ChatInboxController::class, 'mode'])->name('mode');
            });
        });

        /* ------------------------------------------------------- reviews */
        Route::prefix('reviews')->name('reviews.')->group(function () {
            Route::get('/', [ReviewController::class, 'index'])->name('index');

            Route::middleware('throttle:60,1')->group(function () {
                Route::post('/', [ReviewController::class, 'store'])->name('store');
                Route::post('{review}', [ReviewController::class, 'update'])
                    ->whereNumber('review')->name('update');

                // The approve/reject switch on a list row.
                Route::patch('{review}/toggle', [ReviewController::class, 'toggle'])
                    ->whereNumber('review')->name('toggle');

                Route::delete('{review}', [ReviewController::class, 'destroy'])
                    ->whereNumber('review')->name('destroy');
                Route::patch('{review}/restore', [ReviewController::class, 'restore'])
                    ->whereNumber('review')->name('restore');
            });
        });

        /* ------------------------------------------------------- journal */
        Route::prefix('blog')->name('blog.')->group(function () {

            // Images dropped into a post body from the editor. Throttled
            // harder than the rest: it is the only endpoint here that writes
            // a file per call.
            Route::post('uploads', [BlogMediaController::class, 'store'])
                ->middleware('throttle:40,1')
                ->name('uploads.store');

            Route::prefix('categories')->name('categories.')->group(function () {
                Route::get('/', [BlogCategoryController::class, 'index'])->name('index');

                Route::middleware('throttle:60,1')->group(function () {
                    Route::post('/', [BlogCategoryController::class, 'store'])->name('store');
                    Route::post('{category}', [BlogCategoryController::class, 'update'])
                        ->whereNumber('category')->name('update');
                    Route::patch('{category}/toggle', [BlogCategoryController::class, 'toggle'])
                        ->whereNumber('category')->name('toggle');
                    Route::delete('{category}', [BlogCategoryController::class, 'destroy'])
                        ->whereNumber('category')->name('destroy');
                });
            });

            // {post:id}, not {post}: BlogPost resolves on its slug for the
            // storefront URL, and the panel addresses posts by id - including
            // drafts, which have no public URL at all.
            Route::prefix('posts')->name('posts.')->group(function () {
                Route::get('/', [BlogPostController::class, 'index'])->name('index');
                Route::get('{post:id}', [BlogPostController::class, 'show'])
                    ->whereNumber('post')->name('show');

                Route::middleware('throttle:60,1')->group(function () {
                    Route::post('/', [BlogPostController::class, 'store'])->name('store');
                    Route::post('{post:id}', [BlogPostController::class, 'update'])
                        ->whereNumber('post')->name('update');
                    Route::patch('{post:id}/toggle', [BlogPostController::class, 'toggle'])
                        ->whereNumber('post')->name('toggle');
                    Route::delete('{post:id}', [BlogPostController::class, 'destroy'])
                        ->whereNumber('post')->name('destroy');
                });
            });
        });

        /* ------------------------------------------------ product detail */
        Route::prefix('products/{product}')
            ->whereNumber('product')
            ->name('products.detail.')
            // Every {image} must belong to the {product} in the URL. Without
            // this an id from another product would resolve and be mutated.
            ->scopeBindings()
            ->group(function () {
                Route::get('detail', [ProductDetailController::class, 'show'])->name('show');

                Route::middleware('throttle:60,1')->group(function () {
                    Route::post('images', [ProductDetailController::class, 'storeImages'])
                        ->name('images.store');

                    // Before {image}, or "order" is read as an image id.
                    Route::patch('images/order', [ProductDetailController::class, 'reorderImages'])
                        ->name('images.order');

                    Route::patch('images/{image}/primary', [ProductDetailController::class, 'setPrimaryImage'])
                        ->whereNumber('image')->name('images.primary');

                    Route::delete('images/{image}', [ProductDetailController::class, 'destroyImage'])
                        ->whereNumber('image')->name('images.destroy');
                });
            });
    });
});

/*
|--------------------------------------------------------------------------
| Storefront API
|--------------------------------------------------------------------------
|
| Public and unauthenticated: these are called by the cart and the checkout
| pages in a shopper's browser. Every one of them re-derives prices from the
| catalogue, so nothing here trusts what the page sends beyond which product,
| which size and how many.
|
| The throttles are per IP. Placing an order is the tightest of them - it is
| the only endpoint that writes, and a card form is worth brute-force
| protection on its own.
|
*/
Route::prefix('storefront')->name('api.storefront.')->group(function () {

    // Fires on every keystroke in the header box, so the throttle is the
    // loosest here. Read-only, and it returns nothing a visitor could not
    // reach by browsing the shop.
    Route::get('search', [StorefrontSearchController::class, 'suggest'])
        ->middleware('throttle:240,1')
        ->name('search.suggest');

    Route::get('coupons', [StorefrontCouponController::class, 'index'])
        ->middleware('throttle:120,1')
        ->name('coupons.index');

    Route::post('coupons/apply', [StorefrontCouponController::class, 'apply'])
        ->middleware('throttle:30,1')
        ->name('coupons.apply');

    Route::post('checkout/quote', [CheckoutController::class, 'quote'])
        ->middleware('throttle:60,1')
        ->name('checkout.quote');

    Route::post('checkout', [CheckoutController::class, 'place'])
        ->middleware('throttle:10,1')
        ->name('checkout.place');

    // Writing a review is rate limited twice over: this per-IP brake, and a
    // per-address daily cap in ReviewService. Nothing posted here is visible
    // until an operator approves it.
    Route::post('reviews', [StorefrontReviewController::class, 'submit'])
        ->middleware('throttle:10,1')
        ->name('reviews.submit');

    Route::post('reviews/{review}/helpful', [StorefrontReviewController::class, 'helpful'])
        ->whereNumber('review')
        ->middleware('throttle:60,1')
        ->name('reviews.helpful');
});
