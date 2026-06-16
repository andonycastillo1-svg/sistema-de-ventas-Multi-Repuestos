-- DDL MySQL para Sistema de Inventarios, Compras y Ventas
-- Compatible con MySQL Server/InnoDB. Requiere MySQL 8.0+ para enforcement de CHECK.

CREATE DATABASE IF NOT EXISTS multi_repuestos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE multi_repuestos;

CREATE TABLE roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(50) NOT NULL,
  descripcion VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT uk_roles_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rol_id BIGINT UNSIGNED NOT NULL,
  usuario VARCHAR(60) NOT NULL,
  email VARCHAR(160) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  ultimo_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT uk_usuarios_usuario UNIQUE (usuario),
  CONSTRAINT uk_usuarios_email UNIQUE (email),
  CONSTRAINT fk_usuarios_roles FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE categorias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) NULL,
  estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT uk_categorias_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

CREATE TABLE unidades_medida (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT uk_unidades_codigo UNIQUE (codigo)
) ENGINE=InnoDB;

CREATE TABLE productos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id BIGINT UNSIGNED NOT NULL,
  unidad_medida_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(80) NOT NULL,
  codigo_barras VARCHAR(120) NULL,
  nombre VARCHAR(180) NOT NULL,
  descripcion TEXT NULL,
  costo_compra DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  precio_venta DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  iva_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  stock_actual DECIMAL(14,3) NOT NULL DEFAULT 0.000,
  stock_minimo DECIMAL(14,3) NOT NULL DEFAULT 0.000,
  stock_maximo DECIMAL(14,3) NOT NULL DEFAULT 0.000,
  estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT uk_productos_sku UNIQUE (sku),
  CONSTRAINT uk_productos_codigo_barras UNIQUE (codigo_barras),
  CONSTRAINT fk_productos_categorias FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  CONSTRAINT fk_productos_unidades FOREIGN KEY (unidad_medida_id) REFERENCES unidades_medida(id),
  CONSTRAINT chk_productos_costos CHECK (costo_compra >= 0 AND precio_venta >= 0),
  CONSTRAINT chk_productos_iva CHECK (iva_porcentaje >= 0 AND iva_porcentaje <= 100),
  CONSTRAINT chk_productos_stock CHECK (stock_actual >= 0 AND stock_minimo >= 0 AND stock_maximo >= 0),
  INDEX idx_productos_nombre (nombre),
  INDEX idx_productos_estado_stock (estado, stock_actual, stock_minimo)
) ENGINE=InnoDB;

CREATE TABLE proveedores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nit_rut VARCHAR(50) NOT NULL,
  nombre VARCHAR(180) NOT NULL,
  telefono VARCHAR(40) NULL,
  email VARCHAR(160) NULL,
  direccion VARCHAR(255) NULL,
  estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT uk_proveedores_nit_rut UNIQUE (nit_rut),
  INDEX idx_proveedores_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE compras (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  folio VARCHAR(40) NOT NULL,
  proveedor_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  usuario_nombre VARCHAR(160) NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado ENUM('SOLICITADO','RECIBIDO','CANCELADO') NOT NULL DEFAULT 'SOLICITADO',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  impuestos DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  recibido_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT uk_compras_folio UNIQUE (folio),
  CONSTRAINT fk_compras_proveedores FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
  CONSTRAINT fk_compras_usuarios FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT chk_compras_totales CHECK (subtotal >= 0 AND impuestos >= 0 AND total >= 0),
  INDEX idx_compras_fecha_estado (fecha, estado),
  INDEX idx_compras_proveedor_fecha (proveedor_id, fecha)
) ENGINE=InnoDB;

