<?php
// error reporting bc the dropdown was being stupid
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
        echo "<p style='color:green;'>Study added successfully!</p>";
    } else {
        echo "<p style='color:red;'>Error: " . $conn->error . "</p>";
    }
}

// researcher dropdown so it doesnt do nothing when u put in researcher id that is not real
// plus guys how would faculty or researchers even remember the researcher id
$researchers = $conn->query("SELECT ResearcherID, FirstName, LastName FROM Researcher");
?>

<h2>Add Study</h2>
<form action="add_study.php" method="post">
    Study Title: <input type="text" name="StudyTitle" required><br>
    Description: <textarea name="Description" required></textarea><br>
    Start Date: <input type="date" name="StartDate"><br>
    End Date: <input type="date" name="EndDate"><br>
    
    Researcher:
    <select name="ResearcherID" required>
        <option value="">--Select Researcher--</option>
        <?php 
        if($researchers && $researchers->num_rows > 0) {
            while($row = $researchers->fetch_assoc()) {
                echo '<option value="' . $row['ResearcherID'] . '">'
                     . $row['FirstName'] . ' ' . $row['LastName'] . '</option>';
            }
        } else {
            echo '<option value="">No researchers found</option>';
        }
        ?>
    </select><br><br>

    <input type="submit" value="Add Study">
</form>

<p><a href="view_studies.php">View all studies</a></p>