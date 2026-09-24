<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A small, realistic catalogue so the panel has something to render before the
 * first real product is entered.
 *
 * Everything is keyed by slug through updateOrCreate, so running the seeder
 * twice updates the same rows instead of building a second copy of the shop.
 */
class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalogue() as $group) {
            $category = ProductCategory::updateOrCreate(
                ['slug' => Str::slug($group['name'])],
                [
                    'name' => $group['name'],
                    'description' => $group['description'],
                    'sort_order' => $group['sort_order'],
                    'is_active' => true,
                    'is_featured' => $group['featured'],
                ],
            );

            foreach ($group['products'] as $item) {
                $product = Product::updateOrCreate(
                    ['slug' => Str::slug($item['name'])],
                    [
                        'category_id' => $category->id,
                        'name' => $item['name'],
                        'sku' => $item['sku'],
                        'brand' => 'NoRx Dose',
                        'short_description' => $item['short'],
                        'description' => $item['description'],
                        'price' => $item['price'],
                        'compare_at_price' => $item['compare'],
                        'cost_price' => round($item['price'] * 0.42, 2),
                        'currency' => 'USD',
                        'unit' => $item['unit'],
                        'weight_grams' => $item['weight'],
                        'track_inventory' => true,
                        'stock_quantity' => $item['stock'],
                        'low_stock_threshold' => 10,
                        'status' => 'active',
                        'is_featured' => $item['featured'],
                        'published_at' => now(),
                    ],
                );

                $product->detail()->updateOrCreate(
                    ['product_id' => $product->id],
                    $item['detail'],
                );

                // Pack sizes drive the "Select size" chooser. The first one is
                // the default and sets the price shown on listing cards, which
                // is why it matches $item['price'].
                foreach ($item['packs'] as $position => $pack) {
                    $product->packs()->updateOrCreate(
                        ['label' => $pack['label']],
                        [
                            'price' => $pack['price'],
                            'compare_at_price' => $pack['compare'],
                            'stock_quantity' => $pack['stock'],
                            'is_best_value' => $pack['best'] ?? false,
                            'sort_order' => $position + 1,
                        ],
                    );
                }
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalogue(): array
    {
        return [
            [
                'name' => 'Sleep & Recovery',
                'description' => 'Formulas that help the body wind down and repair overnight.',
                'sort_order' => 1,
                'featured' => true,
                'products' => [
                    [
                        'name' => 'Calm Magnesium Complex',
                        'sku' => 'AW-MAG-60',
                        'price' => 42.00,
                        'compare' => 52.00,
                        'unit' => '60 capsules',
                        'weight' => 180,
                        'stock' => 124,
                        'featured' => true,
                        'short' => 'Three forms of magnesium to support restful sleep and muscle recovery.',
                        'description' => "A blend of magnesium glycinate, citrate and malate, chosen because each is absorbed differently and together they cover more ground than any one form alone.\n\nMost people notice the difference within the first week.",
                        'detail' => [
                            'ingredients' => "Magnesium glycinate 200 mg\nMagnesium citrate 100 mg\nMagnesium malate 100 mg\nVegetable cellulose capsule",
                            'benefits' => "Supports normal muscle function\nHelps reduce tiredness and fatigue\nContributes to normal psychological function",
                            'how_to_use' => 'Two capsules with water, 30 to 60 minutes before bed.',
                            'storage' => 'Keep in a cool, dry place away from direct sunlight.',
                            'warnings' => 'Not suitable if you are pregnant or breastfeeding without medical advice. Do not exceed the stated dose.',
                            'country_of_origin' => 'United Kingdom',
                            'manufacturer' => 'NoRx Dose Labs',
                            'shelf_life_months' => 24,
                            'is_vegetarian' => true,
                            'is_gluten_free' => true,
                            'specifications' => [
                                ['label' => 'Serving size', 'value' => '2 capsules'],
                                ['label' => 'Servings per pack', 'value' => '30'],
                                ['label' => 'Elemental magnesium', 'value' => '400 mg per serving'],
                            ],
                        ],
                        'packs' => [
                            ['label' => '30 count', 'price' => 42.00, 'compare' => 52.00, 'stock' => 124],
                            ['label' => '60 count', 'price' => 76.00, 'compare' => 99.00, 'stock' => 62, 'best' => true],
                            ['label' => '90 count', 'price' => 108.00, 'compare' => 148.00, 'stock' => 41],
                            ['label' => '120 count', 'price' => 136.00, 'compare' => 196.00, 'stock' => 28],
                            ['label' => '180 count', 'price' => 192.00, 'compare' => 292.00, 'stock' => 15],
                        ],
                    ],
                    [
                        'name' => 'Night Recovery Tea',
                        'sku' => 'AW-TEA-30',
                        'price' => 19.00,
                        'compare' => 24.00,
                        'unit' => '30 sachets',
                        'weight' => 90,
                        'stock' => 6,
                        'featured' => false,
                        'short' => 'Chamomile, lemon balm and passionflower for the hour before bed.',
                        'description' => 'A caffeine-free infusion built around chamomile, with lemon balm and passionflower for a rounder, less floral cup than chamomile on its own.',
                        'detail' => [
                            'ingredients' => "Chamomile flowers\nLemon balm leaf\nPassionflower\nLavender",
                            'benefits' => "Caffeine free\nHelps you settle before bed",
                            'how_to_use' => 'One sachet in freshly boiled water. Steep for five minutes.',
                            'storage' => 'Reseal after opening and keep dry.',
                            'warnings' => 'Avoid if you are allergic to plants of the daisy family.',
                            'country_of_origin' => 'United Kingdom',
                            'manufacturer' => 'NoRx Dose Labs',
                            'shelf_life_months' => 18,
                            'is_vegetarian' => true,
                            'is_gluten_free' => true,
                            'specifications' => [
                                ['label' => 'Sachets per pack', 'value' => '30'],
                                ['label' => 'Caffeine', 'value' => 'None'],
                            ],
                        ],
                        'packs' => [
                            ['label' => '30 sachets', 'price' => 19.00, 'compare' => 24.00, 'stock' => 6],
                            ['label' => '60 sachets', 'price' => 34.00, 'compare' => 46.00, 'stock' => 22, 'best' => true],
                            ['label' => '120 sachets', 'price' => 62.00, 'compare' => 90.00, 'stock' => 11],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Daily Essentials',
                'description' => 'The everyday foundations - greens, omegas and vitamins.',
                'sort_order' => 2,
                'featured' => true,
                'products' => [
                    [
                        'name' => 'Daily Greens Blend',
                        'sku' => 'AW-GRN-250',
                        'price' => 28.50,
                        'compare' => 35.00,
                        'unit' => '250 g',
                        'weight' => 300,
                        'stock' => 88,
                        'featured' => true,
                        'short' => 'Twelve greens and a digestive enzyme blend in one scoop.',
                        'description' => 'Spirulina, chlorella, wheatgrass and nine more, with a small enzyme blend so the powder sits more comfortably than greens usually do.',
                        'detail' => [
                            'ingredients' => "Spirulina\nChlorella\nWheatgrass\nBarley grass\nKale\nSpinach\nDigestive enzyme blend",
                            'benefits' => "A convenient daily serving of greens\nSupports normal energy-yielding metabolism",
                            'how_to_use' => 'One scoop in water or a smoothie, once a day.',
                            'storage' => 'Keep the lid tightly closed. Use within 90 days of opening.',
                            'warnings' => 'Contains barley and wheatgrass. Not suitable for anyone avoiding gluten.',
                            'country_of_origin' => 'United Kingdom',
                            'manufacturer' => 'NoRx Dose Labs',
                            'shelf_life_months' => 18,
                            'is_vegetarian' => true,
                            'is_gluten_free' => false,
                            'specifications' => [
                                ['label' => 'Serving size', 'value' => '1 scoop (8 g)'],
                                ['label' => 'Servings per pack', 'value' => '31'],
                            ],
                        ],
                        'packs' => [
                            ['label' => '250 g', 'price' => 28.50, 'compare' => 35.00, 'stock' => 88],
                            ['label' => '500 g', 'price' => 52.00, 'compare' => 68.00, 'stock' => 34, 'best' => true],
                            ['label' => '1 kg', 'price' => 96.00, 'compare' => 132.00, 'stock' => 12],
                        ],
                    ],
                    [
                        'name' => 'Omega-3 Fish Oil',
                        'sku' => 'AW-OMG-90',
                        'price' => 35.00,
                        'compare' => 42.00,
                        'unit' => '90 softgels',
                        'weight' => 210,
                        'stock' => 13,
                        'featured' => false,
                        'short' => 'High-strength EPA and DHA from wild-caught sardines and anchovies.',
                        'description' => 'Molecularly distilled to remove heavy metals, and third-party tested for oxidation on every batch.',
                        'detail' => [
                            'ingredients' => "Fish oil concentrate 1000 mg\nEPA 360 mg\nDHA 240 mg\nNatural vitamin E",
                            'benefits' => "EPA and DHA contribute to normal heart function\nDHA contributes to normal brain function",
                            'how_to_use' => 'One to three softgels daily with food.',
                            'storage' => 'Refrigerate after opening.',
                            'warnings' => 'Contains fish. Speak to your doctor first if you take anticoagulants.',
                            'country_of_origin' => 'Norway',
                            'manufacturer' => 'NoRx Dose Labs',
                            'shelf_life_months' => 24,
                            'is_vegetarian' => false,
                            'is_gluten_free' => true,
                            'specifications' => [
                                ['label' => 'EPA per softgel', 'value' => '360 mg'],
                                ['label' => 'DHA per softgel', 'value' => '240 mg'],
                                ['label' => 'Source', 'value' => 'Wild sardine and anchovy'],
                            ],
                        ],
                        'packs' => [
                            ['label' => '90 softgels', 'price' => 35.00, 'compare' => 42.00, 'stock' => 13],
                            ['label' => '180 softgels', 'price' => 64.00, 'compare' => 82.00, 'stock' => 27, 'best' => true],
                            ['label' => '270 softgels', 'price' => 90.00, 'compare' => 122.00, 'stock' => 9],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Beauty & Skin',
                'description' => 'Collagen and antioxidants for skin, hair and nails.',
                'sort_order' => 3,
                'featured' => false,
                'products' => [
                    [
                        'name' => 'Collagen Beauty Boost',
                        'sku' => 'AW-COL-300',
                        'price' => 56.00,
                        'compare' => 68.00,
                        'unit' => '300 g',
                        'weight' => 360,
                        'stock' => 9,
                        'featured' => true,
                        'short' => 'Marine collagen peptides with vitamin C and hyaluronic acid.',
                        'description' => 'Hydrolysed to a low molecular weight so it dissolves clear in cold water, with the vitamin C that collagen synthesis actually depends on.',
                        'detail' => [
                            'ingredients' => "Marine collagen peptides 10 g\nVitamin C 80 mg\nHyaluronic acid 100 mg\nBiotin",
                            'benefits' => "Vitamin C contributes to normal collagen formation\nBiotin contributes to the maintenance of normal skin and hair",
                            'how_to_use' => 'One scoop in water, juice or coffee, once daily.',
                            'storage' => 'Keep dry and reseal after use.',
                            'warnings' => 'Contains fish. Not suitable for vegetarians or vegans.',
                            'country_of_origin' => 'France',
                            'manufacturer' => 'NoRx Dose Labs',
                            'shelf_life_months' => 24,
                            'is_vegetarian' => false,
                            'is_gluten_free' => true,
                            'specifications' => [
                                ['label' => 'Collagen per serving', 'value' => '10 g'],
                                ['label' => 'Servings per pack', 'value' => '30'],
                                ['label' => 'Source', 'value' => 'Wild-caught marine'],
                            ],
                        ],
                        'packs' => [
                            ['label' => '300 g', 'price' => 56.00, 'compare' => 68.00, 'stock' => 9],
                            ['label' => '600 g', 'price' => 102.00, 'compare' => 132.00, 'stock' => 19, 'best' => true],
                        ],
                    ],
                ],
            ],
        ];
    }
}
