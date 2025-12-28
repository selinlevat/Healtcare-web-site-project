<?php
session_start();
require 'db.php';

// Only doctor
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'doctor') {
    header("Location: login.php");
    exit;
}

$doctorId   = (int)$_SESSION['user_id'];
$doctorName = $_SESSION['name'] ?? 'Doctor';

$appointmentId = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
if ($appointmentId <= 0) {
    die("Invalid appointment.");
}

/* -------------------- Helpers -------------------- */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function status_en($s){
    $s = strtolower(trim((string)$s));
    if ($s === 'istek') return 'Requested';
    if ($s === 'tamamlandi') return 'Completed';
    if ($s === 'iptal') return 'Cancelled';
    return ucfirst($s);
}

function test_name_en($name){
    $name = trim((string)$name);

    // Turkish -> English mapping (for old records + display)
    $map = [
        'Hemogram (Tam Kan Sayımı)' => 'Complete Blood Count (CBC)',
        'Sedimentasyon (ESR)'       => 'ESR (Erythrocyte Sedimentation Rate)',
        'Sedimentasyon (ESR) '      => 'ESR (Erythrocyte Sedimentation Rate)',
        'CRP'                       => 'CRP',
        'Ferritin'                  => 'Ferritin',
        'Vitamin B12'               => 'Vitamin B12',
        'D Vitamini'                => 'Vitamin D',
        'Açlık Kan Şekeri (Glukoz)'  => 'Fasting Blood Glucose',
        'HbA1c'                     => 'HbA1c',
        'Üre'                       => 'Urea',
        'Kreatinin'                 => 'Creatinine',
        'AST'                       => 'AST',
        'ALT'                       => 'ALT',
        'ALP'                       => 'ALP',
        'GGT'                       => 'GGT',
        'Kolesterol'                => 'Total Cholesterol',
        'LDL'                       => 'LDL Cholesterol',
        'HDL'                       => 'HDL Cholesterol',
        'Trigliserid'               => 'Triglycerides',

        'TSH'                       => 'TSH',
        'T3'                        => 'T3',
        'T4'                        => 'T4',
        'Anti-TPO'                  => 'Anti-TPO',
        'Anti-TG'                   => 'Anti-TG',
        'FSH'                       => 'FSH',
        'LH'                        => 'LH',
        'Östrojen'                  => 'Estrogen',
        'Progesteron'               => 'Progesterone',
        'Testosteron'               => 'Testosterone',
        'Prolaktin'                 => 'Prolactin',

        'Tam idrar tahlili'         => 'Urinalysis (Complete)',
        'İdrar kültürü'             => 'Urine Culture',
        'Gaita testi'               => 'Stool Test',
        'Gaitada gizli kan'         => 'Fecal Occult Blood Test',
        'Parazit incelemesi'        => 'Parasite Examination',

        'Prokalsitonin'             => 'Procalcitonin',
        'Hepatit B'                 => 'Hepatitis B',
        'Hepatit C'                 => 'Hepatitis C',
        'HIV'                       => 'HIV',
        'COVID-19'                  => 'COVID-19',
        'TORCH testleri'            => 'TORCH Panel',

        'Röntgen'                   => 'X-Ray',
        'Ultrason (USG)'            => 'Ultrasound (USG)',
        'MR (Manyetik Rezonans)'    => 'MRI (Magnetic Resonance Imaging)',
        'Bilgisayarlı Tomografi (BT)'=> 'CT (Computed Tomography)',
        'EKG'                       => 'ECG (EKG)',
        'EKO'                       => 'Echocardiography (ECHO)',

        'Alerji testleri (IgE)'     => 'Allergy Tests (IgE)',
        'PSA'                       => 'PSA',
        'CA-125'                    => 'CA-125',
        'CEA'                       => 'CEA',
        'PT'                        => 'PT',
        'INR'                       => 'INR',
        'aPTT'                      => 'aPTT',

        // Some common variants users typed
        'idrar tahlili'             => 'Urinalysis',
        'Tam idrar tahlili'         => 'Urinalysis (Complete)',
        'Böbrek fonksiyon testleri' => 'Kidney Function Tests',
        'Karaciğer fonksiyon testleri' => 'Liver Function Tests',
        'Kan şekeri'                => 'Blood Glucose',
        'İdrar tahlili'             => 'Urinalysis',
    ];

    return $map[$name] ?? $name; // if already English or unknown, keep as is
}

