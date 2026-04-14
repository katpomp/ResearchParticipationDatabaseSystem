<?php

/**
 * Schema + helpers for master-initiated faculty/researcher promotions (email OTP).
 */

function sona_ensure_role_promotion_schema(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS RolePromotionToken (
            TokenID INT AUTO_INCREMENT PRIMARY KEY,
            UserID INT NOT NULL,
            NewRole VARCHAR(20) NOT NULL,
            CodeHash VARCHAR(255) NOT NULL,
            ExpiresAt DATETIME NOT NULL,
            CreatedByUserID INT NOT NULL,
            CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UsedAt DATETIME NULL,
            INDEX idx_user_active (UserID, UsedAt),
            INDEX idx_expires (ExpiresAt),
            FOREIGN KEY (UserID) REFERENCES users(id),
            FOREIGN KEY (CreatedByUserID) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/**
 * Master accounts use the Faculty profile table for shared edit_profile UI (name/email).
 */
/**
 * Rows that reference Student.StudentID (PK) block DELETE Student unless removed first.
 */
function sona_detach_student_dependent_rows(mysqli $conn, int $studentPk): void
{
    if ($studentPk <= 0) {
        return;
    }
    $detach = [
        'DELETE FROM StudentPhoneNumber WHERE StudentID = ?',
        'DELETE FROM EmailNotification WHERE StudentID = ?',
        'DELETE FROM InPersonSession WHERE StudentID = ?',
    ];
    foreach ($detach as $sql) {
        $d = $conn->prepare($sql);
        if (!$d) {
            throw new Exception($conn->error ?: 'Failed to prepare dependent-row cleanup.');
        }
        $d->bind_param("i", $studentPk);
        if (!$d->execute()) {
            throw new Exception($d->error);
        }
    }
}

function sona_ensure_master_faculty_profile(mysqli $conn, int $userId, string $email): void
{
    $chk = $conn->prepare("SELECT FacultyID FROM Faculty WHERE UserID = ? LIMIT 1");
    $chk->bind_param("i", $userId);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        return;
    }
    $firstName = 'System';
    $lastName = 'Administrator';
    $ins = $conn->prepare("INSERT INTO Faculty (FirstName, LastName, Email, UserID) VALUES (?, ?, ?, ?)");
    $ins->bind_param("sssi", $firstName, $lastName, $email, $userId);
    $ins->execute();
}

/**
 * Promote a user who is currently a student (mirrors faculty_directory student→researcher, adds student→faculty).
 *
 * @throws Exception on invalid state or DB errors
 */
function sona_promote_student_to_role(mysqli $conn, int $userId, string $newRole): void
{
    if ($newRole !== 'faculty' && $newRole !== 'researcher') {
        throw new Exception('Invalid target role.');
    }

    $roleStmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $roleStmt->bind_param("i", $userId);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    if (!$roleRow || $roleRow['role'] !== 'student') {
        throw new Exception('Only student accounts can accept this invitation.');
    }

    $sourceStmt = $conn->prepare("SELECT StudentID, FirstName, LastName, Email FROM Student WHERE UserID = ? LIMIT 1");
    $sourceStmt->bind_param("i", $userId);
    $sourceStmt->execute();
    $person = $sourceStmt->get_result()->fetch_assoc();
    if (!$person) {
        throw new Exception('Student profile not found.');
    }

    $studentPk = (int)$person['StudentID'];
    sona_detach_student_dependent_rows($conn, $studentPk);

    if ($newRole === 'researcher') {
        $insertStmt = $conn->prepare("INSERT INTO Researcher (FirstName, LastName, Email, UserID) VALUES (?, ?, ?, ?)");
        $insertStmt->bind_param("sssi", $person['FirstName'], $person['LastName'], $person['Email'], $userId);
        if (!$insertStmt->execute()) {
            throw new Exception($insertStmt->error);
        }
        $deleteStmt = $conn->prepare("DELETE FROM Student WHERE UserID = ?");
        $deleteStmt->bind_param("i", $userId);
        if (!$deleteStmt->execute()) {
            throw new Exception($deleteStmt->error);
        }
    } else {
        $insertStmt = $conn->prepare("INSERT INTO Faculty (FirstName, LastName, Email, UserID) VALUES (?, ?, ?, ?)");
        $insertStmt->bind_param("sssi", $person['FirstName'], $person['LastName'], $person['Email'], $userId);
        if (!$insertStmt->execute()) {
            throw new Exception($insertStmt->error);
        }
        $deleteStmt = $conn->prepare("DELETE FROM Student WHERE UserID = ?");
        $deleteStmt->bind_param("i", $userId);
        if (!$deleteStmt->execute()) {
            throw new Exception($deleteStmt->error);
        }
    }

    $upd = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $upd->bind_param("si", $newRole, $userId);
    if (!$upd->execute()) {
        throw new Exception($upd->error);
    }
}

function sona_invalidate_pending_promotion_tokens(mysqli $conn, int $userId): void
{
    $st = $conn->prepare("UPDATE RolePromotionToken SET UsedAt = NOW() WHERE UserID = ? AND UsedAt IS NULL");
    $st->bind_param("i", $userId);
    $st->execute();
}
