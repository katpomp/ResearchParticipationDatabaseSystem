<?php
session_start();
include "db_connect.php";
require_once __DIR__ . '/study_participation_schema.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

$studentID = $_SESSION['user_id'];
$message = '';

sona_ensure_participation_status_columns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studyID = intval($_POST['studyID']);
    $action = $_POST['action'];

    if ($action === 'signup') {
        $stmt = $conn->prepare("INSERT INTO StudyParticipant (StudyID, StudentID) VALUES (?, ?)");
        $stmt->bind_param("ii", $studyID, $studentID);
        if ($stmt->execute()) {
            require_once __DIR__ . '/study_signup_notifications.php';
            $mailResult = sona_notify_study_signup($conn, $studyID, $studentID);
            $message = "Successfully signed up for study.";
            if (!empty($mailResult['student_send_failed'])) {
                $message .= " We could not send a confirmation email; your sign-up is still saved—check My Schedule on the site.";
            } elseif (!empty($mailResult['student_skipped_non_edu'])) {
                $message .= " Add a .edu address on your profile if you want email confirmations.";
            }
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
$studyStmt = $conn->prepare("
    SELECT StudyID, StudyTitle, Description, Status, StartDate, EndDate
    FROM Study
    WHERE StartDate >= CURDATE()
    ORDER BY StartDate ASC
");
$studyStmt->execute();
$studyRes = $studyStmt->get_result();
while ($row = $studyRes->fetch_assoc()) {
    $studies[] = $row;
}

$participationByStudy = [];
$signedStmt = $conn->prepare("SELECT StudyID, ParticipationStatus FROM StudyParticipant WHERE StudentID=?");
$signedStmt->bind_param("i", $studentID);
$signedStmt->execute();
$signedRes = $signedStmt->get_result();
while ($row = $signedRes->fetch_assoc()) {
    $participationByStudy[(int)$row['StudyID']] = $row['ParticipationStatus'] ?? 'pending';
}

$myUpcoming = [];
$myStmt = $conn->prepare("
    SELECT s.StudyID, s.StudyTitle, s.StartDate, s.EndDate
    FROM StudyParticipant sp
    JOIN Study s ON s.StudyID = sp.StudyID
    WHERE sp.StudentID = ? AND sp.ParticipationStatus = 'pending' AND s.StartDate >= CURDATE()
    ORDER BY s.StartDate ASC
");
$myStmt->bind_param("i", $studentID);
$myStmt->execute();
$myRes = $myStmt->get_result();
while ($row = $myRes->fetch_assoc()) {
    $myUpcoming[] = $row;
}

$pastStudies = [];
$pastStmt = $conn->prepare("
    SELECT s.StudyID, s.StudyTitle, s.Description, s.Status, s.StartDate, s.EndDate, sp.ParticipationStatus
    FROM StudyParticipant sp
    JOIN Study s ON s.StudyID = sp.StudyID
    WHERE sp.StudentID = ?
      AND (sp.ParticipationStatus = 'completed'
           OR sp.ParticipationStatus = 'no_show'
           OR (sp.ParticipationStatus = 'pending' AND s.StartDate < CURDATE()))
    ORDER BY s.StartDate DESC
");
$pastStmt->bind_param("i", $studentID);
$pastStmt->execute();
$pastRes = $pastStmt->get_result();
while ($row = $pastRes->fetch_assoc()) {
    $pastStudies[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Studies - Research Participation System</title>
<link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
:root { --cnu-blue: #003366; --cnu-silver: #E0E0E0; --text-dark: #333; }
body { margin:0; padding:0; font-family:'Inter',sans-serif; background:#f0f2f5; color:var(--text-dark); }
header {
    background:linear-gradient(90deg, #002b55 0%, var(--cnu-blue) 100%);
    padding:1rem 2rem;
    color:white;
    box-shadow:0 4px 14px rgba(0,0,0,0.2);
    font-family:'Crimson Pro', serif;
}
.header-inner { display:flex; justify-content:center; }
.header-title { margin:0; font-size:2rem; font-weight:700; letter-spacing:0.3px; text-align:center; }
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
.top-tab-link.active { background:var(--cnu-blue); color:white; border-color:var(--cnu-blue); }
.tab-spacer { margin-left:auto; }
.profile-dropdown { position:relative; display:inline-block; }
.profile-dropdown > a {
    display:inline-block;
    padding:8px 12px;
    border-radius:6px;
    text-decoration:none;
    font-weight:600;
    font-size:0.95rem;
    color:var(--cnu-blue);
    background:white;
    border:1px solid #c7ccd3;
}
.profile-dropdown > a:hover { background:#f4f6f8; }
.profile-dropdown-content { display:none; position:absolute; right:0; background:white; min-width:180px; box-shadow:0px 8px 16px rgba(0,0,0,0.2); z-index:1; border-radius:6px; }
.profile-dropdown-content a { color:var(--text-dark); padding:12px 16px; text-decoration:none; display:block; }
.profile-dropdown-content a:hover { background:#f1f1f1; }
.profile-dropdown:hover .profile-dropdown-content { display:block; }

.container { padding:20px 2rem; }
.content-grid { display:grid; grid-template-columns: 1fr 1.2fr; gap:20px; align-items:start; }
.left-column { display:flex; flex-direction:column; gap:20px; }
.message { padding:10px; background:#dff0d8; color:#3c763d; border-radius:6px; }
.panel { background:white; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.05); padding:16px; }
.panel-title {
    display:block;
    margin-bottom:14px;
    padding:10px 12px;
    border-radius:8px;
    font-family:'Crimson Pro', serif;
    font-size:1.5em;
    color:white;
    background:var(--cnu-blue);
    text-decoration:none;
    text-align:center;
}
.my-list { display:flex; flex-wrap:wrap; gap:10px; }
.my-chip {
    padding:8px 12px;
    border-radius:999px;
    border:1px solid #cfd8e2;
    background:#f8fbff;
    font-size:0.92rem;
}
.study-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:14px; }
.study-card {
    border:1px solid #d9dfe7;
    border-left:4px solid var(--cnu-blue);
    border-radius:8px;
    background:#fafbfd;
    padding:12px;
    display:flex;
    flex-direction:column;
    gap:8px;
}
.study-header { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
.study-title { font-weight:700; color:#26313d; }
.study-date { color:#4c5a67; font-size:0.92rem; }
.study-description { color:#3f4b56; line-height:1.45; }
.status-badge {
    padding:3px 8px;
    border-radius:999px;
    font-size:0.8rem;
    font-weight:700;
    background:#d9e7f6;
    color:var(--cnu-blue);
    white-space:nowrap;
}
form.study-action { text-align:center; margin-top:4px; }
form input[type="submit"] {
    padding:6px 14px;
    background-color:var(--cnu-blue);
    color:white;
    border:none;
    border-radius:4px;
    cursor:pointer;
}
form input[type="submit"]:hover { background:#002244; }
.empty-note { color:#5a6673; font-style:italic; }
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
    <a href="student_studies.php" class="top-tab-link active">Studies</a>
    <a href="view_credits.php" class="top-tab-link">My Schedule/Credits</a>
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
    <?php if($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="content-grid">
        <div class="left-column">
            <div class="panel">
                <span class="panel-title">My Upcoming Schedule</span>
                <?php if (count($myUpcoming) === 0): ?>
                    <p class="empty-note">You are not signed up for any upcoming studies yet.</p>
                <?php else: ?>
                    <div class="my-list">
                        <?php foreach($myUpcoming as $study): ?>
                            <div class="my-chip">
                                <?php echo htmlspecialchars($study['StudyTitle']); ?> -
                                <?php echo htmlspecialchars(date('M j, Y', strtotime($study['StartDate']))); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel">
                <span class="panel-title">Past Studies</span>
                <?php if (count($pastStudies) === 0): ?>
                    <p class="empty-note">No past studies found in your schedule yet.</p>
                <?php else: ?>
                    <div class="study-grid">
                        <?php foreach($pastStudies as $study): ?>
                            <div class="study-card">
                                <div class="study-header">
                                    <div class="study-title"><?php echo htmlspecialchars($study['StudyTitle']); ?></div>
                                    <?php
                                    $ps = $study['ParticipationStatus'] ?? 'pending';
                                    $plabel = $ps === 'completed' ? 'Completed' : ($ps === 'no_show' ? 'No-show' : 'Awaiting confirmation');
                                    ?>
                                    <span class="status-badge"><?php echo htmlspecialchars($plabel); ?></span>
                                </div>
                                <div class="study-date">
                                    <?php echo htmlspecialchars(date('M j, Y', strtotime($study['StartDate']))); ?>
                                    <?php if (!empty($study['EndDate'])): ?>
                                        - <?php echo htmlspecialchars(date('M j, Y', strtotime($study['EndDate']))); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="study-description"><?php echo htmlspecialchars($study['Description'] ?? ''); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <span class="panel-title">Upcoming Studies</span>
            <?php if (count($studies) === 0): ?>
                <p class="empty-note">No upcoming studies are currently available.</p>
            <?php else: ?>
                <div class="study-grid">
                    <?php foreach($studies as $study): ?>
                        <div class="study-card">
                            <div class="study-header">
                                <div class="study-title"><?php echo htmlspecialchars($study['StudyTitle']); ?></div>
                                <span class="status-badge"><?php echo htmlspecialchars($study['Status'] ?? 'Open'); ?></span>
                            </div>
                            <div class="study-date">
                                <?php echo htmlspecialchars(date('M j, Y', strtotime($study['StartDate']))); ?>
                                <?php if (!empty($study['EndDate'])): ?>
                                    - <?php echo htmlspecialchars(date('M j, Y', strtotime($study['EndDate']))); ?>
                                <?php endif; ?>
                            </div>
                            <div class="study-description"><?php echo htmlspecialchars($study['Description'] ?? ''); ?></div>

                            <?php
                            $pstat = $participationByStudy[$study['StudyID']] ?? null;
                            ?>
                            <form method="post" class="study-action">
                                <input type="hidden" name="studyID" value="<?php echo $study['StudyID']; ?>">
                                <?php if ($pstat === 'completed'): ?>
                                    <span class="empty-note" style="font-style:normal;font-weight:600;color:#2f6f39;">Completed — credits recorded</span>
                                <?php elseif ($pstat === 'no_show'): ?>
                                    <span class="empty-note" style="font-style:normal;">Recorded as no-show</span>
                                <?php elseif ($pstat === 'pending'): ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="submit" value="Cancel Signup">
                                <?php else: ?>
                                    <input type="hidden" name="action" value="signup">
                                    <input type="submit" value="Sign Up">
                                <?php endif; ?>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
