<?php
include "db_connect.php";

$sql = "SELECT * FROM Study ORDER BY StudyID DESC";
$result = $conn->query($sql);
?>

<h2>All Studies</h2>
<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Description</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Researcher ID</th>
    </tr>
<?php
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['StudyID']}</td>
                <td>{$row['StudyTitle']}</td>
                <td>{$row['Description']}</td>
                <td>{$row['StartDate']}</td>
                <td>{$row['EndDate']}</td>
                <td>{$row['ResearcherID']}</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='6'>No studies found</td></tr>";
}
?>
</table>

<p><a href="add_study.php">Add a new study</a></p>