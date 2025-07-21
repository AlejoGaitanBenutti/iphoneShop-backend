<?php

require_once __DIR__ . '/../utils/cors.php';
include_once('../class/conexion.php');
include_once('../class/usuarios.php');


header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;

}


// Conexión a la base de datos
$database = new DataBase();
$db = $database->getConnection();
$usuarios = new Usuarios($db);


// Obtener los datos JSON enviados desde el cliente
$json = file_get_contents("php://input");

// Verificar que el cuerpo de la solicitud no esté vacío
if (empty($json)) {
    error_log("ERROR: El cuerpo de la solicitud está vacío.");
    echo json_encode(["mensaje" => "El cuerpo de la solicitud está vacío."]);
    exit;  // Terminar el script si no hay datos para procesar
}

// Intentar decodificar el JSON
$data = json_decode($json);

// Verificar si los datos fueron decodificados correctamente
if ($data === null) {
    error_log("ERROR: Los datos no pudieron ser decodificados como JSON.");
    echo json_encode(["mensaje" => "La solicitud no contiene un JSON válido."]);
    exit;  // Terminar el script si el JSON no es válido
}

// Verifica que los datos esperados estén presentes
// error_log("Datos recibidos: Nombre: " . (isset($data->nombre) ? $data->nombre : 'No disponible') .
//     ", Correo: " . (isset($data->correo) ? $data->correo : 'No disponible') .
//     ", Username: " . (isset($data->username) ? $data->username : 'No disponible') .
//     ", Contraseña: " . (isset($data->password) ? $data->password : 'No disponible'));

// Verificar que todos los campos necesarios estén presentes y no vacíos
if (empty($data->nombre) || empty($data->correo) || empty($data->username) || empty($data->password)) {
    echo json_encode(["mensaje" => "Faltan datos para realizar el Registro"]);
    exit;  // Terminar el script si falta algún dato
}

// Registrar el usuario
$resultado = $usuarios->registrar($data->nombre, $data->correo, $data->password, $data->username);

// Si el mail ya fue registrado
if ($resultado === "correo_en_uso") {
    echo json_encode(["mensaje" => "El correo ya está en uso."]); // mejorar los errores, usar HTTP STATUS CODES.
    return;
}

// Si el registro es exitoso
if ($resultado) {
    echo json_encode(["mensaje" => "Registro Exitoso", "usuario" => $data]);
    return;
}


error_log("Error en el registro: resultado no válido o fallo de inserción");
// Si hubo un error en el registro
echo json_encode(["mensaje" => "Error en el registro"]);

// Cerrar la conexión a la base de datos
$database->closeConnection();
