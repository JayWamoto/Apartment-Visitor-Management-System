<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$full_name = trim($_POST['full_name']);
$id_number = trim($_POST['id_number']);
$phone_number = trim($_POST['phone_number']);
$vehicle_number = trim($_POST['vehicle_number']);
$resident_id = intval($_POST['resident_id']);
$guard_id = $_SESSION['user_id'];

if (empty($full_name) || empty($id_number) || empty($phone_number) || empty($resident_id)) {
    die("Please fill in all required fields.");
}

// 1. Check if visitor exists in database
$stmt = $conn->prepare("SELECT visitor_id FROM visitors WHERE id_number = ?");
$stmt->bind_param("s", $id_number);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $visitor_id = $row['visitor_id'];
} else {
    // Insert new visitor record
    $stmt = $conn->prepare("INSERT INTO visitors (full_name, phone_number, id_number, vehicle_number) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $full_name, $phone_number, $id_number, $vehicle_number);
    $stmt->execute();
    $visitor_id = $stmt->insert_id;
}

// 2. Create visit log entry with ONGOING status
$stmt = $conn->prepare("INSERT INTO visit_logs (visitor_id, resident_id, recorded_by_user_id, status) VALUES (?, ?, ?, 'ONGOING')");
$stmt->bind_param("iii", $visitor_id, $resident_id, $guard_id);

if ($stmt->execute()) {
    header("Location: guard.php");
    exit;
} else {
    die("Error saving visit log: " . $conn->error);
}
?>