USE pmsguate_multi_repuestos;

ALTER TABLE ventas
  MODIFY metodo_pago ENUM('EFECTIVO','TARJETA','DEPOSITO','CARGO_EXPRESS','TRANSFERENCIA','MIXTO') NOT NULL DEFAULT 'EFECTIVO';

INSERT INTO roles (nombre, descripcion)
SELECT 'ADMINISTRADOR', 'Administrador del sistema'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'ADMINISTRADOR');

INSERT INTO roles (nombre, descripcion)
SELECT 'VENDEDOR', 'Vendedor / operador de punto de venta'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'VENDEDOR');
