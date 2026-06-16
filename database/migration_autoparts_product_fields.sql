USE pmsguate_multi_repuestos;

ALTER TABLE productos
  ADD COLUMN marca_vehiculo VARCHAR(80) NULL AFTER descripcion,
  ADD COLUMN modelo_vehiculo VARCHAR(80) NULL AFTER marca_vehiculo,
  ADD COLUMN anio_inicio SMALLINT UNSIGNED NULL AFTER modelo_vehiculo,
  ADD COLUMN anio_fin SMALLINT UNSIGNED NULL AFTER anio_inicio,
  ADD COLUMN motor VARCHAR(80) NULL AFTER anio_fin,
  ADD COLUMN lado ENUM('NO_APLICA','IZQUIERDO','DERECHO','DELANTERO','TRASERO') NOT NULL DEFAULT 'NO_APLICA' AFTER motor,
  ADD COLUMN ubicacion VARCHAR(120) NULL AFTER lado,
  ADD INDEX idx_productos_vehiculo (marca_vehiculo, modelo_vehiculo, anio_inicio, anio_fin);
