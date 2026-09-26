<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'student') {
    header('Location: accessdenied.php');
    exit;
}

$section = $_GET['section'] ?? 'dashboard';
?>





<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Student Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>

<h2>Student Menu</h2>
<nav>
    <a href="student dashboard.php?section=profile">My Profile</a><br>
    <a href="student dashboard.php?section=grades">My Grades</a><br>
</nav>

<hr>

<?php if ($section === 'profile'): ?>
    <h2>My Profile</h2>
    <p>Your personal information.</p>
<?php elseif ($section === 'grades'): ?>
    <h2>My Grades</h2>
    <p>Your grades are displayed here.</p>
<?php else: ?>
    <p>Select an option from the menu above.</p>
<?php endif; ?>

<p><a href="index.php?logout=1">Logout</a></p>

</body>
</html>