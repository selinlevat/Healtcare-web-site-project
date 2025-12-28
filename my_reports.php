<?php
session_start();
require 'db.php';

// Only patient
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: login.php");
    exit;
}

$patientId   = (int)$_SESSION['user_id'];
$patientName = $_SESSION['name'] ?? 'Patient';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Filters
$yearFrom = isset($_GET['year_from']) ? (int)$_GET['year_from'] : (int)date('Y')-1;
$yearTo   = isset($_GET['year_to']) ? (int)$_GET['year_to'] : (int)date('Y');
$q        = trim($_GET['q'] ?? '');

$minYear = 2000;
$maxYear = (int)date('Y') + 1;
if ($yearFrom < $minYear) $yearFrom = $minYear;
if ($yearTo > $maxYear) $yearTo = $maxYear;
if ($yearTo < $yearFrom) $yearTo = $yearFrom;

// Build SQL
$params = [':pid' => $patientId];

$sql = "
    SELECT r.id,
           r.issue_date,
           r.start_date,
           r.end_date,
           r.leave_days,
           r.diagnosis,
           r.department,
           r.pdf_path,
           d.name AS doctor_name
    FROM reports r
    LEFT JOIN users d ON d.id = r.doctor_id
    WHERE r.patient_id = :pid
      AND YEAR(r.issue_date) BETWEEN :yfrom AND :yto
";
$params[':yfrom'] = $yearFrom;
$params[':yto']   = $yearTo;

if ($q !== '') {
    $sql .= " AND (
        r.diagnosis LIKE :q
        OR r.department LIKE :q
        OR d.name LIKE :q
        OR r.report_no LIKE :q
    )";
    $params[':q'] = "%{$q}%";
}

