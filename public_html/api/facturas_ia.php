<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['message' => 'Método no permitido.'], 405);
}

if (!defined('CLAUDE_API_KEY') || strpos(CLAUDE_API_KEY, 'TU_') === 0 || CLAUDE_API_KEY === '') {
    json_response([
        'message' => 'La importación con IA no está configurada. ' .
                     'Agrega CLAUDE_API_KEY en api/db_config.php.',
    ], 503);
}

if (!function_exists('curl_init')) {
    json_response(['message' => 'cURL no está disponible en este servidor.'], 503);
}

$uploadError = $_FILES['factura']['error'] ?? UPLOAD_ERR_NO_FILE;
if ($uploadError !== UPLOAD_ERR_OK) {
    $messages = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo supera el tamaño permitido por el servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el tamaño permitido.',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente.',
        UPLOAD_ERR_NO_FILE    => 'No se recibió ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Error interno del servidor (tmp).',
        UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en el servidor.',
    ];
    json_response(['message' => $messages[$uploadError] ?? 'Error al recibir el archivo.'], 400);
}

$file = $_FILES['factura'];
$mimeType = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';

$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
if (!in_array($mimeType, $allowedTypes, true)) {
    json_response(['message' => 'Tipo de archivo no soportado. Usa JPG, PNG, WebP o PDF.'], 400);
}

if ($file['size'] > 10 * 1024 * 1024) {
    json_response(['message' => 'El archivo es demasiado grande. Máximo 10 MB.'], 400);
}

$rawFile = file_get_contents($file['tmp_name']);
if ($rawFile === false) {
    json_response(['message' => 'No se pudo leer el archivo subido.'], 500);
}
$fileData = base64_encode($rawFile);

$prompt = 'Eres un asistente de extracción de datos de facturas de compra. '
    . 'Analiza este documento y extrae SOLO la información en JSON con esta estructura exacta, '
    . 'sin texto adicional, sin markdown, sin explicaciones:\n'
    . '{\n'
    . '  "proveedor": "nombre del proveedor o empresa",\n'
    . '  "nit": "NIT o RUT del proveedor si aparece, sino vacío",\n'
    . '  "factura_numero": "número de factura si aparece, sino vacío",\n'
    . '  "fecha": "YYYY-MM-DD si aparece, sino vacío",\n'
    . '  "items": [\n'
    . '    { "descripcion": "descripción del producto", "cantidad": 1, "precio_unitario": 0.00 }\n'
    . '  ]\n'
    . '}\n'
    . 'Si un campo no aparece en el documento, usa string vacío o 0. '
    . 'Responde SOLO con el JSON válido.';

if ($mimeType === 'application/pdf') {
    $content = [
        [
            'type'   => 'document',
            'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $fileData],
        ],
        ['type' => 'text', 'text' => $prompt],
    ];
} else {
    $content = [
        [
            'type'   => 'image',
            'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $fileData],
        ],
        ['type' => 'text', 'text' => $prompt],
    ];
}

$requestBody = json_encode([
    'model'      => 'claude-haiku-5-5',
    'max_tokens' => 2048,
    'messages'   => [['role' => 'user', 'content' => $content]],
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestBody,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . CLAUDE_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    json_response(['message' => 'Error de conexión con la IA: ' . $curlError], 503);
}

if ($httpCode !== 200) {
    $errData = json_decode($response, true);
    $errMsg  = $errData['error']['message'] ?? 'HTTP ' . $httpCode;
    json_response(['message' => 'Error de la API de IA: ' . $errMsg], 503);
}

$apiResponse = json_decode($response, true);
$text        = trim($apiResponse['content'][0]['text'] ?? '');

$extracted = json_decode($text, true);
if (!is_array($extracted)) {
    if (preg_match('/\{[\s\S]*\}/m', $text, $matches)) {
        $extracted = json_decode($matches[0], true);
    }
}

if (!is_array($extracted) || empty($extracted['items'])) {
    json_response([
        'message' => 'No se pudo extraer información de la factura. ' .
                     'Intenta con una imagen más clara y bien iluminada.',
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
