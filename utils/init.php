<?php

require_once __DIR__ . '/../vendor/autoload.php';  // cARGA AUTOMATIC LAS DEPENDENCIAS

if (!isset($_ENV['APP_ENV'])) { //  Verifica si ya está seteado el entorno (APP_ENV)

     //Crea una instancia de Dotenv para leer el archivo .env.local.
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env.local');  // El primer parámetro (__DIR__ . '/../') es el directorio donde buscará el archivo .env.local.
    if (file_exists(__DIR__ . '/../.env.local')) { // Asegura que el archivo exista antes de intentar cargarlo
        $dotenv->load(); // Carga todas las variables del archivo .env.local y las guarda en $_ENV.

        // ✅ Hace que getenv() funcione
        foreach ($_ENV as $key => $value) {
            putenv("$key=$value");
        }

        $_ENV['APP_ENV'] = 'local';
        error_log("✅ Variables cargadas desde .env.local");
    } else {
        error_log("⚠️ Archivo .env.local no encontrado.");
        $_ENV['APP_ENV'] = 'production';
    }
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);


// "Cargo mis variables .env si no están cargadas, aseguro que estén disponibles vía getenv(), y configuro cómo manejar errores en PHP."