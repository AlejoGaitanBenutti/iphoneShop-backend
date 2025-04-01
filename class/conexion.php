<?php
// class/conexion.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

class Database
{
    private $conexion;

    public function __construct()
    {
        $host = getenv("DB_HOST") ?: 'localhost';
        $port = getenv("DB_PORT") ?: '3306';
        $dbname = getenv("DB_NAME") ?: 'reliable';
        $user = getenv("DB_USER") ?: 'root';
        $pass = getenv("DB_PASS") ?: '';

        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            $this->conexion = new PDO($dsn, $user, $pass);
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
