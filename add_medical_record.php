<?php
session_start();
require 'db.php';

// Only doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit;
}

$doctorId = (int)$_SESSION['user_id'];
$doctorName = $_SESSION['name'] ?? 'Doctor';

$appointmentId = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
if ($appointmentId <= 0) {
    die("Invalid appointment.");
}

// ✅ Get appointment + patient, and ensure this appointment belongs to this doctor
$sqlAppt = "
    SELECT a.id, a.patient_id, a.appointment_date, a.appointment_time, a.status,
           p.name AS patient_name
    FROM appointments a
    JOIN users p ON p.id = a.patient_id
    WHERE a.id = :aid AND a.doctor_id = :did
    LIMIT 1
";
$stmtAppt = $conn->prepare($sqlAppt);
$stmtAppt->execute([':aid' => $appointmentId, ':did' => $doctorId]);
$appt = $stmtAppt->fetch(PDO::FETCH_ASSOC);

if (!$appt) {
    die("Invalid patient / appointment (not found or not assigned to you).");
}

$patientId = (int)$appt['patient_id'];
$patientName = $appt['patient_name'] ?? 'Patient';

$errors = [];
$success = null;

// ✅ Back button fallback (if history doesn't exist)
$backFallback = 'doctor_dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnosis   = trim($_POST['diagnosis'] ?? '');
    $medications = trim($_POST['medications'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    if ($diagnosis === '' && $medications === '' && $notes === '') {
        $errors[] = "Please fill at least one field (diagnosis / medications / notes).";
    }

    if (empty($errors)) {
        $sqlIns = "
            INSERT INTO medical_records (patient_id, doctor_id, diagnosis, medications, notes, created_at)
            VALUES (:pid, :did, :diag, :meds, :notes, NOW())
        ";
        $stmtIns = $conn->prepare($sqlIns);
        $stmtIns->execute([
            ':pid'   => $patientId,
            ':did'   => $doctorId,
            ':diag'  => $diagnosis,
            ':meds'  => $medications,
            ':notes' => $notes
        ]);

        // Redirect after save
        header("Location: doctor_dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Medical Record</title>
  <style>
    body{font-family:Arial,sans-serif;background:#f4f6f9;margin:0}
    .navbar{background:#37474f;color:#fff;padding:10px 18px;display:flex;justify-content:space-between;align-items:center}
    .navbar a{color:#fff;text-decoration:none;margin-left:12px}
    .page{max-width:820px;margin:28px auto;background:#fff;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.1);padding:20px 22px}
    label{font-weight:700;display:block;margin-top:12px;margin-bottom:6px}
    input,textarea{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;box-sizing:border-box}
    textarea{min-height:90px;resize:vertical}
    .btn{margin-top:16px;background:#1976d2;color:#fff;border:0;padding:10px 18px;border-radius:8px;cursor:pointer;font-weight:700}
    .err{background:#ffebee;color:#c62828;padding:10px 12px;border-radius:8px;margin-bottom:10px}
    .ok{background:#e8f5e9;color:#2e7d32;padding:10px 12px;border-radius:8px;margin-bottom:10px}
    .meta{color:#555;font-size:13px;margin-top:6px}
  </style>
</head>
<body>

<div class="navbar">
  <div>Healthcare Record System</div>
  <div>
    Dr. <?php echo htmlspecialchars($doctorName); ?>

    <!-- ✅ BACK (history varsa geri, yoksa dashboard'a) -->
    <a href="<?php echo htmlspecialchars($backFallback); ?>"
       onclick="if (window.history.length > 1) { window.history.back(); return false; }">
      Back
    </a>

    <a href="logout.php">Log out</a>
  </div>
</div>

<div class="page">
  <h2>Add Medical Record</h2>
  <div class="meta">
    Patient: <strong><?php echo htmlspecialchars($patientName); ?></strong><br>
    Appointment: <?php echo htmlspecialchars($appt['appointment_date'] . ' ' . substr($appt['appointment_time'],0,5)); ?>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="err">
      <?php foreach($errors as $e) echo htmlspecialchars($e)."<br>"; ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="ok"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <form method="post">
    <label>Diagnosis</label>
    <textarea name="diagnosis" placeholder="Type diagnosis..."></textarea>

    <label>Medications</label>
    <textarea name="medications" placeholder="Type medications..."></textarea>

    <label>Notes</label>
    <textarea name="notes" placeholder="Type notes..."></textarea>

    <button class="btn" type="submit">Save</button>
  </form>
</div>

</body>
</html>
