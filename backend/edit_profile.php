<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$message = '';
$error = '';

function getDashboardByRole($role)
{
    if ($role === 'student') {
        return 'student_dashboard.php';
    }
    if ($role === 'researcher') {
        return 'researcher_dashboard.php';
    }
    if ($role === 'faculty') {
        return 'faculty_dashboard.php';
    }
    return 'login.php';
}

function getRoleTable($role)
{
    if ($role === 'student') {
        return 'Student';
    }
    if ($role === 'researcher') {
        return 'Researcher';
    }
    if ($role === 'faculty') {
        return 'Faculty';
    }
    return '';
}

$dashboardUrl = getDashboardByRole($role);
$roleTable = getRoleTable($role);

if ($roleTable === '') {
    header("Location: login.php");
    exit();
}

$firstName = '';
$lastName = '';
$email = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($firstName === '' || $lastName === '' || $email === '') {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $checkStmt->bind_param("si", $email, $userID);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result();

        if ($checkRes->num_rows > 0) {
            $error = "That email is already in use.";
        } else {
            $conn->begin_transaction();
            try {
                $userStmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                $userStmt->bind_param("si", $email, $userID);
                if (!$userStmt->execute()) {
                    throw new Exception($userStmt->error);
                }

                $profileSql = "UPDATE {$roleTable} SET FirstName = ?, LastName = ?, Email = ? WHERE UserID = ?";
                $profileStmt = $conn->prepare($profileSql);
                $profileStmt->bind_param("sssi", $firstName, $lastName, $email, $userID);
                if (!$profileStmt->execute()) {
                    throw new Exception($profileStmt->error);
                }

                $conn->commit();
                $_SESSION['email'] = $email;
                $message = "Profile updated successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Unable to update profile. Please try again.";
            }
        }
    }
}

$sql = "SELECT FirstName, LastName, Email FROM {$roleTable} WHERE UserID = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    $firstName = $user['FirstName'] ?? $firstName;
    $lastName = $user['LastName'] ?? $lastName;
    $email = $user['Email'] ?? $email;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root { --cnu-blue: #003366; --cnu-silver: #E0E0E0; --text-dark: #333; }
        body { margin:0; font-family:'Inter',sans-serif; background:#f0f2f5; color:var(--text-dark); }
        header { background:linear-gradient(90deg, #002b55 0%, var(--cnu-blue) 100%); color:white; padding:1rem 2rem; box-shadow:0 4px 14px rgba(0,0,0,0.2); }
        .header-title { margin:0; text-align:center; font-family:'Crimson Pro', serif; font-size:2rem; }
        .top-tabs { display:flex; align-items:center; gap:10px; padding:8px 2rem; background:var(--cnu-silver); border-top:1px solid #cfd3d8; border-bottom:1px solid #cfd3d8; }
        .top-tab-link { display:inline-block; padding:8px 12px; border-radius:6px; text-decoration:none; color:var(--cnu-blue); font-weight:600; background:white; border:1px solid #c7ccd3; }
        .top-tab-link.active { background:var(--cnu-blue); color:white; border-color:var(--cnu-blue); }
        .top-tab-link:hover { background:#f4f6f8; }
        .container { max-width:720px; margin:30px auto; padding:0 16px; }
        .card { background:white; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.06); padding:24px; }
        h2 { margin-top:0; color:var(--cnu-blue); font-family:'Crimson Pro', serif; font-size:2rem; }
        .form-group { margin-bottom:14px; }
        label { display:block; margin-bottom:6px; font-weight:600; }
        input[type="text"], input[type="email"] { width:100%; padding:11px; border:1px solid #ccd3db; border-radius:6px; box-sizing:border-box; font-size:1rem; }
        input:focus { outline:none; border-color:var(--cnu-blue); box-shadow:0 0 0 2px rgba(0,51,102,0.1); }
        .actions { display:flex; gap:10px; margin-top:16px; }
        .btn { display:inline-block; text-decoration:none; border:none; cursor:pointer; font-weight:600; border-radius:6px; padding:10px 14px; font-size:0.95rem; }
        .btn-primary { background:var(--cnu-blue); color:white; }
        .btn-primary:hover { background:#002244; }
        .btn-secondary { background:#eef1f5; color:#1f2d3a; border:1px solid #d2d8e0; }
        .btn-secondary:hover { background:#e5e9ef; }
        .alert { border-radius:6px; padding:10px 12px; margin-bottom:12px; }
        .alert-success { background:#e6f4ea; border:1px solid #b7dfb9; color:#2f6f39; }
        .alert-error { background:#fde8e8; border:1px solid #f8b4b4; color:#b42318; }
    </style>
</head>
<body>
    <header>
        <h1 class="header-title">Christopher Newport University - Research Participation System</h1>
    </header>
    <div class="top-tabs">
        <a href="<?php echo htmlspecialchars($dashboardUrl); ?>" class="top-tab-link">&#8962; Home</a>
        <a href="edit_profile.php" class="top-tab-link active">Edit Profile</a>
        <a href="change_password.php" class="top-tab-link">Change Password</a>
    </div>
    <div class="container">
        <div class="card">
            <h2>Edit Profile</h2>
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($firstName); ?>">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($lastName); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="<?php echo htmlspecialchars($dashboardUrl); ?>" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>