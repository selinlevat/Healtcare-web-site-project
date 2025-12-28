<?php
session_start();
require 'db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || (strtolower($_SESSION['role'] ?? '') !== 'doctor')) {
    header("Location: login.php");
    exit;
}

$doctorId = (int)$_SESSION['user_id'];

$appointmentId = (int)($_POST['appointment_id'] ?? 0);
$action        = strtolower(trim($_POST['action'] ?? ''));

if ($appointmentId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    header("Location: doctor_dashboard.php");
    exit;
}

// Only allow changing doctor's own appointment
$stmt = $conn->prepare("SELECT id, doctor_id, status FROM appointments WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $appointmentId]);
$appt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appt || (int)$appt['doctor_id'] !== $doctorId) {
    header("Location: doctor_dashboard.php");
    exit;
}

// Only pending can be approved/rejected
$currentStatus = strtolower((string)($appt['status'] ?? ''));
if ($currentStatus !== 'pending') {
    header("Location: doctor_dashboard.php");
    exit;
}

$newStatus = ($action === 'approve') ? 'approved' : 'cancelled';

$up = $conn->prepare("UPDATE appointments SET status = :st WHERE id = :id AND doctor_id = :did");
$up->execute([
    ':st'  => $newStatus,
    ':id'  => $appointmentId,
    ':did' => $doctorId
]);

header("Location: doctor_dashboard.php");
exit;
