<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../class/productos.php';
require_once __DIR__ . '/../../utils/init.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../class/authMiddleware.php';
require_once __DIR__ . '/../../utils/uploadToCloudinary.php';

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
    try {
        $input = [];
        $accion = '';

        if (strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
            $rawData = file_get_contents("php://input");
            error_log("📦 Raw JSON recibido: " . $rawData);
            $input = json_decode($rawData, true);
            $accion = $input['accion'] ?? '';
        } else {
            $accion = $_POST['accion'] ?? '';
        }

        // GUARDAR
        if ($accion === 'guardar') {
            AuthMiddleware::verificarAdmin();

            $producto->marca = $_POST['marca'] ?? '';
            $producto->modelo = $_POST['modelo'] ?? '';
            $producto->descripcion = $_POST['descripcion'] ?? '';
            $producto->descripcion_dos = $_POST['descripcion_dos'] ?? '';
            $producto->precio = $_POST['precio'] ?? '';
            $producto->año = $_POST['año'] ?? '';
            $producto->kilometraje = $_POST['kilometraje'] ?? '';
            $producto->combustible = $_POST['combustible'] ?? '';
            $producto->tipo_de_cuerpo = $_POST['tipo_de_cuerpo'] ?? '';
            $producto->caja = $_POST['caja'] ?? '';
            $producto->transmicion = $_POST['transmicion'] ?? '';
            $producto->cv = $_POST['cv'] ?? '';
            $producto->color = $_POST['color'] ?? '';

            $nombreCampos = ['imagen_uno', 'imagen_dos', 'imagen_tres', 'imagen_cuatro'];
            for ($i = 0; $i < 4; $i++) {
                $nombreCampo = $nombreCampos[$i];
                if (isset($_FILES["imagen_$i"]) && $_FILES["imagen_$i"]["error"] === UPLOAD_ERR_OK) {
                    $url = subirACloudinary($_FILES["imagen_$i"]["tmp_name"], $_FILES["imagen_$i"]["name"]);
                    $producto->$nombreCampo = $url ?: '';
                } else {
                    $producto->$nombreCampo = '';
                }
            }

            if ($producto->insertar()) {
                echo json_encode(["success" => true, "message" => "Producto insertado correctamente."]);
            } else {
                echo json_encode(["success" => false, "message" => "Error al insertar el producto."]);
            }
            return;
        }

        // ELIMINAR
        if ($accion === 'eliminar' && isset($_POST['id'])) {
            if ($producto->eliminar($_POST['id'])) {
                echo json_encode(["success" => true, "message" => "Producto eliminado correctamente."]);
            } else {
                echo json_encode(["success" => false, "message" => "Error al eliminar el producto."]);
            }
            return;
        }

  // EDITAR
if ($accion === "editar" && (isset($_POST['id']) || isset($input['id']))) {
    try {
        if (strpos($_SERVER["CONTENT_TYPE"], "multipart/form-data") !== false) {
            $id = $_POST['id'];
            $productoActual = $producto->obtenerProductoPorId($id);
            $producto->cargarDesdePost(array_merge($productoActual, $_POST)); // Merge: prioriza $_POST

            $nombreCampos = ['imagen_uno', 'imagen_dos', 'imagen_tres', 'imagen_cuatro'];
            for ($i = 0; $i < 4; $i++) {
                $nombreCampo = $nombreCampos[$i];
                if (isset($_FILES["imagen_$i"]) && $_FILES["imagen_$i"]["error"] === UPLOAD_ERR_OK) {
                    $url = subirACloudinary($_FILES["imagen_$i"]["tmp_name"], $_FILES["imagen_$i"]["name"]);
                    $producto->$nombreCampo = $url ?: $productoActual[$nombreCampo];
                } else {
                    $producto->$nombreCampo = $productoActual[$nombreCampo]; // Mantener imagen vieja
                }
            }
        } else {
            // JSON puro
            $id = $input['id'];
            $productoActual = $producto->obtenerProductoPorId($id);
            $producto->cargarDesdePost(array_merge($productoActual, $input));
            // No se tocan las imágenes
            $producto->imagen_uno = $productoActual['imagen_uno'];
            $producto->imagen_dos = $productoActual['imagen_dos'];
            $producto->imagen_tres = $productoActual['imagen_tres'];
            $producto->imagen_cuatro = $productoActual['imagen_cuatro'];
        }

        if ($producto->editar($id)) {
            echo json_encode(["success" => true, "message" => "Producto editado correctamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al editar producto."]);
        }
    } catch (Throwable $e) {
        error_log("❌ Error interno en editar: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Error interno del servidor."]);
    }
    return;
}


        // ACCIÓN INVÁLIDA
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Acción inválida o faltan datos."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error interno: " . $e->getMessage()]);
    }
}
