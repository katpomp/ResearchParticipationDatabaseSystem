<?php
session_start();
include "db_connect.php";
require_once __DIR__ . '/study_session_schema.php';
require_once __DIR__ . '/inperson_session_schema.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'researcher') {
    header("Location: login.php");
    exit();
}

$userID = (int)$_SESSION['user_id'];
$message = '';
$error = '';

sona_ensure_study_session_columns($conn);
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
    $error = "Researcher profile not found. Please contact support.";
}

$studyID = isset($_REQUEST['studyID']) ? (int)$_REQUEST['studyID'] : 0;
$study = null;
if ($researcherProfileID !== null && $studyID > 0) {
    $load = $conn->prepare("SELECT StudyID, StudyTitle, SessionMode, BuildingName, RoomNumber FROM Study WHERE StudyID=? AND ResearcherID=? LIMIT 1");
    $load->bind_param("ii", $studyID, $researcherProfileID);
    $load->execute();
    $study = $load->get_result()->fetch_assoc();
    if ($study && (($study['SessionMode'] ?? 'in_person') === 'online')) {
        $error = "This study is an online study. Time slots are only for in-person studies.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $researcherProfileID !== null && $study && $error === '') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        $date = (string)($_POST['session_date'] ?? '');
        $time = (string)($_POST['session_time'] ?? '');
        $duration = (int)($_POST['duration'] ?? 0);
        $building = trim((string)($_POST['building_name'] ?? ''));
        $room = trim((string)($_POST['room_number'] ?? ''));

        if ($date === '' || $time === '') {
            $error = "Session date and time are required.";
        } elseif ($building === '' || $room === '') {
            $error = "Building name and room number are required for in-person time slots.";
        }

        if ($error === '') {
            // Ensure the Location row exists for FK.
            $loc = $conn->prepare("INSERT INTO Location (BuildingName, RoomNumber) VALUES (?, ?) ON DUPLICATE KEY UPDATE BuildingName=BuildingName");
            if ($loc) {
                $loc->bind_param("ss", $building, $room);
                $loc->execute();
            }

            $ins = $conn->prepare("
                INSERT INTO InPersonSession (SessionDate, SessionTime, Duration, AttendanceStatus, StudyID, StudentID, ResearcherID, BuildingName, RoomNumber)
                VALUES (?, ?, ?, 'open', ?, NULL, ?, ?, ?)
            ");
            if ($ins) {
                $ins->bind_param("ssiiiss", $date, $time, $duration, $studyID, $researcherProfileID, $building, $room);
                if ($ins->execute()) {
                    $message = "Time slot created.";
                } else {
                    $error = "Could not create time slot: " . $ins->error;
                }
            } else {
                $error = "Could not prepare create statement.";
            }
        }
    } elseif ($action === 'delete') {
        $sessionID = (int)($_POST['sessionID'] ?? 0);
        if ($sessionID > 0) {
            $del = $conn->prepare("DELETE FROM InPersonSession WHERE SessionID=? AND StudyID=? AND ResearcherID=? AND StudentID IS NULL");
            if ($del) {
                $del->bind_param("iii", $sessionID, $studyID, $researcherProfileID);
                if ($del->execute()) {
                    $message = $del->affected_rows > 0 ? "Time slot deleted." : "Only unclaimed time slots can be deleted.";
                } else {
                    $error = "Could not delete time slot: " . $del->error;
                }
            }
        }
    } elseif ($action === 'unassign') {
        $sessionID = (int)($_POST['sessionID'] ?? 0);
        if ($sessionID > 0) {
            $upd = $conn->prepare("UPDATE InPersonSession SET StudentID=NULL, AttendanceStatus='open' WHERE SessionID=? AND StudyID=? AND ResearcherID=?");
            if ($upd) {
                $upd->bind_param("iii", $sessionID, $studyID, $researcherProfileID);
                if ($upd->execute()) {
                    $message = $upd->affected_rows > 0 ? "Slot unassigned." : "No changes were made.";
                } else {
                    $error = "Could not unassign: " . $upd->error;
                }
            }
        }
    }
}

$slots = [];
if ($study && $error === '') {
    $q = $conn->prepare("
        SELECT ips.SessionID, ips.SessionDate, ips.SessionTime, ips.Duration, ips.AttendanceStatus,
               ips.BuildingName, ips.RoomNumber,
               COALESCE(u.email, st.Email) AS StudentEmail
        FROM InPersonSession ips
        LEFT JOIN Student st ON st.StudentID = ips.StudentID
        LEFT JOIN users u ON u.id = st.UserID
        WHERE ips.StudyID = ? AND ips.ResearcherID = ?
        ORDER BY ips.SessionDate ASC, ips.SessionTime ASC, ips.SessionID ASC
    ");
    if ($q) {
        $q->bind_param("ii", $studyID, $researcherProfileID);
        $q->execute();
        $res = $q->get_result();
        while ($row = $res->fetch_assoc()) {
            $slots[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Time slots - Research Participation System</title>
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
.profile-dropdown-content { display:none; position:absolute; right:0; background:white; min-width:180px; box-shadow:0 8px 16px rgba(0,0,0,0.2); z-index:2; border-radius:6px; }
.profile-dropdown-content a { color:var(--text-dark); padding:12px 16px; text-decoration:none; display:block; }
.profile-dropdown-content a:hover { background:#f1f1f1; }
.profile-dropdown:hover .profile-dropdown-content { display:block; }

.container { max-width:980px; margin:20px auto; padding:0 2rem; }
.panel { background:white; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.05); padding:16px; }
.panel-title { display:block; margin-bottom:16px; padding:10px 12px; border-radius:8px; font-family:'Crimson Pro', serif; font-size:1.5em; color:white; background:var(--cnu-blue); text-align:center; text-decoration:none; }
.message { padding:10px; border-radius:6px; margin-bottom:12px; }
.ok { background:#dff0d8; color:#3c763d; }
.err { background:#f8d7da; color:#842029; }
.hint { color:#4e5c69; margin:8px 0 14px 0; line-height:1.45; }

.grid { display:grid; grid-template-columns: 1fr 1fr; gap:14px; }
@media (max-width: 860px) { .grid { grid-template-columns: 1fr; } }
.card { border:1px solid #d9dfe7; border-left:4px solid var(--cnu-blue); border-radius:8px; background:#fafbfd; padding:14px; }
label { display:block; margin-top:10px; margin-bottom:6px; font-weight:600; color:#41505d; }
input[type="text"], input[type="date"], input[type="time"], input[type="number"] { width:100%; border:1px solid #cfd8e2; border-radius:6px; padding:10px; font-family:'Inter',sans-serif; box-sizing:border-box; }
.button-row { display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-start; align-items:center; margin-top:12px; }
.btn { padding:8px 14px; border-radius:6px; font-weight:600; border:1px solid var(--cnu-blue); background:var(--cnu-blue); color:white; cursor:pointer; }
.btn:hover { background:#002244; border-color:#002244; }
.btn-secondary { background:white; color:var(--cnu-blue); }
.btn-secondary:hover { background:#f0f5fa; border-color:var(--cnu-blue); color:var(--cnu-blue); }
.btn-danger { background:#fff; border-color:#b42318; color:#b42318; }
.btn-danger:hover { background:#fde8e8; }

.slot-row { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px; padding:10px 0; border-top:1px solid #e6ebf2; }
.slot-row:first-child { border-top:none; padding-top:0; }
.slot-main { display:flex; flex-direction:column; gap:2px; }
.slot-title { font-weight:700; color:#1f2d3a; }
.slot-sub { color:#4e5c69; font-size:0.92rem; }
.badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:0.85rem; font-weight:700; background:#e8eef5; color:var(--cnu-blue); }
.badge.taken { background:#fff7ed; color:#9a3412; }
.badge.open { background:#e6f4ea; color:#2f6f39; }
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
        <span class="panel-title">In-person time slots</span>
        <?php if ($message !== ''): ?><div class="message ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="message err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if (!$study): ?>
            <p class="hint">Choose an in-person study from <a href="researcher_studies.php">Your Studies</a> and click Time slots.</p>
        <?php elseif ($error !== ''): ?>
            <p class="hint"><a href="researcher_studies.php">&larr; Back to studies</a></p>
        <?php else: ?>
            <p class="hint"><strong>Study:</strong> <?php echo htmlspecialchars($study['StudyTitle']); ?>. Create time slots below; students will sign up for a specific slot.</p>

            <div class="grid">
                <div class="card">
                    <div class="slot-title">Create a new slot</div>
                    <form method="post">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="studyID" value="<?php echo (int)$study['StudyID']; ?>">

                        <label for="session_date">Date</label>
                        <input id="session_date" type="date" name="session_date" required>

                        <label for="session_time">Time</label>
                        <input id="session_time" type="time" name="session_time" required>

                        <label for="duration">Duration (minutes)</label>
                        <input id="duration" type="number" min="0" step="1" name="duration" value="0">

                        <label for="building_name">Building</label>
                        <input id="building_name" type="text" name="building_name" required value="<?php echo htmlspecialchars((string)($study['BuildingName'] ?? '')); ?>">

                        <label for="room_number">Room</label>
                        <input id="room_number" type="text" name="room_number" required value="<?php echo htmlspecialchars((string)($study['RoomNumber'] ?? '')); ?>">

                        <div class="button-row">
                            <button class="btn" type="submit">Add slot</button>
                            <a class="btn btn-secondary" href="edit_study.php?studyID=<?php echo (int)$study['StudyID']; ?>">Edit study</a>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="slot-title">Existing slots</div>
                    <?php if (count($slots) === 0): ?>
                        <p class="hint" style="margin:10px 0 0 0;">No slots yet.</p>
                    <?php else: ?>
                        <?php foreach ($slots as $s): ?>
                            <?php
                            $d = $s['SessionDate'] ? date('D, M j, Y', strtotime($s['SessionDate'])) : '—';
                            $t = '—';
                            if (!empty($s['SessionTime'])) {
                                $ts = strtotime('1970-01-01 ' . $s['SessionTime']);
                                $t = $ts ? date('g:i A', $ts) : (string)$s['SessionTime'];
                            }
                            $loc = trim((string)($s['BuildingName'] ?? '') . ((string)($s['BuildingName'] ?? '') !== '' && (string)($s['RoomNumber'] ?? '') !== '' ? ', ' : '') . (string)($s['RoomNumber'] ?? ''));
                            $taken = !empty($s['StudentEmail']);
                            ?>
                            <div class="slot-row">
                                <div class="slot-main">
                                    <div class="slot-title">
                                        <?php echo htmlspecialchars($d . ' at ' . $t); ?>
                                        <?php if ($taken): ?>
                                            <span class="badge taken">claimed</span>
                                        <?php else: ?>
                                            <span class="badge open">open</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="slot-sub">
                                        <?php if ($loc !== ''): ?><?php echo htmlspecialchars($loc); ?> · <?php endif; ?>
                                        <?php if (!empty($s['Duration'])): ?><?php echo htmlspecialchars((string)(int)$s['Duration']); ?> min · <?php endif; ?>
                                        <?php if ($taken): ?>Student: <?php echo htmlspecialchars((string)$s['StudentEmail']); ?><?php else: ?>No student yet<?php endif; ?>
                                    </div>
                                </div>
                                <div class="button-row" style="margin:0; justify-content:flex-end;">
                                    <?php if ($taken): ?>
                                        <form method="post" style="margin:0;">
                                            <input type="hidden" name="action" value="unassign">
                                            <input type="hidden" name="studyID" value="<?php echo (int)$study['StudyID']; ?>">
                                            <input type="hidden" name="sessionID" value="<?php echo (int)$s['SessionID']; ?>">
                                            <button class="btn btn-secondary" type="submit" onclick="return confirm('Unassign this slot from the student?');">Unassign</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" style="margin:0;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="studyID" value="<?php echo (int)$study['StudyID']; ?>">
                                            <input type="hidden" name="sessionID" value="<?php echo (int)$s['SessionID']; ?>">
                                            <button class="btn btn-danger" type="submit" onclick="return confirm('Delete this unclaimed time slot?');">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <p class="hint" style="margin-top:14px;"><a href="researcher_studies.php">&larr; Back to studies</a></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

