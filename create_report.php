<?php
session_start();
require 'db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Only doctor
if (!isset($_SESSION['user_id']) || (strtolower($_SESSION['role'] ?? '') !== 'doctor')) {
    header("Location: login.php");
    exit;
}

$doctorId   = (int)$_SESSION['user_id'];
$doctorName = $_SESSION['name'] ?? 'Doctor';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function tableExists(PDO $conn, string $table): bool {
    $sql = "SELECT 1 FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1";
    $st = $conn->prepare($sql);
    $st->execute([':t' => $table]);
    return (bool)$st->fetchColumn();
}

function getColumns(PDO $conn, string $table): array {
    $sql = "SELECT column_name FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :t";
    $st = $conn->prepare($sql);
    $st->execute([':t' => $table]);
    $cols = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $c) {
        $cols[strtolower($c)] = true;
    }
    return $cols; // lowercase map
}

function calcDaysInclusive(string $start, string $end): ?int {
    $s = strtotime($start);
    $e = strtotime($end);
    if (!$s || !$e) return null;
    if ($e < $s) return null;
    return (int)(($e - $s) / 86400) + 1;
}

$appointmentId = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
if ($appointmentId <= 0) {
    header("Location: doctor_dashboard.php");
    exit;
}

// Fetch appointment + patient
$sqlAppt = "
    SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.appointment_time, a.hospital_name,
           p.name AS patient_name
    FROM appointments a
    JOIN users p ON p.id = a.patient_id
    WHERE a.id = :aid
    LIMIT 1
";
$stmtAppt = $conn->prepare($sqlAppt);
$stmtAppt->execute([':aid' => $appointmentId]);
$appt = $stmtAppt->fetch(PDO::FETCH_ASSOC);

if (!$appt || (int)$appt['doctor_id'] !== $doctorId) {
    header("Location: doctor_dashboard.php");
    exit;
}

$patientId   = (int)$appt['patient_id'];
$patientName = $appt['patient_name'] ?? 'Patient';

// --- Get doctor info safely (department / hospital_name may or may not exist)
$usersCols = tableExists($conn, 'users') ? getColumns($conn, 'users') : [];
$hasDept   = isset($usersCols['department']);
$hasHosp   = isset($usersCols['hospital_name']);

$defaultDepartment = '';
$defaultHospital   = (string)($appt['hospital_name'] ?? '');

if ($hasDept || $hasHosp) {
    $selectParts = [];
    if ($hasDept) $selectParts[] = "department";
    if ($hasHosp) $selectParts[] = "hospital_name";

    $sqlDoc = "SELECT " . implode(", ", $selectParts) . " FROM users WHERE id = :id LIMIT 1";
    $stmtDoc = $conn->prepare($sqlDoc);
    $stmtDoc->execute([':id' => $doctorId]);
    $docRow = $stmtDoc->fetch(PDO::FETCH_ASSOC) ?: [];

    if ($hasDept) $defaultDepartment = (string)($docRow['department'] ?? '');
    if ($hasHosp) $defaultHospital   = (string)($docRow['hospital_name'] ?? $defaultHospital);
}

// Form values
$issue_date = $_POST['issue_date'] ?? date('Y-m-d');
$start_date = $_POST['start_date'] ?? '';
$end_date   = $_POST['end_date'] ?? '';
$department = $_POST['department'] ?? $defaultDepartment;
$diagnosis  = $_POST['diagnosis'] ?? '';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$issue_date || !$start_date || !$end_date || !$department) {
        $error = "Please fill in all required fields.";
    } else {
        $days = calcDaysInclusive($start_date, $end_date);
        if ($days === null) {
            $error = "Invalid date range. End date must be after or equal to start date.";
        } else {
            if (!tableExists($conn, 'reports')) {
                $error = "Database error: 'reports' table not found.";
            } else {
                $reportsCols = getColumns($conn, 'reports');

                $reportNo = "R-" . date('Ymd') . "-" . $appointmentId . "-" . $doctorId;

                // Candidate data (we will only insert columns that exist in reports table)
                $data = [
                    'report_no'   => $reportNo,
                    'report_type' => 'REST_REPORT',
                    'patient_id'  => $patientId,
                    'doctor_id'   => $doctorId,
                    'department'  => $department,
                    'diagnosis'   => $diagnosis,
                    'issue_date'  => $issue_date,
                    'start_date'  => $start_date,
                    'end_date'    => $end_date,
                    'leave_days'  => $days, // only if column exists
                    'hospital_name' => $defaultHospital, // only if column exists
                ];

                // Filter by actual columns
                $filtered = [];
                foreach ($data as $k => $v) {
                    if (isset($reportsCols[strtolower($k)])) {
                        $filtered[$k] = $v;
                    }
                }

                if (empty($filtered)) {
                    $error = "Database error: No matching columns found in 'reports' table.";
                } else {
                    $cols = array_keys($filtered);
                    $ph   = array_map(fn($c) => ":" . $c, $cols);

                    $sqlIns = "INSERT INTO reports (" . implode(", ", $cols) . ")
                               VALUES (" . implode(", ", $ph) . ")";
                    $stmtIns = $conn->prepare($sqlIns);
                    $stmtIns->execute(array_combine($ph, array_values($filtered)));

                    // Optional: mark appointment completed after report creation
                    // $conn->prepare("UPDATE appointments SET status='completed' WHERE id=:id AND doctor_id=:did")
                    //      ->execute([':id'=>$appointmentId, ':did'=>$doctorId]);

                    $success = "Report created successfully.";
                }
            }
        }
    }
}

