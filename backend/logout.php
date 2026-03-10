<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logged out</title>
</head>
<body>
<p>You have been logged out.</p>
<p><a href="login.php">Login again</a> · <a href="register.php">Register</a></p>
</body>
</html>
