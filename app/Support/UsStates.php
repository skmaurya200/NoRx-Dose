<?php

namespace App\Support;

/**
 * The only states the store ships to. One list, read by the checkout <select>
 * and by PlaceOrderRequest, so the form can never offer something the
 * validator would reject.
 *
 * Codes are the USPS two-letter abbreviations, which is what the address is
 * stored as - "CA" survives a rename or a display-language change in a way
 * "California" does not.
 */
final class UsStates
{
    /**
     * The fifty states, the District of Columbia and the inhabited territories
     * that use USPS addressing.
     *
     * @var array<string, string>
     */
    private const STATES = [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District of Columbia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
        'AS' => 'American Samoa',
        'GU' => 'Guam',
        'MP' => 'Northern Mariana Islands',
        'PR' => 'Puerto Rico',
        'VI' => 'U.S. Virgin Islands',
    ];

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::STATES;
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(self::STATES);
    }

    public static function isValid(?string $code): bool
    {
        return $code !== null && isset(self::STATES[mb_strtoupper(trim($code))]);
    }

    public static function name(?string $code): ?string
    {
        return self::STATES[mb_strtoupper(trim((string) $code))] ?? null;
    }
}
