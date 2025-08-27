<?php

class Ventas
{
    private PDO $db;
    private ?int $usuarioId;

    public function __construct(PDO $db, ?int $usuarioId = null)
    {
        $this->db        = $db;
        $this->usuarioId = $usuarioId;
    }

    /* =========================================================
     *  PRODUCTOS DISPONIBLES (buscador del módulo de ventas)
     *  - Solo trae status_stock = 'disponible'
     *  - Búsqueda por tokens en marca/modelo/color/IMEI/SKU/GB
     *  - Incluye IMEI y SKU para armar la etiqueta en el front
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
                            OR LOWER(color) LIKE {$k}
                            OR imei_1 LIKE {$k}
                            OR imei_2 LIKE {$k}
                            OR sku    LIKE {$k}
                            OR CAST(almacenamiento_gb AS CHAR) LIKE {$k})";
                $i++;
            }
            if ($parts) {
                $sql .= ' AND ' . implode(' AND ', $parts);
            }
        }

        $sql .= " ORDER BY created_at DESC, id DESC LIMIT 100";

        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, PDO::PARAM_STR);
        }
        $st->execute();

        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Normalización suave de tipos
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
     *  CREAR VENTA
     * ==================== */
    public function crearVenta(array $data): array
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            throw new Exception("Agregá al menos un producto.");
        }

        $metodo    = $data['metodo_pago'] ?? 'efectivo';
        $descuento = (float)($data['descuento'] ?? 0);
        $impuestos = (float)($data['impuestos'] ?? 0);

        // Subtotal a partir de los precios unitarios recibidos
        $subtotal = 0.0;
        foreach ($items as $it) {
            $subtotal += (float)($it['precio_unit'] ?? 0);
        }
        $total = max(0, $subtotal - $descuento + $impuestos);

        $this->db->beginTransaction();

        // 1) Cabecera
        $insVenta = $this->db->prepare(
            "INSERT INTO ventas (cliente_id, usuario_id, subtotal, descuento, impuestos, total, metodo_pago, fecha_venta)
             VALUES (:cliente_id, :usuario_id, :subtotal, :descuento, :impuestos, :total, :metodo_pago, NOW())"
        );
        $insVenta->execute([
            ':cliente_id'  => ($data['cliente_id'] ?? null) !== '' ? $data['cliente_id'] : null,
            ':usuario_id'  => $this->usuarioId,
            ':subtotal'    => $subtotal,
            ':descuento'   => $descuento,
            ':impuestos'   => $impuestos,
            ':total'       => $total,
            ':metodo_pago' => $metodo,
        ]);
        $ventaId = (int)$this->db->lastInsertId();

        // 2) Items + marcar productos
        $insItem = $this->db->prepare(
            "INSERT INTO venta_items (venta_id, producto_id, precio_unit)
             VALUES (:venta_id, :producto_id, :precio_unit)"
        );

        $updProd = $this->db->prepare(
            "UPDATE productos
               SET status_stock = 'vendido', updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status_stock = 'disponible'"
        );

        $insMov = $this->db->prepare(
            "INSERT INTO inventario_movimientos
                 (producto_id, tipo, cantidad, costo_unit, usuario_id, referencia, venta_id, notas, fecha)
             VALUES
                 (:producto_id, 'venta', 1, 0, :usuario_id, :referencia, :venta_id, :notas, NOW())"
        );

        foreach ($items as $it) {
            $pid    = (int)$it['producto_id'];
            $precio = (float)($it['precio_unit'] ?? 0);

            // Chequeo de estado actual
            $chk = $this->db->prepare("SELECT id, status_stock FROM productos WHERE id = :id LIMIT 1");
            $chk->execute([':id' => $pid]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception("Producto #{$pid} no existe.");
            }
            if ($row['status_stock'] !== 'disponible') {
                throw new Exception("Producto #{$pid} no está disponible.");
            }

            // Item
            $insItem->execute([
                ':venta_id'    => $ventaId,
                ':producto_id' => $pid,
                ':precio_unit' => $precio,
            ]);

            // Marcar vendido
            $updProd->execute([':id' => $pid]);
            if ($updProd->rowCount() === 0) {
                throw new Exception("No se pudo marcar vendido el producto #{$pid}.");
            }

            // Movimiento de inventario
            $insMov->execute([
                ':producto_id' => $pid,
                ':usuario_id'  => $this->usuarioId,
                ':referencia'  => "Venta #{$ventaId}",
                ':venta_id'    => $ventaId,
                ':notas'       => null,
            ]);
        }

        $this->db->commit();

        return [
            'venta_id'  => $ventaId,
            'subtotal'  => $subtotal,
            'descuento' => $descuento,
            'impuestos' => $impuestos,
            'total'     => $total,
        ];
    }

    /* ======================
     *  VER VENTA
     * ==================== */
    public function verVenta(int $id): array
    {
        if ($id <= 0) {
            throw new Exception("ID inválido");
        }

        $st = $this->db->prepare("SELECT * FROM ventas WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $venta = $st->fetch(PDO::FETCH_ASSOC);
        if (!$venta) {
            throw new Exception("Venta no encontrada");
        }

        $it = $this->db->prepare(
            "SELECT vi.id, vi.producto_id, vi.precio_unit,
                    p.modelo, p.almacenamiento_gb, p.color, p.sku
             FROM venta_items vi
             JOIN productos p ON p.id = vi.producto_id
             WHERE vi.venta_id = :id
             ORDER BY vi.id ASC"
        );
        $it->execute([':id' => $id]);
        $items = $it->fetchAll(PDO::FETCH_ASSOC);

        return ['venta' => $venta, 'items' => $items];
    }

    /* =========================================
     *  LISTAR VENTAS (últimas 100 para dashboard)
     * ======================================= */
    public function listarVentas(): array
    {
        $sql = "SELECT v.*,
                       (SELECT COUNT(*) FROM venta_items vi WHERE vi.venta_id = v.id) AS items_count
                FROM ventas v
                ORDER BY v.fecha_venta DESC, v.id DESC
                LIMIT 100";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
