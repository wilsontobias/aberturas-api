<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Se le manda al cliente la boleta (PDF adjunto) cuando su pedido sale. */
class InvoiceMail extends Mailable
{
    use Queueable;

    public function __construct(public Order $order, public Invoice $invoice, public string $pdf)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu pedido N.º ' . $this->order->id . ' salió: boleta ' . $this->invoice->number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, 'boleta-' . $this->invoice->number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
