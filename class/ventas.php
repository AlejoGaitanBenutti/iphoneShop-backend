<?php

class Ventas
{
    private PDO $db;
    private ?int $usuarioId;

    public function __construct(PDO $db, ?int $usuarioId = null)
    {
        $this->db        = $db;
        $this->usuarioId = $usuarioId;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /* =========================================================
     *  PRODUCTOS DISPONIBLES (para buscador del módulo ventas)
     * ======================================================= */
    public function productosDisponibles(string $q = ''): array
    {
        $sql = "SELECT
                    id, sku, marca, modelo, almacenamiento_gb, color,
                    imei_1, imei_2,
                    precio_lista, costo,
                    bateria_salud, bateria_ciclos,
                    status_stock, created_at
                FROM productos
                WHERE status_stock = 'disponible'";

        $params = [];
        $q = trim($q ?? '');
        if ($q !== '') {
            $tokens = preg_split('/\s+/', mb_strtolower($q, 'UTF-8'));
            $parts  = [];
            $i = 0;
            foreach ($tokens as $t) {
                if ($t === '') continue;
                $k = ":t{$i}";
                $params[$k] = "%{$t}%";
                $parts[] = "(LOWER(marca) LIKE {$k}
                            OR LOWER(modelo) LIKE {$k}
                            OR LOWER(color)  LIKE {$k}
                            OR imei_1 LIKE {$k}
                            OR imei_2 LIKE {$k}
                            OR sku    LIKE {$k}
                            OR CAST(almacenamiento_gb AS CHAR) LIKE {$k})";
                $i++;
            }
            if ($parts) $sql .= ' AND ' . implode(' AND ', $parts);
        }

        $sql .= " ORDER BY created_at DESC, id DESC LIMIT 100";

        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v, PDO::PARAM_STR);
        $st->execute();
        $rows = $st->fetchAll();

        foreach ($rows as &$r) {
            $r['id']                = (int)$r['id'];
            $r['almacenamiento_gb'] = isset($r['almacenamiento_gb']) ? (int)$r['almacenamiento_gb'] : null;
            $r['bateria_salud']     = isset($r['bateria_salud']) ? (int)$r['bateria_salud'] : null;
            $r['bateria_ciclos']    = isset($r['bateria_ciclos']) ? (int)$r['bateria_ciclos'] : null;
            $r['precio_lista']      = (float)$r['precio_lista'];
            $r['costo']             = (float)$r['costo'];
        }
        unset($r);

