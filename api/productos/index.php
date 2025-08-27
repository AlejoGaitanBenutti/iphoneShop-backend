<?php
// api/productos/index.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

require_once __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/../../utils/init.php";      // Dotenv si aplica
require_once __DIR__ . "/../../class/conexion.php";  // tu clase Database
require_once __DIR__ . "/../../class/Productos.php"; // tu modelo

/**
 * Lee el cuerpo de la request para PUT/DELETE.
 * Soporta JSON (application/json) o x-www-form-urlencoded (text/plain también).
 */
function readInput(): array {
  $raw = file_get_contents('php://input') ?: '';
  $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($ct, 'application/json') !== false) {
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
  }
  $data = [];
  parse_str($raw, $data);
  return is_array($data) ? $data : [];
}

try {
  $db   = new Database();
  $pdo  = $db->getConnection();
  $prod = new Productos($pdo);

  $method = $_SERVER['REQUEST_METHOD'];
  $id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

  // ---------------------- POST (alta) ----------------------
  if ($method === 'POST') {
    $accion = $_POST['accion'] ?? ($_GET['accion'] ?? '');

    if ($accion === 'guardar') {
      $p = fn($k, $def=null) => isset($_POST[$k]) ? trim($_POST[$k]) : $def;

      $marca  = $p('marca');
      $modelo = $p('modelo');
      $alm    = (int) $p('almacenamiento_gb', 0);
      $color  = $p('color');
      $imei1  = $p('imei_1') ?: null;
      $imei2  = $p('imei_2') ?: null;
      $estado = $p('estado', 'usado');
      $origen = $p('origen', 'compra');
      $costo  = (float) $p('costo', 0);
      $precio = (float) $p('precio_lista', 0);
      $prov   = $p('proveedor_id') !== '' ? (int)$p('proveedor_id') : null;
      $gar    = (int) $p('garantia_meses', 0);
      $notas  = $p('notas', '');
      $status = $p('status_stock', 'disponible');

      $bateria_salud  = ($p('bateria_salud')  === null || $p('bateria_salud')  === '') ? null : (int)$p('bateria_salud');
      $bateria_ciclos = ($p('bateria_ciclos') === null || $p('bateria_ciclos') === '') ? null : (int)$p('bateria_ciclos');

      if (!$marca || !$modelo) throw new Exception("Falta marca o modelo");
      if ($bateria_salud !== null && ($bateria_salud < 50 || $bateria_salud > 100)) {
        throw new Exception("La salud de batería debe estar entre 50 y 100.");
      }
      if ($bateria_ciclos !== null && $bateria_ciclos < 0) {
        throw new Exception("Los ciclos de batería no pueden ser negativos.");
      }

      // unicidad IMEIs
      if ($imei1) {
        $q = $pdo->prepare("SELECT id FROM productos WHERE imei_1=:i OR imei_2=:i LIMIT 1");
        $q->execute([':i'=>$imei1]);
        if ($q->fetch()) throw new Exception("IMEI 1 ya registrado");
      }
      if ($imei2) {
        $q = $pdo->prepare("SELECT id FROM productos WHERE imei_1=:i OR imei_2=:i LIMIT 1");
        $q->execute([':i'=>$imei2]);
        if ($q->fetch()) throw new Exception("IMEI 2 ya registrado");
      }

      $pdo->beginTransaction();
      $sql = "INSERT INTO productos
        (marca, modelo, almacenamiento_gb, bateria_salud, bateria_ciclos, color,
         imei_1, imei_2, estado, origen, costo, precio_lista, proveedor_id,
         garantia_meses, notas, status_stock, created_at, updated_at)
        VALUES
        (:marca,:modelo,:alm,:batsalud,:batciclos,:color,
         :imei1,:imei2,:estado,:origen,:costo,:precio,:prov,
         :gar,:notas,:status, NOW(), NOW())";
      $st = $pdo->prepare($sql);
      $st->execute([
        ':marca'    => $marca,
        ':modelo'   => $modelo,
        ':alm'      => $alm,
        ':batsalud' => $bateria_salud,
        ':batciclos'=> $bateria_ciclos,
        ':color'    => $color,
        ':imei1'    => $imei1,
        ':imei2'    => $imei2,
        ':estado'   => $estado,
        ':origen'   => $origen,
        ':costo'    => $costo,
        ':precio'   => $precio,
        ':prov'     => $prov,
        ':gar'      => $gar,
        ':notas'    => $notas,
        ':status'   => $status
      ]);
      $productoId = (int)$pdo->lastInsertId();

      // TODO: manejo de imágenes si subís imagen_0..imagen_3 con $_FILES

      $pdo->commit();
      echo json_encode(['success'=>true, 'id'=>$productoId], JSON_UNESCAPED_UNICODE);
      exit;
    }

    http_response_code(400);
    echo json_encode(['error'=>'Acción POST no soportada'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ---------------------- GET (uno por id) ----------------------
  if ($method === 'GET') {
    if ($id <= 0) {
      http_response_code(400);
      echo json_encode(['error'=>'Parámetro id requerido'], JSON_UNESCAPED_UNICODE);
      exit;
    }
    $row = $prod->obtenerPorId($id);
    echo json_encode($row, JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ---------------------- PUT (editar) ----------------------
  if ($method === 'PUT') {
    if ($id <= 0) {
      http_response_code(400);
      echo json_encode(['error'=>'Parámetro id requerido'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'multipart/form-data') !== false) {
      // editar con imágenes (FormData)
      $data       = $_POST ?? [];
      $reemplazar = isset($_POST['reemplazar_fotos']) ? (int)$_POST['reemplazar_fotos'] : 0;
      $prod->actualizar($id, $data, $_FILES, $reemplazar);
    } else {
      // JSON / urlencoded
      $data = readInput();
      if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error'=>'Body inválido'], JSON_UNESCAPED_UNICODE);
        exit;
      }

      // Filtrar white-list de campos aceptados
      $permitidos = [
        'sku','precio_lista','costo','estado','bateria_salud','bateria_ciclos',
        'color','almacenamiento_gb','status_stock','marca','modelo','proveedor_id',
        'garantia_meses','notas','origen','imei_1','imei_2'
      ];
      $clean = [];
      foreach ($permitidos as $k) {
        if (array_key_exists($k, $data)) $clean[$k] = $data[$k];
      }

      $prod->actualizar($id, $clean, [], 0);
    }

    $row = $prod->obtenerPorId($id);
    echo json_encode($row, JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ---------------------- DELETE (eliminar) ----------------------
  if ($method === 'DELETE') {
    if ($id <= 0) {
      http_response_code(400);
      echo json_encode(['error'=>'Parámetro id requerido'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $prod->eliminar($id);
    echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ---------------------- Método no permitido ----------------------
  http_response_code(405);
  echo json_encode(['error'=>'Método no permitido'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  error_log("[api/productos] ".$e->getMessage());
  echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
