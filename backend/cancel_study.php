<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $studentID = $_SESSION['user_id'];
    $studyID = $_POST['studyID'];

    $stmt = $conn->prepare("DELETE FROM StudentStudy WHERE StudentID=? AND StudyID=?");
    $stmt->bind_param("ii", $studentID, $studyID);

    if ($stmt->execute()) {
        $message = "You have canceled your signup for this study.";
    } else {
        $message = "Error canceling: " . $conn->error;
    }

    header("Location: student_dashboard.php?message=" . urlencode($message));
    exit();
}
?>