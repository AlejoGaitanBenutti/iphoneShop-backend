<?php

require_once __DIR__ . '/../../class/productos.php';
require_once __DIR__ . '/../../utils/init.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../class/authMiddleware.php';



$producto = new Productos();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id'])) {
        try {
            echo json_encode($producto->obtenerProductoPorId($_GET['id']));
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    } else {
        $producto->listar("productos");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $accion = $data['accion'] ?? '';

    if ($accion === 'guardar') {
        foreach ($data as $key => $value) {
            if (property_exists($producto, $key)) {
                $producto->$key = $value;
            }
        }

        if ($producto->insertar()) {
            echo json_encode(["success" => true, "message" => "Producto insertado correctamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al insertar el producto."]);
        }
    }

    if ($accion === 'eliminar' && isset($data['id'])) {
        if ($producto->eliminar($data['id'])) {
            echo json_encode(["success" => true, "message" => "Producto eliminado correctamente"]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al eliminar el producto"]);
        }
    }
}
