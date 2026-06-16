<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
current_user();
$pdo = db();

// Ventas hoy
$stmt = $pdo->query(
    'SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
       FROM ventas WHERE estado = "EMITIDA" AND DATE(fecha) = CURDATE()'
);
$hoy = $stmt->fetch();

// Ventas este mes
$stmt = $pdo->query(
    'SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total,
            COALESCE(SUM(descuento), 0) AS descuentos, COALESCE(SUM(impuestos), 0) AS impuestos
       FROM ventas
      WHERE estado = "EMITIDA"
        AND YEAR(fecha) = YEAR(CURDATE()) AND MONTH(fecha) = MONTH(CURDATE())'
);
$mes = $stmt->fetch();

// Ventas mes anterior (para comparativo)
$stmt = $pdo->query(
    'SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
       FROM ventas
      WHERE estado = "EMITIDA"
        AND YEAR(fecha) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND MONTH(fecha) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))'
);
$mes_anterior = $stmt->fetch();

// Ventas últimos 7 días (por día)
$stmt = $pdo->query(
    'SELECT DATE(fecha) AS dia, COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
       FROM ventas
      WHERE estado = "EMITIDA" AND fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      GROUP BY DATE(fecha)
      ORDER BY dia'
);
$ultimos_7_dias = $stmt->fetchAll();

// Top 5 productos más vendidos este mes
$stmt = $pdo->query(
    'SELECT p.nombre, p.sku, SUM(dv.cantidad) AS unidades, COALESCE(SUM(dv.total_linea), 0) AS total
       FROM detalle_ventas dv
       JOIN ventas v ON v.id = dv.venta_id
       JOIN productos p ON p.id = dv.producto_id
      WHERE v.estado = "EMITIDA"
        AND YEAR(v.fecha) = YEAR(CURDATE()) AND MONTH(v.fecha) = MONTH(CURDATE())
      GROUP BY dv.producto_id
      ORDER BY unidades DESC
      LIMIT 5'
);
$top_productos = $stmt->fetchAll();

// Ventas por método de pago este mes
$stmt = $pdo->query(
    'SELECT metodo_pago, COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
       FROM ventas
      WHERE estado = "EMITIDA"
        AND YEAR(fecha) = YEAR(CURDATE()) AND MONTH(fecha) = MONTH(CURDATE())
      GROUP BY metodo_pago
      ORDER BY total DESC'
);
$por_metodo_pago = $stmt->fetchAll();

// Alertas de inventario
$stmt = $pdo->query(
    'SELECT
        SUM(CASE WHEN stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
        SUM(CASE WHEN stock_actual > 0 AND stock_actual <= stock_minimo THEN 1 ELSE 0 END) AS bajo_minimo,
        COUNT(*) AS total_productos
       FROM productos WHERE estado = "ACTIVO"'
);
$inventario = $stmt->fetch();

// Últimas 5 ventas
$stmt = $pdo->query(
    'SELECT folio, usuario_nombre, fecha, total, metodo_pago
       FROM ventas WHERE estado = "EMITIDA"
      ORDER BY fecha DESC LIMIT 5'
);
$ultimas_ventas = $stmt->fetchAll();

json_response([
    'hoy'           => $hoy,
    'mes'           => $mes,
    'mes_anterior'  => $mes_anterior,
    'ultimos_7_dias' => $ultimos_7_dias,
    'top_productos' => $top_productos,
    'por_metodo_pago' => $por_metodo_pago,
    'inventario'    => $inventario,
    'ultimas_ventas' => $ultimas_ventas,
]);
