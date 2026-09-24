<?php

namespace App\Support;

/**
 * Builds LIKE patterns for search boxes.
 *
 * A raw user term must never go straight into a LIKE: "%" and "_" are wildcards
 * there, so searching for "50%" would match every row and turn a cheap indexed
 * lookup into a full table scan. Escaping them keeps the search literal.
 */
final class SqlLike
{
    /**
     * Wrap a term as a "contains" pattern with its wildcards neutralised.
     */
    public static function contains(?string $term): string
    {
        return '%'.self::escape($term).'%';
    }

    /**
     * Wrap a term as a "starts with" pattern.
     *
     * Kept apart from contains() because the two answer different questions:
     * a name that begins with what was typed is a strong signal, one that
     * merely contains it is not.
     */
    public static function begins(?string $term): string
    {
        return self::escape($term).'%';
    }

    public static function escape(?string $term): string
    {
        // The backslash itself is escaped first by addcslashes, so an input of
        // "\%" cannot smuggle a live wildcard through.
        return addcslashes(trim((string) $term), '%_\\');
    }
}
