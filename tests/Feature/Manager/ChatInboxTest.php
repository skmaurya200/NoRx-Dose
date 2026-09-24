<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reading and answering guest conversations from the panel.
 *
 * The behaviour worth guarding is the handover: once a person takes a
 * conversation over, the model must not answer into it - a visitor being
 * replied to twice, once by staff and once by a machine, is the failure this
 * feature exists to prevent.
 */
class ChatInboxTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();

        config([
            'services.gemini.key' => 'test-private-key',
            'services.gemini.model' => 'gemini-2.5-flash',
            'services.gemini.ca_bundle' => null,
            'chat.requests_per_minute' => 100,
            'chat.ip_requests_per_minute' => 500,
        ]);

        Http::preventStrayRequests();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * A conversation with one question in it, owned by a browser session this
     * test can come back to.
     *
     * @return array{0: ChatSession, 1: string}
     */
    private function conversation(string $question = 'Do you deliver on Sunday?'): array
    {
        $owner = Str::random(64);

        $this->withSession(['chat_owner' => $owner]);
        $uuid = $this->postJson('/api/chat/session')->assertOk()->json('data.session_uuid');

        $session = ChatSession::query()->where('session_uuid', $uuid)->firstOrFail();

        $session->messages()->create([
            'message_uuid' => (string) Str::uuid(),
            'sender' => 'user',
            'authored_by' => ChatMessage::AUTHOR_GUEST,
            'message' => $question,
        ]);

        $session->forceFill(['unread_count' => 1, 'last_message_at' => now()])->save();

        return [$session->refresh(), $owner];
    }

    /* -------------------------------------------------------------- access */

    public function test_a_guest_cannot_read_or_answer_a_conversation(): void
    {
        [$session] = $this->conversation();

        $this->getJson("/api/manager/chats/{$session->id}/messages")->assertStatus(401);
        $this->postJson("/api/manager/chats/{$session->id}/reply", ['message' => 'Hello'])->assertStatus(401);
        $this->patchJson("/api/manager/chats/{$session->id}/mode", ['reply_mode' => 'manual'])->assertStatus(401);
    }

    public function test_a_storefront_customer_cannot_answer_a_conversation(): void
    {
        [$session] = $this->conversation();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/manager/chats/{$session->id}/reply", ['message' => 'Hello'])
            ->assertStatus(403);
    }

    /* ------------------------------------------------------------- screens */

    public function test_the_list_shows_a_conversation_and_its_unread_count(): void
    {
        $this->conversation('Where is my order?');

        $this->asAdmin()
            ->get('/manager/chats')
            ->assertOk()
            ->assertSee('Where is my order?')
            ->assertSee('1 new');
    }

    public function test_opening_a_conversation_shows_the_transcript_and_clears_its_badge(): void
    {
        [$session] = $this->conversation('Is this vegetarian?');

        $this->asAdmin()
            ->get("/manager/chats/{$session->id}")
            ->assertOk()
            ->assertSee('Is this vegetarian?')
            ->assertSee('Visitor');

        $this->assertSame(0, $session->refresh()->unread_count);
    }

    /* ------------------------------------------------------------ handover */

    public function test_a_reply_is_stored_as_a_person_and_takes_the_conversation_over(): void
    {
        [$session] = $this->conversation();

        $this->asAdmin()
            ->postJson("/api/manager/chats/{$session->id}/reply", ['message' => 'Yes, we deliver on Sunday.'])
            ->assertStatus(201)
            ->assertJsonPath('data.message.author', ChatMessage::AUTHOR_MANAGER)
            ->assertJsonPath('data.reply_mode', ChatSession::MODE_MANUAL);

        $this->assertDatabaseHas('tbl_chat_messages', [
            'message' => 'Yes, we deliver on Sunday.',
            'sender' => 'assistant',
            'authored_by' => ChatMessage::AUTHOR_MANAGER,
            'admin_id' => $this->admin->id,
        ]);

        // Answering it is reading it.
        $this->assertSame(0, $session->refresh()->unread_count);
        Http::assertNothingSent();
    }

    public function test_an_empty_reply_is_rejected_with_422(): void
    {
        [$session] = $this->conversation();

        $this->asAdmin()
            ->postJson("/api/manager/chats/{$session->id}/reply", ['message' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->assertSame(ChatSession::MODE_AI, $session->refresh()->reply_mode);
    }

    public function test_taking_over_tells_the_visitor_somebody_is_coming(): void
    {
        [$session] = $this->conversation();

        $this->asAdmin()
            ->patchJson("/api/manager/chats/{$session->id}/mode", ['reply_mode' => ChatSession::MODE_MANUAL])
            ->assertOk()
            ->assertJsonPath('data.reply_mode', ChatSession::MODE_MANUAL);

        $this->assertDatabaseHas('tbl_chat_messages', [
            'message' => config('chat.handover_message'),
            'message_type' => 'system',
            'authored_by' => ChatMessage::AUTHOR_MANAGER,
        ]);
    }

    public function test_an_unknown_reply_mode_is_rejected_with_422(): void
    {
        [$session] = $this->conversation();

        $this->asAdmin()
            ->patchJson("/api/manager/chats/{$session->id}/mode", ['reply_mode' => 'robot'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reply_mode');
    }

    /* --------------------------------------------------------- both sides */

    public function test_the_assistant_does_not_answer_a_conversation_a_person_has_taken_over(): void
    {
        [$session, $owner] = $this->conversation();

        $this->asAdmin()
            ->patchJson("/api/manager/chats/{$session->id}/mode", ['reply_mode' => ChatSession::MODE_MANUAL])
            ->assertOk();

        $before = ChatMessage::query()->count();

        $this->withSession(['chat_owner' => $owner])
            ->postJson('/api/chat/message', [
                'session_uuid' => $session->session_uuid,
                'request_uuid' => (string) Str::uuid(),
                'message' => 'Can somebody help me choose?',
            ])
            ->assertOk()
            ->assertJsonPath('data.awaiting_human', true)
            ->assertJsonPath('data.message', null);

        // The question is stored; nothing answered it, and nothing was sent to
        // the provider to try.
        $this->assertSame($before + 1, ChatMessage::query()->count());
        Http::assertNothingSent();
    }

    public function test_a_visitor_picks_up_a_managers_reply_from_their_own_history(): void
    {
        [$session, $owner] = $this->conversation();

        $last = $session->messages()->orderByDesc('id')->first()->message_uuid;

        $this->asAdmin()
            ->postJson("/api/manager/chats/{$session->id}/reply", ['message' => 'Sunday delivery is available.'])
            ->assertStatus(201);

        $this->withSession(['chat_owner' => $owner])
            ->getJson("/api/chat/{$session->session_uuid}/messages?after={$last}")
            ->assertOk()
            ->assertJsonPath('data.reply_mode', ChatSession::MODE_MANUAL)
            // The handover notice and the reply itself: replying takes the
            // conversation over, and the visitor is told so before the answer
            // rather than being left to wonder who is typing.
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.0.text', config('chat.handover_message'))
            ->assertJsonPath('data.messages.1.text', 'Sunday delivery is available.')
            ->assertJsonPath('data.messages.1.author', ChatMessage::AUTHOR_MANAGER);
    }

    public function test_one_guest_cannot_read_another_conversation_through_the_after_cursor(): void
    {
        [$session] = $this->conversation();

        $this->withSession(['chat_owner' => Str::random(64)])
            ->getJson("/api/chat/{$session->session_uuid}/messages")
            ->assertNotFound();
    }

    public function test_the_panel_sees_only_what_it_has_not_drawn_yet(): void
    {
        [$session] = $this->conversation();

        $last = $session->messages()->orderByDesc('id')->first()->message_uuid;

        $this->asAdmin()
            ->getJson("/api/manager/chats/{$session->id}/messages?after={$last}")
            ->assertOk()
            ->assertJsonCount(0, 'data.messages');

        $this->asAdmin()
            ->postJson("/api/manager/chats/{$session->id}/reply", ['message' => 'On its way.'])
            ->assertStatus(201);

        $this->asAdmin()
            ->getJson("/api/manager/chats/{$session->id}/messages?after={$last}")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.1.text', 'On its way.');
    }
}
