<?php
// class/conexion.php


header('Content-Type: application/json');

class Database
{
    private $conexion;

    public function __construct()
    {
        $host = getenv("DB_HOST");
        $port = getenv("DB_PORT") ?: '3306';
        $dbname = getenv("DB_NAME");
        $user = getenv("DB_USER");
        $pass = getenv("DB_PASS");

        // Ruta al certificado (desde class/ → va a certificados/)
        $sslCA = realpath(__DIR__ . '/../certificados/singlestore_bundle.pem');

        if (!$sslCA || !file_exists($sslCA)) {
            throw new Exception("No se encontró el archivo PEM en: " . $sslCA);
        }

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

        try {
            $this->conexion = new PDO($dsn, $user, $pass, [
                PDO::MYSQL_ATTR_SSL_CA => $sslCA
            ]);
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
