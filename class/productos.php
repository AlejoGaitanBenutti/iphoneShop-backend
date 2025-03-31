<?php
require_once __DIR__ . '../../utils/init.php';
include_once('conexion.php');
require_once('authMiddleware.php'); // Incluir middleware de autenticación
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

            if (empty($result)) {
                echo json_encode(["mensaje" => "No hay productos disponibles"]);
            } else {
                echo json_encode($result);
            }
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    public function insertar()
    {

        AuthMiddleware::verificarAdmin(); // Solo admin puede insertar

        try {
        $sql = "INSERT INTO productos (marca, modelo, descripcion, descripcion_dos, precio, año, kilometraje, combustible, tipo_de_cuerpo, caja, transmicion, cv, color, imagen_uno, imagen_dos, imagen_tres, imagen_cuatro) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conexion->prepare($sql);  // prepara la consulta SQL usando PDO.

        $params = [   // Definicion de los parametros. -> Se toman de las propiedades de la clase Productos.
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
        }catch(Exception $e){
            error_log("❌ Error en insertar(): " . $e->getMessage());
        echo json_encode(["error" => $e->getMessage()]);
        exit;
        }
    }

    public function eliminar($id)
    {

        AuthMiddleware::verificarAdmin(); // Solo admin puede insertar

        $sql = "DELETE FROM productos WHERE id = :id";  // Consulta sql

        // preparar la consulta para evitar inyecciones sql

        $stmt = $this->conexion->prepare($sql);

        // Ejecutar la consulta vinculando el parametro a la id

        return $stmt->execute([':id' => $id]);
    }




    public function obtenerProductoPorId($id)
    {

        $sql = "SELECT * FROM productos WHERE id= :id";    //Consulta para obtener producto por su id

        $stmt = $this->conexion->prepare($sql);  // Prepara la consulta para evitar inyeccion


        // Ejecuta la consulta vinculando el parametro al id
        $stmt->execute([':id' => $id]);

        // obtener ese resultado como un array asoc, como usa php.

        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        //Si no se encuentra el producto devolvemos un error

        if (!$producto) {
            throw new Exception("Producto no encontrado");
        }

        return $producto;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {     // CAPTURA DE LOS DATOS DEL FORMULARIO.
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['accion']) && $data['accion'] === 'guardar') {  // Si recibe un formulario con la clave accion = "guardar" se crea un nuevo objeto de la clase productos.
        $miproducto = new Productos();   // Crea un nuevo objeto con la clase productos 
        $miproducto->marca = $data['marca']; // Le asigna todas estos valores a las props
        $miproducto->modelo = $data['modelo'];
        $miproducto->descripcion = $data['descripcion'];
        $miproducto->descripcion_dos = $data['descripcion_dos'];
        $miproducto->precio = $data['precio'];
        $miproducto->año = $data['año'];
        $miproducto->kilometraje = $data['kilometraje'];
        $miproducto->combustible = $data['combustible'];
        $miproducto->tipo_de_cuerpo = $data['tipo_de_cuerpo'];
        $miproducto->caja = $data['caja'];
        $miproducto->transmicion = $data['transmicion'];
        $miproducto->cv = $data['cv'];
        $miproducto->color = $data['color'];
        $miproducto->imagen_uno = $data['imagen_uno'];
        $miproducto->imagen_dos = $data['imagen_dos'];
        $miproducto->imagen_tres = $data['imagen_tres'];
        $miproducto->imagen_cuatro = $data['imagen_cuatro'];

        if ($miproducto->insertar()) {   // LLAMADA AL METODO INSERTAR del objeto miProducto.
            echo json_encode(["success" => true, "message" => "Producto insertado correctamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al insertar el producto."]);
        }
        exit;
    }

    // Verifica si la accion es "eliminar"

    if ($data['accion'] === 'eliminar' && isset($data['id'])) {

        $miproducto = new Productos();

        // Llama al metodo eliminar y pasa el id del producto

        if ($miproducto->eliminar($data['id'])) {
            echo json_encode(["success" => true, "message" => "Producto eliminado correctamente"]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al eliminar el producto"]);
        }
        exit;
    }
}





if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id'])) {
        $id = $_GET['id'];  // Obtenemos el id desde los parametros de la id.

        try {
            $producto = new Productos();
            $productoDetails = $producto->obtenerProductoPorId($id);  // obtenemos producto por la id

            echo json_encode($productoDetails); // Devolvemos los detalles del producto
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        $tableName = 'productos';
        $filtros = null;

        $productos = new Productos();
        $productos->listar($tableName, $filtros);
    }
}
