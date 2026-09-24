{{--
    Every conversation a visitor has had with the store.

    Read-only here: opening one is where you answer it. Sorted by most recent
    activity, because an inbox is read from the top and the oldest conversation
    is almost never the one that needs you.
--}}
@extends('manager.components.layout')

@section('title', 'Conversations')

@php
    use App\Models\ChatSession;
    use App\Support\ManagerQuery;
@endphp

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">Conversations</h1>
            <p class="ph-sub">
                {{ $sessions->total() }} {{ Str::plural('conversation', $sessions->total()) }} &middot;
                open one to read it or to answer it yourself
            </p>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => 'All conversations', 'value' => $summary['total'], 'icon' => 'bi-chat-dots', 'tone' => 'icon-dark', 'query' => []],
            ['label' => 'Waiting on you', 'value' => $summary['unread'], 'icon' => 'bi-hourglass-split', 'tone' => 'icon-gold', 'query' => ['status' => 'unread']],
            ['label' => 'Answered by a person', 'value' => $summary['manual'], 'icon' => 'bi-person-check', 'tone' => 'icon-dark', 'query' => ['mode' => 'manual']],
            ['label' => 'Left contact details', 'value' => $summary['contacts'], 'icon' => 'bi-envelope', 'tone' => 'icon-gold', 'query' => ['status' => 'contacted']],
        ] as $card)
            <div class="col-6 col-xl-3">
                <a class="stat-card d-block" href="{{ ManagerQuery::url('manager.chats.index', $card['query']) }}">
                    <div class="stat-icon {{ $card['tone'] }}"><i class="bi {{ $card['icon'] }}"></i></div>
                    <div class="stat-label">{{ $card['label'] }}</div>
                    <div class="stat-value">{{ number_format($card['value']) }}</div>
                </a>
            </div>
        @endforeach
    </div>

    <form class="filter-bar" method="POST" action="{{ route('manager.query') }}" data-filter-form
          data-filter-reset-url="{{ route('manager.chats.index') }}">
        @csrf
        <input type="hidden" name="target" value="manager.chats.index">

        <div class="field fb-search">
            <label class="field-label" for="filterSearch">Search</label>
            <input type="search" class="field-input" id="filterSearch" name="search"
                   value="{{ $filters['search'] ?? '' }}" placeholder="Wording, name, email or reference…">
        </div>

        <div class="field">
            <label class="field-label" for="filterMode">Answered by</label>
            <select class="field-select" id="filterMode" name="mode">
                <option value="">Either</option>
                <option value="{{ ChatSession::MODE_AI }}" @selected(($filters['mode'] ?? '') === ChatSession::MODE_AI)>Assistant</option>
                <option value="{{ ChatSession::MODE_MANUAL }}" @selected(($filters['mode'] ?? '') === ChatSession::MODE_MANUAL)>A person</option>
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterStatus">Show</label>
            <select class="field-select" id="filterStatus" name="status">
                <option value="">Everything</option>
                <option value="unread" @selected(($filters['status'] ?? '') === 'unread')>Unread</option>
                <option value="contacted" @selected(($filters['status'] ?? '') === 'contacted')>Left contact details</option>
            </select>
        </div>

        <div class="fb-actions">
            <button type="submit" class="btn-ghost"><i class="bi bi-funnel"></i> Filter</button>
            <button type="button" class="btn-ghost" data-filter-reset>Clear</button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" @if ($sessions->isEmpty()) hidden @endif>
            <thead>
                <tr>
                    <th>Conversation</th>
                    <th>Visitor</th>
                    <th>Messages</th>
                    <th>Answered by</th>
                    <th>Last activity</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sessions as $chat)
                    <tr>
                        <td class="cell-primary">
                            <div class="min-w-0">
                                <a href="{{ route('manager.chats.show', $chat->id) }}" class="cell-title">
                                    {{ Str::limit($chat->latestMessage?->message ?? 'No messages yet', 70) }}
                                </a>
                                {{-- The uuid is how this conversation is
                                     referred to in the logs, so a shortened
                                     form of it is worth showing. --}}
                                <span class="cell-meta">#{{ Str::before($chat->session_uuid, '-') }}</span>
                            </div>
                        </td>

                        <td data-label="Visitor">
                            @if ($chat->latestContact)
                                {{ $chat->latestContact->name }}
                                <span class="cell-meta">{{ $chat->latestContact->email }}</span>
                            @else
                                <span class="cell-meta">Guest &mdash; no details left</span>
                            @endif
                        </td>

                        <td data-label="Messages">
                            {{ number_format($chat->messages_count) }}
                            @if ($chat->unread_count > 0)
                                <span class="badge-status badge-draft">{{ $chat->unread_count }} new</span>
                            @endif
                        </td>

                        <td data-label="Answered by">
                            <span class="badge-status {{ $chat->isManual() ? 'badge-draft' : 'badge-active' }}">
                                {{ $chat->isManual() ? 'A person' : 'Assistant' }}
                            </span>
                        </td>

                        <td data-label="Last activity">
                            {{ optional($chat->last_message_at ?? $chat->updated_at)->diffForHumans() }}
                        </td>

                        <td class="actions" data-label="Actions">
                            <a href="{{ route('manager.chats.show', $chat->id) }}"
                               class="row-btn" title="Open" aria-label="Open conversation">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="empty-state" @if ($sessions->isNotEmpty()) hidden @endif>
            <div class="es-icon"><i class="bi bi-chat-dots"></i></div>
            <h3>No conversations yet</h3>
            <p>Anything a visitor asks the chat widget on the storefront lands here.</p>
        </div>

        @if ($sessions->hasPages())
            <div class="table-foot">
                <span>Showing {{ $sessions->firstItem() }}&ndash;{{ $sessions->lastItem() }} of {{ $sessions->total() }}</span>
                {{ $sessions->onEachSide(1)->links('manager.partials.pagination') }}
            </div>
        @endif
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
