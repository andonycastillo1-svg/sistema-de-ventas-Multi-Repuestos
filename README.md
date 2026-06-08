# Sistema de Control de Inventarios, Compras y Ventas

Este repositorio contiene la propuesta técnica para una aplicación web moderna de control de inventarios, compras y ventas construida con Node.js 10 LTS, Express.js, React.js SPA y MySQL Server.

## Entregables incluidos

- `docs/architecture.md`: arquitectura propuesta, MER textual, estructura de proyecto, endpoints REST y reglas transaccionales.
- `database/schema.sql`: DDL completo de MySQL con tablas, llaves primarias, llaves foráneas, índices y restricciones.
- `backend/src/middlewares/auth.js`: middleware JWT para extraer el usuario autenticado desde el token.
- `backend/src/services/venta.service.js`: ejemplo clave del procesamiento transaccional de una venta, persistiendo el vendedor responsable y actualizando inventario/Kardex.

## Principios de diseño

- Arquitectura limpia/MVC con separación entre rutas, controladores, servicios, repositorios y middlewares.
- Inventario estricto: no se permite vender por encima del stock disponible.
- Auditoría operativa: compras, ventas y movimientos de Kardex registran el usuario responsable autenticado.
- Transacciones de base de datos para operaciones que afectan ventas, compras, stock y Kardex.

## Despliegue en cPanel

Para publicar la aplicación en un hosting con cPanel, consulta `docs/cpanel-deployment.md`. Esa guía cubre requisitos del hosting, creación de base de datos MySQL, variables de entorno, configuración de **Setup Node.js App**, build de React, reglas `.htaccess` para SPA y checklist de seguridad.
