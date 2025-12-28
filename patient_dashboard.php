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

$patientId   = (int)$_SESSION['user_id'];
$patientName = $_SESSION['name'] ?? 'Patient';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Profile info (blood group) ---
$sqlProfile = "SELECT blood_group FROM users WHERE id = :id";
$stmtProfile = $conn->prepare($sqlProfile);
$stmtProfile->execute([':id' => $patientId]);
$profileRow  = $stmtProfile->fetch(PDO::FETCH_ASSOC);
$bloodGroup  = $profileRow['blood_group'] ?? null;

/*
 * --- All medications from medical records (show all) ---
 */
$sqlAllMeds = "
    SELECT GROUP_CONCAT(TRIM(medications) SEPARATOR '||') AS all_meds
    FROM medical_records
    WHERE patient_id = :pid
      AND medications IS NOT NULL
      AND medications <> ''
";
$stmtAllMeds = $conn->prepare($sqlAllMeds);
$stmtAllMeds->execute([':pid' => $patientId]);
$allMedsRow = $stmtAllMeds->fetch(PDO::FETCH_ASSOC);

$allMedications = [];
if ($allMedsRow && !empty($allMedsRow['all_meds'])) {
    $parts = explode('||', $allMedsRow['all_meds']);
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') $allMedications[] = $p;
    }
}

// --- DOCTOR → HOSPITAL mapping (fallback) ---
$doctorHospitalMap = [
    'Emre Aktürk'    => 'Ankara City Hospital – Cardiology',
    'Nisa Atım'      => 'Ankara City Hospital – Cardiology',
    'Alperen Çivici' => 'Ankara City Hospital – Neurology',
    'Hülya Can'      => 'Keçiören Training and Research – Internal Medicine',
    'Leyla Yıldız'   => 'Çankaya State Hospital – Ophthalmology',
    'Selin Yağcı'    => 'Çankaya State Hospital – Dermatology',
];

// ✅ Upcoming appointments ONLY (future OR today with time >= now)
$sqlAppointments = "
    SELECT a.id,
           a.appointment_date,
           a.appointment_time,
           a.status,
           a.hospital_name,
           d.name AS doctor_name
    FROM appointments a
    JOIN users d ON a.doctor_id = d.id
    WHERE a.patient_id = :pid
      AND (
            a.appointment_date > CURDATE()
            OR (a.appointment_date = CURDATE() AND a.appointment_time >= CURTIME())
          )
    ORDER BY a.appointment_date, a.appointment_time
    LIMIT 50
";
$stmtApp = $conn->prepare($sqlAppointments);
$stmtApp->execute([':pid' => $patientId]);
$appointments = $stmtApp->fetchAll(PDO::FETCH_ASSOC);

