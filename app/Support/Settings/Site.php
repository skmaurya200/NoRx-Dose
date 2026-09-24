<?php

namespace App\Support\Settings;

use App\Services\Settings\SettingService;

/**
 * The site's own identity, for the code that cannot be handed a $site bag.
 *
 * Templates get the bag composed in as $site; App\Support\PageSeo and the
 * crawler files are built in controllers and services, and this is how they
 * ask the same question without threading the bag through every call.
 *
 * config/seo.php is still the floor underneath: an operator who has changed
 * nothing gets exactly what the file says, which is what keeps a fresh install
 * working before anybody has opened Settings.
 */
final class Site
{
    public static function settings(): SiteSettings
    {
        return app(SettingService::class)->bag();
    }

    public static function name(): string
    {
        return self::settings()->text('brand.name', (string) config('seo.site_name'));
    }

    public static function tagline(): string
    {
        return self::settings()->text('brand.tagline', (string) config('seo.tagline'));
    }

    public static function description(): string
    {
        return self::settings()->text('seo.meta_description', (string) config('seo.default_description'));
    }

    /**
     * The default share image, as an absolute URL. Null when neither the panel
     * nor the config names one - better than an og:image pointing at a file
     * that is not there.
     */
    public static function shareImage(): ?string
    {
        $uploaded = self::settings()->image('seo.share_image');

        if ($uploaded !== null) {
            return $uploaded;
        }

        $configured = config('seo.default_image');

        return $configured ? asset($configured) : null;
    }

    /**
     * The logo, as an absolute URL, for the Organization schema. Falls back to
     * the share image, because a search engine would rather have the wrong
     * shape than nothing.
     */
    public static function logo(): ?string
    {
        return self::settings()->image('brand.logo') ?? self::shareImage();
    }

    public static function twitterHandle(): ?string
    {
        return self::settings()->text('social.twitter_handle', (string) config('seo.twitter')) ?: null;
    }

    /**
     * Every social profile that has been filled in, for the schema's sameAs.
     *
     * @return array<int, string>
     */
    public static function profiles(): array
    {
        $urls = array_column(self::settings()->socialLinks(), 'url');

        return $urls !== [] ? $urls : array_values((array) config('seo.profiles', []));
    }
}
