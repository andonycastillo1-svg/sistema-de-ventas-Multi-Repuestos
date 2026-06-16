# Arquitectura simplificada para cPanel: HTML + PHP + MySQL

## Importante

HTML por sí solo no puede conectarse de forma segura a MySQL porque expondría el usuario y contraseña de la base de datos en el navegador. Para un hosting compartido limitado, la opción más simple y compatible con cPanel es:

```text
Navegador
  └── HTML + Bootstrap + JavaScript en public_html
        └── API PHP en public_html/api
              └── MySQL de cPanel
```

Con este enfoque no necesitas Node.js, procesos persistentes, PM2 ni `npm install` en el hosting. Solo necesitas Apache/PHP/MySQL, que es lo más común en cPanel.

## Estructura incluida

```text
public_html/
├── index.html              # Pantalla de login y POS básico responsivo
├── assets/
│   └── app.js              # JavaScript del frontend HTML
└── api/
    ├── config.php          # Conexión PDO, JWT simple y helpers JSON
    ├── login.php           # Login contra tabla usuarios con password_hash
    └── venta.php           # Emisión transaccional de venta, stock y Kardex
```

## Instalación en cPanel

1. Crea la base de datos desde **MySQL Databases**.
2. Crea un usuario MySQL y asígnale permisos sobre la base.
3. Importa `database/schema.sql` desde **phpMyAdmin**.
4. Sube el contenido de `public_html/` de este repositorio al `public_html/` real de tu hosting.
5. Edita `public_html/api/config.php` y cambia `DB_NAME`, `DB_USER`, `DB_PASS` y `JWT_SECRET`.
6. Crea al menos un usuario en la tabla `usuarios` con contraseña generada por `password_hash` de PHP.
7. Abre tu dominio y prueba el login.

## Crear un usuario administrador inicial

Puedes generar el hash de contraseña con PHP local o desde una herramienta temporal en cPanel:

```php
<?php echo password_hash('MiPasswordSegura123', PASSWORD_BCRYPT); ?>
```

Luego inserta el usuario desde phpMyAdmin:

```sql
INSERT INTO roles (nombre, descripcion) VALUES ('ADMIN', 'Administrador del sistema');

INSERT INTO usuarios (rol_id, usuario, email, nombre, password_hash, estado)
VALUES (1, 'admin', 'admin@midominio.com', 'Administrador', 'PEGA_AQUI_EL_HASH', 'ACTIVO');
```

## Flujo de venta incluido

La API `api/venta.php` realiza las operaciones críticas dentro de una transacción:

1. Lee y valida el token JWT enviado por el frontend.
2. Bloquea cada producto con `SELECT ... FOR UPDATE`.
3. Valida que el producto exista, esté activo y tenga stock suficiente.
4. Inserta la cabecera en `ventas` con `usuario_id` y `usuario_nombre` del operador autenticado.
5. Inserta las líneas en `detalle_ventas`.
6. Descuenta `productos.stock_actual`.
7. Registra cada salida en `kardex_movimientos`.
8. Confirma con `COMMIT` o revierte con `ROLLBACK` ante errores.

## Limitaciones de este starter

- Es un punto de partida mínimo para hosting compartido, no una aplicación completa de inventario.
- El POS incluido pide el ID del producto; se recomienda agregar búsqueda por SKU/código de barras como siguiente paso.
- El JWT está implementado sin librerías externas para facilitar instalación, pero en una aplicación grande conviene usar una librería PHP mantenida.
- No incluye pantallas CRUD completas para productos, proveedores y compras.

## Por qué esta opción es más fácil en cPanel

- No requiere Node.js en el hosting.
- No requiere compilar React.
- No requiere procesos persistentes.
- Funciona con Apache + PHP + MySQL, el stack típico de hosting compartido.
- Se instala copiando archivos a `public_html` e importando el SQL en phpMyAdmin.
