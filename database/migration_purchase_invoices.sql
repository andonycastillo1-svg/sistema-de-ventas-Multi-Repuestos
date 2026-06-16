USE pmsguate_multi_repuestos;

ALTER TABLE compras
  ADD COLUMN factura_numero VARCHAR(80) NULL AFTER folio,
  ADD INDEX idx_compras_factura (factura_numero);
