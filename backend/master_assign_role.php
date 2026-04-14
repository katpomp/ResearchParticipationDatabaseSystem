<?php
session_start();
include "db_connect.php";
require_once __DIR__ . "/inc_role_promotion.php";
require_once __DIR__ . "/inc_smtp.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'master') {
    header("Location: login.php");
    exit();
}

$masterId = (int)$_SESSION['user_id'];
sona_ensure_master_faculty_profile($conn, $masterId, $_SESSION['email'] ?? '');
sona_ensure_role_promotion_schema($conn);

$message = '';
$error = '';
$mailNotice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetEmail = trim($_POST['target_email'] ?? '');
    $newRole = trim($_POST['new_role'] ?? '');

    if ($targetEmail === '' || $newRole === '') {
        $error = "Email and role are required.";
    } elseif (!filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/@cnu\.edu$/i', $targetEmail)) {
        $error = "Email must be a @cnu.edu address.";
    } elseif ($newRole !== 'faculty' && $newRole !== 'researcher') {
        $error = "Role must be faculty or researcher.";
    } else {
        $stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $targetEmail);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();

        if (!$userRow) {
            $error = "No account exists for that email.";
        } elseif ($userRow['role'] !== 'student') {
            $error = "That account is not a student. Only students can be promoted through this flow.";
        } else {
            $targetUserId = (int)$userRow['id'];
            $code = (string)random_int(100000, 999999);
            $codeHash = password_hash($code, PASSWORD_DEFAULT);
            $expires = (new DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s');

            $conn->begin_transaction();
            try {
                sona_invalidate_pending_promotion_tokens($conn, $targetUserId);
                $ins = $conn->prepare("
                    INSERT INTO RolePromotionToken (UserID, NewRole, CodeHash, ExpiresAt, CreatedByUserID)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $ins->bind_param("isssi", $targetUserId, $newRole, $codeHash, $expires, $masterId);
                if (!$ins->execute()) {
                    throw new Exception($ins->error);
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Could not create invitation. Please try again.";
            }

            if ($error === '') {
                $roleLabel = $newRole === 'faculty' ? 'faculty' : 'researcher';
                $body = "Hello,\n\n"
                    . "A system administrator has invited you to become {$roleLabel} in the CNU Research Participation System.\n\n"
                    . "Your verification code is: {$code}\n\n"
                    . "Sign in to the student portal and open \"Role invitation\" to enter this code. "
                    . "The code expires in 15 minutes.\n\n"
                    . "If you did not expect this message, contact IT support.\n\n"
                    . "CNU Research Participation System";
                $sent = sona_send_plain_email($targetEmail, "CNU Research — role invitation code", $body);
                if ($sent) {
                    $message = 'A 6-digit code was sent to ' . $targetEmail . '. The recipient must sign in as that student and enter the code before it expires.';
                } else {
                    $message = 'The invitation was recorded, but the email could not be sent. Check SMTP settings (config/mail.local.php).';
                    $mailNotice = 'For testing, you may need to retrieve the code from the database or fix mail configuration.';
                }
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
    <title>Assign role | Master admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root { --cnu-blue: #003366; --cnu-silver: #E0E0E0; --text-dark: #333; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: #f0f2f5; color: var(--text-dark); }
        header {
            background: linear-gradient(90deg, #002b55 0%, var(--cnu-blue) 100%);
            padding: 1rem 2rem;
            color: white;
            box-shadow: 0 4px 14px rgba(0,0,0,0.2);
        }
        .header-title { margin: 0; text-align: center; font-family: 'Crimson Pro', serif; font-size: 1.85rem; }
        .top-tabs {
            display: flex; align-items: center; gap: 10px; padding: 8px 2rem;
            background: var(--cnu-silver); border-bottom: 1px solid #cfd3d8;
        }
        .top-tab-link {
            display: inline-block; padding: 8px 12px; border-radius: 6px; text-decoration: none;
            color: var(--cnu-blue); font-weight: 600; background: white; border: 1px solid #c7ccd3;
        }
        .top-tab-link:hover { background: #f4f6f8; }
        .container { max-width: 560px; margin: 28px auto; padding: 0 16px; }
        .card {
            background: white; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            padding: 24px; border-top: 4px solid var(--cnu-blue);
        }
        h2 { margin-top: 0; color: var(--cnu-blue); font-family: 'Crimson Pro', serif; font-size: 1.75rem; }
        .hint { color: #55626e; font-size: 0.92rem; margin-bottom: 1.2rem; line-height: 1.45; }
        label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.92rem; }
        input[type="email"], select {
            width: 100%; padding: 11px; border: 1px solid #ccd3db; border-radius: 6px;
            box-sizing: border-box; font-size: 1rem; margin-bottom: 14px;
        }
        input:focus, select:focus {
            outline: none; border-color: var(--cnu-blue);
            box-shadow: 0 0 0 2px rgba(0,51,102,0.1);
        }
        .btn {
            display: inline-block; padding: 11px 18px; border-radius: 6px; border: none;
            font-weight: 600; cursor: pointer; font-size: 1rem;
            background: var(--cnu-blue); color: white;
        }
        .btn:hover { background: #002244; }
        .btn-secondary {
            background: #eef1f5; color: #1f2d3a; border: 1px solid #d2d8e0; text-decoration: none;
            margin-left: 10px;
        }
        .btn-secondary:hover { background: #e5e9ef; }
        .alert { border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; font-size: 0.92rem; }
        .alert-success { background: #e6f4ea; border: 1px solid #b7dfb9; color: #2f6f39; }
        .alert-error { background: #fde8e8; border: 1px solid #f8b4b4; color: #b42318; }
        .alert-warn { background: #fff8e6; border: 1px solid #f5d67a; color: #7a5a00; }
    </style>
</head>
<body>
    <header>
        <h1 class="header-title">Christopher Newport University — Master administration</h1>
    </header>
    <div class="top-tabs">
        <a href="master_dashboard.php" class="top-tab-link">&larr; Master home</a>
    </div>
    <div class="container">
        <div class="card">
            <h2>Invite faculty or researcher</h2>
            <p class="hint">
                The account must already be registered as a <strong>student</strong>. Submitting this form emails a one-time 6-digit code to that address.
                The student signs in and enters the code under <strong>Role invitation</strong> to complete the upgrade.
            </p>
            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($mailNotice !== ''): ?>
                <div class="alert alert-warn"><?php echo htmlspecialchars($mailNotice); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" action="master_assign_role.php">
                <label for="target_email">Student email (@cnu.edu)</label>
                <input type="email" id="target_email" name="target_email" required
                    pattern=".+@cnu\.edu$" title="Must be @cnu.edu"
                    placeholder="student@cnu.edu" autocomplete="off">

                <label for="new_role">New role</label>
                <select id="new_role" name="new_role" required>
                    <option value="researcher">Researcher</option>
                    <option value="faculty">Faculty</option>
                </select>

                <button type="submit" class="btn">Send verification code</button>
                <a href="master_dashboard.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>
