<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Se le avisa al cliente que rechazamos el comprobante y por qué. */
class DepositRejectedMail extends Mailable
{
    use Queueable;

    public function __construct(public Order $order, public string $reason)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'No pudimos aprobar tu comprobante del pedido N.º ' . $this->order->id);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.deposit-rejected');
    }
}
