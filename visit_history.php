<?php
ob_start();
session_start();
require 'db.php';

// Only allow patient
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $role !== 'patient') {
    header("Location: login.php");
    exit;
}

$patientId   = (int)($_SESSION['user_id']);
$patientName = $_SESSION['name'] ?? 'Patient';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Past appointments only (date < today) OR (date = today and time < now)
$sqlHistory = "
    SELECT
        a.id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.hospital_name,
        a.department,
        d.name AS doctor_name
    FROM appointments a
    JOIN users d ON a.doctor_id = d.id
    WHERE a.patient_id = :pid
      AND (
            a.appointment_date < CURDATE()
            OR (a.appointment_date = CURDATE() AND a.appointment_time < CURTIME())
          )
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 200
";

$stmt = $conn->prepare($sqlHistory);
$stmt->execute([':pid' => $patientId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$backFallback = 'patient_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Visit History</title>

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

        /* Navbar */
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

        .page{ max-width:1200px; margin:28px auto 60px; padding:0 18px; }
        h1{ margin:0 0 6px; text-align:center; }
        .sub{ text-align:center; color:var(--muted); margin:0 0 18px; }

        .card{
            background:#fff;
            border:1px solid var(--border);
            border-radius:18px;
            box-shadow:var(--shadow);
            padding:18px 18px;
        }

        table{ width:100%; border-collapse:collapse; font-size:13px; }
        thead{ background:#f3f4f6; }
        th, td{
            text-align:left;
            padding:12px 10px;
            border-bottom:1px solid #e5e7eb;
            vertical-align:top;
        }
        tbody tr:hover{ background:#fafafa; }

        .status-pill{
            display:inline-flex;
            align-items:center;
            padding:2px 10px;
            border-radius:999px;
            font-size:11px;
            font-weight:900;
            text-transform:capitalize;
            background:#eef2f7;
        }
        .st-pending{ color:#b45309; background:rgba(251,191,36,0.18); border:1px solid rgba(251,191,36,0.35); }
        .st-approved{ color:#1d4ed8; background:rgba(59,130,246,0.15); border:1px solid rgba(59,130,246,0.30); }
        .st-completed{ color:#166534; background:rgba(74,222,128,0.15); border:1px solid rgba(74,222,128,0.30); }
        .st-cancelled{ color:#b91c1c; background:rgba(248,113,113,0.18); border:1px solid rgba(248,113,113,0.35); }

        .muted{ color:var(--muted); }

        @media (max-width:760px){
            .navbar{ padding:10px 14px; }
            th:nth-child(4), td:nth-child(4){ display:none; } /* hide department on small screens */
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
    <h1>Illness / Visit History</h1>
    <p class="sub">This page shows only your past appointments.</p>

    <div class="card">
        <?php if (empty($history)): ?>
            <p class="muted" style="margin:10px 0 0;">No past appointments found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width:120px;">Date</th>
                        <th style="width:90px;">Time</th>
                        <th>Doctor</th>
                        <th>Department</th>
                        <th>Hospital</th>
                        <th style="width:120px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $a): ?>
                        <?php
                            $date = h($a['appointment_date']);
                            $time = h(substr((string)$a['appointment_time'], 0, 5));
                            $doc  = h($a['doctor_name'] ?? '—');
                            $dept = h($a['department'] ?? '—');
                            $hosp = h($a['hospital_name'] ?? '—');

                            $st = strtolower(trim((string)($a['status'] ?? 'pending')));
                            $cls = 'st-pending';
                            if ($st === 'approved') $cls = 'st-approved';
                            elseif ($st === 'completed') $cls = 'st-completed';
                            elseif ($st === 'cancelled') $cls = 'st-cancelled';
                        ?>
                        <tr>
                            <td><?php echo $date; ?></td>
                            <td><?php echo $time; ?></td>
                            <td><?php echo $doc; ?></td>
                            <td><?php echo $dept; ?></td>
                            <td><?php echo $hosp; ?></td>
                            <td>
                                <span class="status-pill <?php echo h($cls); ?>">
                                    <?php echo h($st); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