/* -------------------- Appointment + Patient -------------------- */
$sqlAppt = "
    SELECT a.id, a.patient_id, a.doctor_id, a.hospital_name, a.department,
           a.appointment_date, a.appointment_time, a.status,
           p.name AS patient_name, p.email AS patient_email, p.phone AS patient_phone
    FROM appointments a
    JOIN users p ON p.id = a.patient_id
    WHERE a.id = :aid AND a.doctor_id = :did
    LIMIT 1
";
$stmtAppt = $conn->prepare($sqlAppt);
$stmtAppt->execute([':aid' => $appointmentId, ':did' => $doctorId]);
$appt = $stmtAppt->fetch(PDO::FETCH_ASSOC);

if (!$appt) {
    die("Appointment not found or not assigned to you.");
}

$patientId   = (int)$appt['patient_id'];
$patientName = $appt['patient_name'] ?? 'Patient';

/* -------------------- Test Catalog (English) -------------------- */
$testCatalog = [
    'BLOOD TESTS' => [
        'Complete Blood Count (CBC)',
        'ESR (Erythrocyte Sedimentation Rate)',
        'CRP',
        'Ferritin',
        'Vitamin B12',
        'Vitamin D',
        'Fasting Blood Glucose',
        'HbA1c',
        'Urea',
        'Creatinine',
        'AST',
        'ALT',
        'ALP',
        'GGT',
        'Total Cholesterol',
        'LDL Cholesterol',
        'HDL Cholesterol',
        'Triglycerides'
    ],
    'HORMONE TESTS' => [
        'TSH','T3','T4','Anti-TPO','Anti-TG','FSH','LH',
        'Estrogen','Progesterone','Testosterone','Prolactin'
    ],
    'URINE & STOOL TESTS' => [
        'Urinalysis (Complete)',
        'Urine Culture',
        'Stool Test',
        'Fecal Occult Blood Test',
        'Parasite Examination'
    ],
    'INFECTION TESTS' => [
        'CRP',
        'Procalcitonin',
        'Hepatitis B',
        'Hepatitis C',
        'HIV',
        'COVID-19',
        'TORCH Panel'
    ],
    'IMAGING TESTS' => [
        'X-Ray',
        'Ultrasound (USG)',
        'MRI (Magnetic Resonance Imaging)',
        'CT (Computed Tomography)',
        'ECG (EKG)',
        'Echocardiography (ECHO)'
    ],
    'SPECIAL TESTS' => [
        'Allergy Tests (IgE)',
        'PSA',
        'CA-125',
        'CEA',
        'PT',
        'INR',
        'aPTT'
    ],
    'COMMON CHECK-UP' => [
        'Complete Blood Count (CBC)',
        'Blood Glucose',
        'Total Cholesterol',
        'Liver Function Tests',
        'Kidney Function Tests',
        'Urinalysis (Complete)'
    ]
];

// remove duplicates inside each category (optional)
foreach ($testCatalog as $k => $arr) {
    $testCatalog[$k] = array_values(array_unique($arr));
}

/* -------------------- Actions (POST) -------------------- */
$errors  = [];
$success = null;

// Cancel test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_test_id'])) {
    $cancelId = (int)($_POST['cancel_test_id'] ?? 0);
    if ($cancelId > 0) {
        $sqlCancel = "
            UPDATE tests
            SET status = 'iptal'
            WHERE id = :tid AND doctor_id = :did AND patient_id = :pid AND status = 'istek'
            LIMIT 1
        ";
        $st = $conn->prepare($sqlCancel);
        $st->execute([':tid' => $cancelId, ':did' => $doctorId, ':pid' => $patientId]);
        $success = "Test request cancelled.";
    }
}

