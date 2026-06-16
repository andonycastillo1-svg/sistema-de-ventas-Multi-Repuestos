<?php

declare(strict_types=1);

require __DIR__ . '/../api/config.php';

const SETUP_KEY = 'CAMBIA_ESTA_CLAVE_TEMPORAL';

if (SETUP_KEY === 'CAMBIA_ESTA_CLAVE_TEMPORAL') {
    json_response([
        'message' => 'Antes de usar esta herramienta edita SETUP_KEY en public_html/tools/create_admin.php.'
    ], 500);
}

$key = $_GET['key'] ?? '';
if (!hash_equals(SETUP_KEY, (string) $key)) {
    json_response(['message' => 'Clave de instalación inválida.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Crear administrador</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4">';
    echo '<div class="card shadow-sm mx-auto" style="max-width: 520px"><div class="card-body"><h1 class="h4 mb-3">Crear usuario administrador</h1>';
    echo '<form method="post"><div class="mb-3"><label class="form-label">Usuario</label><input class="form-control" name="usuario" required></div>';
    echo '<div class="mb-3"><label class="form-label">Correo</label><input class="form-control" type="email" name="email" required></div>';
    echo '<div class="mb-3"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>';
    echo '<div class="mb-3"><label class="form-label">Contraseña</label><input class="form-control" type="password" name="password" minlength="8" required></div>';
    echo '<button class="btn btn-primary w-100" type="submit">Crear administrador</button></form>';
    echo '<p class="text-danger small mt-3 mb-0">Elimina este archivo después de crear el usuario.</p></div></div></main></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['message' => 'Método no permitido.'], 405);
}

$usuario = trim((string) ($_POST['usuario'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$nombre = trim((string) ($_POST['nombre'] ?? ''));
$password = trim((string) ($_POST['password'] ?? ''));

if ($usuario === '' || $email === '' || $nombre === '' || strlen($password) < 8) {
    json_response(['message' => 'Completa usuario, correo, nombre y contraseña de al menos 8 caracteres.'], 400);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE nombre = :nombre LIMIT 1');
    $stmt->execute(['nombre' => 'ADMIN']);
    $role = $stmt->fetch();

    if ($role) {
        $rolId = (int) $role['id'];
    } else {
        $stmt = $pdo->prepare('INSERT INTO roles (nombre, descripcion) VALUES (:nombre, :descripcion)');
        $stmt->execute(['nombre' => 'ADMIN', 'descripcion' => 'Administrador del sistema']);
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

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Administrador creado</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4">';
    echo '<div class="alert alert-success">Usuario administrador creado. Ahora elimina <strong>public_html/tools/create_admin.php</strong> del hosting y entra desde <a href="../index.html">la pantalla de login</a>.</div>';
    echo '</main></body></html>';
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response(['message' => $error->getMessage()], 400);
}
