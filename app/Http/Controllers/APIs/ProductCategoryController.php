<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\ProductCategory\StoreProductCategoryRequest;
use App\Http\Requests\APIs\ProductCategory\UpdateProductCategoryRequest;
use App\Http\Resources\APIs\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Services\Catalogue\ProductCategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Product category module API.
 *
 * Validation lives in the request classes, business rules in the service, and
 * the response shape in ApiResponse - so this controller only routes between
 * them. Every failure it can produce is either a 422 from the request or an
 * ApiException from the service; nothing is caught here, because the handler
 * in bootstrap/app.php already renders both.
 */
class ProductCategoryController extends Controller
{
    public function __construct(private readonly ProductCategoryService $categories) {}

    /**
     * GET /api/manager/categories
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->categories->paginate($request->only([
            'search', 'status', 'parent_id', 'per_page',
        ]));

        return ApiResponse::success([
            'items' => ProductCategoryResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Categories loaded.');
    }

    /**
     * GET /api/manager/categories/{category}
     */
    public function show(ProductCategory $category): JsonResponse
    {
        return ApiResponse::success(
            new ProductCategoryResource($category->load('parent:id,name')->loadCount('products')),
            'Category loaded.',
        );
    }

    /**
     * POST /api/manager/categories
     */
    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create(
            $request->payload(),
            $request->file('image'),
        );

        return ApiResponse::success(
            new ProductCategoryResource($category),
            'Category created.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * POST /api/manager/categories/{category}
     *
     * POST rather than PUT: the form sends multipart so it can carry an image,
     * and PHP does not populate $_FILES for a PUT body.
     */
    public function update(UpdateProductCategoryRequest $request, ProductCategory $category): JsonResponse
    {
        $category = $this->categories->update(
            $category,
            $request->payload(),
            $request->file('image'),
            $request->shouldRemoveImage(),
        );

        return ApiResponse::success(
            new ProductCategoryResource($category->load('parent:id,name')),
            'Category updated.',
        );
    }

    /**
     * DELETE /api/manager/categories/{category}
     */
    public function destroy(ProductCategory $category): JsonResponse
    {
        // Throws ResourceInUseException (409) when products or children still
        // depend on it, rather than letting a foreign key blow up as a 500.
        $this->categories->delete($category);

        return ApiResponse::success(null, 'Category deleted.');
    }

    /**
     * PATCH /api/manager/categories/{category}/toggle
     */
    public function toggle(ProductCategory $category): JsonResponse
    {
        $category = $this->categories->toggleActive($category);

        return ApiResponse::success(
            new ProductCategoryResource($category),
            $category->is_active ? 'Category is now active.' : 'Category is now hidden.',
        );
    }

    /**
     * GET /api/manager/categories/options
     *
     * Flat list for the <select> on the product and category forms. Deliberately
     * not paginated and deliberately tiny - a picker does not need the full
     * resource.
     */
    public function options(): JsonResponse
    {
        $options = ProductCategory::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'parent_id'])
            ->map(fn (ProductCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
            ]);

        return ApiResponse::success($options, 'Category options loaded.');
    }
}
