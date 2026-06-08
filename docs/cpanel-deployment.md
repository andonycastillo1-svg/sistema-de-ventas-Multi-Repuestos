# Guía para correr la aplicación en cPanel/hosting compartido

> Estado actual del repositorio: este proyecto contiene entregables de arquitectura, DDL y ejemplos clave de backend, pero todavía no incluye una aplicación Express/React completamente scaffolded y lista para producción. Para publicarla en cPanel primero se debe completar el backend ejecutable, compilar el frontend y configurar la base de datos MySQL.

## 1. Requisitos que debes confirmar con tu hosting

Antes de subir archivos, valida en cPanel o con soporte técnico del proveedor:

1. **Soporte para Node.js:** debe existir la opción **Setup Node.js App**, **Node.js Selector** o equivalente.
2. **Versión de Node:** el requerimiento original pide Node.js 10 LTS, pero esa versión está obsoleta. Si el hosting no ofrece Node 10, usa la versión LTS disponible y ajusta dependencias; si el requisito contractual exige Node 10, solicita explícitamente esa versión al proveedor.
3. **MySQL:** acceso a **MySQL Databases** y **phpMyAdmin** o cliente remoto.
4. **Terminal/SSH:** recomendado para ejecutar `npm install`, migraciones y build de React.
5. **Dominio/subdominio:** define si la app quedará en el dominio principal, por ejemplo `midominio.com`, o en un subdominio como `ventas.midominio.com`.

## 2. Estructura recomendada en cPanel

Para un despliegue sencillo, usa el backend Node como aplicación principal y sirve el build de React desde Express:

```text
/home/usuario_cpanel/
├── sistema-ventas-api/          # Node.js app fuera de public_html
│   ├── package.json
│   ├── src/
│   ├── public/                  # aquí se copia el build de React
│   └── .env
└── public_html/                 # puede quedar vacío o redirigir al Node app
```

Si tu hosting no permite que Node sirva el dominio principal, usa:

```text
/home/usuario_cpanel/
├── sistema-ventas-api/          # backend Node en subdominio api.midominio.com
└── public_html/                 # frontend React compilado
```

En ese segundo escenario, React consume la API usando `https://api.midominio.com/api`.

## 3. Crear la base de datos MySQL

1. En cPanel entra a **MySQL Databases**.
2. Crea una base de datos, por ejemplo `usuario_multi_repuestos`.
3. Crea un usuario MySQL, por ejemplo `usuario_appventas`.
4. Asigna permisos al usuario sobre la base de datos.
5. Abre **phpMyAdmin** y ejecuta el DDL de `database/schema.sql`.

Si el nombre real de la base de datos no será `multi_repuestos`, cambia o elimina estas líneas del script antes de importarlo:

```sql
CREATE DATABASE IF NOT EXISTS multi_repuestos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE multi_repuestos;
```

En hosting compartido normalmente cPanel prefija los nombres con tu usuario, por ejemplo `usuario_multi_repuestos`.

## 4. Variables de entorno del backend

Crea un archivo `.env` en la carpeta de la aplicación Node con valores similares:

```env
NODE_ENV=production
PORT=3000

DB_HOST=localhost
DB_PORT=3306
DB_NAME=usuario_multi_repuestos
DB_USER=usuario_appventas
DB_PASSWORD=CAMBIA_ESTA_PASSWORD

JWT_SECRET=CAMBIA_ESTE_SECRETO_LARGO_Y_ALEATORIO
JWT_EXPIRES_IN=8h

APP_URL=https://ventas.midominio.com
CORS_ORIGIN=https://ventas.midominio.com
```

Nunca subas `.env` al repositorio ni lo dejes dentro de `public_html`.

## 5. Preparar el backend Express

El repositorio actual ya contiene ejemplos de middleware y servicio, pero para ejecutar en cPanel necesitas completar un entrypoint Express, por ejemplo:

```text
backend/
├── package.json
└── src/
    ├── app.js
    ├── server.js
    ├── config/database.js
    ├── routes/*.routes.js
    ├── controllers/*.controller.js
    ├── services/*.service.js
    └── middlewares/*.js
```

