<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Services\Blog\BlogService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The journal screens in the admin panel: the post list, the editor, and the
 * category list beside it.
 *
 * HTML only - every write is posted by the browser to
 * App\Http\Controllers\APIs\BlogPostController and its category counterpart.
 */
class BlogController extends Controller
{
    public function __construct(private readonly BlogService $blog) {}

    /* ----------------------------------------------------------------- posts */

    /**
     * GET /manager/blog
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'status', 'category_id', 'per_page']);

        return view('manager.blog.index', [
            'posts' => $this->blog->paginatePosts($filters),
            'filters' => $filters,
            'categories' => $this->categoryOptions(),
            'summary' => $this->summary(),
        ]);
    }

    /**
     * GET /manager/blog/create
     */
    public function create(): View
    {
        return view('manager.blog.form', [
            'post' => new BlogPost([
                'status' => 'draft',
                'author_name' => 'The Aurum team',
                'is_featured' => false,
            ]),
            'categories' => $this->categoryOptions(),
            'isEdit' => false,
        ]);
    }

    /**
     * GET /manager/blog/{post}/edit
     */
    public function edit(BlogPost $post): View
    {
        return view('manager.blog.form', [
            'post' => $post,
            'categories' => $this->categoryOptions(),
            'isEdit' => true,
        ]);
    }

    /* ------------------------------------------------------------ categories */

    /**
     * GET /manager/blog/categories
     */
    public function categories(Request $request): View
    {
        $filters = $request->only(['search', 'status', 'per_page']);

        return view('manager.blog.categories.index', [
            'categories' => $this->blog->paginateCategories($filters),
            'filters' => $filters,
        ]);
    }

    /**
     * GET /manager/blog/categories/create
     */
    public function createCategory(): View
    {
        return view('manager.blog.categories.form', [
            'category' => new BlogCategory(['is_active' => true, 'sort_order' => 0]),
            'isEdit' => false,
        ]);
    }

    /**
     * GET /manager/blog/categories/{category}/edit
     */
    public function editCategory(BlogCategory $category): View
    {
        return view('manager.blog.categories.form', [
            'category' => $category,
            'isEdit' => true,
        ]);
    }

    /* --------------------------------------------------------------- private */

    /**
     * @return Collection<int, BlogCategory>
     */
    private function categoryOptions(): Collection
    {
        return BlogCategory::query()->ordered()->get(['id', 'name', 'is_active']);
    }

    /**
     * @return array<string, int>
     */
    private function summary(): array
    {
        return [
            'total' => BlogPost::query()->count(),
            'published' => BlogPost::query()->published()->count(),
            'draft' => BlogPost::query()->where('status', 'draft')->count(),
            'views' => (int) BlogPost::query()->sum('views_count'),
        ];
    }
}
