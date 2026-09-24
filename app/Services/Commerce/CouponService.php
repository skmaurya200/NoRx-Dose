<?php

namespace App\Services\Commerce;

use App\Exceptions\Commerce\CouponNotApplicableException;
use App\Models\Coupon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every read and write of a discount code goes through here.
 *
 * The important one is resolve(): it is the only place that decides whether a
 * code may be used, and both the "apply" button on the cart and the order
 * being placed call it - so a customer cannot get a discount by applying a
 * code, waiting for it to expire, and then submitting.
 */
class CouponService
{
    /* ----------------------------------------------------------- panel side */

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Coupon::query()
            ->withCount('orders')
            ->search($filters['search'] ?? null)
            ->when(($filters['status'] ?? '') !== '', function (Builder $query) use ($filters) {
                match ($filters['status']) {
                    'active' => $query->redeemable(),
                    'disabled' => $query->where('is_active', false),
                    'expired' => $query->whereNotNull('ends_at')->where('ends_at', '<', now()),
                    'scheduled' => $query->whereNotNull('starts_at')->where('starts_at', '>', now()),
                    default => null,
                };
            })
            ->when(($filters['type'] ?? '') !== '', fn (Builder $q) => $q->where('type', $filters['type']))
            ->ordered()
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Coupon
    {
        return Coupon::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Coupon $coupon, array $data): Coupon
    {
        $coupon->fill($data)->save();

        return $coupon->refresh();
    }

    /**
     * Soft delete. Orders that used the code keep their snapshot of it, and
     * their coupon_id is nulled by the foreign key only on a hard delete.
     */
    public function delete(Coupon $coupon): void
    {
        $coupon->delete();
    }

    public function toggleActive(Coupon $coupon): Coupon
    {
        $coupon->is_active = ! $coupon->is_active;
        $coupon->save();

        return $coupon;
    }

    /* ------------------------------------------------------ storefront side */

    /**
     * The codes the cart and the checkout advertise, each already measured
     * against this basket so the page can render "Apply" or "Add $12 more"
     * without doing any arithmetic of its own.
     *
     * @return array<int, array<string, mixed>>
     */
    public function offers(float $subtotal): array
    {
        return $this->publicCoupons()
            ->map(fn (Coupon $coupon) => $this->describe($coupon, $subtotal))
            ->all();
    }

    /**
     * The first live public code is the promotion featured on the shop page.
     * Its position is controlled by the same sort order as the offer cards.
     *
     * @return array<string, mixed>|null
     */
    public function featuredOffer(): ?array
    {
        $coupon = $this->publicCouponQuery()->first();

        return $coupon === null ? null : $this->describe($coupon, 0.0);
    }

    /**
     * @return Collection<int, Coupon>
     */
    public function publicCoupons(): Collection
    {
        return $this->publicCouponQuery()->get();
    }

    /**
     * One offer as the storefront needs it.
     *
     * @return array<string, mixed>
     */
    public function describe(Coupon $coupon, float $subtotal): array
    {
        $qualifies = $coupon->qualifies($subtotal);

        return [
            'code' => $coupon->code,
            'value_label' => $coupon->valueLabel(),
            'description' => $coupon->descriptionLabel(),
            'promotion_label' => $coupon->promotionLabel(),
            'type' => $coupon->type,
            'value' => (float) $coupon->value,
            'min_order_amount' => (float) $coupon->min_order_amount,
            'max_discount_amount' => $coupon->max_discount_amount !== null
                ? (float) $coupon->max_discount_amount
                : null,
            'ends_at' => $coupon->ends_at?->toDateString(),
            'ends_at_iso' => $coupon->ends_at?->toIso8601String(),
            'qualifies' => $qualifies,
            'shortfall' => $coupon->shortfallFor($subtotal),
            'discount' => $coupon->discountFor($subtotal),
        ];
    }

    /**
     * The code by name, or a readable failure.
     *
     * Called both when the customer applies a code and again when the order is
     * placed, which is what stops a stale discount being honoured. The
     * messages are deliberately specific: "that code is not valid" for an
     * expired code sends people to support.
     *
     * @throws CouponNotApplicableException
     */
    public function resolve(?string $code, float $subtotal): Coupon
    {
        $code = mb_strtoupper(trim((string) $code));

        if ($code === '') {
            throw new CouponNotApplicableException('Enter a coupon code first.');
        }

        $coupon = Coupon::query()->where('code', $code)->first();

        if ($coupon === null) {
            throw new CouponNotApplicableException('That code is not valid.');
        }

        if (! $coupon->is_active) {
            throw new CouponNotApplicableException('That code is no longer available.');
        }

        if ($coupon->hasNotStarted()) {
            throw new CouponNotApplicableException(
                'That code starts on '.$coupon->starts_at->format('j M Y').'.',
            );
        }

        if ($coupon->hasExpired()) {
            throw new CouponNotApplicableException('That code expired on '.$coupon->ends_at->format('j M Y').'.');
        }

        if ($coupon->isExhausted()) {
            throw new CouponNotApplicableException('That code has been fully redeemed.');
        }

        if (! $coupon->qualifies($subtotal)) {
            $symbol = config('shop.currency_symbol', '$');

            throw new CouponNotApplicableException(
                'Add '.$symbol.number_format($coupon->shortfallFor($subtotal), 2).' more to use this code.',
            );
        }

        return $coupon;
    }

    /**
     * Same as resolve() but returns null instead of throwing, for the places
     * that only want a discount if one happens to apply - a cart repainting
     * after the customer removed an item, say.
     */
    public function resolveQuietly(?string $code, float $subtotal): ?Coupon
    {
        try {
            return $this->resolve($code, $subtotal);
        } catch (CouponNotApplicableException) {
            return null;
        }
    }

    /**
     * Counts one redemption.
     *
     * The increment is a single atomic UPDATE rather than a read-modify-write,
     * so two orders placed in the same instant cannot both see the same
     * used_count and push a limited code one past its limit.
     */
    public function redeem(Coupon $coupon): void
    {
        DB::table($coupon->getTable())
            ->where('id', $coupon->id)
            ->increment('used_count');
    }

    /* -------------------------------------------------------------- private */

    private function publicCouponQuery(): Builder
    {
        return Coupon::query()->redeemable()->public()->ordered();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        return max(5, min(100, $perPage));
    }
}