El archivo `server.js` debe escuchar el puerto entregado por cPanel:

```js
'use strict';

const app = require('./app');
const port = process.env.PORT || 3000;

app.listen(port, function () {
  console.log('Servidor iniciado en puerto ' + port);
});
```

En **Setup Node.js App** configura:

- **Application root:** `sistema-ventas-api` o la carpeta donde subiste el backend.
- **Application URL:** dominio o subdominio elegido.
- **Application startup file:** `src/server.js`.
- **Node.js version:** la versión disponible compatible con tus dependencias.
- **Environment:** `production`.

Luego ejecuta desde la interfaz o terminal:

```bash
npm install --production
```

Finalmente pulsa **Restart** en la aplicación Node.js de cPanel.

## 6. Preparar y subir el frontend React

En tu equipo local o por SSH dentro del hosting:

```bash
cd frontend
npm install
npm run build
```

Después copia el contenido generado por React:

- Si Express servirá el frontend: copia `frontend/build/` o `frontend/dist/` a `backend/public/`.
- Si el frontend va separado: copia el contenido de `build/` o `dist/` a `public_html/`.

Configura la URL de API en React antes del build:

```env
REACT_APP_API_URL=https://ventas.midominio.com/api
```

Si usas Vite en lugar de Create React App, la variable normalmente debe iniciar con `VITE_`, por ejemplo:

```env
VITE_API_URL=https://ventas.midominio.com/api
```

## 7. Configurar rutas SPA

React es una SPA; si refrescas `/productos` o `/ventas`, Apache debe devolver `index.html`.

Si el frontend está en `public_html`, crea o actualiza `public_html/.htaccess`:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

Si Express sirve React, agrega una ruta catch-all en `app.js` para entregar `index.html` después de registrar las rutas `/api`.

## 8. Checklist de seguridad para producción

- Usa HTTPS obligatorio desde cPanel/AutoSSL.
- Define un `JWT_SECRET` largo, único y aleatorio.
- Mantén `.env` fuera de `public_html`.
- Crea un usuario MySQL con permisos limitados solo sobre la base de datos de la app.
- No guardes contraseñas en texto plano; usa bcrypt.
- Valida que las rutas de ventas y compras usen middleware JWT antes de ejecutar servicios transaccionales.
- Configura CORS solo para tu dominio de frontend.
- Haz respaldos automáticos de base de datos desde cPanel o cron.

## 9. Orden práctico de despliegue

1. Completar backend Express ejecutable y frontend React compilable.
2. Crear base de datos y usuario MySQL en cPanel.
3. Importar `database/schema.sql` ajustando el nombre real de la base de datos.
4. Subir backend a una carpeta fuera de `public_html`.
5. Crear `.env` de producción.
6. Configurar **Setup Node.js App** con `src/server.js` como startup file.
7. Ejecutar `npm install --production` y reiniciar la app Node.
8. Compilar React y copiar el build a `backend/public` o `public_html`.
9. Configurar `.htaccess` si el frontend queda en Apache.
10. Probar login, búsqueda de productos, venta con stock suficiente, venta sin stock y Kardex.

## 10. Problemas frecuentes

| Problema | Causa probable | Solución |
|---|---|---|
| `Cannot find module` | Dependencias no instaladas | Ejecutar `npm install --production` en Application root. |
| Error de conexión MySQL | Nombre de DB con prefijo cPanel incorrecto | Revisar `DB_NAME`, `DB_USER`, `DB_PASSWORD` y permisos. |
| API responde 404 | Startup file o rutas mal configuradas | Verificar `src/server.js`, prefijo `/api` y Restart de Node app. |
| React muestra pantalla blanca | API URL incorrecta o assets no encontrados | Revisar variables de entorno y ruta base del build. |
| Refrescar `/ventas` da 404 | Falta rewrite de SPA | Agregar `.htaccess` o catch-all en Express. |
| Token inválido | `JWT_SECRET` cambió o expiró | Volver a iniciar sesión y revisar `.env`. |
