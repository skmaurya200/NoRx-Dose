<?php

namespace App\Support\Content;

/**
 * What is editable on each storefront page.
 *
 * This is the single source of truth for the Pages module: the panel builds
 * its forms from it, the storefront reads its defaults from it, and adding a
 * field is one entry here plus one call in the template.
 *
 * Two rules keep the design safe from the editor:
 *
 *  - every field carries the exact text the template was written with as its
 *    default, so a page nobody has edited renders byte-for-byte as before;
 *  - a heading is type "inline", which permits <em> and <br> and nothing else.
 *    The italic word in "Modern science. Bespoke wellness." is a design
 *    element, so it has to stay editable without opening the page to markup.
 */
final class PageSchema
{
    /**
     * Field types the panel knows how to render and the service knows how to
     * store. Anything else would silently fall through to a text input.
     */
    public const TYPES = ['text', 'inline', 'textarea', 'image', 'url', 'repeater'];

    /**
     * Every page, each with a "Search" section appended.
     *
     * The SEO fields are added here rather than written into each page below,
     * so a page added to pages() gets its meta title and description without
     * anyone having to remember them - which is exactly the kind of thing
     * everyone forgets.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $pages = [];

        foreach (self::pages() as $key => $page) {
            $page['sections']['seo'] = self::seoSection($page['name']);
            $pages[$key] = $page;
        }

        return $pages;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function pages(): array
    {
        return [
            'home' => [
                'name' => 'Home',
                'route' => 'home',
                'view' => 'home',
                'sections' => [
                    'hero' => self::homeHero(),
                    'about' => self::homeAbout(),
                    'stats' => self::homeStats(),
                    'cats' => self::railHead('Categories', 'bi-diagram-3', 'Browse', 'Shop by category',
                        'The tiles underneath come from the Categories module.'),
                    'favourites' => self::railHead('Favourites', 'bi-star', 'Popular now', 'Customer favourites',
                        'The products in the rail are whichever ones are flagged as featured.'),
                    'band' => self::homeBand(),
                    'arrivals' => self::railHead('New arrivals', 'bi-box-seam', 'Just landed', 'New arrivals',
                        'The rail shows the newest published products.'),
                    'journal' => self::homeJournal(),
                    'faq' => self::homeFaq(),
                    'cta' => self::homeCta(),
                ],
            ],

            'about' => [
                'name' => 'About',
                'route' => 'about',
                'view' => 'about',
                'sections' => [
                    'hero' => self::simpleHero(
                        'Our story and mission',
                        'Careful products, <em>made properly.</em>',
                        'We started with one belief — that everyday wellness products should be '
                            .'simple to understand, honestly made, and easy to get hold of.',
                    ),
                    'proof' => self::aboutProof(),
                    'chapters' => self::aboutChapters(),
                    'quotes' => self::aboutQuotes(),
                    'faq' => self::aboutFaq(),
                    'cta' => self::aboutCta(),
                ],
            ],

            'faq' => [
                'name' => 'FAQ',
                'route' => 'faq',
                'view' => 'faq',
                'sections' => [
                    'hero' => self::simpleHero(
                        'Help centre',
                        'Answers, <em>simplified.</em>',
                        'Search or browse by topic — everything about orders, delivery and returns in one place.',
                    ),
                    'search' => self::faqSearch(),
                    'stats' => self::faqStats(),
                    'topics' => self::faqTopics(),
                    'questions' => self::faqQuestions(),
                    'help' => self::faqHelp(),
                ],
            ],

            'reviews' => [
                'name' => 'Reviews',
                'route' => 'reviews',
                'view' => 'reviews',
                'sections' => [
                    'hero' => self::simpleHero(
                        'Real customers, real stories',
                        'Trusted by <em>thousands</em> of customers',
                        'What people say about the products, the packaging, and the support team behind them.',
                    ),
                    'list' => self::reviewsList(),
                    'form' => self::reviewsForm(),
                    'why' => self::reviewsWhy(),
                    'faq' => self::reviewsFaq(),
                    'cta' => self::reviewsCta(),
                ],
            ],

            'shipping-policy' => [
                'name' => 'Shipping policy',
                'route' => 'shipping-policy',
                'view' => 'shipping-policy',
                'sections' => [
                    'hero' => self::simpleHero(
                        'Shipping policy',
                        'Fast and reliable <em>delivery, every time.</em>',
                        'We work with trusted carriers nationwide so every order arrives safely and on '
                            .'schedule — wherever you call home.',
                    ),
                    'pills' => self::shippingPills(),
                    'overview' => self::shippingOverview(),
                    'journey' => self::shippingJourney(),
                    'speeds' => self::shippingSpeeds(),
                    'policy' => self::shippingPolicyDetails(),
                    'faq' => self::shippingFaq(),
                    'help' => self::shippingHelp(),
                ],
            ],

            'all-products' => [
                'name' => 'All products',
                'route' => 'all-products',
                'view' => 'all-products',
                'sections' => [
                    'hero' => [
                        'name' => 'Page heading',
                        'icon' => 'bi-window',
                        'fields' => [
                            'title' => [
                                'label' => 'Heading',
                                'type' => 'inline',
                                'default' => 'All <em>products</em>',
                            ],
                        ],
                    ],
                ],
            ],

            // Shop, cart and checkout carry no heading band of their own -
            // the design puts the products, the basket and the form straight
            // under the nav. Only the copy that is actually on those pages is
            // listed here; inventing a hero for them would change the layout,
            // which this module never does.
            'shop' => [
                'name' => 'Shop',
                'route' => 'shop',
                'view' => 'shop',
                'sections' => ['newsletter' => self::shopNewsletter()],
            ],

            'cart' => [
                'name' => 'Cart',
                'route' => 'cart',
                'view' => 'cart',
                'sections' => ['page' => self::cartCopy()],
            ],

            'checkout' => [
                'name' => 'Checkout',
                'route' => 'checkout',
                'view' => 'checkout',
                'sections' => ['page' => self::checkoutCopy()],
            ],

            'blogs' => [
                'name' => 'Journal',
                'route' => 'blogs',
                'view' => 'blogs',
                'sections' => [
                    'hero' => [
                        'name' => 'Page heading',
                        'icon' => 'bi-window',
                        'fields' => [
                            'eyebrow' => [
                                'label' => 'Eyebrow',
                                'type' => 'text',
                                'default' => 'Reading',
                            ],
                            'title' => [
                                'label' => 'Heading',
                                'type' => 'inline',
                                'default' => 'The <em>journal</em>',
                                'hint' => 'Wrap a word in <em> to set it in italics.',
                            ],
                            'subtitle' => [
                                'label' => 'Intro',
                                'type' => 'textarea',
                                'default' => 'Notes on ingredients, routines and how the products are '
                                    .'actually made. No launch announcements.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------- sections */

    /**
     * What one page tells a search engine about itself.
     *
     * Every default is deliberately empty rather than a copy of the page's
     * heading: blank means "fall back to the site defaults in Settings", and
     * a half-written title is worse for a page than no title at all.
     *
     * @return array<string, mixed>
     */
    private static function seoSection(string $pageName): array
    {
        return [
            'name' => 'Search',
            'icon' => 'bi-search',
            'description' => 'What a search engine and a shared link show for the '
                .mb_strtolower($pageName).' page. Leave a field blank to use the site defaults from Settings.',
            'fields' => [
                'title' => [
                    'label' => 'Meta title',
                    'type' => 'text',
                    'default' => '',
                    'hint' => 'Around 60 characters. The brand name is added to the end automatically.',
                ],
                'description' => [
                    'label' => 'Meta description',
                    'type' => 'textarea',
                    'default' => '',
                    'hint' => 'Around 155 characters. Longer than that and a search engine cuts it off.',
                ],
                'share_image' => [
                    'label' => 'Share image',
                    'type' => 'image',
                    'default' => null,
                    'hint' => 'Shown when a link to this page is pasted into a chat. Around 1200 × 630.',
                ],
            ],
        ];
    }

