<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['user_id'];

$sql = "SELECT username, email FROM users WHERE id='$userID'";
$result = $conn->query($sql);
$user = $result->fetch_assoc();
?>

<h2>Dashboard</h2>

<p>Welcome, <?php echo $user['username']; ?>!</p>
<p>Email: <?php echo $user['email']; ?></p>

<a href="logout.php">Logout</a>