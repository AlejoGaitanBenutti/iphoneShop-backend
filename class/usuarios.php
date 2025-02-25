<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;



header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Cookie");
header('Content-Type: application/json');

include_once('conexion.php');
require_once __DIR__ . "/../vendor/autoload.php"; // Subimos un nivel para acceder a vendor/
require_once 'email.php'; // Asegúrate de que la ruta sea correcta





class Usuarios
{

    private $conexion;
    private $table_name = 'usuarios';
    private $clave_secreta = "clave_secreta_supr_segura";


    public $id;
    public $nombre;
    public $correo;
    public $password;
    public $rol;


    public function __construct($db)
    {

        $this->conexion = $db;
    }

    private function generarJWT($correo, $rol, $nombre)
    {
        $payload = [
            "iss" => "tu_sitio_web.com",
            "aud" => "tu_sitio_web.com",
            "iat" => time(),
            "exp" => time() + 3600, // Expira en 1 hora
            "data" => [
                "correo" => $correo,
                "rol" => $rol,
                "nombre" => $nombre
            ]
        ];
        return JWT::encode($payload, $this->clave_secreta, 'HS256');
    }




    public function login($correo, $password)
    {

        // CONSULTA SQL

        $query = "SELECT id, nombre, correo, password, rol FROM " . $this->table_name . " WHERE correo = :correo LIMIT 1";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindParam(":correo", $correo);

        $stmt->execute();


        // Verificacion para ver si existe el correo.

        if ($stmt->rowCount() > 0) {

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // verificamos si la contraseña usando password_verify.

            if (password_verify($password, $row['password'])) {
                // Generar el JWT

                $token = $this->generarJWT($row['correo'], $row['rol'], $row['nombre']);

                return [
                    "mensaje" => "Login exitoso",
                    "token" => $token,
                    "rol" => $row['rol'],
                    "nombre" => $row['nombre']

                ];
            }
        }


        // Si no encontro el usuario o las crecenciales son incorrectas
        return false;
    }






    public function registrar($nombre, $correo, $password, $username)
    {
        $rol = 'user'; // Asumimos que el rol es 'user' por defecto

        // Verificar si el correo ya está registrado
        $query = "SELECT id FROM " . $this->table_name . " WHERE correo = :correo LIMIT 1";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindParam(":correo", $correo);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            error_log("Correo ya registrado: " . $correo); // Depuración
            return "correo_en_uso"; // Correo ya registrado
        }



        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Generar un token de verificación
        $token_verificacion = bin2hex(random_bytes(32));

        // INSERTAR NUEVO USUARIO.
        $query = "INSERT INTO usuarios (nombre, correo, password, rol, username, verificado, token_verificacion) 
                VALUES (:nombre, :correo, :password, :rol, :username, 0, :token_verificacion)";

        $stmt = $this->conexion->prepare($query);
        // Enlazamos los parámetros a la consulta
        $stmt->bindParam(":nombre", $nombre);
        $stmt->bindParam(":correo", $correo);
        $stmt->bindParam(":password", $hashedPassword);
        $stmt->bindParam(":rol", $rol);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":token_verificacion", $token_verificacion);


        // Ejecutamos la consulta
        try {
            $stmt->execute();
            $lastInsertId = $this->conexion->lastInsertId();

            $email = new Email();
            $email->enviarCorreoVerificacion($correo, $token_verificacion);
            //Retrono de datos del usuario registrado
            return [
                'id' => $lastInsertId,
                'nombre' => $nombre,
                "correo" => $correo,
                'rol' => $rol,
                "username" => $username
            ];
        } catch (PDOException $e) {
            // Captura cualquier error SQL y lo registra
            error_log("Error SQL: " . $e->getMessage());
            return false; // Error en la ejecución
        }
    }
}
