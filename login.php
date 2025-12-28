<?php
ob_start(); // ✅ header/cookie sorunlarını büyük oranda engeller

session_start();
require 'db.php';

// Debug istersen aç:
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

$error = "";
$email_value = "";

function verify_password_flexible(string $inputPassword, string $dbPassword): bool {
    $dbPassword = trim($dbPassword);

    $looksHashed =
        str_starts_with($dbPassword, '$2y$') ||
        str_starts_with($dbPassword, '$2a$') ||
        str_starts_with($dbPassword, '$2b$') ||
        str_starts_with($dbPassword, '$argon2i$') ||
        str_starts_with($dbPassword, '$argon2id$');

    if ($looksHashed) {
        return password_verify($inputPassword, $dbPassword);
    }

    // legacy plain password
    return hash_equals($dbPassword, $inputPassword);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $email_value = $email;

    if ($email === '' || $password === '') {
        $error = "E-mail and password are required.";
    } else {
        try {
            $sql = "SELECT id, name, role, password FROM users WHERE LOWER(email)=LOWER(:email) LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "E-mail or password is incorrect.";
            } else {
                $dbPass = (string)($user['password'] ?? '');

                if (!verify_password_flexible($password, $dbPass)) {
                    $error = "E-mail or password is incorrect.";
                } else {
                    // ✅ başarılı login
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['name']    = (string)($user['name'] ?? '');
                    $_SESSION['role']    = strtolower(trim((string)($user['role'] ?? '')));

                    // test amaçlı:
                    $_SESSION['logged_in'] = true;

                    if ($_SESSION['role'] === 'doctor') {
                        header("Location: doctor_dashboard.php");
                        exit;
                    } elseif ($_SESSION['role'] === 'patient') {
                        header("Location: patient_dashboard.php");
                        exit;
                    } else {
                        $error = "Invalid account role.";
                        session_destroy();
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Database error. Please try again.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Healthcare System</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body{
            font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            padding:20px;
        }
        .login-container{
            background:#fff; border-radius:20px;
            box-shadow:0 20px 60px rgba(0,0,0,.3);
            width:100%; max-width:440px; padding:40px;
            animation:slideIn .4s ease-out;
        }
        @keyframes slideIn{ from{opacity:0;transform:translateY(-30px);} to{opacity:1;transform:translateY(0);} }
        .logo{text-align:center; margin-bottom:30px;}
        .logo h1{ color:#667eea; font-size:32px; font-weight:700; margin-bottom:8px; }
        .logo p{ color:#6b7280; font-size:14px; }
        .alert{
            background:#fee2e2; border-left:4px solid #ef4444; color:#991b1b;
            padding:14px 16px; border-radius:8px; margin-bottom:24px; font-size:14px;
        }
        .form-group{ margin-bottom:24px; }
        label{ display:block; color:#374151; font-weight:600; margin-bottom:8px; font-size:14px; }
        input{
            width:100%; padding:14px 16px;
            border:2px solid #e5e7eb; border-radius:10px; font-size:15px;
            transition:.3s; outline:none;
        }
        input:focus{ border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.1); }
        .btn-login{
            width:100%; padding:14px;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff; border:none; border-radius:10px;
            font-size:16px; font-weight:600; cursor:pointer;
            transition:.3s; margin-top:10px;
        }
        .btn-login:hover{ transform:translateY(-2px); box-shadow:0 10px 25px rgba(102,126,234,.4); }
        .footer-text{ text-align:center; color:#6b7280; font-size:13px; margin-top:24px; }
        .footer-text a{ color:#667eea; text-decoration:none; font-weight:600; }
        .footer-text a:hover{ text-decoration:underline; }
    </style>
</head>
<body>
<div class="login-container">
    <div class="logo">
        <h1>🏥 Healthcare</h1>
        <p>Sign in to your account</p>
    </div>

    <?php if ($error): ?>
        <div class="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email"
                value="<?php echo htmlspecialchars($email_value); ?>"
                required
                autocomplete="email"
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                required
                autocomplete="current-password"
            >
        </div>

        <button type="submit" class="btn-login">Sign In</button>
    </form>

    <div class="footer-text">
        Don't have an account? <a href="register.php">Sign up</a>
    </div>
</div>
</body>
</html>
