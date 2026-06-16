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


## 7. Si el usuario existe pero no entra

Para diagnosticar un login que falla aunque el usuario ya exista:

1. Edita `public_html/tools/check_login.php`.
2. Cambia `SETUP_KEY` por una clave temporal segura, igual que en `create_admin.php`.
3. Abre:

```text
https://tudominio.com/tools/check_login.php?key=TU_CLAVE_TEMPORAL
```

4. Escribe el mismo usuario/correo y contraseña que estás usando en la pantalla de login.
5. La herramienta revisa:
   - si la app está conectando a la base correcta;
   - si el usuario existe;
   - si `estado` es `ACTIVO`;
   - si el hash guardado parece bcrypt;
   - si la contraseña coincide con `password_verify`.
6. Elimina `public_html/tools/check_login.php` al terminar.

Si `check_login.php` dice que el login debería funcionar pero `index.html` no entra, abre directamente:

```text
https://tudominio.com/api/login.php
```

Debe responder `Método no permitido` porque solo acepta POST. Si aparece 404, subiste los archivos en una ruta distinta o el dominio no apunta a ese `public_html`.


## 8. Si al presionar Entrar no aparece ningún error

Esto normalmente significa que el navegador no está cargando `assets/app.js`, la ruta `api/login.php` no responde como JSON, o el hosting está devolviendo HTML/404 en lugar de la API. La interfaz ahora muestra mensajes de estado y tiene un botón **Probar conexión** que llama a `api/health.php`.

Pasos rápidos:

1. Abre `https://tudominio.com/index.html`. Debe aparecer el mensaje `Aplicación cargada`.
2. Pulsa **Probar conexión**. Si falla, revisa que `public_html/api/health.php` exista en el hosting y que `api/config.php` tenga las credenciales correctas.
3. Abre directamente `https://tudominio.com/api/health.php`. Debe responder JSON.
4. Abre las herramientas del navegador con F12 y revisa la pestaña **Console** y **Network**.
5. Si el navegador cargó una versión vieja, limpia caché o usa `index.html?debug=1`.


## 9. Error `File not found` al ingresar

Si **Probar conexión** dice que MySQL conecta, pero al presionar **Entrar** aparece:

```text
La API no respondió JSON válido. Respuesta: File not found.
```

El problema no es la contraseña: el hosting no está encontrando `api/login.php`. Revisa esto:

1. En el administrador de archivos de cPanel debe existir exactamente:

```text
public_html/api/login.php
```

2. El nombre debe estar en minúsculas: `login.php`, no `Login.php` ni `login.php.txt`.
3. Sube nuevamente la carpeta completa `public_html/api/`, no solo `health.php`.
4. Abre directamente:

```text
https://tudominio.com/api/login.php
```

Debe responder JSON con `Método no permitido`. Si sigue diciendo `File not found`, el archivo no está en la ruta correcta o el dominio apunta a otro directorio.
5. Pulsa **Probar conexión** otra vez. Ahora también revisa que existan `api/login.php`, `api/venta.php`, `assets/app.js` e `index.html`.


## 10. Error `Credenciales inválidas`

Si ya conecta a MySQL y `api/login.php` existe, pero el login responde `Credenciales inválidas`, normalmente ocurre una de estas cosas:

- el usuario escrito no existe en la base `pmsguate_multi_repuestos`;
- el usuario existe, pero `estado` no está en `ACTIVO`;
- la contraseña fue guardada como texto plano y no con `password_hash`;
- estás usando una contraseña distinta a la que quedó guardada.

La respuesta puede incluir un código para diagnóstico:

| Código | Significado | Solución |
|---|---|---|
| `USER_NOT_FOUND` | No existe el usuario o correo en la tabla `usuarios`. | Crea el usuario o revisa que estés conectado a la base correcta. |
| `USER_INACTIVE` | El usuario existe, pero no está `ACTIVO`. | Cambia `estado` a `ACTIVO`. |
| `PASSWORD_MISMATCH` | La contraseña no coincide con `password_hash`. | Restablece la contraseña. |

### Restablecer contraseña desde navegador

Se agregó una herramienta temporal:

```text
public_html/tools/reset_password.php
```

Pasos:

1. Edita `public_html/tools/reset_password.php`.
2. Cambia `SETUP_KEY` por una clave temporal segura.
3. Abre:

```text
https://tudominio.com/tools/reset_password.php?key=TU_CLAVE_TEMPORAL
```

4. Escribe el usuario o correo existente y la nueva contraseña.
5. La herramienta guardará la contraseña usando `password_hash` y pondrá el usuario en `ACTIVO`.
6. Elimina `public_html/tools/reset_password.php` al terminar.


### Solución directa para `PASSWORD_MISMATCH`

El código `PASSWORD_MISMATCH` significa que el usuario `admin` sí existe y está activo, pero la contraseña que escribes no coincide con el valor guardado en `usuarios.password_hash`. La solución más rápida es restablecerla:

1. Sube `public_html/tools/reset_password.php`.
2. Edita `SETUP_KEY` dentro de ese archivo.
3. Abre `https://tudominio.com/tools/reset_password.php?key=TU_CLAVE_TEMPORAL`.
4. Escribe `admin` y define una nueva contraseña.
5. Vuelve a entrar con esa nueva contraseña.
6. Elimina `reset_password.php` cuando termines.

No edites `password_hash` escribiendo la contraseña en texto plano en phpMyAdmin; siempre debe guardarse como hash bcrypt generado por PHP.


## 11. Si `reset_password.php` muestra texto JSON y no aparece formulario

Para simplificar la instalación, `reset_password.php` ahora trae una clave temporal inicial:

```php
const SETUP_KEY = '123321';
```

Abre exactamente esta URL, ajustando dominio y carpeta:

```text
https://tudominio.com/SistemadeVentas/public_html/tools/reset_password.php?key=123321
```

Si cambiaste `SETUP_KEY`, entonces el valor de `key=` en la URL debe ser exactamente igual. Cuando termines de restablecer la contraseña, elimina `reset_password.php` del hosting.


## 12. Nuevos módulos: productos, compras, usuarios, reportes, bancos y descuentos

Para habilitar los nuevos módulos en una base ya creada, importa en phpMyAdmin:

```text
database/migration_cpanel_modules.sql
```

Ese script agrega:

- descuento en `ventas`;
- descuento en `detalle_ventas`;
- tabla `bancos`;
- tabla `pagos_banco`.

Luego sube estos endpoints nuevos a `public_html/api/`:

```text
productos.php
compras.php
usuarios.php
reportes.php
bancos.php
```

La pantalla principal ahora contempla:

- creación de productos con costo, precio, IVA y stock;
- ingreso de compras recibidas que suman inventario y registran Kardex;
- creación de usuarios;
- venta con descuento;
- total vendido por rango de fechas;
- creación de bancos y registro de pagos/depósitos bancarios.


## 13. Menú principal, módulos separados y marca

La interfaz ahora usa el nombre **Multi Parts S&A**, muestra un logo en el login y separa los módulos en un menú principal responsivo:

- Punto de venta;
- Productos;
- Compras;
- Usuarios;
- Bancos y pagos;
- Reportes.

Sube también:

```text
public_html/assets/logo.svg
public_html/index.html
public_html/assets/app.js
```

Si tienes el logo original en PNG/JPG, puedes reemplazar `public_html/assets/logo.svg` por tu archivo real manteniendo el mismo nombre o ajustando la ruta en `index.html`.
