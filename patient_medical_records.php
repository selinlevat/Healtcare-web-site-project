<?php
ob_start();
session_start();
require 'db.php';

// Only patient
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $role !== 'patient') {
    header("Location: login.php");
    exit;
}

$patientId   = (int)$_SESSION['user_id'];
$patientName = $_SESSION['name'] ?? 'Patient';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Fetch medical records for patient
$sql = "
    SELECT
        mr.id,
        mr.diagnosis,
        mr.medications,
        mr.notes,
        mr.created_at,
        d.name AS doctor_name
    FROM medical_records mr
    JOIN users d ON d.id = mr.doctor_id
    WHERE mr.patient_id = :pid
    ORDER BY mr.created_at DESC, mr.id DESC
    LIMIT 200
";
$stmt = $conn->prepare($sql);
$stmt->execute([':pid' => $patientId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$backFallback = 'patient_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Medical Records</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root{
            --primary:#2563eb;
            --muted:#6b7280;
            --border:rgba(148,163,184,0.25);
            --shadow:0 14px 30px rgba(15,23,42,0.10);
        }
        *{ box-sizing:border-box; }
        body{
            margin:0;
            font-family:Inter, system-ui, -apple-system, Segoe UI, Arial, sans-serif;
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
        .navbar-left{ font-weight:800; font-size:18px; color:#0f172a; }
        .navbar-right{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            font-size:14px;
            justify-content:flex-end;
        }
        .nav-btn{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 12px;
            border-radius:999px;
            text-decoration:none;
            font-weight:800;
            border:1px solid rgba(37,99,235,0.25);
            background: rgba(37,99,235,0.08);
            color:#2563eb;
            transition: transform .12s ease, filter .12s ease;
        }
        .nav-btn:hover{ transform:translateY(-1px); filter:brightness(1.02); }
        .nav-btn.logout{
            border:1px solid rgba(220,38,38,0.25);
            background: rgba(220,38,38,0.08);
            color:#dc2626;
        }

        .page{ max-width:1100px; margin:28px auto 60px; padding:0 18px; }
        h1{ margin:0 0 6px; text-align:center; }
        .sub{ text-align:center; color:var(--muted); margin:0 0 18px; }

        .card{
            background:#fff;
            border:1px solid var(--border);
            border-radius:18px;
            box-shadow:var(--shadow);
            padding:16px 16px;
        }

        .rec{
            border:1px solid rgba(226,232,240,.9);
            border-radius:14px;
            padding:14px 14px;
            background:#fbfdff;
            margin-bottom:12px;
        }
        .rec-head{
            display:flex;
            flex-wrap:wrap;
            align-items:center;
            gap:10px;
            justify-content:space-between;
            margin-bottom:8px;
        }
        .rec-title{
            font-weight:900;
            font-size:14px;
        }
        .pill{
            font-size:11px;
            padding:3px 10px;
            border-radius:999px;
            background:rgba(37,99,235,0.10);
            border:1px solid rgba(37,99,235,0.20);
            color:#1d4ed8;
            font-weight:900;
        }
        .label{ font-weight:900; font-size:12px; color:#111827; margin-top:8px; }
        .text{ font-size:13px; color:#334155; margin-top:3px; white-space:pre-wrap; }

        .empty{
            color:var(--muted);
            margin:10px 0 0;
            text-align:center;
        }

        @media (max-width:760px){
            .navbar{ padding:10px 14px; }
            .rec-head{ flex-direction:column; align-items:flex-start; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-left">Healthcare Record System</div>
    <div class="navbar-right">
        <span><?php echo h($patientName); ?> (Patient)</span>

        <a class="nav-btn"
           href="<?php echo h($backFallback); ?>"
           onclick="if (window.history.length > 1) { window.history.back(); return false; }">
            ← Back
        </a>

        <a class="nav-btn logout" href="logout.php">Log out</a>
    </div>
</div>

<div class="page">
    <h1>My Medical Records</h1>
    <p class="sub">Diagnoses, medications and notes from your doctors.</p>

    <div class="card">
        <?php if (empty($records)): ?>
            <p class="empty">You do not have any medical records yet.</p>
        <?php else: ?>
            <?php foreach ($records as $r): ?>
                <?php
                    $date = substr((string)$r['created_at'], 0, 10);
                    $doc  = $r['doctor_name'] ?? '—';
                    $diag = trim((string)($r['diagnosis'] ?? ''));
                    $meds = trim((string)($r['medications'] ?? ''));
                    $note = trim((string)($r['notes'] ?? ''));
                ?>
                <div class="rec">
                    <div class="rec-head">
                        <div class="rec-title"><?php echo h($date); ?> — Dr. <?php echo h($doc); ?></div>
                        <div class="pill">Medical Record</div>
                    </div>

                    <?php if ($diag !== ''): ?>
                        <div class="label">Diagnosis</div>
                        <div class="text"><?php echo h($diag); ?></div>
                    <?php endif; ?>

                    <?php if ($meds !== ''): ?>
                        <div class="label">Medications</div>
                        <div class="text"><?php echo h($meds); ?></div>
                    <?php endif; ?>

                    <?php if ($note !== ''): ?>
                        <div class="label">Notes</div>
                        <div class="text"><?php echo h($note); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
