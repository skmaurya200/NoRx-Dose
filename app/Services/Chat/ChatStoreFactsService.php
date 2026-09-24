<?php

namespace App\Services\Chat;

use App\Models\Order;
use App\Services\Settings\SettingService;
use App\Support\Settings\Site;

/**
 * How this store works, as data the assistant reasons from - never as
 * sentences it repeats.
 *
 * Every value is read from the configuration and settings the site itself runs
 * on: the shipping methods and their costs are the ones the checkout charges,
 * the currency is the one prices are stored in, the contact details are the
 * ones an administrator saved in Settings. Nothing here is a written answer to
 * a question. The model is handed the facts and writes its own reply, in the
 * visitor's own language, every time.
 *
 * What this application does not do is stated as data too (no tracking
 * numbers, no cash on delivery), because an assistant that is not told a thing
 * is missing will otherwise describe how shops usually work.
 */
class ChatStoreFactsService
{
    /**
     * Placeholder addresses the site ships with, never real contact details.
     */
    private const PLACEHOLDER_EMAIL = '/@(?:[a-z0-9-]+\.)*example\.(?:com|net|org)$/i';

    public function __construct(private readonly SettingService $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $currency = (string) config('shop.currency', 'USD');

        return [
            'store' => [
                'name' => Site::name(),
                'tagline' => Site::tagline(),
                'website' => url('/'),
                'currency' => $currency,
                'currency_symbol' => (string) config('shop.currency_symbol', '$'),
                'pack_sizes_counted_in' => (string) config('shop.pack_unit_label', ''),
            ],
            'assistant' => [
                'role' => 'virtual assistant for '.Site::name(),
                'is_a_person' => false,
                'can_do' => [
                    'search the catalogue and show products with live prices and availability',
                    'give details of a product and compare products the visitor has been shown',
                    'answer questions from the store pages, FAQ and journal',
                    'explain ordering, payment, delivery, discount codes and reviews',
                    'share the store\'s public contact details',
                    'take the visitor\'s email or phone number so the team can get back to them',
                ],
                'cannot_do' => [
                    'look up, change or track an existing order',
                    'take payments or place orders',
                    'give medical, legal or financial advice',
                ],
            ],
            'ordering' => [
                'guest_checkout' => true,
                'account_needed' => false,
                'how' => 'add products to the cart from a product page, then check out',
                'pack_size_must_be_chosen_when_a_product_has_sizes' => true,
                'shop_page' => route('shop'),
                'cart_page' => route('cart'),
                'checkout_page' => route('checkout'),
                'checkout_asks_for' => ['name', 'email', 'phone', 'delivery address', 'delivery method', 'card details'],
            ],
            'payment' => [
                'methods' => ['card'],
                'cash_on_delivery' => false,
                'upi_or_net_banking' => false,
                'paid_when' => 'at checkout, when the order is placed',
                'currency' => $currency,
            ],
            'delivery' => [
                'ships_to' => [(string) config('shop.country_name', '')],
                'methods' => $this->shippingMethods(),
                'free_delivery_over' => $this->money(config('shop.free_shipping_over')),
                'chosen_at' => 'checkout',
                'policy_page' => route('shipping-policy'),
            ],
            'orders_after_placing' => [
                'confirmation_page_with_private_link' => true,
                'tracking_number' => false,
                'order_statuses' => Order::STATUSES,
            ],
            'discount_codes' => [
                'entered_on' => 'the cart page, before checkout',
                'expired_or_ineligible_codes_are_refused_with_the_reason' => true,
            ],
            'reviews' => [
                'written_on' => 'the product page',
                'checked_by_the_team_before_they_appear' => true,
                'reviews_page' => route('reviews'),
            ],
            'pages' => [
                'about' => route('about'),
                'faq' => route('faq'),
                'shipping_policy' => route('shipping-policy'),
                'journal' => route('blogs'),
                'reviews' => route('reviews'),
            ],
            'public_contact' => $this->publicContact(),
        ];
    }

    /**
     * The contact details an administrator has actually saved in Settings ->
     * Contact, and nothing else. The schema default (hello@example.com) is a
     * placeholder, and no account, admin login email or environment value is
     * ever a source.
     *
     * @return array{email?: string, phone?: string, whatsapp?: string, contact_page?: string}
     */
    public function publicContact(): array
    {
        $saved = $this->settings->values();
        $contact = [];

        $email = trim((string) ($saved['contact.email'] ?? ''));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false && preg_match(self::PLACEHOLDER_EMAIL, $email) !== 1) {
            $contact['email'] = $email;
        }

        foreach (['phone' => 'contact.phone', 'whatsapp' => 'contact.whatsapp'] as $key => $path) {
            $number = trim((string) ($saved[$path] ?? ''));

            if (strlen((string) preg_replace('/\D/', '', $number)) >= 7) {
                $contact[$key] = $number;
            }
        }

        $url = trim((string) ($saved['contact.page_url'] ?? ''));

        if (filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1) {
            $contact['contact_page'] = $url;
        }

        return $contact;
    }

    /**
     * @return list<array{name: string, cost: string, delivery_time: string}>
     */
    private function shippingMethods(): array
    {
        $methods = [];

        foreach ((array) config('shop.shipping', []) as $method) {
            if (! is_array($method) || ! isset($method['name'])) {
                continue;
            }

            $methods[] = [
                'name' => trim((string) $method['name'], ': '),
                'cost' => (string) $this->money($method['cost'] ?? 0),
                'delivery_time' => trim((string) ($method['note'] ?? '')),
            ];
        }

        return $methods;
    }

    private function money(mixed $amount): ?string
    {
        return is_numeric($amount)
            ? config('shop.currency_symbol', '$').number_format((float) $amount, 2)
            : null;
    }
}
