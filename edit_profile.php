<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'User';
$userRole = $_SESSION['role'] ?? '';

/* If your column names differ, change these:
   height, weight, blood_group, country, city
*/
$sql = "SELECT name, email, phone, address, height, weight, blood_group, country, city
        FROM users WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

$name       = $user['name'] ?? '';
$email      = $user['email'] ?? '';
$phone      = $user['phone'] ?? '';
$address    = $user['address'] ?? '';
$height     = $user['height'] ?? '';
$weight     = $user['weight'] ?? '';
$bloodGroup = $user['blood_group'] ?? '';
$country    = $user['country'] ?? 'TURKEY';
$city       = $user['city'] ?? 'ANKARA';

$errors  = [];
$success = null;

$showPasswordSection = false;

$cities = [
    'Adana','Adıyaman','Afyonkarahisar','Ağrı','Amasya','Ankara','Antalya','Artvin','Aydın',
    'Balıkesir','Bilecik','Bingöl','Bitlis','Bolu','Burdur','Bursa','Çanakkale','Çankırı','Çorum',
    'Denizli','Diyarbakır','Edirne','Elazığ','Erzincan','Erzurum','Eskişehir','Gaziantep','Giresun',
    'Gümüşhane','Hakkari','Hatay','Isparta','Mersin','İstanbul','İzmir','Kars','Kastamonu','Kayseri',
    'Kırklareli','Kırşehir','Kocaeli','Konya','Kütahya','Malatya','Manisa','Kahramanmaraş','Mardin',
    'Muğla','Muş','Nevşehir','Niğde','Ordu','Rize','Sakarya','Samsun','Siirt','Sinop','Sivas','Tekirdağ',
    'Tokat','Trabzon','Tunceli','Şanlıurfa','Uşak','Van','Yozgat','Zonguldak','Aksaray','Bayburt',
    'Karaman','Kırıkkale','Batman','Şırnak','Bartın','Ardahan','Iğdır','Yalova','Karabük','Kilis',
    'Osmaniye','Düzce'
];

$bloodGroups = ['A RH +','A RH -','B RH +','B RH -','AB RH +','AB RH -','0 RH +','0 RH -'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $height     = trim($_POST['height'] ?? '');
    $weight     = trim($_POST['weight'] ?? '');
    $bloodGroup = trim($_POST['blood_group'] ?? '');
    $country    = trim($_POST['country'] ?? 'TURKEY');
    $city       = trim($_POST['city'] ?? '');

    $newPass  = trim($_POST['new_password'] ?? '');
    $newPass2 = trim($_POST['new_password_confirm'] ?? '');

    if ($newPass !== '' || $newPass2 !== '') {
        $showPasswordSection = true;
    }

    // Validation (EN)
    if ($name === '') $errors[] = "Full name cannot be empty.";
    if ($email === '') $errors[] = "E-mail cannot be empty.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Please enter a valid e-mail address.";

    if ($phone === '') $errors[] = "Phone number is required.";
    if ($address === '') $errors[] = "Address is required.";

    if ($height === '' || !is_numeric($height) || $height < 50 || $height > 250) {
        $errors[] = "Height must be a number between 50 and 250 (cm).";
    }
    if ($weight === '' || !is_numeric($weight) || $weight < 20 || $weight > 300) {
        $errors[] = "Weight must be a number between 20 and 300 (kg).";
    }
    if ($bloodGroup === '' || !in_array($bloodGroup, $bloodGroups, true)) {
        $errors[] = "Please select a valid blood group.";
    }
    if ($country === '') $errors[] = "Country is required.";
    if ($city === '') $errors[] = "City is required.";

    if ($newPass !== '' || $newPass2 !== '') {
        if ($newPass !== $newPass2) $errors[] = "New password and confirmation do not match.";
        elseif (strlen($newPass) < 6) $errors[] = "New password must be at least 6 characters.";
    }

    if (empty($errors)) {
        if ($newPass !== '' && $newPass === $newPass2) {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);

            $sqlUpdate = "
                UPDATE users
                SET name = :name,
                    email = :email,
                    phone = :phone,
                    address = :address,
                    height = :height,
                    weight = :weight,
                    blood_group = :blood_group,
                    country = :country,
                    city = :city,
                    password = :password
                WHERE id = :id
            ";
            $params = [
                ':name' => $name, ':email' => $email, ':phone' => $phone, ':address' => $address,
                ':height' => $height, ':weight' => $weight, ':blood_group' => $bloodGroup,
                ':country' => $country, ':city' => $city,
                ':password' => $hashed, ':id' => $userId
            ];
        } else {
            $sqlUpdate = "
                UPDATE users
                SET name = :name,
                    email = :email,
                    phone = :phone,
                    address = :address,
                    height = :height,
                    weight = :weight,
                    blood_group = :blood_group,
                    country = :country,
                    city = :city
                WHERE id = :id
            ";
            $params = [
                ':name' => $name, ':email' => $email, ':phone' => $phone, ':address' => $address,
                ':height' => $height, ':weight' => $weight, ':blood_group' => $bloodGroup,
                ':country' => $country, ':city' => $city,
                ':id' => $userId
            ];
        }

        $upd = $conn->prepare($sqlUpdate);
        $upd->execute($params);

        $_SESSION['name'] = $name;
        $success = "Your profile has been updated successfully.";
        $showPasswordSection = false;
    }
}

