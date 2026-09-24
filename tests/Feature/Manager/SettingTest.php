<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\Setting;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Site-wide settings, and the operator's own password.
 */
class SettingTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/manager/settings';

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create(['password' => Hash::make('correct-horse-9')]);

        // The bag is cached forever and forgotten on save; a test that writes
        // rows directly would otherwise read the previous test's cache.
        app(SettingService::class)->forget();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /* ---------------------------------------------------------------- auth */

    public function test_the_endpoints_are_closed_to_guests(): void
    {
        $this->get('/manager/settings')->assertRedirect(route('manager.login'));
        $this->postJson(self::ENDPOINT, [])->assertUnauthorized();
        $this->postJson(self::ENDPOINT.'/password', [])->assertUnauthorized();
    }

    public function test_the_screen_opens(): void
    {
        $this->asAdmin()
            ->get('/manager/settings')
            ->assertOk()
            ->assertSee('Brand name')
            ->assertSee('Change your password');
    }

    /* -------------------------------------------------------------- saving */

    public function test_the_brand_and_contact_details_are_saved_and_shown_on_the_storefront(): void
    {
        $this->asAdmin()->postJson(self::ENDPOINT, [
            'fields' => [
                'brand__name' => 'Northwind Apothecary',
                'brand__name_accent' => 'Apothecary',
                'contact__email' => 'hello@northwind.test',
                'contact__phone' => '+1 415 555 0132',
                'contact__address' => '2500 Mission Street, San Francisco',
            ],
        ])->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSee('Northwind Apothecary')
            ->assertSee('hello@northwind.test')
            ->assertSee('2500 Mission Street, San Francisco')
            ->assertDontSee('hello@example.com');
    }

    public function test_settings_save_when_image_remove_inputs_are_untouched(): void
    {
        $this->asAdmin()->post(self::ENDPOINT, [
            'fields' => ['brand__name' => 'Northwind Apothecary'],
            'remove_images' => ['', ''],
        ])->assertOk();

        $this->assertDatabaseHas('tbl_settings', [
            'group_key' => 'brand',
            'item_key' => 'name',
            'value' => 'Northwind Apothecary',
        ]);
    }

    /**
     * The point of storing only what has changed: clearing a field puts the
     * wording the site was built with back, rather than leaving a hole.
     */
    public function test_clearing_a_field_restores_the_original_wording(): void
    {
        $this->asAdmin()->postJson(self::ENDPOINT, [
            'fields' => ['brand__name' => 'Northwind'],
        ])->assertOk();

        $this->assertDatabaseHas('tbl_settings', ['group_key' => 'brand', 'item_key' => 'name']);

        $this->asAdmin()->postJson(self::ENDPOINT, [
            'fields' => ['brand__name' => ''],
        ])->assertOk();

        $this->assertDatabaseMissing('tbl_settings', ['group_key' => 'brand', 'item_key' => 'name']);

        $this->get('/')->assertOk()->assertSee('Aurum');
    }

    public function test_a_setting_the_schema_does_not_name_is_ignored(): void
    {
        $this->asAdmin()->postJson(self::ENDPOINT, [
            'fields' => ['brand__is_admin' => 'yes', 'nonsense' => 'x'],
        ])->assertOk();

        $this->assertSame(0, Setting::query()->count());
    }

    public function test_the_public_whatsapp_number_and_contact_page_are_saved_from_the_screen(): void
    {
        $this->asAdmin()->get('/manager/settings')->assertOk()->assertSee('WhatsApp number')->assertSee('Contact page URL');

        $this->asAdmin()->postJson(self::ENDPOINT, [
            'fields' => [
                'contact__whatsapp' => '+1 415 555 0199',
                'contact__page_url' => 'https://northwind.test/help',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('tbl_settings', ['group_key' => 'contact', 'item_key' => 'whatsapp', 'value' => '+1 415 555 0199']);
        $this->assertDatabaseHas('tbl_settings', ['group_key' => 'contact', 'item_key' => 'page_url', 'value' => 'https://northwind.test/help']);
    }

    public function test_the_whatsapp_number_and_contact_page_are_validated(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, ['fields' => [
                'contact__whatsapp' => 'call me maybe',
                'contact__page_url' => 'javascript:alert(1)',
            ]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.contact__whatsapp', 'fields.contact__page_url']);

        $this->assertSame(0, Setting::query()->count());
    }

    public function test_the_email_address_is_validated(): void
    {
        $this->asAdmin()
            ->postJson(self::ENDPOINT, ['fields' => ['contact__email' => 'not-an-address']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.contact__email']);
    }

    public function test_a_logo_is_uploaded_and_used_in_the_header(): void
    {
        $this->asAdmin()->postJson(self::ENDPOINT, [
            'images' => ['brand__logo' => UploadedFile::fake()->image('logo.png', 240, 60)],
        ])->assertOk();

        $stored = Setting::query()->where(['group_key' => 'brand', 'item_key' => 'logo'])->value('value');

        $this->assertNotNull($stored);
        $this->assertFileExists(public_path($stored));

        $this->get('/')->assertOk()->assertSee($stored, false);

        @unlink(public_path($stored));
    }

    /* ------------------------------------------------------------ password */

    public function test_the_password_is_changed(): void
    {
        $this->asAdmin()->postJson(self::ENDPOINT.'/password', [
            'current_password' => 'correct-horse-9',
            'password' => 'battery-staple-7',
            'password_confirmation' => 'battery-staple-7',
        ])->assertOk();

        $this->assertTrue(Hash::check('battery-staple-7', $this->admin->fresh()->password));
    }

    public function test_the_current_password_has_to_be_right(): void
    {
        $this->asAdmin()->postJson(self::ENDPOINT.'/password', [
            'current_password' => 'guessing',
            'password' => 'battery-staple-7',
            'password_confirmation' => 'battery-staple-7',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('correct-horse-9', $this->admin->fresh()->password));
    }

    public function test_a_weak_or_mistyped_new_password_is_refused(): void
    {
        $this->asAdmin()->postJson(self::ENDPOINT.'/password', [
            'current_password' => 'correct-horse-9',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
