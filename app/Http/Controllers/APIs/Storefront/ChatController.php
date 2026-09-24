<?php

namespace App\Http\Controllers\APIs\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\Chat\ChatContactRequest;
use App\Http\Requests\APIs\Chat\ChatHistoryRequest;
use App\Http\Requests\APIs\Chat\ChatMessageRequest;
use App\Http\Requests\APIs\Chat\ChatProductsRequest;
use App\Http\Requests\APIs\Chat\ChatSessionRequest;
use App\Services\Chat\ChatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function session(ChatSessionRequest $request): JsonResponse
    {
        return ApiResponse::success(['session_uuid' => $this->chat->start($request)->session_uuid]);
    }

    public function message(ChatMessageRequest $request): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success($this->chat->send($this->chat->owned($request, $data['session_uuid']), $data));
    }

    public function history(ChatHistoryRequest $request, string $sessionUuid): JsonResponse
    {
        return ApiResponse::success($this->chat->history(
            $this->chat->owned($request, $sessionUuid),
            $request->validated('before'),
            $request->validated('after'),
        ));
    }

    public function products(ChatProductsRequest $request, string $sessionUuid, string $messageUuid): JsonResponse
    {
        return ApiResponse::success($this->chat->more(
            $this->chat->owned($request, $sessionUuid), $messageUuid, (int) $request->validated('page'),
        ));
    }

    public function contact(ChatContactRequest $request): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success($this->chat->contact($this->chat->owned($request, $data['session_uuid']), $data));
    }
}
