<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

try {
    $pdo = db();
    $stmt = $pdo->query('SELECT 1 AS ok');
    $result = $stmt->fetch();

    json_response([
        'database' => ($result && (int) $result['ok'] === 1) ? 'connected' : 'unknown',
        'message' => 'Conexión a MySQL correcta.',
    ]);
} catch (Throwable $error) {
    json_response([
        'database' => 'error',
        'message' => $error->getMessage(),
    ], 500);
}
