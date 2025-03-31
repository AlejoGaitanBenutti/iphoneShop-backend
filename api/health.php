<?php
// api/health.php

header('Content-Type: application/json');

// Incluir clase de conexión
require_once __DIR__ . '/../class/conexion.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    echo json_encode([
        "app" => "Reliable ERP API",
        "status" => "online",
        "message" => "Conexión con base de datos exitosa 🎉",
        "database" => getenv("DB_NAME"),
        "timestamp" => date("Y-m-d H:i:s")
    ], JSON_PRETTY_PRINT);

    $db->closeConnection();
} catch (Exception $e) {
    echo json_encode([
        "app" => "Reliable ERP API",
        "status" => "offline",
        "message" => "Error al conectar con la base de datos",
        "error" => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
