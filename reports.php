<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Date Range Filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'ALL';

// Query Visit Summary Reports
$where_clauses = ["DATE(vl.entry_timestamp) BETWEEN '$start_date' AND '$end_date'"];
if ($status_filter !== 'ALL') {
    $where_clauses[] = "vl.status = '$status_filter'";
}
$where_sql = implode(' AND ', $where_clauses);

$reportQuery = "
    SELECT vl.visit_id, v.full_name AS visitor_name, v.phone_number AS visitor_phone, 
           v.id_number, v.vehicle_number, 
           CONCAT(a.block_number, '-', a.unit_number) AS target_unit, 
           r.name AS resident_name, u.username AS recorded_by_guard,
           vl.entry_timestamp, vl.exit_timestamp, vl.status
    FROM visit_logs vl
    JOIN visitors v ON vl.visitor_id = v.visitor_id
    JOIN residents r ON vl.resident_id = r.resident_id
    JOIN apartments a ON r.apartment_id = a.apartment_id
    JOIN users u ON vl.recorded_by_user_id = u.user_id
    WHERE $where_sql
    ORDER BY vl.entry_timestamp DESC
";
$reportResults = $conn->query($reportQuery);

// Summary Metrics
$totalVisits = $reportResults->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activity & Daily Summary Report - VMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #121212; color: #e0e0e0; }
        .card { background-color: #1e1e1e; border: 1px solid #333; }
        .form-control, .form-select { background-color: #2b2b2b; color: #fff; border: 1px solid #444; }
        .table-dark { background-color: #1e1e1e; }
        
        /* Print Styles - Formatted specifically for printable documents */
        @media print {
            .no-print { display: none !important; }
            body { background-color: #fff !important; color: #000 !important; }
            .card { border: none !important; background-color: #fff !important; }
            .table { color: #000 !important; }
            .table-dark { background-color: #fff !important; color: #000 !important; }
            .table-dark th, .table-dark td { border: 1px solid #000 !important; color: #000 !important; }
            .badge { border: 1px solid #000; color: #000 !important; background: transparent !important; }
        }
    </style>
</head>
<body class="container py-4">

    <!-- Header Actions (Hidden when printing) -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2>VISITOR ACTIVITY & SUMMARY REPORT</h2>
        <div>
            <button onclick="window.print()" class="btn btn-warning fw-bold me-2">🖨️ Print / Save PDF</button>
            <a href="admin.php" class="btn btn-outline-light">Back to Admin</a>
        </div>
    </div>

    <!-- Date Range Filter Form (Hidden when printing) -->
    <div class="card p-3 mb-4 no-print">
        <form method="GET" action="reports.php" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status Filter</label>
                <select name="status" class="form-select">
                    <option value="ALL" <?= $status_filter === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="ONGOING" <?= $status_filter === 'ONGOING' ? 'selected' : '' ?>>Ongoing (On-Site)</option>
                    <option value="COMPLETED" <?= $status_filter === 'COMPLETED' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100 fw-bold">Generate Summary Report</button>
            </div>
        </form>
    </div>

    <!-- Report Header (Visible on Screen & Print) -->
    <div class="card p-4">
        <div class="border-bottom pb-3 mb-3 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="m-0 text-primary">APARTMENT VISITOR MANAGEMENT SYSTEM</h4>
                <small class="text-muted">Daily Activity Log Summary</small>
            </div>
            <div class="text-end">
                <small>Report Date Range: <strong><?= htmlspecialchars($start_date) ?></strong> to <strong><?= htmlspecialchars($end_date) ?></strong></small><br>
                <small>Total Records Logged: <strong><?= $totalVisits ?></strong></small>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-dark table-bordered table-striped align-middle">
                <thead>
                    <tr>
                        <th># Log ID</th>
                        <th>Visitor Name</th>
                        <th>ID Number</th>
                        <th>Phone</th>
                        <th>Target Unit</th>
                        <th>Host Resident</th>
                        <th>Entry Time</th>
                        <th>Exit Time</th>
                        <th>Recorded By</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reportResults && $reportResults->num_rows > 0): ?>
                        <?php while ($r = $reportResults->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= $r['visit_id'] ?></td>
                            <td><?= htmlspecialchars($r['visitor_name']) ?></td>
                            <td><?= htmlspecialchars($r['id_number']) ?></td>
                            <td><?= htmlspecialchars($r['visitor_phone']) ?></td>
                            <td><?= htmlspecialchars($r['target_unit']) ?></td>
                            <td><?= htmlspecialchars($r['resident_name']) ?></td>
                            <td><?= $r['entry_timestamp'] ?></td>
                            <td><?= $r['exit_timestamp'] ? $r['exit_timestamp'] : '<em>On-Site</em>' ?></td>
                            <td><?= htmlspecialchars($r['recorded_by_guard']) ?></td>
                            <td>
                                <span class="badge <?= $r['status'] === 'ONGOING' ? 'bg-warning text-dark' : 'bg-success' ?>">
                                    <?= $r['status'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-3">No activity logs found for the selected date range.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>