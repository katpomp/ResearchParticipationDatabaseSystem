<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

$studentID = $_SESSION['user_id'];
$requiredCredits = 22;
$earnedCredits = 0;
$pendingCredits = 0;
$completedCredits = 0;

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
    JOIN Study s ON s.StudyID = sp.StudyID
    WHERE sp.StudentID = ? AND s.StartDate >= CURDATE()
");
$pendingStmt->bind_param("i", $studentID);
$pendingStmt->execute();
$pendingRes = $pendingStmt->get_result();
if ($pendingRow = $pendingRes->fetch_assoc()) {
    $pendingCredits = (int)$pendingRow['pending_count'];
}

$completedStmt = $conn->prepare("
    SELECT COUNT(*) AS completed_count
    FROM StudyParticipant sp
    JOIN Study s ON s.StudyID = sp.StudyID
    WHERE sp.StudentID = ? AND s.StartDate < CURDATE()
");
$completedStmt->bind_param("i", $studentID);
$completedStmt->execute();
$completedRes = $completedStmt->get_result();
if ($completedRow = $completedRes->fetch_assoc()) {
    $completedCredits = (int)$completedRow['completed_count'];
}

$remainingCredits = max(0, $requiredCredits - (int)$earnedCredits);
$progressPercent = 0;
if ($requiredCredits > 0) {
    $progressPercent = min(100, ($earnedCredits / $requiredCredits) * 100);
}

$pendingStudies = [];
$pendingListStmt = $conn->prepare("
    SELECT s.StudyTitle, s.StartDate, s.EndDate, s.Description
    FROM StudyParticipant sp
    JOIN Study s ON s.StudyID = sp.StudyID
    WHERE sp.StudentID = ? AND s.StartDate >= CURDATE()
    ORDER BY s.StartDate ASC
");
$pendingListStmt->bind_param("i", $studentID);
$pendingListStmt->execute();
$pendingListRes = $pendingListStmt->get_result();
while ($row = $pendingListRes->fetch_assoc()) {
    $pendingStudies[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Credits Overview - Research Participation System</title>
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

.container { padding:20px 2rem; display:grid; gap:20px; }
.panel { background:white; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.05); padding:18px; }
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
.overview-grid { display:grid; grid-template-columns: 1fr 1fr; gap:20px; }
.credits-content { display:flex; align-items:center; gap:18px; }
.credits-donut {
    --p: 0%;
    width:140px;
    height:140px;
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
    width:98px;
    height:98px;
    border-radius:50%;
    background:white;
}
.credits-center-value {
    position:relative;
    z-index:1;
    font-size:2.1rem;
    font-weight:700;
    color:var(--text-dark);
}
.credits-legend { display:flex; flex-direction:column; gap:10px; }
.legend-row { display:flex; align-items:center; gap:8px; }
.legend-badge {
    min-width:30px;
    text-align:center;
    padding:4px 7px;
    border-radius:4px;
    color:white;
    font-weight:700;
    font-size:0.9rem;
}
.legend-earned { background:var(--cnu-blue); }
.legend-pending { background:#1f6fb2; }
.legend-required { background:#6f7782; }
.stat-grid { display:grid; grid-template-columns: repeat(2, minmax(120px, 1fr)); gap:12px; }
.stat-card { background:#f8fafc; border:1px solid #d9dfe7; border-radius:8px; padding:12px; }
.stat-label { color:#5a6673; font-size:0.9rem; margin-bottom:5px; }
.stat-value { font-size:1.5rem; font-weight:700; color:var(--cnu-blue); }
.study-row { border:1px solid #d9dfe7; border-left:4px solid var(--cnu-blue); border-radius:8px; background:#fafbfd; padding:12px; margin-bottom:12px; }
.study-title { font-weight:600; margin-bottom:4px; }
.study-meta { color:#4c5a67; font-size:0.95rem; margin-bottom:6px; }
.study-description { color:#444; }
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
    <a href="student_studies.php" class="top-tab-link">Studies</a>
    <a href="view_credits.php" class="top-tab-link active">My Schedule/Credits</a>
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
        <span class="panel-title">Credits Overview</span>
        <div class="overview-grid">
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

            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-label">Required Credits</div>
                    <div class="stat-value"><?php echo htmlspecialchars((string)$requiredCredits); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Remaining</div>
                    <div class="stat-value"><?php echo htmlspecialchars((string)$remainingCredits); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Completed Studies</div>
                    <div class="stat-value"><?php echo htmlspecialchars((string)$completedCredits); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Progress</div>
                    <div class="stat-value"><?php echo htmlspecialchars((string)round($progressPercent)); ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <span class="panel-title">Pending Study Schedule</span>
        <?php if (count($pendingStudies) === 0): ?>
            <p class="empty-note">You currently have no upcoming studies in your schedule.</p>
        <?php else: ?>
            <?php foreach($pendingStudies as $study): ?>
                <div class="study-row">
                    <div class="study-title"><?php echo htmlspecialchars($study['StudyTitle']); ?></div>
                    <div class="study-meta">
                        <?php echo htmlspecialchars(date('M j, Y', strtotime($study['StartDate']))); ?>
                        <?php if (!empty($study['EndDate'])): ?>
                            - <?php echo htmlspecialchars(date('M j, Y', strtotime($study['EndDate']))); ?>
                        <?php endif; ?>
                    </div>
                    <div class="study-description"><?php echo htmlspecialchars($study['Description'] ?? ''); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
