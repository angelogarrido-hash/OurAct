<?php
/* Shared helpers for the Document Request module (used by both admin and student pages). */

// The only document types a request may use (checked on the server, not just in the dropdown).
const DOC_TYPES = [
    'Certificate of Enrollment',
    'Certificate of Grades',
    'Transcript of Records',
    'Good Moral Certificate',
    'Certificate of Graduation',
];

const DOC_STATUSES = ['Pending', 'Approved', 'Rejected', 'Released'];

// Number of requests per status for ONE student, calculated live from tbdocument_requests.
// Every status is returned, even when its count is zero.
function request_counts($conn, $uid) {
    $counts = array_fill_keys(DOC_STATUSES, 0);
    $stmt = $conn->prepare("SELECT status, COUNT(*) AS total FROM tbdocument_requests WHERE user_id = ? GROUP BY status");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $counts[$r['status']] = (int)$r['total'];
    }
    return $counts;
}
