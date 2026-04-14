<?php
session_start();
include "db_connect.php";
require_once __DIR__ . "/inc_role_promotion.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'master') {
    header("Location: login.php");
    exit();
}

$masterId = (int)$_SESSION['user_id'];
sona_ensure_master_faculty_profile($conn, $masterId, $_SESSION['email'] ?? '');
sona_ensure_role_promotion_schema($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master admin | CNU Research Participation</title>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root { --cnu-blue: #003366; --cnu-silver: #E0E0E0; --text-dark: #333; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: #f0f2f5; color: var(--text-dark); }
        header {
            background: linear-gradient(90deg, #1a1a2e 0%, #2d2d44 100%);
            padding: 1rem 2rem;
            color: white;
            box-shadow: 0 4px 14px rgba(0,0,0,0.25);
        }
        .header-title { margin: 0; text-align: center; font-family: 'Crimson Pro', serif; font-size: 1.85rem; }
        .badge {
            display: inline-block; margin-top: 8px; font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 0.08em; opacity: 0.9;
        }
        .top-tabs {
            display: flex; align-items: center; flex-wrap: wrap; gap: 10px; padding: 10px 2rem;
            background: var(--cnu-silver); border-bottom: 1px solid #cfd3d8;
        }
        .top-tab-link {
            display: inline-block; padding: 8px 12px; border-radius: 6px; text-decoration: none;
            color: var(--cnu-blue); font-weight: 600; background: white; border: 1px solid #c7ccd3;
        }
        .top-tab-link:hover { background: #f4f6f8; }
        .top-tab-link.primary { background: var(--cnu-blue); color: white; border-color: var(--cnu-blue); }
        .top-tab-link.primary:hover { background: #002244; }
        .tab-spacer { margin-left: auto; }
        .profile-dropdown { position: relative; display: inline-block; }
        .profile-dropdown > a {
            display: inline-block; padding: 8px 12px; border-radius: 6px; text-decoration: none;
            font-weight: 600; font-size: 0.95rem; color: var(--cnu-blue);
            background: white; border: 1px solid #c7ccd3;
        }
        .profile-dropdown > a:hover { background: #f4f6f8; }
        .profile-dropdown-content {
            display: none; position: absolute; right: 0; background: white; min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.15); z-index: 2; border-radius: 6px;
        }
        .profile-dropdown-content a {
            color: var(--text-dark); padding: 12px 16px; text-decoration: none; display: block;
        }
        .profile-dropdown-content a:hover { background: #f1f1f1; }
        .profile-dropdown:hover .profile-dropdown-content { display: block; }
        .container { max-width: 720px; margin: 32px auto; padding: 0 20px; }
        .panel {
            background: white; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            padding: 22px 24px; margin-bottom: 18px; border-left: 4px solid var(--cnu-blue);
        }
        .panel h2 {
            margin: 0 0 10px 0; color: var(--cnu-blue); font-family: 'Crimson Pro', serif;
            font-size: 1.4rem;
        }
        .panel p { margin: 0 0 12px 0; color: #4a5568; line-height: 1.5; font-size: 0.95rem; }
        .panel p:last-child { margin-bottom: 0; }
    </style>
</head>
<body>
    <header>
        <h1 class="header-title">Research Participation System</h1>
        <p class="badge" style="text-align:center;display:block;">Master administrator</p>
    </header>
    <div class="top-tabs">
        <a href="master_assign_role.php" class="top-tab-link primary">Invite faculty / researcher</a>
        <a href="register.php" class="top-tab-link">Create student account</a>
        <div class="tab-spacer"></div>
        <div class="profile-dropdown">
            <a href="#"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></a>
            <div class="profile-dropdown-content">
                <a href="edit_profile.php">Edit profile</a>
                <a href="change_password.php">Change password</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="panel">
            <h2>Promotions</h2>
            <p>
                Use <strong>Invite faculty / researcher</strong> to email a one-time code to a student’s @cnu.edu address.
                They complete the process on <strong>Role invitation</strong> in their student menu.
            </p>
        
    </div>
</body>
</html>
