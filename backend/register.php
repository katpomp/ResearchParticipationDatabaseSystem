<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email    = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (username, email, password)
            VALUES ('$username', '$email', '$password')";

    if ($conn->query($sql) === TRUE) {
        echo "Account created successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<form action="register.php" method="post">
  Username: <input type="text" name="username" required><br>
  Email: <input type="email" name="email" required><br>
  Password: <input type="password" name="password" required><br><br>
  <input type="submit" value="Register">
</form>