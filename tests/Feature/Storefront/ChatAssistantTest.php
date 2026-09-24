<?php

namespace Tests\Feature\Storefront;

use App\Models\Admin;
use App\Models\ChatContact;
use App\Models\ChatSession;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Chat\ChatProductSearchService;
use App\Services\Chat\ChatToolService;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakesChatProvider;
use Tests\TestCase;

/**
 * Every reply the visitor sees is written by the model - welcome, answers,
 * suggestions, refusals - from what this store's functions return. Nothing is
 * a stored answer, and the application only decides whether a reply may be
 * shown.
 */
class ChatAssistantTest extends TestCase
{
    use FakesChatProvider;
    use RefreshDatabase;

    private const GEMINI = '*:generateContent';

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

        app(SettingService::class)->forget();

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
     * What the application sent the model in the request at $index.
     *
     * @return array<string, mixed>
     */
    private function sentPayload(int $index = 0): array
    {
        $request = Http::recorded()[$index][0];

        return json_decode($request->data()['contents'][0]['parts'][0]['text'], true);
    }

    /* ------------------------------------------------------ generated replies */

    public function test_a_reply_and_its_suggestions_are_the_models_own(): void
    {
        Http::fake([self::GEMINI => Http::response($this->aiReply(
            'Namaste! Main NoRx Dose ka virtual assistant hoon. Batayiye, kya dhoondh rahe hain?',
            ['Sleep ke liye kya hai?', 'Delivery kitne din mein hoti hai?'],
            intent: 'greeting',
        ))]);

        $this->sendMessage($this->start(), 'hi')
            ->assertOk()
            ->assertJsonPath('data.message.text', 'Namaste! Main NoRx Dose ka virtual assistant hoon. Batayiye, kya dhoondh rahe hain?')
            ->assertJsonPath('data.message.suggestions', ['Sleep ke liye kya hai?', 'Delivery kitne din mein hoti hai?'])
            ->assertJsonPath('data.message.intent', 'greeting')
            ->assertJsonPath('data.requires_contact', false);

        // Nothing answered the greeting locally: it went to the model.
        Http::assertSentCount(1);
    }

