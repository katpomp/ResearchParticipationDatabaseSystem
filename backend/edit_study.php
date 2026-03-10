<?php
include "db_connect.php";

if (!isset($_GET['StudyID'])) {
    die("StudyID not provided.");
}
$studyID = $_GET['StudyID'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title       = $_POST['StudyTitle'];
    $description = $_POST['Description'];
    $startDate   = $_POST['StartDate'];
    $endDate     = $_POST['EndDate'];
    $researcherID = $_POST['ResearcherID'];

    $sql = "UPDATE Study 
            SET StudyTitle='$title', Description='$description', StartDate='$startDate', EndDate='$endDate', ResearcherID='$researcherID'
            WHERE StudyID='$studyID'";

    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green;'>Study updated successfully!</p>";
    } else {
        echo "<p style='color:red;'>Error: " . $conn->error . "</p>";
    }
}

$studyResult = $conn->query("SELECT * FROM Study WHERE StudyID='$studyID'");
if ($studyResult->num_rows != 1) {
    die("Study not found.");
}
$study = $studyResult->fetch_assoc();

$researchers = $conn->query("SELECT ResearcherID, FirstName, LastName FROM Researcher");
?>

<h2>Edit Study</h2>
<form action="" method="post">
    Study Title: <input type="text" name="StudyTitle" value="<?php echo $study['StudyTitle']; ?>" required><br>
    Description: <textarea name="Description" required><?php echo $study['Description']; ?></textarea><br>
    Start Date: <input type="date" name="StartDate" value="<?php echo $study['StartDate']; ?>"><br>
    End Date: <input type="date" name="EndDate" value="<?php echo $study['EndDate']; ?>"><br>
    
    Researcher:
    <select name="ResearcherID" required>
        <?php
        if($researchers && $researchers->num_rows > 0) {
            while($row = $researchers->fetch_assoc()) {
                $selected = ($row['ResearcherID'] == $study['ResearcherID']) ? "selected" : "";
                echo '<option value="' . $row['ResearcherID'] . '" ' . $selected . '>'
                     . $row['FirstName'] . ' ' . $row['LastName'] . '</option>';
            }
        } else {
            echo '<option value="">No researchers found</option>';
        }
        ?>
    </select><br><br>

    <input type="submit" value="Update Study">
</form>

<p><a href="view_studies.php">Back to all studies</a></p>