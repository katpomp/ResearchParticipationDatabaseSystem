<?php
session_start();
include "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email    = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (username, email, password) 
            VALUES ('$username', '$email', '$password')";

    if ($conn->query($sql) === TRUE) {
        header("Location: login.php");
        exit();
    } else {
        $reg_error = "Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
</head>
<body>
<?php include "inc_nav_dashboard.php"; ?>
<h2>Register</h2>
<?php if (!empty($reg_error)) { echo "<p style='color:red;'>" . htmlspecialchars($reg_error) . "</p>"; } ?>
<form action="register.php" method="post">
  Username: <input type="text" name="username" required><br>
  Email: <input type="email" name="email" required><br>
  Password: <input type="password" name="password" required><br><br>
  <input type="submit" value="Register">
</form>

<p>Already have an account? <a href="login.php">Login here</a></p>
</body>
</html>
