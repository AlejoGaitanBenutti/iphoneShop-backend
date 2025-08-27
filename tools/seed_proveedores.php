<?php
// tools/seed_proveedores.php
require_once __DIR__ . '/../utils/init.php';
require_once __DIR__ . '/../class/conexion.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $db = (new Database())->getConnection();

  $sql = "INSERT INTO proveedores (razon_social, cuit, telefono, email, direccion, notas)
          VALUES (:rs, :cuit, :tel, :email, :dir, :notas)";

  $stmt = $db->prepare($sql);

  $data = [
    ['Proveedor 1', '20-11111111-1', '1111-1111', 'prov1@correo.com', 'Dirección 1', null],
    ['Proveedor 2', '20-22222222-2', '2222-2222', 'prov2@correo.com', 'Dirección 2', null],
  ];

  $ids = [];
  foreach ($data as [$rs, $cuit, $tel, $email, $dir, $notas]) {
    $stmt->execute([
      ':rs' => $rs,
      ':cuit' => $cuit,
      ':tel' => $tel,
      ':email' => $email,
      ':dir' => $dir,
      ':notas' => $notas,
    ]);
    $ids[] = (int)$db->lastInsertId();
  }

  echo json_encode(['ok' => true, 'inserted_ids' => $ids]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
