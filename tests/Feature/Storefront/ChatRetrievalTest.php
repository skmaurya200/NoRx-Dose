<?php

namespace Tests\Feature\Storefront;

use App\Models\ChatEmbedding;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Chat\ChatIndexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakesChatProvider;
use Tests\TestCase;

/**
 * Answering from the store's own content.
 *
 * Two halves. The index turns products, pages and posts into vectors, which is
 * what lets a question reach a product that never uses the question's words.
 * The answering path then talks about what was retrieved and nothing else -
 * and reads price and stock from the product row rather than from the index,
 * so a stale vector can pick the wrong product but can never quote a price
 * that is not the one on the page.
 */
class ChatRetrievalTest extends TestCase
{
    use FakesChatProvider;
    use RefreshDatabase;

    private const GENERATE = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    private const EMBED = 'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-test:batchEmbedContents';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'test-private-key',
            'services.gemini.model' => 'gemini-2.5-flash',
            'services.gemini.ca_bundle' => null,
            'chat.requests_per_minute' => 100,
            'chat.ip_requests_per_minute' => 500,
            'chat.retrieval.embedding_model' => 'text-embedding-test',
            'chat.retrieval.dimensions' => 3,
        ]);

        Http::preventStrayRequests();
    }

    private function start(): string
    {
        $this->withSession(['chat_owner' => Str::random(64)]);

        return $this->postJson('/api/chat/session')->assertOk()->json('data.session_uuid');
    }

    private function sendMessage(string $session, string $message): TestResponse
    {
        return $this->postJson('/api/chat/message', [
            'session_uuid' => $session,
            'request_uuid' => (string) Str::uuid(),
            'message' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>|string  $output
     * @return array<string, mixed>
     */
    private function aiResponse(array|string $output): array
    {
        return [
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [['text' => is_string($output) ? $output : json_encode($output)]]],
            ]],
            'usageMetadata' => ['totalTokenCount' => 30],
        ];
    }

    /**
     * @param  list<list<float>>  $vectors
     * @return array<string, mixed>
     */
    private function embedResponse(array $vectors): array
    {
        return ['embeddings' => array_map(static fn (array $vector) => ['values' => $vector], $vectors)];
    }

    /**
     * An embedding fake that answers with one vector per record asked for.
     *
     * A fixed-size response would hide the very thing the service checks - a
     * provider whose reply does not line up with the request, which would pair
     * a record with somebody else's vector.
     */
    private function fakeEmbeddings(): void
    {
        Http::fake([self::EMBED => function ($request) {
            $count = count($request->data()['requests'] ?? []);

            // Sized from the configured dimensionality, the way the provider
            // honours outputDimensionality - so a test that changes the
            // setting exercises what a real model change would do.
            $length = (int) config('chat.retrieval.dimensions');

            return Http::response($this->embedResponse(
                array_fill(0, $count, array_fill(0, $length, 0.1)),
            ));
        }]);
    }

    /**
     * The payload of the last answering call, which is not always the last
     * request: a turn may be classified first.
     *
     * @return array<string, mixed>
     */
    private function lastAnswerPayload(): array
    {
        foreach (Http::recorded()->reverse() as $exchange) {
            $sent = json_decode($exchange[0]['contents'][0]['parts'][0]['text'], true);

            if (is_array($sent) && array_key_exists('earlier_turns', $sent)) {
                return $sent;
            }
        }

        $this->fail('No answering call was made.');
    }

    private function product(string $name, string $description, float $price = 24.0): Product
    {
        return Product::factory()->create([
            'category_id' => ProductCategory::factory()->create(['name' => 'Minerals'])->id,
            'name' => $name,
            'short_description' => $description,
            'description' => $description,
            'price' => $price,
            // Pinned, not left to the factory. The unit is part of what the
            // assistant is grounded against, and a random "30 capsules" would
            // make the invented-figure test below pass or fail by luck.
            'unit' => '60 capsules',
        ]);
    }

    /* ------------------------------------------------------------- indexing */

    public function test_the_index_command_embeds_the_catalogue_once_and_skips_unchanged_records(): void
    {
        $this->product('Night Blend', 'A calming evening formula.');

        $this->fakeEmbeddings();

        $this->artisan('chat:index')->assertSuccessful();

        $row = ChatEmbedding::query()->ofType('product')->firstOrFail();

        $this->assertCount(3, $row->vector);
        $this->assertSame(3, $row->dimensions);
        $this->assertSame('text-embedding-test', $row->model);

        $sent = Http::recorded()->count();

        // Nothing changed, so nothing is paid for a second time.
        $this->artisan('chat:index')->assertSuccessful();
        $this->assertSame($sent, Http::recorded()->count());
    }

    public function test_an_unpublished_product_is_dropped_from_the_index(): void
    {
        $product = $this->product('Night Blend', 'A calming evening formula.');

        $this->fakeEmbeddings();

        $this->artisan('chat:index')->assertSuccessful();
        $this->assertTrue(ChatEmbedding::query()->ofType('product')->exists());

        $product->update(['status' => 'draft', 'published_at' => null]);

        $this->artisan('chat:index')->assertSuccessful();

        // A draft must stop being retrievable the moment it is unpublished, or
        // the assistant keeps describing something nobody can buy.
        $this->assertFalse(ChatEmbedding::query()->ofType('product')->exists());
    }

    public function test_the_index_is_rebuilt_when_the_embedding_shape_changes(): void
    {
        $this->product('Night Blend', 'A calming evening formula.');

        $this->fakeEmbeddings();
        $this->artisan('chat:index')->assertSuccessful();

        config(['chat.retrieval.dimensions' => 4]);

        $this->artisan('chat:index')->assertSuccessful();

        // Vectors from two different models are not comparable, so the old
        // ones cannot be left in place beside the new ones.
        $this->assertSame(4, ChatEmbedding::query()->ofType('product')->firstOrFail()->dimensions);
    }

    /* ------------------------------------------------------------ retrieval */

    public function test_a_question_reaches_a_product_that_never_uses_its_words(): void
    {
        $product = $this->product('Night Blend', 'A calming evening formula.');

        // Indexed pointing one way; the question is embedded pointing the same
        // way, which is the whole mechanism under test.
        ChatEmbedding::query()->create([
            'source_type' => 'product',
            'source_key' => (string) $product->id,
            'source_id' => $product->id,
            'title' => $product->name,
            'body' => 'A calming evening formula.',
            'url' => route('product', $product->slug),
            'checksum' => str_repeat('a', 64),
            'model' => 'text-embedding-test',
            'dimensions' => 3,
            'vector' => [1.0, 0.0, 0.0],
        ]);

        app(ChatIndexService::class)->forget();

        Http::fake([
            self::EMBED => Http::response($this->embedResponse([[0.99, 0.01, 0.0]])),
            self::GENERATE => Http::sequence()
                ->push($this->aiCall('searchProducts', ['query' => 'something that helps me wind down']))
                ->push($this->aiReply('Night Blend is the calming evening formula we have.', productIds: [$product->id])),
        ]);

        $this->sendMessage($this->start(), 'I want to buy something that helps me wind down')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.name', 'Night Blend')
            // Read from the product row, never from the indexed text.
            ->assertJsonPath('data.products.0.price', '24.00');
    }

    public function test_a_stated_price_filter_is_not_overridden_by_the_shortlist(): void
    {
        $cheap = $this->product('Night Blend', 'A calming evening formula.', 15.0);
        $dear = $this->product('Night Blend Pro', 'A calming evening formula.', 90.0);

        foreach ([$cheap, $dear] as $product) {
            ChatEmbedding::query()->create([
                'source_type' => 'product',
                'source_key' => (string) $product->id,
                'source_id' => $product->id,
                'title' => $product->name,
                'body' => 'A calming evening formula.',
                'url' => route('product', $product->slug),
                'checksum' => str_repeat('a', 64),
                'model' => 'text-embedding-test',
                'dimensions' => 3,
                'vector' => [1.0, 0.0, 0.0],
            ]);
        }

        app(ChatIndexService::class)->forget();

        Http::fake([
            self::EMBED => Http::response($this->embedResponse([[1.0, 0.0, 0.0]])),
            self::GENERATE => Http::sequence()
                ->push($this->aiCall('searchProducts', ['query' => 'calming', 'max_price' => 20]))
                ->push($this->aiReply('Night Blend is the calming evening formula under that price.', productIds: [$cheap->id, $dear->id])),
        ]);

        $this->sendMessage($this->start(), 'Show me something calming under twenty')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.name', 'Night Blend');
    }

    /* -------------------------------------------------------------- answers */

    public function test_the_conversation_so_far_goes_with_the_question(): void
    {
        $this->product('Night Blend', 'Night Blend is a calming evening formula with magnesium.');

        $session = $this->start();

        Http::fake([self::GENERATE => Http::sequence()
            ->push($this->aiReply('Night Blend helps you wind down in the evening.'))
            ->push($this->aiReply('It contains magnesium.'))]);

        $this->sendMessage($session, 'Tell me about Night Blend')->assertOk();
        $this->sendMessage($session, 'what is in it')->assertOk();

        // The second call carries the first exchange, which is what makes "it"
        // and "that one" mean anything a turn later.
        $payload = $this->lastAnswerPayload();

        $this->assertSame('what is in it', $payload['question']);
        $this->assertSame('visitor', $payload['earlier_turns'][0]['role']);
        $this->assertSame('Tell me about Night Blend', $payload['earlier_turns'][0]['text']);
        $this->assertSame('Night Blend helps you wind down in the evening.', $payload['earlier_turns'][1]['text']);
    }

    public function test_a_contact_detail_from_earlier_never_travels_with_it(): void
    {
        $this->product('Night Blend', 'Night Blend is a calming evening formula with magnesium.');

        $session = $this->start();

        Http::fake([self::GENERATE => Http::response($this->aiResponse([
            'answer' => 'Night Blend is a calming evening formula with magnesium.',
            'offtopic' => false,
        ]))]);

        // Typed unprompted, so it stays in the thread as an ordinary message -
        // and the thread is what history is built from.
        $this->sendMessage($session, 'Tell me about Night Blend')->assertOk();
        $this->sendMessage($session, 'what is in Night Blend, my email is asha@example.test')->assertOk();

        Http::assertSent(fn ($request) => ! str_contains($request->body(), 'asha@example.test'));
    }

    public function test_a_weak_match_is_answered_rather_than_apologised_for(): void
    {
        $this->product('Night Blend', 'Night Blend is a calming evening formula with magnesium and chamomile.');

        Http::fake([self::GENERATE => Http::sequence()
            // The assistant looks the product up, then answers from what came
            // back rather than from the phrasing it was given.
            ->push($this->aiCall('getProductDetails', ['product' => 'Night Blend']))
            ->push($this->aiText('The calming evening formula has magnesium and chamomile in it.'))]);

        // Nothing clears the retrieval floor for this phrasing, so the product
        // is looked up by name rather than the visitor being told the shop has
        // nothing on it.
        $this->sendMessage($this->start(), 'is there chamomile in the evening one')
            ->assertOk()
            ->assertJsonPath('data.message.text', 'The calming evening formula has magnesium and chamomile in it.')
            ->assertJsonPath('data.requires_contact', false);
    }

    public function test_an_answer_that_invents_a_figure_is_refused(): void
    {
        $this->product('Night Blend', 'Night Blend is a calming evening formula with magnesium.');

        // "90 capsules" appears nowhere in the catalogue. Two attempts, then
        // the visitor is offered a person rather than a made-up number.
        Http::fake([self::GENERATE => Http::response($this->aiResponse([
            'answer' => 'Night Blend is a calming evening formula containing magnesium in 90 capsules.',
            'offtopic' => false,
        ]))]);

        $this->sendMessage($this->start(), 'Tell me about Night Blend')
            ->assertOk()
            ->assertJsonPath('data.requires_contact', true)
            ->assertJsonPath('data.message.type', 'fallback');

        Http::assertSentCount(2);
    }
}
