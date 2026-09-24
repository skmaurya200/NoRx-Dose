<?php

namespace App\Models;

use App\Support\PaymentCard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The payment behind an order.
 *
 * The card number is encrypted at rest by the cast below - it is written and
 * read as plain text in PHP and stored as ciphertext, so a database dump
 * without APP_KEY yields nothing.
 *
 * The panel displays it in full, at the operator's request. That is a
 * deliberate choice with a cost: PCI-DSS 3.3 caps a display at first-six and
 * last-four, so any screen or screenshot carrying fullNumber() is in scope for
 * a great deal more than the rest of this application. partialNumber() is kept
 * beside it for anywhere that does not need the whole thing, and the customer's
 * own receipt still uses maskedNumber().
 *
 * The security code is kept too, at the shop owner's instruction, and is
 * encrypted the same way. That reverses this project's original decision; the
 * reasoning and the trade-off are recorded in the migration that added the
 * column, and it can be switched off with SHOP_STORE_CARD_CVC=false.
 *
 * Both values are hidden below, so neither reaches an API resource, the
 * customer's receipt or a stray toArray(). The panel reads them explicitly.
 */
class OrderPayment extends Model
{
    use HasFactory;

    protected $table = 'tbl_order_payments';

    protected $fillable = [
        'method',
        'card_holder',
        'card_brand',
        'card_last4',
        'card_number',
        'card_cvc',
        'exp_month',
        'exp_year',
        'status',
        'reference',
        'amount',
        'currency',
        'paid_at',
    ];

    /**
     * Neither value leaves the server by accident: they are in no resource and
     * no customer-facing view, and hidden here so a stray toArray() cannot put
     * one in a response. The panel asks for them by name.
     */
    protected $hidden = ['card_number', 'card_cvc'];

    protected function casts(): array
    {
        return [
            // AES-256-CBC with an HMAC, keyed on APP_KEY. Rotating that key
            // without keeping the old one in APP_PREVIOUS_KEYS makes existing
            // rows unreadable - which is the intended failure mode.
            'card_number' => 'encrypted',
            'card_cvc' => 'encrypted',
            'amount' => 'decimal:2',
            'exp_month' => 'integer',
            'exp_year' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * What a receipt or the panel shows, e.g. "Visa •••• 4242".
     */
    public function maskedNumber(): string
    {
        if (! $this->card_last4) {
            return $this->card_brand ?: 'Card';
        }

        return trim(($this->card_brand ?: 'Card').' •••• '.$this->card_last4);
    }

    /**
     * The whole number, in the groups it is printed in - four-four-four-four
     * for most schemes, four-six-five for American Express, which is how the
     * digits are actually laid out on the card.
     *
     * Only the panel calls this. Nothing that reaches a customer, an API
     * response or a log ever does.
     */
    public function fullNumber(): ?string
    {
        $digits = $this->card_number;

        if (! $digits) {
            return null;
        }

        if ($this->card_brand === 'American Express' && strlen($digits) === 15) {
            return implode(' ', [
                substr($digits, 0, 4),
                substr($digits, 4, 6),
                substr($digits, 10),
            ]);
        }

        return implode(' ', str_split($digits, 4));
    }

    /**
     * The most of a card number anyone is shown: the first six digits and the
     * last four, with the middle masked - e.g. "4242 42•• •••• 4242".
     *
     * That split is not arbitrary. The first six are the issuer identification
     * number, which identifies the bank and the scheme rather than the
     * cardholder, and PCI-DSS 3.3 names first-six/last-four as the maximum a
     * display may show. Used wherever the whole number is not needed.
     */
    public function partialNumber(): ?string
    {
        $digits = $this->card_number;

        if (! $digits || strlen($digits) < 12) {
            return $this->card_last4 ? '•••• •••• •••• '.$this->card_last4 : null;
        }

        $masked = substr($digits, 0, 6)
            .str_repeat('•', strlen($digits) - 10)
            .substr($digits, -4);

        // mb_str_split, not chunk_split: the mask character is three bytes in
        // UTF-8 and a byte-wise chunker would cut one in half.
        return implode(' ', mb_str_split($masked, 4));
    }

    /**
     * The security code as the panel prints it, or a word explaining why there
     * is nothing to print - an order placed before the column existed, or
     * while SHOP_STORE_CARD_CVC was off, has none and never will.
     */
    public function cvcLabel(): ?string
    {
        return $this->card_cvc ?: null;
    }

    /**
     * The issuer identification number - the part of a card that identifies
     * the bank, not the person. Useful when a payment is disputed.
     */
    public function binLabel(): ?string
    {
        $digits = $this->card_number;

        return $digits && strlen($digits) >= 6 ? substr($digits, 0, 6) : null;
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'paid' => 'badge-active',
            'failed' => 'badge-danger',
            'refunded' => 'badge-warn',
            default => 'badge-muted',
        };
    }

    public function expiryLabel(): ?string
    {
        if (! $this->exp_month || ! $this->exp_year) {
            return null;
        }

        return sprintf('%02d / %02d', $this->exp_month, $this->exp_year % 100);
    }

    public function hasExpired(): bool
    {
        return $this->exp_month !== null
            && $this->exp_year !== null
            && PaymentCard::isExpired($this->exp_month, $this->exp_year);
    }
}
