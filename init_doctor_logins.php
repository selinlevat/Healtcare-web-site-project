<?php
session_start();
require 'db.php';

// Default credentials for doctors
$defaultPassword = "Doctor@123";
$defaultHash = password_hash($defaultPassword, PASSWORD_DEFAULT);

// Find doctors without email / password_hash
$sql = "SELECT id, email, password_hash FROM users WHERE role='doctor'";
$stmt = $conn->prepare($sql);
$stmt->execute();
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;

foreach ($doctors as $d) {
    $id = (int)$d['id'];
    $email = $d['email'] ?? '';
    $hash  = $d['password_hash'] ?? '';

    $newEmail = $email;
    $needEmail = ($newEmail === null || trim($newEmail) === '');

    // Make unique simple email
    if ($needEmail) {
        $newEmail = "doctor{$id}@healthcare.local";
    }

    $needHash = ($hash === null || trim($hash) === '');

    if ($needEmail || $needHash) {
        $sqlUp = "UPDATE users
                  SET email = :email,
                      password_hash = :ph
                  WHERE id = :id AND role='doctor'";
        $stmtUp = $conn->prepare($sqlUp);
        $stmtUp->execute([
            ':email' => $newEmail,
            ':ph'    => $needHash ? $defaultHash : $hash,
            ':id'    => $id
        ]);
        $updated++;
    }
}

echo "DONE. Updated doctors: {$updated}<br>";
echo "Doctor login example: doctor1@healthcare.local / {$defaultPassword}";
