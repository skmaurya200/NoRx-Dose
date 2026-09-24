{{--
    One attribution breakdown.

    Its own partial because the analytics screen draws four of these and they
    differ only in which columns they group by. The bar behind each row is its
    share of the period's revenue, which reads faster than the figures do.

    @var \Illuminate\Support\Collection $rows
    @var array  $columns  heading => property
    @var string $empty
--}}
@php
    use App\Support\Attribution;

    $symbol = config('shop.currency_symbol', '$');
    $top = $rows->max('revenue') ?: 0;
@endphp

@if ($rows->isEmpty())
    <p class="field-hint mb-0">{{ $empty }}</p>
@else
    <table class="data-table">
        <thead>
            <tr>
                @foreach ($columns as $heading => $property)
                    <th>{{ $heading }}</th>
                @endforeach
                <th class="num">Orders</th>
                <th class="num">Revenue</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($columns as $heading => $property)
                        <td data-label="{{ $heading }}">
                            @if ($loop->first)
                                <b>{{ Attribution::label($row->{$property}) }}</b>
                            @else
                                {{ Attribution::label($row->{$property}) }}
                            @endif
                        </td>
                    @endforeach

                    <td class="num" data-label="Orders">{{ number_format($row->orders) }}</td>
                    <td class="num" data-label="Revenue">
                        <b>{{ $symbol }}{{ number_format($row->revenue, 2) }}</b>
                        {{-- Relative to the biggest row, so the largest channel
                             fills the bar and the rest read against it. --}}
                        <span class="attr-bar" aria-hidden="true">
                            <span style="width: {{ $top > 0 ? round($row->revenue / $top * 100) : 0 }}%"></span>
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
