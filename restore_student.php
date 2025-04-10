<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config/db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Restore student by setting is_deleted = 0
    $query = "UPDATE students SET is_deleted = 0 WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        include 'student_history.php';
        updateStudentStatusHistory($conn,$_SESSION['user_id'], $id,date('Y'),null,null,'active');
        header("Location: students.php");
        exit();
    } else {
        echo "Error restoring student.";
    }
}
?>
