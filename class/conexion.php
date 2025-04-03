<?php
// class/conexion.php

require_once __DIR__ . '/../vendor/autoload.php';

// Cargar .env solo si estás en local
if (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1'])) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env.local');
    $dotenv->load();
}

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

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

        // Detectar si es entorno local
        $isLocal = in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']);

        // Configuración de conexión
        $options = [];

        // Si es producción (Render), usar SSL
        if (!$isLocal) {
            $sslCA = realpath(__DIR__ . '/../certificados/singlestore_bundle.pem');
            if (!$sslCA || !file_exists($sslCA)) {
                throw new Exception("No se encontró el archivo PEM en: " . $sslCA);
            }
            $options = [
                PDO::MYSQL_ATTR_SSL_CA => $sslCA
            ];
        }

        try {
            $this->conexion = new PDO($dsn, $user, $pass, $options);
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
