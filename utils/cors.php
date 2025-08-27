<?php
$allowed = ['http://localhost:3000','http://127.0.0.1:3000'];
$o = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($o && in_array($o, $allowed, true)) {
  header("Access-Control-Allow-Origin: $o");
  header('Vary: Origin');
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