    public function test_the_welcome_is_generated_when_an_empty_chat_is_opened(): void
    {
        Http::fake([self::GEMINI => Http::response($this->aiReply(
            'Welcome to NoRx Dose! Ask me about any product or how ordering works.',
            ['Show me something for sleep', 'How do I pay?'],
            intent: 'greeting',
        ))]);

        $session = $this->start();

        $this->getJson("/api/chat/{$session}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.text', 'Welcome to NoRx Dose! Ask me about any product or how ordering works.')
            ->assertJsonPath('data.messages.0.suggestions', ['Show me something for sleep', 'How do I pay?']);

        // Only once: the conversation is no longer empty.
        $this->getJson("/api/chat/{$session}/messages")->assertOk()->assertJsonCount(1, 'data.messages');
        Http::assertSentCount(1);
    }

    public function test_with_the_model_unavailable_an_empty_chat_shows_no_canned_greeting(): void
    {
        config(['services.gemini.key' => '']);
        $session = $this->start();

        $this->getJson("/api/chat/{$session}/messages")->assertOk()->assertJsonCount(0, 'data.messages');
        Http::assertNothingSent();
    }

    public function test_the_widget_carries_no_written_questions_or_greeting(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('talkChips', false)
            ->assertDontSee('What is your shipping policy?')
            ->assertDontSee('Ask about any product, or tell me what you are looking for.');
    }

    public function test_the_store_has_no_stored_answers_left_in_its_configuration(): void
    {
        foreach (['greeting_message', 'clarify_message', 'unsupported_message', 'offtopic_message',
            'no_results_message', 'product_message', 'contact_confirmation', 'nothing_more_message'] as $key) {
            $this->assertNull(config('chat.'.$key), "chat.{$key} is a stored answer.");
        }
    }

    public function test_an_off_topic_question_is_redirected_in_the_models_own_words(): void
    {
        $answer = 'Main sirf NoRx Dose store ke baare mein madad kar sakta hoon. Products ya orders ke baare mein poochiye!';

        Http::fake([self::GEMINI => Http::response($this->aiReply($answer, ['Best sellers dikhao'], intent: 'off_topic'))]);

        $this->sendMessage($this->start(), 'Who won the cricket match yesterday?')
            ->assertOk()
            ->assertJsonPath('data.message.text', $answer)
            ->assertJsonPath('data.message.intent', 'off_topic');

        // The rule for it is in the instruction, not a written reply.
        $sent = Http::recorded()[0][0]->data();
        $this->assertStringContainsString('only help with this store', $sent['systemInstruction']['parts'][0]['text']);
    }

    /* -------------------------------------------------------------- products */

    public function test_a_search_reply_shows_its_products_and_pages_through_the_rest(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Sleep Aids']);
        $products = collect(range(1, 7))->map(fn (int $n) => Product::factory()->for($category, 'category')
            ->create(['name' => "Night Blend {$n}", 'price' => 10 + $n]));

        // What searchProducts will actually return for this query.
        $firstPage = app(ChatToolService::class)->searchProducts('Night Blend');
        $ids = array_column($firstPage['products'], 'id');

        Http::fake([self::GEMINI => Http::sequence()
            ->push($this->aiCall('searchProducts', ['query' => 'Night Blend']))
            ->push($this->aiReply('Here are our Night Blend options.', ['Which one is cheapest?'], $ids, intent: 'product_search'))]);

        $session = $this->start();

        $response = $this->sendMessage($session, 'show me night blend')
            ->assertOk()
            ->assertJsonPath('data.message.type', 'product')
            ->assertJsonCount(5, 'data.products')
            ->assertJsonPath('data.pagination.has_more', true);

        $this->getJson("/api/chat/{$session}/products/{$response->json('data.message.id')}?page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data.products');

        $this->assertSame(7, $products->count());
    }

    public function test_a_product_the_functions_never_returned_is_never_shown_as_a_card(): void
    {
        $shown = Product::factory()->create(['name' => 'Night Blend', 'price' => 24]);
        $hidden = Product::factory()->create(['name' => 'Morning Boost', 'price' => 30]);

        Http::fake([self::GEMINI => Http::sequence()
            ->push($this->aiCall('getProductDetails', ['product' => 'Night Blend']))
            ->push($this->aiReply('Night Blend costs $24.00.', productIds: [$shown->id, $hidden->id]))]);

        $this->sendMessage($this->start(), 'tell me about night blend')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.name', 'Night Blend');
    }

    public function test_which_one_is_cheapest_is_worked_out_by_the_model_from_the_products_on_screen(): void
    {
        $cheap = Product::factory()->create(['name' => 'Calm Tea', 'price' => 12]);
        $dear = Product::factory()->create(['name' => 'Calm Drops', 'price' => 40]);

        Http::fake([self::GEMINI => Http::sequence()
            ->push($this->aiCall('searchProducts', ['query' => 'calm']))
            ->push($this->aiReply('Here are two calming products.', productIds: [$cheap->id, $dear->id]))
            ->push($this->aiReply('Calm Tea is the cheapest at $12.00.', productIds: [$cheap->id]))]);

        $session = $this->start();
        $this->sendMessage($session, 'something calming')->assertOk();

        $this->sendMessage($session, 'which one is cheapest?')
            ->assertOk()
            ->assertJsonPath('data.message.text', 'Calm Tea is the cheapest at $12.00.')
            ->assertJsonPath('data.products.0.name', 'Calm Tea');

        // The live products on screen went with the question.
        $onScreen = $this->sentPayload(2)['products_on_screen'];
        $this->assertSame(['Calm Tea', 'Calm Drops'], array_column($onScreen, 'name'));
        $this->assertSame(12, (int) $onScreen[0]['price']);
    }

    /* -------------------------------------------------------------- honesty */

    public function test_an_invented_price_is_refused_the_model_is_told_why_and_answers_again(): void
    {
        Product::factory()->create(['name' => 'Night Blend', 'price' => 24]);

        Http::fake([self::GEMINI => Http::sequence()
            ->push($this->aiCall('getProductDetails', ['product' => 'Night Blend']))
            ->push($this->aiReply('Night Blend is only $9.99 today.'))
            ->push($this->aiCall('getProductDetails', ['product' => 'Night Blend']))
            ->push($this->aiReply('Night Blend is $24.00.'))]);

        $this->sendMessage($this->start(), 'how much is night blend')
            ->assertOk()
            ->assertJsonPath('data.message.text', 'Night Blend is $24.00.');

        $this->assertStringContainsString('price, number or duration', $this->sentPayload(2)['correction']);
        $this->assertDatabaseMissing('tbl_chat_messages', ['message' => 'Night Blend is only $9.99 today.']);
    }

    public function test_a_reply_that_fails_twice_is_never_shown(): void
    {
        Http::fake([self::GEMINI => Http::response($this->aiReply('Call our CEO directly at ceo@aurumwellness.test.'))]);

        $this->sendMessage($this->start(), 'how can I reach the owner?')
            ->assertOk()
            ->assertJsonPath('data.message.text', config('chat.failure_message'));

        $this->assertDatabaseMissing('tbl_chat_messages', ['message' => 'Call our CEO directly at ceo@aurumwellness.test.']);
        Http::assertSentCount(2);
    }

    public function test_the_public_contact_details_come_from_settings_through_a_function(): void
    {
        app(SettingService::class)->save(['contact.email' => 'support@aurumwellness.test', 'contact.phone' => '+1 415 555 0134']);

        $answer = 'You can reach our team at support@aurumwellness.test or +1 415 555 0134.';

        Http::fake([self::GEMINI => Http::sequence()
            ->push($this->aiCall('getStoreInformation'))
            ->push($this->aiReply($answer, intent: 'contact_request'))]);

        $this->sendMessage($this->start(), 'admin ka number do')
            ->assertOk()
            ->assertJsonPath('data.message.text', $answer);
    }

    /**
     * A function called with no arguments comes back as "args": {}. Sent back
     * as a list ([]) the provider refuses the whole conversation with a 400 -
     * which is how every "who are you" and "how do I pay" failed live.
     */
    public function test_a_call_without_arguments_is_replayed_as_an_object(): void
    {
        Http::fake([self::GEMINI => Http::sequence()
            ->push($this->aiCall('getStoreInformation'))
            ->push($this->aiReply('We take card payments at checkout.'))]);

        $this->sendMessage($this->start(), 'how do I pay?')->assertOk();

        $this->assertStringContainsString('"functionCall":{"name":"getStoreInformation","args":{}}', Http::recorded()[1][0]->body());
    }

    public function test_store_information_never_carries_the_placeholder_or_an_admin_login(): void
    {
        Admin::factory()->admin()->create(['email' => 'owner.login@aurumwellness.test']);

        $facts = app(ChatToolService::class)->call('getStoreInformation', []);

        $this->assertSame([], $facts['public_contact']);
        $this->assertStringNotContainsString('hello@example.com', json_encode($facts));
        $this->assertStringNotContainsString('owner.login@aurumwellness.test', json_encode($facts));
        $this->assertFalse($facts['assistant']['is_a_person']);
    }

    /* ------------------------------------------------------------- privacy */

    public function test_a_visitors_contact_details_are_saved_here_and_never_sent_to_the_model(): void
    {
        Http::fake([self::GEMINI => Http::sequence()
            ->push($this->aiReply('I cannot find that. Type your email or phone number and the team will help.', needsContact: true))
            ->push($this->aiReply('Thank you! Our team has your details and will get back to you soon.'))]);

        $session = $this->start();

        $this->sendMessage($session, 'where is my refund?')->assertOk()->assertJsonPath('data.requires_contact', true);

        $this->sendMessage($session, 'sure, asha@example.test or 9876543210')
            ->assertOk()
            ->assertJsonPath('data.message.text', 'Thank you! Our team has your details and will get back to you soon.');

        $contact = ChatContact::query()->sole();
        $this->assertSame('asha@example.test', $contact->email);
        $this->assertSame('where is my refund?', $contact->message);

        Http::assertSent(fn (Request $request) => ! str_contains(json_encode($request->data()), 'asha@example.test')
            && ! str_contains(json_encode($request->data()), '9876543210'));
        $this->assertStringContainsString('shared their contact details', $this->sentPayload(1)['note']);
    }

    public function test_a_conversation_a_person_has_taken_over_gets_no_generated_reply(): void
    {
        $session = $this->start();
        ChatSession::query()->where('session_uuid', $session)->update(['reply_mode' => ChatSession::MODE_MANUAL]);

        $this->sendMessage($session, 'hello?')
            ->assertOk()
            ->assertJsonPath('data.awaiting_human', true)
            ->assertJsonPath('data.message', null);

        Http::assertNothingSent();
    }

    public function test_a_catalogue_fault_is_handed_to_the_model_as_data_not_an_invented_answer(): void
    {
        $this->mock(ChatProductSearchService::class)
            ->shouldReceive('paginate')->andThrow(new \RuntimeException('database is down'));

        Http::fake([self::GEMINI => Http::sequence()
            ->push($this->aiCall('searchProducts', ['query' => 'sleep']))
            ->push($this->aiReply('Sorry, I could not load the catalogue just now. Please try again in a moment.'))]);

        $this->sendMessage($this->start(), 'sleep products')
            ->assertOk()
            ->assertJsonPath('data.message.text', 'Sorry, I could not load the catalogue just now. Please try again in a moment.')
            ->assertDontSee('database is down');

        $functionResponse = Http::recorded()[1][0]->data()['contents'][2]['parts'][0]['functionResponse']['response'];
        $this->assertFalse($functionResponse['found']);
        $this->assertArrayHasKey('error', $functionResponse);
    }
}
