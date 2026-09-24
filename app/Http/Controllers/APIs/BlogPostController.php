<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\Blog\StoreBlogPostRequest;
use App\Http\Requests\APIs\Blog\UpdateBlogPostRequest;
use App\Http\Resources\APIs\BlogPostResource;
use App\Models\BlogPost;
use App\Services\Blog\BlogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Journal posts - the back-office half.
 *
 * Validation lives in the request classes and the sanitising and slug rules in
 * BlogService, so this controller only routes between them.
 */
class BlogPostController extends Controller
{
    public function __construct(private readonly BlogService $blog) {}

    /**
     * GET /api/manager/blog/posts
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->blog->paginatePosts($request->only([
            'search', 'status', 'category_id', 'per_page',
        ]));

        return ApiResponse::success([
            'items' => BlogPostResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Posts loaded.');
    }

    /**
     * GET /api/manager/blog/posts/{post}
     */
    public function show(BlogPost $post): JsonResponse
    {
        return ApiResponse::success(
            new BlogPostResource($post->load('category:id,name,slug')),
            'Post loaded.',
        );
    }

    /**
     * POST /api/manager/blog/posts
     */
    public function store(StoreBlogPostRequest $request): JsonResponse
    {
        $post = $this->blog->createPost($request->payload(), $request->file('cover'));

        return ApiResponse::success(
            new BlogPostResource($post),
            'Post created.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * POST /api/manager/blog/posts/{post}
     *
     * POST rather than PUT: the form is multipart so it can carry the cover
     * image, and PHP does not populate $_FILES for a PUT body.
     */
    public function update(UpdateBlogPostRequest $request, BlogPost $post): JsonResponse
    {
        $post = $this->blog->updatePost(
            $post,
            $request->payload(),
            $request->file('cover'),
            $request->shouldRemoveCover(),
        );

        return ApiResponse::success(new BlogPostResource($post), 'Post updated.');
    }

    /**
     * DELETE /api/manager/blog/posts/{post}
     */
    public function destroy(BlogPost $post): JsonResponse
    {
        $this->blog->deletePost($post);

        return ApiResponse::success(null, 'Post deleted.');
    }

    /**
     * PATCH /api/manager/blog/posts/{post}/toggle
     */
    public function toggle(BlogPost $post): JsonResponse
    {
        $post = $this->blog->togglePublished($post);

        return ApiResponse::success(
            new BlogPostResource($post),
            $post->status === 'published' ? 'Post is now live.' : 'Post moved back to draft.',
        );
    }
}
