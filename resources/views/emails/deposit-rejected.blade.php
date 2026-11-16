<!DOCTYPE html>
<html lang="es">
<body style="font-family: Arial, sans-serif; color: #212529; max-width: 560px;">
    <h2>No pudimos aprobar tu comprobante</h2>

    <p>Hola {{ $order->user->name }},</p>

    <p>
        Revisamos el comprobante de la seña de tu pedido <strong>N.º {{ $order->id }}</strong>
        y no pudimos aprobarlo.
    </p>

    <p><strong>Motivo:</strong> {{ $reason }}</p>

    <p>
        Tu pedido sigue pendiente de pago. Ingresá a "Mis pedidos" y subí un comprobante nuevo
        de ${{ number_format($order->deposit_amount, 2, ',', '.') }} (JPG, PNG o PDF de hasta 2 MB).
    </p>

    <p>Cualquier duda, escribinos.</p>
</body>
</html>
