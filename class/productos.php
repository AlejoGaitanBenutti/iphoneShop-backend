<?php
require_once __DIR__ . '../../utils/init.php';
include_once('conexion.php');
require_once('authMiddleware.php'); 
require_once __DIR__ . '../../utils/cors.php';

class Productos
{
    public $id;
    public $marca;
    public $modelo;
    public $descripcion;
    public $descripcion_dos;
    public $precio;
    public $año;
    public $kilometraje;
    public $combustible;
    public $tipo_de_cuerpo;
    public $caja;
    public $transmicion;
    public $cv;
    public $color;
    public $imagen_uno;
    public $imagen_dos;
    public $imagen_tres;
    public $imagen_cuatro;

    private $_exist = false;
    private $conexion;

    public function __construct()
    {
        $db = new Database();
        $this->conexion = $db->getConnection();
    }

    public function listar($tableName, $filtros = null)
    {
        $sql = "SELECT * FROM " . $tableName;

        if ($filtros != null) {
            $sql .= " WHERE " . implode(" AND ", array_map(function ($filtro) {
                return $filtro;
            }, $filtros));
        }

        try {
            $resource = $this->conexion->query($sql);
            if (!$resource) {
                throw new Exception("Error en la consulta a la base de datos");
            }

            $result = $resource->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }

    public function insertar()
    {
        AuthMiddleware::verificarAdmin();

        try {
            $sql = "INSERT INTO productos (marca, modelo, descripcion, descripcion_dos, precio, año, kilometraje, combustible, tipo_de_cuerpo, caja, transmicion, cv, color, imagen_uno, imagen_dos, imagen_tres, imagen_cuatro) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conexion->prepare($sql);

            $params = [
                $this->marca,
                $this->modelo,
                $this->descripcion,
                $this->descripcion_dos,
                $this->precio,
                $this->año,
                $this->kilometraje,
                $this->combustible,
                $this->tipo_de_cuerpo,
                $this->caja,
                $this->transmicion,
                $this->cv,
                $this->color,
                $this->imagen_uno,
                $this->imagen_dos,
                $this->imagen_tres,
                $this->imagen_cuatro
            ];

            if ($stmt->execute($params)) {
                return true;
            } else {
                throw new Exception("Error al ejecutar el insert");
            }
        } catch (Exception $e) {
            error_log("❌ Error en insertar(): " . $e->getMessage());
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }

    public function eliminar($id)
    {
        AuthMiddleware::verificarAdmin();

        $sql = "DELETE FROM productos WHERE id = :id";
        $stmt = $this->conexion->prepare($sql);

        return $stmt->execute([':id' => $id]);
    }

    public function obtenerProductoPorId($id)
    {
        $sql = "SELECT * FROM productos WHERE id= :id";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':id' => $id]);

        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
            throw new Exception("Producto no encontrado");
        }

        return $producto;
    }
}
