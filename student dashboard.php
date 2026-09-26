<?php
session_start();
require 'chckroles.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'student') {
    header('Location: accessdenied.php');
    exit;
}

$section = $_GET['section'] ?? 'dashboard';
$uid = (int)$_SESSION['user_id'];

/* ---------- HELPER FUNCTION ---------- */
function grade_status($grade) {
    if ($grade === null) return 'Not Graded';
    return ($grade <= 3.00) ? 'Passed' : 'Failed';
}

/* ---------- DASHBOARD COMPUTATION ---------- */
$dash = $conn->prepare("
    SELECT
        COUNT(*) AS subjects_taken,
        SUM(CASE WHEN grade IS NOT NULL AND grade <= 3.00 THEN 1 ELSE 0 END) AS subjects_passed,
        SUM(CASE WHEN grade IS NOT NULL AND grade > 3.00 THEN 1 ELSE 0 END) AS subjects_failed
    FROM tbstudent_subjects
    WHERE user_id = ?
");
$dash->bind_param('i', $uid);
$dash->execute();
$summary = $dash->get_result()->fetch_assoc();

$subjects_taken  = (int)($summary['subjects_taken'] ?? 0);
$subjects_passed = (int)($summary['subjects_passed'] ?? 0);
$subjects_failed = (int)($summary['subjects_failed'] ?? 0);
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
    <a href="student dashboard.php?section=dashboard">Dashboard</a><br>
    <a href="student dashboard.php?section=subjects">My Subjects</a><br>
    <a href="student dashboard.php?section=grades">My Grades</a><br>
    <a href="student dashboard.php?section=profile">My Profile</a><br>
</nav>

<hr>

<?php if ($section === 'dashboard'): ?>
    <h2>Academic Summary</h2>

    <table border="1" cellpadding="10">
        <tr>
            <th>Subjects Taken</th>
            <th>Subjects Passed</th>
            <th>Subjects Failed</th>
        </tr>
        <tr>
            <td align="center"><?php echo $subjects_taken; ?></td>
            <td align="center"><?php echo $subjects_passed; ?></td>
            <td align="center"><?php echo $subjects_failed; ?></td>
        </tr>
    </table>

    <h3>Quick Overview</h3>
    <table border="1" cellpadding="6">
        <tr>
            <th>Subject Code</th><th>Subject Name</th><th>Units</th><th>Grade</th><th>Status</th>
        </tr>
        <?php
        $stmt = $conn->prepare("
            SELECT s.subject_code, s.subject_name, s.units, ss.grade
            FROM tbstudent_subjects ss
            JOIN tbsubjects s ON s.id = ss.subject_id
            WHERE ss.user_id = ?
            ORDER BY s.subject_code
        ");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0):
        ?>
            <tr><td colspan="5">No subjects enrolled yet.</td></tr>
        <?php else: while ($row = $res->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
                <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                <td><?php echo $row['units']; ?></td>
                <td><?php echo $row['grade'] !== null ? number_format($row['grade'], 2) : '—'; ?></td>
                <td><?php echo grade_status($row['grade']); ?></td>
            </tr>
        <?php endwhile; endif; ?>
    </table>

<?php elseif ($section === 'subjects'): ?>
    <h2>My Subjects</h2>
    <table border="1" cellpadding="6">
        <tr>
            <th>Subject Code</th><th>Subject Name</th><th>Units</th>
        </tr>
        <?php
        $stmt = $conn->prepare("
            SELECT s.subject_code, s.subject_name, s.units
            FROM tbstudent_subjects ss
            JOIN tbsubjects s ON s.id = ss.subject_id
            WHERE ss.user_id = ?
            ORDER BY s.subject_code
        ");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0):
        ?>
            <tr><td colspan="3">No subjects enrolled yet.</td></tr>
        <?php else: while ($row = $res->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
                <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                <td><?php echo $row['units']; ?></td>
            </tr>
        <?php endwhile; endif; ?>
    </table>

<?php elseif ($section === 'grades'): ?>
    <h2>My Grades</h2>
    <table border="1" cellpadding="6">
        <tr>
            <th>Subject Code</th><th>Subject Name</th><th>Units</th><th>Grade</th><th>Status</th>
        </tr>
        <?php
        $stmt = $conn->prepare("
            SELECT s.subject_code, s.subject_name, s.units, ss.grade
            FROM tbstudent_subjects ss
            JOIN tbsubjects s ON s.id = ss.subject_id
            WHERE ss.user_id = ?
            ORDER BY s.subject_code
        ");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0):
        ?>
            <tr><td colspan="5">No grades available yet.</td></tr>
        <?php else: while ($row = $res->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
                <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                <td><?php echo $row['units']; ?></td>
                <td><?php echo $row['grade'] !== null ? number_format($row['grade'], 2) : '—'; ?></td>
                <td><?php echo grade_status($row['grade']); ?></td>
            </tr>
        <?php endwhile; endif; ?>
    </table>

<?php elseif ($section === 'profile'): ?>
    <h2>My Profile</h2>
    <p>Your personal information.</p>

<?php else: ?>
    <p>Select an option from the menu above.</p>
<?php endif; ?>

<p><a href="index.php?logout=1">Logout</a></p>

</body>
</html>