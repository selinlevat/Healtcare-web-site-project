<?php
session_start();
require 'db.php';

// Only allow patient
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: login.php");
    exit;
}

$patientId   = (int)$_SESSION['user_id'];
$patientName = $_SESSION['name'] ?? 'Patient';

$errors = [];
$success = null;

$allowedRelations = ['Mother','Father','Sibling','Friend','Spouse','Other'];
$allowedPriorities = [1,2,3];

function clean_phone($p) {
    $p = trim($p);
    $p = str_replace([' ', '-', '(', ')'], '', $p);
    return $p;
}

// ✅ Back button fallback (history yoksa)
$backFallback = 'patient_dashboard.php';

// ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $contactName = trim($_POST['contact_name'] ?? '');
    $phone       = clean_phone($_POST['phone'] ?? '');
    $relation    = trim($_POST['relation'] ?? '');
    $priority    = (int)($_POST['priority'] ?? 0);

    if ($contactName === '') $errors[] = "Contact name is required.";
    if ($phone === '') $errors[] = "Phone number is required.";

    if ($phone !== '' && !preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
        $errors[] = "Please enter a valid phone number (digits, optional +).";
    }

    if (!in_array($relation, $allowedRelations, true)) $errors[] = "Please select a valid relation.";
    if (!in_array($priority, $allowedPriorities, true)) $errors[] = "Please select a valid priority (1-3).";

    if (empty($errors)) {
        $sqlIns = "INSERT INTO emergency_contacts (patient_id, contact_name, phone, relation, priority)
                   VALUES (:pid, :name, :phone, :rel, :prio)";
        $stmtIns = $conn->prepare($sqlIns);
        $stmtIns->execute([
            ':pid'  => $patientId,
            ':name' => $contactName,
            ':phone'=> $phone,
            ':rel'  => $relation,
            ':prio' => $priority
        ]);
        $success = "Contact added successfully.";
    }
}

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $sqlDel = "DELETE FROM emergency_contacts WHERE id = :id AND patient_id = :pid";
        $stmtDel = $conn->prepare($sqlDel);
        $stmtDel->execute([':id' => $id, ':pid' => $patientId]);
        $success = "Contact deleted.";
    }
}

// LIST
$sqlList = "SELECT id, contact_name, phone, relation, priority, created_at
            FROM emergency_contacts
            WHERE patient_id = :pid
            ORDER BY priority ASC, id DESC";
