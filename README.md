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

Para publicar la aplicación en un hosting con cPanel, consulta `docs/cpanel-deployment.md`. Esa guía cubre requisitos del hosting, alternativas para hosting compartido limitado, creación de base de datos MySQL, variables de entorno, configuración de **Setup Node.js App**, build de React, reglas `.htaccess` para SPA y checklist de seguridad.


## Versión simplificada para hosting compartido

Si el objetivo es instalar más fácil en un cPanel limitado, usa la variante `HTML + PHP + MySQL` documentada en `docs/html-mysql-cpanel.md`. El directorio `public_html/` incluye un starter sin Node.js: `index.html`, JavaScript básico y endpoints PHP para login y emisión de ventas contra MySQL.


## Conexión a MySQL y creación de usuarios

Para conectar la aplicación a la base de datos y crear el primer usuario administrador, sigue `docs/setup-users-db.md`. También se incluye `public_html/api/health.php` para probar la conexión MySQL y `public_html/tools/create_admin.php` como herramienta temporal para crear el primer administrador desde el navegador.
