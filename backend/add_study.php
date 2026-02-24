<?php
session_start();
include "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title       = $_POST['StudyTitle'];
    $description = $_POST['Description'];
    $startDate   = $_POST['StartDate'];
    $endDate     = $_POST['EndDate'];
    $researcherID = $_POST['ResearcherID'];

    $sql = "INSERT INTO Study (StudyTitle, Description, StartDate, EndDate, ResearcherID)
            VALUES ('$title', '$description', '$startDate', '$endDate', '$researcherID')";

    if ($conn->query($sql) === TRUE) {
        echo "Study added successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<h2>Add Study</h2>
<form action="add_study.php" method="post">
    Study Title: <input type="text" name="StudyTitle" required><br>
    Description: <textarea name="Description" required></textarea><br>
    Start Date: <input type="date" name="StartDate"><br>
    End Date: <input type="date" name="EndDate"><br>
    Researcher ID: <input type="number" name="ResearcherID" required><br><br>
    <input type="submit" value="Add Study">
</form>

<p><a href="view_studies.php">View all studies</a></p>