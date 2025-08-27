<?php
// class/Productos.php

class Productos
{
  private PDO $db;
  private string $uploadDirFs;   // ruta en filesystem
  private string $uploadDirWeb;  // ruta web (relativa a public)

  public function __construct(PDO $db)
  {
    $this->db = $db;

    // carpetas de uploads
    $this->uploadDirFs  = rtrim(realpath(__DIR__ . '/../'), '/\\') . '/uploads/productos';
    $this->uploadDirWeb = 'uploads/productos';

    if (!is_dir($this->uploadDirFs)) {
      @mkdir($this->uploadDirFs, 0775, true);
    }

    // por las dudas
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  }

  /* ============ LECTURA ============ */

  public function listar(?string $q = null, ?string $status = null, int $limit = 100): array
  {
    $sql = "SELECT p.*,
              (SELECT url FROM productos_imagenes i
               WHERE i.producto_id = p.id
               ORDER BY i.es_principal DESC, i.id ASC
               LIMIT 1) AS imagen_url
            FROM productos p";
    $where = [];
    $bind  = [];

    if ($q) {
      $where[] = "(p.marca LIKE :q OR p.modelo LIKE :q OR p.color LIKE :q)";
      $bind[':q'] = "%$q%";
    }
    if ($status) {
      $where[] = "p.status_stock = :st";
      $bind[':st'] = $status;
    }
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);

