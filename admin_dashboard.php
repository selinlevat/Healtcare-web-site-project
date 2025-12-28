<?php
session_start();
require 'db.php';

// Only admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// 1) User statistics
$sqlCountUsers = "SELECT role, COUNT(*) AS total FROM users GROUP BY role";
$stmtCountUsers = $conn->query($sqlCountUsers);
$userStats = $stmtCountUsers->fetchAll(PDO::FETCH_ASSOC);

// 2) Appointment statistics
$sqlCountAppointments = "SELECT status, COUNT(*) AS total FROM appointments GROUP BY status";
$stmtCountApp = $conn->query($sqlCountAppointments);
$appStats = $stmtCountApp->fetchAll(PDO::FETCH_ASSOC);

// 3) Last 10 users
$sqlLastUsers = "SELECT id, name, email, role FROM users ORDER BY id DESC LIMIT 10";
$stmtLastUsers = $conn->query($sqlLastUsers);
$lastUsers = $stmtLastUsers->fetchAll(PDO::FETCH_ASSOC);

// 4) Last 10 appointments
$sqlLastAppointments = "
    SELECT a.id,
           a.appointment_date,
           a.appointment_time,
           a.status,
           p.name AS patient_name,
           d.name AS doctor_name
    FROM appointments a
    JOIN users p ON a.patient_id = p.id
    JOIN users d ON a.doctor_id = d.id
    ORDER BY a.created_at DESC
    LIMIT 10
";
$stmtLastAppointments = $conn->query($sqlLastAppointments);
$lastAppointments = $stmtLastAppointments->fetchAll(PDO::FETCH_ASSOC);

$adminName = $_SESSION['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel – Healthcare Record System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eceff1;
            margin: 0;
            padding: 0;
        }
        .navbar {
            background: #263238;
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto 40px;
            padding: 0 15px;
        }
        h2 {
            margin-top: 10px;
            margin-bottom: 16px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1.2fr 2fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 15px 18px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            border-bottom: 1px solid #eee;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f0f3f7;
            font-weight: 600;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            color: white;
        }
        .badge.patient { background: #1976d2; }
        .badge.doctor  { background: #43a047; }
        .badge.admin   { background: #fb8c00; }

        .badge.pending   { background: #f9a825; }
        .badge.approved  { background: #43a047; }
        .badge.completed { background: #1e88e5; }
        .badge.cancelled { background: #e53935; }
    </style>
</head>
<body>
    <div class="navbar">
        <div><strong>Healthcare Record System – Admin Panel</strong></div>
        <div>
            <?php echo htmlspecialchars($adminName); ?> (Admin)
            <a href="logout.php">Log out</a>
        </div>
    </div>

    <div class="container">
        <h2>Overview</h2>

        <div class="grid">
            <!-- User statistics -->
            <div class="card">
                <h3>User Statistics</h3>
                <?php if (count($userStats) === 0): ?>
                    <p>No users registered in the system.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Role</th>
                            <th>Total</th>
                        </tr>
                        <?php foreach ($userStats as $us): ?>
                            <tr>
                                <td>
                                    <?php
                                        $role = $us['role'];
                                        $class = $role;
                                    ?>
                                    <span class="badge <?php echo htmlspecialchars($class); ?>">
                                        <?php echo htmlspecialchars(ucfirst($role)); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($us['total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Appointment statistics -->
            <div class="card">
                <h3>Appointment Statistics</h3>
                <?php if (count($appStats) === 0): ?>
                    <p>No appointments found.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Status</th>
                            <th>Total</th>
                        </tr>
                        <?php foreach ($appStats as $as): ?>
                            <tr>
                                <td>
                                    <?php
                                        $status = $as['status'];
                                        $class = $status;
                                    ?>
                                    <span class="badge <?php echo htmlspecialchars($class); ?>">
                                        <?php echo htmlspecialchars(ucfirst($status)); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($as['total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Latest users -->
        <div class="card">
            <h3>Latest Users</h3>
            <?php if (count($lastUsers) === 0): ?>
                <p>No users found.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>E-mail</th>
                        <th>Role</th>
                    </tr>
                    <?php foreach ($lastUsers as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['id']); ?></td>
                            <td><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td>
                                <span class="badge <?php echo htmlspecialchars($u['role']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($u['role'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <!-- Latest appointments -->
        <div class="card">
            <h3>Latest Appointments</h3>
            <?php if (count($lastAppointments) === 0): ?>
                <p>No appointments found.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($lastAppointments as $a): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['id']); ?></td>
                            <td><?php echo htmlspecialchars($a['appointment_date']); ?></td>
                            <td><?php echo htmlspecialchars(substr($a['appointment_time'], 0, 5)); ?></td>
                            <td><?php echo htmlspecialchars($a['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($a['doctor_name']); ?></td>
                            <td>
                                <span class="badge <?php echo htmlspecialchars($a['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($a['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
