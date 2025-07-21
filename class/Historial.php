<?php

require_once __DIR__ . '../../utils/init.php';
include_once('conexion.php');
require_once('authMiddleware.php'); 
require_once __DIR__ . '../../utils/cors.php';


class Historial{

    private $conexion;
    private $table_name = "historial";





    public function __construct()
    {
        $db = new Database();
        $this->conexion = $db->getConnection();
    }

    public function listarHistorialConUsuarios(){
           $sql = "SELECT h.*, u.nombre 
                FROM {$this->table_name} h
                JOIN usuarios u ON h.usuario_id = u.id
                ORDER BY h.fecha DESC";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }







}


