<?php
session_start();
include "db_connect.php";
require_once __DIR__ . "/inc_role_promotion.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];
sona_ensure_role_promotion_schema($conn);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = preg_replace('/\D/', '', $_POST['code'] ?? '');
    if (strlen($raw) !== 6) {
        $error = "Enter the 6-digit code from your email.";
    } else {
        $stmt = $conn->prepare("
            SELECT TokenID, NewRole, CodeHash
            FROM RolePromotionToken
            WHERE UserID = ? AND UsedAt IS NULL AND ExpiresAt > NOW()
            ORDER BY TokenID DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();

        $matchedId = null;
        $matchedRole = null;
        while ($row = $res->fetch_assoc()) {
            if (password_verify($raw, $row['CodeHash'])) {
                $matchedId = (int)$row['TokenID'];
                $matchedRole = $row['NewRole'];
                break;
            }
        }

        if ($matchedId === null) {
            $error = "That code is incorrect or has expired. Ask your administrator for a new invitation.";
        } else {
            $conn->begin_transaction();
            try {
                sona_promote_student_to_role($conn, $userId, $matchedRole);
                $clear = $conn->prepare("UPDATE RolePromotionToken SET UsedAt = NOW() WHERE UserID = ? AND UsedAt IS NULL");
                $clear->bind_param("i", $userId);
                if (!$clear->execute()) {
                    throw new Exception($clear->error);
                }
                $conn->commit();
                $_SESSION['role'] = $matchedRole;
                if ($matchedRole === 'researcher') {
                    header("Location: researcher_dashboard.php");
                } else {
                    header("Location: faculty_dashboard.php");
                }
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Could not apply the role change. Please contact support.";
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
    <title>Role invitation</title>
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
        .header-title { margin: 0; text-align: center; font-family: 'Crimson Pro', serif; font-size: 1.75rem; }
        .top-tabs {
            display: flex; align-items: center; gap: 10px; padding: 8px 2rem;
            background: var(--cnu-silver); border-bottom: 1px solid #cfd3d8;
        }
        .top-tab-link {
            display: inline-block; padding: 8px 12px; border-radius: 6px; text-decoration: none;
            color: var(--cnu-blue); font-weight: 600; background: white; border: 1px solid #c7ccd3;
        }
        .top-tab-link:hover { background: #f4f6f8; }
        .container { max-width: 480px; margin: 28px auto; padding: 0 16px; }
        .card {
            background: white; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            padding: 24px; border-top: 4px solid var(--cnu-blue);
        }
        h2 { margin-top: 0; color: var(--cnu-blue); font-family: 'Crimson Pro', serif; }
        .hint { color: #55626e; font-size: 0.92rem; margin-bottom: 1.2rem; line-height: 1.45; }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        input[type="text"] {
            width: 100%; padding: 12px; border: 1px solid #ccd3db; border-radius: 6px;
            box-sizing: border-box; font-size: 1.25rem; letter-spacing: 0.35em; text-align: center;
            font-variant-numeric: tabular-nums;
        }
        input:focus {
            outline: none; border-color: var(--cnu-blue);
            box-shadow: 0 0 0 2px rgba(0,51,102,0.1);
        }
        .btn {
            margin-top: 16px; width: 100%; padding: 12px; border: none; border-radius: 6px;
            font-weight: 600; font-size: 1rem; cursor: pointer;
            background: var(--cnu-blue); color: white;
        }
        .btn:hover { background: #002244; }
        .alert { border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; font-size: 0.92rem; }
        .alert-error { background: #fde8e8; border: 1px solid #f8b4b4; color: #b42318; }
    </style>
</head>
<body>
    <header>
        <h1 class="header-title">Christopher Newport University — Research Participation System</h1>
    </header>
    <div class="top-tabs">
        <a href="student_dashboard.php" class="top-tab-link">&larr; Student home</a>
    </div>
    <div class="container">
        <div class="card">
            <h2>Role invitation</h2>
            <p class="hint">
                If an administrator sent you a 6-digit code to become faculty or a researcher, enter it here.
                Codes expire after 15 minutes.
            </p>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" action="redeem_role_code.php" autocomplete="off">
                <label for="code">6-digit code</label>
                <input type="text" id="code" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required
                    placeholder="000000" title="Six digits">
                <button type="submit" class="btn">Verify and upgrade my account</button>
            </form>
        </div>
    </div>
</body>
</html>
