<?php

require_once __DIR__ . '/../../class/usuarios.php';
require_once __DIR__ . '/../../utils/init.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../class/authMiddleware.php';

authMiddleware::verificarAdmin();

header('Content-type: application/json');

try {
    $db = new Database();
    $usuario = new Usuarios($db->getConnection());
    $usuarios = $usuario->listarUsuarios();
    echo json_encode($usuarios);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
