<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$user = 'root';
$pass = ''; // Default XAMPP password is empty
$dbname = 'apartment_vms'; // <-- Ensure variable name is exactly $dbname

// Pass all 4 parameters to select the database immediately on connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>