// Request selected tests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_tests'])) {
    $selected = $_POST['selected_tests'] ?? [];
    $details  = trim($_POST['details'] ?? '');

    if (!is_array($selected) || count($selected) === 0) {
        $errors[] = "Please select at least one test.";
    } else {
        // Insert each selected test
        $sqlIns = "
            INSERT INTO tests (patient_id, doctor_id, test_name, details, requested_by, status)
            VALUES (:pid, :did, :tname, :details, :rby, 'istek')
        ";
        $ins = $conn->prepare($sqlIns);

        foreach ($selected as $tname) {
            $tname = trim((string)$tname);
            if ($tname === '') continue;

            $ins->execute([
                ':pid'     => $patientId,
                ':did'     => $doctorId,
                ':tname'   => $tname,          // store in English
                ':details' => $details !== '' ? $details : null,
                ':rby'     => $doctorName
            ]);
        }

        $success = "Selected tests requested successfully.";
    }
}

/* -------------------- Load requested tests (patient + doctor) -------------------- */
$sqlTests = "
    SELECT id, test_name, details, requested_at, status, result
    FROM tests
    WHERE patient_id = :pid AND doctor_id = :did
    ORDER BY requested_at DESC, id DESC
";
$stmtT = $conn->prepare($sqlTests);
$stmtT->execute([':pid' => $patientId, ':did' => $doctorId]);
$requestedTests = $stmtT->fetchAll(PDO::FETCH_ASSOC);

