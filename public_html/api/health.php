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
        'api/productos.php' => __DIR__ . '/productos.php',
        'api/compras.php' => __DIR__ . '/compras.php',
        'api/inventario.php' => __DIR__ . '/inventario.php',
        'api/usuarios.php' => __DIR__ . '/usuarios.php',
        'api/reportes.php' => __DIR__ . '/reportes.php',
        'api/bancos.php' => __DIR__ . '/bancos.php',
        'assets/app.js' => dirname(__DIR__) . '/assets/app.js',
        'assets/logo.svg' => dirname(__DIR__) . '/assets/logo.svg',
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
