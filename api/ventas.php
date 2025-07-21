<?php
require_once __DIR__ . '/../utils/init.php';
require_once __DIR__ . "/../class/conexion.php";
require_once __DIR__ . "/../utils/formatNumber.php";
require_once __DIR__ . "/../utils/cors.php";




try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT DATE(fecha_venta) as fecha, SUM(total) as total FROM ventas GROUP BY fecha";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ventas as &$venta) {
        $venta['total'] = formatNumber($venta['total']);
    }


    $headers_list = headers_list();
error_log("🧾 HEADERS ENVIADOS POR VENTAS.PHP:");
foreach ($headers_list as $header) {
    error_log($header);
}

    echo json_encode($ventas);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
