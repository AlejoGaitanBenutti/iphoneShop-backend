<?php
require_once __DIR__ . "/../vendor/autoload.php";
require_once("../class/authMiddleware.php");

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, OPTIONS");
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// ✅ Responder a las solicitudes OPTIONS sin hacer más validaciones
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ✅ Ahora sí procesamos la petición con el token
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["error" => "No autorizado: falta token"]);
    exit;
}

$userData = AuthMiddleware::verificarJWT();

// ✅ Asegurar que se devuelve un nombre (puedes modificar la base de datos para incluirlo en el JWT)
$nombre = isset($userData->nombre) ? $userData->nombre : "Usuario Desconocido";

echo json_encode([
    "correo" => $userData->correo,
    "rol" => $userData->rol,
    "nombre" => $nombre
]);
