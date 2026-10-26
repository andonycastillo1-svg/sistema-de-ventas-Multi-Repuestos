<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
// Endpoint público — sin autenticación. No expone costos.

$pdo = db();

try {
    $stmt = $pdo->query(
        'SELECT p.id, p.sku, p.nombre, p.descripcion, c.nombre AS categoria,
                p.marca_vehiculo, p.modelo_vehiculo, p.anio_inicio, p.anio_fin, p.lado,
                ROUND(p.precio_venta + (p.precio_venta * p.iva_porcentaje / 100), 2) AS precio_venta,
                p.stock_actual, p.stock_minimo
           FROM productos p
           LEFT JOIN categorias c ON c.id = p.categoria_id
          WHERE p.estado = "ACTIVO"
          ORDER BY c.nombre, p.nombre'
    );
} catch (Throwable $e) {
    // Columnas de vehículo aún no migradas
    $stmt = $pdo->query(
        'SELECT p.id, p.sku, p.nombre, p.descripcion, c.nombre AS categoria,
                ROUND(p.precio_venta + (p.precio_venta * p.iva_porcentaje / 100), 2) AS precio_venta,
                p.stock_actual, p.stock_minimo
           FROM productos p
           LEFT JOIN categorias c ON c.id = p.categoria_id
          WHERE p.estado = "ACTIVO"
          ORDER BY c.nombre, p.nombre'
    );
}
$productos = $stmt->fetchAll();
foreach ($productos as &$row) {
    $row['imagen'] = product_image_url((int) $row['id']);
}
unset($row);

$categorias = array_values(array_unique(array_filter(
    array_column($productos, 'categoria')
)));
sort($categorias);

header('Cache-Control: public, max-age=60');
json_response([
    'productos'  => $productos,
    'categorias' => $categorias,
]);
