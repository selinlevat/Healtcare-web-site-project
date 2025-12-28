<?php
session_start();
require 'db.php';

// Giriş kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Patient';

// Filtreleme parametreleri
$start_year = $_GET['start_year'] ?? date('Y');
$end_year = $_GET['end_year'] ?? date('Y');
$search = trim($_GET['search'] ?? '');

// Raporları çek
$sql = "SELECT r.*, u.name as doctor_name 
        FROM reports r 
        LEFT JOIN users u ON r.doctor_id = u.id 
        WHERE r.patient_id = :patient_id 
        AND YEAR(r.issue_date) >= :start_year 
        AND YEAR(r.issue_date) <= :end_year";

if ($search !== '') {
    $sql .= " AND (r.diagnosis LIKE :search OR u.name LIKE :search OR r.department LIKE :search)";
}

$sql .= " ORDER BY r.issue_date DESC";

$stmt = $conn->prepare($sql);
$params = [
    ':patient_id' => $user_id,
    ':start_year' => $start_year,
    ':end_year' => $end_year
];

if ($search !== '') {
    $params[':search'] = '%' . $search . '%';
}

$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports - Healthcare System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }

        .header {
            background: white;
            padding: 20px 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            color: #1f2937;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            color: #6b7280;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-back {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-back:hover {
            background: #d1d5db;
        }

        .btn-logout {
            background: #ef4444;
            color: white;
        }

        .btn-logout:hover {
            background: #dc2626;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-title {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .page-title h2 {
            font-size: 28px;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .page-title p {
            color: #6b7280;
            font-size: 14px;
        }

        .filters {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            border-color: #667eea;
        }

        .filter-group input {
            width: 300px;
        }

        .btn-search {
            background: #667eea;
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-search:hover {
            background: #5568d3;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f9fafb;
        }

        th {
            padding: 15px 20px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e5e7eb;
        }

        td {
            padding: 18px 20px;
            color: #374151;
            font-size: 14px;
            border-bottom: 1px solid #f3f4f6;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        .no-results svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }

            .filters {
                flex-direction: column;
            }

            .filter-group input {
                width: 100%;
            }

            .table-container {
                overflow-x: auto;
            }

            table {
                min-width: 900px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Healthcare Record System</h1>
        <div class="header-right">
            <span class="user-info"><?php echo htmlspecialchars($user_name); ?> (Patient)</span>
            <a href="patient_dashboard.php" class="btn btn-back">← Back</a>
            <a href="logout.php" class="btn btn-logout">Log out</a>
        </div>
    </div>

    <div class="container">
        <div class="page-title">
            <h2>My Reports</h2>
            <p>Rest reports issued by your doctors.</p>
        </div>

        <form method="GET" class="filters">
            <div class="filter-group">
                <label>Start Year</label>
                <select name="start_year">
                    <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $start_year ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>End Year</label>
                <select name="end_year">
                    <?php for($y = date('Y') + 1; $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $end_year ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Search</label>
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search (diagnosis, doctor, department)"
                    value="<?php echo htmlspecialchars($search); ?>"
                >
            </div>

            <button type="submit" class="btn-search">🔍 Search</button>
        </form>

        <div class="table-container">
            <?php if (count($reports) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Issue Date</th>
                            <th>Leave Days</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Diagnosis</th>
                            <th>Doctor</th>
                            <th>Department</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['issue_date']); ?></td>
                                <td><?php echo htmlspecialchars($report['leave_days']); ?></td>
                                <td><?php echo htmlspecialchars($report['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($report['end_date']); ?></td>
                                <td><?php echo htmlspecialchars($report['diagnosis']); ?></td>
                                <td><?php echo htmlspecialchars($report['doctor_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($report['department']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3>No reports found</h3>
                    <p>Try adjusting your filters or search criteria</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>