CREATE TABLE detalle_compras (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  compra_id BIGINT UNSIGNED NOT NULL,
  producto_id BIGINT UNSIGNED NOT NULL,
  cantidad DECIMAL(14,3) NOT NULL,
  costo_unitario DECIMAL(14,2) NOT NULL,
  iva_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  subtotal DECIMAL(14,2) NOT NULL,
  impuesto DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_linea DECIMAL(14,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_detalle_compras_compras FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_compras_productos FOREIGN KEY (producto_id) REFERENCES productos(id),
  CONSTRAINT chk_detalle_compras_valores CHECK (cantidad > 0 AND costo_unitario >= 0 AND subtotal >= 0 AND impuesto >= 0 AND total_linea >= 0),
  INDEX idx_detalle_compras_producto (producto_id),
  INDEX idx_detalle_compras_compra_producto (compra_id, producto_id)
) ENGINE=InnoDB;

CREATE TABLE ventas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  folio VARCHAR(40) NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  usuario_nombre VARCHAR(160) NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado ENUM('EMITIDA','ANULADA') NOT NULL DEFAULT 'EMITIDA',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  impuestos DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  descuento DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  metodo_pago ENUM('EFECTIVO','TARJETA','TRANSFERENCIA','MIXTO') NOT NULL DEFAULT 'EFECTIVO',
  observaciones VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT uk_ventas_folio UNIQUE (folio),
  CONSTRAINT fk_ventas_usuarios FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT chk_ventas_totales CHECK (subtotal >= 0 AND impuestos >= 0 AND total >= 0),
  INDEX idx_ventas_fecha_estado (fecha, estado),
  INDEX idx_ventas_usuario_fecha (usuario_id, fecha)
) ENGINE=InnoDB;

CREATE TABLE detalle_ventas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  venta_id BIGINT UNSIGNED NOT NULL,
  producto_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(80) NOT NULL,
  producto_nombre VARCHAR(180) NOT NULL,
  cantidad DECIMAL(14,3) NOT NULL,
  precio_unitario DECIMAL(14,2) NOT NULL,
  iva_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  subtotal DECIMAL(14,2) NOT NULL,
  impuesto DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  descuento DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_linea DECIMAL(14,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_detalle_ventas_ventas FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_ventas_productos FOREIGN KEY (producto_id) REFERENCES productos(id),
  CONSTRAINT chk_detalle_ventas_valores CHECK (cantidad > 0 AND precio_unitario >= 0 AND subtotal >= 0 AND impuesto >= 0 AND total_linea >= 0),
  INDEX idx_detalle_ventas_producto (producto_id),
  INDEX idx_detalle_ventas_venta_producto (venta_id, producto_id)
) ENGINE=InnoDB;

CREATE TABLE kardex_movimientos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  producto_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  usuario_nombre VARCHAR(160) NOT NULL,
  compra_id BIGINT UNSIGNED NULL,
  venta_id BIGINT UNSIGNED NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tipo_movimiento ENUM('ENTRADA','SALIDA','AJUSTE_ENTRADA','AJUSTE_SALIDA','COMPRA','VENTA','ANULACION_VENTA') NOT NULL,
  cantidad DECIMAL(14,3) NOT NULL,
  stock_anterior DECIMAL(14,3) NOT NULL,
  stock_nuevo DECIMAL(14,3) NOT NULL,
  costo_unitario DECIMAL(14,2) NULL,
  precio_unitario DECIMAL(14,2) NULL,
  motivo VARCHAR(255) NOT NULL,
  referencia VARCHAR(80) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_kardex_productos FOREIGN KEY (producto_id) REFERENCES productos(id),
  CONSTRAINT fk_kardex_usuarios FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_kardex_compras FOREIGN KEY (compra_id) REFERENCES compras(id),
  CONSTRAINT fk_kardex_ventas FOREIGN KEY (venta_id) REFERENCES ventas(id),
  CONSTRAINT chk_kardex_cantidad CHECK (cantidad > 0),
  CONSTRAINT chk_kardex_stock CHECK (stock_anterior >= 0 AND stock_nuevo >= 0),
  INDEX idx_kardex_producto_fecha (producto_id, fecha),
  INDEX idx_kardex_usuario_fecha (usuario_id, fecha),
  INDEX idx_kardex_tipo_fecha (tipo_movimiento, fecha)
) ENGINE=InnoDB;


CREATE TABLE bancos (
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

CREATE TABLE pagos_banco (
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
