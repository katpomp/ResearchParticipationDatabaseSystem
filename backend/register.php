<?php
session_start();
include "db_connect.php";
require_once __DIR__ . "/inc_smtp.php";

$isFacultyCreator = isset($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'faculty');
$reg_error = '';
$reg_success = '';

$sendMailNotice = '';

function sendRegistrationConfirmationEmail($toEmail, $firstName)
{
    $safeName = $firstName !== '' ? $firstName : "Student";
    $body = "Hello {$safeName},\n\n"
        . "Your account registration was received successfully.\n"
        . "You can now sign in to the CNU Research Participation System.\n\n"
        . "If you did not create this account, please contact support.\n\n"
        . "CNU Research Participation System";
    return sona_send_plain_email($toEmail, "CNU Research Participation - Registration Received", $body);
}

$conn->query("
    CREATE TABLE IF NOT EXISTS RoleRequest (
        RequestID INT AUTO_INCREMENT PRIMARY KEY,
        UserID INT NOT NULL,
        FirstName VARCHAR(50) NOT NULL,
        LastName VARCHAR(50) NOT NULL,
        Email VARCHAR(100) NOT NULL,
        RequestedRole VARCHAR(20) NOT NULL,
        Status VARCHAR(20) NOT NULL DEFAULT 'pending',
        ReviewedByUserID INT NULL,
        ReviewedAt DATETIME NULL,
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_request (UserID),
        FOREIGN KEY (UserID) REFERENCES users(id)
    )
");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $plainPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['password_confirm'] ?? '';
    $firstName = trim($_POST['FirstName'] ?? '');
    $lastName = trim($_POST['LastName'] ?? '');

    $role = 'student';

    if ($firstName === '' || $lastName === '' || $email === '' || $plainPassword === '' || $confirmPassword === '') {
        $reg_error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $reg_error = "Please enter a valid email address.";
    } elseif (!preg_match('/@cnu\.edu$/i', $email)) {
        $reg_error = "Email must end with @cnu.edu.";
    } elseif (strlen($plainPassword) < 8) {
        $reg_error = "Password must be at least 8 characters.";
    } elseif ($plainPassword !== $confirmPassword) {
        $reg_error = "Passwords do not match.";
    } else {
        $password = password_hash($plainPassword, PASSWORD_DEFAULT);
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $check = $checkStmt->get_result();
    }

    if ($reg_error === '' && $check->num_rows > 0) {
        $reg_error = "Email already exists.";
    }

    if ($reg_error === '') {
        $conn->begin_transaction();
        try {
            $userStmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
            $userStmt->bind_param("sss", $email, $password, $role);
            if (!$userStmt->execute()) {
                throw new Exception($userStmt->error);
            }

            $userID = $conn->insert_id;
            $profileStmt = $conn->prepare("INSERT INTO Student (FirstName, LastName, Email, UserID) VALUES (?, ?, ?, ?)");
            $profileStmt->bind_param("sssi", $firstName, $lastName, $email, $userID);
            if (!$profileStmt->execute()) {
                throw new Exception($profileStmt->error);
            }

            $conn->commit();
            $emailSent = sendRegistrationConfirmationEmail($email, $firstName);

            if ($isFacultyCreator) {
                $reg_success = "Account created successfully.";
                if (!$emailSent) {
                    $sendMailNotice = "Account created, but confirmation email could not be sent.";
                }
            } else {
                header("Location: login.php");
                exit();
            }
        } catch (Exception $e) {
            $conn->rollback();
            $reg_error = "Unable to register account right now. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | University Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --cnu-blue: #003366;
            --cnu-silver: #E0E0E0;
            --text-dark: #333;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #1a2d45;
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        header {
            background-color: var(--cnu-blue);
            color: white;
            padding: 1rem 2rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        header h1 {
            margin: 0;
            font-family: 'Crimson Pro', serif;
            font-size: 1.5rem;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }
        .register-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
        }
        .register-card {
            width: 100%;
            max-width: 470px;
            background: #fff;
            border-radius: 8px;
            border-top: 5px solid var(--cnu-blue);
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            padding: 2.25rem;
        }
        .register-card h2 {
            margin: 0 0 0.5rem 0;
            color: var(--cnu-blue);
            font-family: 'Crimson Pro', serif;
            font-size: 2rem;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #55626e;
            margin-bottom: 1.4rem;
            font-size: 0.95rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.45rem;
            font-weight: 600;
            font-size: 0.92rem;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccd3db;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1rem;
            background: #fff;
        }
        input:focus,
        select:focus {
            outline: none;
            border-color: var(--cnu-blue);
            box-shadow: 0 0 0 2px rgba(0,51,102,0.1);
        }
        .field-hint {
            display: block;
            margin-top: 0.35rem;
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 400;
        }
        .btn-submit {
            width: 100%;
            background-color: var(--cnu-blue);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-submit:hover {
            background-color: #002244;
        }
        .alert {
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 1rem;
            font-size: 0.92rem;
        }
        .alert-error {
            background: #fde8e8;
            border: 1px solid #f8b4b4;
            color: #b42318;
        }
        .alert-success {
            background: #e6f4ea;
            border: 1px solid #b7dfb9;
            color: #2f6f39;
        }
        .footer-links {
            text-align: center;
            margin-top: 1.2rem;
            font-size: 0.92rem;
        }
        .footer-links a {
            color: var(--cnu-blue);
            text-decoration: none;
            font-weight: 600;
        }
        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <header>
        <h1>Christopher Newport University</h1>
    </header>

    <div class="register-container">
        <div class="register-card">
            <h2>Create Account</h2>
            <?php if ($isFacultyCreator): ?>
                <p class="subtitle">Faculty account creation mode.</p>
            <?php endif; ?>

            <?php if ($reg_error !== ''): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($reg_error); ?></div>
            <?php endif; ?>
            <?php if ($reg_success !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($reg_success); ?></div>
            <?php endif; ?>
            <?php if ($sendMailNotice !== ''): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($sendMailNotice); ?></div>
            <?php endif; ?>

            <form action="register.php" method="post">
                <div class="form-group">
                    <label for="FirstName">First Name</label>
                    <input type="text" id="FirstName" name="FirstName" required>
                </div>
                <div class="form-group">
                    <label for="LastName">Last Name</label>
                    <input type="text" id="LastName" name="LastName" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" pattern=".+@cnu\.edu$" title="Use your @cnu.edu email address." required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm password</label>
                    <input type="password" id="password_confirm" name="password_confirm" minlength="8" autocomplete="new-password" required aria-describedby="password_confirm_hint">
                    <span id="password_confirm_hint" class="field-hint">Re-enter your password to confirm.</span>
                </div>

                <button type="submit" class="btn-submit">
                    <?php echo $isFacultyCreator ? 'Create Account' : 'Register'; ?>
                </button>
            </form>

            <div class="footer-links">
                <?php if ($isFacultyCreator): ?>
                    <a href="faculty_dashboard.php">Back to Faculty Dashboard</a>
                <?php else: ?>
                    Already have an account? <a href="login.php">Login here</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var pw = document.getElementById('password');
        var cf = document.getElementById('password_confirm');
        if (!pw || !cf) return;
        function syncMatchValidity() {
            if (cf.value !== '' && pw.value !== cf.value) {
                cf.setCustomValidity('Passwords do not match.');
            } else {
                cf.setCustomValidity('');
            }
        }
        pw.addEventListener('input', syncMatchValidity);
        cf.addEventListener('input', syncMatchValidity);
    })();
    </script>
</body>
</html>