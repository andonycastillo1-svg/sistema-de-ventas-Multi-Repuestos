<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    require_role(['ADMINISTRADOR']);
    $stmt = $pdo->query(
        'SELECT c.id, c.folio, c.factura_numero, p.nombre AS proveedor, c.usuario_nombre, c.fecha, c.estado, c.total
           FROM compras c
           JOIN proveedores p ON p.id = c.proveedor_id
          ORDER BY c.id DESC
          LIMIT 100'
    );
    json_response(['compras' => $stmt->fetchAll()]);
}

if ($method !== 'POST') {
    json_response(['message' => 'Método no permitido.'], 405);
}

$usuarioSesion = require_role(['ADMINISTRADOR']);
$data = request_json();
$items = $data['items'] ?? [];

if (!is_array($items) || count($items) === 0) {
    json_response(['message' => 'La compra requiere productos.'], 400);
}

try {
    $pdo->beginTransaction();

    $proveedorNit = trim((string) ($data['proveedorNit'] ?? 'CF')) ?: 'CF';
    $proveedorNombre = trim((string) ($data['proveedorNombre'] ?? 'Proveedor General')) ?: 'Proveedor General';
    $stmt = $pdo->prepare('SELECT id FROM proveedores WHERE nit_rut = :nit LIMIT 1');
    $stmt->execute(['nit' => $proveedorNit]);
    $proveedor = $stmt->fetch();
    if ($proveedor) {
        $proveedorId = (int) $proveedor['id'];
    } else {
        $stmt = $pdo->prepare('INSERT INTO proveedores (nit_rut, nombre) VALUES (:nit, :nombre)');
        $stmt->execute(['nit' => $proveedorNit, 'nombre' => $proveedorNombre]);
        $proveedorId = (int) $pdo->lastInsertId();
    }

    $folio = 'COM-' . gmdate('YmdHis') . '-' . random_int(1000, 9999);
    $facturaNumero = trim((string) ($data['facturaNumero'] ?? '')) ?: null;
    $fechaCompra = trim((string) ($data['fechaCompra'] ?? '')) ?: gmdate('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaCompra)) {
        throw new RuntimeException('La fecha de compra debe tener formato YYYY-MM-DD.');
    }
    $lineas = [];
    $subtotalCompra = 0.0;
    $impuestosCompra = 0.0;

    foreach ($items as $item) {
        $productoId = (int) ($item['productoId'] ?? 0);
        $cantidad = (float) ($item['cantidad'] ?? 0);
        $costo = (float) ($item['costoUnitario'] ?? 0);
        if ($productoId <= 0 || $cantidad <= 0 || $costo < 0) {
            throw new RuntimeException('Producto, cantidad y costo son requeridos en cada línea.');
        }

        $stmt = $pdo->prepare('SELECT id, iva_porcentaje, stock_actual FROM productos WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $productoId]);
        $producto = $stmt->fetch();
        if (!$producto) {
            throw new RuntimeException('Producto no encontrado: ' . $productoId);
        }

        $iva = (float) $producto['iva_porcentaje'];
        $subtotal = round($cantidad * $costo, 2);
        $impuesto = round($subtotal * ($iva / 100), 2);
        $total = round($subtotal + $impuesto, 2);
        $stockAnterior = (float) $producto['stock_actual'];
        $stockNuevo = $stockAnterior + $cantidad;
        $lineas[] = compact('productoId', 'cantidad', 'costo', 'iva', 'subtotal', 'impuesto', 'total', 'stockAnterior', 'stockNuevo');
        $subtotalCompra = round($subtotalCompra + $subtotal, 2);
        $impuestosCompra = round($impuestosCompra + $impuesto, 2);
    }

    $totalCompra = round($subtotalCompra + $impuestosCompra, 2);
    $stmt = $pdo->prepare(
        'INSERT INTO compras (folio, factura_numero, proveedor_id, usuario_id, usuario_nombre, fecha, estado, subtotal, impuestos, total, recibido_at)
         VALUES (:folio, :factura_numero, :proveedor_id, :usuario_id, :usuario_nombre, :fecha, "RECIBIDO", :subtotal, :impuestos, :total, NOW())'
    );
    $stmt->execute([
        'folio' => $folio,
        'factura_numero' => $facturaNumero,
        'fecha' => $fechaCompra . ' 00:00:00',
        'proveedor_id' => $proveedorId,
        'usuario_id' => (int) $usuarioSesion['sub'],
        'usuario_nombre' => $usuarioSesion['nombre'],
        'subtotal' => $subtotalCompra,
        'impuestos' => $impuestosCompra,
        'total' => $totalCompra,
    ]);
    $compraId = (int) $pdo->lastInsertId();

    foreach ($lineas as $linea) {
        $stmt = $pdo->prepare(
            'INSERT INTO detalle_compras (compra_id, producto_id, cantidad, costo_unitario, iva_porcentaje, subtotal, impuesto, total_linea)
             VALUES (:compra_id, :producto_id, :cantidad, :costo_unitario, :iva_porcentaje, :subtotal, :impuesto, :total_linea)'
        );
        $stmt->execute([
            'compra_id' => $compraId,
            'producto_id' => $linea['productoId'],
            'cantidad' => $linea['cantidad'],
            'costo_unitario' => $linea['costo'],
            'iva_porcentaje' => $linea['iva'],
            'subtotal' => $linea['subtotal'],
            'impuesto' => $linea['impuesto'],
            'total_linea' => $linea['total'],
        ]);

        $stmt = $pdo->prepare('UPDATE productos SET stock_actual = :stock, costo_compra = :costo WHERE id = :id');
        $stmt->execute(['stock' => $linea['stockNuevo'], 'costo' => $linea['costo'], 'id' => $linea['productoId']]);

        $stmt = $pdo->prepare(
            'INSERT INTO kardex_movimientos
                (producto_id, usuario_id, usuario_nombre, compra_id, fecha, tipo_movimiento, cantidad,
                 stock_anterior, stock_nuevo, costo_unitario, motivo, referencia)
             VALUES (:producto_id, :usuario_id, :usuario_nombre, :compra_id, NOW(), "COMPRA", :cantidad,
                 :stock_anterior, :stock_nuevo, :costo_unitario, :motivo, :referencia)'
        );
        $stmt->execute([
            'producto_id' => $linea['productoId'],
            'usuario_id' => (int) $usuarioSesion['sub'],
            'usuario_nombre' => $usuarioSesion['nombre'],
            'compra_id' => $compraId,
            'cantidad' => $linea['cantidad'],
            'stock_anterior' => $linea['stockAnterior'],
            'stock_nuevo' => $linea['stockNuevo'],
            'costo_unitario' => $linea['costo'],
            'motivo' => 'Entrada por compra recibida',
            'referencia' => $folio,
        ]);
    }

    $pdo->commit();
    json_response(['id' => $compraId, 'folio' => $folio, 'total' => $totalCompra, 'message' => 'Compra recibida e inventario actualizado.'], 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['message' => $error->getMessage()], 400);
}
