<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<h1>Welcome to your dashboard, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>

<p><a href="logout.php">Logout</a></p>