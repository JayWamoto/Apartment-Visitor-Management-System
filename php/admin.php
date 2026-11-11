<?php
session_start();
require_once 'db.php';

// Enforce Role-Based Access Control: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    die("<h3 style='color:red;'>Access Denied: Only Administrators can view this page.</h3><a href='guard.php'>Go Back</a>");
}

$message = '';
$error = '';

// --- 1. HANDLE ADDING NEW APARTMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_apartment') {
    $block_number = trim($_POST['block_number']);
    $unit_number = trim($_POST['unit_number']);
    $floor = intval($_POST['floor']);
    
    if (!empty($block_number) && !empty($unit_number)) {
        $stmt = $conn->prepare("INSERT INTO apartments (block_number, unit_number, floor, is_active) VALUES (?, ?, ?, TRUE)");
        $stmt->bind_param("ssi", $block_number, $unit_number, $floor);
        
        if ($stmt->execute()) {
            $message = "Apartment unit created successfully!";
        } else {
            $error = "Error creating apartment: " . $conn->error;
        }
    }
}

// --- 2. HANDLE TOGGLING APARTMENT STATUS (Active <-> Inactive) ---
if (isset($_GET['toggle_apartment'])) {
    $apartment_id = intval($_GET['toggle_apartment']);
    
    $stmt = $conn->prepare("UPDATE apartments SET is_active = NOT is_active WHERE apartment_id = ?");
    $stmt->bind_param("i", $apartment_id);
    
    if ($stmt->execute()) {
        $message = "Apartment status updated successfully!";
    } else {
        $error = "Error updating apartment status: " . $conn->error;
    }
}

// --- 3. HANDLE ADDING NEW SECURITY GUARD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_guard') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (!empty($username) && !empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $role = 'SECURITY_GUARD';
        
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role, is_active) VALUES (?, ?, ?, TRUE)");
        $stmt->bind_param("sss", $username, $hashed_password, $role);
        
        if ($stmt->execute()) {
            $message = "Security Guard account created successfully!";
        } else {
            $error = "Error creating guard account: " . $conn->error;
        }
    }
}

// --- 4. HANDLE TOGGLING GUARD ACCOUNT STATUS (Active <-> Deactivated) ---
if (isset($_GET['toggle_guard'])) {
    $guard_id = intval($_GET['toggle_guard']);
    
    if ($guard_id === $_SESSION['user_id']) {
        $error = "You cannot deactivate your own logged-in admin account.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ? AND role = 'SECURITY_GUARD'");
        $stmt->bind_param("i", $guard_id);
        
        if ($stmt->execute()) {
            $message = "Guard account status updated successfully!";
        } else {
            $error = "Error updating guard status: " . $conn->error;
        }
    }
}

// --- 5. HANDLE ADDING NEW RESIDENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_resident') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone_number']);
    $emergency = trim($_POST['emergency_contact']);
    $apartment_id = intval($_POST['apartment_id']);
    
    if (!empty($name) && !empty($phone) && $apartment_id > 0) {
        $stmt = $conn->prepare("INSERT INTO residents (apartment_id, name, phone_number, emergency_contact) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $apartment_id, $name, $phone, $emergency);
        
        if ($stmt->execute()) {
            $message = "Resident added successfully!";
        } else {
            $error = "Error adding resident: " . $conn->error;
        }
    }
}

// --- 6. HANDLE DELETING A RESIDENT ---
if (isset($_GET['delete_resident'])) {
    $resident_id = intval($_GET['delete_resident']);
    
    $stmt = $conn->prepare("DELETE FROM residents WHERE resident_id = ?");
    $stmt->bind_param("i", $resident_id);
    
    if ($stmt->execute()) {
        $message = "Resident deleted successfully!";
    } else {
        $error = "Cannot delete resident: Visit logs exist for this resident.";
    }
}

