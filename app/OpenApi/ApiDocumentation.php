<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'NFT Payroll API',
    description: 'Şirket, çalışan ve bordro NFT süreçlerini yöneten API.'
)]
#[OA\Server(
    url: '/api',
    description: 'Laravel API base path'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token',
    description: 'Authorization: Bearer {token}'
)]
#[OA\Tag(name: 'System', description: 'Sistem uçları')]
#[OA\Tag(name: 'Auth', description: 'Kimlik doğrulama')]
#[OA\Tag(name: 'Companies', description: 'Şirket yönetimi')]
#[OA\Schema(
    schema: 'User',
    required: ['id', 'name', 'email'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Veysel'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
        new OA\Property(property: 'role', type: 'string', example: 'owner'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AuthResponse',
    required: ['user', 'token'],
    properties: [
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(property: 'token', type: 'string', example: '1|abcdef...'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Company',
    required: ['id', 'name', 'owner_id'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'owner_id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Acme Inc.'),
        new OA\Property(property: 'type', type: 'string', nullable: true, example: 'LLC'),
        new OA\Property(property: 'tax_number', type: 'string', nullable: true, example: '1234567890'),
        new OA\Property(property: 'country', type: 'string', nullable: true, example: 'TR'),
        new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Istanbul'),
        new OA\Property(property: 'address', type: 'string', nullable: true, example: 'Levent'),
    ],
    type: 'object'
)]
class ApiDocumentation
{
    #[OA\Get(
        path: '/health',
        summary: 'API sağlık kontrolü',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sağlıklı durum',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'abi_exists', type: 'boolean', example: true),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function health(): void
    {
    }

    #[OA\Post(
        path: '/register',
        summary: 'Yeni kullanıcı kaydı',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Veysel'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'secret123'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Kayıt başarılı', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse')),
        ]
    )]
    public function register(): void
    {
    }

    #[OA\Post(
        path: '/login',
        summary: 'Giriş yap',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'secret123'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Giriş başarılı', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse')),
        ]
    )]
    public function login(): void
    {
    }

    #[OA\Get(
        path: '/me',
        summary: 'Giriş yapan kullanıcı',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Kullanıcı bilgisi', content: new OA\JsonContent(ref: '#/components/schemas/User')),
        ]
    )]
    public function me(): void
    {
    }

    #[OA\Post(
        path: '/logout',
        summary: 'Mevcut token ile çıkış',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Çıkış başarılı',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Çıkış yapıldı'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function logout(): void
    {
    }

    #[OA\Get(
        path: '/companies',
        summary: 'Kullanıcının şirketlerini listele',
        security: [['sanctum' => []]],
        tags: ['Companies'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Şirket listesi',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Company'))
            ),
        ]
    )]
    public function companiesIndex(): void
    {
    }

    #[OA\Post(
        path: '/companies',
        summary: 'Şirket oluştur',
        security: [['sanctum' => []]],
        tags: ['Companies'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Acme Inc.'),
                    new OA\Property(property: 'type', type: 'string', nullable: true, example: 'LLC'),
                    new OA\Property(property: 'tax_number', type: 'string', nullable: true, example: '1234567890'),
                    new OA\Property(property: 'country', type: 'string', nullable: true, example: 'TR'),
                    new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Istanbul'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Şirket oluşturuldu', content: new OA\JsonContent(ref: '#/components/schemas/Company')),
        ]
    )]
    public function companiesStore(): void
    {
    }
}
