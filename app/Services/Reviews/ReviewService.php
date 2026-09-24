<?php

namespace App\Services\Reviews;

use App\Exceptions\Reviews\TooManyReviewsException;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Every read and write of a review.
 *
 * Two rules this class exists to keep:
 *
 *  - a review is invisible until an operator approves it, so the storefront
 *    never queries anything but approved rows;
 *  - tbl_products carries a denormalised rating_avg and rating_count, and they
 *    are recalculated here on every change of moderation state. Nothing else
 *    may write those two columns or they drift away from the reviews.
 */
class ReviewService
{
    /* ---------------------------------------------------------- panel side */

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Review::query()
            ->with('product:id,name,slug')
            ->when(($filters['trashed'] ?? '') === 'only', fn (Builder $q) => $q->onlyTrashed())
            ->search($filters['search'] ?? null)
            ->when(($filters['status'] ?? '') !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(($filters['rating'] ?? '') !== '', fn (Builder $q) => $q->where('rating', (int) $filters['rating']))
            ->when(($filters['source'] ?? '') !== '', fn (Builder $q) => $q->where('source', $filters['source']))
            ->when(($filters['product_id'] ?? '') !== '', function (Builder $query) use ($filters) {
                $filters['product_id'] === 'general'
                    ? $query->whereNull('product_id')
                    : $query->where('product_id', (int) $filters['product_id']);
            })
            // Pending first: the panel's job is moderation, and a queue that
            // buries what needs attention is not a queue. A CASE rather than
            // MySQL's FIELD(), which the test database does not have.
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * A review written by an operator. Approved on the spot - there is nobody
     * else to approve it, and asking someone to approve their own writing is
     * a step that means nothing.
     *
     * @param  array<string, mixed>  $data
     */
    public function createByManager(array $data): Review
    {
        return DB::transaction(function () use ($data) {
            $review = Review::create($data + ['source' => 'manager']);

            $this->approve($review);

            return $review->refresh();
        });
    }

    /**
     * A review submitted from the storefront. Always pending, whatever the
     * form said - moderation state is not the visitor's to set.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws TooManyReviewsException
     */
    public function submit(array $data): Review
    {
        $this->guardDailyLimit($data['author_email'] ?? null, $data['ip_address'] ?? null);

        $order = $this->matchOrder($data['author_email'] ?? null, $data['product_id'] ?? null);

        $review = Review::create($data + [
            'source' => 'customer',
            'order_id' => $order?->id,
            // "Verified" means the shop can see an order from this address for
            // this product. Nothing else earns the badge.
            'is_verified' => $order !== null,
        ]);

        // status is not fillable, so the row's default has to be read back
        // rather than assumed - the caller reports it to the visitor.
        return $review->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Review $review, array $data): Review
    {
        return DB::transaction(function () use ($review, $data) {
            $review->fill($data)->save();

            // The rating may have changed, so the product's average has too.
            $this->settleFeatured($review);
            $this->recount($review->product_id);

            return $review->refresh();
        });
    }

    public function approve(Review $review, ?Admin $by = null): Review
    {
        return DB::transaction(function () use ($review, $by) {
            $review->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => ($by ?? Auth::guard('admin')->user())?->id,
            ])->save();

            $this->settleFeatured($review);
            $this->recount($review->product_id);

            return $review->refresh();
        });
    }

    /**
     * Takes a review off the storefront. Kept rather than deleted, so the same
     * review is not re-submitted and re-moderated a week later.
     */
    public function reject(Review $review): Review
    {
        return DB::transaction(function () use ($review) {
            $review->forceFill([
                'status' => 'rejected',
                'approved_at' => null,
                'approved_by' => null,
                'is_featured' => false,
            ])->save();

            $this->recount($review->product_id);

            return $review->refresh();
        });
    }

    /**
     * The approve/reject switch the list rows use.
     */
    public function toggleApproved(Review $review): Review
    {
        return $review->isApproved() ? $this->reject($review) : $this->approve($review);
    }

    public function delete(Review $review): void
    {
        DB::transaction(function () use ($review) {
            $productId = $review->product_id;

            $review->delete();

            // A soft-deleted review is off the storefront, so it must come out
            // of the product's average too.
            $this->recount($productId);
        });
    }

    public function restore(Review $review): Review
    {
        return DB::transaction(function () use ($review) {
            $review->restore();

            $this->recount($review->product_id);

            return $review->refresh();
        });
    }

    /* ------------------------------------------------------ storefront side */

    /**
     * Every approved review the reviews page is allowed to show, in one go.
     *
     * The page filters by category and reveals six at a time in the browser,
     * so paging on the server would fight it. Capped rather than unbounded:
     * a shop with ten thousand reviews should not ship them all to render six.
     *
     * @return Collection<int, Review>
     */
    public function feed(int $limit = 60): Collection
    {
        return Review::query()
            ->approved()
            ->with('product:id,name,slug')
            ->newest()
            ->limit($limit)
            ->get();
    }

    /**
     * One review as the storefront's scripts consume it. Nothing here that a
     * visitor is not allowed to see - no email address, no IP, no moderation
     * trail.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(Review $review): array
    {
        return [
            'id' => $review->id,
            'who' => $review->author_name,
            'meta' => $review->byline(),
            'cat' => $review->category,
            'cat_label' => $review->categoryLabel(),
            'stars' => (int) $review->rating,
            'title' => $review->title,
            'text' => $review->body,
            'helpful' => (int) $review->helpful_count,
            'verified' => (bool) $review->is_verified,
            'product' => $review->product?->name,
            'date' => $review->approved_at?->format('j M Y') ?? $review->created_at?->format('j M Y'),
        ];
    }

    /**
     * The review pulled out at the top of the page: the flagged one, or the
     * newest five-star otherwise.
     */
    public function featured(): ?Review
    {
        return Review::query()->approved()->where('is_featured', true)->newest()->first()
            ?? Review::query()->approved()->where('rating', 5)->newest()->first();
    }

    /**
     * @return Collection<int, Review>
     */
    public function forProduct(Product $product, int $limit = 20): Collection
    {
        return Review::query()
            ->approved()
            ->forProduct($product->id)
            ->newest()
            ->limit($limit)
            ->get();
    }

    /**
     * The counts behind the rating bars: how many approved reviews at each
     * star, and what share of the whole that is.
     *
     * @return array{total: int, average: float, bars: array<int, array<string, int|float>>}
     */
    public function summary(?int $productId = null): array
    {
        $counts = Review::query()
            ->approved()
            ->when($productId !== null, fn (Builder $q) => $q->forProduct($productId))
            ->when($productId === null, fn (Builder $q) => $q)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $total = (int) $counts->sum();
        $weighted = $counts->reduce(fn (int $carry, int $count, int $rating) => $carry + ($rating * $count), 0);

        $bars = [];

        // Five down to one, which is the order a rating breakdown is read in.
        foreach (range(5, 1) as $rating) {
            $count = (int) ($counts[$rating] ?? 0);

            $bars[] = [
                'rating' => $rating,
                'count' => $count,
                'percent' => $total > 0 ? (int) round($count / $total * 100) : 0,
            ];
        }

        return [
            'total' => $total,
            'average' => $total > 0 ? round($weighted / $total, 1) : 0.0,
            'bars' => $bars,
        ];
    }

    /**
     * The average score per category, for the four figures under the rating
     * bars. A category nobody has reviewed is left out rather than shown as
     * zero, which would read as "we score 0 on packaging".
     *
     * @return array<int, array{key: string, label: string, average: float, total: int}>
     */
    public function categoryAverages(): array
    {
        $rows = Review::query()
            ->approved()
            ->selectRaw('category, COUNT(*) as total, AVG(rating) as average')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $out = [];

        // Driven by the model's list rather than by the query, so the figures
        // are always in the same order however the data happens to land.
        foreach (Review::CATEGORIES as $key => $label) {
            $row = $rows[$key] ?? null;

            if ($row === null) {
                continue;
            }

            $out[] = [
                'key' => $key,
                'label' => $label,
                'average' => round((float) $row->average, 1),
                'total' => (int) $row->total,
            ];
        }

        return $out;
    }

    /**
     * Counts one "this was helpful". Deliberately not deduplicated: there are
     * no accounts to key it to, and a cookie for it would be more machinery
     * than the number is worth.
     */
    public function markHelpful(Review $review): int
    {
        Review::query()->whereKey($review->id)->increment('helpful_count');

        return (int) $review->fresh()->helpful_count;
    }

    /* -------------------------------------------------------------- private */

    /**
     * Caps how many reviews one person can file in a day.
     *
     * Counted on the email address and the IP together rather than either
     * alone: the address is what a real customer keeps constant, the IP is
     * what someone cycling addresses cannot.
     *
     * @throws TooManyReviewsException
     */
    private function guardDailyLimit(?string $email, ?string $ip): void
    {
        $limit = (int) config('shop.reviews.max_per_day', 5);

        if ($limit <= 0) {
            return;
        }

        $today = Review::withTrashed()
            ->where('created_at', '>=', now()->subDay())
            ->where(function (Builder $query) use ($email, $ip) {
                $query->when(filled($email), fn (Builder $q) => $q->orWhere('author_email', $email))
                    ->when(filled($ip), fn (Builder $q) => $q->orWhere('ip_address', $ip));
            })
            ->count();

        if ($today >= $limit) {
            throw new TooManyReviewsException;
        }
    }

    /**
     * The order that makes a review "verified", if there is one.
     *
     * Matched on the email address, because guest checkout is all this shop
     * has - there are no accounts to match on. A product review needs an order
     * containing that product; a general one needs any order.
     */
    private function matchOrder(?string $email, ?int $productId): ?Order
    {
        if (blank($email)) {
            return null;
        }

        return Order::query()
            ->where('email', $email)
            ->where('status', '!=', 'cancelled')
            ->when($productId !== null, fn (Builder $q) => $q->whereHas(
                'items',
                fn (Builder $items) => $items->where('product_id', $productId),
            ))
            ->latest('placed_at')
            ->first();
    }

    /**
     * One featured review at a time, so setting a new one clears the last.
     */
    private function settleFeatured(Review $review): void
    {
        if (! $review->is_featured) {
            return;
        }

        Review::query()
            ->whereKeyNot($review->id)
            ->where('is_featured', true)
            ->update(['is_featured' => false]);
    }

    /**
     * Rewrites a product's rating from its approved reviews.
     *
     * forceFill rather than update: rating_avg and rating_count are excluded
     * from the model's fillable list precisely so a form cannot set them, and
     * this is the one place allowed to.
     */
    private function recount(?int $productId): void
    {
        if ($productId === null) {
            return;
        }

        $product = Product::find($productId);

        if ($product === null) {
            return;
        }

        $stats = Review::query()
            ->approved()
            ->forProduct($productId)
            ->selectRaw('COUNT(*) as total, COALESCE(AVG(rating), 0) as average')
            ->first();

        $product->forceFill([
            'rating_count' => (int) $stats->total,
            'rating_avg' => round((float) $stats->average, 2),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? config('admin.catalogue.per_page', 15));

        return max(5, min((int) config('admin.catalogue.max_per_page', 100), $perPage));
    }
}
