<?php
// Configuración de conexión. Ajusta usuario/clave a tu MySQL (XAMPP: root sin clave).
return [
    'db' => [
        'host'    => getenv('P05_DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('P05_DB_PORT') ?: '3306',
        'name'    => getenv('P05_DB_NAME') ?: 'practica05_bioinformatica',
        'user'    => getenv('P05_DB_USER') ?: 'root',
        'pass'    => getenv('P05_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    // Orígenes permitidos si el front se sirve desde otro puerto (Live Server). Mismo origen no necesita CORS.
    'cors_origins' => ['http://localhost:5500', 'http://127.0.0.1:5500', 'http://localhost:8080'],
];
