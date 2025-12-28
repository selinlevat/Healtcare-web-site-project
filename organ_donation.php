<?php
session_start();
require 'db.php';

// only patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patientId   = $_SESSION['user_id'];
$patientName = $_SESSION['name'] ?? 'Patient';

$message = null;

// Save preference
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $choice = isset($_POST['organ_donor']) ? (int)$_POST['organ_donor'] : 0;

    $sql = "UPDATE users SET organ_donor = :choice WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':choice' => $choice,
        ':id'     => $patientId
    ]);

    $message = "Your preference has been saved.";
}

// Get current preference
$sqlPref = "SELECT organ_donor FROM users WHERE id = :id";
$stmtPref = $conn->prepare($sqlPref);
$stmtPref->execute([':id' => $patientId]);
$rowPref  = $stmtPref->fetch(PDO::FETCH_ASSOC);
$current  = isset($rowPref['organ_donor']) ? (int)$rowPref['organ_donor'] : 0;

// ✅ Back button fallback (history yoksa)
$backFallback = 'patient_dashboard.php'; // senin projende dashboard_patient.php ise onu yaz
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Organ Donation Preference</title>
    <style>
        body{
            margin:0;
            font-family:Arial, sans-serif;
            background:#f4f6f9;
        }
        .navbar{
            background:#1976d2;
            color:#fff;
            padding:10px 18px;
            display:flex;
            justify-content:space-between;
            align-items:center;
        }
        .navbar-left{font-weight:bold;}

        .navbar-right{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            justify-content:flex-end;
        }

        /* ✅ NAV BUTTONS (Back + Logout) */
        .nav-btn{
            color:#fff;
            text-decoration:none;
            font-weight:700;
            padding:7px 12px;
            border-radius:999px;
            border:1px solid rgba(255,255,255,0.35);
            background: rgba(255,255,255,0.12);
            transition: transform .12s ease, filter .12s ease;
            display:inline-flex;
            align-items:center;
            gap:8px;
        }
        .nav-btn:hover{
            transform: translateY(-1px);
            filter:brightness(1.05);
        }
        .nav-btn.logout{
            border:1px solid rgba(255,255,255,0.35);
            background: rgba(0,0,0,0.12);
        }

        .page{
            max-width:1100px;
            margin:40px auto 60px;
            padding:0 20px;
        }

        .card{
            background:#fff;
            border-radius:14px;
            box-shadow:0 4px 14px rgba(0,0,0,0.08);
            padding:28px 32px 30px;
        }

        h1{
            margin-top:0;
            font-size:30px;
            color:#1f2933;
        }
        .lead{
            color:#555;
            margin-bottom:22px;
            line-height:1.5;
        }

        .question{
            font-size:18px;
            font-weight:600;
            margin-bottom:18px;
        }

        .radio-row{
            margin-bottom:10px;
            font-size:15px;
        }
        .radio-row input{
            margin-right:6px;
        }

        .btn-primary{
            margin-top:22px;
            padding:10px 26px;
            border-radius:22px;
            border:none;
            background:#1976d2;
            color:#fff;
            font-weight:600;
            cursor:pointer;
            box-shadow:0 2px 8px rgba(0,0,0,0.18);
        }
        .btn-primary:hover{background:#145ca3;}

        .message{
            margin-top:16px;
            font-size:14px;
            color:#2e7d32;
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-left">Healthcare Record System</div>
    <div class="navbar-right">
        <span><?php echo htmlspecialchars($patientName); ?> (Patient)</span>

        <!-- ✅ BACK BUTTON -->
        <a class="nav-btn"
           href="<?php echo htmlspecialchars($backFallback); ?>"
           onclick="if (window.history.length > 1) { window.history.back(); return false; }">
            ← Back
        </a>

        <a class="nav-btn logout" href="logout.php">Logout</a>
    </div>
</div>

<div class="page">
    <div class="card">
        <h1>Organ Donation Preference</h1>
        <p class="lead">
            Here you can choose whether you would like to be registered as an organ donor.
            You can update this preference at any time in the future.
        </p>

        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="question">Would you like to be an organ donor?</div>

            <label class="radio-row">
                <input type="radio" name="organ_donor" value="1" <?php echo $current === 1 ? 'checked' : ''; ?>>
                Yes, I want to be an organ donor.
            </label>

            <label class="radio-row">
                <input type="radio" name="organ_donor" value="0" <?php echo $current === 0 ? 'checked' : ''; ?>>
                No, I do not want to be an organ donor.
            </label>

            <button type="submit" class="btn-primary">Save Preference</button>
        </form>
    </div>
</div>

</body>
</html>
