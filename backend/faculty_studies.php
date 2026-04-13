<?php
session_start();
include "db_connect.php";
require_once __DIR__ . '/study_participation_schema.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

sona_ensure_participation_status_columns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_signup'])) {
    $studyID = intval($_POST['studyID'] ?? 0);
    $studentUserID = intval($_POST['studentUserID'] ?? 0);

    if ($studyID > 0 && $studentUserID > 0) {
        $stmt = $conn->prepare("DELETE FROM StudyParticipant WHERE StudyID=? AND StudentID=?");
        $stmt->bind_param("ii", $studyID, $studentUserID);
        if ($stmt->execute()) {
            $message = "Signup cancelled successfully.";
        } else {
            $error = "Error cancelling signup: " . $stmt->error;
        }
    }
}

$studies = [];
$result = $conn->query("
    SELECT s.StudyID, s.StudyTitle, s.Description, s.Status, s.StartDate, s.EndDate,
           r.FirstName AS ResearcherFirstName, r.LastName AS ResearcherLastName
    FROM Study s
    LEFT JOIN Researcher r ON r.ResearcherID = s.ResearcherID
    ORDER BY s.StartDate ASC
");
while ($row = $result->fetch_assoc()) {
    $studies[] = $row;
}

$signups = [];
$stmt = $conn->prepare("
    SELECT sp.StudyID, sp.StudentID AS StudentUserID, sp.ParticipationStatus,
           s.FirstName, s.LastName, s.Email
    FROM StudyParticipant sp
    JOIN Student s ON sp.StudentID = s.UserID
");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $signups[$row['StudyID']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>All Studies & Signups - Research Participation System</title>
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
.panel-title { display:block; margin-bottom:16px; padding:10px 12px; border-radius:8px; font-family:'Crimson Pro', serif; font-size:1.5em; color:white; background:var(--cnu-blue); text-align:center; }
.message { padding:10px; border-radius:6px; margin-bottom:12px; }
.ok { background:#dff0d8; color:#3c763d; }
.err { background:#f8d7da; color:#842029; }
.study-item { margin-bottom:14px; padding:12px; border:1px solid #d9dfe7; border-left:4px solid var(--cnu-blue); border-radius:8px; background:#fafbfd; }
.study-header { display:flex; justify-content:space-between; align-items:baseline; gap:12px; }
.study-title { font-weight:700; }
.study-date { color:#55626e; white-space:nowrap; }
.meta { margin-top:6px; color:#4e5c69; font-size:0.92rem; }
.signup-row { margin-top:8px; padding:8px; background:white; border:1px solid #d9dfe7; border-radius:6px; display:flex; align-items:center; justify-content:space-between; gap:10px; }
.signup-name { color:#2f3b46; }
.signup-action form { margin:0; }
input[type="submit"] { padding:6px 12px; background:var(--cnu-blue); color:white; border:none; border-radius:4px; cursor:pointer; }
input[type="submit"]:hover { background:#002244; }
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
    <a href="faculty_dashboard.php" class="top-tab-link">&#8962; Home</a>
    <a href="faculty_studies.php" class="top-tab-link active">All Studies & Signups</a>
    <a href="faculty_directory.php" class="top-tab-link">Researchers & Students</a>
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
        <span class="panel-title">All Studies & Signups</span>
        <?php if ($message !== ''): ?><div class="message ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="message err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if (count($studies) === 0): ?>
            <p class="empty-note">No studies are currently available.</p>
        <?php else: ?>
            <?php foreach($studies as $study): ?>
                <div class="study-item">
                    <div class="study-header">
                        <span class="study-title"><?php echo htmlspecialchars($study['StudyTitle']); ?></span>
                        <span class="study-date"><?php echo htmlspecialchars(date('M j, Y', strtotime($study['StartDate']))); ?></span>
                    </div>
                    <div class="meta">Researcher: <?php echo htmlspecialchars(trim(($study['ResearcherFirstName'] ?? '') . ' ' . ($study['ResearcherLastName'] ?? '')) ?: 'Unknown'); ?></div>
                    <div class="meta">Status: <?php echo htmlspecialchars($study['Status'] ?? 'Open'); ?></div>
                    <div class="meta"><?php echo htmlspecialchars($study['Description'] ?? ''); ?></div>

                    <?php if (!empty($signups[$study['StudyID']])): ?>
                        <?php foreach($signups[$study['StudyID']] as $student): ?>
                            <div class="signup-row">
                                <div class="signup-name"><?php echo htmlspecialchars($student['FirstName'] . ' ' . $student['LastName'] . ' (' . $student['Email'] . ')'); ?>
                                    <span style="font-size:0.85rem;color:#4e5c69;"> — <?php echo htmlspecialchars($student['ParticipationStatus'] ?? 'pending'); ?></span>
                                </div>
                                <div class="signup-action">
                                    <form method="post">
                                        <input type="hidden" name="studyID" value="<?php echo $study['StudyID']; ?>">
                                        <input type="hidden" name="studentUserID" value="<?php echo $student['StudentUserID']; ?>">
                                        <input type="submit" name="cancel_signup" value="Cancel Signup">
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="meta">No students signed up yet.</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
