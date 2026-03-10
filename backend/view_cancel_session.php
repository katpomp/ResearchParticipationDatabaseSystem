<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include "db_connect.php";

$role_status = [
    'researcher' => 'scheduled_researcher',
    'admin'      => 'scheduled_admin',
    'student'    => 'scheduled_student',
];
$allowed_roles = array_keys($role_status);

$message = '';
$error = '';
$filter_role = $_GET['role'] ?? $_POST['filter_role'] ?? '';


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['cancel_id'])) {
    $cancel_id = (int)$_POST['cancel_id'];
    if ($cancel_id > 0) {
        $stmt = $conn->prepare(
            "UPDATE InPersonSession SET AttendanceStatus = 'cancelled'
             WHERE SessionID = ? AND AttendanceStatus IN ('scheduled_student','scheduled_researcher','scheduled_admin')"
        );
        if ($stmt && $stmt->bind_param("i", $cancel_id) && $stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $stmt->close();
                $redirect = "view_cancel_session.php";
                $role_after = $_POST['filter_role'] ?? '';
                if ($role_after !== '' && in_array($role_after, $allowed_roles, true)) {
                    $redirect .= "?role=" . urlencode($role_after);
                }
                header("Location: " . $redirect);
                exit();
            }
            $error = "Session not found or already cancelled.";
            $stmt->close();
        } else {
            $error = $stmt ? $stmt->error : $conn->error;
        }
    }
}

$sql = "SELECT s.SessionID, s.SessionDate, s.Duration, s.AttendanceStatus,
               s.StudyID, s.StudentID, s.ResearcherID, s.BuildingName, s.RoomNumber,
               st.StudyTitle
        FROM InPersonSession s
        LEFT JOIN Study st ON s.StudyID = st.StudyID
        WHERE 1=1";

$params = [];
$types = '';

if ($filter_role !== '' && isset($role_status[$filter_role])) {
    $sql .= " AND s.AttendanceStatus = ?";
    $types = 's';
    $params[] = $role_status[$filter_role];
}

$sql .= " ORDER BY s.SessionDate DESC, s.SessionID DESC";

if ($types !== '') {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = false;
        $error = $conn->error;
    }
} else {
    $result = $conn->query($sql);
    if (!$result) {
        $error = $conn->error;
    }
}

function role_from_status($status) {
    if ($status === 'scheduled_student') return 'student';
    if ($status === 'scheduled_researcher') return 'researcher';
    if ($status === 'scheduled_admin') return 'admin';
    if ($status === 'cancelled') return 'cancelled';
    return $status;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View / cancel sessions</title>
    <style>
        table { border-collapse: collapse; margin-top: 1em; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        tr.cancelled { opacity: 0.6; }
    </style>
</head>
<body>
<?php include "inc_nav_dashboard.php"; ?>
<h2>View sessions (InPersonSession)</h2>

<?php if ($message) { echo "<p style='color:green;'>" . htmlspecialchars($message) . "</p>"; } ?>
<?php if ($error && !$result) { echo "<p style='color:red;'>" . htmlspecialchars($error) . "</p>"; } ?>

<form action="view_cancel_session.php" method="get">
    <label>Show role:
        <select name="role" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="researcher" <?php echo $filter_role === 'researcher' ? 'selected' : ''; ?>>Researcher</option>
            <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
            <option value="student" <?php echo $filter_role === 'student' ? 'selected' : ''; ?>>Student</option>
        </select>
    </label>
    <noscript><input type="submit" value="Filter"></noscript>
</form>

<table>
    <tr>
        <th>SessionID</th>
        <th>Study</th>
        <th>Date</th>
        <th>Duration</th>
        <th>StudentID</th>
        <th>ResearcherID</th>
        <th>Location</th>
        <th>Role / Status</th>
        <th>Action</th>
    </tr>
<?php
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $is_cancelled = ($row['AttendanceStatus'] === 'cancelled');
        $tr_class = $is_cancelled ? " class='cancelled'" : '';
        $title = $row['StudyTitle'] ? htmlspecialchars($row['StudyTitle']) : '(Study ' . (int)$row['StudyID'] . ')';
        $role_disp = role_from_status($row['AttendanceStatus']);
        echo "<tr$tr_class>";
        echo "<td>" . (int)$row['SessionID'] . "</td>";
        echo "<td>$title</td>";
        echo "<td>" . htmlspecialchars($row['SessionDate']) . "</td>";
        echo "<td>" . (int)$row['Duration'] . " min</td>";
        echo "<td>" . (int)$row['StudentID'] . "</td>";
        echo "<td>" . (int)$row['ResearcherID'] . "</td>";
        echo "<td>" . htmlspecialchars($row['BuildingName']) . " / " . htmlspecialchars($row['RoomNumber']) . "</td>";
        echo "<td>" . htmlspecialchars($role_disp) . "</td>";
        echo "<td>";
        if (!$is_cancelled && in_array($row['AttendanceStatus'], ['scheduled_student','scheduled_researcher','scheduled_admin'], true)) {
            echo "<form action='view_cancel_session.php' method='post' style='display:inline;' onsubmit=\"return confirm('Cancel this session?');\">";
            echo "<input type='hidden' name='filter_role' value='" . htmlspecialchars($filter_role) . "'>";
            echo "<input type='hidden' name='cancel_id' value='" . (int)$row['SessionID'] . "'>";
            echo "<button type='submit'>Cancel</button></form>";
        } else {
            echo "—";
        }
        echo "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='9'>No sessions found. <a href='add_session.php'>Add a session</a></td></tr>";
}
if (isset($stmt)) {
    $stmt->close();
}
?>
</table>

<p><a href="add_session.php">Add / sign up for a session</a> · <a href="dashboard.php">Dashboard</a></p>
</body>
</html>
