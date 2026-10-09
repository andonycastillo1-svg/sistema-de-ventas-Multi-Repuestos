<?php

declare(strict_types=1);

// ============================================================
// SEGURIDAD — se ejecuta en cada petición a la API
// ============================================================

// 1. Solo aceptar solicitudes JSON/XHR — rechazar navegadores y bots
//    que accedan directamente a la API
$requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
$contentType   = $_SERVER['CONTENT_TYPE']          ?? '';
$accept        = $_SERVER['HTTP_ACCEPT']           ?? '';
$userAgent     = $_SERVER['HTTP_USER_AGENT']        ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD']         ?? 'GET';

// Bloquear user-agents de bots/crawlers conocidos
$botPatterns = [
    'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
    'yandexbot', 'sogou', 'exabot', 'facebot', 'ia_archiver',
    'mj12bot', 'ahrefsbot', 'semrushbot', 'dotbot', 'rogerbot',
    'screaming frog', 'python-requests', 'go-http-client', 'curl/',
    'wget/', 'libwww', 'scrapy', 'httpclient', 'java/', 'perl/',
    'ruby', 'php/', 'masscan', 'zgrab', 'nmap', 'sqlmap', 'nikto',
    'dirbuster', 'wfuzz', 'hydra', 'burpsuite', 'nessus',
];
$uaLower = strtolower($userAgent);
foreach ($botPatterns as $bot) {
    if (strpos($uaLower, $bot) !== false) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message' => 'Acceso denegado.']);
        exit;
    }
}

// Bloquear peticiones sin User-Agent (típico de scripts automatizados)
if (trim($userAgent) === '') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => 'Acceso denegado.']);
    exit;
}

// 2. Rate limiting simple por IP — máx. 120 peticiones por minuto
//    Usa archivos temporales del sistema (sin base de datos)
(function () {
    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $safeIp  = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $ip);
    $dir     = sys_get_temp_dir() . '/api_rl/';
    $file    = $dir . $safeIp . '.json';
    $limit   = 120;   // peticiones por ventana
    $window  = 60;    // segundos
    $now     = time();

    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $data = ['count' => 0, 'reset' => $now + $window];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $data = json_decode($raw, true) ?: $data;
        }
    }

    if ($now > (int) $data['reset']) {
        $data = ['count' => 0, 'reset' => $now + $window];
    }

    $data['count']++;
    @file_put_contents($file, json_encode($data), LOCK_EX);

    if ($data['count'] > $limit) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        header('Retry-After: ' . max(0, (int) $data['reset'] - $now));
        echo json_encode(['message' => 'Demasiadas solicitudes. Intenta en un momento.']);
        exit;
    }
})();

// 3. Cabeceras de seguridad en todas las respuestas de la API
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, private');

// 4. CORS — solo permite solicitudes desde el mismo dominio
$allowedOrigins = [
    'http://litgt.com',
    'https://litgt.com',
    'http://www.litgt.com',
    'https://www.litgt.com',
    'http://localhost',
    'http://127.0.0.1',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token');
    header('Access-Control-Max-Age: 3600');
}
if ($requestMethod === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ============================================================

const DB_HOST = 'localhost';
const DB_NAME = 'pmsguate_multi_repuestos';
const DB_USER = 'pmsguate_appventas';
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

function authorization_header(): string
{
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return (string) $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'authorization') {
                return (string) $value;
            }
        }
    }

    return '';
}

function current_user(): array
{
    // 1. Authorization: Bearer <token>
    $header = authorization_header();
    if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
        return jwt_decode(trim($matches[1]));
    }

    // 2. X-Token header (fallback cuando Apache bloquea Authorization)
    $xToken = $_SERVER['HTTP_X_TOKEN'] ?? '';
    if (is_string($xToken) && $xToken !== '') {
        return jwt_decode($xToken);
    }

    // 3. Query param ?token= (fallback para cPanel/mod_rewrite)
    $queryToken = $_GET['token'] ?? $_GET['access_token'] ?? '';
    if (is_string($queryToken) && $queryToken !== '') {
        return jwt_decode($queryToken);
    }

    json_response(['message' => 'Token requerido.'], 401);
}


function normalized_role(array $user): string
{
    $role = strtoupper((string) ($user['rol'] ?? ''));
    if ($role === 'ADMIN') {
        return 'ADMINISTRADOR';
    }
    return $role;
}

function require_role(array $allowedRoles): array
{
    $user = current_user();
    $role = normalized_role($user);
    $allowed = array_map('strtoupper', $allowedRoles);

    if (!in_array($role, $allowed, true)) {
        json_response(['message' => 'No tienes permisos para este módulo. Rol requerido: ' . implode(', ', $allowed)], 403);
    }

    return $user;
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
