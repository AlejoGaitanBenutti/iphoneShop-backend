<?php
require_once __DIR__ . '/../utils/init.php';
require_once __DIR__ . '/../utils/cors.php';

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$esProduccion = $_ENV['APP_ENV'] === 'production';
$domain = $esProduccion ? "backend-reliable.onrender.com" : "";

// 🔹 Eliminar cookie sin dominio (funciona en localhost)
setcookie("jwt", "", time() - 3600, "/", "", false, true);

// 🔹 Eliminar cookie con dominio (funciona en producción)
if ($esProduccion) {
    setcookie("jwt", "", time() - 3600, "/", $domain, true, true);
}

unset($_COOKIE['jwt']);

echo json_encode(["success" => true, "mensaje" => "Sesión cerrada correctamente"]);
exit;
