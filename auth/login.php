<?php

error_log("🔍 Se ejecutó login.php");


require_once __DIR__ . '/../utils/init.php';
require_once __DIR__ . "/../utils/cors.php";

// 🔹 VERIFICAR QUE SEA MÉTODO POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    die(json_encode(["error" => "Método no permitido"]));
}

// 🔹 LEER EL CUERPO JSON
$json = file_get_contents("php://input");

if (!$json) {
    error_log("❌ No se recibió JSON");
    die(json_encode(["error" => "No se recibió JSON en la petición"]));
}

$data = json_decode($json, true);

if (!$data || empty($data["correo"]) || empty($data["password"])) {
    http_response_code(400);
    error_log("⚠️ Datos faltantes en la petición.");
    echo json_encode(["error" => "Faltan datos para realizar el login"]);
    exit;
}

require_once("../class/conexion.php");
require_once("../class/usuarios.php");

// 🔹 CONECTAR CON BASE DE DATOS
$database = new Database();
$db = $database->getConnection();
$usuarios = new Usuarios($db);

// 🔹 Validar credenciales
$resultado = $usuarios->login($data["correo"], $data["password"]);

if ($resultado && isset($resultado["token"])) {
    // 🔹 GUARDAR JWT EN UNA COOKIE
    setcookie("jwt", $resultado["token"], [
        "expires" => time() + 3600,
        "path" => "/",
        "domain" => "", // correcto para localhost
        "secure" => false, // si estás en http
        "httponly" => true,
        "samesite" => "Lax"
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
    echo json_encode(["error" => "Credenciales incorrectas"]);
}
