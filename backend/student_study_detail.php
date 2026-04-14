<?php
session_start();
include "db_connect.php";
require_once __DIR__ . '/study_participation_schema.php';
require_once __DIR__ . '/study_session_schema.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

$studentID = (int)$_SESSION['user_id'];
sona_ensure_participation_status_columns($conn);
sona_ensure_study_session_columns($conn);

$studyID = isset($_GET['studyID']) ? (int)$_GET['studyID'] : 0;
$justSignedUp = isset($_GET['new']) && $_GET['new'] === '1';

$signupFlash = '';
if (!empty($_SESSION['signup_study_flash'])) {
    $signupFlash = (string)$_SESSION['signup_study_flash'];
    unset($_SESSION['signup_study_flash']);
}

$study = null;
$error = '';

if ($studyID <= 0) {
    $error = 'Invalid study.';
} else {
    $stmt = $conn->prepare("
        SELECT s.StudyID, s.StudyTitle, s.Description, s.Status, s.StartDate, s.EndDate,
               s.SessionMode, s.OnlineMeetingURL, s.BuildingName, s.RoomNumber,
               sp.ParticipationStatus
        FROM StudyParticipant sp
        JOIN Study s ON s.StudyID = sp.StudyID
        WHERE sp.StudentID = ? AND s.StudyID = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $studentID, $studyID);
    $stmt->execute();
    $study = $stmt->get_result()->fetch_assoc();
    if (!$study) {
        $error = 'You are not signed up for this study, or it could not be found.';
    }
}

