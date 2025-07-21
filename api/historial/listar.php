<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . '/../../utils/init.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../class/Historial.php';

header('Content-Type: application/json');

try {
    $historial = new Historial();
    $registros = $historial->listarHistorialConUsuarios();
    echo json_encode($registros);
} catch (Exception $e) {
    error_log("❌ Error al listar historial: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Error al obtener historial: " . $e->getMessage()]);
}