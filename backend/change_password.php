<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

$current = $_POST['current_password'];
$new = $_POST['new_password'];

$sql = "SELECT password FROM users WHERE id='$userID'";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

if (password_verify($current, $user['password'])) {

$newHash = password_hash($new, PASSWORD_DEFAULT);

$update = "UPDATE users SET password='$newHash' WHERE id='$userID'";
$conn->query($update);

echo "Password updated successfully";

} else {

echo "Current password incorrect";

}
}
?>

<h2>Change Password</h2>

<form method="post">
Current Password: <input type="password" name="current_password"><br>
New Password: <input type="password" name="new_password"><br><br>

<input type="submit" value="Change Password">
</form>

<a href="dashboard.php">Back to Dashboard</a>