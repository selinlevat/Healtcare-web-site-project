<?php
session_start();
require 'db.php';

// Sadece hasta
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patientId = $_SESSION['user_id'];
$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($appointmentId <= 0) {
    die("Geçersiz randevu.");
}

try {
    // Bu randevu hastaya mı ait kontrol et
    $sqlCheck = "
        SELECT id
        FROM appointments
        WHERE id = :id AND patient_id = :pid
        LIMIT 1
    ";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->execute([':id' => $appointmentId, ':pid' => $patientId]);
    $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        die("Bu randevu bulunamadı veya size ait değil.");
    }

    // İptal et
    $sqlCancel = "
        UPDATE appointments
        SET status = 'cancelled'
        WHERE id = :id AND patient_id = :pid
    ";
    $stmtCancel = $conn->prepare($sqlCancel);
    $stmtCancel->execute([':id' => $appointmentId, ':pid' => $patientId]);

    header("Location: patient_dashboard.php");
    exit;

} catch (PDOException $e) {
    die("Randevu iptal edilirken hata oluştu: " . $e->getMessage());
}
