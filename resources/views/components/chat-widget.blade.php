{{--
    The storefront chat.

    Everything the script needs arrives as a data attribute on the root, and
    every one of them is a named route - there is no key, no model name and no
    provider detail anywhere in this markup, because it is served to the public
    on every page.

    The panel is rendered empty. Messages are drawn by public/js/chat.js from
    the API, as text nodes rather than markup, so a visitor's own words can
    never come back as HTML.
--}}
@if (config('chat.enabled'))
<aside id="talkToUs" class="talk"
       data-session-url="{{ route('api.chat.session') }}"
       data-message-url="{{ route('api.chat.message') }}"
       data-history-url="{{ route('api.chat.history', ['sessionUuid' => '__SESSION__']) }}"
       data-products-url="{{ route('api.chat.products', ['sessionUuid' => '__SESSION__', 'messageUuid' => '__MESSAGE__']) }}"
       data-poll-seconds="{{ (int) config('chat.poll_seconds', 8) }}">

    <button class="talk__launcher" id="talkOpen" type="button" aria-controls="talkPanel" aria-expanded="false">
        <span class="talk__launcher-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
            </svg>
        </span>
        <span class="talk__launcher-text">Talk to Us</span>
        <span class="talk__launcher-dot" id="talkDot" hidden aria-hidden="true"></span>
    </button>

    <section id="talkPanel" class="talk__panel" role="dialog" aria-labelledby="talkTitle" aria-modal="false" hidden>
        <header class="talk__header">
            <span class="talk__avatar" aria-hidden="true">✦</span>
            <div class="talk__titles">
                <h2 id="talkTitle">Talk to Us</h2>
                <p class="talk__presence" id="talkPresence">
                    <span class="talk__pulse" aria-hidden="true"></span>
                    Store assistant · answers from our catalogue
                </p>
            </div>
            <button id="talkClose" class="talk__close" type="button" aria-label="Close chat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="talk__scroll" id="talkScroll">
            {{-- No written greeting: the assistant's own welcome, with its own
                 suggestions, arrives as the first message. --}}
            <!-- <div class="talk__intro">
                <p class="talk__intro-note">
                    Please don’t share passwords, payment details or medical information.
                </p>
            </div> -->

            <button id="talkOlder" class="talk__secondary" type="button" hidden>Load earlier messages</button>

            <div id="talkMessages" role="log" aria-live="polite" aria-relevant="additions" aria-label="Chat messages"></div>

            {{-- Drawn while a reply is being worked out, and while a person is
                 typing one. Hidden from the log so it is not read out as a
                 message that arrived. --}}
            <div class="talk__typing" id="talkTyping" hidden aria-hidden="true">
                <span></span><span></span><span></span>
            </div>
        </div>

        <p id="talkError" class="talk__error" role="alert" hidden></p>
        <button id="talkRetry" class="talk__secondary talk__secondary--block" type="button" hidden>Reconnect chat</button>

        <form id="talkForm" class="talk__composer">
            <label class="visually-hidden" for="talkInput">Your message</label>
            <textarea id="talkInput" rows="1" maxlength="1000"
                      placeholder="Ask about a product or our store…" required></textarea>
            <button id="talkSend" type="submit" disabled aria-label="Send message">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                </svg>
            </button>
        </form>

        <p class="talk__privacy">Chat is saved for support. Contact details are never sent to AI.</p>
    </section>
</aside>
@endif
