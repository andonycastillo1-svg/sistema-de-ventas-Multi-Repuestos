<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
current_user();
$pdo = db();
$filtro = $_GET['filtro'] ?? 'todos';
$where = '1=1';

if ($filtro === 'sin_stock') {
    $where = 'p.stock_actual <= 0';
} elseif ($filtro === 'bajo_minimo') {
    $where = 'p.stock_actual > 0 AND p.stock_actual <= p.stock_minimo';
}

$stmt = $pdo->query(
    'SELECT p.id, p.sku, p.nombre, p.marca_vehiculo, p.modelo_vehiculo, p.anio_inicio, p.anio_fin,
            p.motor, p.ubicacion, p.stock_actual, p.stock_minimo, p.stock_maximo, p.precio_venta,
            CASE
              WHEN p.stock_actual <= 0 THEN "SIN_STOCK"
              WHEN p.stock_actual <= p.stock_minimo THEN "BAJO_MINIMO"
              ELSE "OK"
            END AS alerta
       FROM productos p
      WHERE ' . $where . '
      ORDER BY alerta DESC, p.nombre ASC
      LIMIT 500'
);
$productos = $stmt->fetchAll();

$resumen = $pdo->query(
    'SELECT
        SUM(CASE WHEN stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
        SUM(CASE WHEN stock_actual > 0 AND stock_actual <= stock_minimo THEN 1 ELSE 0 END) AS bajo_minimo,
        COUNT(*) AS total_productos
       FROM productos'
)->fetch();

json_response(['filtro' => $filtro, 'resumen' => $resumen, 'productos' => $productos]);
