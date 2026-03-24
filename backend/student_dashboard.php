<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}
?>

<h1>Student Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?></p>

<!-- STUDENT PERMISSION AKA WHAT THEY SEE -->
<a href="view_studies.php">View Available Studies</a>