<?php
session_start();
include "db_connect.php";
require_once __DIR__ . '/study_participation_schema.php';
require_once __DIR__ . '/study_session_schema.php';
require_once __DIR__ . '/inperson_session_schema.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

$studentID = $_SESSION['user_id'];
$message = '';

sona_ensure_participation_status_columns($conn);
sona_ensure_study_session_columns($conn);
sona_ensure_inperson_session_columns($conn);
$studentPk = sona_student_primary_key_for_user($conn, (int)$studentID);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studyID = intval($_POST['studyID']);
    $action = $_POST['action'];

    if ($action === 'signup') {
        // In-person studies require selecting a time slot first.
        $modeStmt = $conn->prepare("SELECT SessionMode FROM Study WHERE StudyID=? LIMIT 1");
        $sessionMode = 'in_person';
        if ($modeStmt) {
            $modeStmt->bind_param("i", $studyID);
            $modeStmt->execute();
            if ($m = $modeStmt->get_result()->fetch_assoc()) {
                $sessionMode = (($m['SessionMode'] ?? 'in_person') === 'online') ? 'online' : 'in_person';
            }
        }
        if ($sessionMode !== 'online') {
            $_SESSION['signup_study_flash'] = "This is an in-person study. Please choose a time slot to sign up.";
            header("Location: student_study_detail.php?studyID=" . (int)$studyID);
            exit();
        }
        $stmt = $conn->prepare("INSERT INTO StudyParticipant (StudyID, StudentID) VALUES (?, ?)");
        $stmt->bind_param("ii", $studyID, $studentID);
        if ($stmt->execute()) {
            require_once __DIR__ . '/study_signup_notifications.php';
            $mailResult = sona_notify_study_signup($conn, $studyID, $studentID, null);
            $flash = "Successfully signed up for study.";
            if (!empty($mailResult['student_send_failed'])) {
                $flash .= " We could not send a confirmation email; your sign-up is still saved.";
            } elseif (!empty($mailResult['student_skipped_non_edu'])) {
                $flash .= " Add a .edu address on your profile if you want email confirmations.";
            }
            $_SESSION['signup_study_flash'] = $flash;
            header("Location: student_study_detail.php?studyID=" . (int)$studyID . "&new=1");
            exit();
        } else {
            $message = "Error: " . $stmt->error;
        }
    } elseif ($action === 'cancel') {
        $stmt = $conn->prepare("DELETE FROM StudyParticipant WHERE StudyID=? AND StudentID=? AND ParticipationStatus='pending'");
        $stmt->bind_param("ii", $studyID, $studentID);
        if ($stmt->execute()) {
            $message = $stmt->affected_rows > 0
                ? "Successfully cancelled study signup."
                : "You can only cancel a sign-up that is still pending.";
        } else {
            $message = "Error: " . $stmt->error;
        }
    }
}

$studies = [];
$result = $conn->query("SELECT StudyID, StudyTitle, StartDate, Description AS StudyDescription FROM Study WHERE StartDate >= CURDATE() ORDER BY StartDate ASC");
while ($row = $result->fetch_assoc()) {
    $studies[] = $row;
}

$participationByStudy = [];
$stmt = $conn->prepare("SELECT StudyID, ParticipationStatus FROM StudyParticipant WHERE StudentID=?");
$stmt->bind_param("i", $studentID);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $participationByStudy[(int)$row['StudyID']] = $row['ParticipationStatus'] ?? 'pending';
}

// Credits overview metrics
$requiredCredits = 9;
$earnedCredits = 0;
$pendingCredits = 0;
$creditsPerStudy = 3;

$creditsStmt = $conn->prepare("SELECT total_credits FROM credits WHERE user_id=?");
$creditsStmt->bind_param("i", $studentID);
$creditsStmt->execute();
$creditsRes = $creditsStmt->get_result();
if ($creditsRow = $creditsRes->fetch_assoc()) {
    $earnedCredits = (float)$creditsRow['total_credits'];
}

