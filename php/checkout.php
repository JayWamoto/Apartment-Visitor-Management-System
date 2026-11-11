<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $visit_id = intval($_GET['id']);
    
    $stmt = $conn->prepare("UPDATE visit_logs SET exit_timestamp = NOW(), status = 'COMPLETED' WHERE visit_id = ?");
    $stmt->bind_param("i", $visit_id);
    
    if ($stmt->execute()) {
        header("Location: guard.php");
        exit;
    } else {
        die("Error checking out visitor: " . $conn->error);
    }
} else {
    header("Location: guard.php");
    exit;
}
?>