// ✅ Emergency contacts (Dashboard’da göstermek için)
$emergencyContacts = [];
try {
    $sqlContacts = "
        SELECT id, contact_name, phone, relation, priority
        FROM emergency_contacts
        WHERE patient_id = :pid
        ORDER BY priority ASC, id DESC
        LIMIT 5
    ";
    $stmtContacts = $conn->prepare($sqlContacts);
    $stmtContacts->execute([':pid' => $patientId]);
    $emergencyContacts = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $emergencyContacts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root{
            --primary:#1976d2;
            --primary-soft:#e3f2fd;
            --danger:#e53935;
            --success:#43a047;
            --warning:#fb8c00;
            --card-radius:16px;
            --shadow-soft:0 10px 25px rgba(15,23,42,0.08);
        }

        *{ box-sizing:border-box; }

        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: radial-gradient(circle at top left,#e3f2fd 0,#f4f6f9 40%,#f4f6f9 100%);
            margin: 0;
            padding: 0;
            color:#1f2933;
        }

        /* NAVBAR */
        .navbar {
            background:#ffffffaa;
            backdrop-filter: blur(10px);
            border-bottom:1px solid rgba(148,163,184,0.35);
            color: #111827;
            padding: 10px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position:sticky;
            top:0;
            z-index:20;
        }
        .navbar-left {
            font-weight: 700;
            letter-spacing: .03em;
            font-size: 18px;
            color:#0f172a;
        }
        .navbar-right{
            font-size:14px;
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            justify-content:flex-end;
        }

        .nav-btn{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 12px;
            border-radius:999px;
            text-decoration:none;
            font-weight:700;
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

        .page {
            max-width: 1380px;
            margin: 32px auto 60px;
            padding: 0 18px 40px;
        }

        h1 {
            text-align: center;
            margin: 4px 0 6px;
            font-size: 30px;
            letter-spacing:.03em;
        }
        .sub-text {
            text-align: center;
            color: #6b7280;
            margin-bottom: 28px;
            font-size:14px;
        }

        .layout-row {
            display: grid;
            grid-template-columns: 260px minmax(0,1.8fr) 1.1fr;
            gap: 28px;
            align-items: flex-start;
        }

        .side-left {
            display: flex;
            flex-direction: column;
            gap: 12px;
            position:sticky;
            top:96px;
        }

        .btn-left {
            display: inline-flex;
            align-items:center;
            justify-content:center;
            width: 100%;
            padding: 14px 24px;
            border-radius: 999px;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            color: #fff;
            box-shadow: 0 6px 18px rgba(15,23,42,0.25);
            border:none;
            transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
        }
        .btn-left:hover{
            transform: translateY(-1px);
            filter:brightness(1.05);
            box-shadow:0 10px 24px rgba(15,23,42,0.30);
        }
        .btn-red  { background:linear-gradient(135deg,#ef4444,#dc2626); }
        .btn-blue { background:linear-gradient(135deg,#2563eb,#1d4ed8); }
        .btn-gray { background:linear-gradient(135deg,#4b5563,#111827); }

        .center-col { min-width:0; }

        .card {
            background: #ffffff;
            border-radius: var(--card-radius);
            padding: 18px 22px;
            box-shadow: var(--shadow-soft);
            border:1px solid rgba(148,163,184,0.25);
        }
        .card h2 {
            margin-top: 0;
            margin-bottom: 8px;
            font-size:20px;
            display:flex;
            align-items:center;
            gap:8px;
        }
        .card h2 span.icon{
            width:26px;
            height:26px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:999px;
            background:var(--primary-soft);
            color:var(--primary);
            font-size:16px;
        }

        .section-caption{
            font-size:12px;
            color:#9ca3af;
            margin-bottom:14px;
        }

        .card-appointments ul{ list-style:none; padding:0; margin:0; }
        .appointment-item{
            padding:10px 12px;
            border-radius:12px;
            background:#f9fafb;
            border:1px solid rgba(209,213,219,0.8);
            margin-bottom:10px;
            display:flex;
            flex-direction:column;
            gap:4px;
        }

        .appt-main{ font-size:14px; font-weight:500; color:#111827; }
        .appt-meta{
            font-size:12px;
            color:#6b7280;
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            align-items:center;
        }
        .appt-actions{ margin-left:auto; display:flex; gap:8px; }
        .appt-actions a{ font-size:12px; text-decoration:none; font-weight:600; }
        .link-blue { color: #2563eb; }
        .link-red  { color: #dc2626; }

        .pill-status{
            padding:2px 8px;
            border-radius:999px;
            font-size:11px;
            font-weight:600;
            text-transform:capitalize;
        }
        .status-pending{ background:rgba(251,191,36,0.18); color:#92400e; }
        .status-approved{ background:rgba(59,130,246,0.15); color:#1d4ed8; }
        .status-cancelled{ background:rgba(248,113,113,0.18); color:#b91c1c; }
        .status-completed{ background:rgba(74,222,128,0.15); color:#166534; }

        .side-right { display:flex; flex-direction:column; gap:18px; }

        .profile-card{ text-align:center; padding-top:22px; padding-bottom:20px; }

        .btn-green {
            display: inline-flex;
            align-items:center;
            justify-content:center;
            gap:6px;
            background: linear-gradient(135deg,#22c55e,#16a34a);
            color: #fff;
            padding: 8px 22px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 6px 18px rgba(22,163,74,0.35);
        }
        .btn-green:hover{ filter:brightness(1.05); }

        .badge-blood {
            display: inline-flex;
            align-items:center;
            gap:6px;
            margin-top: 12px;
            background: #e0f2f1;
            color: #065f46;
            padding: 6px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight:500;
        }

        .quick-card{
            display:flex;
            align-items:center;
            gap:14px;
            padding:16px 16px;
            border-radius:18px;
            background:#fff;
            text-decoration:none;
            color:#0f172a;
            box-shadow: 0 10px 25px rgba(2,6,23,.08);
            border: 1px solid rgba(226,232,240,.9);
            transition: transform .15s ease, box-shadow .15s ease;
            margin-top: 14px;
            text-align:left;
        }
        .quick-card:hover{
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(2,6,23,.12);
        }
        .quick-icon{
            width:56px;
            height:56px;
            border-radius:16px;
            background:#f1f5ff;
            display:flex;
            align-items:center;
            justify-content:center;
            flex: 0 0 56px;
        }
        .quick-icon svg{ width:28px; height:28px; color:#1d4ed8; }
        .quick-title{ font-weight:800; font-size:16px; line-height:1.15; margin-bottom:4px; }
        .quick-sub{ font-size:13px; color:#64748b; line-height:1.3; }

        .emg-box{
            margin-top:14px;
            padding:12px 14px;
            border-radius:14px;
            background:#f9fafb;
            border:1px solid rgba(148,163,184,0.25);
            text-align:left;
        }
        .emg-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            margin-bottom:10px;
        }
        .emg-title{ font-weight:800; font-size:14px; color:#0f172a; }
        .emg-link{ font-size:12px; font-weight:700; color:#2563eb; text-decoration:none; }
        .emg-list{
            list-style:none;
            margin:0;
            padding:0;
            display:flex;
            flex-direction:column;
            gap:8px;
        }
        .emg-item{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:10px;
            padding:10px 10px;
            background:#fff;
            border:1px solid rgba(226,232,240,.9);
            border-radius:12px;
        }
        .emg-left strong{ display:block; font-size:13px; color:#111827; }
        .emg-left span{ display:block; font-size:12px; color:#6b7280; margin-top:2px; }
        .emg-prio{
            min-width:28px;
            height:28px;
            border-radius:999px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:900;
            font-size:12px;
            color:#3730a3;
            background:#eef2ff;
            border:1px solid #c7d2fe;
        }
        .emg-empty{ font-size:13px; color:#6b7280; margin:0; }

        .all-meds-card {
            background: #fff5f5;
            border-radius: 12px;
            padding: 10px 14px 12px;
            font-size: 13px;
            color: #b91c1c;
            border:1px solid rgba(248,113,113,0.5);
            margin-top:18px;
            text-align:left;
        }
        .all-meds-card h3 {
            margin: 0 0 6px;
            font-size: 14px;
            display:flex;
            align-items:center;
            gap:6px;
        }
        .all-meds-card h3 span.icon-pill{
            width:22px;
            height:22px;
            border-radius:999px;
            background:#fecaca;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-size:13px;
        }
        .all-meds-card ul { margin: 4px 0 0; padding-left: 18px; }

        .floating-questions {
            position: fixed;
            right: 26px;
            bottom: 22px;
            background: linear-gradient(135deg,#f59e0b,#f97316);
            color: #111827;
            padding: 11px 26px;
            border-radius: 999px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 10px 26px rgba(245,158,11,0.5);
            font-size: 14px;
            display:inline-flex;
            align-items:center;
            gap:8px;
        }

        @media (max-width: 1100px) {
            .layout-row { grid-template-columns: 1fr; }
            .side-left{
                position:static;
                flex-direction:row;
                flex-wrap:wrap;
                justify-content:center;
            }
            .side-left .btn-left{
                flex:1 1 48%;
                min-width:210px;
            }
        }

        @media (max-width: 720px){
            .page{ padding:0 12px 36px; }
            h1{ font-size:24px; }
            .side-left .btn-left{ min-width:100%; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-left">Healthcare Record System</div>
    <div class="navbar-right">
        <span><?php echo h($patientName); ?> (Patient)</span>
        <a class="nav-btn logout" href="logout.php">Log out</a>
    </div>
</div>

<div class="page">
    <h1>Patient Dashboard</h1>
    <p class="sub-text">Here you can see your upcoming appointments and quick actions.</p>

    <div class="layout-row">
        <!-- LEFT BUTTONS -->
        <div class="side-left">
            <a href="book_appointment.php" class="btn-left btn-red">New Appointment</a>
            <a href="organ_donation.php" class="btn-left btn-blue">Organ Donation Preference</a>
            <a href="visit_history.php" class="btn-left btn-gray">Illness / Visit History</a>
            <a href="tests.php" class="btn-left btn-gray">My Tests</a>
            <a href="my_reports.php" class="btn-left btn-gray">My Reports</a>

            <!-- ✅ NEW: Medical Records button under My Reports -->
            <a href="patient_medical_records.php" class="btn-left btn-gray">My Medical Records</a>
        </div>

        <!-- CENTER: Upcoming Appointments -->
        <div class="center-col">
            <div class="card card-appointments">
                <h2><span class="icon">📅</span>Upcoming Appointments</h2>
                <div class="section-caption">Only future appointments are shown here.</div>

                <?php if (empty($appointments)): ?>
                    <p>You do not have any upcoming appointments.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($appointments as $appt): ?>
                            <?php
                                $date      = h($appt['appointment_date']);
                                $time      = h(substr((string)$appt['appointment_time'], 0, 5));
                                $doctorRaw = $appt['doctor_name'] ?? '';
                                $doctor    = h($doctorRaw);
                                $status    = strtolower(trim((string)($appt['status'] ?? 'pending')));

                                $hospital = $appt['hospital_name'] ?? '';
                                if (trim((string)$hospital) === '' && isset($doctorHospitalMap[$doctorRaw])) {
                                    $hospital = $doctorHospitalMap[$doctorRaw];
                                }
                                if (trim((string)$hospital) === '') $hospital = 'Hospital information not available';
                                $hospitalEsc = h($hospital);

                                $statusClass = 'status-' . $status;
                            ?>
                            <li class="appointment-item">
                                <div class="appt-main">
                                    <?php echo $date . " · " . $time . " — " . $hospitalEsc . " — Dr. " . $doctor; ?>
                                </div>
                                <div class="appt-meta">
                                    <span class="pill-status <?php echo h($statusClass); ?>">
                                        <?php echo h($status); ?>
                                    </span>

                                    <span>Doctor: <strong><?php echo $doctor; ?></strong></span>

                                    <span class="appt-actions">
                                        <a class="link-blue" href="update_appointment.php?id=<?php echo (int)$appt['id']; ?>">
                                            Change time
                                        </a>
                                        <a class="link-red"
                                           href="cancel_appointment.php?id=<?php echo (int)$appt['id']; ?>"
                                           onclick="return confirm('Are you sure you want to cancel this appointment?');">
                                            Cancel
                                        </a>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: Profile + Contacts + Meds -->
        <div class="side-right">
            <div class="card profile-card">
                <a href="edit_profile.php" class="btn-green">Edit Profile</a><br>

                <span class="badge-blood">
                    <span>🩸</span>
                    <span>Your Blood Type: <?php echo $bloodGroup ? h($bloodGroup) : 'Not set yet'; ?></span>
                </span>

                <a class="quick-card" href="add_contact.php">
                    <div class="quick-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.08 4.18 2 2 0 0 1 4.06 2h3a2 2 0 0 1 2 1.72c.12.86.3 1.7.54 2.5a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.58-1.06a2 2 0 0 1 2.11-.45c.8.24 1.64.42 2.5.54A2 2 0 0 1 22 16.92z"/>
                            <path d="M16 6h6"/>
                            <path d="M19 3v6"/>
                        </svg>
                    </div>
                    <div class="quick-text">
                        <div class="quick-title">Add Contact</div>
                        <div class="quick-sub">Priority contacts for emergencies</div>
                    </div>
                </a>

                <div class="emg-box">
                    <div class="emg-head">
                        <div class="emg-title">Emergency Contacts</div>
                        <a class="emg-link" href="add_contact.php">Manage</a>
                    </div>

                    <?php if (empty($emergencyContacts)): ?>
                        <p class="emg-empty">No contacts added yet.</p>
                    <?php else: ?>
                        <ul class="emg-list">
                            <?php foreach ($emergencyContacts as $ec): ?>
                                <li class="emg-item">
                                    <div class="emg-left">
                                        <strong><?php echo h($ec['contact_name']); ?></strong>
                                        <span><?php echo h($ec['relation']); ?> • <?php echo h($ec['phone']); ?></span>
                                    </div>
                                    <div class="emg-prio" title="Priority"><?php echo (int)$ec['priority']; ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="all-meds-card">
                    <h3><span class="icon-pill">💊</span>All My Medications</h3>
                    <?php if (empty($allMedications)): ?>
                        <p style="margin:6px 0 0;">You do not have any recorded medications yet.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($allMedications as $m): ?>
                                <li><?php echo nl2br(h($m)); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<a href="ask_question.php" class="floating-questions">❓ My Questions</a>

</body>
</html>
