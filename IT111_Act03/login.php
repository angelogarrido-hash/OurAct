<?php
session_start();
require 'chckroles.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT Role, Username, Password FROM tbusers WHERE Username = ? AND Password = ?");
$stmt->bind_param('si', $username, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $_SESSION['loggedin'] = true;
    $_SESSION['username'] = $row['Username'];
    $_SESSION['role']     = strtolower($row['Role']);

    if ($_SESSION['role'] === 'admin') {
        header('Location: admin dashboard.php');
    } else {
        header('Location: student dashboard.php');
    }
    exit;
} else {
    header('Location: accessdenied.php');
    exit;
}
?>