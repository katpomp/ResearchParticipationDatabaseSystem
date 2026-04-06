<?php
session_start();
include "db_connect.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$loggedOut = isset($_GET['logged_out']) && $_GET['logged_out'] === '1';
$pendingRequestSubmitted = isset($_GET['pending_request']) && $_GET['pending_request'] === '1';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            if ($user['role'] === 'pending') {
                $error = "Your role request is still pending faculty approval.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] == 'student') {
                    header("Location: student_dashboard.php");
                } elseif ($user['role'] == 'researcher') {
                    header("Location: researcher_dashboard.php");
                } elseif ($user['role'] == 'faculty') {
                    header("Location: faculty_dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }

                exit();
            }
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "Email not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | University Portal</title>
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
            background-image: url('');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            background-color: var(--cnu-blue);
            padding: 1rem 2rem;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        header h1 {
            margin: 0;
            font-family: 'Crimson Pro', serif;
            font-size: 1.5rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .login-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-card {
            background: white;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 400px;
            border-top: 5px solid var(--cnu-blue);
        }

        .login-card h2 {
            margin-top: 0;
            font-family: 'Crimson Pro', serif;
            color: var(--cnu-blue);
            font-size: 2rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box; 
            font-size: 1rem;
        }

        input:focus {
            outline: none;
            border-color: var(--cnu-blue);
            box-shadow: 0 0 0 2px rgba(0,51,102,0.1);
        }

        input[type="submit"] {
            width: 100%;
            background-color: var(--cnu-blue);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        input[type="submit"]:hover {
            background-color: #002244;
        }

        .error-msg {
            background-color: #fde8e8;
            color: #c53030;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            text-align: center;
            border: 1px solid #f8b4b4;
        }

        .success-msg {
            background-color: #dff0d8;
            color: #3c763d;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            text-align: center;
            border: 1px solid #b7dfb9;
        }

        .footer-links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
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

<div class="login-container">
    <div class="login-card">
        <h2>Account Login</h2>
        
        <?php if(isset($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if($loggedOut): ?>
            <div class="success-msg">You have been logged out successfully.</div>
        <?php endif; ?>
        <?php if($pendingRequestSubmitted): ?>
            <div class="success-msg">Your role request was submitted and is awaiting faculty approval.</div>
        <?php endif; ?>

        <form action="login.php" method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <input type="submit" value="Sign In">
        </form>

        <div class="footer-links">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</div>

</body>
</html>