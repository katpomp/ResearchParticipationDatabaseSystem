<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userID = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $fromRole = $_POST['from_role'] ?? '';
    $toRole = $_POST['to_role'] ?? '';

    $validSwitch = (
        $userID > 0 &&
        (($fromRole === 'student' && $toRole === 'researcher') ||
         ($fromRole === 'researcher' && $toRole === 'student'))
    );

    if (!$validSwitch) {
        $error = "Invalid role change request.";
    } else {
        $conn->begin_transaction();
        try {
            if ($fromRole === 'student') {
                $sourceStmt = $conn->prepare("SELECT FirstName, LastName, Email FROM Student WHERE UserID = ? LIMIT 1");
                $sourceStmt->bind_param("i", $userID);
                $sourceStmt->execute();
                $sourceRes = $sourceStmt->get_result();
                $person = $sourceRes->fetch_assoc();

                if (!$person) {
                    throw new Exception("Student record not found.");
                }

                $insertStmt = $conn->prepare("INSERT INTO Researcher (FirstName, LastName, Email, UserID) VALUES (?, ?, ?, ?)");
                $insertStmt->bind_param("sssi", $person['FirstName'], $person['LastName'], $person['Email'], $userID);
                if (!$insertStmt->execute()) {
                    throw new Exception($insertStmt->error);
                }

                $deleteStmt = $conn->prepare("DELETE FROM Student WHERE UserID = ?");
                $deleteStmt->bind_param("i", $userID);
                if (!$deleteStmt->execute()) {
                    throw new Exception($deleteStmt->error);
                }
            } else {
                $sourceStmt = $conn->prepare("SELECT FirstName, LastName, Email FROM Researcher WHERE UserID = ? LIMIT 1");
                $sourceStmt->bind_param("i", $userID);
                $sourceStmt->execute();
                $sourceRes = $sourceStmt->get_result();
                $person = $sourceRes->fetch_assoc();

                if (!$person) {
                    throw new Exception("Researcher record not found.");
                }

                $insertStmt = $conn->prepare("INSERT INTO Student (FirstName, LastName, Email, UserID) VALUES (?, ?, ?, ?)");
                $insertStmt->bind_param("sssi", $person['FirstName'], $person['LastName'], $person['Email'], $userID);
                if (!$insertStmt->execute()) {
                    throw new Exception($insertStmt->error);
                }

                $deleteStmt = $conn->prepare("DELETE FROM Researcher WHERE UserID = ?");
                $deleteStmt->bind_param("i", $userID);
                if (!$deleteStmt->execute()) {
                    throw new Exception($deleteStmt->error);
                }
            }

            $roleStmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $roleStmt->bind_param("si", $toRole, $userID);
            if (!$roleStmt->execute()) {
                throw new Exception($roleStmt->error);
            }

            $conn->commit();
            $message = "Role updated successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Could not update role. Please try again.";
        }
    }
}

$researchers = [];
$students = [];

$researcherRes = $conn->query("SELECT UserID, FirstName, LastName, Email FROM Researcher ORDER BY LastName ASC, FirstName ASC");
while ($row = $researcherRes->fetch_assoc()) {
    $researchers[] = $row;
}

$studentRes = $conn->query("SELECT UserID, FirstName, LastName, Email FROM Student ORDER BY LastName ASC, FirstName ASC");
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
.alert { margin:0 2rem; margin-top:14px; border-radius:6px; padding:10px 12px; font-size:0.92rem; }
.alert-success { background:#e6f4ea; border:1px solid #b7dfb9; color:#2f6f39; }
.alert-error { background:#fde8e8; border:1px solid #f8b4b4; color:#b42318; }
.role-action { margin-top:8px; }
.role-button { background:var(--cnu-blue); color:white; border:none; border-radius:6px; padding:7px 10px; cursor:pointer; font-weight:600; font-size:0.82rem; }
.role-button:hover { background:#002244; }
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
<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
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
                    <form method="post" class="role-action">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars((string)$researcher['UserID']); ?>">
                        <input type="hidden" name="from_role" value="researcher">
                        <input type="hidden" name="to_role" value="student">
                        <button type="submit" class="role-button">Change to Student</button>
                    </form>
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
                    <form method="post" class="role-action">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars((string)$student['UserID']); ?>">
                        <input type="hidden" name="from_role" value="student">
                        <input type="hidden" name="to_role" value="researcher">
                        <button type="submit" class="role-button">Change to Researcher</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
