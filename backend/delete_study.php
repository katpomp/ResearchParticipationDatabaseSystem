<?php
include "db_connect.php";

// Check if StudyID is provided
if (!isset($_GET['StudyID'])) {
    die("StudyID not provided.");
}

$studyID = $_GET['StudyID'];

// Delete the study
$sql = "DELETE FROM Study WHERE StudyID='$studyID'";
if ($conn->query($sql) === TRUE) {
    // Redirect back to view studies page
    header("Location: view_studies.php");
    exit;
} else {
    echo "Error deleting study: " . $conn->error;
}
?>