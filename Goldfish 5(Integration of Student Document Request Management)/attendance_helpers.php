<?php
/* Shared helpers for the Attendance module (used by both admin and student pages). */

// Strict YYYY-MM-DD check (rejects things like 2026-02-31).
function valid_date($d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

// Per-subject attendance totals for ONE student, calculated live from tbattendance.
// Every enrolled subject is returned, even those with no attendance yet (zeros).
function attendance_summary($conn, $uid) {
    $stmt = $conn->prepare("
        SELECT s.subject_code, s.subject_name,
               COALESCE(SUM(a.status = 'Present'), 0) AS present,
               COALESCE(SUM(a.status = 'Absent'),  0) AS absent,
               COALESCE(SUM(a.status = 'Late'),    0) AS late
        FROM tbstudent_subjects ss
        JOIN tbsubjects s ON s.id = ss.subject_id
        LEFT JOIN tbattendance a
               ON a.user_id = ss.user_id AND a.subject_id = ss.subject_id
        WHERE ss.user_id = ?
        GROUP BY s.id, s.subject_code, s.subject_name
        ORDER BY s.subject_code
    ");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Grand totals across all subjects (same source as the table above, so they always match).
function attendance_totals($rows) {
    $t = ['present' => 0, 'absent' => 0, 'late' => 0];
    foreach ($rows as $r) {
        $t['present'] += (int)$r['present'];
        $t['absent']  += (int)$r['absent'];
        $t['late']    += (int)$r['late'];
    }
    return $t;
}
