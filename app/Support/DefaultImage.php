<?php

namespace App\Support;

/**
 * The picture a page shows when the database has none.
 *
 * Every one is a small SVG under public/images/defaults, drawn in the site's
 * own palette. They exist so a template can say
 *
 *     <img src="{{ $product->thumbnailUrl() ?? DefaultImage::product() }}">
 *
 * instead of branching around an empty slot, which is what the storefront used
 * to do at half a dozen places.
 */
final class DefaultImage
{
    /**
     * Slot name => file. Named after what the picture is for rather than what
     * it shows, so swapping the artwork later is one edit here.
     *
     * @var array<string, string>
     */
    private const FILES = [
        'hero' => 'hero.svg',        // wide, dark, text sits over it
        'portrait' => 'portrait.svg', // tall - a practitioner
        'lab' => 'lab.svg',          // landscape - glassware on a bench
        'consult' => 'consult.svg',  // small landscape - notes and a bottle
        'article' => 'article.svg',  // journal cover
        'product' => 'product.svg',  // a bottle, for a card with no photo
    ];

    /**
     * Falls back to the product jar for an unknown slot rather than returning
     * a path to a file that does not exist.
     */
    public static function for(string $slot): string
    {
        return asset('images/defaults/'.(self::FILES[$slot] ?? self::FILES['product']));
    }

    public static function hero(): string
    {
        return self::for('hero');
    }

    public static function portrait(): string
    {
        return self::for('portrait');
    }

    public static function lab(): string
    {
        return self::for('lab');
    }

    public static function consult(): string
    {
        return self::for('consult');
    }

    public static function article(): string
    {
        return self::for('article');
    }

    public static function product(): string
    {
        return self::for('product');
    }
}
