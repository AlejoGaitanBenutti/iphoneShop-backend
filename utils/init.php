<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Solo intentar cargar .env.local si estamos en local
if (!isset($_ENV['APP_ENV'])) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env.local');
    if (file_exists(__DIR__ . '/../.env.local')) {
        $dotenv->load();
        $_ENV['APP_ENV'] = 'local';
        error_log("✅ Variables cargadas desde .env.local");
    } else {
        error_log("⚠️ Archivo .env.local no encontrado.");
        $_ENV['APP_ENV'] = 'production'; // Por seguridad asumimos producción si no existe
    }
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
