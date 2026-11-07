<?php
session_start();
require 'chckroles.php';
require 'attendance_helpers.php';
require 'document_helpers.php';

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

/* ---------- ATTENDANCE HANDLERS ---------- */
$att_statuses = ['Present', 'Absent', 'Late'];

// Record Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_attendance'])) {
    $uid    = (int)($_POST['user_id'] ?? 0);
    $sid    = (int)($_POST['subject_id'] ?? 0);
    $date   = trim($_POST['attendance_date'] ?? '');
    $status = $_POST['status'] ?? '';

    if ($uid <= 0 || $sid <= 0 || !valid_date($date) || !in_array($status, $att_statuses, true)) {
        $message = "Please select a student, a valid date and a status.";
    } else {
        // Only students enrolled in the selected subject may be recorded
        $chk = $conn->prepare("SELECT id FROM tbstudent_subjects WHERE user_id = ? AND subject_id = ?");
        $chk->bind_param('ii', $uid, $sid);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows === 0) {
            $message = "That student is not enrolled in the selected subject.";
        } else {
            $dup = $conn->prepare("SELECT id FROM tbattendance WHERE user_id = ? AND subject_id = ? AND attendance_date = ?");
            $dup->bind_param('iis', $uid, $sid, $date);
            $dup->execute();
            $dup->store_result();

            if ($dup->num_rows > 0) {
                $message = "Attendance for that student, subject and date already exists. Edit it instead.";
            } else {
                try {
                    $stmt = $conn->prepare("INSERT INTO tbattendance (user_id, subject_id, attendance_date, status) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('iiss', $uid, $sid, $date, $status);
                    $stmt->execute();
                    $message = "Attendance recorded.";
                } catch (mysqli_sql_exception $e) {
                    $message = "Error recording attendance.";
                }
            }
        }
    }
}

// Update Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    $aid    = (int)($_POST['att_id'] ?? 0);
    $date   = trim($_POST['attendance_date'] ?? '');
    $status = $_POST['status'] ?? '';

    if ($aid <= 0 || !valid_date($date) || !in_array($status, $att_statuses, true)) {
        $message = "Please provide a valid date and status.";
    } else {
        $cur = $conn->prepare("SELECT user_id, subject_id FROM tbattendance WHERE id = ?");
        $cur->bind_param('i', $aid);
        $cur->execute();
        $c = $cur->get_result()->fetch_assoc();

        if (!$c) {
            $message = "Attendance record not found.";
        } else {
            $dup = $conn->prepare("SELECT id FROM tbattendance WHERE user_id = ? AND subject_id = ? AND attendance_date = ? AND id <> ?");
            $dup->bind_param('iisi', $c['user_id'], $c['subject_id'], $date, $aid);
            $dup->execute();
            $dup->store_result();

            if ($dup->num_rows > 0) {
                $message = "Another record already exists for that student, subject and date.";
            } else {
                try {
                    $stmt = $conn->prepare("UPDATE tbattendance SET attendance_date = ?, status = ? WHERE id = ?");
                    $stmt->bind_param('ssi', $date, $status, $aid);
                    $stmt->execute();
                    $message = "Attendance updated.";
                } catch (mysqli_sql_exception $e) {
                    $message = "Error updating attendance.";
                }
            }
        }
    }
}

