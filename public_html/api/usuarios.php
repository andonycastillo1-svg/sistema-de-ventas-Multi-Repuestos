<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

const ROLES_VALIDOS = ['ADMINISTRADOR', 'VENDEDOR'];

function normalizar_rol(string $rol): string
{
    $rol = strtoupper(trim($rol));
    return $rol === 'ADMIN' ? 'ADMINISTRADOR' : $rol;
}

function rol_id(PDO $pdo, string $rolNombre): int
{
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE nombre = :nombre LIMIT 1');
    $stmt->execute(['nombre' => $rolNombre]);
    $role = $stmt->fetch();
    if ($role) {
        return (int) $role['id'];
    }
    $stmt = $pdo->prepare('INSERT INTO roles (nombre, descripcion) VALUES (:nombre, :descripcion)');
    $stmt->execute(['nombre' => $rolNombre, 'descripcion' => $rolNombre]);
    return (int) $pdo->lastInsertId();
}

function admins_activos(PDO $pdo, int $excluirId = 0): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id = u.rol_id
          WHERE r.nombre IN ("ADMINISTRADOR","ADMIN") AND u.estado = "ACTIVO" AND u.id <> :id'
    );
    $stmt->execute(['id' => $excluirId]);
    return (int) $stmt->fetchColumn();
}

try {
    $pdo = db();
    $method = $_GET['_method'] ?? $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        require_role(['ADMINISTRADOR']);
        try {
            $stmt = $pdo->query(
                'SELECT u.id, u.usuario, u.email, u.nombre, u.estado, r.nombre AS rol,
                        u.ultimo_login_at, u.created_at
                   FROM usuarios u JOIN roles r ON r.id = u.rol_id
                  ORDER BY u.estado, r.nombre, u.nombre'
            );
        } catch (Throwable $e) {
            $stmt = $pdo->query(
                'SELECT u.id, u.usuario, u.email, u.nombre, u.estado, r.nombre AS rol,
                        NULL AS ultimo_login_at, NULL AS created_at
                   FROM usuarios u JOIN roles r ON r.id = u.rol_id
                  ORDER BY u.estado, r.nombre, u.nombre'
            );
        }
        $usuarios = $stmt->fetchAll();
        foreach ($usuarios as &$u) {
            $u['rol'] = normalizar_rol((string) $u['rol']);
        }
        unset($u);
        json_response(['usuarios' => $usuarios]);
    }

    if ($method === 'PUT') {
        $yo = require_role(['ADMINISTRADOR']);
        $yoId = (int) ($yo['sub'] ?? 0);
        $data = request_json();
        $id = (int) ($data['id'] ?? 0);
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $rol = normalizar_rol((string) ($data['rol'] ?? ''));
        $estado = strtoupper(trim((string) ($data['estado'] ?? 'ACTIVO')));
        $password = (string) ($data['password'] ?? '');

        if ($id <= 0 || $nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['message' => 'Nombre y un correo válido son requeridos.'], 400);
        }
        if (!in_array($rol, ROLES_VALIDOS, true) || !in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            json_response(['message' => 'Rol o estado inválido.'], 400);
        }
        if ($password !== '' && strlen($password) < 8) {
            json_response(['message' => 'La nueva contraseña debe tener al menos 8 caracteres.'], 400);
        }
        if ($id === $yoId && ($estado !== 'ACTIVO' || $rol !== 'ADMINISTRADOR')) {
            json_response(['message' => 'No puedes desactivarte ni quitarte el rol de administrador a ti mismo.'], 400);
        }
        if (($estado !== 'ACTIVO' || $rol !== 'ADMINISTRADOR') && admins_activos($pdo, $id) === 0) {
            json_response(['message' => 'Debe quedar al menos un administrador activo.'], 400);
        }

        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email AND id <> :id LIMIT 1');
        $stmt->execute(['email' => $email, 'id' => $id]);
        if ($stmt->fetch()) {
            json_response(['message' => 'Ese correo ya lo usa otro usuario.'], 400);
        }

        $sql = 'UPDATE usuarios SET nombre = :nombre, email = :email, rol_id = :rol_id, estado = :estado';
        $params = ['nombre' => $nombre, 'email' => $email, 'rol_id' => rol_id($pdo, $rol), 'estado' => $estado, 'id' => $id];
        if ($password !== '') {
            $sql .= ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }
        $stmt = $pdo->prepare($sql . ' WHERE id = :id');
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            $chk = $pdo->prepare('SELECT id FROM usuarios WHERE id = :id');
            $chk->execute(['id' => $id]);
            if (!$chk->fetch()) {
                json_response(['message' => 'El usuario no existe.'], 404);
            }
        }
        json_response(['message' => 'Usuario actualizado.']);
    }

    if ($method !== 'POST') {
        json_response(['message' => 'Método no permitido.'], 405);
    }

    // Solo se permite crear sin sesión cuando aún no existe ningún usuario (instalación inicial).
    $totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($totalUsuarios > 0) {
        require_role(['ADMINISTRADOR']);
    }

    $data = request_json();
    $usuario = trim((string) ($data['usuario'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $nombre = trim((string) ($data['nombre'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $rolNombre = $totalUsuarios === 0 ? 'ADMINISTRADOR' : normalizar_rol((string) ($data['rol'] ?? 'VENDEDOR'));

    if (!in_array($rolNombre, ROLES_VALIDOS, true)) {
        json_response(['message' => 'Rol inválido. Usa ADMINISTRADOR o VENDEDOR.'], 400);
    }
    if ($usuario === '' || $nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        json_response(['message' => 'Usuario, nombre, correo válido y contraseña de 8 caracteres son requeridos.'], 400);
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $usuario)) {
        json_response(['message' => 'El usuario solo puede tener letras, números, punto, guion y guion bajo (3 a 60).'], 400);
    }

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = :usuario OR email = :email LIMIT 1');
    $stmt->execute(['usuario' => $usuario, 'email' => $email]);
    if ($stmt->fetch()) {
        json_response(['message' => 'Ya existe un usuario con ese usuario o correo.'], 400);
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (rol_id, usuario, email, nombre, password_hash, estado)
         VALUES (:rol_id, :usuario, :email, :nombre, :password_hash, "ACTIVO")'
    );
    $stmt->execute([
        'rol_id' => rol_id($pdo, $rolNombre),
        'usuario' => $usuario,
        'email' => $email,
        'nombre' => $nombre,
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
    ]);
    $newId = (int) $pdo->lastInsertId();
    $pdo->commit();
    json_response(['id' => $newId, 'message' => 'Usuario creado.'], 201);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('usuarios.php: ' . $error->getMessage());
    json_response(['message' => 'No se pudo completar la operación con usuarios.'], 500);
}
