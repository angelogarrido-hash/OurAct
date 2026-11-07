<?php
session_start();
require 'chckroles.php';
require 'attendance_helpers.php';
require 'document_helpers.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'student') {
    header('Location: accessdenied.php');
    exit;
}

$section = $_GET['section'] ?? 'dashboard';
$uid = (int)$_SESSION['user_id'];

/* ---------- ACCESS RULES FOR ATTENDANCE ---------- */
// Students may never record, edit or delete attendance.
if (isset($_POST['record_attendance']) || isset($_POST['update_attendance']) || isset($_POST['delete_attendance'])
    || isset($_GET['edit_attendance'])) {
    header('Location: accessdenied.php');
    exit;
}
// Students may only see their OWN attendance: any request naming another student is denied.
foreach (['student_id', 'user_id', 'f_student'] as $key) {
    if (isset($_GET[$key]) && (int)$_GET[$key] !== $uid) {
        header('Location: accessdenied.php');
        exit;
    }
}

/* ---------- ACCESS RULES FOR DOCUMENT REQUESTS ---------- */
// Students may never approve, reject, release, delete or change the status/remarks of a request.
foreach (['approve_request', 'reject_request', 'release_request', 'delete_request',
          'view_request', 'status', 'remarks'] as $key) {
    if (isset($_POST[$key]) || isset($_GET[$key])) {
        header('Location: accessdenied.php');
        exit;
    }
}
// A request can only be submitted for yourself.
if (isset($_POST['user_id']) && (int)$_POST['user_id'] !== $uid) {
    header('Location: accessdenied.php');
    exit;
}

$message = '';

// Submit a document request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $type    = $_POST['document_type'] ?? '';
    $purpose = trim($_POST['purpose'] ?? '');

    if (!in_array($type, DOC_TYPES, true)) {
        $message = "Please select a valid document type.";
    } elseif ($purpose === '') {
        $message = "Please enter the purpose of your request.";
    } elseif (strlen($purpose) > 255) {
        $message = "The purpose must be 255 characters or fewer.";
    } else {
        $stmt = $conn->prepare("INSERT INTO tbdocument_requests (user_id, document_type, purpose) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $uid, $type, $purpose);
        $stmt->execute();
        $message = "Your request has been submitted.";
    }
}

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

/* ---------- ATTENDANCE COMPUTATION (live from tbattendance) ---------- */
$att_rows   = attendance_summary($conn, $uid);
$att_totals = attendance_totals($att_rows);
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
    <a href="student dashboard.php?section=attendance">My Attendance</a><br>
    <a href="student dashboard.php?section=requests">Document Requests</a><br>
    <a href="student dashboard.php?section=profile">My Profile</a><br>
</nav>

<hr>

<?php if ($message): ?>
    <p style="color:green;"><strong><?php echo htmlspecialchars($message); ?></strong></p>
<?php endif; ?>

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

    <h2>Attendance Summary</h2>

    <table border="1" cellpadding="10">
        <tr>
            <th>Total Present</th>
            <th>Total Absent</th>
            <th>Total Late</th>
        </tr>
        <tr>
            <td align="center"><?php echo $att_totals['present']; ?></td>
            <td align="center"><?php echo $att_totals['absent']; ?></td>
            <td align="center"><?php echo $att_totals['late']; ?></td>
        </tr>
    </table>

    <h2>Document Request Summary</h2>
    <?php $req_counts = request_counts($conn, $uid); ?>

    <table border="1" cellpadding="10">
        <tr>
            <th>Pending</th>
            <th>Approved</th>
            <th>Rejected</th>
            <th>Released</th>
        </tr>
        <tr>
            <td align="center"><?php echo $req_counts['Pending']; ?></td>
            <td align="center"><?php echo $req_counts['Approved']; ?></td>
            <td align="center"><?php echo $req_counts['Rejected']; ?></td>
            <td align="center"><?php echo $req_counts['Released']; ?></td>
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

<?php elseif ($section === 'attendance'): ?>
    <h2>My Attendance</h2>
    <table border="1" cellpadding="6">
        <tr>
            <th>Subject Code</th><th>Subject Name</th><th>Present</th><th>Absent</th><th>Late</th>
        </tr>
        <?php if (count($att_rows) === 0): ?>
            <tr><td colspan="5">No subjects enrolled yet, so there is no attendance to show.</td></tr>
        <?php else: foreach ($att_rows as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
                <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                <td><?php echo (int)$row['present']; ?></td>
                <td><?php echo (int)$row['absent']; ?></td>
                <td><?php echo (int)$row['late']; ?></td>
            </tr>
        <?php endforeach; ?>
            <tr>
                <th colspan="2">Total</th>
                <th><?php echo $att_totals['present']; ?></th>
                <th><?php echo $att_totals['absent']; ?></th>
                <th><?php echo $att_totals['late']; ?></th>
            </tr>
        <?php endif; ?>
    </table>
    <?php if (count($att_rows) > 0 && array_sum($att_totals) === 0): ?>
        <p>No attendance has been recorded for you yet.</p>
    <?php endif; ?>

<?php elseif ($section === 'requests'): ?>
    <h2>Document Requests</h2>

    <h3>Submit a Request</h3>
    <form method="post" action="student dashboard.php?section=requests">
        <label>Document Type:</label>
        <select name="document_type" required>
            <option value="">-- Select Document --</option>
            <?php foreach (DOC_TYPES as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
            <?php endforeach; ?>
        </select><br>
        <label>Purpose:</label>
        <input type="text" name="purpose" maxlength="255" size="50" required><br>
        <button type="submit" name="submit_request">Submit Request</button>
    </form>

    <h3>My Requests</h3>
    <table border="1" cellpadding="6">
        <tr>
            <th>Date Requested</th><th>Document</th><th>Purpose</th><th>Status</th><th>Admin Remarks</th><th>Last Updated</th>
        </tr>
        <?php
        // Always filtered by the logged-in student's own id.
        $stmt = $conn->prepare("
            SELECT document_type, purpose, status, remarks, requested_at,
                   COALESCE(released_at, processed_at) AS last_update
            FROM tbdocument_requests
            WHERE user_id = ?
            ORDER BY requested_at DESC, id DESC
        ");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0):
        ?>
            <tr><td colspan="6">You have not submitted any document requests yet.</td></tr>
        <?php else: while ($row = $res->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['requested_at']); ?></td>
                <td><?php echo htmlspecialchars($row['document_type']); ?></td>
                <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
                <td><?php echo $row['remarks'] !== null && $row['remarks'] !== '' ? htmlspecialchars($row['remarks']) : '—'; ?></td>
                <td><?php echo $row['last_update'] !== null ? htmlspecialchars($row['last_update']) : '—'; ?></td>
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