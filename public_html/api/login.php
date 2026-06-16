<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['message' => 'Método no permitido.'], 405);
}

$data = request_json();
$usuario = trim((string) ($data['usuario'] ?? ''));
$password = trim((string) ($data['password'] ?? ''));

if ($usuario === '' || $password === '') {
    json_response(['message' => 'Usuario y contraseña son requeridos.'], 400);
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT u.id, u.usuario, u.email, u.nombre, u.password_hash, u.estado, r.nombre AS rol
       FROM usuarios u
       JOIN roles r ON r.id = u.rol_id
      WHERE u.usuario = :usuario OR u.email = :usuario
      LIMIT 1'
);
$stmt->execute(['usuario' => $usuario]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['message' => 'Credenciales inválidas.', 'code' => 'USER_NOT_FOUND'], 401);
}

if ($user['estado'] !== 'ACTIVO') {
    json_response(['message' => 'Credenciales inválidas.', 'code' => 'USER_INACTIVE'], 401);
}

if (!password_verify($password, $user['password_hash'])) {
    json_response(['message' => 'Credenciales inválidas.', 'code' => 'PASSWORD_MISMATCH'], 401);
}

$payload = [
    'sub' => (int) $user['id'],
    'usuario' => $user['usuario'],
    'nombre' => $user['nombre'],
    'rol' => $user['rol'],
    'iat' => time(),
    'exp' => time() + (8 * 60 * 60),
];

json_response([
    'token' => jwt_encode($payload),
    'usuario' => [
        'id' => (int) $user['id'],
        'usuario' => $user['usuario'],
        'nombre' => $user['nombre'],
        'rol' => $user['rol'],
    ],
]);
