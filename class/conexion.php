<?php
class Database
{
    private $conexion;

    public function __construct()
    {
        $entorno = $_ENV['APP_ENV'] ?? 'local';

        if ($entorno === 'production') {
            $host = $_ENV['DB_HOST_PROD'];
            $port = $_ENV['DB_PORT_PROD'];
            $dbname = $_ENV['DB_NAME_PROD'];
            $user = $_ENV['DB_USER_PROD'];
            $pass = $_ENV['DB_PASS_PROD'];

            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

            // Opciones SSL para producción
            $options = [
                PDO::MYSQL_ATTR_SSL_CA => __DIR__ . '/../certificados/singlestore_bundle.pem',
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
            ];
        } else {
            $host = $_ENV['DB_HOST_LOCAL'] ?? 'localhost';
            $port = $_ENV['DB_PORT_LOCAL'] ?? '3306';
            $dbname = $_ENV['DB_NAME_LOCAL'] ?? 'reliable';
            $user = $_ENV['DB_USER_LOCAL'] ?? 'root';
            $pass = $_ENV['DB_PASS_LOCAL'] ?? '';

            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            $options = [];
        }

        try {
            $this->conexion = new PDO($dsn, $user, $pass, $options);
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            error_log("✅ Conexión exitosa");
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
