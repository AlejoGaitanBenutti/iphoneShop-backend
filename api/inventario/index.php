<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

require_once __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/../../utils/init.php";
require_once __DIR__ . "/../../class/conexion.php";

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  // --------- Filtros ---------
  $q        = isset($_GET["q"]) ? trim($_GET["q"]) : "";
  $modelo   = isset($_GET["modelo"]) ? trim($_GET["modelo"]) : "";
  $color    = isset($_GET["color"]) ? trim($_GET["color"]) : "";
  $almStr   = isset($_GET["almacenamiento_gb"]) ? $_GET["almacenamiento_gb"] : "";
  $estado   = isset($_GET["estado"]) ? trim($_GET["estado"]) : "";
  $minStr   = isset($_GET["min_bateria"]) ? $_GET["min_bateria"] : "";
  $stock    = isset($_GET["status_stock"]) ? trim($_GET["status_stock"]) : "";

  $almacenamiento = (is_numeric($almStr) ? (int)$almStr : null);
  $min_bateria    = (is_numeric($minStr) ? (int)$minStr : null);

  $page     = max(1, isset($_GET["page"]) ? (int)$_GET["page"] : 1);
  $perPage  = min(100, max(1, isset($_GET["per_page"]) ? (int)$_GET["per_page"] : 50));
  $offset   = ($page - 1) * $perPage;

  // --------- WHERE + binds ---------
  $where = ["1=1"];
  $bind  = [];

  // status por defecto: disponible
  if ($stock !== "") {
    $where[]       = "p.status_stock = :stock";
    $bind[":stock"] = $stock;
  } else {
    $where[] = "p.status_stock = 'disponible'";
  }

  if ($q !== "") {
    $where[]    = "(
      p.modelo LIKE :q
      OR p.color LIKE :q
      OR p.sku LIKE :q
      OR p.imei_1 LIKE :q
      OR p.imei_2 LIKE :q
    )";
    $bind[":q"] = "%{$q}%";
  }

  if ($modelo !== "" && $modelo !== "all") {
    $where[] = "p.modelo = :modelo";
    $bind[":modelo"] = $modelo;
  }
  if ($color !== "" && $color !== "all") {
    $where[] = "p.color = :color";
    $bind[":color"] = $color;
  }
  if ($almacenamiento !== null) {
    $where[] = "p.almacenamiento_gb = :alm";
    $bind[":alm"] = $almacenamiento;
  }
  if ($estado !== "" && $estado !== "all") {
    $where[] = "p.estado = :estado";
    $bind[":estado"] = $estado;
  }
  if ($min_bateria !== null && $min_bateria !== "") {
    $where[] = "p.bateria_salud >= :minbat";
    $bind[":minbat"] = $min_bateria;
  }

  $whereSql = implode(" AND ", $where);

  // --------- ITEMS (con imagen principal y datos de trade-in) ---------
  $sqlItems = "
    SELECT
      p.id, p.sku, p.marca, p.modelo, p.almacenamiento_gb, p.bateria_salud, p.bateria_ciclos,
      p.color, p.estado, p.origen, p.costo, p.precio_lista, p.garantia_meses, p.status_stock,
      p.imei_1, p.imei_2,
      p.created_at, p.updated_at,
      COALESCE(pi.url, '') AS imagen_url,

      -- Valor recibido por permuta
      (SELECT im.costo_unit
         FROM inventario_movimientos im
        WHERE im.producto_id = p.id
          AND im.tipo = 'permuta_ingreso'
        ORDER BY im.id ASC
        LIMIT 1) AS recibido_valor,

      -- Fecha de ingreso por permuta
      (SELECT im.fecha
         FROM inventario_movimientos im
        WHERE im.producto_id = p.id
          AND im.tipo = 'permuta_ingreso'
        ORDER BY im.id ASC
        LIMIT 1) AS recibido_fecha,

      -- Venta que originó la permuta
      (SELECT v.id
         FROM inventario_movimientos im
         JOIN ventas v ON v.id = im.venta_id
        WHERE im.producto_id = p.id
          AND im.tipo = 'permuta_ingreso'
        ORDER BY im.id ASC
        LIMIT 1) AS recibido_venta_id,

      -- Cliente que entregó (clientes.nombre o snapshot)
      (SELECT COALESCE(c.nombre, JSON_UNQUOTE(JSON_EXTRACT(v.comprador_snapshot, '$.nombre')))
         FROM inventario_movimientos im
         JOIN ventas v ON v.id = im.venta_id
    LEFT JOIN clientes c ON c.id = v.cliente_id
        WHERE im.producto_id = p.id
          AND im.tipo = 'permuta_ingreso'
        ORDER BY im.id ASC
        LIMIT 1) AS recibido_cliente

    FROM productos p
    LEFT JOIN (
      SELECT producto_id, MAX(id) AS max_id
        FROM productos_imagenes
       GROUP BY producto_id
    ) pim ON pim.producto_id = p.id
    LEFT JOIN productos_imagenes pi ON pi.id = pim.max_id
    WHERE $whereSql
    ORDER BY p.updated_at DESC
    LIMIT :limit OFFSET :offset
  ";

  $stmt = $pdo->prepare($sqlItems);
  foreach ($bind as $k => $v) $stmt->bindValue($k, $v);
  $stmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
  $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
  $stmt->execute();
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Normalizo tipos numéricos para evitar strings
  $items = array_map(function ($r) {
    $r["almacenamiento_gb"] = isset($r["almacenamiento_gb"]) ? (int)$r["almacenamiento_gb"] : null;
    $r["bateria_salud"]     = isset($r["bateria_salud"]) ? (int)$r["bateria_salud"] : null;
    $r["bateria_ciclos"]    = isset($r["bateria_ciclos"]) ? (int)$r["bateria_ciclos"] : null;
    $r["costo"]             = isset($r["costo"]) ? (float)$r["costo"] : null;
    $r["precio_lista"]      = isset($r["precio_lista"]) ? (float)$r["precio_lista"] : null;
    if (array_key_exists("recibido_valor", $r) && $r["recibido_valor"] !== null) {
      $r["recibido_valor"] = (float)$r["recibido_valor"];
    }
    return $r;
  }, $items);

  // --------- KPIs (mismos WHERE, sin paginar) ---------
  $sqlKpis = "
    SELECT
      COUNT(*)                                        AS total,
      COALESCE(SUM(p.costo), 0)                       AS valor_costo,
      COALESCE(SUM(p.precio_lista), 0)                AS valor_venta,
      SUM(CASE WHEN p.estado = 'nuevo' THEN 1 ELSE 0 END) AS nuevos,
      SUM(CASE WHEN p.estado = 'usado' THEN 1 ELSE 0 END) AS usados
    FROM productos p
    WHERE $whereSql
  ";

  $stmK = $pdo->prepare($sqlKpis);
  foreach ($bind as $k => $v) $stmK->bindValue($k, $v);
  $stmK->execute();
  $k = $stmK->fetch(PDO::FETCH_ASSOC) ?: [];

  $kpis = [
    "total"       => (int)($k["total"] ?? 0),
    "valor_costo" => (float)($k["valor_costo"] ?? 0),
    "valor_venta" => (float)($k["valor_venta"] ?? 0),
    "nuevos"      => (int)($k["nuevos"] ?? 0),
    "usados"      => (int)($k["usados"] ?? 0),
  ];

  echo json_encode([
    "items" => $items,
    "meta"  => [
      "page"      => $page,
      "per_page"  => $perPage,
      "count"     => count($items),
      "kpis"      => $kpis,
    ],
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
