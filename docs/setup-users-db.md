# Cómo conectar la base de datos y crear usuarios para entrar

Esta guía aplica a la versión `HTML + PHP + MySQL` ubicada en `public_html/`, pensada para hosting compartido con cPanel.

## 1. Crear y conectar la base de datos en cPanel

1. Entra a cPanel.
2. Abre **MySQL Databases**.
3. Crea una base de datos, por ejemplo `multi_repuestos`.
4. Crea un usuario MySQL, por ejemplo `appventas`.
5. Asigna ese usuario a la base de datos con permisos sobre la base.
6. Recuerda que cPanel normalmente agrega prefijo. Por ejemplo:
   - Base real: `pmsguate_multi_repuestos`
   - Usuario real: `pmsguate_appventas`
7. Abre **phpMyAdmin**.
8. Selecciona la base de datos real.
9. Importa `database/schema.sql`.

Si `schema.sql` incluye `CREATE DATABASE multi_repuestos` y tu base real tiene prefijo de cPanel, puedes eliminar estas líneas antes de importar:

```sql
CREATE DATABASE IF NOT EXISTS multi_repuestos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE multi_repuestos;
```

## 2. Configurar conexión en PHP

Edita `public_html/api/config.php` en tu hosting y cambia estos valores:

```php
const DB_HOST = 'localhost';
const DB_NAME = 'pmsguate_multi_repuestos';
const DB_USER = 'pmsguate_appventas';
const DB_PASS = 'TU_PASSWORD_MYSQL';
const JWT_SECRET = 'UN_SECRETO_LARGO_ALEATORIO';
```

En la mayoría de hostings compartidos `DB_HOST` es `localhost`. Si tu proveedor usa otro host, cPanel o soporte técnico te lo indicará.

## 3. Probar conexión MySQL

Sube los archivos y abre en el navegador:

```text
https://tudominio.com/api/health.php
```

Si todo está bien verás una respuesta parecida a:

```json
{
  "database": "connected",
  "message": "Conexión a MySQL correcta."
}
```

Si aparece error, revisa `DB_NAME`, `DB_USER`, `DB_PASS`, permisos del usuario MySQL y que hayas importado el SQL en la base correcta.

## 4. Crear el primer usuario administrador desde navegador

El proyecto incluye una herramienta temporal para crear el primer usuario:

```text
public_html/tools/create_admin.php
```

Pasos:

1. Edita `public_html/tools/create_admin.php`.
2. Cambia esta línea por una clave temporal difícil de adivinar:

```php
const SETUP_KEY = 'CAMBIA_ESTA_CLAVE_TEMPORAL';
```

Por ejemplo:

```php
const SETUP_KEY = 'instalar-2026-clave-muy-segura';
```

3. Sube el archivo al hosting.
4. Abre esta URL, cambiando dominio y clave:

```text
https://tudominio.com/tools/create_admin.php?key=instalar-2026-clave-muy-segura
```

5. Completa usuario, correo, nombre y contraseña.
6. Al guardar, entra desde:

```text
https://tudominio.com/index.html
```

7. Muy importante: elimina `public_html/tools/create_admin.php` después de crear el usuario.

## 5. Crear usuario manualmente desde phpMyAdmin

Si prefieres no usar la herramienta temporal, puedes crear el hash de contraseña con PHP:

```php
<?php echo password_hash('MiPasswordSegura123', PASSWORD_BCRYPT); ?>
```

Luego ejecuta en phpMyAdmin:

```sql
INSERT INTO roles (nombre, descripcion)
VALUES ('ADMIN', 'Administrador del sistema');

INSERT INTO usuarios (rol_id, usuario, email, nombre, password_hash, estado)
VALUES (1, 'admin', 'admin@tudominio.com', 'Administrador', 'PEGA_AQUI_EL_HASH', 'ACTIVO');
```

Si ya existe el rol `ADMIN`, usa su `id` real en `rol_id`.

## 6. Errores comunes

| Error | Causa probable | Solución |
|---|---|---|
| `Access denied for user` | Usuario o contraseña MySQL incorrectos | Revisa `DB_USER`, `DB_PASS` y permisos en cPanel. |
| `Unknown database` | Nombre de base sin prefijo cPanel | Usa el nombre completo, por ejemplo `pmsguate_multi_repuestos`. |
| Login dice credenciales inválidas | Contraseña no fue guardada con `password_hash` | Genera un hash bcrypt con PHP y actualiza `usuarios.password_hash`. |
| `Token requerido` al vender | No has iniciado sesión o localStorage no tiene token | Vuelve a iniciar sesión. |
| `Table ... doesn't exist` | No importaste `database/schema.sql` | Importa el SQL en phpMyAdmin sobre la base correcta. |
