<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$usuarioSesion = require_role(['ADMINISTRADOR']);
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];

// GET — lista de cuentas por pagar
if ($method === 'GET') {
    $filtro = $_GET['filtro'] ?? 'pendiente'; // pendiente | pagado | todos

    $where = '';
    if ($filtro === 'pendiente') { $where = 'WHERE c.estado_pago = "PENDIENTE"'; }
    if ($filtro === 'pagado')    { $where = 'WHERE c.estado_pago = "PAGADO"'; }

    $stmt = $pdo->query(
        'SELECT c.id, c.folio, c.factura_numero,
                p.nombre AS proveedor,
                DATE(c.fecha) AS fecha_compra,
                c.fecha_vencimiento,
                c.fecha_pago,
                c.estado_pago,
                c.subtotal, c.impuestos, c.total, c.costo_envio,
                (c.total + c.costo_envio) AS total_con_envio,
                DATEDIFF(c.fecha_vencimiento, CURDATE()) AS dias_restantes
           FROM compras c
           JOIN proveedores p ON p.id = c.proveedor_id
         ' . $where . '
          ORDER BY c.fecha_vencimiento ASC, c.id DESC'
    );
    $compras = $stmt->fetchAll();

    // Resumen
    $stmt2 = $pdo->query(
        'SELECT
            COALESCE(SUM(CASE WHEN estado_pago="PENDIENTE" THEN total + costo_envio END), 0) AS total_pendiente,
            COALESCE(SUM(CASE WHEN estado_pago="PENDIENTE" AND fecha_vencimiento < CURDATE() THEN total + costo_envio END), 0) AS total_vencido,
            COALESCE(SUM(CASE WHEN estado_pago="PENDIENTE" AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN total + costo_envio END), 0) AS vence_7_dias,
            COUNT(CASE WHEN estado_pago="PENDIENTE" THEN 1 END) AS cant_pendiente,
            COUNT(CASE WHEN estado_pago="PENDIENTE" AND fecha_vencimiento < CURDATE() THEN 1 END) AS cant_vencido
           FROM compras'
    );
    $resumen = $stmt2->fetch();

    json_response(['compras' => $compras, 'resumen' => $resumen]);
}

// POST — marcar como pagada
if ($method === 'POST') {
    $data       = request_json();
    $compraId   = (int) ($data['compra_id'] ?? 0);
    $fechaPago  = trim((string) ($data['fecha_pago'] ?? date('Y-m-d')));

    if ($compraId <= 0) {
        json_response(['message' => 'ID de compra requerido.'], 400);
    }

    $stmt = $pdo->prepare(
        'UPDATE compras SET estado_pago = "PAGADO", fecha_pago = :fecha_pago
          WHERE id = :id AND estado_pago = "PENDIENTE"'
    );
    $stmt->execute(['fecha_pago' => $fechaPago, 'id' => $compraId]);

    if ($stmt->rowCount() === 0) {
        json_response(['message' => 'Compra no encontrada o ya estaba pagada.'], 400);
    }

    json_response(['message' => 'Compra marcada como pagada.']);
}

json_response(['message' => 'Método no permitido.'], 405);
