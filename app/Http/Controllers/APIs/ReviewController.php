<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\Review\StoreReviewRequest;
use App\Http\Resources\APIs\ReviewResource;
use App\Models\Review;
use App\Services\Reviews\ReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reviews module API - the back-office half.
 *
 * The storefront reads and writes through APIs\Storefront\ReviewController,
 * which is unauthenticated and can only ever create a pending review.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    /**
     * GET /api/manager/reviews
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->reviews->paginate($request->only([
            'search', 'status', 'rating', 'source', 'product_id', 'trashed', 'per_page',
        ]));

        return ApiResponse::success([
            'items' => ReviewResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Reviews loaded.');
    }

    /**
     * POST /api/manager/reviews
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $review = $this->reviews->createByManager($request->payload());

        return ApiResponse::success(
            new ReviewResource($review),
            'Review published.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * POST /api/manager/reviews/{review}
     */
    public function update(StoreReviewRequest $request, Review $review): JsonResponse
    {
        $review = $this->reviews->update($review, $request->payload());

        return ApiResponse::success(new ReviewResource($review), 'Review updated.');
    }

    /**
     * PATCH /api/manager/reviews/{review}/toggle
     *
     * The approve/reject switch on a list row.
     */
    public function toggle(Review $review): JsonResponse
    {
        $review = $this->reviews->toggleApproved($review);

        return ApiResponse::success(
            new ReviewResource($review),
            $review->isApproved() ? 'Review is now live.' : 'Review taken off the storefront.',
        );
    }

    /**
     * DELETE /api/manager/reviews/{review}
     */
    public function destroy(Review $review): JsonResponse
    {
        $this->reviews->delete($review);

        return ApiResponse::success(null, 'Review deleted.');
    }

    /**
     * PATCH /api/manager/reviews/{review}/restore
     */
    public function restore(int $review): JsonResponse
    {
        $found = Review::onlyTrashed()->findOrFail($review);

        return ApiResponse::success(
            new ReviewResource($this->reviews->restore($found)),
            'Review restored.',
        );
    }
}
