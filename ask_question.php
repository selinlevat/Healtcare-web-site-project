<?php
session_start();
require 'db.php';

// Only patient
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header('Location: login.php');
    exit;
}

$patientId   = (int)$_SESSION['user_id'];
$patientName = $_SESSION['name'] ?? 'Patient';

$error    = null;
$dbError  = null;
$sentFlag = isset($_GET['sent']);

// ✅ Back button fallback (history yoksa)
$backFallback = 'patient_dashboard.php';

/* -------------------------------------------------
   1) Patient appointments (with doctor)
   ------------------------------------------------- */
$apptSql = "
    SELECT 
        a.id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.hospital_name,
        d.id   AS doctor_id,
        d.name AS doctor_name
    FROM appointments a
    JOIN users d ON a.doctor_id = d.id
    WHERE a.patient_id = :pid
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
";
$stmtAppt = $conn->prepare($apptSql);
$stmtAppt->execute([':pid' => $patientId]);
$appointments = $stmtAppt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   2) POST -> insert question
   ------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $questionText  = trim($_POST['question_text'] ?? '');
    $appointmentId = $_POST['appointment_id'] ?? '';

    if ($questionText === '' || $appointmentId === '') {
        $error = 'Please select an appointment and type your question.';
    } else {
        try {
            // Verify appointment belongs to this patient
            $verifySql = "
                SELECT 
                    a.id,
                    d.id   AS doctor_id,
                    d.name AS doctor_name
                FROM appointments a
                JOIN users d ON a.doctor_id = d.id
                WHERE a.id = :aid
                  AND a.patient_id = :pid
                LIMIT 1
            ";
            $stmt = $conn->prepare($verifySql);
            $stmt->execute([
                ':aid' => $appointmentId,
                ':pid' => $patientId
            ]);
            $appt = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$appt) {
                $error = 'Invalid appointment selection.';
            } else {
                $doctorId = (int)$appt['doctor_id'];

                $insertSql = "
                    INSERT INTO questions (patient_id, doctor_id, question_text)
                    VALUES (:pid, :did, :qtext)
                ";
                $ins = $conn->prepare($insertSql);
                $ins->execute([
                    ':pid'   => $patientId,
                    ':did'   => $doctorId,
                    ':qtext' => $questionText
                ]);

                header('Location: ask_question.php?sent=1');
                exit;
            }
        } catch (PDOException $e) {
            $dbError = $e->getMessage();
        }
    }
}

/* -------------------------------------------------
   3) Previous questions
   ------------------------------------------------- */
$qSql = "
    SELECT 
        q.id,
        q.question_text,
        q.answer_text,
        q.created_at,
        d.name AS doctor_name
    FROM questions q
    JOIN users d ON q.doctor_id = d.id
    WHERE q.patient_id = :pid
    ORDER BY q.created_at DESC
";
$stmtQ = $conn->prepare($qSql);
$stmtQ->execute([':pid' => $patientId]);
$questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

