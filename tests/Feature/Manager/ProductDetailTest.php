<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Support\PublicUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductDetailTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Product $product;

    /** @var array<int, string> */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => ProductCategory::factory(),
        ]);
    }

    /**
     * These tests write real files into public/uploads, because that is the
     * whole point of the convention. Every one of them is removed again here.
     */
    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function endpoint(string $suffix = ''): string
    {
        return '/api/manager/products/'.$this->product->id.$suffix;
    }

    private function uploadOne(): ProductImage
    {
        $response = $this->asAdmin()->post($this->endpoint('/images'), [
            'images' => [UploadedFile::fake()->image('shot.jpg', 500, 500)],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);

        // Taken from the response rather than re-queried: the images relation
        // orders by sort_order, so a "latest" query here would not reliably
        // return the row this call just created.
        $image = ProductImage::findOrFail($response->json('data.0.id'));
        $this->written[] = public_path($image->image_path);

        return $image;
    }

    /**
     * image_path is deliberately not fillable - only the service may set it -
     * so a fixture row has to assign it directly.
     */
    private function fixtureImage(Product $product, string $path, int $sort = 1): ProductImage
    {
        $image = new ProductImage(['sort_order' => $sort]);
        $image->product_id = $product->id;
        $image->image_path = $path;
        $image->save();

        return $image;
    }

    /* ------------------------------------------------------------- access */

    public function test_a_guest_cannot_reach_the_detail_api(): void
    {
        $this->getJson($this->endpoint('/detail'))->assertStatus(401);
        $this->postJson($this->endpoint('/images'), [])->assertStatus(401);
    }

    /* ------------------------------------------------------------- reading */

    public function test_an_admin_can_read_the_detail_and_gallery(): void
    {
        $this->product->detail()->create([
            'ingredients' => 'Magnesium glycinate',
            'specifications' => [['label' => 'Serving', 'value' => '2 capsules']],
        ]);

        $this->asAdmin()
            ->getJson($this->endpoint('/detail'))
            ->assertOk()
            ->assertJsonPath('data.detail.ingredients', 'Magnesium glycinate')
            ->assertJsonPath('data.detail.specifications.0.label', 'Serving')
            ->assertJsonPath('data.images', []);
    }

    /* ------------------------------------------------------------ uploads */

    public function test_an_image_is_written_into_the_public_folder(): void
    {
        $image = $this->uploadOne();

        $this->assertStringStartsWith('uploads/products/', $image->image_path);
        $this->assertFileExists(public_path($image->image_path));

        // The stored name is generated, never taken from the upload - that is
        // what removes "../", ".php" and null bytes in one move.
        $this->assertStringNotContainsString('shot', $image->image_path);
    }

    public function test_the_first_image_becomes_the_primary_one(): void
    {
        $this->assertTrue($this->uploadOne()->is_primary);
    }

    public function test_a_second_image_does_not_steal_primary(): void
    {
        $first = $this->uploadOne();
        $second = $this->uploadOne();

        $this->assertTrue($first->fresh()->is_primary);
        $this->assertFalse($second->fresh()->is_primary);
    }

    public function test_a_disguised_script_is_rejected(): void
    {
        // A .php file renamed to .jpg with an image content type - the mime
        // sniff and the getimagesize check are what stop it.
        $file = UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo "pwned";');

        $this->asAdmin()->post($this->endpoint('/images'), [
            'images' => [$file],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(0, $this->product->images()->count());
    }

    public function test_the_gallery_ceiling_is_enforced(): void
    {
        $limit = (int) config('admin.catalogue.max_gallery_images');

        // Fill it right up to the limit without going through the API.
        for ($i = 0; $i < $limit; $i++) {
            $this->fixtureImage($this->product, 'uploads/products/placeholder-'.$i.'.jpg', $i);
        }

        $this->asAdmin()->post($this->endpoint('/images'), [
            'images' => [UploadedFile::fake()->image('one-too-many.jpg')],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame($limit, $this->product->images()->count());
    }

    /* ------------------------------------------------------------- primary */

    public function test_setting_a_primary_image_moves_the_flag_and_the_thumbnail(): void
    {
        $first = $this->uploadOne();
        $second = $this->uploadOne();

        $this->asAdmin()
            ->patchJson($this->endpoint('/images/'.$second->id.'/primary'))
            ->assertOk();

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);

        // The card thumbnail follows the primary image, so the listing and the
        // product page cannot disagree about which photo leads.
        $this->assertSame($second->image_path, $this->product->fresh()->thumbnail_path);
    }

    /* -------------------------------------------------------------- delete */

    public function test_deleting_an_image_removes_the_file_from_disk(): void
    {
        $image = $this->uploadOne();
        $absolute = public_path($image->image_path);

        $this->asAdmin()
            ->deleteJson($this->endpoint('/images/'.$image->id))
            ->assertOk();

        $this->assertDatabaseMissing('tbl_product_images', ['id' => $image->id]);
        $this->assertFileDoesNotExist($absolute);
    }

    public function test_deleting_the_primary_image_promotes_another(): void
    {
        $first = $this->uploadOne();
        $second = $this->uploadOne();

        $this->asAdmin()
            ->deleteJson($this->endpoint('/images/'.$first->id))
            ->assertOk();

        // A gallery must never be left without a lead image.
        $this->assertTrue($second->fresh()->is_primary);
    }

    /* ------------------------------------------------------------ scoping */

    public function test_an_image_belonging_to_another_product_cannot_be_touched(): void
    {
        $other = Product::factory()->create(['category_id' => ProductCategory::factory()]);
        $foreign = $this->fixtureImage($other, 'uploads/products/foreign.jpg');

        // scopeBindings() is what makes this a 404 rather than a cross-product
        // write.
        $this->asAdmin()
            ->deleteJson($this->endpoint('/images/'.$foreign->id))
            ->assertStatus(404);

        $this->assertDatabaseHas('tbl_product_images', ['id' => $foreign->id]);
    }

    /* ------------------------------------------------------------- reorder */

    public function test_the_gallery_can_be_reordered(): void
    {
        $first = $this->uploadOne();
        $second = $this->uploadOne();

        $this->asAdmin()
            ->patchJson($this->endpoint('/images/order'), [
                'order' => [$second->id, $first->id],
            ])
            ->assertOk();

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }

    public function test_reordering_rejects_an_image_from_another_product(): void
    {
        $mine = $this->uploadOne();

        $other = Product::factory()->create(['category_id' => ProductCategory::factory()]);
        $foreign = $this->fixtureImage($other, 'uploads/products/foreign.jpg');

        $this->asAdmin()
            ->patchJson($this->endpoint('/images/order'), [
                'order' => [$mine->id, $foreign->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order.1');
    }

    public function test_reordering_rejects_a_duplicated_id(): void
    {
        $image = $this->uploadOne();

        $this->asAdmin()
            ->patchJson($this->endpoint('/images/order'), [
                'order' => [$image->id, $image->id],
            ])
            ->assertStatus(422);
    }

    /* -------------------------------------------------------- upload guard */

    public function test_a_tampered_path_cannot_delete_a_file_outside_the_bucket(): void
    {
        $canary = public_path('uploads/products/../canary.txt');

        if (! is_dir(public_path('uploads/products'))) {
            mkdir(public_path('uploads/products'), 0755, true);
        }

        file_put_contents($canary, 'do not delete me');
        $this->written[] = $canary;

        // A doctored database value pointing outside the bucket must be
        // ignored, not followed.
        PublicUpload::delete('uploads/products/../canary.txt', 'products');

        $this->assertFileExists($canary);
    }
}
