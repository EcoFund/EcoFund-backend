<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'EcoFund API',
    version: '1.0.0',
    description: 'Dokumentasi API backend EcoFund.'
)]
#[OA\Server(
    url: '/api',
    description: 'Server API'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Gunakan format: Bearer {token}'
)]
class OpenApiSpec
{
}
