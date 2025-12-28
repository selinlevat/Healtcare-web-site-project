<?php
ob_start();
session_start();
require 'db.php';

// Only allow doctor
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $role !== 'doctor') {
    header("Location: login.php");
    exit;
}

$doctorId   = (int)($_SESSION['user_id']);
$doctorName = $_SESSION['name'] ?? 'Doctor';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$flashSuccess = null;
$flashError   = null;

/* -------------------- APPROVE / REJECT ACTION -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appt_action'], $_POST['appointment_id'])) {
    $action = $_POST['appt_action'];
    $aid    = (int)$_POST['appointment_id'];

    if ($aid <= 0) {
        $flashError = "Invalid appointment.";
    } else {
        // Check appointment belongs to this doctor
        $check = $conn->prepare("SELECT id, status FROM appointments WHERE id = :id AND doctor_id = :did LIMIT 1");
        $check->execute([':id' => $aid, ':did' => $doctorId]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $flashError = "Appointment not found or not yours.";
        } else {
            $currentStatus = strtolower(trim($row['status'] ?? 'pending'));

            if ($action === 'approve') {
                // Approve only if pending
                if ($currentStatus !== 'pending') {
                    $flashError = "Only pending appointments can be approved.";
                } else {
                    $upd = $conn->prepare("UPDATE appointments SET status = 'approved' WHERE id = :id AND doctor_id = :did");
                    $upd->execute([':id' => $aid, ':did' => $doctorId]);
                    $flashSuccess = "Appointment approved.";
                }
            } elseif ($action === 'reject') {
                // Reject -> cancelled
                if ($currentStatus === 'cancelled') {
                    $flashError = "This appointment is already cancelled.";
                } else {
                    $upd = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = :id AND doctor_id = :did");
                    $upd->execute([':id' => $aid, ':did' => $doctorId]);
                    $flashSuccess = "Appointment cancelled.";
                }
            } else {
                $flashError = "Invalid action.";
            }
        }
    }
}

/* -------------------- PENDING APPROVALS (for modal) -------------------- */
$sqlPending = "
    SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.hospital_name, a.department,
           p.name AS patient_name
    FROM appointments a
    JOIN users p ON a.patient_id = p.id
    WHERE a.doctor_id = :did
      AND LOWER(a.status) = 'pending'
    ORDER BY a.appointment_date, a.appointment_time
    LIMIT 50
";
$stmtPending = $conn->prepare($sqlPending);
$stmtPending->execute([':did' => $doctorId]);
$pendingApprovals = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

/* -------------------- UPCOMING APPOINTMENTS (doctor) -------------------- */
/*
  Upcoming = (date > today) OR (date = today AND time >= now)
  Exclude cancelled
*/
$sqlAppointments = "
    SELECT a.id,
           a.appointment_date,
           a.appointment_time,
           a.status,
           a.hospital_name,
           a.department,
           p.name AS patient_name
    FROM appointments a
    JOIN users p ON a.patient_id = p.id
    WHERE a.doctor_id = :did
      AND LOWER(a.status) <> 'cancelled'
      AND (
            a.appointment_date > CURDATE()
            OR (a.appointment_date = CURDATE() AND a.appointment_time >= CURTIME())
          )
    ORDER BY a.appointment_date, a.appointment_time
    LIMIT 50
";
$stmtApp = $conn->prepare($sqlAppointments);
$stmtApp->execute([':did' => $doctorId]);
$appointments = $stmtApp->fetchAll(PDO::FETCH_ASSOC);

/* -------------------- Medical records created by this doctor -------------------- */
$sqlRecords = "
    SELECT mr.id,
           mr.diagnosis,
           mr.medications,
           mr.notes,
           mr.created_at,
           p.name AS patient_name
    FROM medical_records mr
    JOIN users p ON mr.patient_id = p.id
    WHERE mr.doctor_id = :did
    ORDER BY mr.created_at DESC
    LIMIT 30
";
$stmtRec = $conn->prepare($sqlRecords);
$stmtRec->execute([':did' => $doctorId]);
$records = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

