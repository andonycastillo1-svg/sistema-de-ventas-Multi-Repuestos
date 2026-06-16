'use strict';

function roundMoney(value) {
  return Math.round(Number(value) * 100) / 100;
}

function createFolio(prefix) {
  const now = new Date();
  const stamp = now.toISOString().replace(/[-:.TZ]/g, '').slice(0, 14);
  const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
  return prefix + '-' + stamp + '-' + random;
}

async function emitirVenta(db, payload, usuarioSesion) {
  if (!usuarioSesion || !usuarioSesion.id || !usuarioSesion.nombre) {
    throw new Error('No existe usuario autenticado para emitir la venta.');
  }

  if (!payload || !Array.isArray(payload.items) || payload.items.length === 0) {
    throw new Error('La venta requiere al menos un producto.');
  }

  const connection = await db.getConnection();

  try {
    await connection.beginTransaction();

    const folio = createFolio('VTA');
    const lineas = [];
    let subtotalVenta = 0;
    let impuestosVenta = 0;

    for (const item of payload.items) {
      const cantidad = Number(item.cantidad);

      if (!item.productoId || cantidad <= 0) {
        throw new Error('Cada línea requiere productoId y cantidad mayor a cero.');
      }

      const [productos] = await connection.execute(
        `SELECT id, sku, nombre, precio_venta, iva_porcentaje, stock_actual, estado
           FROM productos
          WHERE id = ?
          FOR UPDATE`,
        [item.productoId]
      );

      if (productos.length === 0) {
        throw new Error('Producto no encontrado: ' + item.productoId);
      }

      const producto = productos[0];

      if (producto.estado !== 'ACTIVO') {
        throw new Error('Producto inactivo: ' + producto.nombre);
      }

      const stockActual = Number(producto.stock_actual);
      if (stockActual < cantidad) {
        throw new Error('Stock insuficiente para ' + producto.nombre + '. Disponible: ' + stockActual);
      }

      const precioUnitario = Number(producto.precio_venta);
      const ivaPorcentaje = Number(producto.iva_porcentaje);
      const subtotal = roundMoney(cantidad * precioUnitario);
      const impuesto = roundMoney(subtotal * (ivaPorcentaje / 100));
      const totalLinea = roundMoney(subtotal + impuesto);
      const stockNuevo = stockActual - cantidad;

      lineas.push({
        productoId: producto.id,
        sku: producto.sku,
        nombre: producto.nombre,
        cantidad,
        precioUnitario,
        ivaPorcentaje,
        subtotal,
        impuesto,
        totalLinea,
        stockAnterior: stockActual,
        stockNuevo
      });

      subtotalVenta = roundMoney(subtotalVenta + subtotal);
      impuestosVenta = roundMoney(impuestosVenta + impuesto);
    }

    const totalVenta = roundMoney(subtotalVenta + impuestosVenta);

    const [ventaResult] = await connection.execute(
      `INSERT INTO ventas
        (folio, usuario_id, usuario_nombre, fecha, estado, subtotal, impuestos, total, metodo_pago, observaciones)
       VALUES (?, ?, ?, NOW(), 'EMITIDA', ?, ?, ?, ?, ?)`,
      [
        folio,
        usuarioSesion.id,
        usuarioSesion.nombre,
        subtotalVenta,
        impuestosVenta,
        totalVenta,
        payload.metodoPago || 'EFECTIVO',
        payload.observaciones || null
      ]
    );

    const ventaId = ventaResult.insertId;

    for (const linea of lineas) {
      await connection.execute(
        `INSERT INTO detalle_ventas
          (venta_id, producto_id, sku, producto_nombre, cantidad, precio_unitario,
           iva_porcentaje, subtotal, impuesto, total_linea)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          ventaId,
          linea.productoId,
          linea.sku,
          linea.nombre,
          linea.cantidad,
          linea.precioUnitario,
          linea.ivaPorcentaje,
          linea.subtotal,
          linea.impuesto,
          linea.totalLinea
        ]
      );

      await connection.execute(
        `UPDATE productos
            SET stock_actual = ?
          WHERE id = ?`,
        [linea.stockNuevo, linea.productoId]
      );

      await connection.execute(
        `INSERT INTO kardex_movimientos
          (producto_id, usuario_id, usuario_nombre, venta_id, fecha, tipo_movimiento,
           cantidad, stock_anterior, stock_nuevo, precio_unitario, motivo, referencia)
         VALUES (?, ?, ?, ?, NOW(), 'VENTA', ?, ?, ?, ?, ?, ?)`,
        [
          linea.productoId,
          usuarioSesion.id,
          usuarioSesion.nombre,
          ventaId,
          linea.cantidad,
          linea.stockAnterior,
          linea.stockNuevo,
          linea.precioUnitario,
          'Salida por venta POS',
          folio
        ]
      );
    }

    await connection.commit();

    return {
      id: ventaId,
      folio,
      usuarioId: usuarioSesion.id,
      usuarioNombre: usuarioSesion.nombre,
      subtotal: subtotalVenta,
      impuestos: impuestosVenta,
      total: totalVenta,
      items: lineas
    };
  } catch (error) {
    await connection.rollback();
    throw error;
  } finally {
    connection.release();
  }
}

module.exports = {
  emitirVenta
};
