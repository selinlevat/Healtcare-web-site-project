<?php
declare(strict_types=1);

$host = "localhost";
$dbname = "healthcare_db";
$username = "root";
$password = "";

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Prod ortamında kullanıcıya detaylı hata göstermemek daha iyi olur.
    die("Database connection error.");
}
