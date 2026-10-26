<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['message' => 'Método no permitido.'], 405);
}
require_role(['ADMINISTRADOR']);

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    json_response(['message' => 'Producto inválido.'], 400);
}
$stmt = db()->prepare('SELECT id FROM productos WHERE id = :id');
$stmt->execute(['id' => $id]);
if (!$stmt->fetch()) {
    json_response(['message' => 'El producto no existe.'], 404);
}

$path = product_image_path($id);

if (($_POST['accion'] ?? '') === 'eliminar') {
    if (is_file($path)) {
        @unlink($path);
    }
    json_response(['message' => 'Foto eliminada.', 'imagen' => null]);
}

$file = $_FILES['imagen'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_response(['message' => 'No se recibió la imagen.'], 400);
}
if ($file['size'] > 3 * 1024 * 1024) {
    json_response(['message' => 'La imagen supera 3 MB.'], 400);
}
// El navegador la convierte a JPEG; verificamos el contenido real, no la extensión.
$info = @getimagesize($file['tmp_name']);
if (!$info || $info[2] !== IMAGETYPE_JPEG) {
    json_response(['message' => 'Formato no válido. Sube una foto JPG, PNG o WebP.'], 400);
}

$dir = dirname($path);
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
    json_response(['message' => 'No se pudo crear la carpeta de imágenes en el servidor.'], 500);
}
if (!move_uploaded_file($file['tmp_name'], $path)) {
    json_response(['message' => 'No se pudo guardar la imagen.'], 500);
}
@chmod($path, 0644);
clearstatcache(true, $path);

json_response(['message' => 'Foto guardada.', 'imagen' => product_image_url($id)]);
