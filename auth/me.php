<?php
require_once __DIR__ . "/../vendor/autoload.php";
require_once("../class/authMiddleware.php");


header("Access-Control-Allow-Credentials: true"); // 🔹 Permitir credenciales (cookies)
header("Access-Control-Allow-Methods: GET, OPTIONS, POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Cookie");
header("Content-Type: application/json");




// 🔹 Agregar ambos puertos a CORS
$allowed_origins = [
  "http://localhost:5173",
  "http://localhost:3000",
  "https://reliable-ecommerce.vercel.app",
  "https://reliablecarsapp.com",
    "https://www.reliablecarsapp.com"

];

if (isset($_SERVER["HTTP_ORIGIN"]) && in_array($_SERVER["HTTP_ORIGIN"], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER["HTTP_ORIGIN"]);
}

error_log("🔍 Origin recibido: " . ($_SERVER["HTTP_ORIGIN"] ?? 'No origin'));
error_log("🔍 Cookies recibidas en me.php: " . print_r($_COOKIE, true));


// 🔹 Manejar preflight OPTIONS antes de procesar la solicitud
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}



error_log("🔍 Cookies recibidas en `me.php`: " . print_r($_COOKIE, true));

// 🔹 Si no hay cookie, devolvemos un usuario `null` en lugar de `401`
if (!isset($_COOKIE['jwt'])) {
    echo json_encode(["usuario" => null, "error" => "JWT no presente en cookies"]);
    exit;
}

// 🔹 Extraer y verificar el token desde la cookie
// 🔹 Verificar el JWT desde la cookie
$userData = AuthMiddleware::verificarJWT($_COOKIE['jwt']);

if (!$userData) {
    http_response_code(401);
    echo json_encode(["usuario" => null, "error" => "Token inválido"]);
    exit;
}



echo json_encode([
    "correo" => $userData->correo,
    "rol" => $userData->rol,
    "nombre" => $userData->nombre
]);