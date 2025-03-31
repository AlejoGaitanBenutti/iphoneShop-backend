<?php



require_once __DIR__ . '/../../utils/init.php';
require_once __DIR__ . "/../../class/conexion.php";
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../class/authMiddleware.php';


authMiddleware::verificarAdmin();  // Solo un admin puede cambiar roles.


header('Content-type: application/json');



try{
    $pdo = (new Database())->getConnection();

    //consulta para obtener los datos necesarios para la tabla

    $stmt= $pdo->query("SELECT id, nombre, apellido, correo, rol FROM usuarios");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($usuarios);
}catch(Exception $e){
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

