<?php
// api/dolar.php
declare(strict_types=1);

// ====== Config ======
$CACHE_DIR   = __DIR__ . '/cache';
$CACHE_FILE  = $CACHE_DIR . '/dolar.json';
$CACHE_TTL_S = 300; // 5 minutos
$TIMEOUT_S   = 6;

// Opcional: limita orígenes si usás cookies. Ajustá según tu front:
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
$allowedOrigins = ['http://localhost:3000', 'http://127.0.0.1:3000']; // agrega tu dominio si aplica
if ($origin !== '*' && in_array($origin, $allowedOrigins, true)) {
  header('Access-Control-Allow-Origin: ' . $origin);
  header('Vary: Origin');
  header('Access-Control-Allow-Credentials: true');
} else {
  header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
header('Content-Type: application/json; charset=utf-8');

// Asegurar cache dir
if (!is_dir($CACHE_DIR)) { @mkdir($CACHE_DIR, 0775, true); }

// ====== Helpers ======
function fetch_json(string $url, int $timeout = 6): ?array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => $timeout,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_USERAGENT      => 'AdminTrust/1.0 (+dolar.php)',
    CURLOPT_SSL_VERIFYPEER => true,
  ]);
  $res = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($res === false || $code < 200 || $code >= 300) return null;
  $data = json_decode($res, true);
  return is_array($data) ? $data : null;
}

function ok(array $of, array $bl, string $source, bool $cached = false): void {
  $out = [
    'oficial'    => ['compra' => $of['compra'] ?? null, 'venta' => $of['venta'] ?? null],
    'blue'       => ['compra' => $bl['compra'] ?? null, 'venta' => $bl['venta'] ?? null],
    'source'     => $source,
    'cached'     => $cached,
    'updated_at' => gmdate('c'),
  ];
  echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function save_cache(string $file, array $payload): void {
  @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

// ====== 1) Entregar cache si está fresco ======
if (is_file($CACHE_FILE) && (time() - filemtime($CACHE_FILE) < $CACHE_TTL_S)) {
  $cached = json_decode(@file_get_contents($CACHE_FILE), true);
  if (is_array($cached)) {
    echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

// ====== 2) Fuente principal: DolarAPI ======
$of = fetch_json('https://dolarapi.com/v1/dolares/oficial', $TIMEOUT_S);
$bl = fetch_json('https://dolarapi.com/v1/dolares/blue',    $TIMEOUT_S);

if ($of && $bl) {
  $oficial = [
    'compra' => isset($of['compra']) ? (float)$of['compra'] : (isset($of['buy']) ? (float)$of['buy'] : null),
    'venta'  => isset($of['venta'])  ? (float)$of['venta']  : (isset($of['sell']) ? (float)$of['sell'] : null),
  ];
  $blue = [
    'compra' => isset($bl['compra']) ? (float)$bl['compra'] : (isset($bl['buy']) ? (float)$bl['buy'] : null),
    'venta'  => isset($bl['venta'])  ? (float)$bl['venta']  : (isset($bl['sell']) ? (float)$bl['sell'] : null),
  ];
  $payload = [
    'oficial'    => $oficial,
    'blue'       => $blue,
    'source'     => 'dolarapi',
    'cached'     => false,
    'updated_at' => gmdate('c'),
  ];
  save_cache($CACHE_FILE, $payload);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// ====== 3) Fallback: Bluelytics ======
$blt = fetch_json('https://api.bluelytics.com.ar/v2/latest', $TIMEOUT_S);
if ($blt && isset($blt['oficial'], $blt['blue'])) {
  $oficial = [
    'compra' => isset($blt['oficial']['value_buy'])  ? (float)$blt['oficial']['value_buy']  : null,
    'venta'  => isset($blt['oficial']['value_sell']) ? (float)$blt['oficial']['value_sell'] : null,
  ];
  $blue = [
    'compra' => isset($blt['blue']['value_buy'])  ? (float)$blt['blue']['value_buy']  : null,
    'venta'  => isset($blt['blue']['value_sell']) ? (float)$blt['blue']['value_sell'] : null,
  ];
  $payload = [
    'oficial'    => $oficial,
    'blue'       => $blue,
    'source'     => 'bluelytics',
    'cached'     => false,
    'updated_at' => gmdate('c'),
  ];
  save_cache($CACHE_FILE, $payload);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// ====== 4) Si todo falló: intenta servir cache viejo, o error ======
if (is_file($CACHE_FILE)) {
  $stale = json_decode(@file_get_contents($CACHE_FILE), true);
  if (is_array($stale)) {
    $stale['cached'] = true; // avisamos que es cache
    echo json_encode($stale, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

http_response_code(502);
echo json_encode(['error' => 'No se pudo obtener la cotización']);
