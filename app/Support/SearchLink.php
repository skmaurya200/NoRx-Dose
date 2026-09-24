<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Seals a search term into the URL so it is not readable there.
 *
 * A search term sitting in plain sight in a query string ends up in three
 * places nobody chose to put it: the browser's history and address bar, the
 * server's access logs, and the Referer header sent to any third party the
 * results page happens to load something from. Sealing it keeps it to the one
 * place it belongs - the page itself.
 *
 * Laravel's own encrypter does the work, so the key handling, the IV and the
 * MAC are the framework's rather than something written here. Its output is
 * base64 of a JSON payload, which is around 200 characters: long for a URL,
 * and the price of not inventing a cipher for this.
 *
 * A term that cannot be opened - a link from before the app key was rotated,
 * or one somebody has edited - reads as no search at all. It is a URL a
 * stranger can paste, so it fails to an empty results page rather than to an
 * error.
 */
final class SearchLink
{
    /**
     * Base64's "+", "/" and "=" are all reserved or awkward in a query string.
     * These three are unreserved in RFC 3986, so the swap is reversible and
     * needs no percent-encoding - and it costs no extra length, which a second
     * round of base64 would.
     */
    private const FROM = '+/=';

    private const TO = '-_~';

    public static function seal(string $term): string
    {
        $term = trim($term);

        if ($term === '') {
            return '';
        }

        return strtr(Crypt::encryptString($term), self::FROM, self::TO);
    }

    /**
     * The term behind a sealed value, or "" when there is not one.
     */
    public static function open(?string $sealed): string
    {
        $sealed = trim((string) $sealed);

        if ($sealed === '') {
            return '';
        }

        try {
            return trim(Crypt::decryptString(strtr($sealed, self::TO, self::FROM)));
        } catch (DecryptException) {
            return '';
        }
    }

    /**
     * The results URL for a term, sealed. Every link to a search - the popular
     * chips, the suggestion panel, the panel's own dashboard - goes through
     * here, or one of them would quietly put the term back in the open.
     */
    public static function url(string $term): string
    {
        $term = trim($term);

        return $term === ''
            ? route('search')
            : route('search', ['q' => self::seal($term)]);
    }
}
