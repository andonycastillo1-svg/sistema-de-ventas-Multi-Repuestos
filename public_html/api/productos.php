<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query(
        'SELECT p.id, p.sku, p.codigo_barras, p.nombre, p.descripcion, p.marca_vehiculo,
                p.modelo_vehiculo, p.anio_inicio, p.anio_fin, p.motor, p.lado, p.ubicacion,
                p.costo_compra, p.precio_venta,
                ROUND(p.precio_venta + (p.precio_venta * p.iva_porcentaje / 100), 2) AS precio_final,
                p.iva_porcentaje, p.stock_actual, p.stock_minimo,
                p.stock_maximo, p.estado, c.nombre AS categoria, u.codigo AS unidad
           FROM productos p
           JOIN categorias c ON c.id = p.categoria_id
           JOIN unidades_medida u ON u.id = p.unidad_medida_id
          ORDER BY p.id DESC
          LIMIT 200'
    );
    json_response(['productos' => $stmt->fetchAll()]);
}

if ($method !== 'POST') {
    json_response(['message' => 'Método no permitido.'], 405);
}

current_user();
$data = request_json();
$nombre = trim((string) ($data['nombre'] ?? ''));
$sku = trim((string) ($data['sku'] ?? ''));

if ($nombre === '' || $sku === '') {
    json_response(['message' => 'SKU y nombre son requeridos.'], 400);
}

try {
    $pdo->beginTransaction();

    $categoriaNombre = trim((string) ($data['categoria'] ?? 'General')) ?: 'General';
    $unidadCodigo = trim((string) ($data['unidad'] ?? 'PZA')) ?: 'PZA';

    $stmt = $pdo->prepare('SELECT id FROM categorias WHERE nombre = :nombre LIMIT 1');
    $stmt->execute(['nombre' => $categoriaNombre]);
    $categoria = $stmt->fetch();
    if ($categoria) {
        $categoriaId = (int) $categoria['id'];
    } else {
        $stmt = $pdo->prepare('INSERT INTO categorias (nombre) VALUES (:nombre)');
        $stmt->execute(['nombre' => $categoriaNombre]);
        $categoriaId = (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('SELECT id FROM unidades_medida WHERE codigo = :codigo LIMIT 1');
    $stmt->execute(['codigo' => $unidadCodigo]);
    $unidad = $stmt->fetch();
    if ($unidad) {
        $unidadId = (int) $unidad['id'];
    } else {
        $stmt = $pdo->prepare('INSERT INTO unidades_medida (codigo, nombre) VALUES (:codigo, :nombre)');
        $stmt->execute(['codigo' => $unidadCodigo, 'nombre' => $unidadCodigo]);
        $unidadId = (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare(
        'INSERT INTO productos
            (categoria_id, unidad_medida_id, sku, codigo_barras, nombre, descripcion,
             marca_vehiculo, modelo_vehiculo, anio_inicio, anio_fin, motor, lado, ubicacion,
             costo_compra, precio_venta, iva_porcentaje, stock_actual, stock_minimo, stock_maximo, estado)
         VALUES
            (:categoria_id, :unidad_medida_id, :sku, :codigo_barras, :nombre, :descripcion,
             :marca_vehiculo, :modelo_vehiculo, :anio_inicio, :anio_fin, :motor, :lado, :ubicacion,
             :costo_compra, :precio_venta, :iva_porcentaje, :stock_actual, :stock_minimo, :stock_maximo, "ACTIVO")'
    );
    $stmt->execute([
        'categoria_id' => $categoriaId,
        'unidad_medida_id' => $unidadId,
        'sku' => $sku,
        'codigo_barras' => $data['codigoBarras'] ?? null,
        'nombre' => $nombre,
        'descripcion' => $data['descripcion'] ?? null,
        'marca_vehiculo' => $data['marcaVehiculo'] ?? null,
        'modelo_vehiculo' => $data['modeloVehiculo'] ?? null,
        'anio_inicio' => ($data['anioInicio'] ?? null) ?: null,
        'anio_fin' => ($data['anioFin'] ?? null) ?: null,
        'motor' => $data['motor'] ?? null,
        'lado' => $data['lado'] ?? 'NO_APLICA',
        'ubicacion' => $data['ubicacion'] ?? null,
        'costo_compra' => (float) ($data['costoCompra'] ?? 0),
        'precio_venta' => (float) ($data['precioVenta'] ?? 0),
        'iva_porcentaje' => (float) ($data['ivaPorcentaje'] ?? 0),
        'stock_actual' => (float) ($data['stockActual'] ?? 0),
        'stock_minimo' => (float) ($data['stockMinimo'] ?? 0),
        'stock_maximo' => (float) ($data['stockMaximo'] ?? 0),
    ]);

    $pdo->commit();
    json_response(['id' => (int) $pdo->lastInsertId(), 'message' => 'Producto creado.'], 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['message' => $error->getMessage()], 400);
}
