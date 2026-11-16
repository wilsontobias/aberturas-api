<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Le avisa a n8n de lo que pasa en el sistema (webhook).
 * Si n8n no está configurado o no responde, el sistema sigue funcionando:
 * el error queda en el log y no se corta lo que estaba haciendo el usuario.
 */
class N8nNotifier
{
    /** Evento: un cliente subió el comprobante de la seña. */
    public function receiptUploaded(Order $order, Payment $payment): void
    {
        $order->loadMissing('user');

        $this->send('receipt_uploaded', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => (float) $payment->amount,
            'deposit_percentage' => $order->deposit_percentage,
            'total' => (float) $order->total,
            'requires_manufacturing' => (bool) $order->requires_manufacturing,
            'customer' => [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
            ],
            'uploaded_at' => now()->toIso8601String(),
        ]);
    }

    private function send(string $event, array $data): void
    {
        $url = config('services.n8n.webhook_url');

        if (!$url) {
            return;
        }

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->withHeaders(['X-N8N-Token' => (string) config('services.n8n.token')])
                ->post($url, ['event' => $event] + $data);

            if (!$response->successful()) {
                Log::error("n8n respondió con error al enviar el evento {$event}.", [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("No se pudo avisar a n8n el evento {$event}: " . $e->getMessage());
        }
    }
}
