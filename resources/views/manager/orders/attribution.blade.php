{{--
    Where an order came from.

    Two touches, because they answer different questions: the first says how
    this customer found the shop, the last says what brought them back to buy.
    They are often the same, and the panel says so rather than printing the
    same four lines twice.

    Everything here is printed with {{ }}. A referrer is an outside URL and a
    campaign name is whatever somebody typed into a link - neither is trusted
    markup, and neither is treated as any.

    @var \App\Models\OrderAttribution|null $attribution
--}}
@php
    use App\Support\Attribution;
@endphp

<div class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Attribution</h2>
        @if ($attribution)
            <span class="badge-status badge-muted">{{ $attribution->lastLabel() }}</span>
        @endif
    </div>

    @if (! $attribution)
        {{-- Placed before tracking existed, or by a visitor whose browser sent
             no cookie. Said plainly rather than shown as "direct", which would
             be a claim the shop cannot make. --}}
        <p class="field-hint mb-0">
            <i class="bi bi-info-circle"></i>
            No attribution recorded for this order.
        </p>
    @else
        <div class="attr-touch">
            <span class="attr-touch__label">First touch</span>
            <p class="attr-touch__pair">{{ $attribution->firstLabel() }}</p>

            <dl class="kv kv--tight">
                <div class="kv-row"><dt>Source</dt><dd>{{ Attribution::label($attribution->first_source) }}</dd></div>
                <div class="kv-row"><dt>Medium</dt><dd>{{ Attribution::label($attribution->first_medium) }}</dd></div>
                <div class="kv-row"><dt>Campaign</dt><dd>{{ Attribution::label($attribution->first_campaign) }}</dd></div>
                <div class="kv-row">
                    <dt>Landing page</dt>
                    <dd class="attr-url">{{ Attribution::label($attribution->first_landing_page) }}</dd>
                </div>
                <div class="kv-row">
                    <dt>Referrer</dt>
                    <dd class="attr-url">{{ Attribution::label($attribution->first_referrer) }}</dd>
                </div>
                <div class="kv-row"><dt>Seen</dt><dd>{{ $attribution->first_at?->format('j M Y, H:i') ?? '—' }}</dd></div>
            </dl>
        </div>

        @if ($attribution->touchesDiffer())
            <div class="attr-touch">
                <span class="attr-touch__label">Last touch</span>
                <p class="attr-touch__pair">{{ $attribution->lastLabel() }}</p>

                <dl class="kv kv--tight">
                    <div class="kv-row"><dt>Source</dt><dd>{{ Attribution::label($attribution->last_source) }}</dd></div>
                    <div class="kv-row"><dt>Medium</dt><dd>{{ Attribution::label($attribution->last_medium) }}</dd></div>
                    <div class="kv-row"><dt>Campaign</dt><dd>{{ Attribution::label($attribution->last_campaign) }}</dd></div>
                    <div class="kv-row">
                        <dt>Landing page</dt>
                        <dd class="attr-url">{{ Attribution::label($attribution->last_landing_page) }}</dd>
                    </div>
                    <div class="kv-row">
                        <dt>Referrer</dt>
                        <dd class="attr-url">{{ Attribution::label($attribution->last_referrer) }}</dd>
                    </div>
                    <div class="kv-row"><dt>Seen</dt><dd>{{ $attribution->last_at?->format('j M Y, H:i') ?? '—' }}</dd></div>
                </dl>
            </div>
        @else
            <p class="field-hint">
                <i class="bi bi-arrow-repeat"></i>
                Same source both times &mdash; this customer arrived and bought from one place.
            </p>
        @endif

        @if ($attribution->hasUtm())
            <div class="attr-touch">
                <span class="attr-touch__label">Campaign tags</span>

                <dl class="kv kv--tight">
                    <div class="kv-row"><dt>utm_source</dt><dd>{{ Attribution::label($attribution->last_source) }}</dd></div>
                    <div class="kv-row"><dt>utm_medium</dt><dd>{{ Attribution::label($attribution->last_medium) }}</dd></div>
                    <div class="kv-row"><dt>utm_campaign</dt><dd>{{ Attribution::label($attribution->last_campaign) }}</dd></div>
                    <div class="kv-row"><dt>utm_term</dt><dd>{{ Attribution::label($attribution->last_term) }}</dd></div>
                    <div class="kv-row"><dt>utm_content</dt><dd>{{ Attribution::label($attribution->last_content) }}</dd></div>
                </dl>
            </div>
        @endif
    @endif
</div>
