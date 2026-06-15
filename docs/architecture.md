# Arquitectura detallada: Inventarios, Compras y Ventas

## 1. Stack tecnológico

- **Backend:** Node.js 10 LTS + Express.js, estructurado en capas tipo Clean Architecture/MVC.
- **Frontend:** React.js SPA responsiva, Mobile-First, recomendando Tailwind CSS para utilidades de diseño y composición adaptable.
- **Base de datos:** MySQL Server/InnoDB con integridad referencial, restricciones, índices y transacciones ACID.
- **Autenticación:** bcrypt para hash de contraseñas y JWT para sesiones stateless.

## 2. Arquitectura por capas

```text
Cliente React SPA
  └── API REST Express.js
        ├── routes/        -> definición de endpoints y middlewares por recurso
        ├── controllers/   -> validación HTTP, request/response y códigos de estado
        ├── services/      -> reglas de negocio, transacciones e invariantes
        ├── repositories/  -> acceso SQL parametrizado
        ├── middlewares/   -> auth JWT, errores, validación y auditoría
        └── config/        -> conexión MySQL, JWT, variables de entorno
              └── MySQL Server/InnoDB
```

### Reglas transaccionales críticas

1. **Venta:** `ventas`, `detalle_ventas`, decremento de `productos.stock_actual` y alta en `kardex_movimientos` deben ejecutarse dentro de una única transacción.
2. **Compra recibida:** cuando una compra cambia a `RECIBIDO`, se incrementa el stock y se registra Kardex dentro de la misma transacción.
3. **Responsable inmutable:** `usuario_id` y `usuario_nombre` se copian desde el JWT a compras, ventas y Kardex al momento de emitir o recibir operaciones.
4. **Control de stock estricto:** el backend bloquea cada producto vendido con `SELECT ... FOR UPDATE` antes de descontar inventario.

## 3. Modelo Entidad-Relación textual

```text
roles 1──N usuarios
categorias 1──N productos
unidades_medida 1──N productos
proveedores 1──N compras
usuarios 1──N compras
usuarios 1──N ventas
usuarios 1──N kardex_movimientos
compras 1──N detalle_compras
ventas 1──N detalle_ventas
productos 1──N detalle_compras
productos 1──N detalle_ventas
productos 1──N kardex_movimientos
compras 0..1──N kardex_movimientos
ventas 0..1──N kardex_movimientos
```

### Tablas principales

- **usuarios:** credenciales, estado y nombre operativo usado para auditoría.
- **productos:** catálogo, precios, impuestos, unidad, estado y límites de stock.
- **proveedores:** datos fiscales y de contacto.
- **compras / detalle_compras:** cabecera y líneas de abastecimiento.
- **ventas / detalle_ventas:** cabecera y líneas de venta POS.
- **kardex_movimientos:** historial auditable de entradas, salidas, ajustes, compras y ventas.

El DDL completo está disponible en `database/schema.sql`.

## 4. Estructura sugerida del proyecto

```text
sistema-de-ventas-Multi-Repuestos/
├── backend/
│   ├── package.json
│   ├── .env.example
│   └── src/
│       ├── app.js
│       ├── server.js
│       ├── config/
│       │   ├── database.js
│       │   └── jwt.js
│       ├── routes/
│       │   ├── auth.routes.js
│       │   ├── productos.routes.js
│       │   ├── proveedores.routes.js
│       │   ├── compras.routes.js
│       │   ├── ventas.routes.js
│       │   └── kardex.routes.js
│       ├── controllers/
│       │   ├── auth.controller.js
│       │   ├── productos.controller.js
│       │   ├── proveedores.controller.js
│       │   ├── compras.controller.js
│       │   ├── ventas.controller.js
│       │   └── kardex.controller.js
│       ├── services/
│       │   ├── auth.service.js
│       │   ├── productos.service.js
│       │   ├── compras.service.js
│       │   ├── ventas.service.js
│       │   └── kardex.service.js
│       ├── repositories/
│       │   ├── usuarios.repository.js
│       │   ├── productos.repository.js
│       │   ├── compras.repository.js
│       │   ├── ventas.repository.js
│       │   └── kardex.repository.js
│       ├── middlewares/
│       │   ├── auth.js
│       │   ├── validate.js
│       │   └── errorHandler.js
│       └── utils/
│           ├── folios.js
│           └── money.js
├── frontend/
│   ├── package.json
│   ├── public/
│   └── src/
│       ├── App.jsx
│       ├── main.jsx
│       ├── api/
│       │   ├── httpClient.js
│       │   ├── authApi.js
│       │   ├── productosApi.js
│       │   ├── comprasApi.js
│       │   └── ventasApi.js
│       ├── components/
│       │   ├── Layout/
│       │   ├── Forms/
│       │   ├── Tables/
│       │   └── Ticket/
│       ├── pages/
│       │   ├── LoginPage.jsx
│       │   ├── DashboardPage.jsx
│       │   ├── ProductosPage.jsx
│       │   ├── ComprasPage.jsx
│       │   ├── PosVentaPage.jsx
│       │   └── KardexPage.jsx
│       ├── routes/
│       │   └── ProtectedRoute.jsx
│       ├── store/
│       │   ├── authStore.js
│       │   └── cartStore.js
│       └── styles/
│           └── index.css
└── database/
    ├── schema.sql
    └── seeds.sql
```

