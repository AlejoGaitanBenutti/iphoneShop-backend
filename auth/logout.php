<?php
require_once __DIR__ . '/../utils/init.php';
require_once __DIR__ . '/../utils/cors.php';

// ⚠️ Cuidado con el dominio, usamos la misma lógica que en login.php
$esProduccion = $_ENV['APP_ENV'] === 'production';
$domain = $esProduccion ? "backend-reliable.onrender.com" : "";

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

if (isset($_COOKIE['jwt'])) {
    // ✅ Expira la cookie correctamente para cualquier entorno
    setcookie("jwt", "", time() - 3600, "/", $domain, $esProduccion, true);
    unset($_COOKIE['jwt']);
}

echo json_encode(["success" => true, "mensaje" => "Sesión cerrada correctamente"]);
exit;
