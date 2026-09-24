<?php

namespace Tests\Feature\Storefront;

use App\Models\ChatContact;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakesChatProvider;
use Tests\TestCase;

/**
 * Getting hold of a visitor the assistant could not help.
 *
 * There is no form. The assistant asks in the conversation, and whatever comes
 * back is read for an address or a number. The rule that matters here is the
 * one this module has always had: a customer's own contact details are never
 * sent to the provider, and the way that is kept true is that the message
 * carrying them is answered before anything could leave.
 */
class ChatContactTest extends TestCase
{
    use FakesChatProvider;
    use RefreshDatabase;

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
     * Asks something the store has nothing on, which is what makes the
     * assistant ask for a way to reach the visitor.
     */
    private function askUnanswerable(string $session): void
    {
        $this->sendMessage($session, 'Who won the cricket match?')
            ->assertOk()
            ->assertJsonPath('data.requires_contact', true);
    }

    /* ------------------------------------------------------------- capture */

    public function test_an_email_typed_in_reply_is_saved_without_a_form(): void
    {
        $session = $this->start();
        $this->askUnanswerable($session);

        // The confirmation is the model's own; the address never goes with it.
        Http::fake(['*:generateContent' => Http::response($this->aiReply('Thank you, the team has your details.'))]);

        $this->sendMessage($session, 'sure, asha@example.test')
            ->assertOk()
            ->assertJsonPath('data.message.text', 'Thank you, the team has your details.')
            ->assertJsonPath('data.requires_contact', false);

        $contact = ChatContact::query()->firstOrFail();

        $this->assertSame('asha@example.test', $contact->email);
        $this->assertNull($contact->phone);

        // The question they could not get an answer to goes with it - it is the
        // whole reason support is being handed this.
        $this->assertSame('Who won the cricket match?', $contact->message);

        // The provider was told contact details arrived - never what they were.
        Http::assertSent(fn ($request) => ! str_contains(json_encode($request->data()), 'asha@example.test'));
    }

    public function test_a_phone_number_typed_in_reply_is_saved_too(): void
    {
        $session = $this->start();
        $this->askUnanswerable($session);

        $this->sendMessage($session, 'call me on +1 (415) 555-0132')->assertOk();

        $contact = ChatContact::query()->firstOrFail();

        $this->assertNull($contact->email);
        $this->assertStringContainsString('415', $contact->phone);
        Http::assertNothingSent();
    }

    public function test_both_are_kept_when_both_are_offered(): void
    {
        $session = $this->start();
        $this->askUnanswerable($session);

        $this->sendMessage($session, 'asha@example.test or 9876543210')->assertOk();

        $contact = ChatContact::query()->firstOrFail();

        $this->assertSame('asha@example.test', $contact->email);
        $this->assertSame('9876543210', $contact->phone);
    }

    public function test_an_address_is_not_mistaken_for_a_phone_number(): void
    {
        $session = $this->start();
        $this->askUnanswerable($session);

        // The local part of an address is full of digits often enough that
        // looking for a number in it finds one.
        $this->sendMessage($session, 'asha2024551234@example.test')->assertOk();

        $this->assertNull(ChatContact::query()->firstOrFail()->phone);
    }

    public function test_contact_details_are_never_sent_to_the_provider(): void
    {
        $session = $this->start();
        $this->askUnanswerable($session);

        // The message is answered before anything could leave, so there is no
        // redaction to get wrong.
        $this->sendMessage($session, 'asha@example.test')->assertOk();

        Http::assertNothingSent();
    }

    /* ------------------------------------------------------------ the ask */

    public function test_a_reply_with_nothing_to_go_on_is_not_read_as_contact_details(): void
    {
        $session = $this->start();
        $this->askUnanswerable($session);

        // Nothing was said that could be looked up or answered, so the ask
        // stands rather than being taken for an address.
        Http::fake(['*:generateContent' => Http::response($this->aiText(''))]);

        $this->sendMessage($session, 'I would rather not say')
            ->assertOk()
            ->assertJsonPath('data.requires_contact', true);

        $this->assertSame(0, ChatContact::query()->count());
    }

    public function test_a_question_is_still_answered_while_we_are_waiting_for_details(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Minerals']);
        Product::factory()->for($category, 'category')->create(['name' => 'Night Blend']);

        $session = $this->start();
        $this->askUnanswerable($session);

        $product = Product::query()->where('name', 'Night Blend')->sole();

        Http::fake(['*:generateContent' => Http::sequence()
            ->push($this->aiCall('getProductDetails', ['product' => 'Night Blend']))
            ->push($this->aiReply('Here is Night Blend.', productIds: [$product->id]))]);

        // Being asked for an address does not trap the visitor: a real question
        // is still a real question.
        $this->sendMessage($session, 'Night Blend')
            ->assertOk()
            ->assertJsonCount(1, 'data.products');

        $this->assertSame(0, ChatContact::query()->count());
    }

    public function test_an_unprompted_address_is_not_swallowed(): void
    {
        $session = $this->start();

        Http::fake(['*:generateContent' => Http::response([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [['text' => json_encode([
                    'intent' => 'unsupported', 'query' => '', 'brand' => null, 'category' => null,
                    'min_price' => null, 'max_price' => null, 'currency' => null,
                    'in_stock' => null, 'ref' => null,
                ])]]],
            ]],
            'usageMetadata' => ['totalTokenCount' => 30],
        ])]);

        // Nobody asked, so this is not an answer to anything - it goes down the
        // ordinary path rather than being filed as a support request.
        $this->sendMessage($session, 'my email is asha@example.test')->assertOk();

        $this->assertSame(0, ChatContact::query()->count());
    }

    /* ----------------------------------------------------------- keywords */

}
