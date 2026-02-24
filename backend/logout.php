<?php
session_start();
session_unset();
session_destroy();
echo "You have been logged out.<br>";
echo '<a href="login.php">Login again</a>';
?>