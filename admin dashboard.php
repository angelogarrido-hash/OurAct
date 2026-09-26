<?php
session_start();
require 'chckroles.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: accessdenied.php');
    exit;
}

$section = $_GET['section'] ?? 'dashboard';
$message = '';

/* ---------- HANDLE FORM SUBMISSIONS ---------- */

// Add Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $code = trim($_POST['subject_code'] ?? '');
    $name = trim($_POST['subject_name'] ?? '');
    $units = (int)($_POST['units'] ?? 0);

    if ($code !== '' && $name !== '' && $units > 0) {
        $stmt = $conn->prepare("INSERT INTO tbsubjects (subject_code, subject_name, units) VALUES (?, ?, ?)");
        $stmt->bind_param('ssi', $code, $name, $units);
        if ($stmt->execute()) {
            $message = "Subject added successfully.";
        } else {
            $message = "Error: " . $conn->error;
        }
    } else {
        $message = "Please fill in all fields correctly.";
    }
}

// Delete Subject
if (isset($_GET['delete_subject'])) {
    $sid = (int)$_GET['delete_subject'];
    $conn->query("DELETE FROM tbstudent_subjects WHERE subject_id = $sid");
    $conn->query("DELETE FROM tbsubjects WHERE id = $sid");
    $message = "Subject deleted.";
}

// Assign Subject to Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_subject'])) {
    $uid = (int)($_POST['user_id'] ?? 0);
    $sid = (int)($_POST['subject_id'] ?? 0);

    if ($uid > 0 && $sid > 0) {
        // Prevent duplicates
        $check = $conn->prepare("SELECT id FROM tbstudent_subjects WHERE user_id = ? AND subject_id = ?");
        $check->bind_param('ii', $uid, $sid);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO tbstudent_subjects (user_id, subject_id, grade) VALUES (?, ?, NULL)");
            $stmt->bind_param('ii', $uid, $sid);
            $stmt->execute();
            $message = "Subject assigned to student.";
        } else {
            $message = "This subject is already assigned to that student.";
        }
    } else {
        $message = "Please select a student and a subject.";
    }
}

// Update Grade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_grade'])) {
    $ssid  = (int)($_POST['ss_id'] ?? 0);
    $grade = $_POST['grade'] === '' ? null : (float)$_POST['grade'];

    $stmt = $conn->prepare("UPDATE tbstudent_subjects SET grade = ? WHERE id = ?");
    $stmt->bind_param('di', $grade, $ssid);
    if ($stmt->execute()) {
        $message = "Grade updated.";
    } else {
        $message = "Error updating grade.";
    }
}

// Remove assignment
if (isset($_GET['remove_assignment'])) {
    $ssid = (int)$_GET['remove_assignment'];
    $conn->query("DELETE FROM tbstudent_subjects WHERE id = $ssid");
    $message = "Assignment removed.";
}

/* ---------- HELPER FUNCTION ---------- */
function grade_status($grade) {
    if ($grade === null) return 'Not Graded';
    return ($grade <= 3.00) ? 'Passed' : 'Failed';
}
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
    <a href="admin dashboard.php?section=subjects">Subject Management</a><br>
    <a href="admin dashboard.php?section=assign">Student Subject Assignment</a><br>
    <a href="admin dashboard.php?section=grades">Grade Management</a><br>
    <a href="admin dashboard.php?section=profile">Profile</a><br>
</nav>

<hr>

<?php if ($message): ?>
    <p style="color:green;"><strong><?php echo htmlspecialchars($message); ?></strong></p>
<?php endif; ?>

<?php if ($section === 'students'): ?>
    <h2>Student Management</h2>
    <p>Here you can manage students.</p>

<?php elseif ($section === 'users'): ?>
    <h2>User Management</h2>
    <p>Here you can manage users.</p>

<?php elseif ($section === 'subjects'): ?>
    <h2>Subject Management</h2>

    <h3>Add New Subject</h3>
    <form method="post" action="admin dashboard.php?section=subjects">
        <label>Subject Code:</label>
        <input type="text" name="subject_code" required><br>
        <label>Subject Name:</label>
        <input type="text" name="subject_name" required><br>
        <label>Units:</label>
        <input type="number" name="units" min="1" required><br>
        <button type="submit" name="add_subject">Add Subject</button>
    </form>

    <h3>Existing Subjects</h3>
    <table border="1" cellpadding="6">
        <tr>
            <th>ID</th><th>Code</th><th>Name</th><th>Units</th><th>Action</th>
        </tr>
        <?php
        $res = $conn->query("SELECT * FROM tbsubjects ORDER BY subject_code");
        while ($row = $res->fetch_assoc()):
        ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
            <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
            <td><?php echo $row['units']; ?></td>
            <td>
                <a href="admin dashboard.php?section=subjects&delete_subject=<?php echo $row['id']; ?>"
                   onclick="return confirm('Delete this subject?');">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

