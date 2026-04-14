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

$studyID = isset($_REQUEST['studyID']) ? (int)$_REQUEST['studyID'] : 0;
$study = null;

if ($researcherProfileID !== null && $studyID > 0) {
    $load = $conn->prepare("SELECT StudyID, StudyTitle, Description, Status, StartDate, EndDate, SessionMode, OnlineMeetingURL, BuildingName, RoomNumber FROM Study WHERE StudyID=? AND ResearcherID=? LIMIT 1");
    $load->bind_param("ii", $studyID, $researcherProfileID);
    $load->execute();
    $study = $load->get_result()->fetch_assoc();
}

if ($researcherProfileID !== null && $studyID > 0 && $study === null && $error === '') {
    $error = "Study not found or you do not have permission to edit it.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $researcherProfileID !== null && $study !== null) {
    $postStudyID = (int)($_POST['studyID'] ?? 0);
    if ($postStudyID !== (int)$study['StudyID']) {
        $error = "Invalid study.";
    } else {
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
        }

        if ($error === '') {
            $endDateParam = $endDate !== '' ? $endDate : null;
            $urlParam = $sessionMode === 'online' ? $onlineUrl : null;
            $buildParam = $sessionMode === 'in_person' ? $building : null;
            $roomParam = $sessionMode === 'in_person' ? $room : null;

            $stmt = $conn->prepare("
                UPDATE Study SET StudyTitle=?, Description=?, Status=?, StartDate=?, EndDate=?,
                    SessionMode=?, OnlineMeetingURL=?, BuildingName=?, RoomNumber=?
                WHERE StudyID=? AND ResearcherID=?
            ");
            $stmt->bind_param(
                "sssssssssii",
                $title,
                $description,
                $status,
                $startDate,
                $endDateParam,
                $sessionMode,
                $urlParam,
                $buildParam,
                $roomParam,
                $postStudyID,
                $researcherProfileID
            );
            if ($stmt->execute()) {
                $message = "Study updated successfully.";
                $study['StudyTitle'] = $title;
                $study['Description'] = $description;
                $study['Status'] = $status;
                $study['StartDate'] = $startDate;
                $study['EndDate'] = $endDateParam;
                $study['SessionMode'] = $sessionMode;
                $study['OnlineMeetingURL'] = $urlParam;
                $study['BuildingName'] = $buildParam;
                $study['RoomNumber'] = $roomParam;
            } else {
                $error = "Error updating study: " . $stmt->error;
            }
        }
    }
}

