
<?php
require_once __DIR__ . '/../utils/init.php';
require_once __DIR__ . "/../class/conexion.php";
require_once __DIR__ . "/../utils/formatNumber.php";
require_once __DIR__ . "/../utils/cors.php";





try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT COUNT(*) as nuevos_clientes FROM clientes WHERE MONTH(fecha_registro) = MONTH(CURDATE())";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $data['nuevos_clientes'] = formatNumber($data['nuevos_clientes']);

    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
