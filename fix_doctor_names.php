<?php
// fix_doctor_names.php
// ÇALIŞTIKTAN SONRA SİLEBİLİRSİN

require 'db.php';

// Kullanılacak rastgele isim ve soyisim listeleri
$firstNames = [
    'Ahmet','Mehmet','Ayşe','Fatma','Elif','Zeynep','Ali','Hüseyin','Mustafa','Emre',
    'Burak','Can','Deniz','Selin','Gizem','Hakan','Serkan','Merve','Yasin','Cem',
    'Ece','İrem','Buse','Onur','Kerem','Berk','Dilara','Melis','Sena','Kaan',
    'Okan','Yasemin','Gökhan','Barış','Cansu','Gamze','Oğuz','Kübra','Sibel','Melike',
    'Özge','Derya','Hande','Tuğba','Oya','Nisa','Hale','Seda','Esra','Nazlı'
];

$lastNames = [
    'Yılmaz','Kaya','Demir','Çelik','Şahin','Yıldız','Yıldırım','Aydın','Öztürk','Arslan',
    'Doğan','Kılıç','Aslan','Çetin','Kurt','Koç','Kara','Aksoy','Yalçın','Taş',
    'Keskin','Polat','Özkan','Kaplan','Tekin','Güneş','Bulut','Özer','Işık','Erdem',
    'Güler','Kurtuluş','Uzun','Çakır','Kılıçoğlu','Özdemir','Bayraktar','Korkmaz','Erdoğan','Karaca',
    'Uçar','Özbay','Çağlar','Bozkurt','Ateş','Ergin','Toprak','Akın','Sezer','Balcı'
];

// 1) Sadece otomatik oluşturulan isimleri seç
//    (Dr. Auto ... ile başlayan doktorlar)
$stmt = $conn->query("
    SELECT id
    FROM users
    WHERE role = 'doctor'
      AND name LIKE 'Dr. Auto %'
");
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$doctors) {
    echo "Güncellenecek 'Dr. Auto ...' isimli doktor bulunamadı.";
    exit;
}

$update = $conn->prepare("UPDATE users SET name = :name WHERE id = :id");

// Aynı isimden çok fazla olmasın diye kullanılan isimleri takip edelim
$usedNames = [];

foreach ($doctors as $doc) {
    // Benzersiz bir isim üret
    while (true) {
        $first = $firstNames[array_rand($firstNames)];
        $last  = $lastNames[array_rand($lastNames)];
        $newName = "Dr. $first $last";

        if (!isset($usedNames[$newName])) {
            $usedNames[$newName] = true;
            break;
        }
    }

    $update->execute([
        ':name' => $newName,
        ':id'   => $doc['id']
    ]);
}

echo "Toplam " . count($doctors) . " doktor ismi güncellendi.";
