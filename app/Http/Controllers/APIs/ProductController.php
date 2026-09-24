<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\Product\StoreProductRequest;
use App\Http\Requests\APIs\Product\UpdateProductRequest;
use App\Http\Resources\APIs\ProductResource;
use App\Models\Product;
use App\Services\Catalogue\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Product module API.
 *
 * The gallery and the long-form detail live on their own controller
 * (ProductDetailController) so this one stays about the product record itself.
 */
class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    /**
     * GET /api/manager/products
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->products->paginate($request->only([
            'search', 'category_id', 'status', 'stock', 'sort', 'direction', 'per_page',
        ]));

        return ApiResponse::success([
            'items' => ProductResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Products loaded.');
    }

    /**
     * GET /api/manager/products/{product}
     */
    public function show(Product $product): JsonResponse
    {
        return ApiResponse::success(
            new ProductResource($product->load(['category:id,name', 'detail', 'images', 'packs'])),
            'Product loaded.',
        );
    }

    /**
     * POST /api/manager/products
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->products->create(
            $request->payload(),
            $request->file('thumbnail'),
            $request->galleryFiles(),
            $request->touchesPacks() ? $request->packs() : null,
        );

        return ApiResponse::success(
            new ProductResource($product),
            'Product created.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * POST /api/manager/products/{product}
     *
     * POST, not PUT: multipart bodies carry the images, and PHP does not
     * populate $_FILES for a PUT request.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->products->update(
            $product,
            $request->payload(),
            $request->file('thumbnail'),
            $request->galleryFiles(),
            $request->shouldRemoveThumbnail(),
            $request->touchesPacks() ? $request->packs() : null,
        );

        return ApiResponse::success(
            new ProductResource($product),
            'Product updated.',
        );
    }

    /**
     * DELETE /api/manager/products/{product}
     */
    public function destroy(Product $product): JsonResponse
    {
        // Soft delete: an existing order still has to be able to resolve this
        // product's name and price long after it leaves the catalogue.
        $this->products->delete($product);

        return ApiResponse::success(null, 'Product deleted.');
    }

    /**
     * PATCH /api/manager/products/{product}/status
     */
    public function updateStatus(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Product::STATUSES)],
        ]);

        $product = $this->products->updateStatus($product, $validated['status']);

        return ApiResponse::success(
            new ProductResource($product),
            'Product status updated to '.$product->status.'.',
        );
    }
}
