<?php
session_start();
require 'db.php';

/**
 * tests.php (Patient - My Tests)
 * - English UI
 * - Back button on top-right
 * - Translates Turkish test names to English (display only)
 * - Translates DB status values: istek/tamamlandi/iptal -> Requested/Completed/Cancelled
 * - Shows requested_at as Date (if exists in DB)
 * - NO RESULT COLUMN
 */

// Patient only
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: login.php");
    exit;
}

$patientId   = (int)$_SESSION['user_id'];
$patientName = $_SESSION['name'] ?? 'Patient';

// Map Turkish test names (stored in DB) -> English display
$testNameMap = [
    // BLOOD TESTS
    'Hemogram (Tam Kan Sayımı)' => 'Complete Blood Count (CBC)',
    'Sedimentasyon (ESR)'       => 'Erythrocyte Sedimentation Rate (ESR)',
    'CRP'                       => 'CRP',
    'Ferritin'                  => 'Ferritin',
    'Vitamin B12'               => 'Vitamin B12',
    'D Vitamini'                => 'Vitamin D',
    'Açlık Kan Şekeri (Glukoz)' => 'Fasting Blood Glucose',
    'HbA1c'                     => 'HbA1c',
    'Üre'                       => 'Urea',
    'Kreatinin'                 => 'Creatinine',
    'AST'                       => 'AST',
    'ALT'                       => 'ALT',
    'ALP'                       => 'ALP',
    'GGT'                       => 'GGT',
    'Kolesterol'                => 'Total Cholesterol',
    'LDL'                       => 'LDL',
    'HDL'                       => 'HDL',
    'Trigliserid'               => 'Triglycerides',

    // HORMONE TESTS
    'TSH'        => 'TSH',
    'T3'         => 'T3',
    'T4'         => 'T4',
    'Anti-TPO'   => 'Anti-TPO',
    'Anti-TG'    => 'Anti-TG',
    'FSH'        => 'FSH',
    'LH'         => 'LH',
    'Östrojen'   => 'Estrogen',
    'Progesteron'=> 'Progesterone',
    'Testosteron'=> 'Testosterone',
    'Prolaktin'  => 'Prolactin',

    // URINE & STOOL TESTS
    'Tam idrar tahlili'   => 'Urinalysis (Complete)',
    'İdrar kültürü'       => 'Urine Culture',
    'Gaita testi'         => 'Stool Test',
    'Gaitada gizli kan'   => 'Fecal Occult Blood Test',
    'Parazit incelemesi'  => 'Parasite Examination',

    // INFECTION TESTS
    'Prokalsitonin' => 'Procalcitonin',
    'Hepatit B'     => 'Hepatitis B',
    'Hepatit C'     => 'Hepatitis C',
    'HIV'           => 'HIV',
    'COVID-19'      => 'COVID-19',
    'TORCH testleri'=> 'TORCH Panel',

    // IMAGING TESTS
    'Röntgen'                  => 'X-Ray',
    'Ultrason (USG)'           => 'Ultrasound (USG)',
    'MR (Manyetik Rezonans)'   => 'MRI (Magnetic Resonance Imaging)',
    'Bilgisayarlı Tomografi (BT)' => 'CT (Computed Tomography)',
    'EKG'                      => 'ECG (EKG)',
    'EKO'                      => 'Echocardiography (ECHO)',

    // SPECIAL TESTS
    'Alerji testleri (IgE)' => 'Allergy Tests (IgE)',
    'PSA'                   => 'PSA',
    'CA-125'                => 'CA-125',
    'CEA'                   => 'CEA',
    'PT'                    => 'PT',
    'INR'                   => 'INR',
    'aPTT'                  => 'aPTT',

    // Common things you showed on screen
    'idrar tahlili' => 'Urinalysis',
    'İdrar tahlili' => 'Urinalysis',
    'Böbrek fonksiyon testleri' => 'Kidney Function Tests',
];

// Status mapping (DB -> display)
function mapStatusLabel(string $dbStatus): string {
    $s = mb_strtolower(trim($dbStatus));
    if ($s === 'istek' || $s === 'requested') return 'Requested';
    if ($s === 'tamamlandi' || $s === 'completed' || $s === 'done') return 'Completed';
    if ($s === 'iptal' || $s === 'cancelled' || $s === 'canceled') return 'Cancelled';
    return 'Requested';
}

function mapStatusClass(string $dbStatus): string {
    $s = mb_strtolower(trim($dbStatus));
    if ($s === 'tamamlandi' || $s === 'completed' || $s === 'done') return 'status-completed';
    if ($s === 'iptal' || $s === 'cancelled' || $s === 'canceled') return 'status-cancelled';
    return 'status-pending';
}

