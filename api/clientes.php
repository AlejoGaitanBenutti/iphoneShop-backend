<?php
require_once __DIR__ . "/../utils/cors.php";
require_once __DIR__ . '/../utils/init.php';
require_once __DIR__ . "/../class/conexion.php";
require_once __DIR__ . "/../utils/formatNumber.php";





try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT id_cliente, nombre, apellido, correo, telefono, direccion, ciudad, codigo_postal, pais, fecha_registro FROM clientes";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);


    echo json_encode($clientes);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
