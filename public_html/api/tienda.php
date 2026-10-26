<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
// Endpoint público — sin autenticación

$pdo = db();

$stmt = $pdo->query(
    'SELECT id, sku, nombre, descripcion, categoria,
            precio_venta, stock_actual, stock_minimo
       FROM productos
      WHERE estado = "ACTIVO"
      ORDER BY categoria, nombre'
);
$productos = $stmt->fetchAll();

$categorias = array_values(array_unique(array_filter(
    array_column($productos, 'categoria')
)));
sort($categorias);

json_response([
    'productos'  => $productos,
    'categorias' => $categorias,
]);
