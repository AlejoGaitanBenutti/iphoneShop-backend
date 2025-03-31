<?php
// CANTIDAD DE VENTAS

require_once __DIR__ . '/../utils/init.php';
require_once __DIR__ . "/../class/conexion.php";
require_once __DIR__ . "/../utils/formatNumber.php";
require_once __DIR__ . "/../utils/cors.php";






try {
    $database = new Database();
    $db = $database->getConnection();

    // Sumar todas las ventas
    $query = "SELECT COUNT(*) as totalVentas FROM ventas";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalVentas = $result['totalVentas'];

    // Formatear el total con la función existente y agregar el símbolo de USD
    $formattedTotal = "" . formatNumber($totalVentas);

    echo json_encode(["totalVentas" => $formattedTotal]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
