<?php


require_once __DIR__ . '/../../class/conexion.php';
require_once __DIR__ . '/../../utils/init.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../class/authMiddleware.php';

authMiddleware::verificarAdmin();  // Solo un admin puede cambiar roles.

header('Content-type: application/json');

$data = json_decode(file_get_contents("php://input"),true);

if(!isset($data['id_usuario']) || !isset($data['rol'])){
    http_response_code(400);
    echo json_encode(["error" => "Faltan parámetros obligatorios"]);
    exit;
}

$id = $data['id_usuario'];
$rol = $data['rol'];

if(!in_array($rol,['user', 'admin'])){
    http_response_code(400);
    echo json_encode(["error" => "Rol no válido."]);
    exit;
}

try{
    $pdo = (new Database())->getConnection();
    $stmt = $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
    $stmt->execute([$rol, $id]);

    echo json_encode(["success" => true, "message" => "Rol Actualizado"]);
}catch(Exception $e){
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}