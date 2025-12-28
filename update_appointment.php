<?php
session_start();
require 'db.php';

// Sadece hasta
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patientId = $_SESSION['user_id'];
$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$error = "";
$success = "";

// Önce randevuyu çek
$sqlGet = "
    SELECT id, appointment_date, appointment_time, status
    FROM appointments
    WHERE id = :id AND patient_id = :pid
    LIMIT 1
";
$stmtGet = $conn->prepare($sqlGet);
$stmtGet->execute([':id' => $appointmentId, ':pid' => $patientId]);
$appointment = $stmtGet->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    die("Bu randevu bulunamadı veya size ait değil.");
}

// Form gönderildiyse
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newDate = $_POST['appointment_date'] ?? '';
    $newTime = $_POST['appointment_time'] ?? '';

    if ($newDate === '' || $newTime === '') {
        $error = "Lütfen yeni tarih ve saat seçin.";
    } else {
        try {
            // Aynı doktora aynı tarih-saatte başka randevu var mı?
            // Önce doktor_id'yi al
            $sqlDoc = "SELECT doctor_id FROM appointments WHERE id = :id AND patient_id = :pid";
            $stmtDoc = $conn->prepare($sqlDoc);
            $stmtDoc->execute([':id' => $appointmentId, ':pid' => $patientId]);
            $rowDoc = $stmtDoc->fetch(PDO::FETCH_ASSOC);

            if (!$rowDoc) {
                $error = "Randevu bulunamadı.";
            } else {
                $doctorId = $rowDoc['doctor_id'];

                $sqlCheck = "
                    SELECT COUNT(*) AS c
                    FROM appointments
                    WHERE doctor_id = :doc
                      AND appointment_date = :adate
                      AND appointment_time = :atime
                      AND status IN ('pending','approved')
                      AND id <> :cur
                ";
                $stmtCheck = $conn->prepare($sqlCheck);
                $stmtCheck->execute([
                    ':doc' => $doctorId,
                    ':adate' => $newDate,
                    ':atime' => $newTime,
                    ':cur' => $appointmentId
                ]);
                $check = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($check && $check['c'] > 0) {
                    $error = "Seçtiğiniz tarih ve saatte bu doktor için başka randevu zaten var.";
                } else {
                    // Güncelle
                    $sqlUpdate = "
                        UPDATE appointments
                        SET appointment_date = :adate,
                            appointment_time = :atime
                        WHERE id = :id AND patient_id = :pid
                    ";
                    $stmtUpdate = $conn->prepare($sqlUpdate);
                    $stmtUpdate->execute([
                        ':adate' => $newDate,
                        ':atime' => $newTime,
                        ':id'    => $appointmentId,
                        ':pid'   => $patientId
                    ]);

                    $success = "Randevu saatiniz başarıyla güncellendi.";
                    // Tekrar güncel bilgiyi çek
                    $stmtGet->execute([':id' => $appointmentId, ':pid' => $patientId]);
                    $appointment = $stmtGet->fetch(PDO::FETCH_ASSOC);
                }
            }
        } catch (PDOException $e) {
            $error = "Güncelleme sırasında hata oluştu: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Randevu Güncelle</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 500px;
            margin: 40px auto;
            background: #fff;
            padding: 20px 24px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            margin-top: 0;
        }
        label {
            display: block;
            margin-top: 10px;
            margin-bottom: 4px;
        }
        input {
            width: 100%;
            padding: 7px 9px;
            margin-bottom: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
        button {
            margin-top: 10px;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            background: #1976d2;
            color: white;
            cursor: pointer;
        }
        .error { color: #c62828; margin-bottom: 8px; }
        .success { color: #2e7d32; margin-bottom: 8px; }
        a { font-size: 14px; text-decoration: none; }
    </style>
</head>
<body>

<div class="container">
    <h1>Randevu Saatini Güncelle</h1>

    <?php if (!empty($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <p class="success"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>

    <form method="POST" action="update_appointment.php?id=<?php echo (int)$appointmentId; ?>">
        <label>Yeni Tarih:</label>
        <input type="date" name="appointment_date"
               value="<?php echo htmlspecialchars($appointment['appointment_date']); ?>">

        <label>Yeni Saat:</label>
        <input type="time" name="appointment_time"
               value="<?php echo htmlspecialchars(substr($appointment['appointment_time'], 0, 5)); ?>">

        <button type="submit">Randevuyu Güncelle</button>
    </form>

    <p style="margin-top: 15px;">
        <a href="patient_dashboard.php">&laquo; Hasta Paneline Dön</a>
    </p>
</div>

</body>
</html>
