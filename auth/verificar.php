<?php
require_once './../class/conexion.php'; // Incluir la clase de conexión
require_once '../class/usuarios.php'; // Si tienes una clase Usuarios, también inclúyela

// Crear una instancia de la base de datos
try {
    $db = new Database();
    $conexion = $db->getConnection();
} catch (Exception $e) {
    die(json_encode(["error" => "Error de conexión: " . $e->getMessage()]));
}

// Verificar si hay un token en la URL
if (!isset($_GET['token'])) {
    die(json_encode(["error" => "Token no proporcionado"]));
}

$token = $_GET['token'];

// Buscar el usuario con ese token
$sql = "SELECT id, correo FROM usuarios WHERE token_verificacion = :token";
$stmt = $conexion->prepare($sql);
$stmt->bindParam(':token', $token, PDO::PARAM_STR);
$stmt->execute();
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario) {
    // Si el usuario existe, actualizar su estado a verificado
    $idUsuario = $usuario['id'];

    $updateSql = "UPDATE usuarios SET verificado = 1, token_verificacion = NULL WHERE id = :id";
    $updateStmt = $conexion->prepare($updateSql);
    $updateStmt->bindParam(':id', $idUsuario, PDO::PARAM_INT);
    $updateStmt->execute();

    echo json_encode(["success" => "✅ Tu cuenta ha sido verificada con éxito."]);
} else {
    echo json_encode(["error" => "❌ Token inválido o la cuenta ya ha sido verificada."]);
}

// Cerrar conexión
$db->closeConnection();
