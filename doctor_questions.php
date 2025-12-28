<?php
session_start();
require 'db.php';

// Only allow doctor
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'doctor') {
    header("Location: login.php");
    exit;
}

$doctorId   = (int)$_SESSION['user_id'];
$doctorName = $_SESSION['name'] ?? 'Doctor';

// Questions for this doctor
$sql = "
    SELECT q.id,
           q.question_text,
           q.answer_text,
           q.status,
           q.created_at,
           q.answered_at,
           p.name AS patient_name
    FROM questions q
    JOIN users p ON q.patient_id = p.id
    WHERE q.doctor_id = :did
    ORDER BY q.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute([':did' => $doctorId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Hasta Soruları</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
            color:#111827;
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
            max-width: 1000px;
            margin: 20px auto;
            padding: 0 15px;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 15px 18px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            border-bottom: 1px solid #eee;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f0f3f7;
            font-weight: 700;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            color: white;
            font-weight:700;
            text-transform:capitalize;
        }
        .badge.pending { background: #f9a825; }
        .badge.answered { background: #43a047; }

        .actions a {
            font-size: 12px;
            margin-right: 8px;
            text-decoration:none;
            font-weight:700;
            color:#1565c0;
        }
        .actions a:hover{ text-decoration:underline; }
    </style>
</head>
<body>

<div class="navbar">
    <div><strong>Healthcare Record System</strong></div>

    <div class="nav-right">
        <span style="font-weight:700;">Dr. <?php echo htmlspecialchars($doctorName); ?></span>

        <!-- ✅ Dashboard yerine Back -->
        <a class="pill" href="doctor_dashboard.php">← Back</a>

        <a class="pill logout" href="logout.php">Çıkış</a>
    </div>
</div>

<div class="container">
    <h2>Hastalardan Gelen Sorular</h2>

    <div class="card">
        <?php if (count($questions) === 0): ?>
            <p>Şu anda size yöneltilmiş soru bulunmuyor.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Hasta</th>
                    <th>Soru</th>
                    <th>Durum</th>
                    <th>Oluşturulma</th>
                    <th>İşlem</th>
                </tr>

                <?php foreach ($questions as $q): ?>
                    <?php
                        $status = strtolower((string)($q['status'] ?? 'pending'));
                        if ($status !== 'answered') $status = 'pending'; // güvenli
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($q['patient_name']); ?></td>

                        <td>
                            <?php echo nl2br(htmlspecialchars(mb_strimwidth((string)$q['question_text'], 0, 80, '...'))); ?>
                        </td>

                        <td>
                            <span class="badge <?php echo $status; ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </td>

                        <td><?php echo htmlspecialchars($q['created_at']); ?></td>

                        <td class="actions">
                            <a href="answer_question.php?id=<?php echo (int)$q['id']; ?>">Görüntüle / Cevapla</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