/* ✅ Back fallback */
$backFallback = ($userRole === 'doctor') ? 'doctor_dashboard.php' : 'patient_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{
            --bg:#f3f4f6;
            --card:#ffffff;
            --muted:#6b7280;
            --text:#111827;
            --line:#e5e7eb;
            --primary:#1f4d8f;
            --dangerBg:#fff1f2;
            --dangerText:#9f1239;
            --successBg:#ecfdf5;
            --successText:#065f46;
            --radius:18px;
            --shadow: 0 10px 30px rgba(17,24,39,.08);
        }
        *{ box-sizing:border-box; }
        body{ margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial; background:var(--bg); color:var(--text); }
        .topbar{ height:64px; display:flex; align-items:center; justify-content:space-between; padding:0 18px; background:transparent; }
        .brand{ font-weight:700; letter-spacing:.2px; }
        .top-actions{ display:flex; align-items:center; gap:10px; color:var(--muted); font-size:14px; flex-wrap:wrap; }
        .pill{ background:#fff; border:1px solid var(--line); border-radius:999px; padding:7px 12px; box-shadow:0 6px 18px rgba(17,24,39,.06); text-decoration:none; color:inherit; }
        .wrap{ max-width: 1180px; margin: 0 auto; padding: 6px 18px 28px; }
        .page-title{ font-size:34px; margin: 8px 0 18px; font-weight:750; }
        .grid{ display:grid; grid-template-columns: 340px 1fr; gap:18px; align-items:start; }
        .card{ background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }

        .profile-card{ padding:18px; }
        .avatar-wrap{ display:flex; justify-content:center; padding:14px 0 8px; }
        .avatar{ width:170px; height:170px; border-radius:999px; background: radial-gradient(circle at 30% 30%, #e5e7eb 0, #cbd5e1 55%, #e5e7eb 100%); position:relative; }
        .avatar:after{ content:""; position:absolute; inset:34px 48px auto 48px; height:70px; border-radius:999px; background:rgba(255,255,255,.35); }
        .avatar:before{ content:""; position:absolute; left:38px; right:38px; bottom:30px; height:60px; border-radius:999px 999px 22px 22px; background:rgba(255,255,255,.28); }
        .pencil{ position:absolute; right:14px; bottom:14px; width:42px; height:42px; border-radius:999px; background:#fff; border:1px solid var(--line); display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(17,24,39,.12); }
        .pencil svg{ width:18px; height:18px; }
        .profile-name{ margin:12px 0 10px; font-size:18px; font-weight:800; }
        .meta{ display:flex; flex-direction:column; gap:10px; margin: 6px 0 16px; }
        .meta-row{ display:flex; gap:10px; align-items:flex-start; font-size:14px; }
        .icon{ width:22px; height:22px; display:flex; align-items:center; justify-content:center; margin-top:2px; }
        .meta small{ color:var(--muted); display:block; margin-top:2px; }
        .btn-ghost{ width:100%; margin-top:8px; padding:12px 14px; border-radius:999px; border:1px solid var(--line); background:#111827; color:#fff; cursor:pointer; font-weight:700; }

        .info-card{ padding:0; }
        .info-header{ display:flex; gap:12px; align-items:center; padding:18px 18px 10px; }
        .info-header .badge{ width:40px; height:40px; border-radius:999px; background:#111827; display:flex; align-items:center; justify-content:center; }
        .info-header .badge svg{ width:20px; height:20px; fill:#fff; }
        .info-header h2{ margin:0; font-size:18px; font-weight:850; }
        .divider{ height:1px; background:var(--line); margin: 0 18px; }
        .info-body{ padding: 14px 18px 18px; }

        .form-grid{ display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-top:10px; }
        .form-grid.five{ grid-template-columns: repeat(5, 1fr); }

        .field label{ display:block; font-size:13px; color:var(--muted); margin: 0 0 6px; font-weight:700; }
        .input{ width:100%; border:1px solid var(--line); border-radius:12px; background:#f9fafb; padding:12px 12px; outline:none; font-size:14px; }
        .input:focus{ border-color: rgba(29,78,216,.35); box-shadow: 0 0 0 4px rgba(29,78,216,.12); background:#fff; }
        textarea.input{ min-height:116px; resize:vertical; }

        .alerts{ margin:0 0 12px; }
        .alert{ border-radius:14px; padding:10px 12px; font-size:14px; border:1px solid var(--line); margin-bottom:10px; }
        .alert.danger{ background:var(--dangerBg); color:var(--dangerText); border-color:#fecdd3; }
        .alert.success{ background:var(--successBg); color:var(--successText); border-color:#bbf7d0; }

        .actions{ display:flex; justify-content:flex-end; gap:10px; padding-top: 12px; }
        .btn-primary{ display:inline-flex; align-items:center; gap:10px; padding:12px 16px; border:none; border-radius:999px; background: var(--primary); color:#fff; cursor:pointer; font-weight:800; box-shadow:0 12px 22px rgba(31,77,143,.18); }
        .btn-primary:hover{ background:#173e73; }
        .btn-secondary{ display:inline-flex; align-items:center; gap:10px; padding:12px 14px; border:1px solid var(--line); border-radius:999px; background:#fff; cursor:pointer; font-weight:800; color:#111827; }

        .pw-wrap{ border:1px dashed var(--line); border-radius:14px; padding:12px; background:#fff; margin-top: 10px; }
        .pw-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
        .pw-head .hint{ color:var(--muted); font-size:13px; line-height:1.3; }
        .pw-panel{ overflow:hidden; max-height:0; opacity:0; transition: max-height .28s ease, opacity .2s ease; }
        .pw-panel.open{ max-height:500px; opacity:1; margin-top:12px; }

        @media (max-width: 1100px){
            .form-grid.five{ grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 960px){
            .grid{ grid-template-columns: 1fr; }
            .form-grid{ grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="brand">Healthcare Record System</div>
    <div class="top-actions">
        <div class="pill"><?php echo htmlspecialchars($userName); ?> (<?php echo htmlspecialchars($userRole); ?>)</div>

        <!-- ✅ Patient Dashboard yerine BACK -->
        <a class="pill"
           href="<?php echo htmlspecialchars($backFallback); ?>"
           onclick="if (window.history.length > 1) { window.history.back(); return false; }">
            ← Back
        </a>

        <a class="pill" href="logout.php">Log out</a>
    </div>
</div>

<div class="wrap">
    <div class="page-title">My Profile</div>

    <div class="grid">
        <!-- LEFT -->
        <div class="card profile-card">
            <div class="avatar-wrap">
                <div class="avatar">
                    <div class="pencil" title="Profile photo (placeholder)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="profile-name"><?php echo htmlspecialchars($name); ?></div>

            <div class="meta">
                <div class="meta-row">
                    <div class="icon">🪪</div>
                    <div>
                        <div><strong>User ID:</strong> <?php echo htmlspecialchars((string)$userId); ?></div>
                        <small>Role: <?php echo htmlspecialchars($userRole); ?></small>
                    </div>
                </div>

                <div class="meta-row">
                    <div class="icon">📍</div>
                    <div>
                        <div><strong>City:</strong> <?php echo htmlspecialchars(mb_strtoupper($city)); ?></div>
                        <small><?php echo htmlspecialchars($address !== '' ? $address : '—'); ?></small>
                    </div>
                </div>

                <div class="meta-row">
                    <div class="icon">🩸</div>
                    <div>
                        <div><strong>Blood Group:</strong> <?php echo htmlspecialchars($bloodGroup ?: '—'); ?></div>
                        <small>Height: <?php echo htmlspecialchars($height ?: '—'); ?> cm • Weight: <?php echo htmlspecialchars($weight ?: '—'); ?> kg</small>
                    </div>
                </div>
            </div>

            <button class="btn-ghost" type="button">There is an issue with my identity info</button>
        </div>

        <!-- RIGHT -->
        <div class="card info-card">
            <div class="info-header">
                <div class="badge" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5zm0 2c-3.866 0-7 2.239-7 5v1h14v-1c0-2.761-3.134-5-7-5z"/>
                    </svg>
                </div>
                <h2>Personal Information</h2>
            </div>
            <div class="divider"></div>

            <div class="info-body">
                <?php if (!empty($errors)): ?>
                    <div class="alerts">
                        <div class="alert danger">
                            <strong>Error:</strong>
                            <ul style="margin:8px 0 0 18px;">
                                <?php foreach ($errors as $e): ?>
                                    <li><?php echo htmlspecialchars($e); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alerts">
                        <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="form-grid">
                        <div class="field">
                            <label for="email">E-mail Address</label>
                            <input class="input" type="email" id="email" name="email"
                                   value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>

                        <div class="field">
                            <label for="phone">Mobile Phone</label>
                            <input class="input" type="text" id="phone" name="phone"
                                   value="<?php echo htmlspecialchars($phone); ?>" required>
                        </div>

                        <div class="field" style="grid-column: 1 / -1;">
                            <label for="name">Full Name</label>
                            <input class="input" type="text" id="name" name="name"
                                   value="<?php echo htmlspecialchars($name); ?>" required>
                        </div>

                        <div class="field" style="grid-column: 1 / -1;">
                            <label for="address">Address</label>
                            <textarea class="input" id="address" name="address" required><?php echo htmlspecialchars($address); ?></textarea>
                        </div>
                    </div>

                    <div class="form-grid five" style="margin-top:14px;">
                        <div class="field">
                            <label for="height">Height</label>
                            <input class="input" type="number" id="height" name="height" min="50" max="250"
                                   value="<?php echo htmlspecialchars($height); ?>" placeholder="cm" required>
                        </div>

                        <div class="field">
                            <label for="weight">Weight</label>
                            <input class="input" type="number" id="weight" name="weight" min="20" max="300"
                                   value="<?php echo htmlspecialchars($weight); ?>" placeholder="kg" required>
                        </div>

                        <div class="field">
                            <label for="blood_group">Blood Group</label>
                            <select class="input" id="blood_group" name="blood_group" required>
                                <option value="" disabled <?php echo $bloodGroup===''?'selected':''; ?>>Select</option>
                                <?php foreach($bloodGroups as $bg): ?>
                                    <option value="<?php echo htmlspecialchars($bg); ?>"
                                        <?php echo ($bloodGroup === $bg) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($bg); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="country">Country</label>
                            <select class="input" id="country" name="country" required>
                                <option value="TURKEY" <?php echo ($country==='TURKEY')?'selected':''; ?>>TURKEY</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="city">City</label>
                            <select class="input" id="city" name="city" required>
                                <option value="" disabled <?php echo $city===''?'selected':''; ?>>Select</option>
                                <?php foreach($cities as $c): ?>
                                    <?php $cUp = mb_strtoupper($c); ?>
                                    <option value="<?php echo htmlspecialchars($cUp); ?>"
                                        <?php echo (mb_strtoupper($city) === $cUp) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cUp); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="pw-wrap">
                        <div class="pw-head">
                            <div>
                                <div style="font-weight:900;">Password</div>
                                <div class="hint">Click the button to change your password. (Leave empty to keep the current password.)</div>
                            </div>
                            <button type="button" class="btn-secondary" id="togglePwBtn">🔒 Change Password</button>
                        </div>

                        <div class="pw-panel <?php echo $showPasswordSection ? 'open' : ''; ?>" id="pwPanel">
                            <div class="form-grid" style="margin-top:10px;">
                                <div class="field">
                                    <label for="new_password">New Password</label>
                                    <input class="input" type="password" id="new_password" name="new_password" minlength="6">
                                </div>
                                <div class="field">
                                    <label for="new_password_confirm">New Password (Repeat)</label>
                                    <input class="input" type="password" id="new_password_confirm" name="new_password_confirm" minlength="6">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn-primary">💾 Save</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
    const btn = document.getElementById('togglePwBtn');
    const panel = document.getElementById('pwPanel');
    btn.addEventListener('click', () => panel.classList.toggle('open'));
</script>

</body>
</html>
