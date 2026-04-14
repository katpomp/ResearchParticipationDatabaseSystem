<?php

/**
 * Adds online vs in-person session fields on Study (idempotent ALTERs).
 */
function sona_ensure_study_session_columns(mysqli $conn): void
{
    $res = $conn->query("SHOW COLUMNS FROM Study");
    if (!$res) {
        return;
    }
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $cols[$row['Field']] = true;
    }
    if (empty($cols['SessionMode'])) {
        $conn->query("ALTER TABLE Study ADD COLUMN SessionMode VARCHAR(20) NOT NULL DEFAULT 'in_person'");
    }
    if (empty($cols['OnlineMeetingURL'])) {
        $conn->query("ALTER TABLE Study ADD COLUMN OnlineMeetingURL VARCHAR(1024) NULL");
    }
    if (empty($cols['BuildingName'])) {
        $conn->query("ALTER TABLE Study ADD COLUMN BuildingName VARCHAR(100) NULL");
    }
    if (empty($cols['RoomNumber'])) {
        $conn->query("ALTER TABLE Study ADD COLUMN RoomNumber VARCHAR(20) NULL");
    }
}

/**
 * Normalize and validate http(s) URL for storage. Returns null if invalid/empty.
 */
function sona_normalize_online_meeting_url(string $raw): ?string
{
    $url = trim($raw);
    if ($url === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $parts = parse_url($url);
    if (!isset($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
        return null;
    }
    return $url;
}

/**
 * Safe href for output (only http/https).
 */
function sona_safe_http_url_for_href(?string $stored): ?string
{
    if ($stored === null || $stored === '') {
        return null;
    }
    return sona_normalize_online_meeting_url($stored);
}