    $sql .= " ORDER BY p.created_at DESC LIMIT :lim";
    $stmt = $this->db->prepare($sql);
    foreach ($bind as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function obtenerPorId(int $id): array
  {
    $p = $this->getProducto($id);
    $p['imagenes']   = $this->getImagenes($id);          // id, url, es_principal
    $p['imagen_url'] = $p['imagenes'][0]['url'] ?? null; // compat front
    return $p;
  }

  private function getProducto(int $id): array
  {
    $st = $this->db->prepare("SELECT * FROM productos WHERE id = :id");
    $st->execute([':id' => $id]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) throw new Exception("Producto no encontrado");
    return $p;
  }

  private function getImagenes(int $productoId): array
  {
    $st = $this->db->prepare("SELECT id, url, es_principal
                              FROM productos_imagenes
                              WHERE producto_id = :id
                              ORDER BY es_principal DESC, id ASC");
    $st->execute([':id' => $productoId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  /* ============ CREAR ============ */

  public function crear(array $data, array $files = []): int
  {
    $this->validarCamposObligatorios($data, true);
    $this->verificarUnicidadImeis($data['imei_1'] ?? null, $data['imei_2'] ?? null, null);

    $this->db->beginTransaction();
    try {
      $sql = "INSERT INTO productos
        (marca, modelo, almacenamiento_gb, bateria_salud, bateria_ciclos, color,
         imei_1, imei_2, estado, origen,
         costo, precio_lista, proveedor_id, garantia_meses, notas, status_stock,
         created_at, updated_at)
        VALUES
        (:marca, :modelo, :alm, :batsalud, :batciclos, :color,
         :imei1, :imei2, :estado, :origen,
         :costo, :precio, :prov, :gar, :notas, :status,
         NOW(), NOW())";

      $st = $this->db->prepare($sql);
      $st->execute([
        ':marca'    => $data['marca'],
        ':modelo'   => $data['modelo'],
        ':alm'      => (int)($data['almacenamiento_gb'] ?? 0),
        ':batsalud' => $this->intOrNull($data['bateria_salud']  ?? null),
        ':batciclos'=> $this->intOrNull($data['bateria_ciclos'] ?? null),
        ':color'    => $data['color'],
        ':imei1'    => $this->nullIfEmpty($data['imei_1'] ?? null),
        ':imei2'    => $this->nullIfEmpty($data['imei_2'] ?? null),
        ':estado'   => $data['estado'] ?? 'usado',
        ':origen'   => $data['origen'] ?? 'compra',
        ':costo'    => $this->floatOrNull($data['costo'] ?? null),
        ':precio'   => $this->floatOrNull($data['precio_lista'] ?? null),
        ':prov'     => $this->intOrNull($data['proveedor_id'] ?? null),
        ':gar'      => (int)($data['garantia_meses'] ?? 0),
        ':notas'    => $data['notas'] ?? '',
        ':status'   => $data['status_stock'] ?? 'disponible',
      ]);

      $id = (int)$this->db->lastInsertId();

      // imágenes (imagen_0..imagen_3)
      if (!empty($files)) {
        $this->guardarImagenesSubidas($id, $files);
      }

      // historial (best-effort)
      $this->registrarHistorialActual('alta', $id, "Producto creado");

      $this->db->commit();
      return $id;

    } catch (Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }

  /* ============ EDITAR ============ */

  public function actualizar(int $id, array $data, array $files = [], int $reemplazarFotos = 0): void
  {
    // comprobar existencia
    $actual = $this->getProducto($id);

    // normalizar IMEIs y verificar unicidad (excluyendo este producto)
    $imei1Norm = array_key_exists('imei_1', $data) ? $this->nullIfEmpty($data['imei_1']) : $actual['imei_1'];
    $imei2Norm = array_key_exists('imei_2', $data) ? $this->nullIfEmpty($data['imei_2']) : $actual['imei_2'];
    $this->verificarUnicidadImeis($imei1Norm, $imei2Norm, $id);

    // campos editables
    $campos = [
      'sku','marca','modelo','almacenamiento_gb','color',
      'estado','origen','costo','precio_lista','proveedor_id',
      'garantia_meses','notas','status_stock',
      'bateria_salud','bateria_ciclos','imei_1','imei_2',
    ];

    $set  = [];
    $bind = [':id' => $id];

    foreach ($campos as $c) {
      if (!array_key_exists($c, $data)) continue;
      $val = $data[$c];

      // casteos
      if (in_array($c, ['almacenamiento_gb','garantia_meses','bateria_salud','bateria_ciclos'], true)) {
        $val = $this->intOrNull($val);
      } elseif (in_array($c, ['costo','precio_lista'], true)) {
        $val = $this->floatOrNull($val);
      } elseif ($c === 'proveedor_id') {
        $val = $this->intOrNull($val);
      } elseif (in_array($c, ['imei_1','imei_2'], true)) {
        $val = $this->nullIfEmpty($val);
      }

      $set[] = "$c = :$c";
      $bind[":$c"] = $val;
    }

    if (!$set && empty($files)) {
      return; // nada que actualizar
    }

    $this->db->beginTransaction();
    try {
      if ($set) {
        $sql = "UPDATE productos
                   SET " . implode(', ', $set) . ", updated_at = NOW()
                 WHERE id = :id";
        $st  = $this->db->prepare($sql);
        $st->execute($bind);
      }

      // imágenes nuevas (si mandaste FormData con imagen_0..imagen_3)
      if (!empty($files)) {
        if ($reemplazarFotos) $this->borrarImagenesProducto($id);
        $this->guardarImagenesSubidas($id, $files, $reemplazarFotos ? true : false);
      }

      // historial (best-effort)
      $this->registrarHistorialActual('edicion', $id, "Producto actualizado");

      $this->db->commit();

    } catch (Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }

  /* ============ ELIMINAR ============ */

  public function eliminar(int $id): void
  {
    // asegurar que existe
    $this->getProducto($id);

    $this->db->beginTransaction();
    try {
      // borrar imágenes (ficheros + filas)
      $this->borrarImagenesProducto($id);

      // borrar producto
      $st = $this->db->prepare("DELETE FROM productos WHERE id = :id");
      $st->execute([':id'=>$id]);

      // historial (best-effort)
      $this->registrarHistorialActual('eliminacion', $id, "Producto eliminado");

      $this->db->commit();

    } catch (Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }

  /* ============ HELPERS ============ */

  private function validarCamposObligatorios(array $d, bool $esAlta = true): void
  {
    $req = ['marca','modelo','almacenamiento_gb','color','estado','origen','costo','precio_lista'];
    foreach ($req as $k) {
      if ($esAlta && (!isset($d[$k]) || $d[$k] === '')) {
        throw new Exception("Falta campo obligatorio: $k");
      }
    }
    // validaciones batería si vienen
    if (isset($d['bateria_salud']) && $d['bateria_salud'] !== '' && $d['bateria_salud'] !== null) {
      $bs = (int)$d['bateria_salud'];
      if ($bs < 0 || $bs > 100) throw new Exception("La salud de batería debe estar entre 0 y 100");
    }
    if (isset($d['bateria_ciclos']) && $d['bateria_ciclos'] !== '' && $d['bateria_ciclos'] !== null) {
      if ((int)$d['bateria_ciclos'] < 0) throw new Exception("Los ciclos de batería no pueden ser negativos");
    }
  }

  private function verificarUnicidadImeis(?string $i1, ?string $i2, ?int $excluirId): void
  {
    foreach (['i1' => $i1, 'i2' => $i2] as $imei) {
      if (!$imei) continue;
      $sql = "SELECT id FROM productos
              WHERE (imei_1 = :i OR imei_2 = :i)";
      if ($excluirId) $sql .= " AND id <> :ex";
      $st = $this->db->prepare($sql);
      $st->bindValue(':i', $imei);
      if ($excluirId) $st->bindValue(':ex', $excluirId, PDO::PARAM_INT);
      $st->execute();
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if ($row) throw new Exception("El IMEI '{$imei}' ya existe en otro equipo");
    }
  }

  private function guardarImagenesSubidas(int $productoId, array $files, bool $insertPrincipalPrimero = true): void
  {
    // Keys esperadas: imagen_0..imagen_3
    $orden = [0,1,2,3];
    $insert = $this->db->prepare(
      "INSERT INTO productos_imagenes (producto_id, url, es_principal)
       VALUES (:pid, :url, :es)"
    );

    foreach ($orden as $i) {
      $key = "imagen_$i";
      if (!isset($files[$key]) || $files[$key]['error'] !== UPLOAD_ERR_OK) continue;

      $tmp  = $files[$key]['tmp_name'];
      $ext  = strtolower(pathinfo($files[$key]['name'], PATHINFO_EXTENSION) ?: 'jpg');
      $name = 'img_' . uniqid('', true) . '.' . $ext;
      $dest = rtrim($this->uploadDirFs,'/\\') . DIRECTORY_SEPARATOR . $name;

      if (!@move_uploaded_file($tmp, $dest)) {
        throw new Exception("No se pudo guardar $key");
      }

      $urlWeb = rtrim($this->uploadDirWeb,'/\\') . '/' . $name;

      // principal = true sólo si es imagen_0 y no existe ya una principal
      $esPrincipal = 0;
      if ($i === 0) {
        $esPrincipal = $insertPrincipalPrimero ? 1 : 0;
        if ($this->existePrincipal($productoId)) $esPrincipal = 0;
      }

      $insert->execute([
        ':pid'  => $productoId,
        ':url'  => $urlWeb,
        ':es'   => $esPrincipal,
      ]);
    }

    // si no existe principal, setear la primera como principal
    if (!$this->existePrincipal($productoId)) {
      $st = $this->db->prepare("SELECT id FROM productos_imagenes WHERE producto_id = :p ORDER BY id ASC LIMIT 1");
      $st->execute([':p'=>$productoId]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        $up = $this->db->prepare("UPDATE productos_imagenes SET es_principal = 1 WHERE id = :id");
        $up->execute([':id'=>$row['id']]);
      }
    }
  }

  private function existePrincipal(int $productoId): bool
  {
    $st = $this->db->prepare("SELECT 1 FROM productos_imagenes WHERE producto_id = :p AND es_principal = 1 LIMIT 1");
    $st->execute([':p'=>$productoId]);
    return (bool)$st->fetchColumn();
  }

  private function borrarImagenesProducto(int $productoId): void
  {
    $imgs = $this->getImagenes($productoId);
    foreach ($imgs as $img) {
      $fsPath = $this->rutaWebToFs($img['url']);
      if ($fsPath && file_exists($fsPath)) {
        @unlink($fsPath);
      }
    }
    $del = $this->db->prepare("DELETE FROM productos_imagenes WHERE producto_id = :p");
    $del->execute([':p'=>$productoId]);
  }

  private function rutaWebToFs(string $urlWeb): ?string
  {
    $urlWeb = str_replace(['\\','//'], '/', $urlWeb);
    $base = rtrim($this->uploadDirWeb, '/');
    if (strpos($urlWeb, $base) === 0) {
      $filename = substr($urlWeb, strlen($base));
      return rtrim($this->uploadDirFs, '/\\') . DIRECTORY_SEPARATOR . ltrim($filename, '/\\');
    }
    // fallback (por si guardaste otro path relativo)
    $candidate = realpath(__DIR__ . '/../' . ltrim($urlWeb, '/\\'));
    return $candidate ?: null;
  }

  private function registrarHistorialActual(string $accion, int $productoId, string $detalles): void
  {
    // Todo el bloque en best-effort para no romper si no existe la tabla o columnas
    try {
      $usuario_id = null;
      try {
        if (class_exists('AuthMiddleware')) {
          $userData = AuthMiddleware::verificarJWT();
          $usuario_id = $userData->id ?? null;
        }
      } catch (Throwable $e) {}

      $st = $this->db->prepare(
        "INSERT INTO historial (usuario_id, accion, producto_id, detalles, created_at)
         VALUES (:u, :a, :p, :d, NOW())"
      );
      $st->execute([
        ':u' => $usuario_id,
        ':a' => $accion,
        ':p' => $productoId,
        ':d' => $detalles,
      ]);
    } catch (Throwable $e) {
      // registramos pero nunca interrumpimos el flujo de la app
      error_log('[historial omitido] ' . $e->getMessage());
    }
  }

  private function nullIfEmpty($v) {
    return ($v === '' || $v === null) ? null : $v;
  }
  private function intOrNull($v): ?int {
    if ($v === '' || $v === null) return null;
    return is_numeric($v) ? (int)$v : null;
  }
  private function floatOrNull($v): ?float {
    if ($v === '' || $v === null) return null;
    return is_numeric($v) ? (float)$v : null;
  }
}