<?php elseif ($section === 'assign'): ?>
    <h2>Student Subject Assignment</h2>

    <form method="post" action="admin dashboard.php?section=assign">
        <label>Student:</label>
        <select name="user_id" required>
            <option value="">-- Select Student --</option>
            <?php
            $students = $conn->query("SELECT id, Username FROM tbusers WHERE LOWER(Role) = 'student' ORDER BY Username");
            while ($s = $students->fetch_assoc()):
            ?>
                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['Username']); ?></option>
            <?php endwhile; ?>
        </select><br>

        <label>Subject:</label>
        <select name="subject_id" required>
            <option value="">-- Select Subject --</option>
            <?php
            $subs = $conn->query("SELECT id, subject_code, subject_name FROM tbsubjects ORDER BY subject_code");
            while ($s = $subs->fetch_assoc()):
            ?>
                <option value="<?php echo $s['id']; ?>">
                    <?php echo htmlspecialchars($s['subject_code'] . ' - ' . $s['subject_name']); ?>
                </option>
            <?php endwhile; ?>
        </select><br>

        <button type="submit" name="assign_subject">Assign Subject</button>
    </form>

    <h3>Current Assignments</h3>
    <table border="1" cellpadding="6">
        <tr>
            <th>Student</th><th>Subject Code</th><th>Subject Name</th><th>Units</th><th>Grade</th><th>Status</th><th>Action</th>
        </tr>
        <?php
        $sql = "SELECT ss.id AS ss_id, u.Username, s.subject_code, s.subject_name, s.units, ss.grade
                FROM tbstudent_subjects ss
                JOIN tbusers u ON u.id = ss.user_id
                JOIN tbsubjects s ON s.id = ss.subject_id
                ORDER BY u.Username, s.subject_code";
        $res = $conn->query($sql);
        while ($row = $res->fetch_assoc()):
        ?>
        <tr>
            <td><?php echo htmlspecialchars($row['Username']); ?></td>
            <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
            <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
            <td><?php echo $row['units']; ?></td>
            <td><?php echo $row['grade'] !== null ? number_format($row['grade'], 2) : '—'; ?></td>
            <td><?php echo grade_status($row['grade']); ?></td>
            <td>
                <a href="admin dashboard.php?section=assign&remove_assignment=<?php echo $row['ss_id']; ?>"
                   onclick="return confirm('Remove this assignment?');">Remove</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

<?php elseif ($section === 'grades'): ?>
    <h2>Grade Management</h2>

    <table border="1" cellpadding="6">
        <tr>
            <th>Student</th><th>Subject</th><th>Units</th><th>Grade</th><th>Status</th><th>Action</th>
        </tr>
        <?php
        $sql = "SELECT ss.id AS ss_id, u.Username, s.subject_code, s.subject_name, s.units, ss.grade
                FROM tbstudent_subjects ss
                JOIN tbusers u ON u.id = ss.user_id
                JOIN tbsubjects s ON s.id = ss.subject_id
                ORDER BY u.Username, s.subject_code";
        $res = $conn->query($sql);
        while ($row = $res->fetch_assoc()):
        ?>
        <tr>
            <td><?php echo htmlspecialchars($row['Username']); ?></td>
            <td><?php echo htmlspecialchars($row['subject_code'] . ' - ' . $row['subject_name']); ?></td>
            <td><?php echo $row['units']; ?></td>
            <td>
                <form method="post" action="admin dashboard.php?section=grades" style="display:inline;">
                    <input type="hidden" name="ss_id" value="<?php echo $row['ss_id']; ?>">
                    <input type="number" step="0.01" min="1" max="5" name="grade"
                           value="<?php echo $row['grade'] !== null ? $row['grade'] : ''; ?>" required>
            </td>
            <td><?php echo grade_status($row['grade']); ?></td>
            <td>
                    <button type="submit" name="update_grade">Save</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

<?php elseif ($section === 'profile'): ?>
    <h2>Profile</h2>
    <p>Your admin profile information.</p>

<?php else: ?>
    <p>Select an option from the menu above.</p>
<?php endif; ?>

<p><a href="index.php?logout=1">Logout</a></p>

</body>
</html>