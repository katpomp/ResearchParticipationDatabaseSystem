<?php
session_start();
include "db_connect.php";
require_once __DIR__ . '/study_session_schema.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'researcher') {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['user_id'];
$message = '';
$error = '';

sona_ensure_study_session_columns($conn);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $researcherProfileID !== null) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'Open');
    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';
    $sessionMode = $_POST['session_mode'] ?? 'in_person';
    if (!in_array($sessionMode, ['online', 'in_person'], true)) {
        $sessionMode = 'in_person';
    }

    if ($title === '' || $startDate === '') {
        $error = "Study title and start date are required.";
    }

    $onlineUrl = null;
    $building = null;
    $room = null;

    if ($error === '' && $sessionMode === 'online') {
        $onlineUrl = sona_normalize_online_meeting_url($_POST['online_meeting_url'] ?? '');
        if ($onlineUrl === null) {
            $error = "Enter a valid http(s) URL for the online study (e.g. a Zoom or Teams link).";
        }
    } elseif ($error === '') {
        $building = trim($_POST['building_name'] ?? '');
        $room = trim($_POST['room_number'] ?? '');
        if ($building === '' || $room === '') {
            $error = "Building name and room number are required for in-person studies.";
        }
    }

    if ($error === '') {
        $endDateParam = $endDate !== '' ? $endDate : null;
        $urlParam = $sessionMode === 'online' ? $onlineUrl : null;
        $buildParam = $sessionMode === 'in_person' ? $building : null;
        $roomParam = $sessionMode === 'in_person' ? $room : null;

        $stmt = $conn->prepare("
            INSERT INTO Study (StudyTitle, Description, Status, StartDate, EndDate, ResearcherID, SessionMode, OnlineMeetingURL, BuildingName, RoomNumber)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssissss",
            $title,
            $description,
            $status,
            $startDate,
            $endDateParam,
            $researcherProfileID,
            $sessionMode,
            $urlParam,
            $buildParam,
            $roomParam
        );
        if ($stmt->execute()) {
            $message = "Study created successfully.";
        } else {
            $error = "Error creating study: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create New Study - Research Participation System</title>
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
.container { max-width:900px; margin:20px auto; padding:0 2rem; }
.panel { background:white; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.05); padding:16px; }
.panel-title { display:block; margin-bottom:16px; padding:10px 12px; border-radius:8px; font-family:'Crimson Pro', serif; font-size:1.5em; color:white; background:var(--cnu-blue); text-align:center; text-decoration:none; }
.form-card { border:1px solid #d9dfe7; border-left:4px solid var(--cnu-blue); border-radius:8px; background:#fafbfd; padding:14px; }
label { display:block; margin-top:10px; margin-bottom:6px; font-weight:600; color:#41505d; }
input[type="text"], input[type="date"], textarea, select { width:100%; border:1px solid #cfd8e2; border-radius:6px; padding:10px; font-family:'Inter',sans-serif; box-sizing:border-box; }
textarea { min-height:110px; resize:vertical; }
.button-row { text-align:center; margin-top:16px; }
input[type="submit"] { padding:10px 16px; background:var(--cnu-blue); color:white; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
input[type="submit"]:hover { background:#002244; }
.message { padding:10px; border-radius:6px; margin-bottom:12px; }
.ok { background:#dff0d8; color:#3c763d; }
.err { background:#f8d7da; color:#842029; }
.session-fieldset { border:1px solid #cfd8e2; border-radius:8px; padding:12px 14px; margin-top:14px; background:#fff; }
.session-fieldset legend { font-weight:700; color:#41505d; padding:0 6px; }
.mode-row { display:flex; flex-wrap:wrap; gap:16px; margin-bottom:10px; }
.mode-row label { display:inline-flex; align-items:center; gap:8px; margin-top:0; font-weight:500; cursor:pointer; }
.session-block { margin-top:8px; }
.session-block[hidden] { display:none !important; }
.hint-sm { font-size:0.88rem; color:#6b7280; font-weight:400; margin-top:4px; }
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
    <a href="new_study.php" class="top-tab-link active">Create New Study</a>
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
    <div class="panel">
        <span class="panel-title">Create New Study</span>
        <?php if ($message !== ''): ?><div class="message ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="message err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="form-card">
            <form method="post">
                <label for="title">Study Title</label>
                <input id="title" type="text" name="title" required>

                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Describe the study and participant expectations..."></textarea>

                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="Open">Open</option>
                    <option value="Closed">Closed</option>
                    <option value="Draft">Draft</option>
                </select>

                <label for="startDate">Start Date</label>
                <input id="startDate" type="date" name="startDate" required>

                <label for="endDate">End Date (optional)</label>
                <input id="endDate" type="date" name="endDate">

                <fieldset class="session-fieldset">
                    <legend>Session format</legend>
                    <div class="mode-row">
                        <label><input type="radio" name="session_mode" value="in_person" checked id="mode_inperson"> In person</label>
                        <label><input type="radio" name="session_mode" value="online" id="mode_online"> Online</label>
                    </div>
                    <div id="block_inperson" class="session-block">
                        <label for="building_name">Building name</label>
                        <input id="building_name" type="text" name="building_name" placeholder="e.g. Luter Hall">
                        <span class="hint-sm">Students will see this after they sign up.</span>
                        <label for="room_number">Room number</label>
                        <input id="room_number" type="text" name="room_number" placeholder="e.g. 201">
                    </div>
                    <div id="block_online" class="session-block" hidden>
                        <label for="online_meeting_url">Study / meeting URL</label>
                        <input id="online_meeting_url" type="url" name="online_meeting_url" placeholder="https://...">
                        <span class="hint-sm">Enter the link students will use to participate. Shown after sign-up.</span>
                    </div>
                </fieldset>

                <div class="button-row">
                    <input type="submit" value="Create Study">
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    var rIn = document.getElementById('mode_inperson');
    var rOn = document.getElementById('mode_online');
    var bIn = document.getElementById('block_inperson');
    var bOn = document.getElementById('block_online');
    var building = document.getElementById('building_name');
    var room = document.getElementById('room_number');
    var url = document.getElementById('online_meeting_url');
    function sync() {
        var online = rOn && rOn.checked;
        if (bIn) bIn.hidden = online;
        if (bOn) bOn.hidden = !online;
        if (building) building.required = !online;
        if (room) room.required = !online;
        if (url) url.required = online;
    }
    if (rIn) rIn.addEventListener('change', sync);
    if (rOn) rOn.addEventListener('change', sync);
    sync();
})();
</script>
</body>
</html>
