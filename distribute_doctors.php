<?php
// distribute_doctors.php
// Doktorların hospital_name ve department alanlarını,
// her hastane + bölüm kombinasyonu için farklı olacak şekilde dağıtır.
// ÇALIŞTIKTAN SONRA BU DOSYAYI SİLEBİLİRSİN.

require 'db.php';

// Book appointment sayfasındakiyle aynı bölümler:
$departments = [
    'Cardiology',
    'Neurology',
    'Internal Medicine',
    'Ophthalmology',
    'Dermatology',
    'Orthopedics',
    'Pediatrics',
    'General Surgery',
    'Psychiatry',
    'Gynecology and Obstetrics'
];

// Frontend’de kullandığın hastane isimleri (cityDistrictHospitals ile uyumlu OLMAK ZORUNDA):
$hospitals = [
    // ANKARA
    'Ankara City Hospital',
    'Çankaya State Hospital',
    'Keçiören Training and Research Hospital',
    'Yenimahalle State Hospital',
    'Etimesgut State Hospital',

    // İSTANBUL
    'Acıbadem Maslak Hospital',
    'Memorial Şişli Hospital',
    'Şişli Etfal Training and Research Hospital',
    'American Hospital',
    'Acıbadem Kadıköy Hospital',
    'Kadıköy State Hospital',
    'Istanbul University Hospital',
    'Beyoğlu State Hospital',
    'Bakırköy State Hospital',

    // İZMİR
    'Ege University Hospital',
    'Dokuz Eylül University Hospital',
    'Karşıyaka State Hospital',
    'Bornova State Hospital',

    // ANTALYA
    'Antalya Training and Research Hospital',
    'Akdeniz University Hospital'
    // İstersen buraya başka hastaneler de ekleyebilirsin
];

// 1) Tüm doktorların id'lerini al
$docStmt = $conn->query("
    SELECT id
    FROM users
    WHERE role = 'doctor'
    ORDER BY id
");
$doctorIds = $docStmt->fetchAll(PDO::FETCH_COLUMN);

if (!$doctorIds) {
    echo "Hiç doctor kullanıcısı bulunamadı.\n";
    exit;
}

$totalDoctors   = count($doctorIds);
$totalHospitals = count($hospitals);
$totalDepts     = count($departments);

echo "Toplam doktor: $totalDoctors Hastane sayısı: $totalHospitals Bölüm sayısı: $totalDepts<br>\n";

// 2) Her hastane + bölüm kombinasyonu için sırayla doktor ata
//    index -> (hastaneIndex, bölümIndex)
//    hastaneIndex  = index % totalHospitals
//    bölümIndex    = intdiv(index, totalHospitals) % totalDepts

$update = $conn->prepare("
    UPDATE users
    SET hospital_name = :hospital,
        department    = :department
    WHERE id = :id
");

$index = 0;

foreach ($doctorIds as $id) {
    $hospitalIndex = $index % $totalHospitals;
    // HATA DÜZELTİLDİ: intdiv($index, $totalHospitals)
    $deptIndex     = intdiv($index, $totalHospitals) % $totalDepts;

    $hospitalName  = $hospitals[$hospitalIndex];
    $department    = $departments[$deptIndex];

    $update->execute([
        ':hospital'   => $hospitalName,
        ':department' => $department,
        ':id'         => $id
    ]);

    $index++;
}

echo "Bitti. $totalDoctors doktor için hospital_name ve department güncellendi.\n";
