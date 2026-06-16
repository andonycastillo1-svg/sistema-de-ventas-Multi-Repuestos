<?php

declare(strict_types=1);

require __DIR__ . '/../api/config.php';

const SETUP_KEY = '123321';

function render_setup_error(string $title, string $message): void
{
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4">';
    echo '<div class="alert alert-warning"><h1 class="h4">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p class="mb-0">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></div>';
    echo '</main></body></html>';
    exit;
}

$key = $_GET['key'] ?? '';
if (!hash_equals(SETUP_KEY, (string) $key)) {
    render_setup_error(
        'Clave de instalación inválida',
        'Abre esta herramienta con ?key=123321 o cambia SETUP_KEY dentro de public_html/tools/reset_password.php.'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Restablecer contraseña</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4">';
    echo '<div class="card shadow-sm mx-auto" style="max-width: 560px"><div class="card-body"><h1 class="h4 mb-3">Restablecer contraseña</h1>';
    echo '<form method="post"><div class="mb-3"><label class="form-label">Usuario o correo existente</label><input class="form-control" name="usuario" required></div>';
    echo '<div class="mb-3"><label class="form-label">Nueva contraseña</label><input class="form-control" type="password" name="password" minlength="8" required></div>';
    echo '<button class="btn btn-primary w-100" type="submit">Guardar nueva contraseña</button></form>';
    echo '<p class="text-danger small mt-3 mb-0">Elimina este archivo después de restablecer la contraseña.</p></div></div></main></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['message' => 'Método no permitido.'], 405);
}

$usuario = trim((string) ($_POST['usuario'] ?? ''));
$password = trim((string) ($_POST['password'] ?? ''));

if ($usuario === '' || strlen($password) < 8) {
    json_response(['message' => 'Indica usuario/correo y una contraseña de al menos 8 caracteres.'], 400);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'UPDATE usuarios
            SET password_hash = :password_hash,
                estado = "ACTIVO"
          WHERE usuario = :usuario OR email = :usuario'
    );
    $stmt->execute([
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        'usuario' => $usuario,
    ]);

    if ($stmt->rowCount() === 0) {
        json_response(['message' => 'No existe un usuario con ese usuario o correo.'], 404);
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Contraseña actualizada</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4">';
    echo '<div class="alert alert-success">Contraseña actualizada y usuario activado. Elimina <strong>public_html/tools/reset_password.php</strong> y vuelve al <a href="../index.html">login</a>.</div>';
    echo '</main></body></html>';
} catch (Throwable $error) {
    json_response(['message' => $error->getMessage()], 500);
}
