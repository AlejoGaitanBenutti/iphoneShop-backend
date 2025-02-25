<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Cookie");
header("Content-Type: application/json");

// 🔹 Manejo de solicitudes OPTIONS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once("../class/conexion.php");
require_once("../class/usuarios.php");

$database = new Database();
$db = $database->getConnection();
$usuarios = new Usuarios($db);

// 🔹 Leer JSON del request
$json = file_get_contents("php://input");
$data = json_decode($json, true);

// 🔹 Validar que el JSON sea válido
if (!$data) {
    http_response_code(400);
    echo json_encode(["mensaje" => "Error: No se recibieron datos"]);
    exit;
}

// 🔹 Validar credenciales
if (!empty($data["correo"]) && !empty($data["password"])) {
    $resultado = $usuarios->login($data["correo"], $data["password"]);

    if ($resultado && isset($resultado["token"])) {
        // 🔹 Guardar JWT en una cookie segura
        setcookie("jwt", $resultado["token"], [
            "expires" => time() + 3600,
            "path" => "/",
            "domain" => "localhost", // 🔹 IMPORTANTE: Mantener vacío en producción
            "secure" => false, // 🔹 Debe estar en `true` si usas HTTPS
            "httponly" => true, // 🔹 Evita acceso desde JavaScript
            "samesite" => "Lax" // 🔹 Permite cookies en la misma web
        ]);

        echo json_encode([
            "mensaje" => "Login exitoso",
            "usuario" => [
                "rol" => $resultado["rol"],
                "nombre" => $resultado["nombre"]
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["mensaje" => "Credenciales Incorrectas"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["mensaje" => "Faltan datos para realizar el login"]);
}
