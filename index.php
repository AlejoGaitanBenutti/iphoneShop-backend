<?php
// index.php

header('Content-Type: application/json');

echo json_encode([
    "app" => "iphoneShop",
    "status" => "online",
    "version" => "1.0.0",
    "message" => "Bienvenido a la API de IphoneShop🚀",
    "endpoints" => [
        "Auth" => [
            "/auth/login.php",
            "/auth/logout.php",
            "/auth/me.php",
            "/auth/registro.php",
            "/auth/verificar.php"
        ],
        "Usuarios" => [
            "/api/usuarios/listar_usuarios.php",
            "/api/usuarios/actualizar_rol.php"
        ],
        "Clientes & Ventas" => [
            "/api/clientes.php",
            "/api/ingresos.php",
            "/api/nuevosClientes.php",
            "/api/totalVentas.php",
            "/api/usuariosTotales.php",
            "/api/ventas.php"
        ],
        "Clases & Utilidades" => [
            "/class/authMiddleware.php",
            "/class/usuarios.php",
            "/class/email.php",
            "/class/productos.php",
            "/utils/cors.php",
            "/utils/formatNumber.php",
            "/utils/generar_datos.php",
            "/utils/init.php"
        ]
    ],
    "documentacion" => "Próximamente 😉"
], JSON_PRETTY_PRINT);
