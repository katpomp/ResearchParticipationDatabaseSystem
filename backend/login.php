<?php
// Show errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check if username exists
    $sql = "SELECT * FROM users WHERE username = '$username' LIMIT 1";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            echo "Login successful! Welcome, " . htmlspecialchars($user['username']);
        } else {
            echo "Incorrect password.";
        }
    } else {
        echo "Username not found.";
    }
}
?>

<form action="login.php" method="post">
    Username: <input type="text" name="username" required><br>
    Password: <input type="password" name="password" required><br><br>
    <input type="submit" value="Login">
</form>