<?php

namespace App\Services;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\Order;
use App\Support\SimplePdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Boletas: número correlativo único, PDF y envío por mail al cliente. */
class InvoiceService
{
    /** Devuelve la boleta del pedido; si todavía no existe, la emite con el próximo número. */
    public function issue(Order $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            $invoice = Invoice::where('order_id', $order->id)->first();

            if ($invoice) {
                return $invoice;
            }

            $invoice = Invoice::create([
                'order_id' => $order->id,
                'number' => 'tmp-' . uniqid(),
                'issued_at' => now(),
            ]);

            // El número sale del id (que es autoincremental y no se repite): B-000001, B-000002...
            $invoice->update(['number' => sprintf('B-%06d', $invoice->id)]);

            return $invoice;
        });
    }

    /** Al enviar el pedido: se emite la boleta y se la manda por mail al cliente. Si el mail falla, no se rompe nada. */
    public function sendOnShipment(Order $order): void
    {
        try {
            $order = Order::with('user.customerProfile', 'status', 'locality.province', 'items.product', 'payments.type', 'payments.status')
                ->find($order->id);

            $invoice = $this->issue($order);

            Mail::to($order->user->email)->send(new InvoiceMail($order, $invoice, $this->pdf($order, $invoice)));
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar la boleta del pedido ' . $order->id . ': ' . $e->getMessage());
        }
    }

    private function money($value): string
    {
        return '$ ' . number_format((float) $value, 2, ',', '.');
    }

    public function pdf(Order $order, Invoice $invoice): string
    {
        $pdf = new SimplePdf();
        $left = 50;
        $right = SimplePdf::WIDTH - 50;
        $profile = $order->user->customerProfile;

        // Encabezado
        $pdf->text($left, 60, 'Aberturas', 22, true);
        $pdf->text($right, 52, 'BOLETA', 16, true, 'right');
        $pdf->text($right, 68, 'N.º ' . $invoice->number, 11, true, 'right');
        $pdf->text($right, 82, 'Pedido N.º ' . $order->id . ' · Emitida: ' . $invoice->issued_at->format('d/m/Y'), 10, false, 'right');
        $pdf->line($left, 95, $right, 95, 1);

        // Cliente y entrega
        $customer = $profile ? trim($profile->first_name . ' ' . $profile->last_name) : $order->user->name;
        $y = 120;
        $pdf->text($left, $y, 'Cliente', 10, true);
        $pdf->text($left, $y + 15, $customer, 10);
        $pdf->text($left, $y + 29, $order->user->email, 10);

        if ($profile && $profile->phone_1) {
            $pdf->text($left, $y + 43, 'Tel.: ' . $profile->phone_1, 10);
        }

        $pdf->text(310, $y, 'Entrega', 10, true);
        $pdf->text(310, $y + 15, $order->street . ' ' . $order->street_number, 10);
        $pdf->text(310, $y + 29, $order->locality->name . ', ' . $order->locality->province->name, 10);
        $pdf->text(310, $y + 43, 'Envío estimado: ' . ($order->estimated_ship_date ? $order->estimated_ship_date->format('d/m/Y') : 'a confirmar'), 10);

        // Tabla de productos
        $y = 210;
        $pdf->fillRect($left, $y, $right - $left, 20, 0.9);
        $pdf->text($left + 6, $y + 14, 'Producto', 10, true);
        $pdf->text(340, $y + 14, 'Cant.', 10, true, 'right');
        $pdf->text(430, $y + 14, 'Precio unit.', 10, true, 'right');
        $pdf->text($right - 6, $y + 14, 'Subtotal', 10, true, 'right');
        $y += 38;

        foreach ($order->items as $item) {
            if ($y > 740) {
                $pdf->addPage();
                $y = 60;
            }

            $name = $item->product->name;

            while ($pdf->textWidth($name, 10) > 240 && mb_strlen($name) > 4) {
                $name = mb_substr($name, 0, -2);
            }

            if ($name !== $item->product->name) {
                $name .= '...';
            }

            $pdf->text($left + 6, $y, $name, 10);
            $pdf->text(340, $y, (string) $item->quantity, 10, false, 'right');
            $pdf->text(430, $y, $this->money($item->unit_price), 10, false, 'right');
            $pdf->text($right - 6, $y, $this->money($item->subtotal), 10, false, 'right');
            $pdf->text($left + 6, $y + 11, $item->from_stock ? 'De stock' : 'A fabricar', 8);
            $pdf->line($left, $y + 18, $right, $y + 18, 0.3);
            $y += 30;
        }

        // Totales
        if ($y > 640) {
            $pdf->addPage();
            $y = 60;
        }

        $y += 10;
        $pdf->text(430, $y, 'Total', 11, true, 'right');
        $pdf->text($right - 6, $y, $this->money($order->total), 11, true, 'right');
        $pdf->text(430, $y + 18, 'Seña (' . $order->deposit_percentage . ' %)', 10, false, 'right');
        $pdf->text($right - 6, $y + 18, $this->money($order->deposit_amount), 10, false, 'right');
        $pdf->text(430, $y + 34, 'Saldo a pagar al recibir', 10, false, 'right');
        $pdf->text($right - 6, $y + 34, $this->money($order->balance_amount), 10, false, 'right');
        $y += 70;

        // Pagos
        $pdf->text($left, $y, 'Pagos registrados', 10, true);
        $y += 16;
        $payments = $order->payments->sortBy('id');

        if ($payments->isEmpty()) {
            $pdf->text($left, $y, 'Sin pagos registrados.', 9);
        }

        $typeNames = ['deposit' => 'Seña', 'balance' => 'Saldo'];
        $statusNames = ['pending' => 'En revisión', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'];

        foreach ($payments as $payment) {
            $type = $typeNames[$payment->type->name] ?? $payment->type->name;
            $status = $statusNames[$payment->status->name] ?? $payment->status->name;

            $pdf->text($left, $y, $payment->created_at->format('d/m/Y') . '  ' . $type . '  ' . $this->money($payment->amount) . '  (' . $status . ')', 9);
            $y += 13;
        }

        $pdf->line($left, 790, $right, 790, 0.5);
        $pdf->text(SimplePdf::WIDTH / 2, 806, 'Gracias por tu compra. Este documento es un comprobante del pedido.', 8, false, 'center');

        return $pdf->output();
    }
}
