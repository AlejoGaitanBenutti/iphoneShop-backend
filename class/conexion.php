<?php
class Database
{
    private $conexion;

    public function __construct()
    {
        // Detectar entorno
        $entorno = $_ENV['APP_ENV'] ?? 'local';

        if ($entorno === 'production') {
            $host = $_ENV['DB_HOST_PROD'];
            $port = $_ENV['DB_PORT_PROD'];
            $dbname = $_ENV['DB_NAME_PROD'];
            $user = $_ENV['DB_USER_PROD'];
            $pass = $_ENV['DB_PASS_PROD'];
        } else {
            $host = $_ENV['DB_HOST_LOCAL'] ?? 'localhost';
            $port = $_ENV['DB_PORT_LOCAL'] ?? '3306';
            $dbname = $_ENV['DB_NAME_LOCAL'] ?? 'reliable';
            $user = $_ENV['DB_USER_LOCAL'] ?? 'root';
            $pass = $_ENV['DB_PASS_LOCAL'] ?? '';

            // Mostrar logs solo en local para debug
            error_log("🛠 Conectando a base local -> host: $host, dbname: $dbname, user: $user");
        }

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];

        try {
            $this->conexion = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log("❌ Error PDO: " . $e->getMessage());
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
