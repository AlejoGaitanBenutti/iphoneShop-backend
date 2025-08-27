<?php
// class/Inventario.php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

final class Inventario
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo instanceof PDO) {
            $this->pdo = $pdo;
        } else {
            $db = new Database();
            $this->pdo = $db->getConnection();
        }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** Lista con filtros + paginación + KPIs (solo lectura) */
    public function listar(array $q): array
    {
        $where  = ['1=1'];
        $bind   = [];

        // búsqueda libre
        if (!empty($q['q'])) {
            $where[]      = '(p.modelo LIKE :q OR p.color LIKE :q OR p.sku LIKE :q)';
            $bind[':q']   = '%' . $q['q'] . '%';
        }

        if (!empty($q['modelo']) && $q['modelo'] !== 'all') {
            $where[] = 'p.modelo = :modelo';
            $bind[':modelo'] = $q['modelo'];
        }
        if (!empty($q['color']) && $q['color'] !== 'all') {
            $where[] = 'p.color = :color';
            $bind[':color'] = $q['color'];
        }
        if (!empty($q['almacenamiento_gb']) && $q['almacenamiento_gb'] !== 'all') {
            $where[] = 'p.almacenamiento_gb = :alm';
            $bind[':alm'] = (int)$q['almacenamiento_gb'];
        }
        if (!empty($q['estado']) && $q['estado'] !== 'all') {
            $where[] = 'p.estado = :estado';
            $bind[':estado'] = $q['estado'];
        }
        if (isset($q['min_bateria']) && $q['min_bateria'] !== '') {
            $where[] = 'p.bateria_salud >= :minbat';
            $bind[':minbat'] = (int)$q['min_bateria'];
        }

        // por defecto solo disponibles
        if (!empty($q['status_stock'])) {
            $where[] = 'p.status_stock = :stock';
            $bind[':stock'] = $q['status_stock'];
        } else {
            $where[] = "p.status_stock = 'disponible'";
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $limit  = min(100, max(1, (int)($q['limit']  ?? 24)));
        $offset = max(0, (int)($q['offset'] ?? 0));
        $order  = 'p.updated_at DESC';

        // imagen principal
        $imgExpr = '(SELECT url FROM productos_imagenes pi
                    WHERE pi.producto_id = p.id
                    ORDER BY pi.es_principal DESC, pi.id ASC
                    LIMIT 1) AS imagen_url';

        // total
        $stCount = $this->pdo->prepare("SELECT COUNT(*) FROM productos p $whereSql");
        foreach ($bind as $k => $v) $stCount->bindValue($k, $v);
        $stCount->execute();
        $total = (int)$stCount->fetchColumn();

        // KPIs que usa el front
        $stKpi = $this->pdo->prepare("
            SELECT
              COUNT(*)                                                   AS total,
              COALESCE(SUM(p.precio_lista), 0)                           AS valor_venta,
              COALESCE(SUM(p.costo), 0)                                  AS valor_costo,
              COALESCE(SUM(CASE WHEN p.estado = 'nuevo' THEN 1 ELSE 0 END), 0) AS nuevos,
              COALESCE(SUM(CASE WHEN p.estado = 'usado' THEN 1 ELSE 0 END), 0) AS usados
            FROM productos p
            $whereSql
        ");
        foreach ($bind as $k => $v) $stKpi->bindValue($k, $v);
        $stKpi->execute();
        $k = $stKpi->fetch() ?: ['total'=>0,'valor_venta'=>0,'valor_costo'=>0,'nuevos'=>0,'usados'=>0];

        // items
        $sql = "SELECT p.*, $imgExpr
                FROM productos p
                $whereSql
                ORDER BY $order
                LIMIT :limit OFFSET :offset";
        $st = $this->pdo->prepare($sql);
        foreach ($bind as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();
        $items = $st->fetchAll();

        // normalización ligera de tipos numéricos
        foreach ($items as &$r) {
            if (isset($r['almacenamiento_gb'])) $r['almacenamiento_gb'] = (int)$r['almacenamiento_gb'];
            if (isset($r['bateria_salud']))     $r['bateria_salud']     = (int)$r['bateria_salud'];
            if (isset($r['bateria_ciclos']))    $r['bateria_ciclos']    = (int)$r['bateria_ciclos'];
            if (isset($r['costo']))             $r['costo']             = (float)$r['costo'];
            if (isset($r['precio_lista']))      $r['precio_lista']      = (float)$r['precio_lista'];
        }
        unset($r);

        return [
            'items' => $items,
            'meta'  => [
                'total'  => $total,
                'limit'  => $limit,
                'offset' => $offset,
                'kpis'   => [
                    'total'       => (int)$k['total'],
                    'valor_venta' => (float)$k['valor_venta'],
                    'valor_costo' => (float)$k['valor_costo'],
                    'nuevos'      => (int)$k['nuevos'],
                    'usados'      => (int)$k['usados'],
                ],
            ],
        ];
    }

    /** Obtener un producto (lectura simple con imagen principal) */
    public function obtener(int $id): ?array
    {
        $imgExpr = '(SELECT url FROM productos_imagenes pi
                     WHERE pi.producto_id = p.id
                     ORDER BY pi.es_principal DESC, pi.id ASC
                     LIMIT 1) AS imagen_url';
        $st = $this->pdo->prepare("SELECT p.*, $imgExpr FROM productos p WHERE p.id = :id");
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }
}
