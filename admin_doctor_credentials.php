<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/db.php';

// ✅ Admin kontrolü
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

// Tablo yoksa uyarı verelim (seed çalışmadan girilirse)
try {
    $conn->query("SELECT 1 FROM doctor_credentials LIMIT 1");
} catch (Exception $e) {
    die("doctor_credentials table not found. First run seed_doctor_credentials.php");
}

$search = trim($_GET['q'] ?? '');
$params = [];

$sql = "
SELECT dc.doctor_id,
       u.name AS doctor_name,
       dc.email,
       dc.temp_password,
       dc.created_at
FROM doctor_credentials dc
JOIN users u ON u.id = dc.doctor_id
WHERE u.role='doctor'
";

if ($search !== '') {
    $sql .= " AND (u.name LIKE :q OR dc.email LIKE :q) ";
    $params[':q'] = '%' . $search . '%';
}

$sql .= " ORDER BY dc.created_at DESC, dc.doctor_id DESC LIMIT 500";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=doctor_credentials.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['doctor_id', 'doctor_name', 'email', 'temp_password', 'created_at']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['doctor_id'], $r['doctor_name'], $r['email'], $r['temp_password'], $r['created_at']]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Doctor Credentials (Admin)</title>
  <style>
    body{font-family:Arial, sans-serif;background:#f4f6f9;margin:0}
    .navbar{background:#111827;color:#fff;padding:12px 18px;display:flex;justify-content:space-between;align-items:center}
    .navbar a{color:#93c5fd;text-decoration:none;margin-left:12px}
    .page{max-width:1200px;margin:22px auto 50px;padding:0 16px}
    .card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);padding:14px 16px}
    h1{margin:0 0 12px}
    form{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px}
    input[type="text"]{padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;min-width:260px}
    button,.btn{background:#2563eb;color:#fff;border:none;border-radius:8px;padding:8px 12px;cursor:pointer;text-decoration:none;font-size:14px;display:inline-block}
    .btn.gray{background:#374151}
    table{width:100%;border-collapse:collapse;font-size:14px}
    th,td{padding:10px 10px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
    th{background:#f1f5f9;font-weight:700}
    .muted{color:#6b7280;font-size:13px;margin:6px 0 0}
    .pill{display:inline-block;font-size:12px;background:#eef2ff;color:#3730a3;padding:3px 8px;border-radius:999px}
    .pw{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace}
  </style>
</head>
<body>
  <div class="navbar">
    <div><strong>Healthcare Record System</strong> · Admin</div>
    <div>
      <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?>
      <a href="admin_dashboard.php">Dashboard</a>
      <a href="logout.php">Log out</a>
    </div>
  </div>

  <div class="page">
    <h1>Doctor Credentials</h1>
    <div class="card">
      <form method="get">
        <input type="text" name="q" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Search</button>

        <a class="btn gray" href="admin_doctor_credentials.php">Reset</a>

        <a class="btn" href="admin_doctor_credentials.php?q=<?php echo urlencode($search); ?>&export=csv">
          Export CSV
        </a>

        <a class="btn" href="seed_doctor_credentials.php">Run Seeder Again</a>
      </form>

      <div class="muted">
        Showing up to <span class="pill"><?php echo count($rows); ?></span> results (latest first).
      </div>

      <div style="overflow:auto; margin-top:12px;">
        <table>
          <tr>
            <th>ID</th>
            <th>Doctor</th>
            <th>Email</th>
            <th>Temp Password</th>
            <th>Created</th>
          </tr>
          <?php if (count($rows) === 0): ?>
            <tr><td colspan="5">No records found. Run <strong>seed_doctor_credentials.php</strong> first.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo (int)$r['doctor_id']; ?></td>
                <td><?php echo htmlspecialchars($r['doctor_name']); ?></td>
                <td class="pw"><?php echo htmlspecialchars($r['email']); ?></td>
                <td class="pw"><?php echo htmlspecialchars($r['temp_password']); ?></td>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