$sql .= " ORDER BY r.issue_date DESC, r.id DESC LIMIT 200";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Back fallback
$backFallback = 'patient_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Reports</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --primary:#2563eb;
            --primary-soft:#e3f2fd;
            --shadow:0 14px 30px rgba(15,23,42,0.12);
            --radius:18px;
        }
        *{box-sizing:border-box;}
        body{
            margin:0;
            font-family:Inter, system-ui, -apple-system, 'Segoe UI', Arial, sans-serif;
            background:#f4f6f9;
            color:#111827;
        }

        .navbar{
            background:#ffffffaa;
            backdrop-filter: blur(10px);
            border-bottom:1px solid rgba(148,163,184,0.35);
            padding:10px 32px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            position:sticky; top:0; z-index:20;
        }
        .navbar-left{font-weight:900; letter-spacing:.03em; font-size:18px; color:#0f172a;}
        .navbar-right{
            display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end;
            font-size:14px;
        }
        .nav-btn{
            display:inline-flex; align-items:center; gap:8px;
            padding:8px 12px; border-radius:999px;
            text-decoration:none; font-weight:800;
            border:1px solid rgba(37,99,235,0.25);
            background: rgba(37,99,235,0.08);
            color:#2563eb;
            transition: transform .12s ease, filter .12s ease;
        }
        .nav-btn:hover{ transform: translateY(-1px); filter:brightness(1.02); }
        .nav-btn.logout{
            border:1px solid rgba(220,38,38,0.25);
            background: rgba(220,38,38,0.08);
            color:#dc2626;
        }

        .page{max-width:1400px; margin:26px auto 60px; padding:0 18px;}
        .card{
            background:#fff;
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            border:1px solid rgba(148,163,184,0.25);
            padding:22px 22px 14px;
        }

        .top{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:14px;
            flex-wrap:wrap;
            margin-bottom:14px;
        }
        .title{
            margin:0;
            font-size:24px;
            font-weight:900;
            letter-spacing:.02em;
        }
        .subtitle{
            margin:6px 0 0;
            font-size:13px;
            color:#6b7280;
        }

        /* filter bar like screenshot */
        .filters{
            display:flex;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
            justify-content:flex-end;
        }
        .chip{
            display:flex;
            align-items:center;
            gap:8px;
            background:#f9fafb;
            border:1px solid #e5e7eb;
            border-radius:999px;
            padding:8px 12px;
        }
        .chip label{
            font-size:12px;
            font-weight:800;
            color:#374151;
            margin:0;
        }
        .chip select, .chip input{
            border:none;
            background:transparent;
            outline:none;
            font-size:14px;
            padding:0 4px;
        }
        .btn-search{
            display:inline-flex;
            align-items:center;
            gap:8px;
            border:none;
            cursor:pointer;
            border-radius:999px;
            padding:10px 14px;
            background:#4b5563;
            color:#fff;
            font-weight:900;
        }
        .searchbox{
            display:flex;
            align-items:center;
            gap:8px;
            background:#f9fafb;
            border:1px solid #e5e7eb;
            border-radius:999px;
            padding:8px 12px;
        }
        .searchbox input{
            border:none;
            outline:none;
            background:transparent;
            font-size:14px;
            width:240px;
        }

        table{
            width:100%;
            border-collapse:collapse;
            margin-top:14px;
            font-size:13px;
        }
        thead{ background:#f3f4f6; }
        th, td{
            padding:12px 12px;
            text-align:left;
            border-bottom:1px solid #e5e7eb;
            vertical-align:top;
        }
        tbody tr:hover{ background:#fafafa; }

        .btn-pill{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:8px 12px;
            border-radius:999px;
            font-weight:900;
            text-decoration:none;
            border:1px solid rgba(17,24,39,0.12);
            background:#6b7280;
            color:#fff;
            white-space:nowrap;
            font-size:12px;
        }
        .btn-pill.dark{ background:#111827; }
        .btn-pill.disabled{
            opacity:.25;
            pointer-events:none;
        }

        .empty{
            margin-top:14px;
            padding:16px;
            border-radius:14px;
            background:#f9fafb;
            border:1px dashed #d1d5db;
            color:#6b7280;
        }

        @media (max-width: 700px){
            .searchbox input{ width:160px; }
            th, td{ padding:10px 8px; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="navbar-left">Healthcare Record System</div>
    <div class="navbar-right">
        <span><?php echo h($patientName); ?> (Patient)</span>

        <a class="nav-btn"
           href="<?php echo h($backFallback); ?>"
           onclick="if (window.history.length > 1) { window.history.back(); return false; }">
            ← Back
        </a>

        <a class="nav-btn logout" href="logout.php">Log out</a>
    </div>
</div>

<div class="page">
    <div class="card">
        <div class="top">
            <div>
                <h1 class="title">My Reports</h1>
                <p class="subtitle">Rest reports issued by your doctors.</p>
            </div>

            <form class="filters" method="get">
                <div class="chip">
                    <label for="year_from">Start Year</label>
                    <select id="year_from" name="year_from">
                        <?php for($y=$maxYear; $y>=$minYear; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($y===$yearFrom)?'selected':''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="chip">
                    <label for="year_to">End Year</label>
                    <select id="year_to" name="year_to">
                        <?php for($y=$maxYear; $y>=$minYear; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($y===$yearTo)?'selected':''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <button class="btn-search" type="submit">🔍 Search</button>

                <div class="searchbox">
                    <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="Search (diagnosis, doctor, department)">
                    <button class="btn-search" type="submit" style="background:#111827;">🔎</button>
                </div>
            </form>
        </div>

        <?php if (empty($rows)): ?>
            <div class="empty">No reports found for the selected filters.</div>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th style="width:10%;">Issue Date</th>
                    <th style="width:10%;">Leave Days</th>
                    <th style="width:10%;">Start Date</th>
                    <th style="width:10%;">End Date</th>
                    <th>Diagnosis</th>
                    <th style="width:14%;">Doctor</th>
                    <th style="width:14%;">Department</th>
                    <th style="width:10%;">PDF</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                        $pdfPath = $r['pdf_path'] ?? '';
                        $hasPdf = is_string($pdfPath) && trim($pdfPath) !== '';
                    ?>
                    <tr>
                        <td><?php echo h($r['issue_date']); ?></td>
                        <td><?php echo h($r['leave_days']); ?></td>
                        <td><?php echo h($r['start_date']); ?></td>
                        <td><?php echo h($r['end_date']); ?></td>
                        <td><?php echo h($r['diagnosis'] ?? '—'); ?></td>
                        <td><?php echo h($r['doctor_name'] ?? '—'); ?></td>
                        <td><?php echo h($r['department'] ?? '—'); ?></td>
                        <td>
                            <?php if ($hasPdf): ?>
                                <a class="btn-pill" href="<?php echo h($pdfPath); ?>" target="_blank" rel="noopener">Download PDF</a>
                            <?php else: ?>
                                <span class="btn-pill disabled">Download PDF</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
