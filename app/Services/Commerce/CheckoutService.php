<?php

namespace App\Services\Commerce;

use App\Exceptions\Commerce\CheckoutFailedException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPack;
use App\Support\PaymentCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a basket into an order.
 *
 * The rule this class exists to enforce: the browser sends what the customer
 * chose - product, size, quantity - and nothing else. Every price, every
 * discount and every total is read back out of the catalogue here. A basket
 * that arrives claiming a $2 bottle of collagen is priced at $96 like any
 * other, because the number in the payload is never looked at.
 */
class CheckoutService
{
    public function __construct(
        private readonly CouponService $coupons,
        private readonly AttributionService $attribution,
    ) {}

    /* ------------------------------------------------------------- shipping */

    /**
     * @return array<int, array<string, mixed>>
     */
    public function shippingMethods(): array
    {
        return config('shop.shipping', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function shippingMethod(?string $id): ?array
    {
        foreach ($this->shippingMethods() as $method) {
            if ($method['id'] === $id) {
                return $method;
            }
        }

        return null;
    }

    /**
     * Whether this method is free on this order. The promotion is measured
     * against the discounted subtotal, so a coupon cannot accidentally buy
     * free delivery the operator did not intend.
     */
    public function shippingIsFree(string $methodId, float $netSubtotal): bool
    {
        $threshold = config('shop.free_shipping_over');

        return $threshold !== null
            && $netSubtotal >= (float) $threshold
            && $methodId === config('shop.free_shipping_method');
    }

    /* ---------------------------------------------------------------- quote */

    /**
     * Prices a basket without writing anything.
     *
     * @param  array<int, array<string, mixed>>  $lines  [{product_id, pack_label?, pack_id?, quantity}]
     * @return array{
     *     lines: array<int, array<string, mixed>>,
     *     subtotal: float, discount: float, shipping: float, total: float,
     *     coupon: Coupon|null, shipping_method: array<string, mixed>, shipping_is_free: bool, units: int
     * }
     *
     * @throws CheckoutFailedException
     */
    public function quote(array $lines, ?string $couponCode, ?string $shippingMethodId, bool $lock = false): array
    {
        $priced = $this->priceLines($lines, $lock);

        if ($priced === []) {
            throw new CheckoutFailedException('Your cart is empty.', ['items' => ['Your cart is empty.']]);
        }

        $subtotal = round(array_sum(array_column($priced, 'line_total')), 2);

        // Quietly: a code that has stopped qualifying while the customer was
        // typing their address drops off the order rather than blocking it.
        // The panel and the receipt then show what was actually charged.
        $coupon = $couponCode !== null && $couponCode !== ''
            ? $this->coupons->resolveQuietly($couponCode, $subtotal)
            : null;

        $discount = $coupon?->discountFor($subtotal) ?? 0.0;

        $method = $this->shippingMethod($shippingMethodId) ?? $this->shippingMethods()[0];
        $free = $this->shippingIsFree($method['id'], $subtotal - $discount);
        $shipping = $free ? 0.0 : (float) $method['cost'];

        return [
            'lines' => $priced,
            'units' => (int) array_sum(array_column($priced, 'quantity')),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => round($shipping, 2),
            'total' => round(max(0, $subtotal - $discount + $shipping), 2),
            'coupon' => $coupon,
            'shipping_method' => $method,
            'shipping_is_free' => $free,
        ];
    }

    /* ---------------------------------------------------------------- place */

    /**
     * Writes the order, its lines and its payment, redeems the coupon and
     * takes the stock down - all inside one transaction, so a failure part way
     * through leaves no half-order and no stock quietly missing.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws CheckoutFailedException
     */
    public function place(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            // Locked this time: between the quote the customer saw and this
            // moment, another order may have taken the last unit.
            $quote = $this->quote(
                $data['items'],
                $data['coupon_code'] ?? null,
                $data['shipping_method'] ?? null,
                lock: true,
            );

            $order = new Order([
                // Placed, not paid. There is no payment processor wired up, so
                // claiming otherwise would make the panel's payment column
                // meaningless - an operator marks the money in once it is in.
                'status' => 'pending',
                'payment_status' => 'pending',
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'street' => $data['street'],
                'city' => $data['city'],
                'state' => $data['state'],
                'postal_code' => $data['postal_code'],
                'country' => $data['country'],
                'notes' => $data['notes'] ?? null,
            ]);

            $order->forceFill([
                'order_number' => $this->nextOrderNumber(),
                'public_token' => Str::random(48),
                'shipping_method' => $quote['shipping_method']['id'],
                'shipping_method_label' => trim($quote['shipping_method']['name'], ': '),
                'coupon_id' => $quote['coupon']?->id,
                'coupon_code' => $quote['coupon']?->code,
                'coupon_description' => $quote['coupon']?->descriptionLabel(),
                'subtotal' => $quote['subtotal'],
                'discount_total' => $quote['discount'],
                'shipping_total' => $quote['shipping'],
                'grand_total' => $quote['total'],
                'currency' => config('shop.currency', 'USD'),
                'ip_address' => $data['ip'] ?? null,
                'placed_at' => now(),
            ])->save();

            foreach ($quote['lines'] as $line) {
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'pack_id' => $line['pack_id'],
                    'name' => $line['name'],
                    'pack_label' => $line['pack_label'],
                    'sku' => $line['sku'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ]);
            }

            $this->recordPayment($order, $data);

            // Where this customer came from, copied off their cookie and frozen
            // onto the order. Inside the transaction so an order can never
            // exist without the record of how it was won.
            $this->attribution->attachTo($order);

            $this->drawDownStock($quote['lines']);

            if ($quote['coupon'] !== null) {
                $this->coupons->redeem($quote['coupon']);
            }

            return $order->load(['items', 'payment', 'attribution']);
        });
    }

    /* -------------------------------------------------------------- pricing */

    /**
     * Reads each basket line back out of the catalogue.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     *
     * @throws CheckoutFailedException
     */
    private function priceLines(array $lines, bool $lock): array
    {
        $priced = [];
        $seen = [];

        foreach ($lines as $index => $line) {
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($quantity < 1) {
                continue;
            }

            $quantity = min($quantity, (int) config('shop.max_line_quantity', 99));

            $product = $this->findProduct((int) ($line['product_id'] ?? 0), $lock);

            if ($product === null) {
                throw new CheckoutFailedException(
                    'One of the items in your cart is no longer available. Remove it and try again.',
                    ["items.{$index}.product_id" => ['This product is no longer available.']],
                );
            }

            $pack = $this->findPack($product, $line, $lock);

            // A product that sells in sizes has no price of its own to fall
            // back on - an order for "the product" rather than a size would be
            // ambiguous about both price and stock.
            if ($pack === null && $product->packs()->exists()) {
                throw new CheckoutFailedException(
                    'Choose a size for '.$product->name.' before checking out.',
                    ["items.{$index}.pack_label" => ['Choose a size for this product.']],
                );
            }

            // The same product and size twice in one payload is merged rather
            // than rejected - two tabs adding to the cart is a normal thing to
            // do, and a unique key per line keeps the stock check honest.
            $key = $product->id.':'.($pack?->id ?? 0);

            if (isset($seen[$key])) {
                $at = $seen[$key];
                $merged = min(
                    $priced[$at]['quantity'] + $quantity,
                    (int) config('shop.max_line_quantity', 99),
                );

                // Re-checked against the combined quantity: two lines of five
                // are the same demand on stock as one line of ten.
                $this->guardStock($product, $pack, $merged, $index);

                $priced[$at]['quantity'] = $merged;
                $priced[$at]['line_total'] = round($priced[$at]['unit_price'] * $merged, 2);

                continue;
            }

            $unitPrice = round((float) ($pack?->price ?? $product->price), 2);

            $this->guardStock($product, $pack, $quantity, $index);

            $seen[$key] = count($priced);

            $priced[] = [
                'product' => $product,
                'pack' => $pack,
                'product_id' => $product->id,
                'pack_id' => $pack?->id,
                'name' => $product->name,
                'pack_label' => $pack?->label,
                'sku' => $product->sku,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => round($unitPrice * $quantity, 2),
            ];
        }

        return $priced;
    }

    private function findProduct(int $id, bool $lock): ?Product
    {
        if ($id < 1) {
            return null;
        }

        return Product::query()
            ->published()
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->find($id);
    }

    /**
     * The size that was chosen. Matched on the label the page rendered rather
     * than on an id, because that is what the cart carries across a session -
     * and matched case-insensitively so "60 Count" still resolves.
     *
     * @param  array<string, mixed>  $line
     */
    private function findPack(Product $product, array $line, bool $lock): ?ProductPack
    {
        $query = $product->packs()->when($lock, fn ($q) => $q->lockForUpdate());

        if (! empty($line['pack_id'])) {
            return (clone $query)->find((int) $line['pack_id']);
        }

        $label = trim((string) ($line['pack_label'] ?? ''));

        if ($label === '') {
            return null;
        }

        return $query->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])->first();
    }

    /**
     * Stock is checked against whichever row actually holds it: the size when
     * the product sells in sizes, the product itself when it does not. That is
     * the same figure the product page shows, so a customer is never told
     * something is available and then refused at checkout.
     *
     * @throws CheckoutFailedException
     */
    private function guardStock(Product $product, ?ProductPack $pack, int $quantity, int $index): void
    {
        if (! $product->track_inventory || $product->allow_backorder) {
            return;
        }

        $available = $pack !== null ? (int) $pack->stock_quantity : (int) $product->stock_quantity;

        if ($available >= $quantity) {
            return;
        }

        $what = $pack !== null ? $product->name.' — '.$pack->label : $product->name;

        throw new CheckoutFailedException(
            $available > 0
                ? 'Only '.$available.' left of '.$what.'. Lower the quantity and try again.'
                : $what.' has just sold out. Remove it to continue.',
            ["items.{$index}.quantity" => ['Only '.$available.' available.']],
        );
    }

    /**
     * Takes the sold units off the row the stock was checked against. Products
     * that do not track inventory, and backordered ones, are left alone -
     * their count is not meant to mean anything.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function drawDownStock(array $lines): void
    {
        foreach ($lines as $line) {
            /** @var Product $product */
            $product = $line['product'];

            if (! $product->track_inventory) {
                continue;
            }

            /** @var ProductPack|null $pack */
            $pack = $line['pack'];
            $quantity = (int) $line['quantity'];

            if ($pack !== null) {
                // max(0, ...) rather than a raw decrement: the columns are
                // unsigned, and a backordered line is allowed to go past zero
                // conceptually but must not underflow the database.
                $pack->stock_quantity = max(0, (int) $pack->stock_quantity - $quantity);
                $pack->save();

                continue;
            }

            $product->stock_quantity = max(0, (int) $product->stock_quantity - $quantity);
            $product->save();
        }
    }

    /* -------------------------------------------------------------- payment */

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordPayment(Order $order, array $data): void
    {
        $number = $data['card_number'] ?? null;
        $expiry = PaymentCard::parseExpiry($data['card_expiry'] ?? null);

        // The security code arrives here only while shop.store_card_cvc is on;
        // PlaceOrderRequest nulls it otherwise. Both it and the number are
        // encrypted by the model's casts on the way into the table.
        $order->payment()->create([
            'method' => 'card',
            'card_holder' => $data['card_holder'] ?? null,
            'card_brand' => PaymentCard::brand($number),
            'card_last4' => PaymentCard::last4($number),
            'card_number' => PaymentCard::digits($number),
            'card_cvc' => $data['card_cvc'] ?? null,
            'exp_month' => $expiry['month'] ?? null,
            'exp_year' => $expiry['year'] ?? null,
            'status' => 'pending',
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            // Stamped by OrderService when the payment is actually marked in.
            'paid_at' => null,
        ]);
    }

    /* --------------------------------------------------------------- number */

    /**
     * A short, human-readable reference. Random rather than sequential so the
     * number does not tell a competitor how many orders the shop takes, and
     * re-rolled on the vanishingly rare collision.
     */
    private function nextOrderNumber(): string
    {
        do {
            $number = 'AW-'.mb_strtoupper(Str::random(8));
        } while (Order::withTrashed()->where('order_number', $number)->exists());

        return $number;
    }
}
