<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */

    'currency' => 'USD',

    'currency_symbol' => '$',

    /*
    |--------------------------------------------------------------------------
    | What a pack size is counted in
    |--------------------------------------------------------------------------
    |
    | Appended to every size on the product page's chooser, so "30" reads as
    | "30 - Pills". One word for the whole shop by the owner's instruction;
    | change it here if the catalogue stops being tablets.
    |
    */

    'pack_unit_label' => env('SHOP_PACK_UNIT_LABEL', 'Pills'),

    /*
    |--------------------------------------------------------------------------
    | Where the store ships
    |--------------------------------------------------------------------------
    |
    | The storefront sells into the United States only. Both the checkout form
    | and PlaceOrderRequest read this, so the <select> and the validator can
    | never drift apart.
    |
    */

    'country' => 'US',

    'country_name' => 'United States',

    /*
    |--------------------------------------------------------------------------
    | Shipping methods
    |--------------------------------------------------------------------------
    |
    | Declared server-side and handed to the browser, so the price the customer
    | picks is the price the order is charged. A method the server does not
    | know about is a validation failure, not a free delivery.
    |
    */

    'shipping' => [
        [
            'id' => 'fedex',
            'name' => 'Fedex (Overnight):',
            'tag' => 'Fastest',
            'note' => 'Delivered in 1–2 business days',
            'cost' => 50.00,
        ],
        [
            'id' => 'usps',
            'name' => 'U.S.P.S (2-3 Days):',
            'tag' => 'Popular',
            'note' => 'Delivered in 3–5 business days',
            'cost' => 35.00,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Free delivery promotion
    |--------------------------------------------------------------------------
    |
    | Set free_shipping_over to a dollar amount to switch it on; null charges
    | the list prices above exactly as written.
    |
    */

    'free_shipping_over' => null,

    'free_shipping_method' => 'usps',

    /*
    |--------------------------------------------------------------------------
    | Card handling
    |--------------------------------------------------------------------------
    |
    | store_card_cvc keeps the security code alongside the number so the panel
    | can show it. It is switched on at the shop owner's instruction; setting
    | SHOP_STORE_CARD_CVC=false stops new orders writing it, without a code
    | change. Existing rows are left alone either way.
    |
    | Worth knowing before a real card goes through this: PCI-DSS 3.2 prohibits
    | keeping the code after authorisation, and number + expiry + code is
    | everything needed to charge a card that is not present. Both values are
    | encrypted at rest, which protects a database dump and nothing else.
    |
    */

    'store_card_cvc' => (bool) env('SHOP_STORE_CARD_CVC', true),

    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    |
    | Nothing a visitor submits appears on the storefront until an operator
    | approves it, so there is no setting for that - it is not optional.
    |
    */

    'reviews' => [
        'per_page' => 6,

        // How many a product page prints before the list is cut off.
        'per_product' => 20,

        // One address can only file so many before it is somebody testing the
        // form rather than a customer with something to say.
        'max_per_day' => (int) env('REVIEWS_MAX_PER_DAY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Marketing attribution
    |--------------------------------------------------------------------------
    |
    | Where an order came from. The shop takes guest orders - there are no
    | customer accounts - so a visitor is followed by a first-party cookie and
    | nothing else. See App\Http\Middleware\TrackAttribution.
    |
    | The cookie outlives the session on purpose: sessions expire in two hours
    | and a shopper who reads a blog post today and buys next week would
    | otherwise be recorded as direct traffic.
    |
    */

    'attribution' => [
        'cookie' => env('ATTRIBUTION_COOKIE', 'aurum_attr'),

        // 90 days, the window most analytics tools use for a conversion.
        'lifetime_days' => (int) env('ATTRIBUTION_LIFETIME_DAYS', 90),

        // Hosts that mean someone searched for us. Matched on the registrable
        // part, so google.co.uk and www.google.com both count as google.
        'search_engines' => [
            'google' => 'google',
            'bing' => 'bing',
            'yahoo' => 'yahoo',
            'duckduckgo' => 'duckduckgo',
            'yandex' => 'yandex',
            'ecosia' => 'ecosia',
            'baidu' => 'baidu',
            'brave' => 'brave',
        ],

        // Hosts that mean someone followed us from a social network. The key
        // is matched against the referring host; the value is what is stored.
        'social_networks' => [
            'facebook' => 'facebook',
            'fb.com' => 'facebook',
            'instagram' => 'instagram',
            'youtube' => 'youtube',
            'linkedin' => 'linkedin',
            'twitter' => 'twitter',
            'x.com' => 'twitter',
            't.co' => 'twitter',
            'pinterest' => 'pinterest',
            'reddit' => 'reddit',
            'whatsapp' => 'whatsapp',
            'telegram' => 'telegram',
            'tiktok' => 'tiktok',
        ],

        // Ceilings on what is stored. A referrer can be enormous, and none of
        // it past this point is useful.
        'max_value_length' => 100,
        'max_referrer_length' => 500,
        'max_landing_page_length' => 255,
    ],

    /*
    |--------------------------------------------------------------------------
    | Order limits
    |--------------------------------------------------------------------------
    */

    'max_line_quantity' => 99,

    'max_order_lines' => 50,

];