## 5. Endpoints principales de la API REST

### Autenticación y sesión

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/api/auth/login` | Valida correo/usuario y contraseña bcrypt; retorna JWT. |
| `GET` | `/api/auth/me` | Retorna el usuario autenticado desde el token. |
| `POST` | `/api/auth/logout` | Invalida sesión del lado cliente o registra cierre si se implementa blacklist. |

### Productos e inventario

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/productos` | Lista productos con filtros por texto, categoría, estado y bajo stock. |
| `GET` | `/api/productos/:id` | Obtiene un producto. |
| `POST` | `/api/productos` | Crea producto con SKU/código de barras y reglas de stock. |
| `PUT` | `/api/productos/:id` | Actualiza datos del producto. |
| `PATCH` | `/api/productos/:id/estado` | Activa o inactiva un producto. |
| `GET` | `/api/productos/buscar?query=` | Busca por nombre, SKU o código de barras para POS. |
| `GET` | `/api/inventario/alertas` | Productos con stock menor o igual al mínimo. |
| `POST` | `/api/inventario/ajustes` | Registra ajuste manual y Kardex con usuario autenticado. |
| `GET` | `/api/kardex` | Consulta movimientos por producto, fecha, usuario o tipo. |
| `GET` | `/api/productos/:id/kardex` | Kardex de un producto específico. |

### Proveedores y compras

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/proveedores` | Lista proveedores. |
| `POST` | `/api/proveedores` | Crea proveedor. |
| `PUT` | `/api/proveedores/:id` | Actualiza proveedor. |
| `GET` | `/api/compras` | Lista compras. |
| `POST` | `/api/compras` | Crea compra en estado `SOLICITADO`. |
| `GET` | `/api/compras/:id` | Obtiene compra con detalle. |
| `PATCH` | `/api/compras/:id/estado` | Cambia estado; si pasa a `RECIBIDO`, incrementa stock y genera Kardex. |
| `POST` | `/api/compras/:id/recibir` | Endpoint explícito para recepción total de compra. |

### Punto de venta

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/api/ventas` | Emite una venta, valida stock, guarda vendedor, detalle, Kardex y descuenta inventario. |
| `GET` | `/api/ventas` | Lista ventas por fecha, vendedor o folio. |
| `GET` | `/api/ventas/:id` | Obtiene venta con detalle. |
| `GET` | `/api/ventas/:id/ticket` | Devuelve datos formateados para ticket/factura térmica. |
| `POST` | `/api/ventas/:id/anular` | Anula venta y revierte stock mediante movimiento de Kardex. |

## 6. Flujo de login y persistencia del operador

1. El cliente llama a `POST /api/auth/login` con usuario/correo y contraseña.
2. El backend busca `usuarios.usuario` o `usuarios.email`, valida `password_hash` con bcrypt y genera JWT con `sub`, `usuario`, `nombre` y `rol`.
3. React guarda el JWT de forma segura según la estrategia elegida: memoria + refresh controlado, o cookie `HttpOnly` si se implementa backend para cookies.
4. Cada request protegida envía `Authorization: Bearer <token>`.
5. El middleware `auth.js` verifica el token y asigna `req.user = { id, usuario, nombre, rol }`.
6. Los servicios de compras, ventas y Kardex copian `req.user.id` y `req.user.nombre` a los registros de negocio para mantener la auditoría inmutable.

## 7. Ejemplo clave: procesamiento de venta

Los archivos `backend/src/middlewares/auth.js` y `backend/src/services/venta.service.js` muestran cómo extraer el vendedor desde el JWT y cómo emitir una venta transaccional con bloqueo de stock, inserción de detalle, movimiento Kardex y actualización del inventario.
