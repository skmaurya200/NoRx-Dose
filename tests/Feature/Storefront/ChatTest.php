<?php

namespace Tests\Feature\Storefront;

use App\Models\ChatMessage;
use App\Models\ChatProductResult;
use App\Models\ChatSession;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakesChatProvider;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use FakesChatProvider;
    use RefreshDatabase;

    private const GEMINI = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    /**
     * One of the shop's own FAQ questions, typed back at it word for word.
     *
     * The grounding tests below are about what happens to an answer, not about
     * how the question was routed, so they ask something that lands on a known
     * record with no classification round trip to arrange first.
     */
    private const OWN_QUESTION = 'How do discount codes work?';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.gemini.key' => 'test-private-key',
            'services.gemini.model' => 'gemini-2.5-flash',
            'services.gemini.ca_bundle' => null,
            'chat.requests_per_minute' => 100,
            'chat.ip_requests_per_minute' => 500,
        ]);
        Http::preventStrayRequests();
    }

    private function start(?string $owner = null): string
    {
        $this->withSession(['chat_owner' => $owner ?? Str::random(64)]);

        return $this->postJson('/api/chat/session')->assertOk()->json('data.session_uuid');
    }

    private function sendMessage(string $session, string $message, array $extra = []): TestResponse
    {
        return $this->postJson('/api/chat/message', array_replace([
            'session_uuid' => $session, 'request_uuid' => (string) Str::uuid(), 'message' => $message,
        ], $extra));
    }

    private function aiResponse(array|string $output, string $finish = 'STOP'): array
    {
        return [
            'candidates' => [[
                'finishReason' => $finish,
                'content' => ['parts' => [['text' => is_string($output) ? $output : json_encode($output)]]],
            ]],
            'usageMetadata' => ['totalTokenCount' => 30],
        ];
    }

    private function intent(array $values = []): array
    {
        return array_replace([
            'intent' => 'product_search', 'query' => 'magnesium', 'brand' => null, 'category' => null,
            'min_price' => null, 'max_price' => null, 'currency' => null, 'in_stock' => null, 'ref' => null,
        ], $values);
    }

    private function fallback(string $session): string
    {
        return $this->sendMessage($session, 'Who won the cricket match?')
            ->assertOk()->assertJsonPath('data.requires_contact', true)->json('data.message.id');
    }

    private function contactPayload(string $session, string $message): array
    {
        return [
            'session_uuid' => $session, 'message_uuid' => $message,
            'name' => 'Asha Sharma', 'email' => 'asha@example.test', 'phone' => '9876543210',
            'message' => 'Please help with my question.',
        ];
    }

    public function test_creates_and_restores_a_guest_session_without_exposing_internal_fields(): void
    {
        $uuid = $this->start();
        $this->assertTrue(Str::isUuid($uuid));
        $response = $this->postJson('/api/chat/session')->assertOk()->assertJsonPath('data.session_uuid', $uuid);
        $response->assertJsonMissingPath('data.owner_hash')->assertJsonMissingPath('data.id');
        $this->assertDatabaseCount('tbl_chat_sessions', 1);
        $this->assertDatabaseHas('tbl_chat_sessions', ['session_uuid' => $uuid, 'ip_address' => null]);
    }

    public function test_stores_both_messages_and_replays_duplicate_request_without_another_call(): void
    {
        $session = $this->start();
        $joke = 'Why did the scarecrow win an award? He was outstanding in his field.';
        Http::fake([self::GEMINI => Http::response($this->aiText($joke))]);
        $requestUuid = (string) Str::uuid();

        $first = $this->sendMessage($session, 'Tell me a joke.', ['request_uuid' => $requestUuid])->assertOk();
        $second = $this->sendMessage($session, 'Tell me a joke.', ['request_uuid' => $requestUuid])->assertOk();

        $this->assertSame($first->json('data.message.id'), $second->json('data.message.id'));
        $this->assertDatabaseCount('tbl_chat_messages', 2);
        $this->assertDatabaseHas('tbl_chat_messages', ['sender' => 'user', 'message' => 'Tell me a joke.']);
        $this->assertDatabaseHas('tbl_chat_messages', ['sender' => 'assistant', 'message' => $joke]);
        Http::assertSentCount(1);
    }

    public function test_request_uuid_cannot_be_reused_for_different_text(): void
    {
        $session = $this->start();
        $uuid = (string) Str::uuid();
        $this->sendMessage($session, 'cricket', ['request_uuid' => $uuid])->assertOk();
        $this->sendMessage($session, 'python code', ['request_uuid' => $uuid])->assertConflict();
        $this->assertDatabaseCount('tbl_chat_messages', 2);
    }

    public static function invalidMessages(): array
    {
        return [
            'empty' => ['message', ''],
            'whitespace' => ['message', " \t\n "],
            'unicode whitespace' => ['message', "\u{00a0}\u{2003}"],
            'oversized' => ['message', str_repeat('a', 1001)],
            'array' => ['message', ['text']],
            'invalid uuid' => ['session_uuid', '12'],
            'invalid request uuid' => ['request_uuid', '1'],
            'intent override' => ['intent', 'product_search'],
            'sender override' => ['sender', 'assistant'],
            'numeric owner' => ['chat_session_id', 1],
            'product ids' => ['product_ids', [1, 2]],
            'sql' => ['sql', 'DROP TABLE tbl_products'],
            'filters' => ['filters', ['price' => 0]],
            'page size' => ['per_page', 50000],
        ];
    }

    #[DataProvider('invalidMessages')]
    public function test_rejects_invalid_message_payload_without_writing(string $field, mixed $value): void
    {
        $session = $this->start();
        $this->sendMessage($session, 'Hello', [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('tbl_chat_messages', 0);
        Http::assertNothingSent();
    }

    public function test_no_matches_never_invents_products(): void
    {
        $session = $this->start();
        Http::fake([self::GEMINI => Http::sequence()
            ->push($this->aiCall('searchProducts', ['query' => 'nonexistent supplement']))
            ->push($this->aiText('I could not find anything like that in the catalogue.'))]);

        $this->sendMessage($session, 'Show me a nonexistent supplement')->assertOk()
            ->assertJsonCount(0, 'data.products')
            ->assertJsonPath('data.message.text', 'I could not find anything like that in the catalogue.');
        $this->assertDatabaseCount('tbl_chat_product_results', 0);
        Http::assertSentCount(2);
    }

    public function test_rejects_invented_answer_even_when_word_count_is_valid(): void
    {
        $session = $this->start();
        Http::fake([self::GEMINI => Http::response($this->aiResponse([
            'answer' => 'Every order arrives tomorrow with free shipping and a guaranteed refund whenever you ask our team.',
            'offtopic' => false,
        ]))]);

        $this->sendMessage($session, self::OWN_QUESTION)->assertOk()
            ->assertJsonPath('data.requires_contact', true)->assertJsonPath('data.message.type', 'fallback');
        $this->assertDatabaseCount('tbl_chat_messages', 2);
        Http::assertSentCount(2);
    }

    public function test_placeholder_faq_is_not_sent_to_gemini_or_claimed_as_policy(): void
    {
        $session = $this->start();
        Http::fake([self::GEMINI => Http::response($this->aiResponse($this->intent([
            'intent' => 'website_question', 'query' => '', 'ref' => null,
        ])))]);

        $this->sendMessage($session, 'What is your refund policy?')->assertOk()->assertJsonPath('data.requires_contact', true);
        Http::assertSent(fn ($request) => ! str_contains(mb_strtolower($request->body()), 'placeholder'));
    }

    public function test_no_form_is_ever_put_in_front_of_the_visitor(): void
    {
        $session = $this->start();
        $this->fallback($session);

        // The ask is words in the conversation. A four-field form to collect
        // one answer is what most people closed.
        $this->get('/shop')
            ->assertOk()
            ->assertDontSee('talk__contact', false)
            ->assertDontSee('data-contact-url', false);
    }

    public function test_contact_is_validated_trimmed_saved_once_and_confirmation_is_in_history(): void
    {
        $session = $this->start();
        $message = $this->fallback($session);
        $payload = $this->contactPayload($session, $message);
        $payload['name'] = '  Asha Sharma  ';
        $payload['email'] = '  ASHA@example.test  ';

        Http::fake([self::GEMINI => Http::response($this->aiReply('Thanks Asha, the team has your question.'))]);

        $response = $this->postJson('/api/chat/contact', $payload)->assertOk()
            ->assertJsonPath('data.message.text', 'Thanks Asha, the team has your question.');
        $this->postJson('/api/chat/contact', $payload)->assertOk()
            ->assertJsonPath('data.message.id', $response->json('data.message.id'));
        $this->assertDatabaseHas('tbl_chat_contacts', ['name' => 'Asha Sharma', 'email' => 'asha@example.test', 'phone' => '9876543210']);
        $this->assertDatabaseCount('tbl_chat_contacts', 1);
        $this->getJson("/api/chat/$session/messages")->assertOk()->assertJsonCount(4, 'data.messages')
            ->assertJsonPath('data.messages.1.requires_contact', false)
            ->assertJsonMissingPath('data.messages.1.email');
        // Confirmed by the model once, and never with the details themselves.
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => ! str_contains(json_encode($request->data()), 'asha@example.test'));
    }

    public static function invalidContacts(): array
    {
        return [
            'invalid email' => ['email', 'not-email'],
            'oversized email' => ['email', str_repeat('a', 181).'@example.test'],
            'phone prefix' => ['phone', '5123456789'],
            'phone short' => ['phone', '987654321'],
            'phone long' => ['phone', '919876543210'],
            'phone numeric type' => ['phone', 9876543210],
            'blank name' => ['name', '  '],
            'name too short' => ['name', 'A'],
            'name symbols' => ['name', '123!!!'],
            'name html' => ['name', '<script>alert(1)</script>'],
            'name too long' => ['name', str_repeat('a', 121)],
            'blank message' => ['message', '  '],
            'short message' => ['message', 'Hi'],
            'long message' => ['message', str_repeat('a', 2001)],
            'extra owner id' => ['chat_session_id', 22],
        ];
    }

    #[DataProvider('invalidContacts')]
    public function test_rejects_invalid_contact_without_saving_or_confirming(string $field, mixed $value): void
    {
        $session = $this->start();
        $message = $this->fallback($session);

        $this->postJson('/api/chat/contact', array_replace($this->contactPayload($session, $message), [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('tbl_chat_contacts', 0);
        $this->assertDatabaseCount('tbl_chat_messages', 2);
    }

    public function test_guest_cannot_read_write_paginate_or_contact_another_guests_session(): void
    {
        $first = $this->start();
        Product::factory()->create(['name' => 'Magnesium']);
        $productMessage = $this->sendMessage($first, 'Magnesium')->assertOk()->json('data.message.id');
        $fallback = $this->fallback($first);
        $second = $this->start();

        $this->getJson("/api/chat/$first/messages")->assertNotFound();
        $this->sendMessage($first, 'Do not store this')->assertNotFound();
        $this->getJson("/api/chat/$first/products/$productMessage?page=2")->assertNotFound();
        $this->getJson("/api/chat/$second/products/$productMessage?page=2")->assertNotFound();
        $this->postJson('/api/chat/contact', $this->contactPayload($first, $fallback))->assertNotFound();
        $this->postJson('/api/chat/contact', $this->contactPayload($second, $fallback))->assertNotFound();
        $this->getJson("/api/chat/$second/messages?before=$productMessage")->assertNotFound();
        $this->deleteJson("/api/chat/$first/messages")->assertMethodNotAllowed();
        $this->getJson("/api/chat/$second/messages")->assertOk()->assertJsonCount(0, 'data.messages');
        $this->assertDatabaseCount('tbl_chat_messages', 4);
        $this->assertDatabaseCount('tbl_chat_contacts', 0);
        Http::assertNothingSent();
    }

    public function test_history_uses_bounded_chronological_pages_and_opaque_cursors(): void
    {
        $session = $this->start();
        $record = ChatSession::where('session_uuid', $session)->firstOrFail();
        ChatMessage::factory()->count(35)->create(['chat_session_id' => $record->id]);

        $first = $this->getJson("/api/chat/$session/messages")->assertOk()->assertJsonCount(30, 'data.messages')
            ->assertJsonPath('data.pagination.has_more', true);
        $before = $first->json('data.pagination.before');
        $this->getJson("/api/chat/$session/messages?before=$before")->assertOk()->assertJsonCount(5, 'data.messages')
            ->assertJsonPath('data.pagination.has_more', false);
        $this->assertSame($record->messages()->orderBy('id')->skip(5)->first()->message_uuid, $first->json('data.messages.0.id'));
        $this->getJson("/api/chat/$session/messages?before=42")->assertUnprocessable();
        $this->getJson('/api/chat/not-a-uuid/messages')->assertNotFound();
    }

    public function test_message_rate_limit_cannot_be_bypassed_by_changing_client_uuid(): void
    {
        config(['chat.requests_per_minute' => 1]);
        $session = $this->start();
        $this->sendMessage($session, 'cricket')->assertOk();
        $this->sendMessage((string) Str::uuid(), 'cricket')->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertDatabaseCount('tbl_chat_messages', 2);
    }

    public function test_ip_limit_cannot_be_bypassed_by_new_guest_sessions(): void
    {
        config(['chat.ip_requests_per_minute' => 1]);
        $this->start();
        $this->withSession(['chat_owner' => Str::random(64)])->postJson('/api/chat/session')->assertTooManyRequests();
        $this->assertDatabaseCount('tbl_chat_sessions', 1);
    }

    public static function failures(): array
    {
        return ['unavailable' => ['http'], 'timeout' => ['timeout'], 'malformed' => ['json'], 'blocked' => ['blocked'], 'unknown intent' => ['intent'], 'sql field' => ['sql'], 'bad filter' => ['filter']];
    }

    #[DataProvider('failures')]
    public function test_gemini_failures_preserve_question_and_safe_fallback(string $failure): void
    {
        $session = $this->start();
        $response = match ($failure) {
            'http' => Http::response(['error' => 'SECRET api key'], 503),
            'timeout' => Http::failedConnection('SECRET connection'),
            'json' => Http::response($this->aiResponse('{invalid')),
            'blocked' => Http::response($this->aiResponse([], 'SAFETY')),
            'intent' => Http::response($this->aiResponse($this->intent(['intent' => 'execute_sql']))),
            'sql' => Http::response($this->aiResponse($this->intent() + ['sql' => 'DROP TABLE tbl_products'])),
            'filter' => Http::response($this->aiResponse($this->intent(['max_price' => 'SQL']))),
        };
        Http::fake([self::GEMINI => $response]);

        $this->sendMessage($session, 'Ashwagandha gummies')->assertOk()
            ->assertJsonPath('data.message.type', 'fallback')->assertJsonPath('data.requires_contact', true)
            ->assertDontSee('SECRET')->assertDontSee('test-private-key');
        $this->assertDatabaseHas('tbl_chat_messages', ['sender' => 'user', 'message' => 'Ashwagandha gummies']);
        $this->assertDatabaseHas('tbl_chat_messages', ['sender' => 'assistant', 'message' => config('chat.failure_message')]);
        // A connection failure is retried once by the client; an unusable reply
        // is asked for once more; a blocked one is not asked again.
        Http::assertSentCount($failure === 'blocked' ? 1 : 2);
    }

    public function test_transient_gemini_failure_is_retried_once(): void
    {
        $session = $this->start();
        Http::fake([self::GEMINI => Http::sequence()
            ->push(['error' => 'temporarily unavailable'], 503)
            ->push($this->aiText('We do not have any ashwagandha gummies listed.'))]);

        // The retry is what turns a blip into an answer rather than into an
        // apology the visitor has to act on.
        $this->sendMessage($session, 'Ashwagandha gummies')->assertOk()
            ->assertJsonPath('data.message.text', 'We do not have any ashwagandha gummies listed.');
        Http::assertSentCount(2);
    }

    public function test_missing_key_saves_the_question_and_never_answers_from_a_stored_reply(): void
    {
        config(['services.gemini.key' => '']);
        $session = $this->start();

        // Every reply is written by the model, so with no model there is no
        // reply - only the notice that the message was saved for the team.
        $this->sendMessage($session, 'How do I place an order?')->assertOk()
            ->assertJsonPath('data.message.text', config('chat.failure_message'))
            ->assertJsonPath('data.requires_contact', true);

        $this->assertDatabaseHas('tbl_chat_messages', ['sender' => 'user', 'message' => 'How do I place an order?']);
        Http::assertNothingSent();
    }

    public function test_unreadable_ca_bundle_falls_back_without_disabling_tls_verification(): void
    {
        config(['services.gemini.ca_bundle' => 'C:/missing/chat-ca-bundle.pem']);
        $session = $this->start();

        // Nothing here can be answered from the store, so the provider would
        // be reached for - and is not, because its TLS setup is unusable.
        $this->sendMessage($session, 'Ashwagandha gummies')->assertOk()
            ->assertJsonPath('data.requires_contact', true);

        $this->assertDatabaseCount('tbl_chat_messages', 2);
        Http::assertNothingSent();
    }

    public function test_chat_widget_is_public_escaped_and_has_no_secret_configuration(): void
    {
        $this->get('/shop')->assertOk()->assertSee('id="talkToUs"', false)
            ->assertSee('Talk to Us')->assertSee('js/chat.js')->assertDontSee('test-private-key');
        $session = $this->start();
        $text = '<script>alert("cricket")</script>';
        $this->sendMessage($session, $text)->assertOk()->assertJsonPath('data.user_message.text', $text);
        $this->getJson("/api/chat/$session/messages")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_products_endpoint_rejects_huge_pages_and_client_filters(): void
    {
        $session = $this->start();
        Product::factory()->create(['name' => 'Magnesium']);
        $message = $this->sendMessage($session, 'Magnesium')->assertOk()->json('data.message.id');
        $this->getJson("/api/chat/$session/products/$message?page=10001")->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson("/api/chat/$session/products/$message?page=2&per_page=999")->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson("/api/chat/$session/products/$message?page=2&query=other")->assertUnprocessable()->assertJsonValidationErrors('query');
        Http::assertNothingSent();
    }

    public function test_csrf_is_required_without_same_origin_browser_headers(): void
    {
        $this->app['env'] = 'local';
        $this->postJson('/api/chat/session')->assertStatus(419);
        $this->assertDatabaseCount('tbl_chat_sessions', 0);
    }

    public function test_valid_csrf_token_allows_guest_session_initialization(): void
    {
        $this->app['env'] = 'local';
        $this->withSession(['_token' => 'known-csrf-token'])->withHeader('X-CSRF-TOKEN', 'known-csrf-token')
            ->postJson('/api/chat/session')->assertOk();
        $this->assertDatabaseCount('tbl_chat_sessions', 1);
    }

    public function test_disabled_chat_rejects_requests_and_removes_widget(): void
    {
        config(['chat.enabled' => false]);
        $this->postJson('/api/chat/session')->assertNotFound();
        $this->get('/shop')->assertOk()->assertDontSee('id="talkToUs"', false);
        $this->assertDatabaseCount('tbl_chat_sessions', 0);
    }

    public function test_database_failure_has_safe_response_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        $session = $this->start();
        ChatMessage::creating(function (): void {
            throw new \RuntimeException('private database password must not escape');
        });
        try {
            $this->sendMessage($session, 'cricket')->assertInternalServerError()
                ->assertJsonPath('message', 'Something went wrong. Please try again.')
                ->assertDontSee('private database password');
            $this->assertDatabaseCount('tbl_chat_messages', 0);
        } finally {
            ChatMessage::flushEventListeners();
        }
    }

    public function test_failed_product_result_write_rolls_back_assistant_and_saves_fallback(): void
    {
        $session = $this->start();
        Product::factory()->create(['name' => 'Magnesium']);
        ChatProductResult::creating(function (): void {
            throw new \RuntimeException('product result write failed');
        });
        try {
            $this->sendMessage($session, 'Magnesium')->assertOk()->assertJsonPath('data.message.type', 'fallback');
            $this->assertDatabaseCount('tbl_chat_product_results', 0);
            $this->assertDatabaseCount('tbl_chat_messages', 2);
        } finally {
            ChatProductResult::flushEventListeners();
        }
    }

    public function test_owner_cookie_is_required_even_with_a_valid_uuid(): void
    {
        $session = $this->start();
        $this->withSession(['chat_owner' => null])->getJson("/api/chat/$session/messages")->assertNotFound();
        $this->sendMessage($session, 'cricket')->assertNotFound();
        $this->assertDatabaseCount('tbl_chat_messages', 0);
    }

    public function test_contact_requires_a_fallback_message_and_is_rate_limited(): void
    {
        config(['chat.contacts_per_hour' => 1]);
        $session = $this->start();
        $message = $this->fallback($session);
        $this->postJson('/api/chat/contact', $this->contactPayload($session, $message))->assertOk();
        $this->postJson('/api/chat/contact', $this->contactPayload($session, $message))->assertTooManyRequests();
        $this->assertDatabaseCount('tbl_chat_contacts', 1);
    }

    public function test_price_stock_currency_and_publication_filters_are_applied_together(): void
    {
        $session = $this->start();
        $category = ProductCategory::factory()->create(['name' => 'Minerals']);
        Product::factory()->for($category, 'category')->outOfStock()->create(['name' => 'Magnesium selected', 'price' => 20, 'currency' => 'INR']);
        Product::factory()->for($category, 'category')->outOfStock()->create(['name' => 'Magnesium cheap', 'price' => 1, 'currency' => 'INR']);
        Product::factory()->for($category, 'category')->outOfStock()->create(['name' => 'Magnesium foreign', 'price' => 20, 'currency' => 'USD']);
        Product::factory()->for($category, 'category')->create(['name' => 'Magnesium available', 'price' => 20, 'currency' => 'INR']);
        Product::factory()->for($category, 'category')->outOfStock()->create(['name' => 'Magnesium future', 'price' => 20, 'currency' => 'INR', 'published_at' => now()->addDay()]);
        $deleted = Product::factory()->for($category, 'category')->outOfStock()->create(['name' => 'Magnesium deleted', 'price' => 20, 'currency' => 'INR']);
        $deleted->delete();
        config(['shop.currency' => 'INR']);

        Http::fake([self::GEMINI => Http::sequence()
            ->push($this->aiCall('searchProducts', [
                'query' => 'magnesium', 'min_price' => 10, 'max_price' => 30, 'in_stock' => false,
            ]))
            ->push($this->aiReply('One magnesium in that range cannot be ordered at the moment.',
                productIds: [Product::query()->where('name', 'Magnesium selected')->value('id')]))]);

        $this->sendMessage($session, 'Find unavailable magnesium between 10 and 30 rupees')->assertOk()
            ->assertJsonCount(1, 'data.products')->assertJsonPath('data.products.0.name', 'Magnesium selected')
            ->assertJsonPath('data.products.0.availability', 'Out of stock');
        Http::assertSentCount(2);
    }

    public function test_personal_contact_tokens_are_redacted_before_classification(): void
    {
        $session = $this->start();
        Http::fake([self::GEMINI => Http::response($this->aiResponse($this->intent(['intent' => 'unsupported', 'query' => ''])))]);
        $this->sendMessage($session, 'Contact me at private@example.test or 9876543210 about my order')->assertOk();
        Http::assertSent(fn ($request) => ! str_contains($request->body(), 'private@example.test')
            && ! str_contains($request->body(), '9876543210'));
    }

    public function test_credential_messages_are_not_forwarded_to_the_provider(): void
    {
        $session = $this->start();
        $this->sendMessage($session, 'My password is private-value; please check it.')->assertOk()
            ->assertJsonPath('data.requires_contact', true);
        Http::assertNothingSent();
    }
}
