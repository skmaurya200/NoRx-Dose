<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use JsonException;

final class ManagerQuery
{
    public const PARAMETER = 'query';

    private const FROM = '+/=';

    private const MAX_PAYLOAD_LENGTH = 4096;

    private const MAX_VALUE_LENGTH = 500;

    private const TO = '-_~';

    /** @var array<string, list<string>> */
    private const ROUTE_PARAMETERS = [
        'manager.activity-logs.index' => [
            'search', 'admin_id', 'role', 'action', 'status', 'ip', 'from', 'to', 'per_page', 'page',
        ],
        'manager.analytics' => ['from', 'to', 'touch'],
        'manager.blog.categories.index' => ['search', 'status', 'per_page', 'page'],
        'manager.blog.index' => ['search', 'status', 'category_id', 'per_page', 'page'],
        'manager.categories.index' => ['search', 'status', 'parent_id', 'per_page', 'page'],
        'manager.chats.index' => ['search', 'mode', 'status', 'per_page', 'page'],
        'manager.coupons.index' => ['search', 'status', 'type', 'per_page', 'page'],
        'manager.orders.index' => ['search', 'status', 'payment_status', 'trashed', 'per_page', 'page'],
        'manager.products.index' => [
            'search', 'category_id', 'status', 'stock', 'sort', 'direction', 'per_page', 'page',
        ],
        'manager.reviews.index' => [
            'search', 'status', 'rating', 'source', 'product_id', 'trashed', 'per_page', 'page',
        ],
        'manager.search' => ['q'],
        'manager.users.index' => ['search', 'role', 'status', 'per_page', 'page'],
    ];

    public static function supports(string $routeName): bool
    {
        return array_key_exists($routeName, self::ROUTE_PARAMETERS);
    }

    /** @return list<string> */
    public static function allowedParameters(string $routeName): array
    {
        if (! self::supports($routeName)) {
            throw new InvalidArgumentException('The route does not accept an encrypted manager query.');
        }

        return self::ROUTE_PARAMETERS[$routeName];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function url(string $routeName, array $parameters = []): string
    {
        $parameters = self::normalize($routeName, $parameters);

        if ($parameters === []) {
            return route($routeName);
        }

        return route($routeName, [self::PARAMETER => self::seal($routeName, $parameters)]);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    public static function seal(string $routeName, array $parameters): string
    {
        $payload = json_encode([
            'route' => $routeName,
            'parameters' => self::normalize($routeName, $parameters),
        ], JSON_THROW_ON_ERROR);

        return strtr(Crypt::encryptString($payload), self::FROM, self::TO);
    }

    /** @return array<string, string>|null */
    public static function open(string $sealed, string $routeName): ?array
    {
        if ($sealed === '' || mb_strlen($sealed) > self::MAX_PAYLOAD_LENGTH || ! self::supports($routeName)) {
            return null;
        }

        try {
            $payload = json_decode(
                Crypt::decryptString(strtr($sealed, self::TO, self::FROM)),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (! is_array($payload)
            || ($payload['route'] ?? null) !== $routeName
            || ! is_array($payload['parameters'] ?? null)) {
            return null;
        }

        try {
            return self::normalize($routeName, $payload['parameters']);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, string>
     */
    private static function normalize(string $routeName, array $parameters): array
    {
        $allowed = self::allowedParameters($routeName);
        $normalized = [];

        foreach ($parameters as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('The query contains an unsupported parameter.');
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (! is_scalar($value)) {
                throw new InvalidArgumentException('Manager query values must be scalar.');
            }

            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            if (mb_strlen($value) > self::MAX_VALUE_LENGTH) {
                throw new InvalidArgumentException('A manager query value is too long.');
            }

            $normalized[$key] = $value;
        }

        ksort($normalized);

        return $normalized;
    }
}
