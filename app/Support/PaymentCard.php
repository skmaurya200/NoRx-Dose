<?php

namespace App\Support;

/**
 * The card checks the checkout runs before an order is written.
 *
 * Deliberately small: this validates the shape of a card number and works out
 * what to keep. It never stores or transmits anything itself - see
 * App\Models\OrderPayment for what is persisted, and note that the security
 * code is checked here and then dropped, never written anywhere.
 */
final class PaymentCard
{
    /**
     * Digits only, stripped of the spaces and dashes people type.
     */
    public static function digits(?string $number): string
    {
        return preg_replace('/\D/', '', (string) $number) ?? '';
    }

    /**
     * The Luhn checksum every major scheme uses. Catches a mistyped digit or
     * two transposed ones before the order is written, which is the whole
     * point of running it at this end as well as the browser's.
     */
    public static function passesLuhn(?string $number): bool
    {
        $digits = self::digits($number);

        if (strlen($digits) < 13 || strlen($digits) > 19) {
            return false;
        }

        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $value = (int) $digits[$i];

            if ($double) {
                $value *= 2;

                if ($value > 9) {
                    $value -= 9;
                }
            }

            $sum += $value;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }

    /**
     * The scheme, from the issuer identification number. Only used for the
     * label on the order - an unrecognised prefix is "Card", not a rejection,
     * because new BIN ranges appear all the time.
     */
    public static function brand(?string $number): string
    {
        $digits = self::digits($number);

        return match (true) {
            (bool) preg_match('/^4/', $digits) => 'Visa',
            (bool) preg_match('/^(5[1-5]|2(2[2-9][1-9]|[3-6]|7[01]|720))/', $digits) => 'Mastercard',
            (bool) preg_match('/^3[47]/', $digits) => 'American Express',
            (bool) preg_match('/^3(0[0-5]|[68])/', $digits) => 'Diners Club',
            (bool) preg_match('/^6(011|5|4[4-9]|22)/', $digits) => 'Discover',
            (bool) preg_match('/^(2131|1800|35)/', $digits) => 'JCB',
            default => 'Card',
        };
    }

    /**
     * The last four digits - the only part of the number a person is ever
     * shown, on a receipt or in the panel.
     */
    public static function last4(?string $number): string
    {
        return substr(self::digits($number), -4);
    }

    /**
     * How long the security code should be for this scheme. Amex prints four
     * digits on the front, everyone else prints three on the back.
     */
    public static function cvcLength(?string $number): int
    {
        return self::brand($number) === 'American Express' ? 4 : 3;
    }

    public static function isValidCvc(?string $cvc, ?string $number): bool
    {
        $cvc = preg_replace('/\D/', '', (string) $cvc) ?? '';

        return strlen($cvc) === self::cvcLength($number);
    }

    /**
     * Splits "MM / YY", "MM/YYYY" or "MMYY" into a month and a four-digit year.
     *
     * @return array{month: int, year: int}|null null when it is not a date
     */
    public static function parseExpiry(?string $expiry): ?array
    {
        $digits = preg_replace('/\D/', '', (string) $expiry) ?? '';

        if (strlen($digits) !== 4 && strlen($digits) !== 6) {
            return null;
        }

        $month = (int) substr($digits, 0, 2);
        $year = (int) substr($digits, 2);

        if ($month < 1 || $month > 12) {
            return null;
        }

        // Two digits are this century: a card printed "05 / 27" expires in
        // 2027, and no card in circulation expires in 1927.
        if ($year < 100) {
            $year += 2000;
        }

        return ['month' => $month, 'year' => $year];
    }

    /**
     * A card is good through the last day of its printed month.
     */
    public static function isExpired(int $month, int $year): bool
    {
        $now = now();

        return $year < $now->year || ($year === $now->year && $month < $now->month);
    }
}
