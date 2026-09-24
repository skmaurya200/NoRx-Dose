<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\Product\ReorderProductImagesRequest;
use App\Http\Requests\APIs\Product\StoreProductImageRequest;
use App\Http\Resources\APIs\ProductDetailResource;
use App\Http\Resources\APIs\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalogue\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Product details module API - the long-form copy and the image gallery.
 *
 * Split from ProductController because these are edited on their own screen,
 * one action at a time (add an image, make it primary, drag to reorder), rather
 * than as part of a single form submit.
 *
 * Every route here is nested under a product and every image is bound with
 * scopeBindings(), so an image id from another product resolves as a 404
 * instead of being mutated across the tenancy boundary.
 */
class ProductDetailController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    /**
     * GET /api/manager/products/{product}/detail
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['detail', 'images']);

        return ApiResponse::success([
            'detail' => $product->detail
                ? new ProductDetailResource($product->detail)
                : null,
            'images' => ProductImageResource::collection($product->images),
        ], 'Product detail loaded.');
    }

    /**
     * POST /api/manager/products/{product}/images
     */
    public function storeImages(StoreProductImageRequest $request, Product $product): JsonResponse
    {
        // Throws LimitExceededException (422) if this push would take the
        // gallery past the configured ceiling.
        $images = $this->products->addImages($product, $request->uploadedImages());

        return ApiResponse::success(
            ProductImageResource::collection($images),
            count($images) === 1 ? 'Image uploaded.' : count($images).' images uploaded.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * DELETE /api/manager/products/{product}/images/{image}
     */
    public function destroyImage(Product $product, ProductImage $image): JsonResponse
    {
        $this->products->deleteImage($product, $image);

        return ApiResponse::success(null, 'Image removed.');
    }

    /**
     * PATCH /api/manager/products/{product}/images/{image}/primary
     */
    public function setPrimaryImage(Product $product, ProductImage $image): JsonResponse
    {
        $this->products->setPrimaryImage($product, $image);

        return ApiResponse::success(
            ProductImageResource::collection($product->load('images')->images),
            'Primary image updated.',
        );
    }

    /**
     * PATCH /api/manager/products/{product}/images/order
     */
    public function reorderImages(ReorderProductImagesRequest $request, Product $product): JsonResponse
    {
        $this->products->reorderImages($product, $request->orderedIds());

        return ApiResponse::success(
            ProductImageResource::collection($product->load('images')->images),
            'Gallery reordered.',
        );
    }
}
