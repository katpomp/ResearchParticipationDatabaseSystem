<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'] ?? '';

switch ($role) {
    case 'student':
        header("Location: student_dashboard.php");
        exit();
    case 'researcher':
        header("Location: researcher_dashboard.php");
        exit();
    case 'faculty':
        header("Location: faculty_dashboard.php");
        exit();
    case 'master':
        header("Location: master_dashboard.php");
        exit();
    default:
        header("Location: login.php");
        exit();
}