$stmtList = $conn->prepare($sqlList);
$stmtList->execute([':pid' => $patientId]);
$contacts = $stmtList->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Emergency Contacts</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{
            --bg:#f3f4f6;
            --card:#fff;
            --text:#0f172a;
            --muted:#64748b;
            --line:#e5e7eb;
            --shadow:0 10px 25px rgba(2,6,23,.08);
            --radius:18px;
            --primary:#1f4d8f;
        }
        *{box-sizing:border-box;}
        body{margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:var(--bg); color:var(--text);}
        .topbar{
            height:64px; display:flex; align-items:center; justify-content:space-between;
            padding:0 18px; background:#ffffffb3; backdrop-filter: blur(10px);
            border-bottom:1px solid rgba(226,232,240,.9);
        }
        .brand{font-weight:800;}
        .pill{
            background:#fff; border:1px solid var(--line);
            border-radius:999px; padding:7px 12px;
            text-decoration:none; color:inherit;
            display:inline-flex; align-items:center; gap:8px;
            font-weight:700;
        }
        .pill.back{
            border-color: rgba(37,99,235,.22);
            background: rgba(37,99,235,.08);
            color:#2563eb;
        }
        .pill.logout{
            border-color: rgba(220,38,38,.22);
            background: rgba(220,38,38,.08);
            color:#dc2626;
        }

        .wrap{max-width:980px; margin:0 auto; padding:18px;}
        h1{margin:14px 0 6px; font-size:28px;}
        .sub{margin:0 0 18px; color:var(--muted); font-size:14px;}

        .grid{display:grid; grid-template-columns: 1fr 1fr; gap:16px; align-items:start;}
        .card{background:var(--card); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow); padding:16px;}
        .card h2{margin:0 0 12px; font-size:18px;}

        .field{margin-bottom:12px;}
        label{display:block; font-size:13px; color:var(--muted); font-weight:700; margin:0 0 6px;}
        input, select{
            width:100%; border:1px solid var(--line); border-radius:12px; padding:11px 12px;
            background:#f9fafb; outline:none;
        }
        input:focus, select:focus{background:#fff; border-color:rgba(29,78,216,.35); box-shadow:0 0 0 4px rgba(29,78,216,.12);}
        .row{display:grid; grid-template-columns: 1fr 1fr; gap:12px;}

        .btn{
            display:inline-flex; align-items:center; justify-content:center; gap:8px;
            border:none; border-radius:999px; padding:11px 16px; cursor:pointer;
            font-weight:800;
        }
        .btn-primary{background:var(--primary); color:#fff; box-shadow:0 12px 22px rgba(31,77,143,.18);}
        .btn-danger{background:#dc2626; color:#fff;}
        .btn-ghost{background:#fff; border:1px solid var(--line); color:#0f172a;}
        .actions{display:flex; gap:10px; justify-content:flex-end; margin-top:8px; flex-wrap:wrap;}

        .alert{border-radius:14px; padding:10px 12px; border:1px solid var(--line); margin-bottom:12px; font-size:14px;}
        .alert.error{background:#fff1f2; border-color:#fecdd3; color:#9f1239;}
        .alert.success{background:#ecfdf5; border-color:#bbf7d0; color:#065f46;}

        .list{display:flex; flex-direction:column; gap:10px; margin-top:6px;}
        .item{
            border:1px solid var(--line);
            background:#f9fafb;
            border-radius:14px;
            padding:12px;
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:10px;
        }
        .item strong{display:block; font-size:14px;}
        .meta{color:var(--muted); font-size:13px; margin-top:4px; line-height:1.35;}
        .prio{
            display:inline-flex; align-items:center; justify-content:center;
            min-width:28px; height:28px; border-radius:999px;
            background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3;
            font-weight:900; font-size:13px;
        }
        .right{display:flex; flex-direction:column; align-items:flex-end; gap:10px;}
        .mini{font-size:12px; color:var(--muted);}

        @media(max-width:900px){
            .grid{grid-template-columns:1fr;}
            .row{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="brand">Healthcare Record System</div>
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <span class="pill"><?php echo htmlspecialchars($patientName); ?> (Patient)</span>

        <!-- ✅ Dashboard link yerine Back -->
        <a class="pill back"
           href="<?php echo htmlspecialchars($backFallback); ?>"
           onclick="if (window.history.length > 1) { window.history.back(); return false; }">
           ← Back
        </a>

        <a class="pill logout" href="logout.php">Log out</a>
    </div>
</div>

<div class="wrap">
    <h1>Emergency Contacts</h1>
    <p class="sub">Add priority people to contact in emergencies (Priority 1 is the highest).</p>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <strong>Error:</strong>
            <ul style="margin:8px 0 0 18px;">
                <?php foreach($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="grid">
        <!-- ADD FORM -->
        <div class="card">
            <h2>Add New Contact</h2>
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="add">

                <div class="field">
                    <label for="contact_name">Contact Name</label>
                    <input id="contact_name" name="contact_name" type="text" placeholder="e.g., Ayşe Yılmaz" required>
                </div>

                <div class="field">
                    <label for="phone">Phone Number</label>
                    <input id="phone" name="phone" type="text" placeholder="+905xxxxxxxxx or 05xxxxxxxxx" required>
                </div>

                <div class="row">
                    <div class="field">
                        <label for="relation">Relation</label>
                        <select id="relation" name="relation" required>
                            <option value="" disabled selected>Select</option>
                            <?php foreach($allowedRelations as $r): ?>
                                <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority" required>
                            <option value="" disabled selected>Select</option>
                            <option value="1">1 (Highest)</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                        </select>
                    </div>
                </div>

                <div class="actions">
                    <!-- ✅ Form içindeki Back de history/back olacak şekilde kalsın -->
                    <a class="btn btn-ghost"
                       href="<?php echo htmlspecialchars($backFallback); ?>"
                       onclick="if (window.history.length > 1) { window.history.back(); return false; }">
                        Back
                    </a>

                    <button class="btn btn-primary" type="submit">➕ Add Contact</button>
                </div>
            </form>
        </div>

        <!-- LIST -->
        <div class="card">
            <h2>My Contacts</h2>

            <?php if (empty($contacts)): ?>
                <p style="color:var(--muted); margin:0;">No contacts added yet.</p>
            <?php else: ?>
                <div class="list">
                    <?php foreach($contacts as $c): ?>
                        <div class="item">
                            <div>
                                <strong><?php echo htmlspecialchars($c['contact_name']); ?></strong>
                                <div class="meta">
                                    <?php echo htmlspecialchars($c['relation']); ?> •
                                    <?php echo htmlspecialchars($c['phone']); ?>
                                    <div class="mini">Added: <?php echo htmlspecialchars($c['created_at']); ?></div>
                                </div>
                            </div>

                            <div class="right">
                                <div class="prio" title="Priority"><?php echo (int)$c['priority']; ?></div>
                                <form method="post" onsubmit="return confirm('Delete this contact?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                    <button class="btn btn-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
