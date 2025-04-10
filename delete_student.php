<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config/db.php';
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    include 'student_history.php';
    // Soft delete student
    $query = "UPDATE students SET is_deleted = 1 WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        updateStudentStatusHistory($conn,$_SESSION['user_id'], $id,date('Y'),null,null,'inactive');
        header("Location: students.php");
        exit();
    } else {
        echo "Error deleting student.";
    }
}
?>
