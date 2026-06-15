<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

try {
    $pdo = db();
    $stmt = $pdo->query('SELECT 1 AS ok');
    $result = $stmt->fetch();

    $requiredFiles = [
        'api/config.php' => __DIR__ . '/config.php',
        'api/login.php' => __DIR__ . '/login.php',
        'api/venta.php' => __DIR__ . '/venta.php',
        'assets/app.js' => dirname(__DIR__) . '/assets/app.js',
        'index.html' => dirname(__DIR__) . '/index.html',
    ];

    $files = [];
    foreach ($requiredFiles as $label => $path) {
        $files[$label] = file_exists($path) ? 'ok' : 'missing';
    }

    json_response([
        'database' => ($result && (int) $result['ok'] === 1) ? 'connected' : 'unknown',
        'files' => $files,
        'message' => 'Conexión a MySQL correcta.',
    ]);
} catch (Throwable $error) {
    json_response([
        'database' => 'error',
        'message' => $error->getMessage(),
    ], 500);
}
