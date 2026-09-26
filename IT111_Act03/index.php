<?php
session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (isset($_SESSION['loggedin'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin dashboard.php');
    } else {
        header('Location: student dashboard.php');
    }
    exit;
}
?>










<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h2>Login</h2>
<form action="login.php" method="post">
    <label>Username:</label>
    <input type="text" name="username" required><br>
    <label>Password:</label>
    <input type="password" name="password" required><br>
    <button type="submit">Login</button>
</form>

</body>
</html>