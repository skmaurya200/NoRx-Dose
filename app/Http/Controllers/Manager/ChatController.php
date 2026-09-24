<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Services\Chat\ChatInboxService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The conversation screens in the admin panel.
 *
 * HTML only. Replying and switching a conversation between the assistant and a
 * person are posted by the browser to App\Http\Controllers\APIs\ChatInboxController,
 * so a reply is written the same way wherever it comes from.
 */
class ChatController extends Controller
{
    public function __construct(private readonly ChatInboxService $inbox) {}

    /**
     * GET /manager/chats
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'mode', 'status', 'per_page']);

        return view('manager.chats.index', [
            'sessions' => $this->inbox->paginate($filters),
            'filters' => $filters,
            'summary' => $this->inbox->summary(),
        ]);
    }

    /**
     * GET /manager/chats/{session}
     */
    public function show(ChatSession $session): View
    {
        $transcript = $this->inbox->transcript($session);

        // Opening it is reading it.
        $this->inbox->markRead($session);

        return view('manager.chats.show', [
            'session' => $session,
            'messages' => $transcript,
            'contact' => $session->latestContact,
            'lastUuid' => $transcript->last()?->message_uuid,
        ]);
    }
}
