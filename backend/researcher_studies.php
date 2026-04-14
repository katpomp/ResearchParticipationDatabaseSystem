<?php
session_start();
include "db_connect.php";
require_once __DIR__ . '/study_participation_schema.php';
require_once __DIR__ . '/study_session_schema.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'researcher') {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['user_id'];
$message = '';
$error = '';

$researcherProfileID = null;
$researcherStmt = $conn->prepare("SELECT ResearcherID FROM Researcher WHERE UserID=? LIMIT 1");
$researcherStmt->bind_param("i", $userID);
$researcherStmt->execute();
$researcherRes = $researcherStmt->get_result();
if ($researcherRow = $researcherRes->fetch_assoc()) {
    $researcherProfileID = (int)$researcherRow['ResearcherID'];
} else {
    $error = "Researcher profile not found. Please contact support.";
}

sona_ensure_participation_status_columns($conn);
sona_ensure_study_session_columns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $researcherProfileID !== null) {
    $action = $_POST['action'];
    $studyID = intval($_POST['studyID'] ?? 0);

    if ($action === 'delete' && $studyID > 0) {
        $stmt = $conn->prepare("DELETE FROM Study WHERE StudyID=? AND ResearcherID=?");
        $stmt->bind_param("ii", $studyID, $researcherProfileID);
        if ($stmt->execute()) {
            $message = "Study deleted successfully.";
        } else {
            $error = "Error deleting study: " . $stmt->error;
        }
    }
}

$studies = [];
if ($researcherProfileID !== null) {
    $stmt = $conn->prepare("SELECT StudyID, StudyTitle, Description, Status, StartDate, EndDate, SessionMode, OnlineMeetingURL, BuildingName, RoomNumber FROM Study WHERE ResearcherID=? ORDER BY StartDate ASC");
    $stmt->bind_param("i", $researcherProfileID);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $studies[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Your Studies - Research Participation System</title>
<link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
:root { --cnu-blue:#003366; --cnu-silver:#E0E0E0; --text-dark:#333; }
body { margin:0; padding:0; font-family:'Inter',sans-serif; background:#f0f2f5; color:var(--text-dark); }
header { background:linear-gradient(90deg, #002b55 0%, var(--cnu-blue) 100%); padding:1rem 2rem; color:white; box-shadow:0 4px 14px rgba(0,0,0,0.2); font-family:'Crimson Pro', serif; }
.header-inner { display:flex; justify-content:center; }
.header-title { margin:0; font-size:2rem; font-weight:700; letter-spacing:0.3px; text-align:center; }
.top-tabs { display:flex; align-items:center; gap:10px; padding:8px 2rem; background:var(--cnu-silver); border-top:1px solid #cfd3d8; border-bottom:1px solid #cfd3d8; }
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
.container { padding:20px 2rem; }
.panel { background:white; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.05); padding:15px; }
.panel-title { display:block; margin-bottom:16px; padding:10px 12px; border-radius:8px; font-family:'Crimson Pro', serif; font-size:1.5em; color:white; background:var(--cnu-blue); text-align:center; text-decoration:none; }
.message { padding:10px; border-radius:6px; margin-bottom:12px; }
.ok { background:#dff0d8; color:#3c763d; }
.err { background:#f8d7da; color:#842029; }
.study-item { margin-bottom:14px; padding:12px; border:1px solid #d9dfe7; border-left:4px solid var(--cnu-blue); border-radius:8px; background:#fafbfd; }
.study-header { display:flex; justify-content:space-between; align-items:baseline; gap:12px; }
.study-title { font-weight:600; color:var(--text-dark); }
.study-date { color:#555; white-space:nowrap; }
.study-description { margin-top:6px; color:#444; }
.meta { margin-top:6px; color:#4e5c69; font-size:0.92rem; }
form.study-action { text-align:center; margin-top:8px; display:flex; flex-wrap:wrap; gap:10px; justify-content:center; align-items:center; }
form input[type="submit"] { padding:6px 14px; background-color:var(--cnu-blue); color:white; border:none; border-radius:4px; cursor:pointer; }
form input[type="submit"]:hover { background:#002244; }
form.study-action a.attendance-link, form.study-action a.edit-link { display:inline-block; padding:6px 14px; background:#fff; color:var(--cnu-blue); border:1px solid var(--cnu-blue); border-radius:4px; text-decoration:none; font-weight:600; font-size:0.9rem; }
form.study-action a.attendance-link:hover, form.study-action a.edit-link:hover { background:#f0f5fa; }
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
    <a href="researcher_dashboard.php" class="top-tab-link">&#8962; Home</a>
    <a href="new_study.php" class="top-tab-link">Create New Study</a>
    <a href="researcher_studies.php" class="top-tab-link active">Your Studies</a>
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
    <div class="panel">
        <span class="panel-title">Your Studies</span>
        <?php if ($message !== ''): ?><div class="message ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="message err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if (count($studies) === 0): ?>
            <p class="empty-note">No studies found. Create one from the Create New Study page.</p>
        <?php else: ?>
            <?php foreach($studies as $study): ?>
                <div class="study-item">
                    <div class="study-header">
                        <span class="study-title"><?php echo htmlspecialchars($study['StudyTitle']); ?></span>
                        <span class="study-date"><?php echo htmlspecialchars(date('M j, Y', strtotime($study['StartDate']))); ?></span>
                    </div>
                    <div class="meta">Status: <?php echo htmlspecialchars($study['Status'] ?? 'Open'); ?></div>
                    <?php
                    $sm = ($study['SessionMode'] ?? 'in_person') === 'online' ? 'online' : 'in_person';
                    if ($sm === 'online') {
                        $hasLink = trim((string)($study['OnlineMeetingURL'] ?? '')) !== '';
                        echo '<div class="meta">Format: <strong>Online</strong>' . ($hasLink ? ' · meeting link set' : ' · add URL in Edit') . '</div>';
                    } else {
                        $b = trim((string)($study['BuildingName'] ?? ''));
                        $r = trim((string)($study['RoomNumber'] ?? ''));
                        $loc = trim($b . ($b !== '' && $r !== '' ? ', ' : '') . $r);
                        echo '<div class="meta">Format: <strong>In person</strong>';
                        if ($loc !== '') {
                            echo ' · ' . htmlspecialchars($loc);
                        }
                        echo '</div>';
                    }
                    ?>
                    <?php if (!empty($study['EndDate'])): ?>
                        <div class="meta">End Date: <?php echo htmlspecialchars(date('M j, Y', strtotime($study['EndDate']))); ?></div>
                    <?php endif; ?>
                    <div class="study-description"><?php echo htmlspecialchars($study['Description'] ?? ''); ?></div>
                    <form method="post" class="study-action">
                        <a class="edit-link" href="edit_study.php?studyID=<?php echo (int)$study['StudyID']; ?>">Edit study</a>
                        <a class="attendance-link" href="researcher_study_attendance.php?studyID=<?php echo (int)$study['StudyID']; ?>">Attendance &amp; completion</a>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="studyID" value="<?php echo $study['StudyID']; ?>">
                        <input type="submit" value="Delete Study" onclick="return confirm('Delete this study and all sign-up records?');">
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
