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
#[OA\SecurityScheme(
    securityScheme: 'n8nToken',
    type: 'apiKey',
    in: 'header',
    name: 'X-N8N-Token',
    description: 'Token compartido con n8n (N8N_TOKEN del .env).'
)]
class OpenApi
{
}