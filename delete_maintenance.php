<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once 'config.php';
    require 'config/db.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["id"])) {
        $id = intval($_POST["id"]);
        $deleted_by = $_SESSION['user_id']; // Assuming you store the user ID in session
        $deleted_on = date("Y-m-d H:i:s");
    
        $query = "UPDATE maintenance SET is_deleted = 1, deleted_by = ?, deleted_on = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isi", $deleted_by, $deleted_on, $id);
        
        if ($stmt->execute()) {
            echo "success";
        } else {
            echo "error";
        }
        $stmt->close();
        $conn->close();
    } else {
        echo "invalid";
    }
    ?>
    
