<?php

require_once __DIR__ . "/../vendor/autoload.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class authMiddleware
{
    private static $clave_secreta = "clave_secreta_supr_segura";

    public static function verificarJWT($token = null)
    {
        // 🔹 Intentar obtener el token desde la cookie si no se pasa como argumento
        if (!$token) {
            if (!isset($_COOKIE['jwt'])) {
                http_response_code(401);
                echo json_encode(["error" => "No autorizado: falta token"]);
                exit;
            }
            $token = $_COOKIE['jwt'];
        }

        try {
            // 🔹 Decodificar el JWT
            $decoded = JWT::decode($token, new Key(self::$clave_secreta, 'HS256'));
            return $decoded->data; // 🔹 Retornar los datos del usuario (correo, rol)
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(["error" => "Token inválido"]);
            exit;
        }
    }

    public static function verificarAdmin()
    {
        $userData = self::verificarJWT();
        if ($userData->rol !== "admin") {
            http_response_code(403);
            echo json_encode(["error" => "Acceso denegado: Solo los administradores pueden realizar esta acción"]);
            exit;
        }
    }
}
