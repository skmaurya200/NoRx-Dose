{{--
    One row of a transcript.

    The author, not the sender, decides how a row is labelled: a reply typed by
    an admin and a reply written by the model are both the assistant side of
    the conversation, and telling them apart is the whole point of this screen.
--}}
@php
    use App\Models\ChatMessage;

    $author = $message->authored_by ?? ChatMessage::AUTHOR_AI;

    $label = match ($author) {
        ChatMessage::AUTHOR_GUEST => 'Visitor',
        ChatMessage::AUTHOR_MANAGER => $message->admin?->name ?? 'Support team',
        default => 'Assistant',
    };
@endphp

<article class="chat-msg chat-msg--{{ $author }}" data-message-id="{{ $message->message_uuid }}">
    <header class="chat-msg__head">
        <span class="chat-msg__who">{{ $label }}</span>
        <time datetime="{{ $message->created_at->toIso8601String() }}">
            {{ $message->created_at->format('j M, H:i') }}
        </time>
    </header>

    <div class="chat-msg__body">{{ $message->message }}</div>

    @if ($message->productResults->isNotEmpty())
        <ul class="chat-msg__products">
            @foreach ($message->productResults as $result)
                <li>
                    {{ $result->snapshot['name'] ?? 'Product' }}
                    <span>{{ $result->snapshot['price_formatted'] ?? '' }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($message->intent || $message->ai_model)
        <footer class="chat-msg__meta">
            @if ($message->intent)
                <span>{{ str_replace('_', ' ', $message->intent) }}</span>
            @endif
            @if ($message->ai_model)
                <span>{{ $message->ai_model }}</span>
            @endif
            @if ($message->tokens_used)
                <span>{{ number_format($message->tokens_used) }} tokens</span>
            @endif
        </footer>
    @endif
</article>
