-- Costo de envío en compras
ALTER TABLE compras
  ADD COLUMN costo_envio DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total;

-- Tabla de gastos operativos
CREATE TABLE IF NOT EXISTS gastos_operativos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha         DATE NOT NULL,
  categoria     VARCHAR(80) NOT NULL DEFAULT 'General',
  descripcion   VARCHAR(255) NOT NULL,
  monto         DECIMAL(12,2) NOT NULL,
  usuario_id    INT UNSIGNED NULL,
  usuario_nombre VARCHAR(120) NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_gastos_fecha (fecha),
  INDEX idx_gastos_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
