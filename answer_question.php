<?php
session_start();
require 'db.php';

// Only doctors
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'doctor') {
    header("Location: login.php");
    exit;
}

$doctorId   = (int)$_SESSION['user_id'];
$doctorName = $_SESSION['name'] ?? 'Doctor';

$questionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = "";

// Question exists and belongs to this doctor?
$sql = "
    SELECT q.*, p.name AS patient_name
    FROM questions q
    JOIN users p ON q.patient_id = p.id
    WHERE q.id = :id AND q.doctor_id = :did
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->execute([
    ':id'  => $questionId,
    ':did' => $doctorId
]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    die("Bu soru bulunamadı veya size ait değil.");
}

// POST => save answer
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $answer_text = (string)($_POST['answer_text'] ?? '');

    if (trim($answer_text) !== '') {
        $sqlUpdate = "
            UPDATE questions
            SET answer_text = :answer_text,
                status = 'answered',
                answered_at = NOW()
            WHERE id = :id AND doctor_id = :did
        ";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->execute([
            ':answer_text' => $answer_text,
            ':id'          => $questionId,
            ':did'         => $doctorId
        ]);

        $message = "Cevabınız kaydedildi.";

        // refresh record for showing updated answer
        $stmt->execute([
            ':id'  => $questionId,
            ':did' => $doctorId
        ]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $message = "Lütfen bir cevap yazın.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Soruyu Cevapla</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
        }
        .navbar {
            background: #388e3c;
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-right{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
        }

        .pill{
            color:white;
            text-decoration:none;
            padding:7px 12px;
            border-radius:999px;
            border:1px solid rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.10);
            font-weight:700;
            font-size:13px;
        }
        .pill:hover{ filter:brightness(1.06); }

        .pill.logout{
            border:1px solid rgba(239,68,68,0.35);
            background: rgba(239,68,68,0.14);
        }

        .container {
            max-width: 700px;
            margin: 25px auto;
            background: white;
            padding: 20px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        textarea {
            width: 100%;
            height: 150px;
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
        button {
            margin-top: 10px;
            padding: 9px 14px;
            border: none;
            border-radius: 6px;
            background: #388e3c;
            color: white;
            cursor: pointer;
            font-weight:700;
        }
        .question-box {
            background: #f0f4c3;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 15px;
        }
        .message {
            margin-bottom: 10px;
            font-weight:700;
            color:#0f172a;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div><strong>Healthcare Record System</strong></div>

        <div class="nav-right">
            <span style="font-weight:700;">Dr. <?php echo htmlspecialchars($doctorName); ?></span>

            <!-- ✅ Back (Dashboard değil) -->
            <a class="pill" href="doctor_questions.php">← Back</a>

            <a class="pill logout" href="logout.php">Çıkış</a>
        </div>
    </div>

    <div class="container">
        <h2><?php echo htmlspecialchars($question['patient_name']); ?> - Soru Detayı</h2>

        <div class="question-box">
            <strong>Soru:</strong><br>
            <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
        </div>

        <?php if (!empty($question['answer_text'])): ?>
            <div class="question-box" style="background:#e3f2fd;">
                <strong>Mevcut Cevabınız:</strong><br>
                <?php echo nl2br(htmlspecialchars($question['answer_text'])); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <label><strong>Yeni Cevabınız / Güncelleme:</strong></label>
            <textarea name="answer_text"><?php echo htmlspecialchars($question['answer_text'] ?? ''); ?></textarea>
            <button type="submit">Kaydet</button>
        </form>
    </div>
</body>
</html>
