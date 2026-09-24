<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Catalogue\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The product screens in the admin panel: list, create/edit form, and the
 * product details view.
 *
 * As with the categories, these render HTML only - every mutation is posted to
 * App\Http\Controllers\APIs\ProductController and its detail counterpart.
 */
class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    /**
     * GET /manager/products
     */
    public function index(Request $request): View
    {
        $filters = $request->only([
            'search', 'category_id', 'status', 'stock', 'sort', 'direction', 'per_page',
        ]);

        return view('manager.products.index', [
            'products' => $this->products->paginate($filters),
            'filters' => $filters,
            'categories' => $this->categoryOptions(),
            'summary' => $this->summary(),
        ]);
    }

    /**
     * GET /manager/products/create
     */
    public function create(): View
    {
        return view('manager.products.form', [
            // What the form starts on - the shop's defaults for a new product,
            // not the column defaults. Stock is counted per pack size here, so
            // a product is on sale and orderable the moment it is created
            // rather than needing three switches flipped first.
            //
            // setRelation because a brand-new product has no packs relation
            // loaded and the form iterates it.
            'product' => (new Product([
                'status' => 'active',
                'currency' => config('shop.currency', 'USD'),
                'track_inventory' => false,
                'allow_backorder' => true,
                'stock_quantity' => 0,
                'low_stock_threshold' => 5,
            ]))->setRelation('packs', collect()),
            'categories' => $this->categoryOptions(),
            'isEdit' => false,
        ]);
    }

    /**
     * GET /manager/products/{product}/edit
     */
    public function edit(Product $product): View
    {
        return view('manager.products.form', [
            'product' => $product->load(['detail', 'images', 'packs']),
            'categories' => $this->categoryOptions(),
            'isEdit' => true,
        ]);
    }

    /**
     * GET /manager/products/{product}
     *
     * The product details screen - everything about one product on one page,
     * including the gallery manager.
     */
    public function show(Product $product): View
    {
        return view('manager.products.show', [
            'product' => $product->load(['category:id,name,slug', 'detail', 'images', 'packs']),
        ]);
    }

    /**
     * Flat picker list. Children are indented under their parent so a two-level
     * tree reads correctly in a plain <select>.
     *
     * @return Collection<int, array{id: int, label: string}>
     */
    private function categoryOptions(): Collection
    {
        $all = ProductCategory::query()->ordered()->get(['id', 'name', 'parent_id']);

        return $all->whereNull('parent_id')->flatMap(function (ProductCategory $parent) use ($all) {
            $children = $all->where('parent_id', $parent->id)
                ->map(fn (ProductCategory $child) => [
                    'id' => $child->id,
                    'label' => '— '.$child->name,
                ]);

            return collect([['id' => $parent->id, 'label' => $parent->name]])->concat($children);
        })->values();
    }

    /**
     * Counters for the cards above the list. Four cheap aggregates rather than
     * loading the table and counting in PHP.
     *
     * @return array<string, int>
     */
    private function summary(): array
    {
        return [
            'total' => Product::query()->count(),
            'active' => Product::query()->where('status', 'active')->count(),
            'draft' => Product::query()->where('status', 'draft')->count(),
            'low_stock' => Product::query()
                ->where('track_inventory', true)
                ->where('stock_quantity', '>', 0)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                ->count(),
        ];
    }
}
