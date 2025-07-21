<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../class/Usuarios.php';
require_once __DIR__ . '/../../utils/init.php';
require_once __DIR__ . '/../../class/authMiddleware.php';

authMiddleware::verificarAdmin();

header('Content-type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id_usuario']) || !isset($data['rol'])) {
    http_response_code(400);
    echo json_encode(["error" => "Faltan parámetros obligatorios"]);
    exit;
}

try {
    $db = new Database();
    $usuario = new Usuarios($db->getConnection());
    $ok = $usuario->actualizarRol($data['id_usuario'], $data['rol']);

    if ($ok) {
        echo json_encode(["success" => true, "message" => "Rol actualizado"]);
    } else {
        throw new Exception("No se pudo actualizar el rol");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
