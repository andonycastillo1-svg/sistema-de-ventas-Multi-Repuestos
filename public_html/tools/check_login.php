<?php

declare(strict_types=1);

require __DIR__ . '/../api/config.php';

const SETUP_KEY = 'CAMBIA_ESTA_CLAVE_TEMPORAL';

if (SETUP_KEY === 'CAMBIA_ESTA_CLAVE_TEMPORAL') {
    json_response([
        'message' => 'Antes de usar esta herramienta edita SETUP_KEY en public_html/tools/check_login.php.'
    ], 500);
}

$key = $_GET['key'] ?? '';
if (!hash_equals(SETUP_KEY, (string) $key)) {
    json_response(['message' => 'Clave de instalación inválida.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Diagnóstico de login</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4">';
    echo '<div class="card shadow-sm mx-auto" style="max-width: 560px"><div class="card-body"><h1 class="h4 mb-3">Diagnóstico de login</h1>';
    echo '<form method="post"><div class="mb-3"><label class="form-label">Usuario o correo</label><input class="form-control" name="usuario" required></div>';
    echo '<div class="mb-3"><label class="form-label">Contraseña a probar</label><input class="form-control" type="password" name="password" required></div>';
    echo '<button class="btn btn-primary w-100" type="submit">Revisar login</button></form>';
    echo '<p class="text-danger small mt-3 mb-0">Elimina este archivo después de diagnosticar el acceso.</p></div></div></main></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['message' => 'Método no permitido.'], 405);
}

$usuario = trim((string) ($_POST['usuario'] ?? ''));
$password = trim((string) ($_POST['password'] ?? ''));

if ($usuario === '' || $password === '') {
    json_response(['message' => 'Usuario/correo y contraseña son requeridos.'], 400);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id, usuario, email, nombre, estado, password_hash
           FROM usuarios
          WHERE usuario = :usuario OR email = :usuario
          LIMIT 1'
    );
    $stmt->execute(['usuario' => $usuario]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response([
            'ok' => false,
            'step' => 'usuario_no_encontrado',
            'message' => 'No existe un usuario con ese usuario o correo en la base configurada.'
        ], 404);
    }

    $hashInfo = password_get_info((string) $user['password_hash']);
    $passwordOk = password_verify($password, (string) $user['password_hash']);
    $active = $user['estado'] === 'ACTIVO';

    json_response([
        'ok' => $passwordOk && $active,
        'user' => [
            'id' => (int) $user['id'],
            'usuario' => $user['usuario'],
            'email' => $user['email'],
            'nombre' => $user['nombre'],
            'estado' => $user['estado'],
        ],
        'checks' => [
            'usuario_existe' => true,
            'estado_activo' => $active,
            'hash_bcrypt_detectado' => $hashInfo['algoName'] === 'bcrypt',
            'password_correcta' => $passwordOk,
        ],
        'message' => ($passwordOk && $active)
            ? 'El login debería funcionar. Si la pantalla no entra, revisa consola del navegador o ruta api/login.php.'
            : 'El usuario existe, pero está inactivo o la contraseña no coincide con el hash guardado.'
    ]);
} catch (Throwable $error) {
    json_response(['ok' => false, 'message' => $error->getMessage()], 500);
}
