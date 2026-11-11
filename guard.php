<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch Residents for Dropdown / Search
$residentsQuery = "
    SELECT r.resident_id, r.name, CONCAT(a.block_number, ' - Unit ', a.unit_number) AS unit 
    FROM residents r 
    JOIN apartments a ON r.apartment_id = a.apartment_id
    ORDER BY r.name ASC
";
$residents = $conn->query($residentsQuery);

// Fetch Ongoing Visits (Wireframe 6.1)
$activeVisitsQuery = "
    SELECT vl.visit_id, v.full_name, v.phone_number, 
           CONCAT(a.block_number, '-', a.unit_number) AS unit, 
           r.name AS resident_name, TIME(vl.entry_timestamp) AS entry_time
    FROM visit_logs vl
    JOIN visitors v ON vl.visitor_id = v.visitor_id
    JOIN residents r ON vl.resident_id = r.resident_id
    JOIN apartments a ON r.apartment_id = a.apartment_id
    WHERE vl.status = 'ONGOING'
    ORDER BY vl.entry_timestamp DESC
";
$activeVisits = $conn->query($activeVisitsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Guard Dashboard - Visitor Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #121212; color: #e0e0e0; }
        .card { background-color: #1e1e1e; border: 1px solid #333; }
        .form-control, .form-select { background-color: #2b2b2b; color: #fff; border: 1px solid #444; }
        .form-control:focus, .form-select:focus { background-color: #333; color: #fff; }
        .table-dark { background-color: #1e1e1e; }
    </style>
</head>
<body class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-light">APARTMENT VISITOR MANAGEMENT SYSTEM</h2>
        <div>
            Logged in: <strong class="text-primary"><?= htmlspecialchars($_SESSION['username']) ?></strong>
            <a href="logout.php" class="btn btn-sm btn-outline-danger ms-2">Logout</a>
        </div>
    </div>

    <!-- Quick Check-In Form (Wireframe 6.1 with Resident Search) -->
    <div class="card p-4 mb-4">
        <h5 class="text-primary mb-3">NEW VISITOR ENTRY</h5>
        <form action="checkin.php" method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" required placeholder="e.g. John Smith">
            </div>
            <div class="col-md-6">
                <label class="form-label">National/ID No</label>
                <input type="text" name="id_number" class="form-control" required placeholder="e.g. 12345678">
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone_number" class="form-control" required placeholder="e.g. 0712345678">
            </div>
            <div class="col-md-6">
                <label class="form-label">Vehicle Reg (Optional)</label>
                <input type="text" name="vehicle_number" class="form-control" placeholder="e.g. KAA 123A">
            </div>

            <!-- Target Resident Search & Selection -->
            <div class="col-md-6">
                <label class="form-label text-warning">🔍 Search Resident by Name / Unit</label>
                <input type="text" id="residentSearch" class="form-control mb-1" placeholder="Type resident name to filter..." onkeyup="filterResidents()">
            </div>
            <div class="col-md-6">
                <label class="form-label">Target Unit / Host Resident</label>
                <select name="resident_id" id="residentSelect" class="form-select" required>
                    <option value="">-- Select Target Resident --</option>
                    <?php if ($residents && $residents->num_rows > 0): ?>
                        <?php while ($r = $residents->fetch_assoc()): ?>
                            <option value="<?= $r['resident_id'] ?>" data-name="<?= strtolower(htmlspecialchars($r['name'])) ?>" data-unit="<?= strtolower(htmlspecialchars($r['unit'])) ?>">
                                <?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['unit']) ?>)
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="col-12 text-end mt-3">
                <button type="submit" class="btn btn-primary px-4 fw-bold">CHECK-IN VISITOR</button>
            </div>
        </form>
    </div>

    <!-- On-Site Active Visitors Table (Wireframe 6.1) -->
    <div class="card p-4">
        <h5 class="text-primary mb-3">ON-SITE VISITORS (ONGOING)</h5>
        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle">
                <thead>
                    <tr>
                        <th>Visitor Name</th>
                        <th>Phone</th>
                        <th>Unit</th>
                        <th>Resident</th>
                        <th>Entry Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($activeVisits && $activeVisits->num_rows > 0): ?>
                        <?php while ($v = $activeVisits->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['full_name']) ?></td>
                            <td><?= htmlspecialchars($v['phone_number']) ?></td>
                            <td><?= htmlspecialchars($v['unit']) ?></td>
                            <td><?= htmlspecialchars($v['resident_name']) ?></td>
                            <td><?= htmlspecialchars($v['entry_time']) ?></td>
                            <td>
                                <a href="checkout.php?id=<?= $v['visit_id'] ?>" class="btn btn-sm btn-info text-white fw-bold">CHECK-OUT</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">No active ongoing visits at the moment.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Live Filter JavaScript -->
    <script>
        function filterResidents() {
            const query = document.getElementById('residentSearch').value.toLowerCase().trim();
            const select = document.getElementById('residentSelect');
            const options = select.getElementsByTagName('option');

            let firstMatch = null;

            for (let i = 1; i < options.length; i++) {
                const name = options[i].getAttribute('data-name');
                const unit = options[i].getAttribute('data-unit');

                if (name.includes(query) || unit.includes(query)) {
                    options[i].style.display = "";
                    if (!firstMatch) firstMatch = options[i];
                } else {
                    options[i].style.display = "none";
                }
            }

            // Auto-select the first matching resident when searching
            if (query !== "" && firstMatch) {
                select.value = firstMatch.value;
            } else if (query === "") {
                select.value = "";
            }
        }
    </script>
</body>
</html>