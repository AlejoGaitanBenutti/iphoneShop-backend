<?php
require_once __DIR__ . '/../utils/init.php';
require_once __DIR__ . '/../utils/cors.php';

header('Content-Type: application/json; charset=utf-8');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

$entorno = getenv('APP_ENV') ?: 'local';
$esProduccion = ($entorno === 'production');

// ⚠️ IMPORTANTE: usá los mismos parámetros que al setear la cookie en login.php
$opts = [
  'expires'  => time() - 3600,             // expirada
  'path'     => '/',
  'secure'   => $esProduccion,             // true solo en prod
  'httponly' => true,
  'samesite' => $esProduccion ? 'None' : 'Lax',
];

// En producción, si seteaste domain al crearla, también hay que pasarlo acá
if ($esProduccion) {
  // ajustá a tu dominio real si usaste 'domain' en login.php
  $opts['domain'] = 'backend-reliable.onrender.com';
}

setcookie('jwt', '', $opts);

// Limpieza local (no borra la cookie del navegador, pero evita usarla server-side)
unset($_COOKIE['jwt']);

echo json_encode(['ok' => true, 'mensaje' => 'Sesión cerrada correctamente']);
