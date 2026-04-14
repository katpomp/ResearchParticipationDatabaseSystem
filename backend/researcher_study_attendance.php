<?php
session_start();
include "db_connect.php";
require_once __DIR__ . '/study_participation_schema.php';
require_once __DIR__ . '/study_completion_notifications.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'researcher') {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['user_id'];
$message = '';
$error = '';

sona_ensure_participation_status_columns($conn);

$researcherProfileID = null;
$researcherStmt = $conn->prepare("SELECT ResearcherID, FirstName FROM Researcher WHERE UserID=? LIMIT 1");
$researcherStmt->bind_param("i", $userID);
$researcherStmt->execute();
$researcherRes = $researcherStmt->get_result();
if ($researcherRow = $researcherRes->fetch_assoc()) {
    $researcherProfileID = (int)$researcherRow['ResearcherID'];
} else {
    $error = "Researcher profile not found. Please contact support.";
}

$studyID = isset($_GET['studyID']) ? (int)$_GET['studyID'] : 0;
$study = null;

if ($researcherProfileID !== null && $studyID > 0) {
    $st = $conn->prepare("SELECT StudyID, StudyTitle, StartDate, EndDate, Status FROM Study WHERE StudyID=? AND ResearcherID=? LIMIT 1");
    $st->bind_param("ii", $studyID, $researcherProfileID);
    $st->execute();
    $study = $st->get_result()->fetch_assoc();
}