/* -------------------- Reports issued by this doctor -------------------- */
$reports = [];
try{
    $sqlRep = "
        SELECT r.id, r.issue_date, r.start_date, r.end_date, r.leave_days, r.diagnosis, r.department,
               p.name AS patient_name
        FROM reports r
        JOIN users p ON p.id = r.patient_id
        WHERE r.doctor_id = :did
        ORDER BY r.issue_date DESC, r.id DESC
        LIMIT 50
    ";
    $stmtRep = $conn->prepare($sqlRep);
    $stmtRep->execute([':did' => $doctorId]);
    $reports = $stmtRep->fetchAll(PDO::FETCH_ASSOC);
}catch(Exception $e){
    $reports = [];
}

$pendingCount = count($pendingApprovals);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: Inter, Arial, sans-serif;
            background:#f4f6f9;
            margin:0;
            padding:0;
            color:#111827;
        }
        .navbar {
            background:#37474f;
            color:#fff;
            padding:12px 18px;
            display:flex;
            justify-content:space-between;
            align-items:center;
        }
        .navbar-left{ font-weight:800; letter-spacing:.02em; }
        .navbar-right{
            display:flex;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
            font-size:14px;
        }
        .nav-pill{
            color:#fff;
            text-decoration:none;
            padding:7px 12px;
            border-radius:999px;
            border:1px solid rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.08);
            font-weight:800;
            cursor:pointer;
        }
        .nav-pill.logout{
            border:1px solid rgba(239,68,68,0.35);
            background: rgba(239,68,68,0.12);
        }
        .nav-pill.warn{
            border:1px solid rgba(251,191,36,0.45);
            background: rgba(251,191,36,0.15);
        }

        .page { max-width:1250px; margin:25px auto 40px; padding:0 15px; }
        h1 { margin:0 0 6px; text-align:center; }
        .sub-text { text-align:center; color:#555; margin-bottom:18px; }

        .alert{
            max-width:980px;
            margin:0 auto 14px;
            border-radius:12px;
            padding:10px 12px;
            font-size:13px;
            border:1px solid transparent;
        }
        .alert-success{ background:rgba(22,163,74,0.10); border-color:rgba(22,163,74,0.35); color:#166534; }
        .alert-error{ background:rgba(239,68,68,0.10); border-color:rgba(239,68,68,0.35); color:#b91c1c; }

        .grid { display:grid; grid-template-columns: 1.7fr 1.3fr; gap:20px; }
        .stack{ display:flex; flex-direction:column; gap:18px; }

        .card{
            background:#fff;
            border-radius:12px;
            padding:16px 18px;
            box-shadow:0 10px 24px rgba(15,23,42,0.08);
            border:1px solid rgba(148,163,184,0.25);
        }
        .card h2{ margin:0 0 10px; font-size:18px; }

        ul{ padding-left:18px; margin:10px 0 0; }

        .appt-item{ margin-bottom:12px; }
        .appt-meta{ font-size:12px; color:#555; margin-top:3px; display:flex; gap:10px; flex-wrap:wrap; }
        .appt-actions{ margin-top:6px; display:flex; gap:10px; flex-wrap:wrap; }
        .appt-actions a{ font-size:12px; text-decoration:none; color:#1565c0; font-weight:800; }

        .status-pill{
            font-size:11px;
            padding:2px 8px;
            border-radius:999px;
            background:#eceff1;
            font-weight:900;
            text-transform:capitalize;
        }
        .status-pending{ color:#ef6c00; }
        .status-approved{ color:#1d4ed8; }
        .status-completed{ color:#2e7d32; }
        .status-cancelled{ color:#c62828; }

        table{ width:100%; border-collapse:collapse; font-size:13px; }
        thead{ background:#f3f4f6; }
        th, td{ padding:10px; text-align:left; border-bottom:1px solid #e5e7eb; vertical-align:top; }
        tbody tr:hover{ background:#fafafa; }

        /* Modal */
        .modal-overlay{
            position:fixed;
            inset:0;
            background:rgba(2,6,23,0.55);
            display:none;
            align-items:center;
            justify-content:center;
            padding:18px;
            z-index:999;
        }
        .modal{
            width:100%;
            max-width:860px;
            background:#fff;
            border-radius:16px;
            border:1px solid rgba(148,163,184,0.25);
            box-shadow:0 22px 60px rgba(2,6,23,0.35);
            overflow:hidden;
        }
        .modal-head{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:10px;
            padding:14px 16px;
            background:#f8fafc;
            border-bottom:1px solid #e5e7eb;
        }
        .modal-title{
            font-weight:900;
            color:#0f172a;
        }
        .modal-close{
            border:none;
            background:transparent;
            font-weight:900;
            cursor:pointer;
            font-size:16px;
            padding:6px 10px;
            border-radius:10px;
        }
        .modal-close:hover{ background:#eef2ff; }
        .modal-body{ padding:14px 16px 18px; }

        .pending-card{
            background:#fff;
            border:1px solid rgba(226,232,240,.9);
            border-radius:14px;
            padding:12px 12px;
            margin-bottom:10px;
        }
        .pending-top{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:12px;
            flex-wrap:wrap;
        }
        .pending-main{
            font-size:14px;
            font-weight:800;
            color:#111827;
        }
        .pending-sub{
            font-size:12px;
            color:#64748b;
            margin-top:4px;
        }
        .pending-actions{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }
        .btn-small{
            border:none;
            cursor:pointer;
            font-weight:900;
            border-radius:999px;
            padding:8px 12px;
            font-size:12px;
        }
        .btn-approve{ background:rgba(29,78,216,0.12); color:#1d4ed8; border:1px solid rgba(29,78,216,0.35); }
        .btn-reject{ background:rgba(220,38,38,0.10); color:#dc2626; border:1px solid rgba(220,38,38,0.30); }
        .btn-approve:hover,.btn-reject:hover{ filter:brightness(1.02); }

        @media (max-width: 1000px) { .grid{ grid-template-columns:1fr; } }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-left">Healthcare Record System</div>
    <div class="navbar-right">
        <span><?php echo h($doctorName); ?> (Doctor)</span>

        <!-- ✅ Button: Pending approvals (opens modal) -->
        <button class="nav-pill warn" id="openPendingBtn" type="button">
            Pending Approvals (<?php echo (int)$pendingCount; ?>)
        </button>

        <a class="nav-pill" href="doctor_questions.php">My Questions</a>
        <a class="nav-pill" href="edit_profile.php">Edit Profile</a>
        <a class="nav-pill logout" href="logout.php">Log out</a>
    </div>
</div>

<div class="page">
    <h1>Doctor Dashboard</h1>
    <p class="sub-text">From here, you can manage appointments, create medical records, and issue rest reports.</p>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><?php echo h($flashSuccess); ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-error"><?php echo h($flashError); ?></div>
    <?php endif; ?>

    <div class="grid">
        <!-- Appointments -->
        <div class="card">
            <h2>My Upcoming Appointments</h2>

            <?php if (empty($appointments)): ?>
                <p>You do not have any upcoming appointments yet.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($appointments as $a): ?>
                        <?php
                            $date  = h($a['appointment_date']);
                            $time  = h(substr((string)$a['appointment_time'], 0, 5));
                            $pat   = h($a['patient_name']);
                            $hosp  = h($a['hospital_name'] ?: 'Hospital not specified');
                            $dept  = h($a['department'] ?: '');

                            $status = strtolower((string)($a['status'] ?? 'pending'));
                            $statusClass = 'status-pending';
                            if ($status === 'approved') $statusClass = 'status-approved';
                            elseif ($status === 'completed') $statusClass = 'status-completed';
                            elseif ($status === 'cancelled') $statusClass = 'status-cancelled';
                        ?>
                        <li class="appt-item">
                            <strong>
                             <a href="appointment_details_doctor.php?appointment_id=<?php echo (int)$a['id']; ?>" style="text-decoration:none;color:#111827;">
                                <?php echo $date . ' ' . $time; ?>
                            </a>
                            </strong> 
                            –<?php echo $pat; ?><br>

                            <div class="appt-meta">
                                <span><?php echo $hosp; ?><?php echo $dept ? ' • ' . $dept : ''; ?></span>
                                <span class="status-pill <?php echo $statusClass; ?>"><?php echo h($status); ?></span>
                            </div>

                            <div class="appt-actions">
                                <?php if ($status === 'pending'): ?>
                                    <span style="font-size:12px;color:#b45309;font-weight:800;">
                                        Waiting for your approval → use “Pending Approvals”
                                    </span>
                                <?php elseif ($status !== 'cancelled'): ?>
                                    <a href="add_medical_record.php?appointment_id=<?php echo (int)$a['id']; ?>">➕ Add Medical Record</a>
                                    <a href="create_report.php?appointment_id=<?php echo (int)$a['id']; ?>">📝 Create Report (Rest Report)</a>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="stack">
            <!-- Medical Records -->
            <div class="card">
                <h2>Medical Records I Created</h2>

                <?php if (empty($records)): ?>
                    <p>You have not created any medical records yet.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($records as $r): ?>
                            <li style="margin-bottom:12px;">
                                <div style="font-weight:900;">
                                    <?php echo h(substr((string)$r['created_at'], 0, 10)) . ' – ' . h($r['patient_name']); ?>
                                </div>

                                <?php if (!empty($r['diagnosis'])): ?>
                                    <div style="font-size:12px;color:#555;">Diagnosis: <?php echo h($r['diagnosis']); ?></div>
                                <?php endif; ?>

                                <?php if (!empty($r['medications'])): ?>
                                    <div style="font-size:13px;color:#2e7d32;">
                                        Medications:<br>
                                        <?php
                                            $medStr = (string)$r['medications'];
                                            $medStr = str_replace([';', ','], "\n", $medStr);
                                            echo nl2br(h($medStr));
                                        ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($r['notes'])): ?>
                                    <div style="font-size:13px;color:#333;">Notes: <?php echo nl2br(h($r['notes'])); ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Reports -->
            <div class="card">
                <h2>Reports I Issued</h2>

                <?php if (empty($reports)): ?>
                    <p>You have not issued any reports yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>Issue Date</th>
                            <th>Patient</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Days</th>
                            <th>Diagnosis</th>
                            <th>Department</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($reports as $rr): ?>
                            <tr>
                                <td><?php echo h($rr['issue_date']); ?></td>
                                <td><?php echo h($rr['patient_name']); ?></td>
                                <td><?php echo h($rr['start_date']); ?></td>
                                <td><?php echo h($rr['end_date']); ?></td>
                                <td><?php echo h($rr['leave_days']); ?></td>
                                <td><?php echo h($rr['diagnosis'] ?? '—'); ?></td>
                                <td><?php echo h($rr['department'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ✅ PENDING APPROVALS MODAL -->
<div class="modal-overlay" id="pendingModal">
    <div class="modal">
        <div class="modal-head">
            <div class="modal-title">Pending Appointment Approvals (<?php echo (int)$pendingCount; ?>)</div>
            <button class="modal-close" id="closePendingBtn" type="button">✕</button>
        </div>

        <div class="modal-body">
            <?php if (empty($pendingApprovals)): ?>
                <p style="margin:0;color:#64748b;">No pending appointments right now.</p>
            <?php else: ?>
                <?php foreach ($pendingApprovals as $p): ?>
                    <?php
                        $pDate = h($p['appointment_date']);
                        $pTime = h(substr((string)$p['appointment_time'], 0, 5));
                        $pName = h($p['patient_name']);
                        $pHosp = h($p['hospital_name'] ?: 'Hospital not specified');
                        $pDept = h($p['department'] ?: '');
                        $pid   = (int)$p['id'];
                    ?>
                    <div class="pending-card">
                        <div class="pending-top">
                            <div>
                                <div class="pending-main"><?php echo $pDate . " " . $pTime . " — " . $pName; ?></div>
                                <div class="pending-sub"><?php echo $pHosp . ($pDept ? " • " . $pDept : ""); ?></div>
                            </div>

                            <div class="pending-actions">
                                <form method="post" style="margin:0;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $pid; ?>">
                                    <input type="hidden" name="appt_action" value="approve">
                                    <button class="btn-small btn-approve" type="submit">Approve</button>
                                </form>

                                <form method="post" style="margin:0;" onsubmit="return confirm('Reject this appointment? It will be cancelled.');">
                                    <input type="hidden" name="appointment_id" value="<?php echo $pid; ?>">
                                    <input type="hidden" name="appt_action" value="reject">
                                    <button class="btn-small btn-reject" type="submit">Reject</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById('pendingModal');
const openBtn = document.getElementById('openPendingBtn');
const closeBtn = document.getElementById('closePendingBtn');

openBtn.addEventListener('click', ()=> { modal.style.display = 'flex'; });
closeBtn.addEventListener('click', ()=> { modal.style.display = 'none'; });
modal.addEventListener('click', (e)=> { if(e.target === modal) modal.style.display = 'none'; });
</script>

</body>
</html>
