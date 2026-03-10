<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include "db_connect.php";

$message = '';
$error = '';

$role_to_status = [
    'student'    => 'scheduled_student',
    'researcher' => 'scheduled_researcher',
    'admin'      => 'scheduled_admin',
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $study_id       = (int)($_POST['StudyID'] ?? 0);
    $student_id     = (int)($_POST['StudentID'] ?? 0);
    $researcher_id  = (int)($_POST['ResearcherID'] ?? 0);
    $building       = trim($_POST['BuildingName'] ?? '');
    $room           = trim($_POST['RoomNumber'] ?? '');
    $session_date   = $_POST['SessionDate'] ?? '';
    $duration       = max(1, (int)($_POST['Duration'] ?? 60));
    $role           = $_POST['participant_role'] ?? '';

    if (!isset($role_to_status[$role])) {
        $error = "Select a valid role (researcher, admin, or student).";
    } elseif ($study_id < 1 || $student_id < 1 || $researcher_id < 1 || $building === '' || $room === '' || $session_date === '') {
        $error = "All fields are required (valid Study, Student, Researcher IDs and Location).";
    } else {
        $attendance_status = $role_to_status[$role];
        $sql = "INSERT INTO InPersonSession
                (SessionDate, Duration, AttendanceStatus, StudyID, StudentID, ResearcherID, BuildingName, RoomNumber)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            $error = "Prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param(
                "sisiiiss",
                $session_date,
                $duration,
                $attendance_status,
                $study_id,
                $student_id,
                $researcher_id,
                $building,
                $room
            );
            if ($stmt->execute()) {
                $message = "Session added. SessionID: " . $conn->insert_id;
            } else {
                $error = "Error: " . $stmt->error . " — check StudyID, StudentID, ResearcherID, and Location exist.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add / Sign up for a session</title>
</head>
<body>
<?php include "inc_nav_dashboard.php"; ?>
<h2>Add / Sign up for a session</h2>
<p>Uses table <strong>InPersonSession</strong> only (existing FKs must exist).</p>

<?php if ($message) { echo "<p style='color:green;'>" . htmlspecialchars($message) . "</p>"; } ?>
<?php if ($error) { echo "<p style='color:red;'>" . htmlspecialchars($error) . "</p>"; } ?>

<form action="add_session.php" method="post">
    <label>Study ID: <input type="number" name="StudyID" required min="1"></label><br><br>
    <label>Student ID: <input type="number" name="StudentID" required min="1"></label><br><br>
    <label>Researcher ID: <input type="number" name="ResearcherID" required min="1"></label><br><br>
    <label>Building name: <input type="text" name="BuildingName" required maxlength="100"></label><br><br>
    <label>Room number: <input type="text" name="RoomNumber" required maxlength="20"></label><br><br>
    <label>Session date: <input type="date" name="SessionDate" required></label><br><br>
    <label>Duration (minutes): <input type="number" name="Duration" value="60" min="1"></label><br><br>

    <label>I am signing up as:
        <select name="participant_role" required>
            <option value="">— Select —</option>
            <option value="researcher">Researcher</option>
            <option value="admin">Admin</option>
            <option value="student">Student</option>
        </select>
    </label><br><br>

    <input type="submit" value="Add session">
</form>

<p><a href="view_cancel_session.php">View / cancel sessions</a> · <a href="dashboard.php">Dashboard</a></p>
</body>
</html>
