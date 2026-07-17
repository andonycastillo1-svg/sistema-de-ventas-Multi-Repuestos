<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
current_user();
$pdo = db();
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

// Resumen de ventas
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

// Por método de pago
$stmt = $pdo->prepare(
    'SELECT metodo_pago, COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
       FROM ventas
      WHERE estado = "EMITIDA" AND DATE(fecha) BETWEEN :desde AND :hasta
      GROUP BY metodo_pago ORDER BY metodo_pago'
);
$stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
$por_metodo_pago = $stmt->fetchAll();

// Ganancia bruta: precio_venta × cantidad - costo_compra × cantidad
$stmt = $pdo->prepare(
    'SELECT
        COALESCE(SUM(dv.total_linea), 0)                   AS ingresos,
        COALESCE(SUM(dv.cantidad * p.costo_compra), 0)     AS costo_ventas,
        COALESCE(SUM(dv.total_linea), 0)
          - COALESCE(SUM(dv.cantidad * p.costo_compra), 0) AS ganancia_bruta
       FROM detalle_ventas dv
       JOIN ventas v ON v.id = dv.venta_id
       JOIN productos p ON p.id = dv.producto_id
      WHERE v.estado = "EMITIDA" AND DATE(v.fecha) BETWEEN :desde AND :hasta'
);
$stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
$ganancia = $stmt->fetch();

// Costo total de compras en el período
$stmt = $pdo->prepare(
    'SELECT
        COALESCE(SUM(total), 0)       AS total_compras,
        COALESCE(SUM(costo_envio), 0) AS total_envios,
        COALESCE(SUM(total + costo_envio), 0) AS total_con_envio,
        COUNT(*) AS cantidad_compras
       FROM compras
      WHERE estado = "RECIBIDO" AND DATE(fecha) BETWEEN :desde AND :hasta'
);
$stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
$compras = $stmt->fetch();

// Gastos operativos en el período
$stmt = $pdo->prepare(
    'SELECT COALESCE(SUM(monto), 0) AS total_gastos, COUNT(*) AS cantidad_gastos
       FROM gastos_operativos
      WHERE DATE(fecha) BETWEEN :desde AND :hasta'
);
$stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
$gastos = $stmt->fetch();

// Detalle de ventas: cada venta con sus ítems
$stmt = $pdo->prepare(
    'SELECT v.id, v.folio, DATE(v.fecha) AS fecha, v.usuario_nombre AS vendedor,
            v.metodo_pago, v.descuento, v.total
       FROM ventas v
      WHERE v.estado = "EMITIDA" AND DATE(v.fecha) BETWEEN :desde AND :hasta
      ORDER BY v.fecha DESC'
);
$stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
$detalle_ventas = $stmt->fetchAll();

// Para cada venta, cargamos sus líneas
$venta_ids = array_column($detalle_ventas, 'id');
$detalle_items = [];
if ($venta_ids) {
    $placeholders = implode(',', array_fill(0, count($venta_ids), '?'));
    $stmt = $pdo->prepare(
        'SELECT dv.venta_id, p.nombre, p.sku, dv.cantidad, dv.precio_unitario, dv.total_linea
           FROM detalle_ventas dv
           JOIN productos p ON p.id = dv.producto_id
          WHERE dv.venta_id IN (' . $placeholders . ')'
    );
    $stmt->execute($venta_ids);
    foreach ($stmt->fetchAll() as $row) {
        $detalle_items[$row['venta_id']][] = $row;
    }
}

// Adjuntar ítems a cada venta
foreach ($detalle_ventas as &$venta) {
    $venta['items'] = $detalle_items[$venta['id']] ?? [];
}
unset($venta);

json_response([
    'desde'           => $desde,
    'hasta'           => $hasta,
    'ventas'          => $ventas,
    'por_metodo_pago' => $por_metodo_pago,
    'ganancia'        => $ganancia,
    'compras'         => $compras,
    'gastos'          => $gastos,
    'detalle_ventas'  => $detalle_ventas,
]);
