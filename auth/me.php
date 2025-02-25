<?php
require_once __DIR__ . "/../vendor/autoload.php";
require_once("../class/authMiddleware.php");

header("Access-Control-Allow-Origin: http://localhost:5173"); // 🔹 Solo permitir el frontend
header("Access-Control-Allow-Credentials: true"); // 🔹 Permitir credenciales (cookies)
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Cookie");
header("Content-Type: application/json");

// 🔹 Manejar preflight OPTIONS antes de procesar la solicitud
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🔹 Si no hay cookie, devolvemos un usuario `null` en lugar de `401`
if (!isset($_COOKIE['jwt'])) {
    echo json_encode(["usuario" => null]);
    exit;
}

// 🔹 Extraer y verificar el token desde la cookie
$userData = AuthMiddleware::verificarJWT($_COOKIE['jwt']);

echo json_encode([
    "correo" => $userData->correo,
    "rol" => $userData->rol,
    "nombre" => $userData->nombre
]);
