<?php
require_once __DIR__ . '/../utils/init.php';
require_once __DIR__ . '/../utils/cors.php';

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$esProduccion = $_ENV['APP_ENV'] === 'production';
$domain = $esProduccion ? "backend-reliable.onrender.com" : "";

// 🔸 Borrar cookie sin dominio (funciona en localhost y algunos navegadores en prod)
setcookie("jwt", "", [
    "expires" => time() - 3600,
    "path" => "/",
    "httponly" => true,
    "samesite" => $esProduccion ? "None" : "Lax",
    "secure" => $esProduccion,
]);

// 🔸 Borrar cookie con dominio explícito (para Safari / Chrome estrictos en producción)
if ($esProduccion) {
    setcookie("jwt", "", [
        "expires" => time() - 3600,
        "path" => "/",
        "domain" => $domain,
        "httponly" => true,
        "samesite" => "None",
        "secure" => true,
    ]);
}

unset($_COOKIE['jwt']);

echo json_encode(["success" => true, "mensaje" => "Sesión cerrada correctamente"]);
exit;
