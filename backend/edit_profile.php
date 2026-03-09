<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email'];

    $sql = "UPDATE users SET username='$username', email='$email' WHERE id='$userID'";
    $conn->query($sql);

    $_SESSION['username'] = $username;

    echo "Profile updated successfully!";
}

$sql = "SELECT username,email FROM users WHERE id='$userID'";
$result = $conn->query($sql);
$user = $result->fetch_assoc();
?>

<h2>Edit Profile</h2>

<form method="post">
Username: <input type="text" name="username" value="<?php echo $user['username']; ?>"><br>
Email: <input type="email" name="email" value="<?php echo $user['email']; ?>"><br><br>

<input type="submit" value="Update Profile">
</form>

<a href="dashboard.php">Back to Dashboard</a>