<?php

return [

    'allowed_origins' => [
        // Angular local
        'http://localhost:4200',
        'https://localhost:4200',
        'http://127.0.0.1',
        'http://127.0.0.1:4200',
        '*', // si quieres permitir todo durante pruebas
        'http://172.31.208.1:4200',

        // Lo que te está saliendo en el error del navegador
        'https://localhost',

        // Cuando la app corre dentro de Capacitor (Android/iOS)
        'capacitor://localhost',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,
    'supports_credentials' => false, // usa true solo si trabajas con cookies/sesiones
];
