<?php
require 'vendor/autoload.php'; // Cargar Faker
require './class/conexion.php';


$faker = Faker\Factory::create(); // Instancia de Faker
$faker->locale('es_ES'); // Idioma español para nombres, direcciones, etc.


try {
    $db = new Database(); // Instanciar la conexión
    $conexion = $db->getConnection(); // Obtener la conexión PDO
} catch (Exception $e) {
    die("Error de conexión: " . $e->getMessage());
}


// 🔹 Insertar clientes
function insertClientes($conexion, $cantidad)
{
    global $faker;
    $sql = "INSERT INTO clientes (nombre, apellido, email, telefono, direccion, ciudad, codigo_postal, pais, fecha_registro) 
            VALUES (:nombre, :apellido, :email, :telefono, :direccion, :ciudad, :codigo_postal, :pais, :fecha_registro)";

    $stmt = $conexion->prepare($sql);

    for ($i = 0; $i < $cantidad; $i++) {
        $stmt->execute([
            ':nombre' => $faker->firstName(),
            ':apellido' => $faker->lastName(),
            ':email' => $faker->unique()->email(),
            ':telefono' => $faker->phoneNumber(),
            ':direccion' => $faker->streetAddress(),
            ':ciudad' => $faker->city(),
            ':codigo_postal' => $faker->postcode(),
            ':pais' => $faker->country(),
            ':fecha_registro' => $faker->dateTimeThisYear()->format('Y-m-d'),
        ]);
    }
}

// 🔹 Insertar pedidos
function insertPedidos($conexion, $cantidad)
{
    global $faker;
    for ($i = 0; $i < $cantidad; $i++) {
        $id_cliente = rand(1, 10); // Ajusta según el número de clientes generados
        $total = $faker->randomFloat(2, 10000, 500000); // Total aleatorio de 10,000 a 500,000
        $estado = $faker->randomElement(['pendiente', 'completado', 'cancelado']);
        $metodo_pago = $faker->randomElement(['tarjeta', 'transferencia', 'efectivo', 'paypal']);
        $fecha_pedido = $faker->dateTimeThisYear()->format('Y-m-d');

        $sql = "INSERT INTO pedidos (id_cliente, total, estado, metodo_pago, fecha_pedido) 
                VALUES ($id_cliente, $total, '$estado', '$metodo_pago', '$fecha_pedido')";
        $conexion->query($sql);
    }
}





function insertDetallePedidos($conexion, $cantidad)
{
    global $faker;

    // Obtener todos los IDs de pedidos existentes
    $stmtPedidos = $conexion->query("SELECT id_pedido FROM pedidos");
    $pedidos = $stmtPedidos->fetchAll(PDO::FETCH_COLUMN);

    // Obtener todos los IDs de productos existentes
    $stmtProductos = $conexion->query("SELECT id FROM productos");
    $productos = $stmtProductos->fetchAll(PDO::FETCH_COLUMN);

    if (empty($pedidos) || empty($productos)) {
        echo "❌ No hay pedidos o productos en la base de datos. No se pueden generar detalles de pedidos.\n";
        return;
    }

    $sql = "INSERT INTO detalle_pedidos (id_pedido, id_producto, cantidad, precio_unitario, subtotal) 
            VALUES (:id_pedido, :id_producto, :cantidad, :precio_unitario, :subtotal)";

    $stmt = $conexion->prepare($sql);

    for ($i = 0; $i < $cantidad; $i++) {
        $id_pedido = $pedidos[array_rand($pedidos)]; // Selecciona un pedido existente
        $id_producto = $productos[array_rand($productos)]; // Selecciona un producto existente
        $cantidad = rand(1, 5); // Cantidad entre 1 y 5
        $precio_unitario = $faker->randomFloat(2, 50000, 300000); // Precio entre 50,000 y 300,000
        $subtotal = $cantidad * $precio_unitario; // Calcular subtotal

        $stmt->execute([
            ':id_pedido' => $id_pedido,
            ':id_producto' => $id_producto,
            ':cantidad' => $cantidad,
            ':precio_unitario' => $precio_unitario,
            ':subtotal' => $subtotal,
        ]);
    }
    echo "✅ Detalles de pedidos insertados correctamente.\n";
}


// 🔹 Insertar ventas
function insertVentas($conexion, $cantidad)
{
    global $faker;

    // Obtener todos los IDs de pedidos existentes
    $stmtPedidos = $conexion->query("SELECT id_pedido FROM pedidos");
    $pedidos = $stmtPedidos->fetchAll(PDO::FETCH_COLUMN);

    if (empty($pedidos)) {
        echo "❌ No hay pedidos en la base de datos. No se pueden generar ventas.\n";
        return;
    }

    $sql = "INSERT INTO ventas (id_pedido, id_cliente, total, fecha_venta) 
            VALUES (:id_pedido, :id_cliente, :total, :fecha_venta)";

    $stmt = $conexion->prepare($sql);

    for ($i = 0; $i < $cantidad; $i++) {
        $id_pedido = $pedidos[array_rand($pedidos)]; // Selecciona un pedido existente

        $stmt->execute([
            ':id_pedido' => $id_pedido,
            ':id_cliente' => rand(1, 10), // Ajusta según los clientes generados
            ':total' => $faker->randomFloat(2, 10000, 500000),
            ':fecha_venta' => $faker->dateTimeThisYear()->format('Y-m-d'),
        ]);
    }
    echo "✅ Ventas insertadas correctamente.\n";
}

// Ejecutar las funciones para poblar la base de datos
insertClientes($conexion, 0);  // Inserta 10 clientes
insertPedidos($conexion, 0);   // Inserta 10 pedidos
insertVentas($conexion, 0);    // Inserta 10 ventas
insertDetallePedidos($conexion, 300);

echo "Datos insertados correctamente.";
