<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

class Database
{

    private $conexion;

    public function __construct()
    {
        try {
            $this->conexion = new PDO("mysql:host=localhost;port=3306;dbname=reliable", "root", "");
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conexion->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        } catch (PDOException $e) {
            throw new Exception("No se pudo establecer la conexión: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->conexion;
    }

    public function closeConnection()
    {

        $this->conexion = null;
    }
}
