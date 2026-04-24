<?php

require_once __DIR__ . '/../vendor/autoload.php';

if(file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

$host   = $_ENV['DB_HOST']  ?? getenv('DB_HOST')  ?? 'localhost';
$dbname = $_ENV['DB_NAME']  ?? getenv('DB_NAME')  ?? 'ecommerce';
$user   = $_ENV['DB_USER']  ?? getenv('DB_USER')  ?? 'root';
$pass   = $_ENV['DB_PASS']  ?? getenv('DB_PASS')  ?? '';

define('BASE_URL', $_ENV['BASE_URL'] ?? getenv('BASE_URL') ?? '/Ecommerce_site/');
try {
    $pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . 
    ';dbname=' . getenv('DB_NAME') . 
    ';port=' . getenv('DB_PORT') . 
    ';charset=utf8mb4',
    getenv('DB_USER'),
    getenv('DB_PASS')
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Conexiune esuata: " . $e->getMessage());
}
?>