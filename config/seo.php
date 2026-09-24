<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site identity
    |--------------------------------------------------------------------------
    |
    | Used in <title> suffixes, Open Graph tags and the Organization schema.
    | Kept here rather than hard-coded in templates so a rename is one edit.
    |
    */

    'site_name' => env('SEO_SITE_NAME', 'NoRx Dose'),

    'tagline' => 'Batch-tested supplements and skincare',

    'default_description' => 'Batch-tested supplements and skincare, made in small runs and '
        .'shipped the same day. Every batch has an independent lab report.',

    'locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Default share image
    |--------------------------------------------------------------------------
    |
    | Path relative to public/, used whenever a page has nothing better of its
    | own. Null means no og:image tag is emitted at all, which is better than
    | pointing at a file that does not exist.
    |
    */

    'default_image' => env('SEO_DEFAULT_IMAGE'),

    /*
    |--------------------------------------------------------------------------
    | Social accounts
    |--------------------------------------------------------------------------
    |
    | The Twitter handle goes on the card tags; every URL listed becomes a
    | sameAs entry on the Organization schema, which is how a search engine
    | ties the site to its profiles.
    |
    */

    'twitter' => env('SEO_TWITTER_HANDLE'),

    'profiles' => array_values(array_filter([
        env('SEO_INSTAGRAM_URL'),
        env('SEO_FACEBOOK_URL'),
        env('SEO_LINKEDIN_URL'),
    ])),

    /*
    |--------------------------------------------------------------------------
    | Sitemap
    |--------------------------------------------------------------------------
    |
    | Cached, because it walks three tables and crawlers ask for it far more
    | often than the content changes. Zero disables the cache.
    |
    */

    'sitemap' => [
        'cache_minutes' => (int) env('SEO_SITEMAP_CACHE_MINUTES', 60),

        // Hard ceiling per section. The protocol's own limit is 50,000 URLs
        // per file; this stays well under it without needing an index file.
        'max_per_section' => 2000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feed
    |--------------------------------------------------------------------------
    */

    'feed' => [
        'limit' => 20,
        'cache_minutes' => (int) env('SEO_FEED_CACHE_MINUTES', 30),
    ],

];
