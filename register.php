<?php
session_start();
require 'db.php';

// If user is already logged in, send to dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'patient') {
        header("Location: patient_dashboard.php");
    } elseif ($_SESSION['role'] === 'doctor') {
        header("Location: doctor_dashboard.php");
    }
    exit;
}

$errors  = [];
$success = null;

$name    = '';
$email   = '';
$phone   = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $pass    = trim($_POST['password'] ?? '');
    $pass2   = trim($_POST['password_confirm'] ?? '');

    if ($name === '') {
        $errors[] = "Full name is required.";
    }
    if ($email === '') {
        $errors[] = "E-mail is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid e-mail address.";
    }
    if ($phone === '') {
        $errors[] = "Phone number is required.";
    }
    if ($address === '') {
        $errors[] = "Address is required.";
    }
    if ($pass === '' || $pass2 === '') {
        $errors[] = "Password and confirmation are required.";
    } elseif ($pass !== $pass2) {
        $errors[] = "Password and confirmation do not match.";
    } elseif (strlen($pass) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    // Check if email already exists
    if (empty($errors)) {
        $sql = "SELECT COUNT(*) FROM users WHERE email = :email";
        $st  = $conn->prepare($sql);
        $st->execute([':email' => $email]);
        if ($st->fetchColumn() > 0) {
            $errors[] = "This e-mail address is already registered.";
        }
    }

    if (empty($errors)) {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);

        $insert = "
            INSERT INTO users (name, email, phone, address, password, role)
            VALUES (:name, :email, :phone, :address, :password, 'patient')
        ";
        $stmt = $conn->prepare($insert);
        $stmt->execute([
            ':name'     => $name,
            ':email'    => $email,
            ':phone'    => $phone,
            ':address'  => $address,
            ':password' => $hashed
        ]);

        $success = "Your account has been created successfully. You can now sign in.";
        // Optionally, you can automatically log the user in:
        // $userId = $conn->lastInsertId();
        // $_SESSION['user_id'] = $userId;
        // $_SESSION['name']    = $name;
        // $_SESSION['role']    = 'patient';
        // header("Location: patient_dashboard.php"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account – Healthcare Record System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background:#f4f6f9;
            margin:0;
            padding:0;
        }
        .navbar {
            background:#37474f;
            color:#fff;
            padding:10px 18px;
        }
        .page {
            max-width:450px;
            margin:40px auto;
            background:#fff;
            padding:20px 24px;
            border-radius:10px;
            box-shadow:0 2px 6px rgba(0,0,0,0.1);
        }
        h1 {
            margin-top:0;
            text-align:center;
        }
        .form-group {
            margin-bottom:12px;
        }
        label {
            display:block;
            margin-bottom:4px;
            font-weight:bold;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        textarea {
            width:100%;
            padding:8px 10px;
            border-radius:4px;
            border:1px solid #ccc;
            box-sizing:border-box;
        }
        textarea {
            resize:vertical;
            min-height:60px;
        }
        .btn {
            width:100%;
            background:#388e3c;
            color:#fff;
            border:none;
            padding:9px 18px;
            border-radius:4px;
            cursor:pointer;
            font-weight:bold;
            margin-top:6px;
        }
        .errors {
            background:#ffebee;
            border:1px solid #ef9a9a;
            color:#c62828;
            padding:8px 10px;
            border-radius:4px;
            margin-bottom:10px;
        }
        .success {
            background:#e8f5e9;
            border:1px solid #a5d6a7;
            color:#2e7d32;
            padding:8px 10px;
            border-radius:4px;
            margin-bottom:10px;
        }
        .link {
            text-align:center;
            margin-top:10px;
            font-size:14px;
        }
        .link a {
            color:#1976d2;
            text-decoration:none;
        }
    </style>
</head>
<body>
<div class="navbar">
    Healthcare Record System
</div>

<div class="page">
    <h1>Create Account</h1>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="name">Full Name *</label>
            <input type="text" id="name" name="name"
                   value="<?php echo htmlspecialchars($name); ?>" required>
        </div>

        <div class="form-group">
            <label for="email">E-mail *</label>
            <input type="email" id="email" name="email"
                   value="<?php echo htmlspecialchars($email); ?>" required>
        </div>

        <div class="form-group">
            <label for="phone">Phone Number *</label>
            <input type="text" id="phone" name="phone"
                   value="<?php echo htmlspecialchars($phone); ?>" required>
        </div>

        <div class="form-group">
            <label for="address">Address *</label>
            <textarea id="address" name="address" required><?php
                echo htmlspecialchars($address);
            ?></textarea>
        </div>

        <div class="form-group">
            <label for="password">Password *</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
            <label for="password_confirm">Password (Repeat) *</label>
            <input type="password" id="password_confirm" name="password_confirm" required>
        </div>

        <button type="submit" class="btn">Sign Up</button>
    </form>

    <div class="link">
        Already have an account?
        <a href="login.php">Sign in</a>
    </div>
</div>
</body>
</html>
