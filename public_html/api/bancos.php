<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
$usuarioSesion = current_user();
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $bancos = $pdo->query('SELECT id, nombre, numero_cuenta, moneda, estado FROM bancos ORDER BY nombre')->fetchAll();
    $pagos = $pdo->query('SELECT p.id, b.nombre AS banco, p.usuario_nombre, p.fecha, p.tipo, p.monto, p.referencia, p.observaciones FROM pagos_banco p JOIN bancos b ON b.id = p.banco_id ORDER BY p.id DESC LIMIT 100')->fetchAll();
    json_response(['bancos' => $bancos, 'pagos' => $pagos]);
}

if ($method !== 'POST') {
    json_response(['message' => 'Método no permitido.'], 405);
}

$data = request_json();
$accion = $data['accion'] ?? 'pago';

if ($accion === 'banco') {
    $stmt = $pdo->prepare('INSERT INTO bancos (nombre, numero_cuenta, moneda) VALUES (:nombre, :numero, :moneda)');
    $stmt->execute([
        'nombre' => trim((string) ($data['nombre'] ?? '')),
        'numero' => $data['numeroCuenta'] ?? null,
        'moneda' => $data['moneda'] ?? 'GTQ',
    ]);
    json_response(['id' => (int) $pdo->lastInsertId(), 'message' => 'Banco creado.'], 201);
}

$stmt = $pdo->prepare(
    'INSERT INTO pagos_banco (banco_id, usuario_id, usuario_nombre, venta_id, fecha, tipo, monto, referencia, observaciones)
     VALUES (:banco_id, :usuario_id, :usuario_nombre, :venta_id, NOW(), :tipo, :monto, :referencia, :observaciones)'
);
$stmt->execute([
    'banco_id' => (int) ($data['bancoId'] ?? 0),
    'usuario_id' => (int) $usuarioSesion['sub'],
    'usuario_nombre' => $usuarioSesion['nombre'],
    'venta_id' => $data['ventaId'] ?? null,
    'tipo' => $data['tipo'] ?? 'DEPOSITO',
    'monto' => (float) ($data['monto'] ?? 0),
    'referencia' => $data['referencia'] ?? null,
    'observaciones' => $data['observaciones'] ?? null,
]);
json_response(['id' => (int) $pdo->lastInsertId(), 'message' => 'Pago bancario registrado.'], 201);
