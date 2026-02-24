<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "db_connect.php";

$sql = "SELECT users.id, users.username, credits.total_credits, credits.last_updated
        FROM users
        LEFT JOIN credits ON users.id = credits.user_id
        ORDER BY users.id ASC";

$result = $conn->query($sql);
?>

<h2>Student Credits</h2>
<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>Student ID</th>
        <th>Username</th>
        <th>Total Credits</th>
        <th>Last Updated</th>
    </tr>
<?php
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $credits = $row['total_credits'] ?? 0; // default to 0 if null
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['username']}</td>
                <td>{$credits}</td>
                <td>{$row['last_updated']}</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='4'>No students found</td></tr>";
}
?>
</table>

<p><a href="log_participation.php">Log Participation</a></p>