<!DOCTYPE html>
<html lang="es">
<body style="font-family: Arial, sans-serif; color: #212529; max-width: 560px;">
    <h2>¡Aprobamos tu seña!</h2>

    <p>Hola {{ $order->user->name }},</p>

    <p>
        Verificamos el comprobante de la seña de tu pedido <strong>N.º {{ $order->id }}</strong>
        ({{ $order->deposit_percentage }} %, ${{ number_format($order->deposit_amount, 2, ',', '.') }}).
        Tu pedido quedó confirmado.
    </p>

    @if ($order->estimated_ship_date)
        <p>
            <strong>Fecha estimada de envío:</strong>
            {{ $order->estimated_ship_date->format('d/m/Y') }}
        </p>
    @endif

    <p>
        El saldo de ${{ number_format($order->balance_amount, 2, ',', '.') }} lo abonás en efectivo al recibir el pedido.
        Podés seguir el estado de tu pedido en cualquier momento desde "Mis pedidos".
    </p>

    <p>¡Gracias por elegirnos!</p>
</body>
</html>