// --- FETCH DATA FOR DASHBOARD ---
$allApartments = $conn->query("SELECT * FROM apartments ORDER BY block_number, unit_number ASC");
$guards = $conn->query("SELECT user_id, username, role, is_active, created_at FROM users WHERE role = 'SECURITY_GUARD' ORDER BY user_id DESC");
$residents = $conn->query("
    SELECT r.resident_id, r.name, r.phone_number, r.emergency_contact, CONCAT(a.block_number, ' - Unit ', a.unit_number) AS unit, a.floor, a.is_active AS apt_status
    FROM residents r
    JOIN apartments a ON r.apartment_id = a.apartment_id
    ORDER BY r.resident_id DESC
");
$activeApartments = $conn->query("SELECT apartment_id, block_number, unit_number FROM apartments WHERE is_active = TRUE ORDER BY block_number, unit_number ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Visitor Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #121212; color: #e0e0e0; }
        .card { background-color: #1e1e1e; border: 1px solid #333; }
        .form-control, .form-select { background-color: #2b2b2b; color: #fff; border: 1px solid #444; }
        .form-control:focus, .form-select:focus { background-color: #333; color: #fff; }
        .table-dark { background-color: #1e1e1e; }
    </style>
</head>
<body class="container-fluid py-4 px-4">
    <!-- Header (Wireframe 6.2) -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-light">ADMIN DASHBOARD</h2>
        <div>
            Logged in: <strong class="text-danger"><?= htmlspecialchars($_SESSION['username']) ?></strong>
            <a href="reports.php" class="btn btn-sm btn-warning ms-2 fw-bold">📊 Reports & Printing</a>
            <a href="guard.php" class="btn btn-sm btn-outline-info ms-2">Guard View</a>
            <a href="logout.php" class="btn btn-sm btn-outline-danger ms-2">Logout</a>
        </div>
    </div>

    <?php if ($message): ?><div class="alert alert-success alert-dismissible fade show"><?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?= $error ?></div><?php endif; ?>

    <div class="row g-4">
        <!-- 1. MANAGE APARTMENTS (INACTIVE TOGGLE) -->
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h5 class="text-primary mb-3">MANAGE APARTMENTS</h5>
                <form method="POST" action="admin.php" class="row g-2 mb-4">
                    <input type="hidden" name="action" value="add_apartment">
                    <div class="col-4">
                        <label class="form-label">Block</label>
                        <input type="text" name="block_number" class="form-control" placeholder="A" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Unit No</label>
                        <input type="text" name="unit_number" class="form-control" placeholder="101" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Floor</label>
                        <input type="number" name="floor" class="form-control" placeholder="1" required>
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">+ ADD APARTMENT</button>
                    </div>
                </form>

                <h6 class="text-secondary mb-2">APARTMENT DIRECTORY</h6>
                <div class="table-responsive" style="max-height: 250px;">
                    <table class="table table-dark table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Unit</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($apt = $allApartments->fetch_assoc()): ?>
                            <tr>
                                <td>Block <?= htmlspecialchars($apt['block_number']) ?> - <?= htmlspecialchars($apt['unit_number']) ?></td>
                                <td>
                                    <?php if ($apt['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive / Repair</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="admin.php?toggle_apartment=<?= $apt['apartment_id'] ?>" 
                                       class="btn btn-sm <?= $apt['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                       <?= $apt['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 2. MANAGE GUARDS (WITH DEACTIVATION TOGGLE) -->
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h5 class="text-primary mb-3">MANAGE SECURITY GUARDS</h5>
                <form method="POST" action="admin.php" class="mb-4">
                    <input type="hidden" name="action" value="add_guard">
                    <div class="mb-2">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required placeholder="Guard_02">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="Password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">+ CREATE GUARD</button>
                </form>

                <h6 class="text-secondary mb-2">SECURITY GUARDS LIST</h6>
                <div class="table-responsive" style="max-height: 250px;">
                    <table class="table table-dark table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($guards && $guards->num_rows > 0): ?>
                                <?php while ($g = $guards->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($g['username']) ?></td>
                                    <td>
                                        <?php if ($g['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Deactivated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="admin.php?toggle_guard=<?= $g['user_id'] ?>" 
                                           class="btn btn-sm <?= $g['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                           <?= $g['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-muted text-center py-2">No security guards found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. MANAGE RESIDENTS (WITH DELETE - Wireframe 6.2) -->
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h5 class="text-primary mb-3">MANAGE RESIDENTS</h5>
                <form method="POST" action="admin.php" class="row g-2 mb-4">
                    <input type="hidden" name="action" value="add_resident">
                    <div class="col-6">
                        <input type="text" name="name" class="form-control" required placeholder="Full Name">
                    </div>
                    <div class="col-6">
                        <input type="text" name="phone_number" class="form-control" required placeholder="Phone Number">
                    </div>
                    <div class="col-6">
                        <input type="text" name="emergency_contact" class="form-control" required placeholder="Emergency Contact">
                    </div>
                    <div class="col-6">
                        <select name="apartment_id" class="form-select" required>
                            <option value="">Select Active Unit</option>
                            <?php while ($apt = $activeApartments->fetch_assoc()): ?>
                                <option value="<?= $apt['apartment_id'] ?>">Block <?= $apt['block_number'] ?> - <?= $apt['unit_number'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12 mt-2">
                        <button type="submit" class="btn btn-success w-100 fw-bold">+ ADD RESIDENT</button>
                    </div>
                </form>

                <h6 class="text-secondary mb-2">RESIDENT DIRECTORY</h6>
                <div class="table-responsive" style="max-height: 250px;">
                    <table class="table table-dark table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Unit</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($residents && $residents->num_rows > 0): ?>
                                <?php while ($r = $residents->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['name']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($r['unit']) ?>
                                        <?php if (!$r['apt_status']): ?>
                                            <span class="badge bg-danger ms-1">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="admin.php?delete_resident=<?= $r['resident_id'] ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete resident <?= htmlspecialchars($r['name']) ?>?');">
                                           Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-muted text-center py-2">No residents registered yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

</html>