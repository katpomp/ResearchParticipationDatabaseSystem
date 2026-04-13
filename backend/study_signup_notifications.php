<?php

require_once __DIR__ . '/inc_smtp.php';

function sona_mail_standard_banner(): string
{
    return "CNU Research Participation System\n\n";
}

function sona_mail_standard_footer(): string
{
    return "\n\nThis is an automated message. Please do not reply to this email.\n";
}

function sona_format_mail_date(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('l, F j, Y', $ts) : $date;
}

/**
 * After a successful StudyParticipant insert: email student (.edu) and researcher.
 *
 * @return array{student_sent:bool,researcher_sent:bool,student_skipped_non_edu:bool,student_send_failed:bool}
 */
function sona_notify_study_signup(mysqli $conn, int $studyID, int $userId): array
{
    $result = [
        'student_sent' => false,
        'researcher_sent' => false,
        'student_skipped_non_edu' => false,
        'student_send_failed' => false,
    ];

    $studyStmt = $conn->prepare("
        SELECT s.StudyTitle, s.StartDate, s.EndDate, s.ResearcherID,
               r.FirstName AS ResearcherFirstName, r.LastName AS ResearcherLastName, r.Email AS ResearcherEmail
        FROM Study s
        LEFT JOIN Researcher r ON r.ResearcherID = s.ResearcherID
        WHERE s.StudyID = ?
        LIMIT 1
    ");
    if (!$studyStmt) {
        return $result;
    }
    $studyStmt->bind_param("i", $studyID);
    $studyStmt->execute();
    $studyRow = $studyStmt->get_result()->fetch_assoc();
    if (!$studyRow) {
        return $result;
    }

    $studentRow = null;
    $st = $conn->prepare("SELECT StudentID, FirstName, LastName, Email FROM Student WHERE UserID = ? LIMIT 1");
    if ($st) {
        $st->bind_param("i", $userId);
        $st->execute();
        $studentRow = $st->get_result()->fetch_assoc();
    }

    $accountEmail = '';
    $u = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    if ($u) {
        $u->bind_param("i", $userId);
        $u->execute();
        if ($ur = $u->get_result()->fetch_assoc()) {
            $accountEmail = trim((string)($ur['email'] ?? ''));
        }
    }

    $studentEmail = trim((string)($studentRow['Email'] ?? ''));
    if ($studentEmail === '') {
        $studentEmail = $accountEmail;
    }

    $studentFirst = trim((string)($studentRow['FirstName'] ?? ''));
    if ($studentFirst === '') {
        $studentFirst = 'Student';
    }
    $studentLast = trim((string)($studentRow['LastName'] ?? ''));
    $studentFull = trim($studentFirst . ' ' . $studentLast);

    $studyTitle = (string)$studyRow['StudyTitle'];
    $startFmt = sona_format_mail_date($studyRow['StartDate'] ?? null);
    $endFmt = sona_format_mail_date($studyRow['EndDate'] ?? null);

    $researcherEmail = trim((string)($studyRow['ResearcherEmail'] ?? ''));
    $researcherFirst = trim((string)($studyRow['ResearcherFirstName'] ?? ''));
    if ($researcherFirst === '') {
        $researcherFirst = 'Researcher';
    }

    $studentEdu = $studentEmail !== '' && preg_match('/\.edu$/i', $studentEmail);
    if (!$studentEdu) {
        $result['student_skipped_non_edu'] = $studentEmail !== '';
    }

    if ($studentEdu) {
        $body = sona_mail_standard_banner();
        $body .= "STUDY SIGN-UP CONFIRMATION\n\n";
        $body .= "Hello {$studentFirst},\n\n";
        $body .= "This confirms that you have successfully signed up for the following study:\n\n";
        $body .= "  Study title:  {$studyTitle}\n";
        $body .= "  Start date:   {$startFmt}\n";
        $body .= "  End date:     {$endFmt}\n\n";
        $body .= "Please follow any instructions the researcher has provided and check the Research Participation System for updates.\n\n";
        $body .= "If you did not sign up for this study, sign in to the system to cancel or contact support.\n";
        $body .= sona_mail_standard_footer();

        $subject = 'CNU Research Participation — Study sign-up confirmed';
        $result['student_sent'] = sona_send_plain_email($studentEmail, $subject, $body);
        $result['student_send_failed'] = !$result['student_sent'];
    }

    if ($researcherEmail !== '' && filter_var($researcherEmail, FILTER_VALIDATE_EMAIL)) {
        $body = sona_mail_standard_banner();
        $body .= "NEW PARTICIPANT NOTIFICATION\n\n";
        $body .= "Hello {$researcherFirst},\n\n";
        $body .= "A student has signed up for your study through the Research Participation System.\n\n";
        $body .= "  Study title:         {$studyTitle}\n";
        $body .= "  Start date:          {$startFmt}\n";
        $body .= "  End date:            {$endFmt}\n";
        $body .= "  Participant name:    {$studentFull}\n";
        $body .= "  Participant email:   {$studentEmail}\n\n";
        $body .= "You can review participation in the Research Participation System.\n";
        $body .= sona_mail_standard_footer();

        $subject = 'CNU Research Participation — New participant: ' . $studyTitle;
        $result['researcher_sent'] = sona_send_plain_email($researcherEmail, $subject, $body);
    }

    $researcherID = isset($studyRow['ResearcherID']) ? (int)$studyRow['ResearcherID'] : 0;
    $studentTableID = isset($studentRow['StudentID']) ? (int)$studentRow['StudentID'] : 0;
    if ($researcherID > 0 && $studentTableID > 0) {
        $logSubject = 'Study sign-up: ' . $studyTitle;
        $logBody = "Participant: {$studentFull} <{$studentEmail}>\n"
            . 'Student confirmation sent: ' . ($result['student_sent'] ? 'yes' : 'no') . "\n"
            . 'Researcher notification sent: ' . ($result['researcher_sent'] ? 'yes' : 'no');
        $log = $conn->prepare("INSERT INTO EmailNotification (Subject, MessageBody, ResearcherID, StudentID) VALUES (?, ?, ?, ?)");
        if ($log) {
            $log->bind_param("ssii", $logSubject, $logBody, $researcherID, $studentTableID);
            $log->execute();
        }
    }

    return $result;
}
