<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Services\Catalogue\ProductCategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The category screens in the admin panel.
 *
 * These render HTML only. Every create, update, delete and toggle is posted by
 * the browser to App\Http\Controllers\APIs\ProductCategoryController, so there
 * is exactly one implementation of the rules and one set of validation messages
 * whether the caller is this panel or a mobile app.
 */
class ProductCategoryController extends Controller
{
    public function __construct(private readonly ProductCategoryService $categories) {}

    /**
     * GET /manager/categories
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'status', 'parent_id', 'per_page']);

        return view('manager.categories.index', [
            'categories' => $this->categories->paginate($filters),
            'filters' => $filters,
            'parents' => $this->parentOptions(),
        ]);
    }

    /**
     * GET /manager/categories/create
     */
    public function create(): View
    {
        return view('manager.categories.form', [
            'category' => new ProductCategory(['is_active' => true, 'sort_order' => 0]),
            'parents' => $this->parentOptions(),
            'isEdit' => false,
        ]);
    }

    /**
     * GET /manager/categories/{category}/edit
     */
    public function edit(ProductCategory $category): View
    {
        return view('manager.categories.form', [
            'category' => $category,
            // A category cannot be its own parent, so it is excluded from the
            // picker rather than only being rejected after submit.
            'parents' => $this->parentOptions($category->id),
            'isEdit' => true,
        ]);
    }

    /**
     * Top-level categories only. The tree is intentionally two levels deep:
     * deeper nesting makes storefront navigation worse, not better.
     *
     * @return Collection<int, ProductCategory>
     */
    private function parentOptions(?int $exclude = null): Collection
    {
        return ProductCategory::query()
            ->whereNull('parent_id')
            ->when($exclude !== null, fn ($query) => $query->whereKeyNot($exclude))
            ->ordered()
            ->get(['id', 'name']);
    }
}
