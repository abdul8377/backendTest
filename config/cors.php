<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
        'allowed_methods' => ['*'],
        'allowed_origins' => [
        'http://localhost',
        'https://localhost',
        'http://localhost:4200',
        'https://localhost:4200',
        'http://127.0.0.1',
        'http://127.0.0.1:4200',
        '*', // si quieres permitir todo durante pruebas
    ],

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false, // usa true solo si trabajas con cookies/sesiones
];