if ($researcherProfileID !== null && $study === null && $studyID > 0) {
    $error = "Study not found or you do not have access.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $researcherProfileID !== null && $study !== null) {
    $postStudyID = (int)($_POST['studyID'] ?? 0);
    $studentUserID = (int)($_POST['studentUserID'] ?? 0);
    $action = $_POST['attendance_action'] ?? '';

    if ($postStudyID !== (int)$study['StudyID']) {
        $error = "Invalid study.";
    } elseif ($studentUserID <= 0 || !in_array($action, ['complete', 'no_show'], true)) {
        $error = "Invalid request.";
    } else {
        $verify = $conn->prepare("SELECT 1 FROM StudyParticipant WHERE StudyID=? AND StudentID=? AND ParticipationStatus='pending' LIMIT 1");
        $verify->bind_param("ii", $postStudyID, $studentUserID);
        $verify->execute();
        if ($verify->get_result()->num_rows === 0) {
            $error = "That participant is not pending confirmation, or was not found.";
        } else {
            if ($action === 'no_show') {
                $upd = $conn->prepare("UPDATE StudyParticipant SET ParticipationStatus='no_show', CompletedAt=NOW() WHERE StudyID=? AND StudentID=? AND ParticipationStatus='pending'");
                $upd->bind_param("ii", $postStudyID, $studentUserID);
                if ($upd->execute() && $upd->affected_rows > 0) {
                    $message = "Participant marked as no-show.";
                } else {
                    $error = "Could not update attendance.";
                }
            } else {
                $credit = sona_study_completion_credit_amount();
                $studyTitleForMail = $study['StudyTitle'];
                $conn->begin_transaction();
                try {
                    $upd = $conn->prepare("UPDATE StudyParticipant SET ParticipationStatus='completed', CompletedAt=NOW() WHERE StudyID=? AND StudentID=? AND ParticipationStatus='pending'");
                    $upd->bind_param("ii", $postStudyID, $studentUserID);
                    if (!$upd->execute() || $upd->affected_rows === 0) {
                        throw new Exception("update participant");
                    }

                    $titleShort = substr($studyTitleForMail, 0, 100);
                    $insA = $conn->prepare("INSERT INTO attendance (user_id, event_date, event_name, credits_earned) VALUES (?, CURDATE(), ?, ?)");
                    $insA->bind_param("isd", $studentUserID, $titleShort, $credit);
                    if (!$insA->execute()) {
                        throw new Exception("attendance");
                    }

                    $credUp = $conn->prepare("INSERT INTO credits (user_id, total_credits) VALUES (?, ?) ON DUPLICATE KEY UPDATE total_credits = total_credits + ?");
                    $credUp->bind_param("idd", $studentUserID, $credit, $credit);
                    if (!$credUp->execute()) {
                        throw new Exception("credits");
                    }

                    $conn->commit();

                    $stu = $conn->prepare("SELECT FirstName, Email FROM Student WHERE UserID=? LIMIT 1");
                    $stu->bind_param("i", $studentUserID);
                    $stu->execute();
                    $stuRow = $stu->get_result()->fetch_assoc();
                    $firstName = trim((string)($stuRow['FirstName'] ?? ''));
                    $stuEmail = trim((string)($stuRow['Email'] ?? ''));
                    if ($stuEmail === '') {
                        $u = $conn->prepare("SELECT email FROM users WHERE id=? LIMIT 1");
                        $u->bind_param("i", $studentUserID);
                        $u->execute();
                        if ($ur = $u->get_result()->fetch_assoc()) {
                            $stuEmail = trim((string)($ur['email'] ?? ''));
                        }
                    }

                    $mailOk = sona_send_study_completion_email($stuEmail, $firstName, $studyTitleForMail, $credit);
                    $message = "Participant marked as completed. Credits have been recorded.";
                    if (!$mailOk) {
                        $message .= " Completion email could not be sent (check the student's email on file).";
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Could not record completion. Please try again.";
                }
            }
        }
    }
}

$participants = [];
if ($study !== null) {
    $pq = $conn->prepare("
        SELECT sp.StudentID AS UserID, sp.ParticipationStatus, sp.CompletedAt,
               st.FirstName, st.LastName, st.Email
        FROM StudyParticipant sp
        JOIN Student st ON st.UserID = sp.StudentID
        WHERE sp.StudyID = ?
        ORDER BY st.LastName ASC, st.FirstName ASC
    ");
    $pq->bind_param("i", $study['StudyID']);
    $pq->execute();
    $pr = $pq->get_result();
    while ($row = $pr->fetch_assoc()) {
        $participants[] = $row;
    }
}

function sona_status_label(string $status): string
{
    switch ($status) {
        case 'completed':
            return 'Completed';
        case 'no_show':
            return 'No-show';
        default:
            return 'Pending';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Study attendance - Research Participation System</title>
<link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
:root { --cnu-blue:#003366; --cnu-silver:#E0E0E0; --text-dark:#333; }
body { margin:0; padding:0; font-family:'Inter',sans-serif; background:#f0f2f5; color:var(--text-dark); }
header { background:linear-gradient(90deg, #002b55 0%, var(--cnu-blue) 100%); padding:1rem 2rem; color:white; box-shadow:0 4px 14px rgba(0,0,0,0.2); font-family:'Crimson Pro', serif; }
.header-inner { display:flex; justify-content:center; }
.header-title { margin:0; font-size:2rem; font-weight:700; letter-spacing:0.3px; text-align:center; }
.top-tabs { display:flex; align-items:center; gap:10px; padding:8px 2rem; background:var(--cnu-silver); border-top:1px solid #cfd3d8; border-bottom:1px solid #cfd3d8; flex-wrap:wrap; }
.top-tab-link { display:inline-block; padding:8px 12px; border-radius:6px; text-decoration:none; color:var(--cnu-blue); font-weight:600; background:white; border:1px solid #c7ccd3; }
.top-tab-link:hover { background:#f4f6f8; }
.top-tab-link.active { background:var(--cnu-blue); color:white; border-color:var(--cnu-blue); }
.tab-spacer { margin-left:auto; }
.profile-dropdown { position:relative; display:inline-block; }
.profile-dropdown > a { display:inline-block; padding:8px 12px; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.95rem; color:var(--cnu-blue); background:white; border:1px solid #c7ccd3; }
.profile-dropdown > a:hover { background:#f4f6f8; }
.profile-dropdown-content { display:none; position:absolute; right:0; background:white; min-width:180px; box-shadow:0px 8px 16px rgba(0,0,0,0.2); z-index:1; border-radius:6px; }
.profile-dropdown-content a { color:var(--text-dark); padding:12px 16px; text-decoration:none; display:block; }
.profile-dropdown-content a:hover { background:#f1f1f1; }
.profile-dropdown:hover .profile-dropdown-content { display:block; }
.container { padding:20px 2rem; max-width:960px; margin:0 auto; }
.panel { background:white; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.05); padding:16px; }
.panel-title { display:block; margin-bottom:16px; padding:10px 12px; border-radius:8px; font-family:'Crimson Pro', serif; font-size:1.4em; color:white; background:var(--cnu-blue); text-align:center; }
.message { padding:10px; border-radius:6px; margin-bottom:12px; }
.ok { background:#dff0d8; color:#3c763d; }
.err { background:#f8d7da; color:#842029; }
.study-dates-bar {
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:10px 16px;
    padding:10px 14px;
    margin-bottom:14px;
    background:#f4f7fb;
    border:1px solid #e2e8f0;
    border-radius:8px;
    font-size:0.95rem;
    color:#3d4b58;
}
.study-dates-bar .dates-label { font-weight:700; color:var(--cnu-blue); text-transform:uppercase; font-size:0.72rem; letter-spacing:0.06em; }
.study-dates-bar .dates-range { font-weight:600; }
.attendance-legend {
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap:10px;
    margin-bottom:18px;
}
.legend-card {
    display:flex;
    align-items:stretch;
    border-radius:8px;
    overflow:hidden;
    border:1px solid #e2e8f0;
    background:#fff;
}
.legend-card::before {
    content:'';
    width:4px;
    flex-shrink:0;
}
.legend-card-complete::before { background:var(--cnu-blue); }
.legend-card-noshow::before { background:#6c757d; }
.legend-card-inner { padding:10px 12px; }
.legend-card-inner .legend-title { display:block; font-weight:700; font-size:0.9rem; color:#1f2933; }
.legend-card-inner .legend-sub { display:block; font-size:0.8rem; color:#6b7785; margin-top:2px; }
.participants-heading { font-size:0.82rem; font-weight:600; color:#5a6673; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:10px; }
.participant-row { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; padding:12px; border:1px solid #d9dfe7; border-radius:8px; margin-bottom:10px; background:#fafbfd; }
.participant-info { flex:1; min-width:200px; }
.participant-name { font-weight:600; }
.participant-email { font-size:0.92rem; color:#4e5c69; }
.status-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:0.8rem; font-weight:700; margin-top:6px; }
.st-pending { background:#fff3cd; color:#856404; }
.st-done { background:#d4edda; color:#155724; }
.st-noshow { background:#f8d7da; color:#721c24; }
.row-actions { display:flex; flex-wrap:wrap; gap:8px; }
.row-actions form { margin:0; display:inline; }
.row-actions button { padding:8px 12px; border:none; border-radius:6px; font-weight:600; cursor:pointer; font-size:0.88rem; }
.btn-complete { background:var(--cnu-blue); color:white; }
.btn-complete:hover { background:#002244; }
.btn-noshow { background:#6c757d; color:white; }
.btn-noshow:hover { background:#545b62; }
.empty-note { color:#5a6673; font-style:italic; }
.back-link { display:inline-block; margin-bottom:14px; color:var(--cnu-blue); font-weight:600; text-decoration:none; }
.back-link:hover { text-decoration:underline; }
</style>
</head>
<body>
<header>
    <div class="header-inner">
        <h1 class="header-title">Christopher Newport University - Research Participation System</h1>
    </div>
</header>
<div class="top-tabs">
    <a href="researcher_dashboard.php" class="top-tab-link">&#8962; Home</a>
    <a href="new_study.php" class="top-tab-link">Create New Study</a>
    <a href="researcher_studies.php" class="top-tab-link">Your Studies</a>
    <div class="tab-spacer"></div>
    <div class="profile-dropdown">
        <a href="#"><?php echo htmlspecialchars($_SESSION['email']); ?></a>
        <div class="profile-dropdown-content">
            <a href="edit_profile.php">Edit Profile</a>
            <a href="change_password.php">Change Password</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>
<div class="container">
    <a class="back-link" href="researcher_studies.php">&larr; Back to Your Studies</a>
    <?php if ($study !== null): ?>
        <a class="back-link" href="edit_study.php?studyID=<?php echo (int)$study['StudyID']; ?>" style="margin-left:1rem;">Edit study details</a>
    <?php endif; ?>

    <?php if ($study === null && $studyID <= 0): ?>
        <div class="panel">
            <p class="empty-note">Choose a study from Your Studies and open Attendance &amp; completion.</p>
        </div>
    <?php elseif ($study === null): ?>
        <div class="panel">
            <div class="message err"><?php echo htmlspecialchars($error !== '' ? $error : 'Unable to load study.'); ?></div>
        </div>
    <?php else: ?>
        <div class="panel">
            <span class="panel-title"><?php echo htmlspecialchars($study['StudyTitle']); ?> — Attendance</span>
            <?php if ($message !== ''): ?><div class="message ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="message err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <?php
            $credAmt = sona_study_completion_credit_amount();
            $credLabel = rtrim(rtrim(number_format($credAmt, 2, '.', ''), '0'), '.');
            $credWord = abs($credAmt - 1.0) < 0.0001 ? 'credit' : 'credits';
            $startFmt = date('M j, Y', strtotime($study['StartDate']));
            $endFmt = !empty($study['EndDate']) ? date('M j, Y', strtotime($study['EndDate'])) : '';
            ?>
            <div class="study-dates-bar">
                <span class="dates-label">Study dates</span>
                <span class="dates-range">
                    <?php echo htmlspecialchars($startFmt); ?>
                    <?php if ($endFmt !== ''): ?>
                        <span style="color:#8a96a3;font-weight:500;"> → </span><?php echo htmlspecialchars($endFmt); ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="attendance-legend" aria-label="What each action does">
                <div class="legend-card legend-card-complete">
                    <div class="legend-card-inner">
                        <span class="legend-title">Mark completed</span>
                        <span class="legend-sub"><?php echo htmlspecialchars($credLabel . ' ' . $credWord); ?> · Student email</span>
                    </div>
                </div>
                <div class="legend-card legend-card-noshow">
                    <div class="legend-card-inner">
                        <span class="legend-title">No-show</span>
                        <span class="legend-sub">Student absent · No credit or email</span>
                    </div>
                </div>
            </div>

            <div class="participants-heading">Participants</div>
            <?php if (count($participants) === 0): ?>
                <p class="empty-note">No students have signed up for this study yet.</p>
            <?php else: ?>
                <?php foreach ($participants as $p): ?>
                    <?php
                    $st = $p['ParticipationStatus'] ?? 'pending';
                    $badgeClass = $st === 'completed' ? 'st-done' : ($st === 'no_show' ? 'st-noshow' : 'st-pending');
                    ?>
                    <div class="participant-row">
                        <div class="participant-info">
                            <div class="participant-name"><?php echo htmlspecialchars($p['FirstName'] . ' ' . $p['LastName']); ?></div>
                            <div class="participant-email"><?php echo htmlspecialchars($p['Email']); ?></div>
                            <span class="status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars(sona_status_label($st)); ?></span>
                            <?php if (!empty($p['CompletedAt']) && $st !== 'pending'): ?>
                                <span style="font-size:0.85rem;color:#6c757d;margin-left:8px;"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($p['CompletedAt']))); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="row-actions">
                            <?php if ($st === 'pending'): ?>
                                <form method="post" onsubmit="return confirm('Award <?php echo htmlspecialchars($credLabel . ' ' . $credWord); ?> and email this student?');">
                                    <input type="hidden" name="studyID" value="<?php echo (int)$study['StudyID']; ?>">
                                    <input type="hidden" name="studentUserID" value="<?php echo (int)$p['UserID']; ?>">
                                    <input type="hidden" name="attendance_action" value="complete">
                                    <button type="submit" class="btn-complete" title="Record completion, add credit, send confirmation email">Mark completed</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Mark as absent? No credit or email will be sent.');">
                                    <input type="hidden" name="studyID" value="<?php echo (int)$study['StudyID']; ?>">
                                    <input type="hidden" name="studentUserID" value="<?php echo (int)$p['UserID']; ?>">
                                    <input type="hidden" name="attendance_action" value="no_show">
                                    <button type="submit" class="btn-noshow" title="Student did not attend">No-show</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
