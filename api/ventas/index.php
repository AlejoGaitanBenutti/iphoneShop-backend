<?php
require_once __DIR__ . '/../../utils/init.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../class/conexion.php';
require_once __DIR__ . '/../../class/authMiddleware.php';
require_once __DIR__ . '/../../class/Ventas.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') { http_response_code(204); exit; }

try {
  AuthMiddleware::verificarAdmin();
  $db = (new Database())->getConnection();

  // Usuario actual (para auditoría)
  $jwtUser = AuthMiddleware::verificarJWT();
  $uid     = $jwtUser->id ?? null;

  $ventas = new Ventas($db, $uid);

  // ---- helper para leer JSON/POST/GET ----
  $json  = null;
  $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($ctype, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
  }
  $in = function(string $k, $def=null) use ($json) {
    if (is_array($json)) return array_key_exists($k, $json) ? $json[$k] : $def;
    return $_POST[$k] ?? ($_GET[$k] ?? $def);
  };

  if ($method === 'POST') {
    $accion = $in('accion', '');
    if ($accion === 'crear') {
      $data = [
        'cliente_id'  => $in('cliente_id'),
        'metodo_pago' => $in('metodo_pago', 'efectivo'),
        'descuento'   => $in('descuento', 0),
        'impuestos'   => $in('impuestos', 0),
        'items'       => $in('items', []), // [{producto_id, precio_unit}]
      ];
      $res = $ventas->crearVenta($data);
      echo json_encode(['success' => true] + $res);
      exit;
    }

    throw new Exception("Acción POST no soportada");
  }

  // ---- GET ----
  $accion = $_GET['accion'] ?? '';

  if ($accion === 'ver') {
    $id  = (int)($_GET['id'] ?? 0);
    $res = $ventas->verVenta($id);
    echo json_encode(['success' => true] + $res);
    exit;
  }

  if ($accion === 'disponibles') {
    $q = trim($_GET['q'] ?? '');
    echo json_encode($ventas->productosDisponibles($q));
    exit;
  }

  // Por defecto: listar últimas ventas
  echo json_encode($ventas->listarVentas());
  exit;

} catch (Throwable $e) {
  if (isset($db) && $db->inTransaction()) $db->rollBack();
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