// Back button fallback
$backFallback = 'doctor_dashboard.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Appointment Details</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    :root{
      --bg:#f3f4f6;
      --card:#ffffff;
      --muted:#6b7280;
      --text:#111827;
      --line:#e5e7eb;
      --primary:#1f4d8f;
      --danger:#dc2626;
      --success:#16a34a;
      --radius:18px;
      --shadow: 0 10px 30px rgba(17,24,39,.08);
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--text)}
    .topbar{
      height:60px; display:flex; align-items:center; justify-content:space-between;
      padding:0 18px; background:#37474f; color:#fff;
    }
    .topbar a{color:#fff;text-decoration:none;margin-left:14px;font-weight:700}
    .wrap{max-width:1200px;margin:26px auto;padding:0 18px}
    .grid{display:grid;grid-template-columns: 1.35fr 1fr;gap:18px;align-items:start}
    .card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);border:1px solid rgba(229,231,235,.9);overflow:hidden}
    .card-pad{padding:18px}
    h2{margin:0 0 10px;font-size:20px}
    .meta{color:var(--muted);font-size:14px;line-height:1.5}
    .badge{
      display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;
      background:#eef2ff;color:#1e40af;font-weight:800;font-size:12px;margin-left:10px
    }
    .divider{height:1px;background:var(--line);margin:14px 0}
    .alert{border-radius:12px;padding:10px 12px;font-size:14px;border:1px solid var(--line);margin-bottom:10px}
    .alert.ok{background:#ecfdf5;border-color:#bbf7d0;color:#065f46}
    .alert.err{background:#fff1f2;border-color:#fecdd3;color:#9f1239}
    .test-item{
      border:1px solid var(--line);border-radius:14px;padding:14px 14px;margin-bottom:12px;
      display:flex;justify-content:space-between;gap:14px;align-items:flex-start
    }
    .test-item h4{margin:0 0 6px;font-size:16px}
    .test-item .small{color:var(--muted);font-size:13px}
    .btn-danger{
      border:none;border-radius:999px;background:rgba(220,38,38,.12);color:var(--danger);
      padding:10px 14px;font-weight:900;cursor:pointer
    }

    /* pill buttons */
    .pill-group{display:flex;flex-wrap:wrap;gap:10px;margin:10px 0 14px}
    .pill{
      position:relative; display:inline-flex; align-items:center; gap:10px;
      border:1px solid #cbd5e1; background:#fff; color:#0f172a;
      border-radius:999px; padding:10px 14px; cursor:pointer; font-weight:800; font-size:13px;
      user-select:none;
    }
    .pill input{position:absolute;opacity:0;pointer-events:none}
    .pill .dot{
      width:14px;height:14px;border-radius:999px;border:2px solid #9ca3af;display:inline-block
    }
    .pill input:checked + .dot{
      border-color: var(--primary);
      box-shadow: inset 0 0 0 4px var(--primary);
    }

    .field label{display:block;font-size:13px;color:var(--muted);font-weight:800;margin:0 0 6px}
    .input{width:100%;border:1px solid var(--line);border-radius:12px;background:#f9fafb;padding:12px 12px;outline:none;font-size:14px}
    textarea.input{min-height:90px;resize:vertical}
    .btn-primary{
      display:inline-flex;align-items:center;justify-content:center;
      width:100%; margin-top:10px;
      border:none;border-radius:999px;background:var(--primary);color:#fff;
      padding:12px 14px;font-weight:900;cursor:pointer
    }

    @media (max-width: 980px){
      .grid{grid-template-columns:1fr}
    }
  </style>
</head>
<body>

<div class="topbar">
  <div style="font-weight:900;">Healthcare Record System</div>
  <div>
    <?php echo e($doctorName); ?> (Doctor)
    <a href="<?php echo e($backFallback); ?>"
       onclick="if (window.history.length > 1) { window.history.back(); return false; }">← Back</a>
    <a href="logout.php">Log out</a>
  </div>
</div>

<div class="wrap">
  <div class="grid">

    <!-- LEFT: Appointment + Requested tests -->
    <div class="card">
      <div class="card-pad">
        <h2>Appointment Details</h2>
        <div class="meta">
          <div><strong>Patient:</strong> <?php echo e($patientName); ?></div>
          <div>
            <strong>Date/Time:</strong>
            <?php echo e($appt['appointment_date'] . ' ' . substr($appt['appointment_time'], 0, 5)); ?>
            <span class="badge"><?php echo e(ucfirst($appt['status'])); ?></span>
          </div>
          <div><strong>Hospital:</strong> <?php echo e($appt['hospital_name'] ?? '—'); ?> / <?php echo e($appt['department'] ?? '—'); ?></div>
          <div><strong>Contact:</strong> <?php echo e($appt['patient_email'] ?? '—'); ?> • <?php echo e($appt['patient_phone'] ?? '—'); ?></div>
        </div>

        <div class="divider"></div>

        <h2 style="margin-top:0;">Requested Tests</h2>

        <?php if (!empty($requestedTests)): ?>
          <?php foreach ($requestedTests as $t): ?>
            <div class="test-item">
              <div>
                <h4><?php echo e(test_name_en($t['test_name'])); ?></h4>
                <div class="small">
                  <strong>Status:</strong> <?php echo e(status_en($t['status'])); ?>
                  <?php if (!empty($t['requested_at'])): ?>
                    • <?php echo e($t['requested_at']); ?>
                  <?php endif; ?>
                </div>
                <?php if (!empty($t['details'])): ?>
                  <div class="small" style="margin-top:6px;"><strong>Details:</strong> <?php echo e($t['details']); ?></div>
                <?php endif; ?>
                <?php if (!empty($t['result'])): ?>
                  <div class="small" style="margin-top:6px;"><strong>Result:</strong> <?php echo e($t['result']); ?></div>
                <?php endif; ?>
              </div>

              <?php if (($t['status'] ?? '') === 'istek'): ?>
                <form method="post" style="margin:0;">
                  <input type="hidden" name="cancel_test_id" value="<?php echo (int)$t['id']; ?>">
                  <button class="btn-danger" type="submit">Cancel</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="meta">No tests requested yet.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIGHT: Request tests -->
    <div class="card">
      <div class="card-pad">
        <h2>Request Tests</h2>

        <?php if (!empty($errors)): ?>
          <div class="alert err">
            <?php foreach ($errors as $er) echo e($er)."<br>"; ?>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert ok"><?php echo e($success); ?></div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="request_tests" value="1">

          <?php foreach ($testCatalog as $section => $items): ?>
            <div style="font-weight:900;margin-top:14px;"><?php echo e($section); ?></div>
            <div class="pill-group">
              <?php foreach ($items as $item): ?>
                <label class="pill">
                  <input type="checkbox" name="selected_tests[]" value="<?php echo e($item); ?>">
                  <span class="dot"></span>
                  <span><?php echo e($item); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>

          <div class="field" style="margin-top:12px;">
            <label for="details">Additional details (optional)</label>
            <textarea class="input" id="details" name="details" placeholder="e.g., 2 tubes of blood, chest MRI, etc."></textarea>
          </div>

          <button class="btn-primary" type="submit">Request Selected Tests</button>
        </form>

      </div>
    </div>

  </div>
</div>

</body>
</html>
