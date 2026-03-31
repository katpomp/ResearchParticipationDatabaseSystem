<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: login.php");
    exit();
}

$researchers = [];
$students = [];

$researcherRes = $conn->query("SELECT FirstName, LastName, Email FROM Researcher ORDER BY LastName ASC, FirstName ASC");
while ($row = $researcherRes->fetch_assoc()) {
    $researchers[] = $row;
}

$studentRes = $conn->query("SELECT FirstName, LastName, Email FROM Student ORDER BY LastName ASC, FirstName ASC");
while ($row = $studentRes->fetch_assoc()) {
    $students[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Researchers & Students - Research Participation System</title>
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
.container { padding:20px 2rem; display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.panel { background:white; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.05); padding:15px; }
.panel-title { display:block; margin-bottom:16px; padding:10px 12px; border-radius:8px; font-family:'Crimson Pro', serif; font-size:1.5em; color:white; background:var(--cnu-blue); text-align:center; }
.person-card { margin-bottom:12px; padding:10px; border:1px solid #d9dfe7; border-left:4px solid var(--cnu-blue); border-radius:8px; background:#fafbfd; }
.person-name { font-weight:700; color:#2f3b46; }
.person-email { color:#55626e; font-size:0.92rem; }
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
    <a href="faculty_studies.php" class="top-tab-link">All Studies & Signups</a>
    <a href="faculty_directory.php" class="top-tab-link active">Researchers & Students</a>
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
        <span class="panel-title">Researchers</span>
        <?php if (count($researchers) === 0): ?>
            <p class="empty-note">No researchers found.</p>
        <?php else: ?>
            <?php foreach($researchers as $researcher): ?>
                <div class="person-card">
                    <div class="person-name"><?php echo htmlspecialchars($researcher['FirstName'] . ' ' . $researcher['LastName']); ?></div>
                    <div class="person-email"><?php echo htmlspecialchars($researcher['Email']); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="panel">
        <span class="panel-title">Students</span>
        <?php if (count($students) === 0): ?>
            <p class="empty-note">No students found.</p>
        <?php else: ?>
            <?php foreach($students as $student): ?>
                <div class="person-card">
                    <div class="person-name"><?php echo htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']); ?></div>
                    <div class="person-email"><?php echo htmlspecialchars($student['Email']); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