$pendingStmt = $conn->prepare("
    SELECT COUNT(*) AS pending_count
    FROM StudyParticipant sp
    WHERE sp.StudentID = ? AND sp.ParticipationStatus = 'pending'
");
$pendingStmt->bind_param("i", $studentID);
$pendingStmt->execute();
$pendingRes = $pendingStmt->get_result();
if ($pendingRow = $pendingRes->fetch_assoc()) {
    $pendingCredits = (int)$pendingRow['pending_count'] * $creditsPerStudy;
}

$progressPercent = 0;
if ($requiredCredits > 0) {
    $progressPercent = min(100, ($earnedCredits / $requiredCredits) * 100);
}

$events = [];

// Booked pending sessions: use the student's time slot when available (in-person).
$calendarStmt = $conn->prepare("
    SELECT s.StudyID, s.StudyTitle, s.StartDate, s.SessionMode, ips.SessionDate, ips.SessionTime
    FROM StudyParticipant sp
    JOIN Study s ON s.StudyID = sp.StudyID
    LEFT JOIN InPersonSession ips ON ips.StudyID = s.StudyID AND ips.StudentID = ?
    WHERE sp.StudentID = ? AND sp.ParticipationStatus = 'pending'
    ORDER BY COALESCE(ips.SessionDate, s.StartDate) ASC, ips.SessionTime ASC
");
$slotStudentPk = $studentPk ?? 0;
$calendarStmt->bind_param("ii", $slotStudentPk, $studentID);
$calendarStmt->execute();
$calendarRes = $calendarStmt->get_result();
while ($calendarRow = $calendarRes->fetch_assoc()) {
    $mode = (($calendarRow['SessionMode'] ?? 'in_person') === 'online') ? 'online' : 'in_person';
    $start = null;
    if ($mode !== 'online' && !empty($calendarRow['SessionDate'])) {
        $start = sona_inperson_slot_start_iso($calendarRow['SessionDate'], $calendarRow['SessionTime'] ?? null);
    }
    if ($start === null && !empty($calendarRow['StartDate'])) {
        $start = $calendarRow['StartDate'];
    }
    if ($start === null) {
        continue;
    }
    $events[] = [
        'title' => 'My session: ' . $calendarRow['StudyTitle'],
        'start' => $start,
        'url' => 'student_study_detail.php?studyID=' . (int)$calendarRow['StudyID'],
        'backgroundColor' => '#003366',
        'borderColor' => '#002244',
    ];
}

// Open in-person time slots students can still claim.
$openRes = $conn->query("
    SELECT ips.SessionID, ips.SessionDate, ips.SessionTime, s.StudyTitle, s.StudyID
    FROM InPersonSession ips
    INNER JOIN Study s ON s.StudyID = ips.StudyID
    WHERE (ips.StudentID IS NULL OR ips.StudentID = 0)
      AND (ips.SessionDate >= CURDATE() OR ips.SessionDate IS NULL)
      AND (s.SessionMode IS NULL OR LOWER(s.SessionMode) <> 'online')
      AND s.Status = 'Open'
    ORDER BY ips.SessionDate ASC, ips.SessionTime ASC, ips.SessionID ASC
");
if ($openRes) {
    while ($openRow = $openRes->fetch_assoc()) {
        $start = sona_inperson_slot_start_iso($openRow['SessionDate'] ?? null, $openRow['SessionTime'] ?? null);
        if ($start === null) {
            continue;
        }
        $events[] = [
            'title' => 'Open slot: ' . $openRow['StudyTitle'],
            'start' => $start,
            'url' => 'student_study_detail.php?studyID=' . (int)$openRow['StudyID'],
            'backgroundColor' => '#94a3b8',
            'borderColor' => '#64748b',
        ];
    }
}

$events_json = json_encode($events);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Christopher Newport University - Research Participation System</title>
<link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<style>
:root { --cnu-blue: #003366; --cnu-silver: #E0E0E0; --text-dark: #333; }
body { margin:0;padding:0;font-family:'Inter',sans-serif; background:#f0f2f5; color:var(--text-dark); }
header {
    background:linear-gradient(90deg, #002b55 0%, var(--cnu-blue) 100%);
    padding:1rem 2rem;
    color:white;
    box-shadow:0 4px 14px rgba(0,0,0,0.2);
    font-family:'Crimson Pro', serif;
}
.header-inner {
    display:flex;
    justify-content:center;
}
.header-title {
    margin:0;
    font-size:2rem;
    font-weight:700;
    letter-spacing:0.3px;
    text-align:center;
}

.top-tabs {
    display:flex;
    align-items:center;
    gap:10px;
    padding:8px 2rem;
    background:var(--cnu-silver);
    border-top:1px solid #cfd3d8;
    border-bottom:1px solid #cfd3d8;
}
.top-tab-link {
    display:inline-block;
    padding:8px 12px;
    border-radius:6px;
    text-decoration:none;
    color:var(--cnu-blue);
    font-weight:600;
    background:white;
    border:1px solid #c7ccd3;
}
.top-tab-link:hover { background:#f4f6f8; }
.tab-spacer { margin-left:auto; }

.profile-dropdown { position:relative; display:inline-block; }
.profile-dropdown > a {
    display:inline-block;
    padding:8px 12px;
    border-radius:6px;
    text-decoration:none;
    font-family:'Inter',sans-serif;
    font-weight:600;
    font-size:0.95rem;
    color:var(--cnu-blue);
    background:white;
    border:1px solid #c7ccd3;
}
.profile-dropdown > a:hover { background:#f4f6f8; }
.profile-dropdown-content { display:none; position:absolute; right:0; background:white; min-width:180px; box-shadow:0px 8px 16px rgba(0,0,0,0.2); z-index:1; border-radius:6px;}
.profile-dropdown-content a { color:var(--text-dark); padding:12px 16px; text-decoration:none; display:block;}
.profile-dropdown-content a:hover { background:#f1f1f1; }
.profile-dropdown:hover .profile-dropdown-content { display:block; }
.container { display:flex; gap:20px; padding:20px 2rem 0 2rem; margin-bottom:20px; align-items:flex-start; }
#calendar { width:65%; background:white; padding:15px; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.05);}
.right-column { width:35%; display:flex; flex-direction:column; gap:16px; }
.panel { background:white; padding:15px; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.05); }
.studies-panel { overflow-y:auto; max-height:52vh; }
.sidebar-header-link {
    display:block;
    margin-top:0;
    margin-bottom:16px;
    padding:10px 12px;
    border-radius:8px;
    font-family:'Crimson Pro', serif;
    font-size:1.5em;
    color:white;
    background:var(--cnu-blue);
    letter-spacing:0.2px;
    text-decoration:none;
    text-align:center;
}
.sidebar-header-link:hover {
    background:#002244;
}
.study-item {
    margin-bottom:14px;
    padding:12px;
    border:1px solid #d9dfe7;
    border-left:4px solid var(--cnu-blue);
    border-radius:8px;
    background:#fafbfd;
}
.study-header { display:flex; justify-content:space-between; align-items:baseline; gap:12px; }
.study-title { font-weight:600; color:var(--text-dark); }
.study-date { color:#555; white-space:nowrap; }
.study-description { margin-top:6px; color:#444; }
.message { padding:10px; background:#dff0d8; color:#3c763d; border-radius:4px; margin-bottom:15px;}
form.study-action { text-align:center; margin-top:8px; }
form input[type="submit"] { padding:6px 14px; background-color:var(--cnu-blue); color:white; border:none; border-radius:4px; cursor:pointer; margin-top:5px;}
form input[type="submit"]:hover { background:#002244;}
.action-outline {
    display:inline-block;
    margin-right:8px;
    padding:6px 12px;
    background:#fff;
    color:var(--cnu-blue);
    border:1px solid var(--cnu-blue);
    border-radius:4px;
    text-decoration:none;
    font-weight:600;
    font-size:0.88rem;
    line-height:1.2;
}
form.study-action input[type="submit"].action-outline {
    -webkit-appearance:none;
    appearance:none;
}
.action-outline:hover { background:#f0f5fa; }
.credits-panel {
    padding:12px;
    border:1px solid #d9dfe7;
    border-radius:8px;
    background:#fafbfd;
}
.credits-panel h3 {
    margin:0 0 10px 0;
    color:var(--cnu-blue);
    font-family:'Crimson Pro', serif;
    font-size:1.3rem;
}
.credits-content { display:flex; align-items:center; gap:18px; }
.credits-donut {
    --p: 0%;
    width:120px;
    height:120px;
    border-radius:50%;
    background:conic-gradient(var(--cnu-blue) var(--p), #b0b7c3 0);
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
    flex-shrink:0;
}
.credits-donut::before {
    content:"";
    position:absolute;
    width:84px;
    height:84px;
    border-radius:50%;
    background:white;
}
.credits-center-value {
    position:relative;
    z-index:1;
    font-size:2rem;
    font-weight:700;
    color:var(--text-dark);
}
.credits-legend { display:flex; flex-direction:column; gap:10px; }
.legend-row { display:flex; align-items:center; gap:8px; }
.legend-badge {
    min-width:26px;
    text-align:center;
    padding:3px 6px;
    border-radius:4px;
    color:white;
    font-weight:700;
    font-size:0.9rem;
}
.legend-earned { background:var(--cnu-blue); }
.legend-pending { background:#1f6fb2; }
.legend-required { background:#6f7782; }
</style>
</head>
<body>

<header>
    <div class="header-inner">
        <h1 class="header-title">Christopher Newport University - Research Participation System</h1>
    </div>
</header>

<div class="top-tabs">
    <a href="student_dashboard.php" class="top-tab-link">&#8962; Home</a>
    <a href="student_studies.php" class="top-tab-link">Studies</a>
    <a href="view_credits.php" class="top-tab-link">My Schedule/Credits</a>
    <div class="tab-spacer"></div>
    <div class="profile-dropdown">
        <a href="#"><?php echo htmlspecialchars($_SESSION['email']); ?></a>
        <div class="profile-dropdown-content">
            <a href="redeem_role_code.php">Role invitation (code)</a>
            <a href="edit_profile.php">Edit Profile</a>
            <a href="change_password.php">Change Password</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="container">
    <div id="calendar"></div>

    <div class="right-column">
        <div class="panel credits-panel">
            <a href="view_credits.php" class="sidebar-header-link">Credits Overview</a>
            <div class="credits-content">
                <div class="credits-donut" style="--p: <?php echo htmlspecialchars((string)$progressPercent); ?>%;">
                    <span class="credits-center-value"><?php echo htmlspecialchars((string)(int)$earnedCredits); ?></span>
                </div>
                <div class="credits-legend">
                    <div class="legend-row">
                        <span class="legend-badge legend-earned"><?php echo htmlspecialchars((string)(int)$earnedCredits); ?></span>
                        <span>Earned</span>
                    </div>
                    <div class="legend-row">
                        <span class="legend-badge legend-pending"><?php echo htmlspecialchars((string)$pendingCredits); ?></span>
                        <span>Pending</span>
                    </div>
                    <div class="legend-row">
                        <span class="legend-badge legend-required"><?php echo htmlspecialchars((string)$requiredCredits); ?></span>
                        <span>Required</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel studies-panel">
            <a href="student_studies.php" class="sidebar-header-link">Upcoming Studies</a>
            <?php if($message) echo "<div class='message'>$message</div>"; ?>

            <?php foreach($studies as $study): ?>
                <div class="study-item">
                    <div class="study-header">
                        <span class="study-title"><?php echo htmlspecialchars($study['StudyTitle']); ?></span>
                        <span class="study-date">
                            <?php
                                $friendlyDate = date('M j, Y', strtotime($study['StartDate']));
                                echo htmlspecialchars($friendlyDate);
                            ?>
                        </span>
                    </div>
                    <div class="study-description"><?php echo htmlspecialchars($study['StudyDescription'] ?? ''); ?></div>

                    <?php $pstat = $participationByStudy[$study['StudyID']] ?? null; ?>
                    <form method="post" class="study-action">
                        <input type="hidden" name="studyID" value="<?php echo $study['StudyID']; ?>">
                        <?php if ($pstat === 'completed'): ?>
                            <a class="action-outline" href="student_study_detail.php?studyID=<?php echo (int)$study['StudyID']; ?>">Study details</a>
                            <span style="font-weight:600;color:#2f6f39;">Completed</span>
                        <?php elseif ($pstat === 'no_show'): ?>
                            <a class="action-outline" href="student_study_detail.php?studyID=<?php echo (int)$study['StudyID']; ?>">Study details</a>
                            <span style="color:#6c757d;">No-show</span>
                        <?php elseif ($pstat === 'pending'): ?>
                            <a class="action-outline" href="student_study_detail.php?studyID=<?php echo (int)$study['StudyID']; ?>">Study details</a>
                            <input type="hidden" name="action" value="cancel">
                            <input class="action-outline" type="submit" value="Cancel">
                        <?php else: ?>
                            <input type="hidden" name="action" value="signup">
                            <input type="submit" value="Sign Up">
                        <?php endif; ?>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        events: <?php echo $events_json; ?>,
        height: 'auto',
    });
    calendar.render();
});
</script>

</body>
</html>