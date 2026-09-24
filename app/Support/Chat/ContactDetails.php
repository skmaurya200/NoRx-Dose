<?php

namespace App\Support\Chat;

/**
 * An email address or a phone number typed into a sentence.
 *
 * The assistant asks for these in conversation now rather than putting a form
 * in front of the visitor, so the reply arrives as ordinary chat text - "sure,
 * asha@example.test" - and has to be found in it.
 *
 * Deliberately deterministic and local. A message that turns out to carry
 * contact details is answered here and never reaches the model: the one rule
 * this module has always had is that a customer's own contact details are not
 * sent to a provider, and the surest way to keep it is to have no code path
 * where they could be.
 */
final class ContactDetails
{
    /**
     * Anything that would be a valid address. Deliberately looser than the
     * RFC: this decides whether to stop and save, not whether to send mail.
     */
    private const EMAIL = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';

    /**
     * Seven to fifteen digits, however they were spaced or bracketed - which
     * covers every national format without pretending to validate one.
     *
     * Anchored on a word boundary so an order reference or a price in the same
     * sentence is not read as a number to call back on.
     */
    private const PHONE = '/(?<![\w.])\+?\d[\d\s().\-]{5,18}\d(?![\w.])/';

    /**
     * What the message offers, or null when it offers neither.
     *
     * @return array{email: string|null, phone: string|null}|null
     */
    public static function find(string $message): ?array
    {
        $email = self::match(self::EMAIL, $message);
        $phone = null;

        if ($email !== null) {
            // The local part of an address is full of digits often enough that
            // looking for a phone number in it finds one.
            $message = str_replace($email, ' ', $message);
        }

        $raw = self::match(self::PHONE, $message);

        if ($raw !== null) {
            $digits = preg_replace('/\D/', '', $raw);

            // Below seven it is a quantity, a year or a dose; above fifteen it
            // is not a number anyone can be reached on.
            if (strlen((string) $digits) >= 7 && strlen((string) $digits) <= 15) {
                $phone = trim($raw);
            }
        }

        if ($email === null && $phone === null) {
            return null;
        }

        return [
            'email' => $email === null ? null : mb_strtolower($email),
            'phone' => $phone,
        ];
    }

    public static function has(string $message): bool
    {
        return self::find($message) !== null;
    }

    private static function match(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $found) === 1 ? $found[0] : null;
    }
}
