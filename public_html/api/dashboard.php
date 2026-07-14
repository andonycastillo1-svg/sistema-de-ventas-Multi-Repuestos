<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
current_user();

try {
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

// Envíos de compras del mes
$stmt = $pdo->query(
    'SELECT COALESCE(SUM(costo_envio), 0) AS total_envios
       FROM compras
      WHERE estado = "RECIBIDO"
        AND YEAR(fecha) = YEAR(CURDATE()) AND MONTH(fecha) = MONTH(CURDATE())'
);
$envios_mes = $stmt->fetch();

// Gastos operativos del mes
$stmt = $pdo->query(
    'SELECT COALESCE(SUM(monto), 0) AS total_gastos
       FROM gastos_operativos
      WHERE YEAR(fecha) = YEAR(CURDATE()) AND MONTH(fecha) = MONTH(CURDATE())'
);
$gastos_mes = $stmt->fetch();

// Ganancia bruta este mes:
//   ingresos     = precio_venta × cantidad (sin IVA, desde detalle_ventas.total_linea)
//   costo_ventas = costo_compra × cantidad
//   ganancia_bruta = ingresos - costo_ventas
$stmt = $pdo->query(
    'SELECT
        COALESCE(SUM(dv.total_linea), 0)                  AS ingresos,
        COALESCE(SUM(dv.cantidad * p.costo_compra), 0)    AS costo_ventas,
        COALESCE(SUM(dv.total_linea), 0)
          - COALESCE(SUM(dv.cantidad * p.costo_compra), 0) AS ganancia_bruta
       FROM detalle_ventas dv
       JOIN ventas v ON v.id = dv.venta_id
       JOIN productos p ON p.id = dv.producto_id
      WHERE v.estado = "EMITIDA"
        AND YEAR(v.fecha) = YEAR(CURDATE()) AND MONTH(v.fecha) = MONTH(CURDATE())'
);
$ganancia_mes = $stmt->fetch();

// Ganancia bruta últimos 7 días (por día)
$stmt = $pdo->query(
    'SELECT DATE(v.fecha) AS dia,
            COALESCE(SUM(dv.total_linea), 0)                  AS ingresos,
            COALESCE(SUM(dv.cantidad * p.costo_compra), 0)    AS costo_ventas,
            COALESCE(SUM(dv.total_linea), 0)
              - COALESCE(SUM(dv.cantidad * p.costo_compra), 0) AS ganancia_bruta
       FROM detalle_ventas dv
       JOIN ventas v ON v.id = dv.venta_id
       JOIN productos p ON p.id = dv.producto_id
      WHERE v.estado = "EMITIDA" AND v.fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      GROUP BY DATE(v.fecha)
      ORDER BY dia'
);
$ganancia_7_dias = $stmt->fetchAll();

// Últimas 5 ventas
$stmt = $pdo->query(
    'SELECT folio, usuario_nombre, fecha, total, metodo_pago
       FROM ventas WHERE estado = "EMITIDA"
      ORDER BY fecha DESC LIMIT 5'
);
$ultimas_ventas = $stmt->fetchAll();

json_response([
    'hoy'            => $hoy,
    'mes'            => $mes,
    'mes_anterior'   => $mes_anterior,
    'ultimos_7_dias' => $ultimos_7_dias,
    'top_productos'  => $top_productos,
    'por_metodo_pago' => $por_metodo_pago,
    'inventario'      => $inventario,
    'ultimas_ventas'  => $ultimas_ventas,
    'ganancia_mes'    => $ganancia_mes,
    'ganancia_7_dias' => $ganancia_7_dias,
    'envios_mes'      => $envios_mes,
    'gastos_mes'      => $gastos_mes,
]);
} catch (\Throwable $e) {
    json_response(['message' => 'Error en dashboard: ' . $e->getMessage()], 500);
}
