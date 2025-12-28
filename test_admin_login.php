<?php
// admin login neden olmuyor, onu test eden dosya
require 'db.php';

$email    = 'admin@example.com'; // Burayı admin emailine göre değiştir
$password = '123456';            // Girdiğini düşündüğün şifre

echo "<pre>";

echo "Test edilen e-posta: $email\n";
echo "Test edilen şifre : $password\n\n";

$sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "SONUÇ: Bu e-posta ile kullanıcı BULUNAMADI.\n\n";
    echo "users tablosunda email aynen böyle mi yazılı, phpMyAdmin'den kontrol et.\n";
    exit;
}

echo "Veritabanından gelen kullanıcı kaydı:\n";
print_r($user);
echo "\n";

if (password_verify($password, $user['password_hash'])) {
    echo "SONUÇ: ŞİFRE DOĞRU. (password_verify TRUE döndü)\n";
} else {
    echo "SONUÇ: ŞİFRE YANLIŞ. (password_verify FALSE döndü)\n";
}

echo "\nRol alanı (role): " . $user['role'] . "\n";

echo "\nNot: Eğer burada 'ŞİFRE DOĞRU' yazıyorsa ama login.php 'E-posta veya şifre yanlış' diyorsa,\nlogin.php dosyandaki kodu tekrar kontrol etmelisin.\n";
