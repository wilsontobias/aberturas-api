<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Se le avisa al cliente que aprobamos el comprobante de su seña. */
class DepositApprovedMail extends Mailable
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Aprobamos tu seña del pedido N.º ' . $this->order->id);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.deposit-approved');
    }
}
