<?php
session_start();

// Eğer zaten giriş yapılmışsa rolüne göre direkt panele at
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'patient') {
        header("Location: patient_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] === 'doctor') {
        header("Location: doctor_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Healthcare Record Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #1976d2, #43a047);
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
        }
        .wrapper {
            max-width: 900px;
            width: 90%;
            background: rgba(255,255,255,0.96);
            color: #333;
            border-radius: 12px;
            padding: 25px 30px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.25);
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 20px;
        }
        @media (max-width: 800px) {
            .wrapper {
                grid-template-columns: 1fr;
            }
        }
        .left h1 {
            margin-top: 0;
        }
        .left p {
            line-height: 1.5;
        }
        .feature-list {
            margin-top: 10px;
            padding-left: 18px;
        }
        .feature-list li {
            margin-bottom: 4px;
        }
        .right {
            border-left: 1px solid #eee;
            padding-left: 15px;
        }
        @media (max-width: 800px) {
            .right {
                border-left: none;
                border-top: 1px solid #eee;
                padding-left: 0;
                padding-top: 15px;
            }
        }
        .role-box {
            margin-bottom: 15px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #f5f7fb;
        }
        .role-box h3 {
            margin: 0 0 4px 0;
        }
        .btn {
            display: inline-block;
            margin-top: 6px;
            margin-right: 6px;
            padding: 6px 10px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: #1976d2;
            color: white;
        }
        .btn-outline {
            background: white;
            color: #1976d2;
            border: 1px solid #1976d2;
        }
        .tag {
            display: inline-block;
            background: #e3f2fd;
            color: #1565c0;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            margin-right: 5px;
            margin-top: 3px;
        }
        .footer-text {
            font-size: 11px;
            margin-top: 10px;
            color: #666;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="left">
        <h1>Healthcare Record Management System</h1>
        <p>
            Bu sistemde hastalar, doktorlarıyla olan tüm süreçlerini tek yerden yönetebilir:
            randevular, tıbbi kayıtlar, laboratuvar sonuçları, reçeteler ve doktor notları.
        </p>
        <ul class="feature-list">
            <li>🔐 Güvenli giriş sistemi (hasta, doktor, admin rolleri)</li>
            <li>📅 Doktor randevusu alma ve randevu takibi</li>
            <li>📄 Tıbbi kayıtlar ve doktor notlarını görüntüleme</li>
            <li>💬 Hastadan doktora soru–cevap sistemi</li>
            <li>🧮 Admin paneli ile kullanıcı & randevu istatistikleri</li>
        </ul>

        <div style="margin-top: 10px;">
            <span class="tag">HTML</span>
            <span class="tag">CSS</span>
            <span class="tag">PHP (PDO)</span>
            <span class="tag">MySQL</span>
            <span class="tag">JavaScript</span>
        </div>

        <p class="footer-text">
            Not: Bu proje, ders kapsamında hasta kayıt ve randevu yönetimini simüle etmek için hazırlanmış örnek bir web uygulamasıdır.
        </p>
    </div>

    <div class="right">
        <div class="role-box">
            <h3>Hastalar</h3>
            <p>Randevularını görüntüleyip yeni randevu alabilir, tıbbi kayıtlarını ve doktor cevaplarını görebilir.</p>
            <a href="login.php" class="btn btn-primary">Hasta Girişi</a>
            <a href="register.php" class="btn btn-outline">Yeni Hasta Kaydı</a>
        </div>

        <div class="role-box">
            <h3>Doktorlar</h3>
            <p>Kendi randevularını, hastaların sorularını ve tıbbi kayıtları yönetebilir.</p>
            <a href="login.php" class="btn btn-primary">Doktor Girişi</a>
            <a href="register.php" class="btn btn-outline">Yeni Doktor Kaydı</a>
        </div>

        <div class="role-box">
            <h3>Admin</h3>
            <p>Kullanıcı ve randevu istatistiklerini görebilir, sistemi genel olarak izler.</p>
            <a href="login.php" class="btn btn-primary">Admin Girişi</a>
        </div>
    </div>
</div>
</body>
</html>

