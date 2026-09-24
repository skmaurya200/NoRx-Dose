<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\Blog\StoreBlogCategoryRequest;
use App\Http\Requests\APIs\Blog\UpdateBlogCategoryRequest;
use App\Http\Resources\APIs\BlogCategoryResource;
use App\Models\BlogCategory;
use App\Services\Blog\BlogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Journal categories - the chips above the post list.
 */
class BlogCategoryController extends Controller
{
    public function __construct(private readonly BlogService $blog) {}

    /**
     * GET /api/manager/blog/categories
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->blog->paginateCategories($request->only(['search', 'status', 'per_page']));

        return ApiResponse::success([
            'items' => BlogCategoryResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Categories loaded.');
    }

    /**
     * POST /api/manager/blog/categories
     */
    public function store(StoreBlogCategoryRequest $request): JsonResponse
    {
        $category = $this->blog->createCategory($request->payload());

        return ApiResponse::success(
            new BlogCategoryResource($category),
            'Category created.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * POST /api/manager/blog/categories/{category}
     */
    public function update(UpdateBlogCategoryRequest $request, BlogCategory $category): JsonResponse
    {
        $category = $this->blog->updateCategory($category, $request->payload());

        return ApiResponse::success(new BlogCategoryResource($category), 'Category updated.');
    }

    /**
     * DELETE /api/manager/blog/categories/{category}
     */
    public function destroy(BlogCategory $category): JsonResponse
    {
        // Throws ResourceInUseException (409) while posts still point at it,
        // rather than silently detaching them.
        $this->blog->deleteCategory($category);

        return ApiResponse::success(null, 'Category deleted.');
    }

    /**
     * PATCH /api/manager/blog/categories/{category}/toggle
     */
    public function toggle(BlogCategory $category): JsonResponse
    {
        $category = $this->blog->toggleCategory($category);

        return ApiResponse::success(
            new BlogCategoryResource($category),
            $category->is_active ? 'Category is now active.' : 'Category is now hidden.',
        );
    }
}
