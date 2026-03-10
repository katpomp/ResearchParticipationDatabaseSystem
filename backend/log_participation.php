<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $studentID = $_POST['StudentID'];
    $eventName = $_POST['EventName'];
    $eventDate = $_POST['EventDate'];
    $creditsEarned = $_POST['CreditsEarned'];

    $sql = "INSERT INTO attendance (user_id, event_name, event_date, credits_earned)
            VALUES ('$studentID', '$eventName', '$eventDate', '$creditsEarned')";

    if ($conn->query($sql) === TRUE) {
        $msg = "Participation logged successfully!";
    } else {
        $msg = "Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log Participation</title>
</head>
<body>
<?php include "inc_nav_dashboard.php"; ?>
<h2>Log Participation</h2>
<?php if (!empty($msg)) { echo "<p>" . htmlspecialchars($msg) . "</p>"; } ?>
<form action="log_participation.php" method="post">
    Student ID: <input type="number" name="StudentID" required><br>
    Event Name: <input type="text" name="EventName" required><br>
    Event Date: <input type="date" name="EventDate" required><br>
    Credits Earned: <input type="number" step="0.01" name="CreditsEarned" required><br><br>
    <input type="submit" value="Log Participation">
</form>

<p><a href="view_credits.php">View Student Credits</a> · <a href="dashboard.php">Dashboard</a></p>
</body>
</html>
