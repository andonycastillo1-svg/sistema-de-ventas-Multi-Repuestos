<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['message' => 'Método no permitido.'], 405);
}

if (!defined('GEMINI_API_KEY') || strpos(GEMINI_API_KEY, 'TU_') === 0 || GEMINI_API_KEY === '') {
    json_response([
        'message' => 'La importación con IA no está configurada. ' .
                     'Agrega GEMINI_API_KEY en api/db_config.php ' .
                     '(clave gratuita en aistudio.google.com/apikey).',
    ], 503);
}

if (!function_exists('curl_init')) {
    json_response(['message' => 'cURL no está disponible en este servidor.'], 503);
}

$uploadError = $_FILES['factura']['error'] ?? UPLOAD_ERR_NO_FILE;
if ($uploadError !== UPLOAD_ERR_OK) {
    $errMsg = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo supera el tamaño permitido por el servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el tamaño permitido.',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente.',
        UPLOAD_ERR_NO_FILE    => 'No se recibió ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Error interno del servidor (tmp).',
        UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en el servidor.',
    ][$uploadError] ?? 'Error al recibir el archivo (código ' . $uploadError . ').';
    json_response(['message' => $errMsg], 400);
}

$file     = $_FILES['factura'];
$mimeType = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';

// Gemini inline_data soporta: image/jpeg, image/png, image/webp, image/gif
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mimeType, $allowedTypes, true)) {
    json_response([
        'message' => 'Tipo de archivo no soportado (' . $mimeType . '). ' .
                     'Los PDFs se convierten automáticamente a imagen en tu navegador.',
    ], 400);
}

if ($file['size'] > 10 * 1024 * 1024) {
    json_response(['message' => 'El archivo es demasiado grande. Máximo 10 MB.'], 400);
}

$rawFile = file_get_contents($file['tmp_name']);
if ($rawFile === false) {
    json_response(['message' => 'No se pudo leer el archivo subido.'], 500);
}
$fileData = base64_encode($rawFile);

$prompt = 'Eres un asistente experto en extracción de datos de facturas de compra. '
    . 'Analiza esta imagen y extrae los datos en JSON con esta estructura exacta '
    . '(sin texto adicional, sin markdown, solo JSON válido):\n'
    . '{\n'
    . '  "proveedor": "nombre del proveedor o empresa emisora",\n'
    . '  "nit": "número de NIT, RFC o RUC del proveedor si aparece",\n'
    . '  "factura_numero": "número o serie de la factura si aparece",\n'
    . '  "fecha": "fecha en formato YYYY-MM-DD, o vacío si no aparece",\n'
    . '  "items": [\n'
    . '    { "descripcion": "descripción completa del producto o servicio", '
    . '"cantidad": 1, "precio_unitario": 0.00 }\n'
    . '  ]\n'
    . '}\n'
    . 'Reglas: si un campo no aparece usa string vacío o 0; '
    . 'incluye TODOS los ítems de la factura; '
    . 'precio_unitario debe ser el precio por unidad (sin impuestos si están separados); '
    . 'responde SOLO con el JSON.';

$requestBody = json_encode([
    'contents' => [[
        'parts' => [
            ['inline_data' => ['mime_type' => $mimeType, 'data' => $fileData]],
            ['text' => $prompt],
        ],
    ]],
    'generationConfig' => [
        'temperature'      => 0.1,
        'responseMimeType' => 'application/json',
        'maxOutputTokens'  => 2048,
    ],
]);

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent?key='
     . rawurlencode(GEMINI_API_KEY);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestBody,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    json_response(['message' => 'Error de conexión con Gemini: ' . $curlError], 503);
}

if ($httpCode !== 200) {
    $errData = json_decode($response, true);
    $errMsg  = $errData['error']['message'] ?? 'HTTP ' . $httpCode;
    json_response(['message' => 'Error de Gemini: ' . $errMsg], 503);
}

$apiResponse = json_decode($response, true);
$text        = trim($apiResponse['candidates'][0]['content']['parts'][0]['text'] ?? '');

$extracted = json_decode($text, true);
if (!is_array($extracted)) {
    if (preg_match('/\{[\s\S]*\}/m', $text, $matches)) {
        $extracted = json_decode($matches[0], true);
    }
}

if (!is_array($extracted) || empty($extracted['items'])) {
    json_response([
        'message' => 'No se pudo extraer información de la factura. ' .
                     'Intenta con una imagen más clara, bien iluminada y enfocada.',
    ], 422);
}

$items = array_map(function (array $item): array {
    return [
        'descripcion'     => substr(trim((string) ($item['descripcion']     ?? '')), 0, 200),
        'cantidad'        => max(0, (float) ($item['cantidad']        ?? 1)),
        'precio_unitario' => max(0, (float) ($item['precio_unitario'] ?? 0)),
    ];
}, (array) $extracted['items']);

json_response([
    'data' => [
        'proveedor'      => substr(trim((string) ($extracted['proveedor']      ?? '')), 0, 200),
        'nit'            => substr(trim((string) ($extracted['nit']            ?? '')), 0, 50),
        'factura_numero' => substr(trim((string) ($extracted['factura_numero'] ?? '')), 0, 100),
        'fecha'          => preg_match('/^\d{4}-\d{2}-\d{2}$/', $extracted['fecha'] ?? '')
                            ? $extracted['fecha'] : '',
        'items'          => $items,
    ],
]);
