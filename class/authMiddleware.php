<?php



require_once __DIR__ . "/../vendor/autoload.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class authMiddleware
{
    private static $clave_secreta = "clave_secreta_supr_segura";

    public static function verificarJWT()
    {

        // Revisamos si se envio la cabecera authorization que viene desde el JS.

        $headers = getallheaders(); // obtiene las cabeceras HTTP enviadas en la solicitud.
        if (!isset($headers["Authorization"])) { // Ver si la cabecera tiene Authorization
            http_response_code(401);
            echo json_encode(["error" => "No autorizado: falta token"]);
            exit;
        }



        //Extraer el token (formato esperado: "Bearer <token>")

        $authHeader = $headers['Authorization'];
        $token = str_replace("Bearer ", "", $authHeader);



        try {
            // Decodificar el JWT
            $decoded = JWT::decode($token, new Key(self::$clave_secreta, 'HS256')); // ::decode decodifica el token con la clave secreta
            return $decoded->data; // Retornamos los datos del usuario (correo y rol)
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(["error" => "Token invalido"]);
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