$safeUrl = null;
if ($study && (($study['SessionMode'] ?? '') === 'online')) {
    $safeUrl = sona_safe_http_url_for_href($study['OnlineMeetingURL'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Study details - Research Participation System</title>
<link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
:root { --cnu-blue: #003366; --cnu-silver: #E0E0E0; --text-dark: #333; }
body { margin:0; padding:0; font-family:'Inter',sans-serif; background:#f0f2f5; color:var(--text-dark); }
header {
    background:linear-gradient(90deg, #002b55 0%, var(--cnu-blue) 100%);
    padding:1rem 2rem; color:white; box-shadow:0 4px 14px rgba(0,0,0,0.2);
    font-family:'Crimson Pro', serif;
}
.header-inner { display:flex; justify-content:center; }
.header-title { margin:0; font-size:1.75rem; font-weight:700; text-align:center; }
.top-tabs {
    display:flex; align-items:center; gap:10px; padding:8px 2rem;
    background:var(--cnu-silver); border-bottom:1px solid #cfd3d8; flex-wrap:wrap;
}
.top-tab-link {
    display:inline-block; padding:8px 12px; border-radius:6px; text-decoration:none;
    color:var(--cnu-blue); font-weight:600; background:white; border:1px solid #c7ccd3;
}
.top-tab-link:hover { background:#f4f6f8; }
.tab-spacer { margin-left:auto; }
.profile-dropdown { position:relative; display:inline-block; }
.profile-dropdown > a {
    display:inline-block; padding:8px 12px; border-radius:6px; text-decoration:none;
    font-weight:600; font-size:0.95rem; color:var(--cnu-blue); background:white; border:1px solid #c7ccd3;
}
.profile-dropdown > a:hover { background:#f4f6f8; }
.profile-dropdown-content {
    display:none; position:absolute; right:0; background:white; min-width:180px;
    box-shadow:0 8px 16px rgba(0,0,0,0.2); z-index:2; border-radius:6px;
}
.profile-dropdown-content a { color:var(--text-dark); padding:12px 16px; text-decoration:none; display:block; }
.profile-dropdown-content a:hover { background:#f1f1f1; }
.profile-dropdown:hover .profile-dropdown-content { display:block; }
.container { max-width:640px; margin:24px auto; padding:0 20px; }
.panel {
    background:white; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.06);
    padding:22px 24px; border-top:4px solid var(--cnu-blue);
}
h1 { margin:0 0 8px 0; color:var(--cnu-blue); font-family:'Crimson Pro', serif; font-size:1.65rem; }
.banner {
    background:#e6f4ea; border:1px solid #b7dfb9; color:#2f6f39;
    padding:10px 12px; border-radius:6px; margin-bottom:16px; font-size:0.95rem;
}
.err { background:#fde8e8; border:1px solid #f8b4b4; color:#b42318; padding:12px; border-radius:6px; }
.meta { color:#4e5c69; font-size:0.95rem; margin-bottom:12px; line-height:1.5; }
.desc { line-height:1.55; color:#333; margin:14px 0; white-space:pre-wrap; }
.section-title {
    font-size:0.8rem; text-transform:uppercase; letter-spacing:0.06em;
    color:#5a6673; margin:20px 0 8px 0; font-weight:700;
}
.session-badge {
    display:inline-block; padding:4px 10px; border-radius:999px; font-size:0.85rem;
    font-weight:700; background:#e8eef5; color:var(--cnu-blue); margin-bottom:8px;
}
.join-link {
    display:inline-block; margin-top:8px; padding:12px 18px; background:var(--cnu-blue);
    color:white !important; text-decoration:none; border-radius:8px; font-weight:600;
}
.join-link:hover { background:#002244; }
.location-box {
    background:#f8fafc; border:1px solid #dce4ee; border-radius:8px; padding:14px 16px;
    font-size:1.05rem; line-height:1.5;
}
.back { margin-top:20px; }
.back a { color:var(--cnu-blue); font-weight:600; text-decoration:none; }
.back a:hover { text-decoration:underline; }
.status-note { font-size:0.92rem; color:#5a6673; margin-top:12px; }
</style>
</head>
<body>
<header>
    <div class="header-inner">
        <h1 class="header-title">Christopher Newport University — Research Participation System</h1>
    </div>
</header>
<div class="top-tabs">
    <a href="student_dashboard.php" class="top-tab-link">&#8962; Home</a>
    <a href="student_studies.php" class="top-tab-link">Studies</a>
    <a href="view_credits.php" class="top-tab-link">My Schedule/Credits</a>
    <div class="tab-spacer"></div>
    <div class="profile-dropdown">
        <a href="#"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></a>
        <div class="profile-dropdown-content">
            <a href="redeem_role_code.php">Role invitation (code)</a>
            <a href="edit_profile.php">Edit Profile</a>
            <a href="change_password.php">Change Password</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="container">
    <?php if ($error !== ''): ?>
        <div class="panel">
            <div class="err"><?php echo htmlspecialchars($error); ?></div>
            <p class="back"><a href="student_studies.php">&larr; Back to studies</a></p>
        </div>
    <?php else: ?>
        <div class="panel">
            <?php if ($signupFlash !== ''): ?>
                <div class="banner"><?php echo htmlspecialchars($signupFlash); ?> Open the Research Participation System any time to return to this page from <strong>Studies</strong> or your home dashboard.</div>
            <?php elseif ($justSignedUp): ?>
                <div class="banner">You are signed up for this study. Save this page or bookmark it for easy access.</div>
            <?php endif; ?>

            <h1><?php echo htmlspecialchars($study['StudyTitle']); ?></h1>

            <?php
            $mode = $study['SessionMode'] ?? 'in_person';
            $modeLabel = $mode === 'online' ? 'Online session' : 'In-person session';
            ?>
            <span class="session-badge"><?php echo htmlspecialchars($modeLabel); ?></span>

            <div class="meta">
                <strong>Start:</strong> <?php echo htmlspecialchars(date('l, F j, Y', strtotime($study['StartDate']))); ?>
                <?php if (!empty($study['EndDate'])): ?>
                    <br><strong>End:</strong> <?php echo htmlspecialchars(date('l, F j, Y', strtotime($study['EndDate']))); ?>
                <?php endif; ?>
                <br><strong>Status:</strong> <?php echo htmlspecialchars($study['Status'] ?? 'Open'); ?>
            </div>

            <?php if (trim((string)($study['Description'] ?? '')) !== ''): ?>
                <div class="desc"><?php echo htmlspecialchars($study['Description']); ?></div>
            <?php endif; ?>

            <?php if ($mode === 'online'): ?>
                <div class="section-title">Join this study</div>
                <?php if ($safeUrl !== null): ?>
                    <p>Open the link your researcher provided to take part (opens in a new tab).</p>
                    <a class="join-link" href="<?php echo htmlspecialchars($safeUrl); ?>" target="_blank" rel="noopener noreferrer">Open study link</a>
                    <p class="status-note" style="word-break:break-all;">URL: <?php echo htmlspecialchars($safeUrl); ?></p>
                <?php else: ?>
                    <p class="status-note">Your researcher has not added a meeting link yet. Check back later or contact them.</p>
                <?php endif; ?>
            <?php else: ?>
                <div class="section-title">Location</div>
                <div class="location-box">
                    <?php
                    $b = trim((string)($study['BuildingName'] ?? ''));
                    $r = trim((string)($study['RoomNumber'] ?? ''));
                    ?>
                    <?php if ($b !== '' || $r !== ''): ?>
                        <?php if ($b !== ''): ?>
                            <strong>Building:</strong> <?php echo htmlspecialchars($b); ?><br>
                        <?php endif; ?>
                        <?php if ($r !== ''): ?>
                            <strong>Room:</strong> <?php echo htmlspecialchars($r); ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="status-note" style="margin:0;">Location details have not been posted yet. Watch your email or contact the researcher.</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php
            $ps = $study['ParticipationStatus'] ?? 'pending';
            if ($ps === 'pending'): ?>
                <p class="status-note">Your participation is <strong>pending</strong> until the researcher confirms attendance.</p>
            <?php elseif ($ps === 'completed'): ?>
                <p class="status-note">This study is marked <strong>completed</strong> on your record.</p>
            <?php elseif ($ps === 'no_show'): ?>
                <p class="status-note">This study was recorded as a <strong>no-show</strong>.</p>
            <?php endif; ?>

            <p class="back"><a href="student_studies.php">&larr; Back to studies</a></p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
