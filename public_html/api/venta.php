<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['message' => 'Método no permitido.'], 405);
}

$usuarioSesion = current_user();
$data = request_json();
$items = $data['items'] ?? [];

if (!is_array($items) || count($items) === 0) {
    json_response(['message' => 'La venta requiere al menos un producto.'], 400);
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $folio = 'VTA-' . gmdate('YmdHis') . '-' . random_int(1000, 9999);
    $lineas = [];
    $subtotalVenta = 0.0;
    $impuestosVenta = 0.0;

    foreach ($items as $item) {
        $productoId = (int) ($item['productoId'] ?? 0);
        $cantidad = (float) ($item['cantidad'] ?? 0);

        if ($productoId <= 0 || $cantidad <= 0) {
            throw new RuntimeException('Cada línea requiere productoId y cantidad mayor a cero.');
        }

        $stmt = $pdo->prepare(
            'SELECT id, sku, nombre, precio_venta, iva_porcentaje, stock_actual, estado
               FROM productos
              WHERE id = :id
              FOR UPDATE'
        );
        $stmt->execute(['id' => $productoId]);
        $producto = $stmt->fetch();

        if (!$producto) {
            throw new RuntimeException('Producto no encontrado: ' . $productoId);
        }

        if ($producto['estado'] !== 'ACTIVO') {
            throw new RuntimeException('Producto inactivo: ' . $producto['nombre']);
        }

        $stockActual = (float) $producto['stock_actual'];
        if ($stockActual < $cantidad) {
            throw new RuntimeException('Stock insuficiente para ' . $producto['nombre'] . '. Disponible: ' . $stockActual);
        }

        $precioUnitario = (float) $producto['precio_venta'];
        $ivaPorcentaje = (float) $producto['iva_porcentaje'];
        $subtotal = round($cantidad * $precioUnitario, 2);
        $impuesto = round($subtotal * ($ivaPorcentaje / 100), 2);
        $totalLinea = round($subtotal + $impuesto, 2);
        $stockNuevo = $stockActual - $cantidad;

        $lineas[] = [
            'producto_id' => (int) $producto['id'],
            'sku' => $producto['sku'],
            'nombre' => $producto['nombre'],
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'iva_porcentaje' => $ivaPorcentaje,
            'subtotal' => $subtotal,
            'impuesto' => $impuesto,
            'total_linea' => $totalLinea,
            'stock_anterior' => $stockActual,
            'stock_nuevo' => $stockNuevo,
        ];

        $subtotalVenta = round($subtotalVenta + $subtotal, 2);
        $impuestosVenta = round($impuestosVenta + $impuesto, 2);
    }

    $totalVenta = round($subtotalVenta + $impuestosVenta, 2);

    $stmt = $pdo->prepare(
        'INSERT INTO ventas
            (folio, usuario_id, usuario_nombre, fecha, estado, subtotal, impuestos, total, metodo_pago, observaciones)
         VALUES
            (:folio, :usuario_id, :usuario_nombre, NOW(), "EMITIDA", :subtotal, :impuestos, :total, :metodo_pago, :observaciones)'
    );
    $stmt->execute([
        'folio' => $folio,
        'usuario_id' => (int) $usuarioSesion['sub'],
        'usuario_nombre' => $usuarioSesion['nombre'],
        'subtotal' => $subtotalVenta,
        'impuestos' => $impuestosVenta,
        'total' => $totalVenta,
        'metodo_pago' => $data['metodoPago'] ?? 'EFECTIVO',
        'observaciones' => $data['observaciones'] ?? null,
    ]);

    $ventaId = (int) $pdo->lastInsertId();

    foreach ($lineas as $linea) {
        $stmt = $pdo->prepare(
            'INSERT INTO detalle_ventas
                (venta_id, producto_id, sku, producto_nombre, cantidad, precio_unitario,
                 iva_porcentaje, subtotal, impuesto, total_linea)
             VALUES
                (:venta_id, :producto_id, :sku, :producto_nombre, :cantidad, :precio_unitario,
                 :iva_porcentaje, :subtotal, :impuesto, :total_linea)'
        );
        $stmt->execute([
            'venta_id' => $ventaId,
            'producto_id' => $linea['producto_id'],
            'sku' => $linea['sku'],
            'producto_nombre' => $linea['nombre'],
            'cantidad' => $linea['cantidad'],
            'precio_unitario' => $linea['precio_unitario'],
            'iva_porcentaje' => $linea['iva_porcentaje'],
            'subtotal' => $linea['subtotal'],
            'impuesto' => $linea['impuesto'],
            'total_linea' => $linea['total_linea'],
        ]);

        $stmt = $pdo->prepare('UPDATE productos SET stock_actual = :stock WHERE id = :id');
        $stmt->execute(['stock' => $linea['stock_nuevo'], 'id' => $linea['producto_id']]);

        $stmt = $pdo->prepare(
            'INSERT INTO kardex_movimientos
                (producto_id, usuario_id, usuario_nombre, venta_id, fecha, tipo_movimiento,
                 cantidad, stock_anterior, stock_nuevo, precio_unitario, motivo, referencia)
             VALUES
                (:producto_id, :usuario_id, :usuario_nombre, :venta_id, NOW(), "VENTA",
                 :cantidad, :stock_anterior, :stock_nuevo, :precio_unitario, :motivo, :referencia)'
        );
        $stmt->execute([
            'producto_id' => $linea['producto_id'],
            'usuario_id' => (int) $usuarioSesion['sub'],
            'usuario_nombre' => $usuarioSesion['nombre'],
            'venta_id' => $ventaId,
            'cantidad' => $linea['cantidad'],
            'stock_anterior' => $linea['stock_anterior'],
            'stock_nuevo' => $linea['stock_nuevo'],
            'precio_unitario' => $linea['precio_unitario'],
            'motivo' => 'Salida por venta POS',
            'referencia' => $folio,
        ]);
    }

    $pdo->commit();

    json_response([
        'id' => $ventaId,
        'folio' => $folio,
        'subtotal' => $subtotalVenta,
        'impuestos' => $impuestosVenta,
        'total' => $totalVenta,
    ], 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response(['message' => $error->getMessage()], 400);
}
