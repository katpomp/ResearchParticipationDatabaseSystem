<?php
session_start();
include "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $firstName = $_POST['FirstName'];
    $lastName  = $_POST['LastName'];
    
    $check = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($check->num_rows > 0) {
        $reg_error = "Email already exists.";
    } else {
        $sql = "INSERT INTO users (email, password, role) 
                VALUES ('$email', '$password', '$role')";

        if ($conn->query($sql) === TRUE) {
            $userID = $conn->insert_id;
            if ($role == "student") {
                $conn->query("INSERT INTO Student (FirstName, LastName, Email, UserID) 
                    VALUES ('$firstName', '$lastName', '$email','$userID')");
            }
            elseif ($role == "researcher") {
                $conn->query("INSERT INTO Researcher (FirstName, LastName, Email, UserID) 
                    VALUES ('$firstName', '$lastName', '$email','$userID')");
            }
            elseif ($role == "faculty") {
                $conn->query("INSERT INTO Faculty (FirstName, LastName, Email, UserID) 
                    VALUES ('$firstName', '$lastName', '$email','$userID')");
            }
        
            header("Location: login.php");
            exit();
        } else {
            $reg_error = "Error: " . $conn->error;
        }
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
  First Name: <input type="text" name="FirstName" required><br>
  Last Name: <input type="text" name="LastName" required><br>
  Email: <input type="email" name="email" required><br>
  Role:
  <select name="role" required>
  	<option value="">--Select Role--</option>
  	<option value="student">Student</option>
  	<option value="researcher">Researcher</option>
  	<option value="faculty">Faculty</option>
  </select><br>
  Password: <input type="password" name="password" required><br><br>
  <input type="submit" value="Register">
</form>

<p>Already have an account? <a href="login.php">Login here</a></p>
</body>
</html>