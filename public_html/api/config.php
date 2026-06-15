<?php

declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_NAME = 'cpanel_usuario_multi_repuestos';
const DB_USER = 'cpanel_usuario_appventas';
const DB_PASS = 'CAMBIA_ESTA_PASSWORD';
const JWT_SECRET = 'CAMBIA_ESTE_SECRETO_LARGO_Y_ALEATORIO';

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function db(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    $padding = strlen($data) % 4;
    if ($padding > 0) {
        $data .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($data, '-_', '+/')) ?: '';
}

function jwt_encode(array $payload): string
{
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $segments = [
        base64url_encode(json_encode($header)),
        base64url_encode(json_encode($payload)),
    ];
    $signature = hash_hmac('sha256', implode('.', $segments), JWT_SECRET, true);
    $segments[] = base64url_encode($signature);

    return implode('.', $segments);
}

function jwt_decode(string $token): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        json_response(['message' => 'Token inválido.'], 401);
    }

    [$header, $payload, $signature] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true));

    if (!hash_equals($expected, $signature)) {
        json_response(['message' => 'Firma de token inválida.'], 401);
    }

    $data = json_decode(base64url_decode($payload), true);
    if (!is_array($data) || !isset($data['exp']) || time() >= (int) $data['exp']) {
        json_response(['message' => 'Token expirado.'], 401);
    }

    return $data;
}

function current_user(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
        json_response(['message' => 'Token requerido.'], 401);
    }

    return jwt_decode($matches[1]);
}

function request_json(): array
{
    $body = file_get_contents('php://input') ?: '{}';
    $data = json_decode($body, true);

    if (!is_array($data)) {
        json_response(['message' => 'JSON inválido.'], 400);
    }

    return $data;
}
