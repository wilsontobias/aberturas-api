<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'API Sistema de Gestión de Pedidos de Aberturas',
    version: '1.0.0',
    description: 'API REST para gestionar los pedidos de una empresa de aberturas.'
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000',
    description: 'Servidor local'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token Sanctum',
    description: 'Token de autenticación generado por Laravel Sanctum.'
)]
class OpenApi
{
}