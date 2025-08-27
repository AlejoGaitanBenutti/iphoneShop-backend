<?php
// tools/seed_admin.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../utils/init.php';
require_once __DIR__ . '/../class/conexion.php';

try {
  $db = (new Database())->getConnection();

  // --- Datos del admin que vamos a crear ---
  $email     = 'admin@local';
  $nombre    = 'Admin';
  $username  = 'admin';
  $rol       = 'admin';
  $passPlano = 'admin123';
  $hash      = password_hash($passPlano, PASSWORD_DEFAULT);

  // ¿Ya existe?
  $stmt = $db->prepare("SELECT id, nombre, correo, rol FROM usuarios WHERE correo = :correo LIMIT 1");
  $stmt->execute([':correo' => $email]);
  if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode([
      'ok' => true,
      'mensaje' => 'El usuario admin ya existe',
      'usuario' => $row,
      'login'   => ['correo' => $email, 'password' => $passPlano]
    ]);
    exit;
  }

  // Columnas reales en la tabla
  $cols = $db->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
  $colNames = array_map(fn($c) => $c['Field'], $cols);

  // Valores candidatos (solo se insertarán si existen esas columnas)
  $values = [
    'nombre'   => $nombre,
    'apellido' => '',
    'correo'   => $email,
    'password' => $hash,
    'rol'      => $rol,
    'username' => $username,
    'verificado' => 1,
    'token_verificacion' => null,
  ];

  // Filtramos por columnas existentes
  $data = [];
  foreach ($values as $k => $v) {
    if (in_array($k, $colNames, true)) $data[$k] = $v;
  }
  if (empty($data)) throw new Exception("No pude mapear columnas de 'usuarios'.");

  // INSERT dinámico
  $columns = implode(',', array_keys($data));
  $params  = implode(',', array_map(fn($k) => ':' . $k, array_keys($data)));
  $sql = "INSERT INTO usuarios ($columns) VALUES ($params)";
  $stmt = $db->prepare($sql);
  $stmt->execute($data);

  echo json_encode([
    'ok' => true,
    'mensaje' => 'Usuario admin creado',
    'login' => ['correo' => $email, 'password' => $passPlano]
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
