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
    if ($role === 'master') {
        return 'master_dashboard.php';
    }
    return 'login.php';
}

$dashboardUrl = getDashboardByRole($role);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        $error = "All password fields are required.";
    } elseif (strlen($new) < 8) {
        $error = "New password must be at least 8 characters.";
    } elseif ($new !== $confirm) {
        $error = "New password and confirmation do not match.";
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user || !password_verify($current, $user['password'])) {
            $error = "Current password is incorrect.";
        } elseif (password_verify($new, $user['password'])) {
            $error = "New password must be different from your current password.";
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $newHash, $userID);
            if ($updateStmt->execute()) {
                $message = "Password updated successfully.";
            } else {
                $error = "Unable to update password. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
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
        input[type="password"] { width:100%; padding:11px; border:1px solid #ccd3db; border-radius:6px; box-sizing:border-box; font-size:1rem; }
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
        <a href="edit_profile.php" class="top-tab-link">Edit Profile</a>
        <a href="change_password.php" class="top-tab-link active">Change Password</a>
    </div>
    <div class="container">
        <div class="card">
            <h2>Change Password</h2>
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" minlength="8" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">Update Password</button>
                    <a href="<?php echo htmlspecialchars($dashboardUrl); ?>" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>