    /**
     * The badge / heading / intro trio every inner page opens with.
     *
     * @return array<string, mixed>
     */
    private static function simpleHero(string $badge, string $title, string $subtitle): array
    {
        return [
            'name' => 'Hero',
            'icon' => 'bi-window',
            'description' => 'The band at the top of the page. The icon beside the badge is part of the design and stays put.',
            'fields' => [
                'badge' => [
                    'label' => 'Badge',
                    'type' => 'text',
                    'default' => $badge,
                    'hint' => 'The small pill above the heading.',
                ],
                'title' => [
                    'label' => 'Heading',
                    'type' => 'inline',
                    'default' => $title,
                    'hint' => 'Wrap a word in <em> to set it in italics, as the design does.',
                ],
                'subtitle' => [
                    'label' => 'Intro',
                    'type' => 'textarea',
                    'default' => $subtitle,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function homeHero(): array
    {
        return [
            'name' => 'Hero',
            'icon' => 'bi-window',
            'description' => 'The full-height opening band. Leave the image empty to keep the placeholder the design ships with.',
            'fields' => [
                'background' => [
                    'label' => 'Background image',
                    'type' => 'image',
                    'default' => null,
                    'hint' => 'Wide and dark works best - the text sits over a scrim. Around 1600 × 900.',
                ],
                'tag' => [
                    'label' => 'Tag',
                    'type' => 'text',
                    'default' => 'Formulated with care',
                    'hint' => 'The small pill above the heading.',
                ],
                'title' => [
                    'label' => 'Heading',
                    'type' => 'inline',
                    'default' => 'Modern science.<br><em>Bespoke</em> wellness.',
                    'hint' => 'Use <br> for the line break and <em> for the italic word.',
                ],
                'subtitle' => [
                    'label' => 'Intro',
                    'type' => 'textarea',
                    'default' => 'Everyday supplements and skincare, developed with clinical input and '
                        .'tested independently before every batch ships.',
                ],
                'primary_label' => [
                    'label' => 'First button',
                    'type' => 'text',
                    'default' => 'Shop the range',
                ],
                'primary_link' => [
                    'label' => 'First button link',
                    'type' => 'url',
                    'default' => '#featured',
                    'hint' => 'A path like /shop, a full address, or #section to scroll down the page.',
                ],
                'secondary_label' => [
                    'label' => 'Second button',
                    'type' => 'text',
                    'default' => 'How it works',
                ],
                'secondary_link' => [
                    'label' => 'Second button link',
                    'type' => 'url',
                    'default' => '#about',
                ],
                'scroll_label' => [
                    'label' => 'Scroll hint',
                    'type' => 'text',
                    'default' => 'SCROLL',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function homeAbout(): array
    {
        return [
            'name' => 'What we stand for',
            'icon' => 'bi-patch-check',
            'description' => 'The section directly under the hero: copy on the left, image collage on the right.',
            'fields' => [
                'eyebrow' => [
                    'label' => 'Eyebrow',
                    'type' => 'text',
                    'default' => 'What we stand for',
                ],
                'title' => [
                    'label' => 'Heading',
                    'type' => 'inline',
                    'default' => 'Expert-led care,<br>personally tailored.',
                    'hint' => 'Use <br> for the line break.',
                ],
                'lead' => [
                    'label' => 'Lead paragraph',
                    'type' => 'textarea',
                    'default' => 'Behind every formula is a small team of researchers and practitioners. '
                        .'We bridge the gap between convenience and real expertise, so you know exactly '
                        .'what you are putting on and in your body.',
                ],

                'card_one_title' => [
                    'label' => 'First card - title',
                    'type' => 'text',
                    'default' => 'Reviewed formulas',
                ],
                'card_one_text' => [
                    'label' => 'First card - text',
                    'type' => 'textarea',
                    'default' => 'Every ingredient list is checked by a qualified reviewer before launch.',
                ],
                'card_two_title' => [
                    'label' => 'Second card - title',
                    'type' => 'text',
                    'default' => 'One small team',
                ],
                'card_two_text' => [
                    'label' => 'Second card - text',
                    'type' => 'textarea',
                    'default' => 'Researchers, formulators and support staff working from one brief.',
                ],

                'image_tall' => [
                    'label' => 'Collage - tall image',
                    'type' => 'image',
                    'default' => null,
                    'hint' => 'Portrait, around 600 × 800.',
                ],
                'image_wide' => [
                    'label' => 'Collage - small image',
                    'type' => 'image',
                    'default' => null,
                    'hint' => 'Landscape, around 480 × 300.',
                ],
                'badge_value' => [
                    'label' => 'Badge figure',
                    'type' => 'text',
                    'default' => '100',
                    'hint' => 'Counts up from zero as the section scrolls into view. Digits only.',
                ],
                'badge_suffix' => [
                    'label' => 'Badge suffix',
                    'type' => 'text',
                    'default' => '%',
                ],
                'badge_label' => [
                    'label' => 'Badge caption',
                    'type' => 'text',
                    'default' => 'Batch tested',
                ],
            ],
        ];
    }

    /**
     * The heading pair above a rail whose contents come from somewhere else -
     * the categories, the featured products, the journal. Only the words are
     * editable here; what is listed underneath belongs to its own module.
     *
     * @return array<string, mixed>
     */
    private static function railHead(
        string $name,
        string $icon,
        string $eyebrow,
        string $title,
        string $note,
    ): array {
        return [
            'name' => $name,
            'icon' => $icon,
            'description' => $note.' Only the heading above it is edited here.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => $eyebrow],
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => $title],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function homeStats(): array
    {
        // The four figures count up as the band scrolls into view. The comma
        // grouping is per slot and belongs to the design, so it is not here.
        $slots = [
            'one' => ['1402', '', 'Formulas tested'],
            'two' => ['98', '%', 'Would reorder'],
            'three' => ['24', 'h', 'Dispatch time'],
            'four' => ['50000', '+', 'Customers served'],
        ];

        $fields = [];
        $position = 1;

        foreach ($slots as $slot => [$value, $suffix, $label]) {
            $fields[$slot.'_value'] = [
                'label' => 'Figure '.$position,
                'type' => 'text',
                'default' => $value,
                'hint' => 'Digits only - it animates up from zero.',
            ];
            $fields[$slot.'_suffix'] = [
                'label' => 'Figure '.$position.' suffix',
                'type' => 'text',
                'default' => $suffix,
            ];
            $fields[$slot.'_label'] = [
                'label' => 'Figure '.$position.' caption',
                'type' => 'text',
                'default' => $label,
            ];

            $position++;
        }

        return [
            'name' => 'Stats',
            'icon' => 'bi-graph-up',
            'description' => 'The four counters. Each one animates up from zero when it scrolls into view.',
            'fields' => $fields,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function homeBand(): array
    {
        return [
            'name' => 'Fresh from research',
            'icon' => 'bi-flask',
            'description' => 'The two-column band: picture on the left, copy and a list on the right.',
            'fields' => [
                'image' => [
                    'label' => 'Image',
                    'type' => 'image',
                    'default' => null,
                    'hint' => 'Landscape, around 800 × 600.',
                ],
                'chip' => ['label' => 'Chip on the image', 'type' => 'text', 'default' => 'In the lab'],
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Fresh from research'],
                'title' => [
                    'label' => 'Heading',
                    'type' => 'inline',
                    'default' => 'Three new formulas,<br>just released.',
                    'hint' => 'Use <br> for the line break.',
                ],
                'text' => [
                    'label' => 'Paragraph',
                    'type' => 'textarea',
                    'default' => 'Our research team has finished validating three new products across skin, '
                        .'rest and daily nutrition. Here is what changed in this round.',
                ],
                'item_one' => [
                    'label' => 'List - first line',
                    'type' => 'text',
                    'default' => 'Reformulated with a gentler base',
                ],
                'item_two' => [
                    'label' => 'List - second line',
                    'type' => 'text',
                    'default' => 'Fragrance-free across the full line',
                ],
                'item_three' => [
                    'label' => 'List - third line',
                    'type' => 'text',
                    'default' => 'Refill pouches for every size',
                    'hint' => 'Leave any line empty to drop it from the list.',
                ],
                'button_label' => ['label' => 'Button', 'type' => 'text', 'default' => "See what's new"],
                'button_link' => ['label' => 'Button link', 'type' => 'url', 'default' => '#new'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function homeJournal(): array
    {
        return [
            'name' => 'Journal',
            'icon' => 'bi-journal-text',
            'description' => 'The posts themselves come from the Journal module. Only the heading and the link are edited here.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Reading'],
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => 'The journal'],
                'link_label' => ['label' => 'Link', 'type' => 'text', 'default' => 'Read all posts'],
                'empty_text' => [
                    'label' => 'Shown when there are no posts',
                    'type' => 'text',
                    'default' => 'The first post is on its way.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function homeCta(): array
    {
        return [
            'name' => 'Closing call to action',
            'icon' => 'bi-megaphone',
            'description' => 'The dark band at the foot of the page.',
            'fields' => [
                'tag' => ['label' => 'Tag', 'type' => 'text', 'default' => 'Start today'],
                'title' => [
                    'label' => 'Heading',
                    'type' => 'inline',
                    'default' => 'Ready to feel <em>your best?</em>',
                    'hint' => 'Wrap a phrase in <em> to set it in italics.',
                ],
                'text' => [
                    'label' => 'Paragraph',
                    'type' => 'textarea',
                    'default' => 'Free shipping over $50, 30-day returns, and a support team that answers '
                        .'seven days a week.',
                ],
                'primary_label' => ['label' => 'First button', 'type' => 'text', 'default' => 'Shop all products'],
                'primary_link' => ['label' => 'First button link', 'type' => 'url', 'default' => '#featured'],
                'secondary_label' => ['label' => 'Second button', 'type' => 'text', 'default' => 'Contact us'],
                'secondary_link' => ['label' => 'Second button link', 'type' => 'url', 'default' => '#'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function homeFaq(): array
    {
        /*
         | Written against what this shop actually does, not against what a
         | shop usually does. Two things it does not do are stated rather than
         | skirted: there is no parcel tracking, and there is no returns or
         | refunds policy. A customer finding that out after paying is the
         | complaint these answers exist to prevent.
         |
         | Deliberately no prices or delivery times in the prose. Those live in
         | config/shop.php, they are printed at checkout from it, and the chat
         | assistant indexes this copy - a figure typed here is a figure that
         | goes stale somewhere nobody is looking.
         */
        $questions = [
            [
                'Do I need an account to order?',
                'No. Everything is bought as a guest: there is nothing to sign up for and no password to '
                    .'remember. You enter your details at checkout and that is the whole of it.',
            ],
            [
                'How do I pay?',
                'By card, on the checkout page, in the same step as placing the order. You will need the name '
                    .'on the card, the number, the expiry date and the security code. There is no cash on '
                    .'delivery and no payment link sent afterwards.',
            ],
            [
                'Where do you deliver?',
                'Within the United States only. If your address is outside it, the checkout will not accept '
                    .'the order rather than taking your money and disappointing you later.',
            ],
            [
                'How long does delivery take, and what does it cost?',
                'You choose the delivery method at checkout, and each one shows its own price and how long it '
                    .'takes before you commit. The cost is added to your total there.',
            ],
            [
                'Can I track my parcel?',
                'No - we do not offer parcel tracking. When you place an order you are taken to a '
                    .'confirmation page with its own private link; keep it, because that is your record of the '
                    .'order. If you need to know where something has got to, ask us and we will find out.',
            ],
            [
                'Can I return something or get a refund?',
                'We do not operate a returns or refunds policy, so please read the product page carefully '
                    .'before ordering - the ingredients, the size and any warnings are all listed there. If '
                    .'something has genuinely gone wrong with your order, contact us and we will look at it.',
            ],
            [
                'How do I use a discount code?',
                'Add what you want to the cart, then type the code into the discount box on the cart page and '
                    .'apply it. The discount shows there straight away and carries through to checkout. A code '
                    .'that has expired, or that your basket does not qualify for, is refused with the reason.',
            ],
            [
                'Where do I find the ingredients and how to use something?',
                'On the product page. Each one carries what is in it, what it is for, how to take it, and any '
                    .'warnings - as far as we have that information from the maker.',
            ],
            [
                'How do I get hold of a person?',
                'Open the chat on any page and ask. If it cannot answer, leave an email address or a phone '
                    .'number in the chat and somebody from the team will come back to you.',
            ],
        ];

        return [
            'name' => 'FAQ',
            'icon' => 'bi-question-circle',
            'description' => 'The accordion at the foot of the home page. Add, reorder or remove '
                .'questions freely - the design draws however many there are.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Common questions'],
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => 'Good to know'],
                'items' => [
                    'label' => 'Questions',
                    'type' => 'repeater',
                    'item_label' => 'Question',
                    'max' => 30,
                    'hint' => 'A row with an empty question is dropped on save.',
                    'fields' => [
                        'question' => ['label' => 'Question', 'type' => 'text'],
                        'answer' => ['label' => 'Answer', 'type' => 'textarea'],
                    ],
                    'default' => array_map(
                        fn (array $row) => ['question' => $row[0], 'answer' => $row[1]],
                        $questions,
                    ),
                ],
            ],
        ];
    }

    /* --------------------------------------------------------------- about */

    /**
     * @return array<string, mixed>
     */
    private static function aboutProof(): array
    {
        return [
            'name' => 'Trust line',
            'icon' => 'bi-people',
            'description' => 'The single line under the hero, beside the stack of initials.',
            'fields' => [
                'text' => [
                    'label' => 'Line',
                    'type' => 'inline',
                    'default' => 'Trusted by <b>150,000+</b> customers in 47 countries',
                    'hint' => 'Wrap the number in <b> to set it in bold, as the design does.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function aboutChapters(): array
    {
        return [
            'name' => 'Story chapters',
            'icon' => 'bi-book',
            'description' => 'The stepper in the middle of the page. It rebuilds itself, '
                .'so add or remove chapters freely - the icons stay with the position.',
            'fields' => [
                'items' => [
                    'label' => 'Chapters',
                    'type' => 'repeater',
                    'item_label' => 'Chapter',
                    'max' => 8,
                    'hint' => 'A row with an empty tab label is dropped on save.',
                    'fields' => [
                        'label' => ['label' => 'Tab label', 'type' => 'text'],
                        'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text'],
                        'title' => ['label' => 'Heading', 'type' => 'text'],
                        'body' => ['label' => 'Text', 'type' => 'textarea'],
                        'chip' => ['label' => 'Chip', 'type' => 'text'],
                        'seal' => ['label' => 'Seal', 'type' => 'text'],
                    ],
                    'default' => [
                        [
                            'label' => 'Our story', 'eyebrow' => '2017 — founded',
                            'title' => 'Three people, one stubborn idea',
                            'body' => 'Placeholder story text. Describe how the company started, who was '
                                .'involved, and what problem they were trying to solve. Four or five '
                                .'sentences is plenty — this is the part people actually read.',
                            'chip' => 'First workshop, year one', 'seal' => 'Est. 2017',
                        ],
                        [
                            'label' => 'Our mission', 'eyebrow' => 'What we are for',
                            'title' => 'Simple products, honestly labelled',
                            'body' => 'Placeholder mission text. Say what you are trying to do and, just as '
                                .'usefully, what you are not trying to do. Specific commitments land better '
                                .'than adjectives.',
                            'chip' => 'Full ingredient disclosure', 'seal' => 'Our promise',
                        ],
                        [
                            'label' => 'How we work', 'eyebrow' => 'Behind the scenes',
                            'title' => 'Tested before anything ships',
                            'body' => 'Placeholder process text. Walk through how a product goes from idea to '
                                .'shelf — formulation, testing, packaging, and who signs it off.',
                            'chip' => 'Independent batch testing', 'seal' => 'Every batch',
                        ],
                        [
                            'label' => 'Our impact', 'eyebrow' => 'Packaging and footprint',
                            'title' => 'Less waste, by default',
                            'body' => 'Placeholder sustainability text. Describe your packaging choices, '
                                .'shipping footprint, and anything you are still working on. Being honest '
                                .'about the gaps builds more trust than claiming there are none.',
                            'chip' => 'Plastic-free since 2022', 'seal' => 'Plastic free',
                        ],
                        [
                            'label' => 'What is next', 'eyebrow' => 'The road ahead',
                            'title' => 'Growing, carefully',
                            'body' => 'Placeholder future text. Share what you are building next and where you '
                                .'are heading, without over-promising dates you cannot keep.',
                            'chip' => '47 countries and counting', 'seal' => '2026',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function aboutQuotes(): array
    {
        return [
            'name' => 'Quotes',
            'icon' => 'bi-quote',
            'description' => 'The rotating quotes. The initials in the circle are taken from the name.',
            'fields' => [
                'items' => [
                    'label' => 'Quotes',
                    'type' => 'repeater',
                    'item_label' => 'Quote',
                    'max' => 8,
                    'hint' => 'A row with an empty quote is dropped on save.',
                    'fields' => [
                        'quote' => ['label' => 'Quote', 'type' => 'textarea'],
                        'name' => ['label' => 'Name', 'type' => 'text'],
                        'role' => ['label' => 'Role', 'type' => 'text'],
                    ],
                    'default' => [
                        [
                            'quote' => 'Placeholder founder quote. Replace this with something a real person '
                                .'on your team said — one or two sentences, specific rather than grand.',
                            'name' => 'Founder name', 'role' => 'Co-founder and formulator',
                        ],
                        [
                            'quote' => 'Placeholder second quote, from someone in a different role. Rotating '
                                .'perspectives reads better than three variations of the same sentiment.',
                            'name' => 'Team member name', 'role' => 'Head of product',
                        ],
                        [
                            'quote' => 'Placeholder third quote. This slot works well for someone on the '
                                .'support or operations side, closer to the day-to-day.',
                            'name' => 'Team member name', 'role' => 'Customer support lead',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function aboutFaq(): array
    {
        return [
            'name' => 'Questions',
            'icon' => 'bi-question-circle',
            'description' => 'The short accordion near the foot of the page.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Quick answers'],
                'title' => [
                    'label' => 'Heading',
                    'type' => 'inline',
                    'default' => 'A few things people <em>ask us</em>',
                    'hint' => 'Wrap a word in <em> to set it in italics.',
                ],
                'items' => [
                    'label' => 'Questions',
                    'type' => 'repeater',
                    'item_label' => 'Question',
                    'max' => 20,
                    'fields' => [
                        'question' => ['label' => 'Question', 'type' => 'text'],
                        'answer' => ['label' => 'Answer', 'type' => 'textarea'],
                    ],
                    'default' => [
                        [
                            'question' => 'How do I know what is in a product?',
                            'answer' => 'Every product page carries its ingredients, what it is for, how to '
                                .'take it and any warnings, as far as we have that from the maker. If '
                                .'something you need is not listed, ask us before you order rather than '
                                .'after.',
                        ],
                        [
                            'question' => 'Who makes what you sell?',
                            'answer' => 'Where the maker and the country of origin are known to us, they are '
                                .'printed on the product page itself. We would rather leave a field blank '
                                .'than fill it in with something we cannot stand behind.',
                        ],
                        [
                            'question' => 'What happens if something is wrong with my order?',
                            'answer' => 'Tell us. We do not run a returns or refunds policy, so there is no '
                                .'form to fill in and no window to miss - you contact us, and we look at what '
                                .'happened.',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function aboutCta(): array
    {
        return [
            'name' => 'Closing band',
            'icon' => 'bi-megaphone',
            'description' => 'The gold band at the foot of the page. The two buttons go to the shop and the reviews.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Ready when you are'],
                'title' => [
                    'label' => 'Heading',
                    'type' => 'inline',
                    'default' => 'See what we <em>actually make</em>',
                ],
                'primary_label' => ['label' => 'First button', 'type' => 'text', 'default' => 'Shop our products'],
                'secondary_label' => ['label' => 'Second button', 'type' => 'text', 'default' => 'Read reviews'],
            ],
        ];
    }

    /* ----------------------------------------------------------------- faq */

    /**
     * @return array<string, mixed>
     */
    private static function faqSearch(): array
    {
        return [
            'name' => 'Search box',
            'icon' => 'bi-search',
            'description' => 'The search field in the hero and the four shortcut buttons under it.',
            'fields' => [
                'placeholder' => [
                    'label' => 'Search placeholder',
                    'type' => 'text',
                    'default' => "Search your question… e.g. 'shipping', 'payment', 'ingredients'",
                ],
                'button' => ['label' => 'Search button', 'type' => 'text', 'default' => 'Search'],
                'quick_one' => ['label' => 'Shortcut 1', 'type' => 'text', 'default' => 'Are products tested?'],
                'quick_two' => ['label' => 'Shortcut 2', 'type' => 'text', 'default' => 'How fast is delivery?'],
                'quick_three' => ['label' => 'Shortcut 3', 'type' => 'text', 'default' => 'Is packaging recyclable?'],
                'quick_four' => ['label' => 'Shortcut 4', 'type' => 'text', 'default' => 'How do I use a discount code?'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function faqStats(): array
    {
        return [
            'name' => 'Stat card',
            'icon' => 'bi-bar-chart',
            'description' => 'The four figures under the hero. Say something you can stand behind - '
                .'a number nobody can check is worth less than no number.',
            'fields' => [
                'one_value' => ['label' => 'First figure', 'type' => 'text', 'default' => '150K+'],
                'one_label' => ['label' => 'First label', 'type' => 'text', 'default' => 'Customers'],
                'two_value' => ['label' => 'Second figure', 'type' => 'text', 'default' => '4.9/5'],
                'two_label' => ['label' => 'Second label', 'type' => 'text', 'default' => 'Support rating'],
                'three_value' => ['label' => 'Third figure', 'type' => 'text', 'default' => '<1h'],
                'three_label' => ['label' => 'Third label', 'type' => 'text', 'default' => 'Median reply'],
                'four_value' => ['label' => 'Fourth figure', 'type' => 'text', 'default' => 'No account'],
                'four_label' => ['label' => 'Fourth label', 'type' => 'text', 'default' => 'Needed to order'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function faqTopics(): array
    {
        return [
            'name' => 'Topics heading',
            'icon' => 'bi-signpost',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Browse by topic'],
                'title' => [
                    'label' => 'Heading',
                    'type' => 'inline',
                    'default' => 'Tap a category to <em>explore</em>',
                ],
                'subtitle' => [
                    'label' => 'Intro',
                    'type' => 'textarea',
                    'default' => 'Everything collapses neatly — open only what you need.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function faqQuestions(): array
    {
        /*
         | Grouped by the topic a customer would go looking under. Two answers
         | here say no rather than dressing it up: there is no parcel tracking
         | and no returns or refunds policy. Both are worth finding before
         | paying rather than after.
         |
         | No prices or delivery times in the prose - those come from
         | config/shop.php and are printed at checkout from it. A figure typed
         | here is one that goes stale where nobody is looking, and the chat
         | assistant indexes this copy.
         */
        $rows = [
            [
                'Products',
                'How do I know what is in a product?',
                'Every product page carries its ingredients, what it is for, how to take it and any warnings '
                    .'- as far as we have that information from the maker. Read it before ordering rather '
                    .'than after.',
            ],
            [
                'Products',
                'Who makes what you sell?',
                'Where the maker and the country of origin are known to us, they are printed on the product '
                    .'page. We leave the field blank rather than fill it with something we cannot stand '
                    .'behind.',
            ],
            [
                'Products',
                'What do the different pack sizes mean?',
                'Each product is sold in one or more sizes, and you pick one on the product page before '
                    .'adding it to the cart. The price changes with the size, and the one marked best value '
                    .'is simply the one that costs least per unit.',
            ],
            [
                'Products',
                'Are the reviews real?',
                'They are written by customers and read by a person before they appear. A review carries a '
                    .'verified mark when we can match the reviewer\'s email address to an order for that '
                    .'product; without a match it still appears, just unmarked.',
            ],
            [
                'Ordering and payment',
                'Do I need an account?',
                'No. Everything is bought as a guest - nothing to sign up for, no password to remember. You '
                    .'enter your details at checkout and that is the whole of it.',
            ],
            [
                'Ordering and payment',
                'How do I pay?',
                'By card, on the checkout page, in the same step as placing the order. You will need the name '
                    .'on the card, the number, the expiry date and the security code. There is no cash on '
                    .'delivery and no payment link sent afterwards.',
            ],
            [
                'Ordering and payment',
                'How do discount codes work?',
                'Add what you want to the cart, type the code into the discount box there and apply it. The '
                    .'discount shows immediately and carries through to checkout. A code that has expired, or '
                    .'that your basket does not qualify for, is refused with the reason.',
            ],
            [
                'Ordering and payment',
                'Can I change or cancel an order after placing it?',
                'There is nothing on the site that will do it, so contact us as soon as you can and we will '
                    .'tell you whether the order has already gone out.',
            ],
            [
                'Delivery',
                'Where do you deliver?',
                'Within the United States only. An address anywhere else is refused at checkout rather than '
                    .'taken and disappointed later.',
            ],
            [
                'Delivery',
                'How long does delivery take, and what does it cost?',
                'You choose the delivery method at checkout, and each one shows its own price and how long it '
                    .'takes before you commit. The cost is added to your total there.',
            ],
            [
                'Delivery',
                'Can I track my parcel?',
                'No - we do not offer parcel tracking, and there is no tracking number or link to follow. If '
                    .'you need to know where an order has got to, ask us and we will find out for you.',
            ],
            [
                'Delivery',
                'What do I get once I have ordered?',
                'A confirmation page with its own private link. Keep it - that link is your record of the '
                    .'order and what you can come back to.',
            ],
            [
                'After your order',
                'Can I return something or get a refund?',
                'We do not operate a returns or refunds policy. That is why the product pages carry the full '
                    .'detail - the size, the ingredients, the warnings - so you can decide before you buy. If '
                    .'something has genuinely gone wrong with an order, contact us and we will look at it.',
            ],
            [
                'After your order',
                'Something arrived damaged or wrong. What do I do?',
                'Tell us what happened and what you received, as soon as you notice. There is no form and no '
                    .'window to miss; we deal with it directly.',
            ],
            [
                'After your order',
                'How do I reach a person?',
                'Open the chat on any page and ask. If it cannot answer, leave an email address or a phone '
                    .'number in the chat and somebody from the team will come back to you.',
            ],
            [
                'After your order',
                'Can I leave a review?',
                'Yes, from the product page. Every review is read before it goes live, so it will not appear '
                    .'the moment you write it.',
            ],
        ];

        return [
            'name' => 'Questions',
            'icon' => 'bi-question-circle',
            'description' => 'Every question on the page. Rows are grouped into accordions by their topic, '
                .'in the order the topics first appear - so reordering rows reorders the sections.',
            'fields' => [
                'items' => [
                    'label' => 'Questions',
                    'type' => 'repeater',
                    'item_label' => 'Question',
                    'max' => 60,
                    'hint' => 'A row with an empty topic is dropped on save.',
                    'fields' => [
                        'topic' => ['label' => 'Topic', 'type' => 'text'],
                        'question' => ['label' => 'Question', 'type' => 'text'],
                        'answer' => ['label' => 'Answer', 'type' => 'textarea'],
                    ],
                    'default' => array_map(
                        fn (array $row) => ['topic' => $row[0], 'question' => $row[1], 'answer' => $row[2]],
                        $rows,
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function faqHelp(): array
    {
        return [
            'name' => 'Still need help',
            'icon' => 'bi-life-preserver',
            'description' => 'The two boxes at the foot of the page.',
            'fields' => [
                'title' => ['label' => 'Heading', 'type' => 'text', 'default' => 'Still need help?'],
                'text' => [
                    'label' => 'Text',
                    'type' => 'textarea',
                    'default' => 'Our support team is available seven days a week — reach out any way you prefer.',
                ],
                'link_one' => ['label' => 'First option', 'type' => 'text', 'default' => 'Email — 2 hour response'],
                'link_two' => ['label' => 'Second option', 'type' => 'text', 'default' => 'Live chat — 9am to 7pm'],
                'link_three' => ['label' => 'Third option', 'type' => 'text', 'default' => 'Phone — toll free'],
                'trust_title' => ['label' => 'Trust heading', 'type' => 'text', 'default' => 'Why people trust us'],
                'trust_one' => ['label' => 'Trust 1', 'type' => 'text', 'default' => 'Secure checkout'],
                'trust_two' => ['label' => 'Trust 2', 'type' => 'text', 'default' => 'Batch tested'],
                'trust_three' => ['label' => 'Trust 3', 'type' => 'text', 'default' => 'Plastic free'],
                'trust_four' => ['label' => 'Trust 4', 'type' => 'text', 'default' => '4.9 rated'],
            ],
        ];
    }

    /* ------------------------------------------------------------- reviews */

    /**
     * @return array<string, mixed>
     */
    private static function reviewsList(): array
    {
        return [
            'name' => 'Reviews tab',
            'icon' => 'bi-chat-quote',
            'description' => 'The wording above the review list and the rating breakdown. '
                .'The reviews, the averages and the bars all come from the Reviews module.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Customer experiences'],
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => 'What our <em>customers</em> say'],
                'subtitle' => [
                    'label' => 'Intro',
                    'type' => 'textarea',
                    'default' => 'Filter by what matters most to you — every review here has been read and published by us.',
                ],
                'load_label' => ['label' => 'Load more button', 'type' => 'text', 'default' => 'Load more reviews'],
                'ratings_eyebrow' => ['label' => 'Ratings eyebrow', 'type' => 'text', 'default' => 'The numbers'],
                'ratings_title' => ['label' => 'Ratings heading', 'type' => 'inline', 'default' => 'Rating <em>breakdown</em>'],
                'ratings_subtitle' => [
                    'label' => 'Ratings intro',
                    'type' => 'textarea',
                    'default' => 'Straight from the published reviews. Nothing is weighted and nothing is left out.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function reviewsForm(): array
    {
        return [
            'name' => 'Write a review',
            'icon' => 'bi-pencil',
            'description' => 'The wording around the submission form. The fields themselves are fixed, '
                .'and everything submitted waits for approval whatever this says.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Your turn'],
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => 'Write a <em>review</em>'],
                'subtitle' => [
                    'label' => 'Intro',
                    'type' => 'textarea',
                    'default' => 'Bought something recently? Tell other people how it went — good or bad. '
                        .'We read every review before it goes up, so give us a day or two.',
                ],
                'button' => ['label' => 'Button', 'type' => 'text', 'default' => 'Submit review'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function reviewsWhy(): array
    {
        return [
            'name' => 'Why choose us',
            'icon' => 'bi-shield-check',
            'description' => 'The six blocks on the third tab. The icons are part of the design and stay with the position.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'What sets us apart'],
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => 'Why people <em>stay</em>'],
                'subtitle' => [
                    'label' => 'Intro',
                    'type' => 'textarea',
                    'default' => 'Replace this placeholder copy with the reasons that are actually true for your business.',
                ],
                'items' => [
                    'label' => 'Blocks',
                    'type' => 'repeater',
                    'item_label' => 'Block',
                    'max' => 12,
                    'fields' => [
                        'title' => ['label' => 'Heading', 'type' => 'text'],
                        'text' => ['label' => 'Text', 'type' => 'textarea'],
                    ],
                    'default' => [
                        ['title' => 'Every review is verified', 'text' => 'We only publish reviews attached to a confirmed order, and we never edit or remove one for being critical.'],
                        ['title' => 'Same-day dispatch', 'text' => 'Order before 2pm on a working day and it leaves the warehouse the same afternoon.'],
                        ['title' => 'Plastic-free packaging', 'text' => 'Cardboard, paper tape and recycled filler. No bubble wrap, no polystyrene, nothing to throw away.'],
                        ['title' => 'Real people on support', 'text' => 'Seven days a week, from 9am to 7pm. Median first reply is under an hour.'],
                        ['title' => 'Batch test reports', 'text' => 'Every batch is tested independently and the report is published on the product page.'],
                        ['title' => '30-day returns', 'text' => 'Unopened items go back free with a prepaid label. Refunds land within a week.'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function reviewsFaq(): array
    {
        return [
            'name' => 'Questions',
            'icon' => 'bi-question-circle',
            'description' => 'The accordion on the last tab.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Common questions'],
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => 'About our <em>reviews</em>'],
                'subtitle' => [
                    'label' => 'Intro',
                    'type' => 'textarea',
                    'default' => 'How reviews are collected, checked and published.',
                ],
                'items' => [
                    'label' => 'Questions',
                    'type' => 'repeater',
                    'item_label' => 'Question',
                    'max' => 20,
                    'fields' => [
                        'question' => ['label' => 'Question', 'type' => 'text'],
                        'answer' => ['label' => 'Answer', 'type' => 'textarea'],
                    ],
                    'default' => [
                        [
                            'question' => 'How do you verify a review?',
                            'answer' => 'When somebody leaves a review we look for an order from the same '
                                .'email address for that same product. If we find one, the review is marked '
                                .'as verified. If we do not, it still appears - just without the mark.',
                        ],
                        [
                            'question' => 'How long until my review appears?',
                            'answer' => 'Not immediately. Every review is read by a person first, so give it '
                                .'a day or two. You will not see your own review on the page until it has '
                                .'been through that either.',
                        ],
                        [
                            'question' => 'Do you remove negative reviews?',
                            'answer' => 'A low rating is not a reason to turn a review down. What does get '
                                .'turned down is spam, abuse, and anything that is not actually about the '
                                .'product.',
                        ],
                        [
                            'question' => 'Can I edit or delete my review?',
                            'answer' => 'Not from the site. Contact us with the product and the address you '
                                .'used and we will take it down or correct it.',
                        ],
                        [
                            'question' => 'Do reviewers get anything in return?',
                            'answer' => 'No. We do not pay for reviews, discount them, or enter reviewers '
                                .'into anything - so nothing on this page was bought.',
                        ],
                        [
                            'question' => 'How is the average rating calculated?',
                            'answer' => 'It is the plain average of every published review for that product, '
                                .'with nothing weighted and nothing dropped.',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function reviewsCta(): array
    {
        return [
            'name' => 'Closing band',
            'icon' => 'bi-megaphone',
            'description' => 'The band at the foot of the page. The heading counts the published reviews on its own.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Ready to try us?'],
                'text' => [
                    'label' => 'Text',
                    'type' => 'textarea',
                    'default' => 'Batch-tested formulas, plastic-free packaging, and a support team that answers seven days a week.',
                ],
                'primary_label' => ['label' => 'First button', 'type' => 'text', 'default' => 'Contact support'],
                'secondary_label' => ['label' => 'Second button', 'type' => 'text', 'default' => 'Shop now'],
                'way_one' => ['label' => 'First way', 'type' => 'text', 'default' => 'Shop'],
                'way_one_text' => ['label' => 'First way - text', 'type' => 'text', 'default' => 'Browse the full range'],
                'way_two' => ['label' => 'Second way', 'type' => 'text', 'default' => 'Support'],
                'way_two_text' => ['label' => 'Second way - text', 'type' => 'text', 'default' => 'Live chat, 9am – 7pm'],
                'way_three' => ['label' => 'Third way', 'type' => 'text', 'default' => 'Email'],
            ],
        ];
    }

    /* ----------------------------------------------------- shipping policy */

    /**
     * @return array<string, mixed>
     */
    private static function shippingPills(): array
    {
        return [
            'name' => 'Hero pills',
            'icon' => 'bi-tags',
            'description' => 'The four pills under the heading, and the two buttons beside them.',
            'fields' => [
                'one' => ['label' => 'First pill', 'type' => 'text', 'default' => 'Plastic-free packaging'],
                'two' => ['label' => 'Second pill', 'type' => 'text', 'default' => 'Same-day dispatch'],
                'three' => ['label' => 'Third pill', 'type' => 'text', 'default' => 'Guest checkout'],
                'four' => ['label' => 'Fourth pill', 'type' => 'text', 'default' => 'US delivery'],
                'primary_label' => ['label' => 'First button', 'type' => 'text', 'default' => 'Shop now'],
                'secondary_label' => ['label' => 'Second button', 'type' => 'text', 'default' => 'All products'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function shippingOverview(): array
    {
        return [
            'name' => 'Overview tab',
            'icon' => 'bi-clock',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Quick facts'],
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => 'Shipping <em>overview</em>'],
                'subtitle' => [
                    'label' => 'Intro',
                    'type' => 'textarea',
                    'default' => 'A fast reference for how long things take and what is included with every order.',
                ],
                'items' => [
                    'label' => 'Facts',
                    'type' => 'repeater',
                    'item_label' => 'Fact',
                    'max' => 10,
                    'fields' => [
                        'title' => ['label' => 'Heading', 'type' => 'text'],
                        'text' => ['label' => 'Text', 'type' => 'textarea'],
                        'tag' => ['label' => 'Tag', 'type' => 'text'],
                    ],
                    'default' => [
                        ['title' => 'Order processing', 'text' => 'Orders placed before 2pm are picked, packed and handed to the carrier the same working day.', 'tag' => 'Same day'],
                        ['title' => 'Standard shipping', 'text' => 'Nationwide delivery in 3–5 working days. Free automatically on orders over $50.', 'tag' => '3–5 days'],
                        ['title' => 'Express shipping', 'text' => 'Priority handling and next-available routing gets your order there in 1–2 working days.', 'tag' => '1–2 days'],
                        ['title' => 'Insurance included', 'text' => 'Every shipment is covered against loss or damage in transit, at no extra cost to you.', 'tag' => 'Always free'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function shippingJourney(): array
    {
        return [
            'name' => 'Journey tab',
            'icon' => 'bi-truck',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Step by step'],
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => 'Your order <em>journey</em>'],
                'subtitle' => [
                    'label' => 'Intro',
                    'type' => 'textarea',
                    'default' => 'What happens between checkout and your doorstep, and when to expect each update.',
                ],
                'items' => [
                    'label' => 'Steps',
                    'type' => 'repeater',
                    'item_label' => 'Step',
                    'max' => 10,
                    'hint' => 'The step numbers are drawn from the order of the rows.',
                    'fields' => [
                        'title' => ['label' => 'Heading', 'type' => 'text'],
                        'text' => ['label' => 'Text', 'type' => 'textarea'],
                        'when' => ['label' => 'When', 'type' => 'text'],
                    ],
                    'default' => [
                        ['title' => 'Order confirmed', 'text' => 'You get an email receipt with your order number the moment payment clears.', 'when' => 'Within minutes'],
                        ['title' => 'Picked and packed', 'text' => 'Your items are pulled from stock, checked against the order, and packed in recyclable materials.', 'when' => 'Same working day'],
                        ['title' => 'Handed to the carrier', 'text' => 'Your order leaves us and goes to the carrier you chose at checkout. We do not offer tracking, so there is no number to watch - ask us if you need to know where it is.', 'when' => 'Day 1'],
                        ['title' => 'On its way', 'text' => 'The carrier takes it from there, within the times shown against the method you picked at checkout.', 'when' => 'In transit'],
                        ['title' => 'Delivered', 'text' => 'Left with you or a neighbour, or held at a nearby pickup point if nobody is in.', 'when' => 'Day 3–5 standard'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function shippingSpeeds(): array
    {
        return [
            'name' => 'Speeds tab',
            'icon' => 'bi-lightning',
            'description' => 'The rates table. These are copy only - what a customer is actually '
                .'charged comes from the shipping methods in config/shop.php.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Options and pricing'],
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => 'Delivery <em>speeds</em>'],
                'subtitle' => [
                    'label' => 'Intro',
                    'type' => 'textarea',
                    'default' => 'Choose a speed at checkout. Cut-off for same-day dispatch is 2pm on working days.',
                ],
                'items' => [
                    'label' => 'Rows',
                    'type' => 'repeater',
                    'item_label' => 'Row',
                    'max' => 12,
                    'fields' => [
                        'method' => ['label' => 'Method', 'type' => 'text'],
                        'method_note' => ['label' => 'Method note', 'type' => 'text'],
                        'arrives' => ['label' => 'Arrives in', 'type' => 'text'],
                        'tracking' => ['label' => 'Tracking', 'type' => 'text'],
                        'cost' => ['label' => 'Cost', 'type' => 'text'],
                        'cost_note' => ['label' => 'Cost note', 'type' => 'text'],
                    ],
                    'default' => [
                        ['method' => 'Standard', 'method_note' => 'Our default option', 'arrives' => '3–5 working days', 'tracking' => 'Full tracking', 'cost' => '$4.95', 'cost_note' => 'Free over $50'],
                        ['method' => 'Express', 'method_note' => 'Priority routing', 'arrives' => '1–2 working days', 'tracking' => 'Full tracking', 'cost' => '$9.95', 'cost_note' => 'Free over $120'],
                        ['method' => 'Next day', 'method_note' => 'Order before 2pm', 'arrives' => 'Next working day', 'tracking' => 'Live tracking', 'cost' => '$14.95', 'cost_note' => 'Selected postcodes'],
                        ['method' => 'Pickup point', 'method_note' => 'Collect when it suits', 'arrives' => '2–4 working days', 'tracking' => 'Full tracking', 'cost' => '$2.95', 'cost_note' => 'Held for 7 days'],
                        ['method' => 'International', 'method_note' => 'Selected countries', 'arrives' => '7–14 working days', 'tracking' => 'Full tracking', 'cost' => 'From $18', 'cost_note' => 'Duties not included'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function shippingPolicyDetails(): array
    {
        return [
            'name' => 'Policy tab',
            'icon' => 'bi-file-text',
            'description' => 'The legal copy. Two blocks state what this shop does not offer - tracking, '
                .'and returns or refunds. Do not soften them: a customer who finds that out after paying is '
                .'the complaint they exist to prevent.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'The fine print'],
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => 'Policy <em>details</em>'],
                'subtitle' => [
                    'label' => 'Intro',
                    'type' => 'textarea',
                    'default' => 'What we do and do not offer, in plain words. Please read the returns section before ordering.',
                ],
                'items' => [
                    'label' => 'Blocks',
                    'type' => 'repeater',
                    'item_label' => 'Block',
                    'max' => 15,
                    'fields' => [
                        'title' => ['label' => 'Heading', 'type' => 'text'],
                        'text' => ['label' => 'Text', 'type' => 'textarea'],
                    ],
                    'default' => [
                        ['title' => 'Where we deliver', 'text' => 'Within the United States only. An address anywhere else is refused at checkout rather than accepted and disappointed afterwards.'],
                        ['title' => 'What delivery costs', 'text' => 'You choose the method at checkout and each one shows its own price and how long it takes before you commit. The cost is added to your total there.'],
                        ['title' => 'Tracking', 'text' => 'We do not offer parcel tracking. There is no tracking number and no link to follow. If you need to know where an order has got to, contact us and we will find out.'],
                        ['title' => 'Your order record', 'text' => 'Placing an order takes you to a confirmation page with its own private link. Keep it - that link is your record of the order, and it is what to send us if you need to ask about it.'],
                        ['title' => 'Address changes', 'text' => 'Nothing on the site will change a delivery address once an order is placed. Contact us as quickly as you can and we will tell you whether it has already gone out.'],
                        ['title' => 'Returns and refunds', 'text' => 'We do not operate a returns or refunds policy. The product pages carry the size, the ingredients and any warnings so the decision can be made before buying. If something has genuinely gone wrong with an order, contact us and we will look at it.'],
                        ['title' => 'Delays', 'text' => 'Weather and peak periods can push transit times out. If an order is well past the time shown for the method you chose, tell us and we will chase it.'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function shippingFaq(): array
    {
        /*
         | Two of these say no. There is no parcel tracking and no returns or
         | refunds policy, and a customer is far better off reading that here
         | than discovering it once they have paid.
         |
         | No prices or transit times in the prose: those come from
         | config/shop.php, are printed at checkout from it, and would go stale
         | here without anybody noticing.
         */
        $questions = [
            [
                'Where do you deliver?',
                'Within the United States only. An address anywhere else is refused at checkout rather than '
                    .'taken and disappointed later.',
            ],
            [
                'How long will my order take, and what does delivery cost?',
                'You pick the delivery method at checkout. Each one shows its own price and how long it '
                    .'takes before you commit, and the cost is added to your total there.',
            ],
            [
                'Can I track my parcel?',
                'No. We do not offer parcel tracking, and there is no tracking number or link to follow. If '
                    .'you want to know where an order has got to, ask us and we will find out for you.',
            ],
            [
                'What do I get once I have ordered?',
                'A confirmation page with its own private link. Keep it - that link is your record of the '
                    .'order, and it is what to come back to or send us if you need to ask about it.',
            ],
            [
                'Can I change my delivery address after ordering?',
                'There is nothing on the site that will do it. Contact us as quickly as you can and we will '
                    .'tell you whether the order has already gone out.',
            ],
            [
                'My parcel arrived damaged. What now?',
                'Tell us what arrived and what is wrong with it, as soon as you notice. There is no form to '
                    .'fill in and no window to miss - we deal with it directly.',
            ],
            [
                'Can I return something or get a refund?',
                'We do not operate a returns or refunds policy. The product pages carry the size, the '
                    .'ingredients and any warnings so that the decision can be made before buying. If '
                    .'something has genuinely gone wrong, contact us.',
            ],
        ];

        return [
            'name' => 'FAQ tab',
            'icon' => 'bi-question-circle',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Common questions'],
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => 'Shipping <em>FAQs</em>'],
                'subtitle' => [
                    'label' => 'Intro',
                    'type' => 'textarea',
                    'default' => 'Still stuck after reading these? The support team answers seven days a week.',
                ],
                'items' => [
                    'label' => 'Questions',
                    'type' => 'repeater',
                    'item_label' => 'Question',
                    'max' => 25,
                    'fields' => [
                        'question' => ['label' => 'Question', 'type' => 'text'],
                        'answer' => ['label' => 'Answer', 'type' => 'textarea'],
                    ],
                    'default' => array_map(
                        fn (array $row) => ['question' => $row[0], 'answer' => $row[1]],
                        $questions,
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function shippingHelp(): array
    {
        return [
            'name' => 'Help band',
            'icon' => 'bi-life-preserver',
            'description' => 'The band at the foot of the page. The email, the hours and the phone '
                .'number come from Settings, not from here.',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Need help?'],
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => 'Get in <em>touch</em>'],
                'primary_label' => ['label' => 'First button', 'type' => 'text', 'default' => 'Contact support'],
                'secondary_label' => ['label' => 'Second button', 'type' => 'text', 'default' => 'Shop now'],
            ],
        ];
    }

    /* ------------------------------------------------------- shop and cart */

    /**
     * @return array<string, mixed>
     */
    private static function shopNewsletter(): array
    {
        return [
            'name' => 'Newsletter band',
            'icon' => 'bi-envelope',
            'description' => 'The band under the product grid. Everything above it - the offer '
                .'strip, the sort controls and the products - is drawn from the catalogue.',
            'fields' => [
                'title' => [
                    'label' => 'Heading',
                    'type' => 'inline',
                    'default' => 'Get new arrivals <em>first</em>',
                    'hint' => 'Wrap a word in <em> to set it in italics, as the design does.',
                ],
                'subtitle' => [
                    'label' => 'Text',
                    'type' => 'textarea',
                    'default' => 'Restock alerts, seasonal offers, and 10% off your first order.',
                ],
                'placeholder' => ['label' => 'Field placeholder', 'type' => 'text', 'default' => 'Your email address'],
                'button' => ['label' => 'Button', 'type' => 'text', 'default' => 'Subscribe'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cartCopy(): array
    {
        return [
            'name' => 'Cart copy',
            'icon' => 'bi-cart',
            'description' => 'The wording around the basket. The lines, the offers and the totals '
                .'are all worked out from what is actually in the cart.',
            'fields' => [
                'title' => ['label' => 'Heading', 'type' => 'inline', 'default' => 'Your <em>cart</em>'],
                'clear_label' => ['label' => 'Clear button', 'type' => 'text', 'default' => 'Clear cart'],
                'back_label' => ['label' => 'Back link', 'type' => 'text', 'default' => '← Continue shopping'],
                'summary_title' => ['label' => 'Summary heading', 'type' => 'text', 'default' => 'Order summary'],
                'summary_sub' => [
                    'label' => 'Summary intro',
                    'type' => 'text',
                    'default' => 'Review your order before checkout',
                ],
                'offers_label' => ['label' => 'Offers heading', 'type' => 'text', 'default' => 'Available offers'],
                'checkout_label' => ['label' => 'Checkout button', 'type' => 'text', 'default' => 'Proceed to checkout'],
                'badge_one' => ['label' => 'Badge 1', 'type' => 'text', 'default' => 'Secure checkout'],
                'badge_two' => ['label' => 'Badge 2', 'type' => 'text', 'default' => 'Plastic-free packing'],
                'badge_three' => ['label' => 'Badge 3', 'type' => 'text', 'default' => 'Batch tested'],
                'badge_four' => ['label' => 'Badge 4', 'type' => 'text', 'default' => 'Same-day dispatch'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function checkoutCopy(): array
    {
        return [
            'name' => 'Checkout copy',
            'icon' => 'bi-credit-card',
            'description' => 'The headings on the checkout. The fields themselves are fixed - they '
                .'have to match what an order needs, and the server validates every one of them.',
            'fields' => [
                'billing_title' => ['label' => 'Billing heading', 'type' => 'text', 'default' => 'Billing details'],
                'payment_title' => ['label' => 'Payment heading', 'type' => 'text', 'default' => 'Payment method'],
                'summary_title' => ['label' => 'Summary heading', 'type' => 'text', 'default' => 'Your order'],
            ],
        ];
    }

    /* -------------------------------------------------------------- lookups */

    /**
     * @return array<string, mixed>|null
     */
    public static function page(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * The view names that need a $content bag composed into them.
     *
     * @return array<int, string>
     */
    public static function views(): array
    {
        return array_values(array_column(self::all(), 'view'));
    }

    /**
     * The page key a view name belongs to, or null for a view with no editable
     * copy.
     */
    public static function keyForView(string $view): ?string
    {
        foreach (self::all() as $key => $page) {
            if ($page['view'] === $view) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Every field on a page, flattened to "section.field" => definition. Used
     * by the request class to validate exactly what the form may send.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function fields(string $pageKey): array
    {
        $page = self::page($pageKey);

        if ($page === null) {
            return [];
        }

        $fields = [];

        foreach ($page['sections'] as $sectionKey => $section) {
            foreach ($section['fields'] as $fieldKey => $field) {
                $fields[$sectionKey.'.'.$fieldKey] = $field + ['type' => 'text'];
            }
        }

        return $fields;
    }

    /**
     * The name an HTML input uses for a path.
     *
     * A dot in a form field name is Laravel's array separator, so
     * "fields[hero.title]" would be read as fields -> hero -> title and its
     * validation rule would never match. The form posts "hero__title" and the
     * request maps it back, which keeps the schema readable and the rules
     * working.
     */
    public static function inputName(string $path): string
    {
        return str_replace('.', '__', $path);
    }

    public static function pathFromInput(string $name): string
    {
        return str_replace('__', '.', $name);
    }

    /**
     * The defaults for one page, as "section.field" => value.
     *
     * @return array<string, string|null>
     */
    public static function defaults(string $pageKey): array
    {
        return array_map(
            fn (array $field) => $field['default'] ?? null,
            self::fields($pageKey),
        );
    }
}
