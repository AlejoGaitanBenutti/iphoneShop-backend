<?php

require_once __DIR__ . '/../vendor/autoload.php';

// 🔧 Cargar .env.local en lugar de .env
$envPath = __DIR__ . '/../.env.local';
if (file_exists($envPath)) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env.local');
    $dotenv->load();
} else {
    error_log("⚠️ Archivo .env.local no encontrado.");
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
