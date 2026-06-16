<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
current_user();
$pdo = db();
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS cantidad_ventas,
            COALESCE(SUM(subtotal), 0) AS subtotal,
            COALESCE(SUM(impuestos), 0) AS impuestos,
            COALESCE(SUM(descuento), 0) AS descuentos,
            COALESCE(SUM(total), 0) AS total_vendido
       FROM ventas
      WHERE estado = "EMITIDA" AND DATE(fecha) BETWEEN :desde AND :hasta'
);
$stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
$ventas = $stmt->fetch();

$stmt = $pdo->prepare(
    'SELECT metodo_pago, COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
       FROM ventas
      WHERE estado = "EMITIDA" AND DATE(fecha) BETWEEN :desde AND :hasta
      GROUP BY metodo_pago
      ORDER BY metodo_pago'
);
$stmt->execute(['desde' => $desde, 'hasta' => $hasta]);

json_response(['desde' => $desde, 'hasta' => $hasta, 'ventas' => $ventas, 'por_metodo_pago' => $stmt->fetchAll()]);