$backFallback = 'doctor_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Report</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --primary:#2563eb;
            --primary-soft:#e3f2fd;
            --danger:#ef4444;
            --success:#16a34a;
            --shadow:0 14px 30px rgba(15,23,42,0.12);
            --radius:18px;
        }
        *{box-sizing:border-box;}
        body{
            margin:0;
            font-family:Inter, system-ui, -apple-system, 'Segoe UI', Arial, sans-serif;
            background:radial-gradient(circle at top left,#e3f2fd 0,#f4f6f9 40%,#f4f6f9 100%);
            color:#111827;
        }
        .navbar{
            background:#ffffffaa;
            backdrop-filter: blur(10px);
            border-bottom:1px solid rgba(148,163,184,0.35);
            padding:10px 32px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            position:sticky; top:0; z-index:20;
        }
        .navbar-left{font-weight:900; letter-spacing:.03em; font-size:18px; color:#0f172a;}
        .navbar-right{
            display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end;
            font-size:14px;
        }
        .nav-btn{
            display:inline-flex; align-items:center; gap:8px;
            padding:8px 12px; border-radius:999px;
            text-decoration:none; font-weight:800;
            border:1px solid rgba(37,99,235,0.25);
            background: rgba(37,99,235,0.08);
            color:#2563eb;
            transition: transform .12s ease, filter .12s ease;
        }
        .nav-btn:hover{ transform: translateY(-1px); filter:brightness(1.02); }
        .nav-btn.logout{
            border:1px solid rgba(220,38,38,0.25);
            background: rgba(220,38,38,0.08);
            color:#dc2626;
        }

        .page{max-width:1100px; margin:32px auto 60px; padding:0 18px;}
        .card{
            background:#fff;
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            border:1px solid rgba(148,163,184,0.25);
            padding:26px 30px 28px;
        }

        .title{ margin:0 0 6px; font-size:26px; display:flex; align-items:center; gap:10px; }
        .title .icon{
            width:32px;height:32px;border-radius:999px;
            background:var(--primary-soft);
            display:inline-flex;align-items:center;justify-content:center;
        }
        .sub{ margin:0 0 18px; color:#6b7280; font-size:14px; }

        .info{
            background:#f9fafb;
            border:1px solid #e5e7eb;
            border-radius:14px;
            padding:12px 14px;
            font-size:13px;
            color:#374151;
            margin-bottom:16px;
            line-height:1.45;
        }

        .row{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        label{ display:block; font-size:13px; font-weight:800; color:#374151; margin:12px 0 6px; }
        input, textarea{
            width:100%;
            border:1px solid #d1d5db;
            border-radius:12px;
            padding:10px 12px;
            font-size:14px;
            background:#fff;
        }
        textarea{ min-height:90px; resize:vertical; }

        .btn{
            margin-top:16px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:11px 18px;
            border-radius:999px;
            border:none;
            cursor:pointer;
            font-weight:900;
            background:linear-gradient(135deg,#2563eb,#1d4ed8);
            color:#fff;
            box-shadow:0 10px 24px rgba(37,99,235,0.35);
        }

        .alert{
            border-radius:12px;
            padding:10px 12px;
            font-size:13px;
            margin-bottom:12px;
        }
        .alert-error{ background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.35); color:#b91c1c; }
        .alert-success{ background:rgba(22,163,74,0.08); border:1px solid rgba(22,163,74,0.35); color:#166534; }

        @media (max-width:800px){
            .row{ grid-template-columns:1fr; }
            .card{ padding:20px 16px; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-left">Healthcare Record System</div>
    <div class="navbar-right">
        <span><?php echo h($doctorName); ?> (Doctor)</span>

        <a class="nav-btn"
           href="<?php echo h($backFallback); ?>"
           onclick="if (window.history.length > 1) { window.history.back(); return false; }">
            ← Back
        </a>

        <a class="nav-btn logout" href="logout.php">Log out</a>
    </div>
</div>

<div class="page">
    <div class="card">
        <h1 class="title"><span class="icon">📄</span>Create Rest Report</h1>
        <p class="sub">Create a rest report for the selected appointment.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>

        <div class="info">
            <b>Appointment:</b> <?php echo h($appt['appointment_date']); ?> <?php echo h(substr($appt['appointment_time'],0,5)); ?><br>
            <b>Patient:</b> <?php echo h($patientName); ?><br>
            <b>Hospital:</b> <?php echo h($defaultHospital ?: 'Not specified'); ?>
        </div>

        <form method="post">
            <div class="row">
                <div>
                    <label for="issue_date">Issue Date *</label>
                    <input type="date" id="issue_date" name="issue_date" value="<?php echo h($issue_date); ?>">
                </div>
                <div>
                    <label for="department">Department / Clinic *</label>
                    <input type="text" id="department" name="department" value="<?php echo h($department); ?>" placeholder="e.g., Cardiology">
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="start_date">Report Start Date *</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo h($start_date); ?>">
                </div>
                <div>
                    <label for="end_date">Report End Date *</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo h($end_date); ?>">
                </div>
            </div>

            <label for="diagnosis">Diagnosis</label>
            <textarea id="diagnosis" name="diagnosis" placeholder="Write diagnosis..."><?php echo h($diagnosis); ?></textarea>

            <button class="btn" type="submit">Save Report</button>
        </form>
    </div>
</div>

</body>
</html>
