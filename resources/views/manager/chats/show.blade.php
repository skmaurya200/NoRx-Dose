{{--
    One conversation, with the switch that decides who answers the next
    message and the box for answering it yourself.

    The transcript is rendered server-side and then kept up to date by
    public/js/manager/chat-inbox.js, which polls for anything the visitor sends
    while this screen is open. Replying and switching mode post to the API.
--}}
@extends('manager.components.layout')

@section('title', 'Conversation')

@php
    use App\Models\ChatMessage;
    use App\Models\ChatSession;
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/manager/chat-inbox.css') }}">
@endpush

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">Conversation</h1>
            <p class="ph-sub">
                Reference #{{ Str::before($session->session_uuid, '-') }} &middot;
                started {{ $session->created_at->diffForHumans() }}
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.chats.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Back to conversations
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="form-card chat-card"
                 data-chat-thread
                 data-messages-url="{{ route('api.manager.chats.messages', $session->id) }}"
                 data-reply-url="{{ route('api.manager.chats.reply', $session->id) }}"
                 data-mode-url="{{ route('api.manager.chats.mode', $session->id) }}"
                 data-last-uuid="{{ $lastUuid }}"
                 data-poll-seconds="{{ (int) config('chat.poll_seconds', 8) }}">

                <div class="chat-log" data-chat-log role="log" aria-live="polite" aria-label="Conversation">
                    @forelse ($messages as $message)
                        @include('manager.chats.partials.message', ['message' => $message])
                    @empty
                        <p class="chat-empty">This visitor has not said anything yet.</p>
                    @endforelse
                </div>

                <p class="chat-status" data-chat-status role="status" hidden></p>
                <p class="field-error" data-chat-error role="alert" hidden></p>

                <form class="chat-composer" data-chat-form>
                    @csrf
                    <label class="visually-hidden" for="replyMessage">Your reply</label>
                    <textarea class="field-textarea" id="replyMessage" name="message" rows="3" maxlength="2000"
                              placeholder="Type a reply to this visitor…" data-chat-input></textarea>
                    <div class="chat-composer__foot">
                        <span class="field-hint">
                            Sending a reply takes this conversation over &mdash; the assistant stops answering it.
                        </span>
                        <button type="submit" class="btn-gold" data-chat-send>
                            <span class="spinner" aria-hidden="true"></span>
                            Send reply
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-card">
                <h2 class="form-card-title">Who answers</h2>
                <p class="form-card-sub">
                    Switch to a person and the assistant stays out of this conversation until you switch back.
                </p>

                <div class="switch-row">
                    <div class="sr-text">
                        <div class="sr-title" data-mode-title>
                            {{ $session->isManual() ? 'You are answering' : 'The assistant is answering' }}
                        </div>
                        <div class="sr-sub">
                            {{ ChatSession::MODE_MANUAL }} turns the assistant off for this visitor only
                        </div>
                    </div>
                    <input type="checkbox" class="switch" data-mode-toggle
                           @checked($session->isManual())
                           aria-label="Answer this conversation yourself">
                </div>
            </div>

            <div class="form-card">
                <h2 class="form-card-title">Visitor</h2>

                @if ($contact)
                    <dl class="chat-facts">
                        <dt>Name</dt>
                        <dd>{{ $contact->name }}</dd>
                        <dt>Email</dt>
                        <dd><a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a></dd>
                        <dt>Phone</dt>
                        <dd><a href="tel:{{ $contact->phone }}">{{ $contact->phone }}</a></dd>
                        <dt>Left at</dt>
                        <dd>{{ $contact->created_at->format('j M Y, H:i') }}</dd>
                    </dl>
                @else
                    <p class="form-card-sub mb-0">
                        This visitor has not left contact details. The chat asks for them whenever the
                        assistant cannot answer something.
                    </p>
                @endif
            </div>

            <div class="form-card">
                <h2 class="form-card-title">Conversation</h2>
                <dl class="chat-facts">
                    <dt>Messages</dt>
                    <dd data-chat-count>{{ $messages->count() }}</dd>
                    <dt>Started</dt>
                    <dd>{{ $session->created_at->format('j M Y, H:i') }}</dd>
                    <dt>Last activity</dt>
                    <dd>{{ optional($session->last_message_at ?? $session->updated_at)->format('j M Y, H:i') }}</dd>
                    <dt>Tokens used</dt>
                    <dd>{{ number_format($messages->sum('tokens_used')) }}</dd>
                </dl>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/chat-inbox.js') }}"></script>
@endpush