// Fetch tests
$sql = "
    SELECT 
        t.id,
        t.test_name,
        t.status,
        t.requested_at,
        d.name AS doctor_name
    FROM tests t
    LEFT JOIN users d ON t.doctor_id = d.id
    WHERE t.patient_id = :pid
    ORDER BY t.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute([':pid' => $patientId]);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$backFallback = 'patient_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Tests</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root{
            --primary:#2563eb;
            --primary-soft:#e3f2fd;
            --danger:#ef4444;
            --card-radius:18px;
            --shadow-soft:0 14px 30px rgba(15,23,42,0.12);
        }
        *{ box-sizing:border-box; }

        body{
            margin:0;
            padding:0;
            font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;
            background:radial-gradient(circle at top left,#e3f2fd 0,#f4f6f9 40%,#f4f6f9 100%);
            color:#111827;
        }

        .navbar {
            background:#ffffffaa;
            backdrop-filter: blur(10px);
            border-bottom:1px solid rgba(148,163,184,0.35);
            color: #111827;
            padding: 10px 32px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            position:sticky;
            top:0;
            z-index:20;
        }
        .navbar-left{
            font-weight:700;
            letter-spacing:.03em;
            font-size:18px;
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
        .nav-btn:hover{
            transform: translateY(-1px);
            filter:brightness(1.05);
        }
        .nav-btn.logout{
            border:1px solid rgba(220,38,38,0.25);
            background: rgba(220,38,38,0.08);
            color:#dc2626;
        }

        .page{
            max-width:1200px;
            margin:32px auto 50px;
            padding:0 18px 30px;
        }

        .tests-card{
            background:#ffffff;
            border-radius:var(--card-radius);
            box-shadow:var(--shadow-soft);
            border:1px solid rgba(148,163,184,0.25);
            padding:26px 32px 30px;
        }

        .tests-header{
            margin-bottom:18px;
        }
        .tests-title{
            font-size:26px;
            margin:0 0 4px;
            display:flex;
            align-items:center;
            gap:10px;
            letter-spacing:.02em;
        }
        .tests-title span.icon{
            width:30px;
            height:30px;
            border-radius:999px;
            background:var(--primary-soft);
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-size:18px;
            color:var(--primary);
        }
        .tests-sub{
            margin:0;
            font-size:14px;
            color:#6b7280;
        }

        .tests-table{
            width:100%;
            border-collapse:collapse;
            margin-top:18px;
            font-size:14px;
        }
        .tests-table thead{
            background:#f3f4f6;
        }
        .tests-table th,
        .tests-table td{
            padding:10px 12px;
            text-align:left;
            border-bottom:1px solid #e5e7eb;
        }
        .tests-table th{
            font-weight:600;
            color:#4b5563;
            font-size:13px;
        }
        .tests-table tbody tr:hover{
            background:#f9fafb;
        }

        .status-pill{
            display:inline-flex;
            align-items:center;
            padding:3px 10px;
            border-radius:999px;
            font-size:12px;
            font-weight:600;
        }
        .status-pending{
            background:rgba(234,179,8,0.10);
            color:#92400e;
        }
        .status-completed{
            background:rgba(22,163,74,0.10);
            color:#166534;
        }
        .status-cancelled{
            background:rgba(239,68,68,0.10);
            color:#b91c1c;
        }

        .empty-state{
            margin-top:22px;
            padding:18px 16px;
            border-radius:16px;
            background:#f9fafb;
            border:1px dashed #d1d5db;
            font-size:14px;
            color:#6b7280;
        }

        .back-link{
            display:inline-flex;
            align-items:center;
            gap:6px;
            margin-top:18px;
            font-size:14px;
            color:#2563eb;
            text-decoration:none;
        }
        .back-link:hover{
            text-decoration:underline;
        }

        @media (max-width:768px){
            .tests-card{ padding:22px 18px 24px; }
            .tests-title{ font-size:22px; }
            .tests-table{ font-size:13px; }
            .tests-table th, .tests-table td{ padding:8px 8px; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-left">Healthcare Record System</div>

    <div class="navbar-right">
        <span><?php echo htmlspecialchars($patientName); ?> (Patient)</span>

        <!-- Back button -->
        <a class="nav-btn"
           href="<?php echo htmlspecialchars($backFallback); ?>"
           onclick="if (window.history.length > 1) { window.history.back(); return false; }">
            ← Back
        </a>

        <a class="nav-btn logout" href="logout.php">Log out</a>
    </div>
</div>

<div class="page">
    <div class="tests-card">
        <div class="tests-header">
            <h1 class="tests-title">
                <span class="icon">🧪</span>
                My Tests
            </h1>
            <p class="tests-sub">
                Here you can see all tests ordered for you and their latest status.
            </p>
        </div>

        <?php if (count($tests) === 0): ?>
            <div class="empty-state">
                You do not have any recorded tests yet.
            </div>
        <?php else: ?>
            <table class="tests-table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Test Name</th>
                    <th>Doctor</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tests as $t): ?>
                    <?php
                        $rawName = (string)($t['test_name'] ?? '');
                        $displayName = $testNameMap[$rawName] ?? $rawName;

                        $rawStatus = (string)($t['status'] ?? 'istek');
                        $statusLabel = mapStatusLabel($rawStatus);
                        $statusClass = mapStatusClass($rawStatus);

                        $dateStr = '—';
                        if (!empty($t['requested_at'])) {
                            $dateStr = date('Y-m-d H:i', strtotime($t['requested_at']));
                        }

                        $doctorName = $t['doctor_name'] ?? '—';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($dateStr); ?></td>
                        <td><?php echo htmlspecialchars($displayName); ?></td>
                        <td><?php echo htmlspecialchars($doctorName); ?></td>
                        <td>
                            <span class="status-pill <?php echo htmlspecialchars($statusClass); ?>">
                                <?php echo htmlspecialchars($statusLabel); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <a href="patient_dashboard.php" class="back-link">
            ← Back to Patient Dashboard
        </a>
    </div>
</div>

</body>
</html>