USE pmsguate_multi_repuestos;

ALTER TABLE ventas
  ADD COLUMN descuento DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER impuestos;

ALTER TABLE detalle_ventas
  ADD COLUMN descuento DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER impuesto;

CREATE TABLE IF NOT EXISTS bancos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  numero_cuenta VARCHAR(80) NULL,
  moneda VARCHAR(10) NOT NULL DEFAULT 'GTQ',
  estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_bancos_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pagos_banco (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  banco_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  usuario_nombre VARCHAR(160) NOT NULL,
  venta_id BIGINT UNSIGNED NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tipo ENUM('DEPOSITO','TRANSFERENCIA','PAGO_VENTA','OTRO') NOT NULL DEFAULT 'DEPOSITO',
  monto DECIMAL(14,2) NOT NULL,
  referencia VARCHAR(120) NULL,
  observaciones VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_pagos_banco_bancos FOREIGN KEY (banco_id) REFERENCES bancos(id),
  CONSTRAINT fk_pagos_banco_usuarios FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_pagos_banco_ventas FOREIGN KEY (venta_id) REFERENCES ventas(id),
  CONSTRAINT chk_pagos_banco_monto CHECK (monto > 0),
  INDEX idx_pagos_banco_fecha (fecha),
  INDEX idx_pagos_banco_banco_fecha (banco_id, fecha)
) ENGINE=InnoDB;
