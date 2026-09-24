<?php

namespace App\Http\Controllers\APIs\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\Review\SubmitReviewRequest;
use App\Models\Review;
use App\Services\Reviews\ReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a shopper may do with reviews: leave one, and say another was useful.
 *
 * Unauthenticated, so it gives nothing back but a confirmation. A submitted
 * review is pending and stays invisible - including to the person who wrote
 * it - until an operator approves it in the panel.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    /**
     * POST /api/storefront/reviews
     */
    public function submit(SubmitReviewRequest $request): JsonResponse
    {
        // Throws TooManyReviewsException (429) once one address has filed the
        // day's allowance.
        $review = $this->reviews->submit($request->payload());

        return ApiResponse::success([
            // The id is enough for the page to say thank you. Nothing about
            // the review itself is echoed back: it is not public yet.
            'id' => $review->id,
            'status' => $review->status,
        ], 'Thank you — your review is with us and appears once we have checked it.',
            Response::HTTP_CREATED);
    }

    /**
     * POST /api/storefront/reviews/{review}/helpful
     *
     * Only an approved review can be voted on, or the count would leak the
     * existence of one nobody is allowed to read.
     */
    public function helpful(Review $review): JsonResponse
    {
        abort_unless($review->isApproved(), Response::HTTP_NOT_FOUND);

        return ApiResponse::success(
            ['helpful_count' => $this->reviews->markHelpful($review)],
            'Thanks for the feedback.',
        );
    }
}