function study_date_input_value(?string $dbDate): string
{
    if ($dbDate === null || $dbDate === '') {
        return '';
    }
    $ts = strtotime($dbDate);
    return $ts ? date('Y-m-d', $ts) : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Study - Research Participation System</title>
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
.button-row { text-align:center; margin-top:16px; display:flex; flex-wrap:wrap; gap:10px; justify-content:center; align-items:stretch; }
.button-row input[type="submit"],
.button-row .cancel-link {
    box-sizing:border-box;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:42px;
    padding:10px 16px;
    border-radius:6px;
    font-family:'Inter',sans-serif;
    font-size:0.95rem;
    font-weight:600;
    line-height:1.25;
    cursor:pointer;
    text-decoration:none;
}
.button-row input[type="submit"] {
    background:var(--cnu-blue);
    color:white;
    border:1px solid var(--cnu-blue);
}
.button-row input[type="submit"]:hover { background:#002244; border-color:#002244; }
.button-row .cancel-link { background:#eef1f5; color:#1f2d3a; border:1px solid #d2d8e0; }
.button-row .cancel-link:hover { background:#e5e9ef; }
.message { padding:10px; border-radius:6px; margin-bottom:12px; }
.ok { background:#dff0d8; color:#3c763d; }
.err { background:#f8d7da; color:#842029; }
.hint { color:#5a6673; font-size:0.92rem; margin-bottom:12px; line-height:1.45; }
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
        <span class="panel-title">Edit Study</span>
        <?php if ($message !== ''): ?><div class="message ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="message err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if ($study === null): ?>
            <p class="hint">Choose a study from <a href="researcher_studies.php">Your Studies</a> and click Edit.</p>
        <?php else: ?>
            <p class="hint">Update details for this study. Students who already signed up keep their enrollment; consider emailing them if dates change significantly.</p>
            <div class="form-card">
                <form method="post" action="edit_study.php">
                    <input type="hidden" name="studyID" value="<?php echo (int)$study['StudyID']; ?>">

                    <label for="title">Study Title</label>
                    <input id="title" type="text" name="title" required value="<?php echo htmlspecialchars($study['StudyTitle']); ?>">

                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Describe the study and participant expectations..."><?php echo htmlspecialchars($study['Description'] ?? ''); ?></textarea>

                    <label for="status">Status</label>
                    <?php
                    $curStatus = $study['Status'] ?? 'Open';
                    $knownStatuses = ['Open', 'Closed', 'Draft'];
                    ?>
                    <select id="status" name="status">
                        <?php if (!in_array($curStatus, $knownStatuses, true)): ?>
                            <option value="<?php echo htmlspecialchars($curStatus); ?>" selected><?php echo htmlspecialchars($curStatus); ?></option>
                        <?php endif; ?>
                        <option value="Open"<?php echo $curStatus === 'Open' ? ' selected' : ''; ?>>Open</option>
                        <option value="Closed"<?php echo $curStatus === 'Closed' ? ' selected' : ''; ?>>Closed</option>
                        <option value="Draft"<?php echo $curStatus === 'Draft' ? ' selected' : ''; ?>>Draft</option>
                    </select>

                    <label for="startDate">Start Date</label>
                    <input id="startDate" type="date" name="startDate" required value="<?php echo htmlspecialchars(study_date_input_value($study['StartDate'] ?? null)); ?>">

                    <label for="endDate">End Date (optional)</label>
                    <input id="endDate" type="date" name="endDate" value="<?php echo htmlspecialchars(study_date_input_value($study['EndDate'] ?? null)); ?>">

                    <?php
                    $curMode = ($study['SessionMode'] ?? 'in_person') === 'online' ? 'online' : 'in_person';
                    $inPersonChecked = $curMode === 'in_person';
                    ?>
                    <fieldset class="session-fieldset">
                        <legend>Session format</legend>
                        <div class="mode-row">
                            <label><input type="radio" name="session_mode" value="in_person" id="mode_inperson"<?php echo $inPersonChecked ? ' checked' : ''; ?>> In person</label>
                            <label><input type="radio" name="session_mode" value="online" id="mode_online"<?php echo !$inPersonChecked ? ' checked' : ''; ?>> Online</label>
                        </div>
                        <div id="block_inperson" class="session-block">
                            <label for="building_name">Building name</label>
                            <input id="building_name" type="text" name="building_name" value="<?php echo htmlspecialchars($study['BuildingName'] ?? ''); ?>" placeholder="e.g. Luter Hall">
                            <label for="room_number">Room number</label>
                            <input id="room_number" type="text" name="room_number" value="<?php echo htmlspecialchars($study['RoomNumber'] ?? ''); ?>" placeholder="e.g. 201">
                        </div>
                        <div id="block_online" class="session-block"<?php echo $inPersonChecked ? ' hidden' : ''; ?>>
                            <label for="online_meeting_url">Study / meeting URL</label>
                            <input id="online_meeting_url" type="url" name="online_meeting_url" value="<?php echo htmlspecialchars($study['OnlineMeetingURL'] ?? ''); ?>" placeholder="https://...">
                            <span class="hint-sm">Students signed up for this study see this link on their study details page.</span>
                        </div>
                    </fieldset>

                    <div class="button-row">
                        <input type="submit" value="Save changes">
                        <a class="cancel-link" href="researcher_studies.php">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
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
        if (building) building.required = false;
        if (room) room.required = false;
        if (url) url.required = online;
    }
    if (rIn) rIn.addEventListener('change', sync);
    if (rOn) rOn.addEventListener('change', sync);
    sync();
})();
</script>
</body>
</html>
