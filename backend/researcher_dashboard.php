<?php
session_start();
include "db_connect.php";
require_once __DIR__ . '/inperson_session_schema.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'researcher') {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['user_id'];
$message = '';
sona_ensure_inperson_session_columns($conn);

// Map logged in user to ResearcherID used by Study table
$researcherProfileID = null;
$researcherStmt = $conn->prepare("SELECT ResearcherID FROM Researcher WHERE UserID=? LIMIT 1");
$researcherStmt->bind_param("i", $userID);
$researcherStmt->execute();
$researcherRes = $researcherStmt->get_result();
if ($researcherRow = $researcherRes->fetch_assoc()) {
    $researcherProfileID = (int)$researcherRow['ResearcherID'];
} else {
    $message = "Researcher profile not found. Please contact support.";
}

$studies = [];
if ($researcherProfileID !== null) {
    $stmt = $conn->prepare("SELECT StudyID, StudyTitle, StartDate, Description FROM Study WHERE ResearcherID=? ORDER BY StartDate ASC");
    $stmt->bind_param("i", $researcherProfileID);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $studies[] = $row;
    }
}

$events = [];
foreach($studies as $study){
    $events[] = ['title'=>$study['StudyTitle'], 'start'=>$study['StartDate']];
}
if ($researcherProfileID !== null) {
    $slotStmt = $conn->prepare("
        SELECT s.StudyID, s.StudyTitle, ips.SessionDate, ips.SessionTime, ips.StudentID
        FROM InPersonSession ips
        INNER JOIN Study s ON s.StudyID = ips.StudyID
        WHERE ips.ResearcherID = ?
          AND ips.SessionDate IS NOT NULL
        ORDER BY ips.SessionDate ASC, ips.SessionTime ASC, ips.SessionID ASC
    ");
    if ($slotStmt) {
        $slotStmt->bind_param("i", $researcherProfileID);
        $slotStmt->execute();
        $slotRes = $slotStmt->get_result();
        while ($slot = $slotRes->fetch_assoc()) {
            $start = sona_inperson_slot_start_iso($slot['SessionDate'] ?? null, $slot['SessionTime'] ?? null);
            if ($start === null) {
                continue;
            }
            $claimed = !empty($slot['StudentID']) && (int)$slot['StudentID'] > 0;
            $events[] = [
                'title' => ($claimed ? 'Claimed slot: ' : 'Open slot: ') . $slot['StudyTitle'],
                'start' => $start,
                'url' => 'researcher_inperson_sessions.php?studyID=' . (int)$slot['StudyID'],
                'backgroundColor' => $claimed ? '#1f6fb2' : '#64748b',
                'borderColor' => $claimed ? '#155a90' : '#475569',
            ];
        }
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
:root{--cnu-blue:#003366;--cnu-silver:#E0E0E0;--text-dark:#333;}
body{margin:0;padding:0;font-family:'Inter',sans-serif;background:#f0f2f5;color:var(--text-dark);}
header{
    background:linear-gradient(90deg, #002b55 0%, var(--cnu-blue) 100%);
    padding:1rem 2rem;
    color:white;
    box-shadow:0 4px 14px rgba(0,0,0,0.2);
    font-family:'Crimson Pro', serif;
}
.header-inner{display:flex;justify-content:center;}
.header-title{margin:0;font-size:2rem;font-weight:700;letter-spacing:0.3px;text-align:center;}
.top-tabs{
    display:flex;
    align-items:center;
    gap:10px;
    padding:8px 2rem;
    background:var(--cnu-silver);
    border-top:1px solid #cfd3d8;
    border-bottom:1px solid #cfd3d8;
}
.top-tab-link{
    display:inline-block;
    padding:8px 12px;
    border-radius:6px;
    text-decoration:none;
    color:var(--cnu-blue);
    font-weight:600;
    background:white;
    border:1px solid #c7ccd3;
}
.top-tab-link:hover{background:#f4f6f8;}
.top-tab-link.active{background:var(--cnu-blue);color:white;border-color:var(--cnu-blue);}
.tab-spacer{margin-left:auto;}
.profile-dropdown{position:relative;display:inline-block;}
.profile-dropdown > a{
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
.profile-dropdown > a:hover{background:#f4f6f8;}
.profile-dropdown-content{display:none;position:absolute;right:0;background:white;min-width:180px;box-shadow:0px 8px 16px rgba(0,0,0,0.2);z-index:1;border-radius:6px;}
.profile-dropdown-content a{color:var(--text-dark);padding:12px 16px;text-decoration:none;display:block;}
.profile-dropdown-content a:hover{background:#f1f1f1;}
.profile-dropdown:hover .profile-dropdown-content{display:block;}
.container{display:flex;gap:20px;padding:20px 2rem 0 2rem;margin-bottom:20px;align-items:flex-start;}
#calendar{width:65%;background:white;padding:15px;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.05);}
.right-column{width:35%;display:flex;flex-direction:column;gap:16px;}
.panel{background:white;padding:15px;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.05);}
.studies-panel{overflow-y:auto;max-height:52vh;}
.sidebar-header-link{
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
.sidebar-header-link:hover{background:#002244;}
.message{padding:10px;background:#dff0d8;color:#3c763d;border-radius:4px;margin-bottom:15px;}
.note{color:#4e5c69;margin:8px 0 14px 0;line-height:1.45;}
form.study-action{text-align:center;margin-top:8px;}
form input[type="submit"]{padding:6px 14px;background-color:var(--cnu-blue);color:white;border:none;border-radius:4px;cursor:pointer;margin-top:5px;}
form input[type="submit"]:hover{background:#002244;}
.study-item{margin-bottom:14px;padding:12px;border:1px solid #d9dfe7;border-left:4px solid var(--cnu-blue);border-radius:8px;background:#fafbfd;}
.study-header{display:flex;justify-content:space-between;align-items:baseline;gap:12px;}
.study-title{font-weight:600;color:var(--text-dark);}
.study-date{color:#555;white-space:nowrap;}
.study-description{margin-top:6px;color:#444;}
.action-link{
    display:inline-block;
    text-decoration:none;
    background:var(--cnu-blue);
    color:white;
    padding:8px 14px;
    border-radius:6px;
    font-weight:600;
}
.action-link:hover{background:#002244;}
</style>
</head>
<body>

<header>
    <div class="header-inner">
        <h1 class="header-title">Christopher Newport University - Research Participation System</h1>
    </div>
</header>

<div class="top-tabs">
    <a href="researcher_dashboard.php" class="top-tab-link active">&#8962; Home</a>
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
    <div id="calendar"></div>

    <div class="right-column">
        <div class="panel">
            <a href="new_study.php" class="sidebar-header-link">Create New Study</a>
            <?php if($message) echo "<div class='message'>$message</div>"; ?>
            <p class="note">Create and publish a new study.</p>
        </div>

        <div class="panel studies-panel" id="your-studies">
            <a href="researcher_studies.php" class="sidebar-header-link">Your Studies</a>
            <?php if (count($studies) === 0): ?>
                <p class="note">No studies created yet.</p>
            <?php else: ?>
                <?php foreach(array_slice($studies, 0, 5) as $study): ?>
                    <div class="study-item">
                        <div class="study-header">
                            <span class="study-title"><?php echo htmlspecialchars($study['StudyTitle']); ?></span>
                            <span class="study-date"><?php echo htmlspecialchars(date('M j, Y', strtotime($study['StartDate']))); ?></span>
                        </div>
                        <div class="study-description"><?php echo htmlspecialchars($study['Description'] ?? ''); ?></div>
                    </div>
                <?php endforeach; ?>
                <div class="study-action">
                    <a href="researcher_studies.php" class="action-link">Manage All Studies</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView:'dayGridMonth',
        events: <?php echo $events_json; ?>,
        height: 'auto'
    });
    calendar.render();
});
</script>

</body>
</html>