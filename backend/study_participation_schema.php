<?php

/**
 * Ensures StudyParticipant has attendance / completion columns (idempotent).
 */
function sona_ensure_participation_status_columns(mysqli $conn): void
{
    $res = $conn->query("SHOW COLUMNS FROM StudyParticipant");
    if (!$res) {
        return;
    }
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $cols[$row['Field']] = true;
    }
    if (empty($cols['ParticipationStatus'])) {
        $conn->query("ALTER TABLE StudyParticipant ADD COLUMN ParticipationStatus VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }
    if (empty($cols['CompletedAt'])) {
        $conn->query("ALTER TABLE StudyParticipant ADD COLUMN CompletedAt DATETIME NULL");
    }
}
