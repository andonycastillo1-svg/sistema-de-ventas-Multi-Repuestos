<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        current_user();
        $stmt = $pdo->query(
            'SELECT u.id, u.usuario, u.email, u.nombre, u.estado, r.nombre AS rol
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              ORDER BY u.id DESC'
        );
        json_response(['usuarios' => $stmt->fetchAll()]);
    }

    if ($method !== 'POST') {
        json_response(['message' => 'Método no permitido.'], 405);
    }

    current_user();
    $data = request_json();
    $usuario = trim((string) ($data['usuario'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $nombre = trim((string) ($data['nombre'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $rolEntrada = strtoupper(trim((string) ($data['rol'] ?? 'VENDEDOR')));
    $mapRoles = [
        'ADMIN' => 'ADMINISTRADOR',
        'ADMINISTRADOR' => 'ADMINISTRADOR',
        'VENDEDOR' => 'VENDEDOR',
        'VENDEDRO' => 'VENDEDOR',
        'VENDEDOR ' => 'VENDEDOR',
    ];
    $rolNombre = $mapRoles[$rolEntrada] ?? $rolEntrada;
    $rolesPermitidos = ['ADMINISTRADOR', 'VENDEDOR'];

    if (!in_array($rolNombre, $rolesPermitidos, true)) {
        json_response(['message' => 'Rol inválido. Usa ADMINISTRADOR o VENDEDOR.'], 400);
    }

    if ($usuario === '' || $email === '' || $nombre === '' || strlen($password) < 8) {
        json_response(['message' => 'Usuario, email, nombre y contraseña de 8 caracteres son requeridos.'], 400);
    }

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = :usuario OR email = :email LIMIT 1');
    $stmt->execute(['usuario' => $usuario, 'email' => $email]);
    if ($stmt->fetch()) {
        json_response(['message' => 'Ya existe un usuario con ese usuario o correo.'], 400);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id FROM roles WHERE nombre = :nombre LIMIT 1');
    $stmt->execute(['nombre' => $rolNombre]);
    $role = $stmt->fetch();

    if ($role) {
        $rolId = (int) $role['id'];
    } else {
        $stmt = $pdo->prepare('INSERT INTO roles (nombre, descripcion) VALUES (:nombre, :descripcion)');
        $stmt->execute(['nombre' => $rolNombre, 'descripcion' => $rolNombre]);
        $rolId = (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (rol_id, usuario, email, nombre, password_hash, estado)
         VALUES (:rol_id, :usuario, :email, :nombre, :password_hash, "ACTIVO")'
    );
    $stmt->execute([
        'rol_id' => $rolId,
        'usuario' => $usuario,
        'email' => $email,
        'nombre' => $nombre,
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
    ]);

    $pdo->commit();
    json_response(['id' => (int) $pdo->lastInsertId(), 'message' => 'Usuario creado.'], 201);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response([
        'message' => 'No se pudo crear el usuario: ' . $error->getMessage(),
        'file' => basename($error->getFile()),
        'line' => $error->getLine(),
    ], 500);
}
