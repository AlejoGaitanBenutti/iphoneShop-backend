<?php
require_once __DIR__ . '/../utils/init.php';
require_once __DIR__ . '/../class/conexion.php';
require_once __DIR__ . '/../utils/formatNumber.php';
require_once __DIR__ . "/../utils/cors.php";



try {
    $database = new Database();
    $db = $database->getConnection();

    // Obtener el ingreso total acumulado
    $query = 'SELECT SUM(total) as total_ingresos FROM ventas';
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Formatear el total de ingresos
    $totalIngresos = formatNumber($result['total_ingresos']);

    echo json_encode(['total_ingresos' => $totalIngresos]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}



?>
