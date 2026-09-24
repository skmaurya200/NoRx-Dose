<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Images uploaded into a post body from the editor's toolbar.
 */
class BlogMediaTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/manager/blog/uploads';

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    public function test_uploading_is_closed_to_guests(): void
    {
        $this->postJson(self::ENDPOINT, ['image' => UploadedFile::fake()->image('x.jpg')])
            ->assertUnauthorized();
    }

    public function test_an_image_is_stored_and_its_address_returned(): void
    {
        $response = $this->asAdmin()
            ->post(self::ENDPOINT, [
                'image' => UploadedFile::fake()->image('inline.jpg', 900, 600),
                'alt' => 'A row of amber bottles',
            ])
            ->assertStatus(201);

        $path = $response->json('data.path');

        $this->assertNotEmpty($path);
        $this->assertFileExists(public_path($path));
        $this->assertSame('A row of amber bottles', $response->json('data.alt'));

        // Root-relative: a post outlives the domain it was written on, and a
        // hard-coded host in stored markup breaks on the first move.
        $this->assertStringStartsWith('/uploads/blog/', $response->json('data.url'));

        @unlink(public_path($path));
    }

    public function test_the_stored_name_is_generated_not_taken_from_the_upload(): void
    {
        $response = $this->asAdmin()
            ->post(self::ENDPOINT, [
                'image' => UploadedFile::fake()->image('../../evil shell.jpg', 400, 400),
            ])
            ->assertStatus(201);

        $path = $response->json('data.path');

        $this->assertStringNotContainsString('evil', $path);
        $this->assertStringNotContainsString('..', $path);
        $this->assertStringStartsWith('uploads/blog/', $path);

        @unlink(public_path($path));
    }

    public function test_a_non_image_is_refused(): void
    {
        $this->asAdmin()
            ->post(self::ENDPOINT, [
                'image' => UploadedFile::fake()->create('payload.php', 8, 'application/x-php'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_an_image_is_required(): void
    {
        $this->asAdmin()->postJson(self::ENDPOINT, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }
}
