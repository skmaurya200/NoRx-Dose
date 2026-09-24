<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\PageContent;
use App\Support\Content\PageSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Editable copy on the storefront's fixed pages.
 *
 * The property worth guarding above all others: editing is additive. A page
 * nobody has touched renders exactly as it was designed, and clearing a field
 * puts the original wording back rather than leaving a hole.
 */
class PageContentTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->admin = Admin::factory()->create();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * Posts a save the way the generated form does.
     *
     * The tests are written in schema paths because those are readable; the
     * form sends "hero__tag", so the keys are translated here rather than
     * spelled out at every call site.
     *
     * @param  array<string, mixed>  $payload
     */
    private function save(string $page, array $payload)
    {
        foreach (['fields', 'images', 'repeaters'] as $group) {
            if (! isset($payload[$group])) {
                continue;
            }

            $translated = [];

            foreach ($payload[$group] as $path => $value) {
                $translated[PageSchema::inputName($path)] = $value;
            }

            $payload[$group] = $translated;
        }

        return $this->asAdmin()->post('/api/manager/pages/'.$page, $payload);
    }

    /* ---------------------------------------------------------------- auth */

    public function test_the_screens_and_the_endpoint_are_closed_to_guests(): void
    {
        $this->get('/manager/pages')->assertRedirect(route('manager.login'));
        $this->postJson('/api/manager/pages/home', [])->assertUnauthorized();
    }

    /* -------------------------------------------------------------- screens */

    public function test_the_list_shows_every_page_in_the_schema(): void
    {
        $response = $this->asAdmin()->get('/manager/pages')->assertOk();

        foreach (PageSchema::all() as $page) {
            $response->assertSee($page['name']);
        }
    }

    public function test_a_page_form_is_built_from_the_schema(): void
    {
        $this->asAdmin()
            ->get('/manager/pages/home')
            ->assertOk()
            ->assertSee('Hero')
            ->assertSee('What we stand for')
            ->assertSee('name="fields[hero__title]"', false)
            ->assertSee('name="images[hero__background]"', false);
    }

    public function test_the_shop_form_saves_when_its_image_remove_input_is_untouched(): void
    {
        $this->save('shop', [
            'fields' => ['newsletter.title' => 'Fresh arrivals first'],
            'remove_images' => [''],
        ])->assertOk();

        $this->assertDatabaseHas('tbl_page_contents', [
            'page_key' => 'shop',
            'section_key' => 'newsletter',
            'field_key' => 'title',
            'value' => 'Fresh arrivals first',
        ]);
    }

    public function test_a_page_outside_the_schema_is_a_404(): void
    {
        $this->asAdmin()->get('/manager/pages/nope')->assertNotFound();
    }

    /**
     * The original wording is the placeholder, not the value: an operator has
     * to be able to see what they are replacing.
     */
    public function test_untouched_fields_show_the_original_copy_as_a_placeholder(): void
    {
        $this->asAdmin()
            ->get('/manager/pages/home')
            ->assertOk()
            ->assertSee('placeholder="Formulated with care"', false);
    }

    /* -------------------------------------------------------------- storefront */

    /**
     * The whole point of the defaults: nothing saved, nothing changed.
     */
    public function test_a_page_with_nothing_saved_renders_its_original_copy(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Formulated with care')
            ->assertSee('Modern science.<br><em>Bespoke</em> wellness.', false)
            ->assertSee('What we stand for')
            ->assertSee('Expert-led care,<br>personally tailored.', false);
    }

    public function test_saved_copy_replaces_the_original_on_the_page(): void
    {
        $this->save('home', ['fields' => [
            'hero.tag' => 'Made in small batches',
            'hero.subtitle' => 'A shorter promise.',
        ]])->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSee('Made in small batches')
            ->assertSee('A shorter promise.')
            ->assertDontSee('Formulated with care');
    }

    public function test_the_section_below_the_hero_is_editable_too(): void
    {
        $this->save('home', ['fields' => [
            'about.eyebrow' => 'Why we exist',
            'about.card_one_title' => 'Checked twice',
            'about.badge_label' => 'Independently tested',
        ]])->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSee('Why we exist')
            ->assertSee('Checked twice')
            ->assertSee('Independently tested')
            ->assertDontSee('What we stand for');
    }

    /**
     * Clearing a box is how an operator undoes an edit, so it has to restore
     * the design's own words rather than blank the page.
     */
    public function test_clearing_a_field_restores_the_original_copy(): void
    {
        $this->save('home', ['fields' => ['hero.tag' => 'Temporary']])->assertOk();
        $this->get('/')->assertSee('Temporary');

        $this->save('home', ['fields' => ['hero.tag' => '']])->assertOk();

        $this->get('/')->assertOk()->assertSee('Formulated with care');
        $this->assertDatabaseMissing('tbl_page_contents', [
            'page_key' => 'home', 'section_key' => 'hero', 'field_key' => 'tag',
        ]);
    }

    /**
     * A form that only carries one section must not wipe the others.
     */
    public function test_a_field_the_form_did_not_send_is_left_alone(): void
    {
        $this->save('home', ['fields' => ['hero.tag' => 'Kept']])->assertOk();
        $this->save('home', ['fields' => ['about.eyebrow' => 'Also kept']])->assertOk();

        $this->get('/')->assertOk()->assertSee('Kept')->assertSee('Also kept');
    }

    /* ---------------------------------------------------------------- headings */

    /**
     * The italic word is a design element, so <em> survives - and nothing else
     * does, because a heading is the largest piece of type on the page.
     */
    public function test_a_heading_keeps_its_inline_emphasis(): void
    {
        $this->save('home', ['fields' => [
            'hero.title' => 'Real <em>science</em>.<br>Real results.',
        ]])->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSee('Real <em>science</em>.<br>Real results.', false);
    }

    public function test_a_heading_cannot_smuggle_markup_onto_the_page(): void
    {
        $this->save('home', ['fields' => [
            'hero.title' => '<script>alert(1)</script><div class="wrecked">Plain <em>words</em></div>',
        ]])->assertOk();

        $response = $this->get('/')->assertOk();

        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertDontSee('wrecked');
        $response->assertSee('Plain <em>words</em>', false);
    }

    /**
     * Everything that is not a heading is stored as text and escaped on the
     * way out, so a stray angle bracket is printed rather than parsed.
     */
    public function test_plain_fields_are_stored_without_markup(): void
    {
        $this->save('home', ['fields' => [
            'hero.tag' => '<b>Bold</b> claim',
        ]])->assertOk();

        $this->assertDatabaseHas('tbl_page_contents', [
            'page_key' => 'home', 'section_key' => 'hero', 'field_key' => 'tag',
            'value' => 'Bold claim',
        ]);
    }

    /* ------------------------------------------------------------------ images */

    /**
     * With nothing uploaded the hero shows the drawn default; an upload takes
     * its place. There is no empty state - a page always has a picture.
     */
    public function test_a_hero_image_replaces_the_default(): void
    {
        $this->get('/')->assertOk()->assertSee('images/defaults/hero.svg', false);

        $this->save('home', [
            'images' => ['hero.background' => UploadedFile::fake()->image('hero.jpg', 1600, 900)],
        ])->assertOk();

        $path = PageContent::query()
            ->where(['page_key' => 'home', 'section_key' => 'hero', 'field_key' => 'background'])
            ->value('value');

        $this->assertNotNull($path);
        $this->assertFileExists(public_path($path));

        $this->get('/')
            ->assertOk()
            ->assertSee(asset($path), false)
            ->assertDontSee('images/defaults/hero.svg', false);

        @unlink(public_path($path));
    }

    public function test_an_image_can_be_removed_and_the_default_returns(): void
    {
        $this->save('home', [
            'images' => ['hero.background' => UploadedFile::fake()->image('hero.jpg', 1600, 900)],
        ])->assertOk();

        $this->save('home', ['remove_images' => ['hero.background']])->assertOk();

        $this->get('/')->assertOk()->assertSee('images/defaults/hero.svg', false);
    }

    public function test_a_non_image_upload_is_refused(): void
    {
        $this->save('home', [
            'images' => ['hero.background' => UploadedFile::fake()->create('x.php', 8, 'application/x-php')],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('images.hero__background');
    }

    /* ------------------------------------------------------------------ safety */

    /**
     * The form is generated from the schema, so a payload naming a field that
     * does not exist writes nothing at all.
     */
    public function test_a_field_outside_the_schema_is_ignored(): void
    {
        $this->save('home', ['fields' => [
            'hero.tag' => 'Real',
            'hero.invented' => 'Should not be stored',
            'nonsense.field' => 'Nor this',
        ]])->assertOk();

        // Only the one real field was written.

        $this->assertDatabaseCount('tbl_page_contents', 1);
        $this->assertDatabaseMissing('tbl_page_contents', ['field_key' => 'invented']);
    }

    public function test_saving_an_unknown_page_is_a_404(): void
    {
        $this->asAdmin()->postJson('/api/manager/pages/nope', ['fields' => []])->assertNotFound();
    }

    /* -------------------------------------------------------------- other pages */

    public function test_every_other_page_is_editable_too(): void
    {
        $pages = [
            'about' => ['/about', 'Our story and mission'],
            'faq' => ['/faq', 'Help centre'],
            'reviews' => ['/reviews', 'Real customers, real stories'],
            'shipping-policy' => ['/shipping-policy', 'Shipping policy'],
        ];

        foreach ($pages as $key => [$url, $original]) {
            $this->get($url)->assertOk()->assertSee($original);

            $this->save($key, ['fields' => ['hero.badge' => 'Edited '.$key]])->assertOk();

            // Not assertDontSee: some of these pages carry the same words in
            // their <title>, which is not part of the editable hero.
            $this->get($url)->assertOk()->assertSee('Edited '.$key);
        }
    }

    public function test_the_journal_heading_is_editable(): void
    {
        $this->save('blogs', ['fields' => [
            'hero.title' => 'Our <em>notebook</em>',
            'hero.eyebrow' => 'Longer reads',
        ]])->assertOk();

        $this->get('/blogs')
            ->assertOk()
            ->assertSee('Our <em>notebook</em>', false)
            ->assertSee('Longer reads');
    }

    /* ----------------------------------------------------------------- tabs */

    /**
     * One tab per section of the page, built from the schema.
     */
    public function test_the_form_has_a_tab_for_every_section(): void
    {
        $response = $this->asAdmin()->get('/manager/pages/home')->assertOk();

        foreach (PageSchema::page('home')['sections'] as $key => $section) {
            $response->assertSee('data-tab-target="tab-'.$key.'"', false);
            $response->assertSee('id="tab-'.$key.'"', false);
            $response->assertSee($section['name']);
        }
    }

    /**
     * Every tab is in the DOM and posts together, so an operator can edit
     * several before pressing Save once.
     */
    public function test_all_tabs_save_in_one_request(): void
    {
        $this->save('home', ['fields' => [
            'hero.tag' => 'From the hero tab',
            'stats.one_label' => 'From the stats tab',
            'cta.tag' => 'From the CTA tab',
        ]])->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSee('From the hero tab')
            ->assertSee('From the stats tab')
            ->assertSee('From the CTA tab');
    }

    /* ------------------------------------------------------- the new sections */

    public function test_the_stats_counters_are_editable(): void
    {
        $this->save('home', ['fields' => [
            'stats.one_value' => '2400',
            'stats.one_label' => 'Batches released',
            'stats.two_suffix' => 'x',
        ]])->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSee('data-count="2400"', false)
            ->assertSee('Batches released')
            ->assertSee('data-suffix="x"', false)
            ->assertDontSee('Formulas tested');
    }

    /**
     * The comma grouping belongs to the design, so it stays put whatever the
     * figure is changed to.
     */
    public function test_the_stats_keep_their_design_details(): void
    {
        $this->save('home', ['fields' => ['stats.one_value' => '999']])->assertOk();

        $this->get('/')->assertOk()->assertSee('data-comma', false);
    }

    public function test_the_research_band_is_editable(): void
    {
        $this->save('home', ['fields' => [
            'band.eyebrow' => 'Straight from the lab',
            'band.chip' => 'Bench notes',
            'band.title' => 'Two new formulas,<br>out now.',
            'band.item_one' => 'Now in glass',
            'band.button_label' => 'See them',
            'band.button_link' => '/shop',
        ]])->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSee('Straight from the lab')
            ->assertSee('Bench notes')
            ->assertSee('Two new formulas,<br>out now.', false)
            ->assertSee('Now in glass')
            ->assertSee('See them')
            ->assertDontSee('Fresh from research');
    }

    /**
     * A bullet cleared in the panel drops out rather than leaving an empty
     * marker on the page.
     */
    public function test_an_emptied_band_bullet_disappears(): void
    {
        $this->get('/')->assertOk()->assertSee('Refill pouches for every size');

        $this->save('home', ['fields' => ['band.item_three' => '']])->assertOk();

        // Still the original, because clearing restores the default.
        $this->get('/')->assertOk()->assertSee('Refill pouches for every size');
    }

    public function test_the_band_image_replaces_its_default(): void
    {
        $this->get('/')->assertOk()->assertSee('images/defaults/lab.svg', false);

        $this->save('home', [
            'images' => ['band.image' => UploadedFile::fake()->image('band.jpg', 800, 600)],
        ])->assertOk();

        $path = PageContent::query()
            ->where(['page_key' => 'home', 'section_key' => 'band', 'field_key' => 'image'])
            ->value('value');

        $this->get('/')->assertOk()->assertSee(asset($path), false)->assertDontSee('images/defaults/lab.svg', false);

        @unlink(public_path($path));
    }

    public function test_the_closing_call_to_action_is_editable(): void
    {
        $this->save('home', ['fields' => [
            'cta.tag' => 'Begin now',
            'cta.title' => 'Ready when <em>you are?</em>',
            'cta.primary_label' => 'Browse everything',
            'cta.secondary_link' => '/faq',
        ]])->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSee('Begin now')
            ->assertSee('Ready when <em>you are?</em>', false)
            ->assertSee('Browse everything')
            ->assertDontSee('Start today');
    }

    /**
     * The rails keep their own contents - only the words above them move.
     */
    public function test_rail_headings_are_editable_while_their_contents_are_not(): void
    {
        $this->save('home', ['fields' => [
            'cats.title' => 'Find your range',
            'favourites.title' => 'Best sellers',
            'arrivals.eyebrow' => 'Hot off the line',
            'journal.link_label' => 'Read everything',
            'faq.title' => 'Before you ask',
        ]])->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSee('Find your range')
            ->assertSee('Best sellers')
            ->assertSee('Hot off the line')
            ->assertSee('Read everything')
            ->assertSee('Before you ask');
    }

    /* ------------------------------------------------------------- FAQ rows */

    /**
     * Unedited, the accordion shows the nine questions the page shipped with.
     */
    public function test_the_faq_falls_back_to_the_questions_the_page_shipped_with(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('Do I need an account to order?');
        $response->assertSee('Where do you deliver?');
        $this->assertSame(9, substr_count($response->getContent(), 'class="q"'));
    }

    public function test_the_faq_rows_can_be_replaced(): void
    {
        $this->save('home', ['repeaters' => ['faq.items' => [
            ['question' => 'Do you deliver on Sundays?', 'answer' => 'Not yet, but we are working on it.'],
            ['question' => 'Can I change my order?', 'answer' => 'Yes, until it is dispatched.'],
        ]]])->assertOk();

        $response = $this->get('/')->assertOk();

        $response->assertSee('Do you deliver on Sundays?');
        $response->assertSee('Not yet, but we are working on it.');
        $response->assertDontSee('Do I need an account to order?');
        $this->assertSame(2, substr_count($response->getContent(), 'class="q"'));
    }

    /**
     * The design draws however many rows there are, so adding one is enough.
     */
    public function test_a_question_can_be_added_beyond_the_original_nine(): void
    {
        $rows = collect(range(1, 12))
            ->map(fn (int $n) => ['question' => 'Question '.$n, 'answer' => 'Answer '.$n])
            ->all();

        $this->save('home', ['repeaters' => ['faq.items' => $rows]])->assertOk();

        $response = $this->get('/')->assertOk();

        $this->assertSame(12, substr_count($response->getContent(), 'class="q"'));
        $response->assertSee('Question 12');
    }

    /**
     * The order in the payload is the order on the page - that is what the
     * move-up and move-down buttons rely on.
     */
    public function test_the_row_order_is_kept(): void
    {
        $this->save('home', ['repeaters' => ['faq.items' => [
            ['question' => 'Second in the list', 'answer' => 'B'],
            ['question' => 'First in the list', 'answer' => 'A'],
        ]]])->assertOk();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'First in the list'),
            strpos($html, 'Second in the list'),
        );
    }

    /**
     * A row with no question is not a row anyone meant to add.
     */
    public function test_a_row_with_no_question_is_dropped(): void
    {
        $this->save('home', ['repeaters' => ['faq.items' => [
            ['question' => 'A real one', 'answer' => 'Yes.'],
            ['question' => '', 'answer' => 'An answer to nothing.'],
            ['question' => '   ', 'answer' => ''],
        ]]])->assertOk();

        $response = $this->get('/')->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), 'class="q"'));
        $response->assertDontSee('An answer to nothing.');
    }

    public function test_clearing_every_row_restores_the_original_questions(): void
    {
        $this->save('home', ['repeaters' => ['faq.items' => [
            ['question' => 'Only mine', 'answer' => 'Yes.'],
        ]]])->assertOk();

        $this->get('/')->assertSee('Only mine');

        $this->save('home', ['repeaters' => ['faq.items' => []]])->assertOk();

        $this->get('/')->assertOk()->assertSee('Do I need an account to order?');
    }

    public function test_a_question_cannot_carry_markup_onto_the_page(): void
    {
        $this->save('home', ['repeaters' => ['faq.items' => [
            ['question' => '<script>alert(1)</script>Safe?', 'answer' => '<b>Yes</b>'],
        ]]])->assertOk();

        $response = $this->get('/')->assertOk();

        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('Safe?');
        // Stored as text and escaped on output, so the tag is printed, not run.
        $response->assertDontSee('<b>Yes</b>', false);
    }

    public function test_the_number_of_rows_is_capped(): void
    {
        $rows = collect(range(1, 40))
            ->map(fn (int $n) => ['question' => 'Q'.$n, 'answer' => 'A'.$n])
            ->all();

        $this->save('home', ['repeaters' => ['faq.items' => $rows]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('repeaters.faq__items');
    }

    public function test_the_form_renders_the_repeater_with_its_rows(): void
    {
        $this->asAdmin()
            ->get('/manager/pages/home')
            ->assertOk()
            ->assertSee('data-content-repeater', false)
            ->assertSee('name="repeaters[faq__items][0][question]"', false)
            ->assertSee('data-repeat-template', false)
            ->assertSee('Do I need an account to order?');
    }

    /* --------------------------------------------------------------- sidebar */

    /**
     * The submenu is built from the schema, so it lists whatever the module
     * covers without anyone maintaining a second list.
     */
    public function test_the_sidebar_lists_every_page_as_a_submenu_item(): void
    {
        $response = $this->asAdmin()->get('/manager/pages')->assertOk();

        foreach (PageSchema::keys() as $key) {
            $response->assertSee(route('manager.pages.edit', $key), false);
        }
    }

    /* ------------------------------------------------------------------ seo */

    /**
     * Every page gets a Search section whether its own entry mentions one or
     * not, which is what stops a page being added to the schema and then
     * quietly having no meta title.
     */
    public function test_every_page_has_a_search_section(): void
    {
        foreach (PageSchema::all() as $key => $page) {
            $this->assertArrayHasKey('seo', $page['sections'], $key.' should have a Search section');
        }
    }

    public function test_a_page_meta_title_and_description_reach_the_head(): void
    {
        $this->save('about', [
            'fields' => [
                'seo.title' => 'How we make things',
                'seo.description' => 'A short account of who we are and what we put in the bottle.',
            ],
        ])->assertOk();

        $this->get('/about')
            ->assertOk()
            ->assertSee('<title>How we make things', false)
            ->assertSee('A short account of who we are and what we put in the bottle.', false);
    }

    /**
     * Blank is the default, and blank means "use the site defaults" - so a
     * page nobody has edited still has a title and a description.
     */
    public function test_a_page_with_no_meta_of_its_own_falls_back_to_the_site_defaults(): void
    {
        $this->get('/about')
            ->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('Batch-tested supplements and skincare', false);
    }

    /* ------------------------------------------------------------- coverage */

    /**
     * Every page the schema names has to actually render. A section wired to
     * a field that does not exist would only show up as a broken page.
     */
    public function test_every_page_in_the_schema_renders(): void
    {
        foreach (PageSchema::all() as $key => $page) {
            $this->get(route($page['route']))
                ->assertOk()
                ->assertDontSee('Undefined', false);
        }
    }
}
