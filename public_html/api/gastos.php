<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$usuarioSesion = require_role(['ADMINISTRADOR']);
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');

    $stmt = $pdo->prepare(
        'SELECT id, fecha, categoria, descripcion, monto, usuario_nombre, created_at
           FROM gastos_operativos
          WHERE fecha BETWEEN :desde AND :hasta
          ORDER BY fecha DESC, id DESC'
    );
    $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
    $gastos = $stmt->fetchAll();

    // Totales por categoría
    $stmt2 = $pdo->prepare(
        'SELECT categoria, COALESCE(SUM(monto), 0) AS total, COUNT(*) AS cantidad
           FROM gastos_operativos
          WHERE fecha BETWEEN :desde AND :hasta
          GROUP BY categoria
          ORDER BY total DESC'
    );
    $stmt2->execute(['desde' => $desde, 'hasta' => $hasta]);

    // Total envíos de compras en el período
    $stmt3 = $pdo->prepare(
        'SELECT COALESCE(SUM(costo_envio), 0) AS total_envios
           FROM compras
          WHERE estado = "RECIBIDO" AND DATE(fecha) BETWEEN :desde AND :hasta'
    );
    $stmt3->execute(['desde' => $desde, 'hasta' => $hasta]);
    $envios = $stmt3->fetch();

    json_response([
        'gastos'        => $gastos,
        'por_categoria' => $stmt2->fetchAll(),
        'total_envios'  => $envios['total_envios'],
        'desde'         => $desde,
        'hasta'         => $hasta,
    ]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['message' => 'ID requerido.'], 400);
    }
    $stmt = $pdo->prepare('DELETE FROM gastos_operativos WHERE id = :id');
    $stmt->execute(['id' => $id]);
    json_response(['message' => 'Gasto eliminado.']);
}

if ($method !== 'POST') {
    json_response(['message' => 'Método no permitido.'], 405);
}

$data        = request_json();
$fecha       = trim((string) ($data['fecha']       ?? date('Y-m-d')));
$categoria   = trim((string) ($data['categoria']   ?? 'General'));
$descripcion = trim((string) ($data['descripcion'] ?? ''));
$monto       = (float) ($data['monto'] ?? 0);

if ($descripcion === '' || $monto <= 0) {
    json_response(['message' => 'Descripción y monto son requeridos.'], 400);
}

$stmt = $pdo->prepare(
    'INSERT INTO gastos_operativos (fecha, categoria, descripcion, monto, usuario_id, usuario_nombre)
     VALUES (:fecha, :categoria, :descripcion, :monto, :usuario_id, :usuario_nombre)'
);
$stmt->execute([
    'fecha'          => $fecha,
    'categoria'      => $categoria ?: 'General',
    'descripcion'    => $descripcion,
    'monto'          => $monto,
    'usuario_id'     => (int) $usuarioSesion['sub'],
    'usuario_nombre' => $usuarioSesion['nombre'],
]);

json_response(['message' => 'Gasto registrado correctamente.', 'id' => (int) $pdo->lastInsertId()], 201);