// Delete Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attendance'])) {
    $aid = (int)($_POST['att_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM tbattendance WHERE id = ?");
    $stmt->bind_param('i', $aid);
    $stmt->execute();
    $message = "Attendance record deleted.";
}

/* ---------- DOCUMENT REQUEST HANDLERS ---------- */
$admin_id = (int)$_SESSION['user_id'];

// Approve a PENDING request (remarks optional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request'])) {
    $rid     = (int)($_POST['request_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if (strlen($remarks) > 255) {
        $message = "Remarks must be 255 characters or fewer.";
    } else {
        $stmt = $conn->prepare("UPDATE tbdocument_requests
                                SET status = 'Approved',
                                    remarks = NULLIF(?, ''),
                                    processed_by = ?, processed_at = NOW()
                                WHERE id = ? AND status = 'Pending'");
        $stmt->bind_param('sii', $remarks, $admin_id, $rid);
        $stmt->execute();
        $message = $stmt->affected_rows > 0 ? "Request approved." : "Only a pending request can be approved.";
    }
}

// Reject a PENDING request (remarks are required)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])) {
    $rid     = (int)($_POST['request_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($remarks === '') {
        $message = "Please provide remarks when rejecting a request.";
    } elseif (strlen($remarks) > 255) {
        $message = "Remarks must be 255 characters or fewer.";
    } else {
        $stmt = $conn->prepare("UPDATE tbdocument_requests
                                SET status = 'Rejected', remarks = ?, processed_by = ?, processed_at = NOW()
                                WHERE id = ? AND status = 'Pending'");
        $stmt->bind_param('sii', $remarks, $admin_id, $rid);
        $stmt->execute();
        $message = $stmt->affected_rows > 0 ? "Request rejected." : "Only a pending request can be rejected.";
    }
}

// Mark an APPROVED request as Released (remarks optional; keeps the existing remark if left blank)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_request'])) {
    $rid     = (int)($_POST['request_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if (strlen($remarks) > 255) {
        $message = "Remarks must be 255 characters or fewer.";
    } else {
        $stmt = $conn->prepare("UPDATE tbdocument_requests
                                SET status = 'Released',
                                    remarks = COALESCE(NULLIF(?, ''), remarks),
                                    released_at = NOW()
                                WHERE id = ? AND status = 'Approved'");
        $stmt->bind_param('si', $remarks, $rid);
        $stmt->execute();
        $message = $stmt->affected_rows > 0 ? "Request marked as Released." : "Only an approved request can be released.";
    }
}

// Delete a request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
    $rid = (int)($_POST['request_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM tbdocument_requests WHERE id = ?");
    $stmt->bind_param('i', $rid);
    $stmt->execute();
    $message = $stmt->affected_rows > 0 ? "Request deleted." : "Request not found.";
}

// Load a record into the edit form
$edit_row = null;
if (isset($_GET['edit_attendance'])) {
    $eid = (int)$_GET['edit_attendance'];
    $stmt = $conn->prepare("
        SELECT a.id, u.Username, s.subject_code, s.subject_name, a.attendance_date, a.status
        FROM tbattendance a
        JOIN tbusers u ON u.id = a.user_id
        JOIN tbsubjects s ON s.id = a.subject_id
        WHERE a.id = ?
    ");
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $edit_row = $stmt->get_result()->fetch_assoc();
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
    <a href="admin dashboard.php?section=attendance">Manage Attendance</a><br>
    <a href="admin dashboard.php?section=requests">Manage Document Requests</a><br>
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

<?php elseif ($section === 'attendance'): ?>
    <h2>Manage Attendance</h2>

    <?php if ($edit_row): ?>
    <!-- ===== EDIT MODE ===== -->
    <h3>Edit Attendance Record</h3>
    <form method="post" action="admin dashboard.php?section=attendance">
        <input type="hidden" name="att_id" value="<?php echo $edit_row['id']; ?>">
        <p>
            <strong>Student:</strong> <?php echo htmlspecialchars($edit_row['Username']); ?><br>
            <strong>Subject:</strong> <?php echo htmlspecialchars($edit_row['subject_code'] . ' - ' . $edit_row['subject_name']); ?>
        </p>
        <label>Date:</label>
        <input type="date" name="attendance_date" value="<?php echo htmlspecialchars($edit_row['attendance_date']); ?>" required><br>
        <label>Status:</label>
        <select name="status" required>
            <?php foreach ($att_statuses as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo $edit_row['status'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
            <?php endforeach; ?>
        </select><br>
        <button type="submit" name="update_attendance">Update</button>
        <a href="admin dashboard.php?section=attendance">Cancel</a>
    </form>

    <?php else: ?>
    <!-- ===== RECORD NEW ===== -->
    <?php $sel_subject = (int)($_POST['subject_id'] ?? $_GET['subject_id'] ?? 0); ?>

    <h3>Record Attendance</h3>
    <form method="get" action="admin dashboard.php">
        <input type="hidden" name="section" value="attendance">
        <label>Subject:</label>
        <select name="subject_id" onchange="this.form.submit()" required>
            <option value="">-- Select Subject --</option>
            <?php
            $subs = $conn->query("SELECT id, subject_code, subject_name FROM tbsubjects ORDER BY subject_code");
            while ($sub = $subs->fetch_assoc()):
            ?>
                <option value="<?php echo $sub['id']; ?>" <?php echo $sel_subject === (int)$sub['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($sub['subject_code'] . ' - ' . $sub['subject_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>
        <noscript><button type="submit">Load Students</button></noscript>
    </form>

    <?php if ($sel_subject > 0): ?>
        <?php
        $enrolled = $conn->prepare("
            SELECT u.id, u.Username
            FROM tbstudent_subjects ss
            JOIN tbusers u ON u.id = ss.user_id
            WHERE ss.subject_id = ? AND LOWER(u.Role) = 'student'
            ORDER BY u.Username
        ");
        $enrolled->bind_param('i', $sel_subject);
        $enrolled->execute();
        $enrolled_students = $enrolled->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>

        <?php if (count($enrolled_students) === 0): ?>
            <p>No students are enrolled in this subject yet.</p>
        <?php else: ?>
        <form method="post" action="admin dashboard.php?section=attendance&subject_id=<?php echo $sel_subject; ?>">
            <input type="hidden" name="subject_id" value="<?php echo $sel_subject; ?>">
            <label>Student:</label>
            <select name="user_id" required>
                <option value="">-- Select Enrolled Student --</option>
                <?php foreach ($enrolled_students as $es): ?>
                    <option value="<?php echo $es['id']; ?>"><?php echo htmlspecialchars($es['Username']); ?></option>
                <?php endforeach; ?>
            </select><br>
            <label>Date:</label>
            <input type="date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required><br>
            <label>Status:</label>
            <select name="status" required>
                <?php foreach ($att_statuses as $st): ?>
                    <option value="<?php echo $st; ?>"><?php echo $st; ?></option>
                <?php endforeach; ?>
            </select><br>
            <button type="submit" name="record_attendance">Record Attendance</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>

    <hr>

    <!-- ===== VIEW / FILTER ===== -->
    <h3>Attendance Records</h3>
    <?php
    $f_subject = (int)($_GET['f_subject'] ?? 0);
    $f_student = (int)($_GET['f_student'] ?? 0);
    ?>
    <form method="get" action="admin dashboard.php">
        <input type="hidden" name="section" value="attendance">
        <label>Subject:</label>
        <select name="f_subject">
            <option value="0">All subjects</option>
            <?php
            $subs = $conn->query("SELECT id, subject_code FROM tbsubjects ORDER BY subject_code");
            while ($sub = $subs->fetch_assoc()):
            ?>
                <option value="<?php echo $sub['id']; ?>" <?php echo $f_subject === (int)$sub['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($sub['subject_code']); ?>
                </option>
            <?php endwhile; ?>
        </select>
        <label>Student:</label>
        <select name="f_student">
            <option value="0">All students</option>
            <?php
            $studs = $conn->query("SELECT id, Username FROM tbusers WHERE LOWER(Role) = 'student' ORDER BY Username");
            while ($st = $studs->fetch_assoc()):
            ?>
                <option value="<?php echo $st['id']; ?>" <?php echo $f_student === (int)$st['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($st['Username']); ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit">Filter</button>
        <a href="admin dashboard.php?section=attendance">Reset</a>
    </form>

    <?php
    $where = []; $types = ''; $params = [];
    if ($f_subject > 0) { $where[] = 'a.subject_id = ?'; $types .= 'i'; $params[] = $f_subject; }
    if ($f_student > 0) { $where[] = 'a.user_id = ?';    $types .= 'i'; $params[] = $f_student; }

    $sql = "SELECT a.id, u.Username, s.subject_code, s.subject_name, a.attendance_date, a.status
            FROM tbattendance a
            JOIN tbusers u ON u.id = a.user_id
            JOIN tbsubjects s ON s.id = a.subject_id"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY a.attendance_date DESC, u.Username, s.subject_code";
    $stmt = $conn->prepare($sql);
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $records = $stmt->get_result();
    ?>
    <table border="1" cellpadding="6">
        <tr>
            <th>Date</th><th>Student</th><th>Subject</th><th>Status</th><th>Action</th>
        </tr>
        <?php if ($records->num_rows === 0): ?>
            <tr><td colspan="5">No attendance records found.</td></tr>
        <?php else: while ($r = $records->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($r['attendance_date']); ?></td>
            <td><?php echo htmlspecialchars($r['Username']); ?></td>
            <td><?php echo htmlspecialchars($r['subject_code'] . ' - ' . $r['subject_name']); ?></td>
            <td><?php echo htmlspecialchars($r['status']); ?></td>
            <td>
                <a href="admin dashboard.php?section=attendance&edit_attendance=<?php echo $r['id']; ?>">Edit</a>
                <form method="post" action="admin dashboard.php?section=attendance" style="display:inline;"
                      onsubmit="return confirm('Delete this attendance record?');">
                    <input type="hidden" name="att_id" value="<?php echo $r['id']; ?>">
                    <button type="submit" name="delete_attendance">Delete</button>
                </form>
            </td>
        </tr>
        <?php endwhile; endif; ?>
    </table>

    <?php if ($f_student > 0): ?>
        <h3>Attendance Summary of Selected Student</h3>
        <?php
        $sum_rows = attendance_summary($conn, $f_student);
        $sum_tot  = attendance_totals($sum_rows);
        ?>
        <table border="1" cellpadding="6">
            <tr><th>Subject Code</th><th>Present</th><th>Absent</th><th>Late</th></tr>
            <?php if (count($sum_rows) === 0): ?>
                <tr><td colspan="4">This student is not enrolled in any subject.</td></tr>
            <?php else: foreach ($sum_rows as $sr): ?>
                <tr>
                    <td><?php echo htmlspecialchars($sr['subject_code']); ?></td>
                    <td><?php echo (int)$sr['present']; ?></td>
                    <td><?php echo (int)$sr['absent']; ?></td>
                    <td><?php echo (int)$sr['late']; ?></td>
                </tr>
            <?php endforeach; ?>
                <tr>
                    <th>Total</th>
                    <th><?php echo $sum_tot['present']; ?></th>
                    <th><?php echo $sum_tot['absent']; ?></th>
                    <th><?php echo $sum_tot['late']; ?></th>
                </tr>
            <?php endif; ?>
        </table>
    <?php endif; ?>

<?php elseif ($section === 'requests'): ?>
    <h2>Manage Document Requests</h2>

    <?php
    $view_id = (int)($_GET['view_request'] ?? 0);
    if ($view_id > 0):
        $stmt = $conn->prepare("
            SELECT r.*, u.Username AS student, p.Username AS processor
            FROM tbdocument_requests r
            JOIN tbusers u ON u.id = r.user_id
            LEFT JOIN tbusers p ON p.id = r.processed_by
            WHERE r.id = ?
        ");
        $stmt->bind_param('i', $view_id);
        $stmt->execute();
        $d = $stmt->get_result()->fetch_assoc();
    ?>
        <h3>Request Details</h3>
        <?php if (!$d): ?>
            <p>Request not found.</p>
        <?php else: ?>
            <table border="1" cellpadding="6">
                <tr><th align="left">Request ID</th><td><?php echo $d['id']; ?></td></tr>
                <tr><th align="left">Student</th><td><?php echo htmlspecialchars($d['student']); ?></td></tr>
                <tr><th align="left">Document Type</th><td><?php echo htmlspecialchars($d['document_type']); ?></td></tr>
                <tr><th align="left">Purpose</th><td><?php echo htmlspecialchars($d['purpose']); ?></td></tr>
                <tr><th align="left">Status</th><td><?php echo htmlspecialchars($d['status']); ?></td></tr>
                <tr><th align="left">Admin Remarks</th><td><?php echo $d['remarks'] !== null && $d['remarks'] !== '' ? htmlspecialchars($d['remarks']) : '—'; ?></td></tr>
                <tr><th align="left">Requested On</th><td><?php echo htmlspecialchars($d['requested_at']); ?></td></tr>
                <tr><th align="left">Processed By</th><td><?php echo $d['processor'] !== null ? htmlspecialchars($d['processor']) : '—'; ?></td></tr>
                <tr><th align="left">Processed On</th><td><?php echo $d['processed_at'] !== null ? htmlspecialchars($d['processed_at']) : '—'; ?></td></tr>
                <tr><th align="left">Released On</th><td><?php echo $d['released_at'] !== null ? htmlspecialchars($d['released_at']) : '—'; ?></td></tr>
            </table>
        <?php endif; ?>
        <p><a href="admin dashboard.php?section=requests">Back to all requests</a></p>
        <hr>
    <?php endif; ?>

    <?php
    $q        = trim($_GET['q'] ?? '');
    $f_status = $_GET['f_status'] ?? '';
    if (!in_array($f_status, DOC_STATUSES, true)) { $f_status = ''; }
    ?>
    <form method="get" action="admin dashboard.php">
        <input type="hidden" name="section" value="requests">
        <label>Search (student or document type):</label>
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>">
        <label>Status:</label>
        <select name="f_status">
            <option value="">All statuses</option>
            <?php foreach (DOC_STATUSES as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo $f_status === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filter</button>
        <a href="admin dashboard.php?section=requests">Reset</a>
    </form>

    <?php
    $where = []; $types = ''; $params = [];
    if ($q !== '') {
        $where[] = '(u.Username LIKE ? OR r.document_type LIKE ?)';
        $types .= 'ss';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if ($f_status !== '') { $where[] = 'r.status = ?'; $types .= 's'; $params[] = $f_status; }

    $sql = "SELECT r.id, u.Username, r.document_type, r.purpose, r.status, r.remarks, r.requested_at
            FROM tbdocument_requests r
            JOIN tbusers u ON u.id = r.user_id"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY r.requested_at DESC, r.id DESC";
    $stmt = $conn->prepare($sql);
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $reqs = $stmt->get_result();
    ?>
    <table border="1" cellpadding="6">
        <tr>
            <th>ID</th><th>Student</th><th>Document</th><th>Purpose</th><th>Status</th><th>Remarks</th><th>Requested</th><th>Action</th>
        </tr>
        <?php if ($reqs->num_rows === 0): ?>
            <tr><td colspan="8">No document requests found.</td></tr>
        <?php else: while ($r = $reqs->fetch_assoc()): ?>
        <tr>
            <td><?php echo $r['id']; ?></td>
            <td><?php echo htmlspecialchars($r['Username']); ?></td>
            <td><?php echo htmlspecialchars($r['document_type']); ?></td>
            <td><?php echo htmlspecialchars($r['purpose']); ?></td>
            <td><?php echo htmlspecialchars($r['status']); ?></td>
            <td><?php echo $r['remarks'] !== null && $r['remarks'] !== '' ? htmlspecialchars($r['remarks']) : '—'; ?></td>
            <td><?php echo htmlspecialchars($r['requested_at']); ?></td>
            <td>
                <a href="admin dashboard.php?section=requests&view_request=<?php echo $r['id']; ?>">Details</a>

                <?php if ($r['status'] === 'Pending'): ?>
                    <form method="post" action="admin dashboard.php?section=requests" style="display:inline;">
                        <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                        <input type="text" name="remarks" placeholder="Remarks (optional)" maxlength="255">
                        <button type="submit" name="approve_request">Approve</button>
                    </form>
                    <form method="post" action="admin dashboard.php?section=requests" style="display:inline;">
                        <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                        <input type="text" name="remarks" placeholder="Remarks (required)" maxlength="255" required>
                        <button type="submit" name="reject_request">Reject</button>
                    </form>
                <?php elseif ($r['status'] === 'Approved'): ?>
                    <form method="post" action="admin dashboard.php?section=requests" style="display:inline;">
                        <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                        <input type="text" name="remarks" placeholder="Remarks (optional)" maxlength="255">
                        <button type="submit" name="release_request">Mark as Released</button>
                    </form>
                <?php endif; ?>

                <form method="post" action="admin dashboard.php?section=requests" style="display:inline;"
                      onsubmit="return confirm('Delete this request?');">
                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                    <button type="submit" name="delete_request">Delete</button>
                </form>
            </td>
        </tr>
        <?php endwhile; endif; ?>
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