<?php
// api/test_db.php

require_once __DIR__ . '/../class/conexion.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        "status" => "conectado",
        "tablas" => $tables
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "mensaje" => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
