<?php
session_start();
require 'db.php';

// Sadece hasta girebilsin (istersen sonradan doctor/admin için genişletebilirsin)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

$error   = "";
$success = "";

// Mevcut kullanıcı bilgilerini çek
$sqlUser = "SELECT name, email, blood_group, phone, address FROM users WHERE id = :id LIMIT 1";
$stmtUser = $conn->prepare($sqlUser);
$stmtUser->execute([':id' => $userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Kullanıcı bulunamadı.");
}

$name       = $user['name'];
$email      = $user['email'];
$bloodGroup = $user['blood_group'];
$phone      = $user['phone'];
$address    = $user['address'];

// Form gönderildiyse
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nameNew       = isset($_POST['name']) ? trim($_POST['name']) : '';
    $emailNew      = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phoneNew      = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $addressNew    = isset($_POST['address']) ? trim($_POST['address']) : '';
    $bloodGroupNew = isset($_POST['blood_group']) ? trim($_POST['blood_group']) : '';

    // Zorunlu alanlar: ad, e-posta, telefon, adres
    if ($nameNew === '' || $emailNew === '' || $phoneNew === '' || $addressNew === '') {
        $error = "Ad Soyad, e-posta, telefon ve adres alanları boş bırakılamaz.";
    } else {
        try {
            $sqlUpdate = "
                UPDATE users
                SET name = :name,
                    email = :email,
                    phone = :phone,
                    address = :addr,
                    blood_group = :bg
                WHERE id = :id
            ";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':name'  => $nameNew,
                ':email' => $emailNew,
                ':phone' => $phoneNew,
                ':addr'  => $addressNew,
                ':bg'    => $bloodGroupNew !== '' ? $bloodGroupNew : null,
                ':id'    => $userId
            ]);

            $success = "Profil başarıyla güncellendi.";

            // Oturumdaki isim de güncellensin
            $_SESSION['name'] = $nameNew;

            // Ekranda güncel değerler gözüksün
            $name       = $nameNew;
            $email      = $emailNew;
            $phone      = $phoneNew;
            $address    = $addressNew;
            $bloodGroup = $bloodGroupNew !== '' ? $bloodGroupNew : null;

        } catch (PDOException $e) {
            $error = "Profil güncellenirken hata oluştu: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Profili Düzenle</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }
        .navbar {
            background: #1976d2;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar a {
            color: #fff;
            text-decoration: none;
            margin-left: 15px;
        }
        .container {
            max-width: 550px;
            margin: 30px auto;
            background: #fff;
            padding: 20px 24px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h2 {
            margin-top: 0;
        }
        label {
            display: block;
            margin-top: 10px;
            margin-bottom: 4px;
        }
        input, select, textarea {
            width: 100%;
            padding: 7px 9px;
            margin-bottom: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
            box-sizing: border-box;
            font-size: 14px;
        }
        textarea {
            min-height: 70px;
        }
        button {
            margin-top: 10px;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            background: #2e7d32;
            color: #fff;
            cursor: pointer;
        }
        .error {
            color: #d32f2f;
            margin-bottom: 10px;
        }
        .success {
            color: #2e7d32;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="navbar">
    <div><strong>Healthcare Record System</strong></div>
    <div>
        <?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?> (Hasta)
        <a href="patient_dashboard.php">Panele Dön</a>
        <a href="logout.php">Çıkış</a>
    </div>
</div>

<div class="container">
    <h2>Profili Düzenle</h2>

    <?php if (!empty($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <p class="success"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>

    <form method="POST" action="profile.php">
        <label>Ad Soyad:</label>
        <input type="text" name="name"
               value="<?php echo htmlspecialchars($name); ?>">

        <label>E-posta:</label>
        <input type="email" name="email"
               value="<?php echo htmlspecialchars($email); ?>">

        <label>Telefon Numarası:</label>
        <input type="text" name="phone"
               value="<?php echo htmlspecialchars($phone); ?>"
               placeholder="05xx xxx xx xx">

        <label>Adres:</label>
        <textarea name="address"
                  placeholder="Mahalle, cadde, sokak, apartman ..."><?php
            echo htmlspecialchars($address);
        ?></textarea>

        <label>Kan Grubu:</label>
        <select name="blood_group">
            <option value="">-- Seçiniz --</option>
            <?php
            $options = [
                '0 Rh+', '0 Rh-',
                'A Rh+', 'A Rh-',
                'B Rh+', 'B Rh-',
                'AB Rh+', 'AB Rh-'
            ];
            foreach ($options as $opt):
                $selected = ($bloodGroup === $opt) ? 'selected' : '';
            ?>
                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $selected; ?>>
                    <?php echo htmlspecialchars($opt); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Kaydet</button>
    </form>
</div>

</body>
</html>
