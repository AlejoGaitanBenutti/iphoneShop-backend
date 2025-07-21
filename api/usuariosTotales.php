<?php
// Usuarios hoy

require_once __DIR__ . '/../utils/init.php';
require_once __DIR__ . "/../class/conexion.php";
require_once __DIR__ . "/../class/usuarios.php";
require_once __DIR__ . "/../utils/formatNumber.php";
require_once __DIR__ . "/../utils/cors.php";




try {
    $database = new Database();
    $db = $database->getConnection();
    $usuarios = new Usuarios($db);

    $totalUsuarios = $usuarios->obtenerTotalUsuarios();

    $totalUsuarios = formatNumber($totalUsuarios);

    echo json_encode(["total_usuarios" => $totalUsuarios]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
