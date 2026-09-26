<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: accessdenied.php');
    exit;
}

$section = $_GET['section'] ?? 'dashboard';
?>











<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Admin Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>

<h2>Admin Menu</h2>
<nav>
    <a href="admin dashboard.php?section=students">Student Management</a><br>
    <a href="admin dashboard.php?section=users">User Management</a><br>
    <a href="admin dashboard.php?section=grades">Grade Management</a><br>
    <a href="admin dashboard.php?section=profile">Profile</a><br>
</nav>

<hr>

<?php if ($section === 'students'): ?>
    <h2>Student Management</h2>
    <p>Here you can manage students.</p>
<?php elseif ($section === 'users'): ?>
    <h2>User Management</h2>
    <p>Here you can manage users.</p>
<?php elseif ($section === 'grades'): ?>
    <h2>Grade Management</h2>
    <p>Here you can manage grades.</p>
<?php elseif ($section === 'profile'): ?>
    <h2>Profile</h2>
    <p>Your admin profile information.</p>
<?php else: ?>
    <p>Select an option from the menu above.</p>
<?php endif; ?>

<p><a href="index.php?logout=1">Logout</a></p>

</body>
</html>