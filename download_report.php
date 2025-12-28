<?php
session_start();
require 'db.php';

// Only patient
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header('Location: login.php');
    exit;
}

$patientId = (int)$_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("Invalid request.");
}

// report belongs to patient?
$sql = "SELECT pdf_path FROM reports WHERE id = :id AND patient_id = :pid LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $id, ':pid' => $patientId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['pdf_path'])) {
    die("PDF not found.");
}

$pdfPath = $row['pdf_path'];

// Güvenlik: sadece belirlediğin klasörden servis etmek istersen kontrol ekle
// Örn: $baseDir = __DIR__ . '/uploads/reports/';
// $real = realpath($baseDir . basename($pdfPath));

if (!file_exists($pdfPath)) {
    die("File does not exist on server.");
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="report_'.$id.'.pdf"');
header('Content-Length: ' . filesize($pdfPath));
readfile($pdfPath);
exit;
