<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "A new order has been placed" - the full summary, sent to the Admin.
 *
 * A plain HTML view rather than Markdown: the customer's name, address and
 * notes go in here, and Markdown would turn "[click](http://...)" typed into a
 * notes box into a live link in the Admin's inbox. Blade escaping alone is
 * enough for HTML.
 *
 * The card is described by brand and last four digits only. The stored number
 * and security code never go into an email.
 */
class OrderPlacedMail extends Mailable
{
    public function __construct(
        public readonly Order $order,
        public readonly string $siteName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New order {$this->order->order_number} - {$this->order->money((float) $this->order->grand_total)}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.orders.placed',
        );
    }
}
