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

    // Check if already signed up
    $check = $conn->prepare("SELECT * FROM StudentStudy WHERE StudentID=? AND StudyID=?");
    $check->bind_param("ii", $studentID, $studyID);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $message = "You are already signed up for this study.";
    } else {
        $stmt = $conn->prepare("INSERT INTO StudentStudy (StudentID, StudyID) VALUES (?, ?)");
        $stmt->bind_param("ii", $studentID, $studyID);
        if ($stmt->execute()) {
            $message = "Successfully signed up!";
        } else {
            $message = "Error signing up: " . $conn->error;
        }
    }

    header("Location: student_dashboard.php?message=" . urlencode($message));
    exit();
}
?>