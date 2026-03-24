<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard</title>

<!-- FullCalendar CDN -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<style>
body {
    font-family: Arial;
    margin: 20px;
}

/* NAV BAR (TABS) */
.navbar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.navbar a {
    padding: 10px 15px;
    background: #eee;
    border-radius: 6px;
    text-decoration: none;
    color: black;
}

.navbar a:hover {
    background: #ddd;
}

/* LAYOUT */
.container {
    display: flex;
    gap: 20px;
}

/* CALENDAR */
#calendar {
    width: 70%;
}

/* RIGHT PANEL */
.sidebar {
    width: 30%;
    background: #f4f4f4;
    padding: 15px;
    border-radius: 8px;
}
</style>
</head>

<body>

<h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>

<!-- TOP NAV TABS -->
<div class="navbar">
    <a href="dashboard.php">Dashboard</a>
    <a href="add_session.php">Sessions</a>
    <a href="view_credits.php">Credits</a>
    <a href="view_studies.php">Studies</a>
    <a href="add_study.php">Add Study</a>
    <a href="edit_profile.php">Profile</a>
    <a href="change_password.php">Password</a>
    <a href="logout.php">Logout</a>
</div>

<div class="container">

    <!-- CALENDAR -->
    <div id="calendar"></div>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h2>Upcoming Studies</h2>

        <?php
        $sql = "SELECT StudyTitle, StartDate FROM Study ORDER BY StartDate ASC LIMIT 5";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<p><strong>" . $row['StudyTitle'] . "</strong><br>";
                echo "Date: " . $row['StartDate'] . "</p>";
            }
        } else {
            echo "<p>No upcoming studies.</p>";
        }
        ?>
    </div>

</div>

<!-- CALENDAR SCRIPT -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',

        // makes dates clickable
        dateClick: function(info) {
            alert("You clicked: " + info.dateStr);
        }
    });

    calendar.render();
});
</script>

</body>
</html>