<?php
declare(strict_types=1);

// Railway / prod ortamında env değişkenlerinden oku (öncelik sırası ile)
$host     = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: 'localhost';
$dbname   = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'healthcare_db';
$username = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root';
$password = getenv('DB_PASS') ?: getenv('MYSQLPASSWORD') ?: '';
$port     = getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: '3306';

try {
    // Port'u da ekledik
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Prod ortamında detay göstermiyoruz
    die("Database connection error.");
}
