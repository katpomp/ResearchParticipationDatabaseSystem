<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'researcher') {
    header("Location: login.php");
    exit();
}
?>

<h1>Researcher Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?></p>

<!-- RESEARCHER PERMISSION AKA WHAT THEY SEE -->
<a href="add_study.php">Add Study</a>
<a href="edit_studies.php">Edit Studies</a>