<?php
require_once __DIR__ . "/../class/conexion.php";
require_once __DIR__ . "/../utils/formatNumber.php";
require_once __DIR__ . "/../utils/cors.php";

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT DATE(fecha_venta) as fecha, SUM(total) as total 
              FROM ventas 
              WHERE MONTH(fecha_venta) = MONTH(CURRENT_DATE())
                AND YEAR(fecha_venta) = YEAR(CURRENT_DATE())
              GROUP BY fecha";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ventas as &$venta) {
        $venta['total'] = formatNumber($venta['total']); // Si querés evitar esto en el gráfico, podés omitirlo
    }

    echo json_encode($ventas);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
