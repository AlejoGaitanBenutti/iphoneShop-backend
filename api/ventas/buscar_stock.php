<?php
// /api/ventas/buscar_stock.php
require_once __DIR__ . '/../../utils/init.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../class/conexion.php';
require_once __DIR__ . '/../../class/authMiddleware.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

try {
  // vendedor/admin logueado
  AuthMiddleware::verificarAdmin();

  $db = (new Database())->getConnection();
  $q  = trim($_GET['q'] ?? '');

  // base: solo stock disponible
  $where  = "p.status_stock = 'disponible'";
  $params = [];

  if ($q !== '') {
    $tokens = preg_split('/\s+/', $q);
    $parts  = [];
    $i = 0;
    foreach ($tokens as $t) {
      if ($t === '') continue;
      $k = ":t$i";
      $params[$k] = '%' . $t . '%';
      $parts[] = "(p.modelo LIKE $k OR p.color LIKE $k OR p.imei_1 LIKE $k OR p.imei_2 LIKE $k OR p.sku LIKE $k)";
      $i++;
    }
    if ($parts) $where .= ' AND ' . implode(' AND ', $parts);
  }

  $sql = "
    SELECT
      p.id, p.sku, p.marca, p.modelo, p.almacenamiento_gb, p.color,
      p.imei_1, p.imei_2, p.precio_lista, p.costo,
      p.bateria_salud, p.bateria_ciclos,
      (SELECT url FROM productos_imagenes i
        WHERE i.producto_id = p.id
        ORDER BY i.orden ASC, i.id ASC LIMIT 1) AS imagen
    FROM productos p
    WHERE $where
    ORDER BY p.created_at DESC
    LIMIT 25
  ";

  $st = $db->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $out = array_map(function($r) {
    $label   = trim(($r['modelo'] ?? '') . ' ' . (int)$r['almacenamiento_gb'] . 'GB ' . ($r['color'] ?? ''));
    $detalle = $r['imei_1'] ? ('IMEI: ' . $r['imei_1']) : ($r['sku'] ?? '');
    return [
      'id'               => (int)$r['id'],
      'sku'              => $r['sku'],
      'label'            => $label,
      'detalle'          => $detalle,
      'modelo'           => $r['modelo'],
      'almacenamiento_gb'=> (int)$r['almacenamiento_gb'],
      'color'            => $r['color'],
      'precio_sugerido'  => (float)$r['precio_lista'],
      'costo'            => (float)$r['costo'],
      'bateria_salud'    => isset($r['bateria_salud']) ? (int)$r['bateria_salud'] : null,
      'bateria_ciclos'   => isset($r['bateria_ciclos']) ? (int)$r['bateria_ciclos'] : null,
      'imagen'           => $r['imagen'],
    ];
  }, $rows);

  echo json_encode($out);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
