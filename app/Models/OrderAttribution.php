<?php

namespace App\Models;

use App\Support\Attribution;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where one order came from.
 *
 * Written once by App\Services\Commerce\AttributionService when the order is
 * placed, and read afterwards. Nothing here recalculates anything - the values
 * are a snapshot of what the visitor's cookie said at that moment.
 */
class OrderAttribution extends Model
{
    use HasFactory;

    protected $table = 'tbl_order_attributions';

    protected $fillable = [
        'first_source', 'first_medium', 'first_campaign', 'first_term',
        'first_content', 'first_referrer', 'first_landing_page', 'first_at',

        'last_source', 'last_medium', 'last_campaign', 'last_term',
        'last_content', 'last_referrer', 'last_landing_page', 'last_at',

        'visitor_token',
    ];

    protected function casts(): array
    {
        return [
            'first_at' => 'datetime',
            'last_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /* -------------------------------------------------------------- reading */

    /**
     * "google / organic", the shorthand a list column wants.
     */
    public function firstLabel(): string
    {
        return $this->pair($this->first_source, $this->first_medium);
    }

    public function lastLabel(): string
    {
        return $this->pair($this->last_source, $this->last_medium);
    }

    /**
     * Whether the two touches differ. The admin screen leans on this: when
     * they are the same there is nothing to compare and the second block is
     * just noise.
     */
    public function touchesDiffer(): bool
    {
        return $this->first_source !== $this->last_source
            || $this->first_medium !== $this->last_medium
            || $this->first_campaign !== $this->last_campaign;
    }

    /**
     * Whether the order carried explicit utm_* values. Only a tagged link
     * produces a campaign, term or content, so their presence is the test.
     */
    public function hasUtm(): bool
    {
        return filled($this->last_campaign) || filled($this->last_term) || filled($this->last_content);
    }

    private function pair(?string $source, ?string $medium): string
    {
        return Attribution::label($source).' / '.Attribution::label($medium);
    }
}
