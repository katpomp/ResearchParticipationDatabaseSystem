<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <style>
        body { font-family: sans-serif; max-width: 640px; margin: 2em auto; padding: 0 1em; }
        h1 { margin-top: 0; }
        .dashboard-links {
            list-style: none;
            padding: 0;
            margin: 1.5em 0;
        }
        .dashboard-links li { margin-bottom: 0.5em; }
        .dashboard-links a {
            display: block;
            padding: 10px 14px;
            background: #f0f0f0;
            border-radius: 6px;
            text-decoration: none;
            color: #222;
            border: 1px solid #ddd;
        }
        .dashboard-links a:hover { background: #e5e5e5; }
        .logout { margin-top: 2em; }
    </style>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>

    <h2>Quick links</h2>
    <ul class="dashboard-links">
        <li><a href="register.php">Register (sign up new account)</a></li>
        <li><a href="add_session.php">Add / sign up for a session</a></li>
        <li><a href="view_cancel_session.php">View / cancel sessions</a></li>
        <li><a href="view_credits.php">View student credits</a></li>
        <li><a href="log_participation.php">Log participation</a></li>
        <li><a href="edit_profile.php">Edit profile</a></li>
        <li><a href="change_password.php">Change password</a></li>
        <li><a href="view_studies.php">View studies</a></li>
        <li><a href="add_study.php">Add study</a></li>
    </ul>

    <p class="logout"><a href="logout.php">Logout</a></p>
</body>
</html>
