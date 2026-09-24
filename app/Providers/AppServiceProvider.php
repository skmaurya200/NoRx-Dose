<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\PageContent;
use App\Models\PersonalAccessToken;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductDetail;
use App\Models\ProductPack;
use App\Observers\AdminObserver;
use App\Observers\ChatKnowledgeObserver;
use App\Services\Auth\PanelSessionService;
use App\Support\Content\PageSchema;
use App\View\Composers\ContentComposer;
use App\View\Composers\FooterComposer;
use App\View\Composers\ManagerComposer;
use App\View\Composers\SettingsComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Nothing may drop a table unless a person deliberately allowed it.
        // The test suite is exempt because RefreshDatabase rebuilds its own
        // in-memory database - but only when the environment really is
        // testing, so a stale config cache that hides phpunit.xml's settings
        // now stops the suite loudly instead of wiping a real database.
        DB::prohibitDestructiveCommands(
            ! $this->app->environment('testing')
                && ! config('database.allow_destructive_commands', false),
        );

        /* The assistant answers out of this shop's own records, so a change to
           any of them is a change to what it knows. Observing the write is how
           that reaches an answer straight away rather than a quarter of an
           hour later, when the cached corpus happens to expire. */
        foreach ([Product::class, ProductDetail::class, ProductPack::class,
            ProductCategory::class, PageContent::class, BlogPost::class] as $model) {
            $model::observe(ChatKnowledgeObserver::class);
        }

        RateLimiter::for('chat', fn (Request $request) => [
            Limit::perMinute(max(1, (int) config('chat.ip_requests_per_minute')))->by('chat-ip:'.$request->ip()),
            Limit::perMinute(60)->by('chat-session:'.hash('sha256', $request->session()->get('chat_owner', $request->session()->getId()))),
        ]);
        RateLimiter::for('chat.messages', fn (Request $request) => Limit::perMinute(max(1, (int) config('chat.requests_per_minute')))
            ->by('chat-message:'.hash('sha256', $request->session()->get('chat_owner', $request->session()->getId()))));
        RateLimiter::for('chat.contacts', fn (Request $request) => Limit::perHour(max(1, (int) config('chat.contacts_per_hour')))
            ->by('chat-contact:'.$request->ip()));

        // Password changes, deactivation and deletion revoke trusted devices,
        // tokens and pending sign-in codes, whichever screen made the change.
        Admin::observe(AdminObserver::class);

        // Every panel sign-in stamps its session, which is what lets an Admin's
        // "sign out everywhere" end sessions it cannot otherwise reach. Covers
        // a sign-in restored from a remember-me cookie as well.
        Event::listen(Login::class, function (Login $event) {
            if ($event->guard === 'admin') {
                app(PanelSessionService::class)->markSignedIn(request());
            }
        });

        // Sanctum's default model points at "personal_access_tokens"; this
        // project stores it as tbl_personal_access_tokens.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Scoped to the manager views rather than shared globally: the
        // storefront has no admin to describe. It covers the whole namespace
        // because the screens read these values too, not only the chrome.
        View::composer('manager.*', ManagerComposer::class);

        // The storefront footer lists real categories on every page.
        View::composer('components.footer', FooterComposer::class);

        // Editable page copy. Bound to the page views by name rather than to
        // the layout: @extends renders the child first, so a composer on the
        // layout would arrive after the sections had already been built.
        View::composer(PageSchema::views(), ContentComposer::class);

        // The brand, the logo and the contact details. Every template may ask
        // for these, so the composer is global rather than a list to maintain.
        View::composer('*', SettingsComposer::class);
    }
}
