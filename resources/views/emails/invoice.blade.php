<!DOCTYPE html>
<html lang="es">
<body style="font-family: Arial, sans-serif; color: #212529; max-width: 560px;">
    <h2>¡Tu pedido salió!</h2>

    <p>Hola {{ $order->user->name }},</p>

    <p>
        Tu pedido <strong>N.º {{ $order->id }}</strong> ya fue enviado.
        Te adjuntamos la boleta <strong>{{ $invoice->number }}</strong> en PDF.
    </p>

    <p>
        El saldo de ${{ number_format($order->balance_amount, 2, ',', '.') }} lo abonás en efectivo al recibir el pedido.
        También podés descargar la boleta desde "Mis pedidos".
    </p>

    <p>¡Gracias por elegirnos!</p>
</body>
</html>
