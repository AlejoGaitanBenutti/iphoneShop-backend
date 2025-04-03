<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);