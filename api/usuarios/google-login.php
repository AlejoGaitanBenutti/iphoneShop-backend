<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../utils/init.php';
require_once __DIR__ . '/../../class/conexion.php';
require_once __DIR__ . '/../../class/Usuarios.php';
require_once __DIR__ . '/../../utils/cors.php';


use Google\Client;

header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);
$token = $input['token'] ?? '';

error_log("💡 Token recibido: " . ($input['token'] ?? 'NULO'));


if (!$token) {
    http_response_code(400);
    echo json_encode(["error" => "Token no proporcionado."]);
    exit;
}

$client = new Client(['client_id' => '315974948397-idtppgrabhi09e6gvn643ecudmdsgctm.apps.googleusercontent.com']);
$payload = $client->verifyIdToken($token);

if ($payload) {
    $email = $payload['email'];
    $nombre = $payload['name'];

    $conexion = (new DataBase())->getConnection();
    $usuarioModel = new Usuarios($conexion);

    $respuesta = $usuarioModel->loginConGoogle($nombre, $email);
    $esProduccion = $_ENV['APP_ENV'] === 'production';

setcookie("jwt", $respuesta["token"], [
    "expires" => time() + 3600,
    "path" => "/",
    "domain" => $esProduccion ? "backend-reliable.onrender.com" : "",
    "secure" => $esProduccion,
    "httponly" => true,
    "samesite" => $esProduccion ? "None" : "Lax"
]);

echo json_encode([
    "mensaje" => "Login con Google exitoso",
    "usuario" => [
        "rol" => $respuesta["rol"],
        "nombre" => $respuesta["nombre"]
    ]
]);


} else {
    http_response_code(401);
    echo json_encode(["error" => "Token inválido"]);
}
