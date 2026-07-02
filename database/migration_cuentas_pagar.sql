ALTER TABLE compras
  ADD COLUMN estado_pago      ENUM('PENDIENTE','PAGADO') NOT NULL DEFAULT 'PENDIENTE' AFTER costo_envio,
  ADD COLUMN fecha_vencimiento DATE NULL AFTER estado_pago,
  ADD COLUMN fecha_pago        DATE NULL AFTER fecha_vencimiento,
  ADD INDEX idx_compras_pago (estado_pago, fecha_vencimiento);

-- Calcular vencimiento de compras existentes (fecha + 40 días)
UPDATE compras SET fecha_vencimiento = DATE_ADD(DATE(fecha), INTERVAL 40 DAY)
 WHERE fecha_vencimiento IS NULL;
