<?php

namespace App\Support\Settings;

/**
 * What is editable in Settings.
 *
 * The same shape as App\Support\Content\PageSchema - groups of fields, each
 * with the value the site was built with as its default - so the Settings form
 * reuses the Pages module's field partials rather than growing a second set.
 *
 * Every default here is the exact text the templates were written with, which
 * is what makes "clear a field to put the original back" true rather than
 * approximately true.
 */
final class SettingSchema
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'brand' => [
                'name' => 'Brand',
                'icon' => 'bi-stars',
                'description' => 'The name and the mark that appear in the header, the footer and every browser tab.',
                'fields' => [
                    'name' => [
                        'label' => 'Brand name',
                        'type' => 'text',
                        'default' => 'NoRx Dose',
                        'hint' => 'Used in the header, the footer, page titles and the share previews.',
                    ],
                    'name_accent' => [
                        'label' => 'Second word',
                        'type' => 'text',
                        'default' => 'Wellness',
                        'hint' => 'The part of the name the design sets in gold. Leave blank to style the whole name the same.',
                    ],
                    'tagline' => [
                        'label' => 'Tagline',
                        'type' => 'text',
                        'default' => 'Batch-tested supplements and skincare',
                        'hint' => 'Sits after the name in the home page title.',
                    ],
                    'logo' => [
                        'label' => 'Logo',
                        'type' => 'image',
                        'default' => null,
                        'hint' => 'Replaces the ✦ mark beside the name. Wide and transparent works best - around 200 × 60.',
                    ],
                    'favicon' => [
                        'label' => 'Browser icon',
                        'type' => 'image',
                        'default' => null,
                        'hint' => 'The little icon in the browser tab. Square, 32 × 32 or larger.',
                    ],
                    'footer_about' => [
                        'label' => 'Footer blurb',
                        'type' => 'textarea',
                        'default' => 'Everyday supplements and skincare, made with clinical input and delivered to your door.',
                    ],
                    'copyright' => [
                        'label' => 'Copyright line',
                        'type' => 'text',
                        'default' => 'All rights reserved.',
                        'hint' => 'The year and the brand name are added in front of this automatically.',
                    ],
                ],
            ],

            'contact' => [
                'name' => 'Contact',
                'icon' => 'bi-telephone',
                'description' => 'Printed in the footer and used by the structured data a search engine reads. '
                    .'The email, phone, WhatsApp and contact page below are also what the chat assistant gives a '
                    .'visitor who asks how to reach the team - so only put public contact details here.',
                'fields' => [
                    'email' => [
                        'label' => 'Email address',
                        'type' => 'text',
                        'default' => 'hello@example.com',
                        'hint' => 'Public support email. The chat assistant only gives it out once it has been saved here.',
                    ],
                    'phone' => [
                        'label' => 'Phone number',
                        'type' => 'text',
                        'default' => '',
                        'hint' => 'Left blank, no phone number is printed anywhere.',
                    ],
                    'whatsapp' => [
                        'label' => 'WhatsApp number',
                        'type' => 'text',
                        'default' => '',
                        'hint' => 'Public WhatsApp number, given out by the chat assistant only. Left blank, none is offered.',
                    ],
                    'page_url' => [
                        'label' => 'Contact page URL',
                        'type' => 'url',
                        'default' => '',
                        'hint' => 'A full link (https://…) to a page where visitors can reach the team. Left blank, none is offered.',
                    ],
                    'address' => [
                        'label' => 'Address',
                        'type' => 'text',
                        'default' => '123 Placeholder Street, Suite 400',
                    ],
                    'hours' => [
                        'label' => 'Opening hours',
                        'type' => 'text',
                        'default' => 'Mon–Sun, 9am – 7pm',
                    ],
                ],
            ],

            'ticker' => [
                'name' => 'Ticker',
                'icon' => 'bi-megaphone',
                'description' => 'The scrolling strip above the navigation, on every page. '
                    .'Wrap the opening words in <b> to set them in gold, as the design does. '
                    .'An item left blank is not drawn.',
                'fields' => [
                    'one' => ['label' => 'Item 1', 'type' => 'inline', 'default' => '<b>Free shipping</b> on orders over $50'],
                    'two' => ['label' => 'Item 2', 'type' => 'inline', 'default' => '<b>30-day</b> returns, no questions'],
                    'three' => ['label' => 'Item 3', 'type' => 'inline', 'default' => '<b>Independently</b> batch tested'],
                    'four' => ['label' => 'Item 4', 'type' => 'inline', 'default' => '<b>Carbon-neutral</b> delivery'],
                    'five' => ['label' => 'Item 5', 'type' => 'inline', 'default' => '<b>Recyclable</b> packaging'],
                    'six' => ['label' => 'Item 6', 'type' => 'inline', 'default' => '<b>Support</b> 7 days a week'],
                ],
            ],

            'social' => [
                'name' => 'Social',
                'icon' => 'bi-share',
                'description' => 'A link left blank is not drawn at all, rather than pointing nowhere.',
                'fields' => [
                    'facebook' => ['label' => 'Facebook', 'type' => 'url', 'default' => ''],
                    'instagram' => ['label' => 'Instagram', 'type' => 'url', 'default' => ''],
                    'x' => ['label' => 'X', 'type' => 'url', 'default' => ''],
                    'youtube' => ['label' => 'YouTube', 'type' => 'url', 'default' => ''],
                    'linkedin' => ['label' => 'LinkedIn', 'type' => 'url', 'default' => ''],
                    'twitter_handle' => [
                        'label' => 'X handle',
                        'type' => 'text',
                        'default' => '',
                        'hint' => 'With the @. Goes on the Twitter card tags so shares are attributed.',
                    ],
                ],
            ],

            'seo' => [
                'name' => 'Search defaults',
                'icon' => 'bi-search',
                'description' => 'What a page falls back to when it has nothing of its own. '
                    .'Each page has its own meta title and description under Pages.',
                'fields' => [
                    'meta_description' => [
                        'label' => 'Default description',
                        'type' => 'textarea',
                        'default' => 'Batch-tested supplements and skincare, made in small runs and '
                            .'shipped the same day. Every batch has an independent lab report.',
                        'hint' => 'Around 155 characters. Longer than that and a search engine cuts it off.',
                    ],
                    'share_image' => [
                        'label' => 'Default share image',
                        'type' => 'image',
                        'default' => null,
                        'hint' => 'Shown when a link to this site is pasted into a chat. Around 1200 × 630.',
                    ],
                ],
            ],
        ];
    }

    /* -------------------------------------------------------------- lookups */

    /**
     * Every field, flattened to "group.item" => definition.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function fields(): array
    {
        $fields = [];

        foreach (self::all() as $groupKey => $group) {
            foreach ($group['fields'] as $itemKey => $field) {
                $fields[$groupKey.'.'.$itemKey] = $field + ['type' => 'text'];
            }
        }

        return $fields;
    }

    /**
     * @return array<string, string|null>
     */
    public static function defaults(): array
    {
        return array_map(
            fn (array $field) => $field['default'] ?? null,
            self::fields(),
        );
    }

    /**
     * A dot is Laravel's array separator in a form field name, so the form
     * posts "brand__name" and the request maps it back. Same convention as
     * the Pages module, and for the same reason.
     */
    public static function inputName(string $path): string
    {
        return str_replace('.', '__', $path);
    }
}
