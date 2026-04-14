<?php

/**
 * Ensure InPersonSession table has the columns we need for selectable time slots.
 * Idempotent: safe to call on every request.
 */
function sona_ensure_inperson_session_columns(mysqli $conn): void
{
    $res = $conn->query("SHOW TABLES LIKE 'InPersonSession'");
    if (!$res || $res->num_rows === 0) {
        return;
    }

    $colsRes = $conn->query("SHOW COLUMNS FROM InPersonSession");
    if (!$colsRes) {
        return;
    }
    $cols = [];
    while ($row = $colsRes->fetch_assoc()) {
        $cols[$row['Field']] = true;
    }

    if (empty($cols['SessionTime'])) {
        $conn->query("ALTER TABLE InPersonSession ADD COLUMN SessionTime TIME NULL AFTER SessionDate");
    }
    if (empty($cols['Duration'])) {
        $conn->query("ALTER TABLE InPersonSession ADD COLUMN Duration INT NULL AFTER SessionTime");
    }
    if (empty($cols['AttendanceStatus'])) {
        $conn->query("ALTER TABLE InPersonSession ADD COLUMN AttendanceStatus VARCHAR(20) NULL");
    }

    // Helpful indexes for lookup by study/date.
    $idxRes = $conn->query("SHOW INDEX FROM InPersonSession");
    if ($idxRes) {
        $idx = [];
        while ($r = $idxRes->fetch_assoc()) {
            $idx[$r['Key_name']] = true;
        }
        if (empty($idx['idx_inperson_study_date'])) {
            $conn->query("CREATE INDEX idx_inperson_study_date ON InPersonSession (StudyID, SessionDate, SessionTime)");
        }
        if (empty($idx['idx_inperson_student'])) {
            $conn->query("CREATE INDEX idx_inperson_student ON InPersonSession (StudentID, StudyID)");
        }
    }
}

/**
 * InPersonSession.StudentID references Student.StudentID (primary key), not users.id.
 */
function sona_student_primary_key_for_user(mysqli $conn, int $userId): ?int
{
    if ($userId <= 0) {
        return null;
    }
    $st = $conn->prepare("SELECT StudentID FROM Student WHERE UserID = ? LIMIT 1");
    if (!$st) {
        return null;
    }
    $st->bind_param("i", $userId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }
    return (int)$row['StudentID'];
}

/**
 * FullCalendar-friendly ISO start string (date, or local datetime if time is set).
 */
function sona_inperson_slot_start_iso(?string $sessionDate, ?string $sessionTime): ?string
{
    if ($sessionDate === null || trim($sessionDate) === '') {
        return null;
    }
    $d = trim($sessionDate);
    if ($sessionTime !== null && trim($sessionTime) !== '') {
        $t = trim($sessionTime);
        if (strlen($t) >= 5) {
            $t = substr($t, 0, 5);
        }
        return $d . 'T' . $t . ':00';
    }
    return $d;
}