        return $rows;
    }

    /* ======================
     *  CREAR VENTA (completo)
     * ==================== */
    public function crearVenta(array $data): array
    {
        // ---------- Cliente (opcional) ----------
        $clienteId = null;
        $compradorSnapshot = null;

        if (!empty($data['cliente']) && is_array($data['cliente'])) {
            $c = $data['cliente'];
            $nombre    = trim((string)($c['nombre']     ?? ''));
            $dni_cuit  = trim((string)($c['documento']  ?? ''));
            $telefono  = trim((string)($c['telefono']   ?? ''));
            $email     = trim((string)($c['email']      ?? ''));
            $direccion = trim((string)($c['direccion']  ?? ''));

            $compradorSnapshot = json_encode([
                'nombre'    => $nombre,
                'dni_cuit'  => $dni_cuit,
                'telefono'  => $telefono,
                'email'     => $email,
                'direccion' => $direccion,
            ], JSON_UNESCAPED_UNICODE);

            // buscar existente
            $row = null;
            if ($dni_cuit !== '') {
                $st = $this->db->prepare("SELECT id FROM clientes WHERE dni_cuit = :dni LIMIT 1");
                $st->execute([':dni' => $dni_cuit]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
            }
            if (!$row && $email !== '') {
                $st = $this->db->prepare("SELECT id FROM clientes WHERE email = :e LIMIT 1");
                $st->execute([':e' => $email]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
            }
            if (!$row && $telefono !== '') {
                $st = $this->db->prepare("SELECT id FROM clientes WHERE telefono = :t LIMIT 1");
                $st->execute([':t' => $telefono]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
            }

            if ($row) {
                $clienteId = (int)$row['id'];
                $upd = $this->db->prepare(
                    "UPDATE clientes
                       SET nombre    = COALESCE(NULLIF(:nombre,''), nombre),
                           telefono  = COALESCE(NULLIF(:tel,''), telefono),
                           email     = COALESCE(NULLIF(:email,''), email),
                           direccion = COALESCE(NULLIF(:dir,''), direccion)
                     WHERE id = :id"
                );
                $upd->execute([
                    ':nombre'=>$nombre, ':tel'=>$telefono, ':email'=>$email, ':dir'=>$direccion, ':id'=>$clienteId
                ]);
            } else {
                $ins = $this->db->prepare(
                    "INSERT INTO clientes (nombre, dni_cuit, telefono, email, direccion, created_at)
                     VALUES (:n,:d,:t,:e,:dir,NOW())"
                );
                $ins->execute([
                    ':n'=>$nombre ?: null, ':d'=>$dni_cuit ?: null, ':t'=>$telefono ?: null,
                    ':e'=>$email ?: null, ':dir'=>$direccion ?: null
                ]);
                $clienteId = (int)$this->db->lastInsertId();
            }
        }
        if (!empty($data['cliente_id'])) $clienteId = (int)$data['cliente_id'];

        // ---------- Validaciones / totales ----------
        $items = $data['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            throw new Exception("Agregá al menos un producto.");
        }

        $descuento = (float)($data['descuento'] ?? 0);
        $impuestos = (float)($data['impuestos'] ?? 0);

        $subtotal = 0.0;
        foreach ($items as $it) $subtotal += (float)($it['precio_unit'] ?? 0);
        $total = max(0, $subtotal - $descuento + $impuestos);

        $this->db->beginTransaction();
        try {
            // ---------- Cabecera de venta ----------
            $insVenta = $this->db->prepare(
                "INSERT INTO ventas
                   (cliente_id, usuario_id, subtotal, descuento, impuestos, total, comprador_snapshot, fecha_venta, created_at, updated_at)
                 VALUES
                   (:cliente_id, :usuario_id, :subtotal, :descuento, :impuestos, :total, :snapshot, NOW(), NOW(), NOW())"
            );
            $insVenta->execute([
                ':cliente_id'=>$clienteId ?: null,
                ':usuario_id'=>$this->usuarioId,
                ':subtotal'=>$subtotal, ':descuento'=>$descuento, ':impuestos'=>$impuestos, ':total'=>$total,
                ':snapshot'=>$compradorSnapshot
            ]);
            $ventaId = (int)$this->db->lastInsertId();

            // ---------- Items + marcar vendidos + movimiento ----------
            $insItem = $this->db->prepare(
                "INSERT INTO venta_items (venta_id, producto_id, precio_unit)
                 VALUES (:v,:p,:pu)"
            );
            $updProd = $this->db->prepare(
                "UPDATE productos
                   SET status_stock = 'vendido', updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND status_stock = 'disponible'"
            );
            $insMovVenta = $this->db->prepare(
                "INSERT INTO inventario_movimientos
                     (producto_id, tipo, cantidad, costo_unit, usuario_id, referencia, venta_id, notas, fecha)
                 VALUES
                     (:p,'venta',1,0,:u,:ref,:v,null,NOW())"
            );

            foreach ($items as $it) {
                $pid    = (int)$it['producto_id'];
                $precio = (float)($it['precio_unit'] ?? 0);

                $chk = $this->db->prepare("SELECT id, status_stock FROM productos WHERE id = :id LIMIT 1");
                $chk->execute([':id'=>$pid]);
                $row = $chk->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new Exception("Producto #{$pid} no existe.");
                if ($row['status_stock'] !== 'disponible') throw new Exception("Producto #{$pid} no está disponible.");

                $insItem->execute([':v'=>$ventaId, ':p'=>$pid, ':pu'=>$precio]);

                $updProd->execute([':id'=>$pid]);
                if ($updProd->rowCount() === 0) {
                    throw new Exception("No se pudo marcar vendido el producto #{$pid}.");
                }

                $insMovVenta->execute([
                    ':p'=>$pid,
                    ':u'=>$this->usuarioId,
                    ':ref'=>"Venta #{$ventaId}",
                    ':v'=>$ventaId
                ]);
            }

            // ---------- Pagos (monto se guarda en USD) ----------
            if (!empty($data['pagos']) && is_array($data['pagos'])) {
                $insPago = $this->db->prepare(
                    "INSERT INTO pagos (venta_id, monto, metodo, referencia, fecha_pago)
                     VALUES (:v,:m,:met,:ref,NOW())"
                );

                foreach ($data['pagos'] as $p) {
                    $met   = (string)($p['metodo'] ?? 'efectivo');
                    $mon   = (string)($p['moneda'] ?? 'USD');
                    $monto = (float)($p['monto']  ?? 0);
                    $tasa  = isset($p['tasa']) ? (float)$p['tasa'] : null;

                    if ($monto <= 0) continue;

                    $montoUSD = ($mon === 'ARS')
                        ? ($tasa && $tasa > 0 ? $monto / $tasa : 0)
                        : $monto;

                    $ref = ($mon === 'ARS' && $tasa)
                        ? "ARS " . number_format($monto,2,'.','') . " @ " . number_format($tasa,2,'.','') .
                          " -> USD " . number_format($montoUSD,2,'.','')
                        : null;

                    $insPago->execute([
                        ':v'=>$ventaId, ':m'=>$montoUSD, ':met'=>$met, ':ref'=>$ref
                    ]);
                }
            }

          // ---------- Permuta (opcional) ----------
if (!empty($data['trade_in']) && is_array($data['trade_in'])) {
    $t = $data['trade_in'];

    $modelo = trim((string)($t['modelo'] ?? ''));
    $alm    = isset($t['almacenamiento_gb']) ? (int)$t['almacenamiento_gb'] : null;
    $color  = trim((string)($t['color'] ?? ''));
    $estado = (string)($t['condicion'] ?? 'usado');
    $imei1  = trim((string)($t['imei_1'] ?? '')) ?: null;
    $imei2  = trim((string)($t['imei_2'] ?? '')) ?: null;
    $valor  = (float)($t['valor_toma'] ?? 0);
    $notas  = trim((string)($t['notas'] ?? ''));

    // batería_salud (0..100) o null
    $bateriaSalud = null;
    if (isset($t['bateria_salud']) && $t['bateria_salud'] !== '' && $t['bateria_salud'] !== null) {
        $bateriaSalud = max(0, min(100, (int)$t['bateria_salud']));
    }

    // Si completaron IMEI, validar que NO exista en otro producto
    $conds = [];
    $bind  = [];
    if ($imei1) { $conds[] = "(imei_1 = :i1 OR imei_2 = :i1)"; $bind[':i1'] = $imei1; }
    if ($imei2) { $conds[] = "(imei_1 = :i2 OR imei_2 = :i2)"; $bind[':i2'] = $imei2; }
    if ($conds) {
        $sql = "SELECT id FROM productos WHERE " . implode(" OR ", $conds) . " LIMIT 1";
        $ch  = $this->db->prepare($sql);
        foreach ($bind as $k => $v) $ch->bindValue($k, $v);
        $ch->execute();
        $dup = $ch->fetch(PDO::FETCH_ASSOC);
        if ($dup) {
            throw new Exception("El IMEI ingresado ya existe en inventario (producto #{$dup['id']}). Cambiá el IMEI o dejalo vacío si no corresponde.");
        }
    }

    // Insertar producto de permuta (entra al stock)
    if ($modelo !== '' || $valor > 0 || $imei1 || $imei2) {
        $insP = $this->db->prepare(
            "INSERT INTO productos
              (marca, modelo, almacenamiento_gb, bateria_salud, bateria_ciclos, color,
               imei_1, imei_2, estado, origen, costo, precio_lista, proveedor_id,
               garantia_meses, notas, status_stock, created_at, updated_at)
             VALUES
              ('Apple', :modelo, :alm, :bat_salud, NULL, :color,
               :i1, :i2, :estado, 'permuta', :costo, :precio_lista, NULL,
               0, :notas, 'disponible', NOW(), NOW())"
        );

        // 👇 IMPORTANTE:
        // - costo       = valor de toma (para tu costo contable)
        // - precio_lista= 0.00 (evita el NOT NULL). Si preferís, podés poner = :costo
        $insP->execute([
            ':modelo'       => $modelo ?: null,
            ':alm'          => $alm ?: null,
            ':bat_salud'    => $bateriaSalud,           // <-- guarda batería salud
            ':color'        => $color ?: null,
            ':i1'           => $imei1,
            ':i2'           => $imei2,
            ':estado'       => $estado ?: 'usado',
            ':costo'        => $valor,
            ':precio_lista' => 0.00,                    // <-- evita el error de NOT NULL
            // ':precio_lista' => $valor,                // (opción alternativa si querés)
            ':notas'        => $notas ?: null,
        ]);

        $nuevoProdId = (int)$this->db->lastInsertId();

        // Movimiento de inventario por permuta (ingresa stock)
        $insMovPerm = $this->db->prepare(
            "INSERT INTO inventario_movimientos
                 (producto_id, tipo, cantidad, costo_unit, usuario_id, referencia, venta_id, notas, fecha)
             VALUES
                 (:p,'permuta_ingreso',1,:costo,:u,:ref,:v,:notas,NOW())"
        );
        $insMovPerm->execute([
            ':p'     => $nuevoProdId,
            ':costo' => $valor,
            ':u'     => $this->usuarioId,
            ':ref'   => "Permuta Venta #{$ventaId}",
            ':v'     => $ventaId,
            ':notas' => $notas ?: null
        ]);
    }
}


            $this->db->commit();

            return [
                'venta_id'  => $ventaId,
                'subtotal'  => $subtotal,
                'descuento' => $descuento,
                'impuestos' => $impuestos,
                'total'     => $total,
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /* ======================
     *  VER VENTA
     * ==================== */
    public function verVenta(int $id): array
    {
        if ($id <= 0) throw new Exception("ID inválido");

        $st = $this->db->prepare("SELECT * FROM ventas WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $venta = $st->fetch();
        if (!$venta) throw new Exception("Venta no encontrada");

        $it = $this->db->prepare(
            "SELECT vi.id, vi.producto_id, vi.precio_unit,
                    p.modelo, p.almacenamiento_gb, p.color, p.sku
             FROM venta_items vi
             JOIN productos p ON p.id = vi.producto_id
             WHERE vi.venta_id = :id
             ORDER BY vi.id ASC"
        );
        $it->execute([':id' => $id]);
        $items = $it->fetchAll();

        return ['venta' => $venta, 'items' => $items];
    }

    /* =========================================
     *  LISTAR VENTAS (últimas 100)
     * ======================================= */
    public function listarVentas(): array
    {
        $sql = "SELECT v.*,
                       (SELECT COUNT(*) FROM venta_items vi WHERE vi.venta_id = v.id) AS items_count
                FROM ventas v
                ORDER BY v.fecha_venta DESC, v.id DESC
                LIMIT 100";
        return $this->db->query($sql)->fetchAll();
    }

    private function nullIfEmpty($v) {
        return ($v === '' || $v === null) ? null : $v;
    }
}
