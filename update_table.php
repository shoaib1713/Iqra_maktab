<?php
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            require_once 'config.php';
            require 'config/db.php';
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit();
        }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];

    switch ($action) {
        case "approve_fee":
            $fee_id = $_POST['fee_id'];
            $approved_by = $_SESSION['user_id']; // Logged-in user's ID
            $approved_on = date("Y-m-d H:i:s");

            $query = "UPDATE fees SET status = 'paid', approved_by = '$approved_by', approved_on = '$approved_on' WHERE id = '$fee_id'";
            if ($conn->query($query) === TRUE) {
                echo "success";
            } else {
                echo "error";
            }
            break;
        case "reject_fee":
            $fee_id = $_POST['fee_id'];
            $reason = $_POST['reason'];
            $rejected_by = $_SESSION['user_id']; // Logged-in user's ID
            $rejected_on = date("Y-m-d H:i:s");
            
            $query = "UPDATE fees SET status = 'rejected', rejected_by = '$rejected_by', rejected_on = '$rejected_on', reason = '$reason' WHERE id = '$fee_id'";
            if ($conn->query($query) === TRUE) {
                echo "success";
            } else {
                echo "error";
            }
            break;
        case "update_user_status":
            $user_id = $_POST['user_id'];
            $status = $_POST['status'];
            
            $query = "UPDATE users SET is_active = '$status' WHERE id = '$user_id'";
            if ($conn->query($query) === TRUE) {
                echo "success";
            } else {
                echo "error";
            }
            break;  
        case "update_cheque_clear_bounce_status":
            $cheque_id = $_POST['cheque_id'];
            $status = $_POST['status'] == 'cleared' ? 1 : 0;
            $bounce_status = $_POST['status'] == 'bounced' ? 1 : 0;
            
            $query = "UPDATE cheque_details SET is_cleared = $status, is_bounced = $bounce_status WHERE id = '$cheque_id'";
            if ($conn->query($query) === TRUE) {
                echo "success";
            } else {
                echo "error";
            }
            break; 
            
        // Future updates for other tables can be added here.
    }
    $conn->close();
    exit();
}
?>