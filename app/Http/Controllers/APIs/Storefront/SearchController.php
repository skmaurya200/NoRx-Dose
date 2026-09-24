<?php

namespace App\Http\Controllers\APIs\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Catalogue\SearchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The suggestions under the header's search box.
 *
 * Unauthenticated and read-only. It returns nothing a visitor could not reach
 * by browsing the shop - live products, active categories, published posts -
 * and it does not count the search: the box fires on every keystroke, and
 * counting "m", "ma", "mag" would make the numbers in tbl_search_queries
 * meaningless. The results page does the counting.
 */
class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    /**
     * GET /api/storefront/search?q=…
     */
    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Capped well below anything a person types. A long term is a
            // paste or a probe, and either way the answer is the same.
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return ApiResponse::success(
            $this->search->suggest((string) ($data['q'] ?? '')),
            'Suggestions loaded.',
        );
    }
}