// ✅ Form state (hata olunca seçim kaybolmasın)
$selectedAppointmentId = (string)($_POST['appointment_id'] ?? '');
$typedQuestionText     = (string)($_POST['question_text'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Questions</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root{
            --primary:#2563eb;
            --primary-soft:#e3f2fd;
            --danger:#ef4444;
            --success:#16a34a;
            --card-radius:18px;
            --shadow-soft:0 18px 40px rgba(15,23,42,0.18);
        }
        *{ box-sizing:border-box; }
        body{
            margin:0;
            padding:0;
            font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;
            background:radial-gradient(circle at top left,#e3f2fd 0,#f4f6f9 40%,#f4f6f9 100%);
            color:#111827;
        }

        .navbar{
            background:#ffffffaa;
            backdrop-filter:blur(10px);
            border-bottom:1px solid rgba(148,163,184,0.35);
            padding:10px 32px;
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

        /* ✅ Back/Logout pill */
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

        .page{
            max-width:1100px;
            margin:32px auto 40px;
            padding:0 18px 40px;
        }

        .card{
            background:#ffffff;
            border-radius:var(--card-radius);
            box-shadow:var(--shadow-soft);
            border:1px solid rgba(148,163,184,0.25);
            padding:26px 30px 28px;
        }

        .page-title{
            font-size:26px;
            margin:0 0 4px;
            display:flex;
            align-items:center;
            gap:10px;
        }
        .page-title .icon{
            width:32px;height:32px;
            border-radius:999px;
            background:var(--primary-soft);
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-size:18px;
            color:var(--primary);
        }
        .page-sub{
            margin:0;
            font-size:14px;
            color:#6b7280;
        }

        .section-title{
            margin:24px 0 8px;
            font-size:16px;
        }

        label{
            display:block;
            font-size:13px;
            font-weight:600;
            color:#374151;
            margin-bottom:6px;
        }

        select, textarea{
            width:100%;
            border-radius:16px;
            border:1px solid #d1d5db;
            padding:10px 14px;
            font-family:inherit;
            font-size:14px;
            background:#f9fafb;
            transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }
        select:focus, textarea:focus{
            outline:none;
            border-color:var(--primary);
            background:#ffffff;
            box-shadow:0 0 0 3px rgba(37,99,235,0.25);
        }
        textarea{
            resize:vertical;
            min-height:90px;
        }

        .btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:10px 22px;
            font-size:15px;
            font-weight:600;
            border-radius:999px;
            border:none;
            background:linear-gradient(135deg,#2563eb,#1d4ed8);
            color:#ffffff;
            cursor:pointer;
            box-shadow:0 12px 26px rgba(37,99,235,0.45);
            transition:transform .12s ease, box-shadow .12s ease, filter .12s ease;
            margin-top:14px;
        }
        .btn:hover{
            transform:translateY(-1px);
            filter:brightness(1.04);
            box-shadow:0 16px 32px rgba(37,99,235,0.50);
        }

        .alert{
            border-radius:999px;
            padding:8px 18px;
            font-size:13px;
            margin:14px 0;
        }
        .alert-error{
            background:rgba(248,113,113,0.08);
            border:1px solid rgba(248,113,113,0.7);
            color:#b91c1c;
        }
        .alert-success{
            background:rgba(22,163,74,0.08);
            border:1px solid rgba(22,163,74,0.7);
            color:#166534;
        }

        .error-box{
            margin-top:18px;
            border-radius:18px;
            background:#020617;
            color:#e5e7eb;
            padding:14px 18px;
            font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,"Liberation Mono","Courier New", monospace;
            font-size:12px;
            overflow:auto;
        }

        .question-list{ margin-top:10px; }
        .question-item{
            padding:10px 14px;
            border-radius:14px;
            border:1px solid #e5e7eb;
            margin-bottom:10px;
            background:#f9fafb;
        }
        .question-date{ font-size:12px; color:#6b7280; }
        .answer-label{ font-weight:600; font-size:13px; }

        /* ✅ Bottom back (optional) */
        .back-link{
            margin-top:18px;
            font-size:13px;
        }
        .back-link a{
            color:#2563eb;
            text-decoration:none;
            font-weight:700;
        }
        .back-link a:hover{ text-decoration:underline; }

        @media (max-width:768px){
            .card{ padding:22px 18px 22px; }
            .page-title{ font-size:22px; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-left">Healthcare Record System</div>

    <div class="navbar-right">
        <span><?php echo htmlspecialchars($patientName); ?> (Patient)</span>

        <!-- ✅ Dashboard link kaldırıldı -> Back eklendi -->
        <a class="nav-btn"
           href="<?php echo htmlspecialchars($backFallback); ?>"
           onclick="if (window.history.length > 1) { window.history.back(); return false; }">
            ← Back
        </a>

        <a class="nav-btn logout" href="logout.php">Log out</a>
    </div>
</div>

<div class="page">
    <div class="card">
        <h1 class="page-title">
            <span class="icon">💬</span>
            My Questions
        </h1>
        <p class="page-sub">
            You can select one of your appointments and send a question to that doctor.
        </p>

        <?php if ($sentFlag && !$error && !$dbError): ?>
            <div class="alert alert-success">Your question has been sent successfully.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($dbError): ?>
            <div class="error-box">
                Database error (show this to your developer):<br>
                <?php echo htmlspecialchars($dbError); ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title">Write your question</h2>

        <form method="post">
            <label for="appointment_id">Select Appointment</label>
            <select id="appointment_id" name="appointment_id">
                <option value="">-- Select Appointment --</option>

                <?php foreach ($appointments as $a): ?>
                    <?php
                        $hosp = $a['hospital_name'] ?: 'Hospital not specified';
                        $label = $a['appointment_date'] . ' ' . substr($a['appointment_time'],0,5)
                              . ' – ' . $hosp
                              . ' – Dr. ' . $a['doctor_name'];

                        $optId = (string)(int)$a['id'];
                        $sel = ($selectedAppointmentId !== '' && $selectedAppointmentId === $optId) ? 'selected' : '';
                    ?>
                    <option value="<?php echo (int)$a['id']; ?>" <?php echo $sel; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="question_text" style="margin-top:12px;">Your Question</label>
            <textarea id="question_text" name="question_text"
                      placeholder="Example: I have been using my medication for 3 years. Can I change the dose?"><?php
                echo htmlspecialchars($typedQuestionText);
            ?></textarea>

            <button type="submit" class="btn">Send Question</button>
        </form>

        <h2 class="section-title" style="margin-top:26px;">Previous Questions</h2>

        <div class="question-list">
            <?php if (count($questions) === 0): ?>
                <p style="font-size:14px;color:#6b7280;">You have not asked any questions yet.</p>
            <?php else: ?>
                <?php foreach ($questions as $q): ?>
                    <div class="question-item">
                        <div class="question-date">
                            <?php echo htmlspecialchars($q['created_at']); ?>
                            – Dr. <?php echo htmlspecialchars($q['doctor_name']); ?>
                        </div>
                        <div>
                            <strong>Question:</strong>
                            <?php echo nl2br(htmlspecialchars($q['question_text'])); ?>
                        </div>
                        <div class="answer">
                            <span class="answer-label">Answer: </span>
                            <?php if (!empty($q['answer_text'])): ?>
                                <?php echo nl2br(htmlspecialchars($q['answer_text'])); ?>
                            <?php else: ?>
                                <em>Not answered yet.</em>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ✅ Alt back link de history/back mantığı ile -->
        <div class="back-link">
            ← <a href="<?php echo htmlspecialchars($backFallback); ?>"
                 onclick="if (window.history.length > 1) { window.history.back(); return false; }">
                Back
            </a>
        </div>

    </div>
</div>

</body>
</html>
