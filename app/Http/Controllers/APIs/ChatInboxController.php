<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\Chat\ManagerChatModeRequest;
use App\Http\Requests\APIs\Chat\ManagerChatReplyRequest;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\Chat\ChatInboxService;
use App\Services\Chat\ChatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel's write access to a guest conversation.
 *
 * Sits behind auth:sanctum + admin like every other module here. A guest's own
 * ownership check does not apply - and must not be confused with this one: an
 * admin reaches any conversation, a guest reaches only their own.
 */
class ChatInboxController extends Controller
{
    public function __construct(
        private readonly ChatService $chat,
        private readonly ChatInboxService $inbox,
    ) {}

    /**
     * GET /api/manager/chats/{session}/messages
     *
     * Everything after the message the screen last drew, so an open
     * conversation shows a visitor's reply without being reloaded.
     */
    public function messages(Request $request, ChatSession $session): JsonResponse
    {
        $after = $request->query('after');
        $after = is_string($after) && $after !== '' ? $after : null;

        $messages = $this->inbox->since($session, $after);

        // Watching a conversation is reading it.
        $this->inbox->markRead($session);

        return ApiResponse::success([
            'reply_mode' => $session->reply_mode,
            'messages' => $messages->map(fn (ChatMessage $message) => $this->inbox->describe($message))->all(),
            'last_uuid' => $messages->last()?->message_uuid ?? $after,
        ], 'Messages loaded.');
    }

    /**
     * POST /api/manager/chats/{session}/reply
     */
    public function reply(ManagerChatReplyRequest $request, ChatSession $session): JsonResponse
    {
        // Replying by hand is taking the conversation over: leaving the
        // assistant switched on would have it answer the next message over the
        // top of a person mid-conversation.
        $this->chat->setReplyMode($session, ChatSession::MODE_MANUAL);

        $message = $this->chat->replyAsManager(
            $session,
            $request->validated('message'),
            (int) $request->user()->getKey(),
        );

        return ApiResponse::success(
            ['message' => $this->inbox->describe($message), 'reply_mode' => $session->refresh()->reply_mode],
            'Reply sent.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * PATCH /api/manager/chats/{session}/mode
     */
    public function mode(ManagerChatModeRequest $request, ChatSession $session): JsonResponse
    {
        $session = $this->chat->setReplyMode($session, $request->validated('reply_mode'));

        return ApiResponse::success([
            'reply_mode' => $session->reply_mode,
            'messages' => $this->inbox->since($session, $request->validated('after'))
                ->map(fn (ChatMessage $message) => $this->inbox->describe($message))->all(),
        ], $session->isManual()
            ? 'You are now answering this conversation.'
            : 'The assistant is answering this conversation again.');
    }
}
