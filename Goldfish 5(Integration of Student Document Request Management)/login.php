<?php
session_start();
require 'chckroles.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT id, Role, Username, Password FROM tbusers WHERE Username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Plain-text comparison (matches existing system)
    if ((string)$row['Password'] === (string)$password) {
        $_SESSION['loggedin'] = true;
        $_SESSION['user_id']  = $row['id'];
        $_SESSION['username'] = $row['Username'];
        $_SESSION['role']     = strtolower($row['Role']);

        if ($_SESSION['role'] === 'admin') {
            header('Location: admin dashboard.php');
        } else {
            header('Location: student dashboard.php');
        }
        exit;
    }
}

header('Location: index.php?error=' . urlencode('Invalid username or password.'));
exit;
?>