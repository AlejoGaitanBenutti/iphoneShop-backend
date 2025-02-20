<?php
require_once __DIR__ . "/../vendor/autoload.php"; // Incluir la librería JWT
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

include_once('../class/conexion.php');
include_once('../class/usuarios.php');

$database = new Database();
$db = $database->getConnection();
$usuarios = new Usuarios($db);

// 🔹 Permitir solicitudes OPTIONS (para CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🔹 Capturar los datos enviados
$json = file_get_contents("php://input");
$data = json_decode($json, true);

if (!$data) {
    error_log("Error: No se recibieron datos"); // Registrar en el log de errores
    echo json_encode(["mensaje" => "Error: No se recibieron datos", "jsonRecibido" => $json]);
    exit;
}

// 🔹 Verificar si los datos necesarios están presentes
if (!empty($data['correo']) && !empty($data['password'])) {
    $resultado = $usuarios->login($data['correo'], $data['password']);

    if ($resultado && isset($resultado["token"])) {
        echo json_encode([
            "mensaje" => "Login exitoso",
            "usuario" => [
                "token" => $resultado["token"],
                "rol" => $resultado["rol"],
                "nombre" => $resultado["nombre"]
            ]
        ]);
    } else {
        echo json_encode(["mensaje" => "Credenciales Incorrectas"]);
    }
} else {
    echo json_encode(["mensaje" => "Faltan datos para realizar el login", "jsonRecibido" => $json]);
}

$database->closeConnection();
