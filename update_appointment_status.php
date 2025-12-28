<?php
session_start();
require 'db.php';

// Sadece doktorlar kullanabilsin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: login.php");
    exit;
}

$doctorId = $_SESSION['user_id'];
$appointmentId = $_GET['id'] ?? null;
$newStatus = $_GET['status'] ?? null;

$allowedStatuses = ['pending', 'approved', 'completed', 'cancelled'];

if (!$appointmentId || !in_array($newStatus, $allowedStatuses)) {
    header("Location: doctor_dashboard.php?msg=" . urlencode("Hata: Geçersiz istek."));
    exit;
}

// Bu randevu gerçekten bu doktora mı ait, önce onu kontrol et
$sqlCheck = "SELECT * FROM appointments WHERE id = :id AND doctor_id = :doctor_id";
$stmtCheck = $conn->prepare($sqlCheck);
$stmtCheck->execute([
    ':id' => $appointmentId,
    ':doctor_id' => $doctorId
]);
$appointment = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    header("Location: doctor_dashboard.php?msg=" . urlencode("Hata: Bu randevu size ait değil."));
    exit;
}

// Güncelle
$sqlUpdate = "UPDATE appointments SET status = :status WHERE id = :id";
$stmtUpdate = $conn->prepare($sqlUpdate);
$stmtUpdate->execute([
    ':status' => $newStatus,
    ':id'     => $appointmentId
]);

header("Location: doctor_dashboard.php?msg=" . urlencode("Randevu durumu güncellendi."));
exit;
