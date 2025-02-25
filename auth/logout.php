<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// 🔹 Manejo de solicitudes OPTIONS para evitar problemas de CORS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// 🔹 Asegurar que la cookie JWT se elimina correctamente
if (isset($_COOKIE['jwt'])) {
    setcookie("jwt", "", time() - 3600, "/", "localhost", false, true);
    setcookie("jwt", "", time() - 3600, "/", "", false, true); // 🔹 Para compatibilidad con otros navegadores
    unset($_COOKIE['jwt']);
}

// 🔹 Responder confirmando que la sesión fue cerrada
echo json_encode(["success" => true, "mensaje" => "Sesión cerrada correctamente"]);
exit;
