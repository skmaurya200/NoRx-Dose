<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Services\Reviews\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The review screens in the admin panel: the moderation queue and the
 * create/edit form.
 *
 * HTML only - approving, rejecting, deleting and saving are posted by the
 * browser to App\Http\Controllers\APIs\ReviewController, so the rules that
 * keep tbl_products.rating_avg in step have one implementation.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    /**
     * GET /manager/reviews
     */
    public function index(Request $request): View
    {
        $filters = $request->only([
            'search', 'status', 'rating', 'source', 'product_id', 'trashed', 'per_page',
        ]);

        return view('manager.reviews.index', [
            'reviews' => $this->reviews->paginate($filters),
            'filters' => $filters,
            'products' => $this->productOptions(),
            'summary' => $this->summary(),
        ]);
    }

    /**
     * GET /manager/reviews/create
     */
    public function create(): View
    {
        return view('manager.reviews.form', [
            'review' => new Review(['rating' => 5, 'category' => 'quality', 'is_verified' => true]),
            'products' => $this->productOptions(),
            'isEdit' => false,
        ]);
    }

    /**
     * GET /manager/reviews/{review}/edit
     */
    public function edit(Review $review): View
    {
        return view('manager.reviews.form', [
            'review' => $review,
            'products' => $this->productOptions(),
            'isEdit' => true,
        ]);
    }

    /**
     * The product picker. A review may also be about the shop rather than
     * about anything in the catalogue, which is the blank option on the form.
     *
     * @return Collection<int, Product>
     */
    private function productOptions(): Collection
    {
        return Product::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Counters for the cards above the queue.
     *
     * @return array<string, int>
     */
    private function summary(): array
    {
        return [
            'total' => Review::query()->count(),
            'pending' => Review::query()->pending()->count(),
            'approved' => Review::query()->approved()->count(),
            'rejected' => Review::query()->where('status', 'rejected')->count(),
        ];
    }
}
