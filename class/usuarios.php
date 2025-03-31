<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$allowed_origins = ["http://localhost:3000", "http://localhost:5173"];

if (isset($_SERVER["HTTP_ORIGIN"]) && in_array($_SERVER["HTTP_ORIGIN"], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER["HTTP_ORIGIN"]);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, Cookie");
    header("Content-Type: application/json");
}

// Manejo de preflight request (solicitudes OPTIONS)
if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    exit;
}

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

    public function obtenerTotalUsuarios()
    {
        try {
            $query = "SELECT COUNT(*) as total_usuarios FROM " . $this->table_name;
            $stmt = $this->conexion->prepare($query);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Depuración: Mostrar qué devuelve la consulta
            error_log("Total de usuarios en la BD: " . json_encode($data));

            return isset($data['total_usuarios']) ? (int) $data['total_usuarios'] : 0;
        } catch (Exception $e) {
            error_log("Error en obtenerTotalUsuarios(): " . $e->getMessage());
            return 0;
        }
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
        $query = "SELECT id, nombre, correo, password, rol FROM " . $this->table_name . " WHERE correo = :correo LIMIT 1";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindParam(":correo", $correo);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($password, $row['password'])) {
                $token = $this->generarJWT($row['correo'], $row['rol'], $row['nombre']);

                return [
                    "mensaje" => "Login exitoso",
                    "token" => $token,
                    "rol" => $row['rol'],
                    "nombre" => $row['nombre']
                ];
            }
        }
        return false;
    }

    public function registrar($nombre, $correo, $password, $username)
    {
        $rol = 'user';

        // Verificar si el correo ya está registrado
        $query = "SELECT id FROM " . $this->table_name . " WHERE correo = :correo LIMIT 1";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindParam(":correo", $correo);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            error_log("Correo ya registrado: " . $correo);
            return "correo_en_uso";
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $token_verificacion = bin2hex(random_bytes(32));

        $query = "INSERT INTO usuarios (nombre, correo, password, rol, username, verificado, token_verificacion) 
                VALUES (:nombre, :correo, :password, :rol, :username, 0, :token_verificacion)";

        $stmt = $this->conexion->prepare($query);
        $stmt->bindParam(":nombre", $nombre);
        $stmt->bindParam(":correo", $correo);
        $stmt->bindParam(":password", $hashedPassword);
        $stmt->bindParam(":rol", $rol);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":token_verificacion", $token_verificacion);

        try {
            $stmt->execute();
            $lastInsertId = $this->conexion->lastInsertId();

            $email = new Email();
            $email->enviarCorreoVerificacion($correo, $token_verificacion);

            return [
                'id' => $lastInsertId,
                'nombre' => $nombre,
                "correo" => $correo,
                'rol' => $rol,
                "username" => $username
            ];
        } catch (PDOException $e) {
            error_log("Error SQL: " . $e->getMessage());
            return false;
        }
    }
}
