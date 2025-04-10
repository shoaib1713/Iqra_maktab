<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Log the received data for debugging
$logFile = 'debug_insert.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - FILES data: " . print_r($_FILES, true) . "\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Action: " . $action . "\n", FILE_APPEND);

    switch ($action) {
        case "add_cheque":
            try {
                $created_by = $_SESSION['user_id'];
                $created_on = date("Y-m-d H:i:s");
                $success = true;
                $error_messages = [];

                // Check if required arrays are set
                if (!isset($_POST['cheque_given_date']) || !isset($_POST['cheque_number']) || !isset($_POST['cheque_amount'])) {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Missing required fields\n", FILE_APPEND);
                    echo "Missing required fields";
                    exit;
                }

                // Log the number of entries
                $entryCount = count($_POST['cheque_given_date']);
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Processing {$entryCount} cheque entries\n", FILE_APPEND);

                foreach ($_POST['cheque_given_date'] as $index => $date) {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Processing entry #{$index}\n", FILE_APPEND);
                    
                    $cheque_number = $_POST['cheque_number'][$index];
                    $amount = $_POST['cheque_amount'][$index];
                    $cheque_handover_teacher = $_POST['cheque_handover_teacher'][$index];
                    $cheque_year = $_POST['cheque_year'][$index];
                    $cheque_month = $_POST['cheque_month'][$index];
                    $bank_name = isset($_POST['bank_name'][$index]) ? $_POST['bank_name'][$index] : '';
                    $status = isset($_POST['status'][$index]) ? $_POST['status'][$index] : 'pending';

                    // Handle File Upload
                    $photo_path = "";
                    if (isset($_FILES['cheque_photo']['name'][$index]) && $_FILES['cheque_photo']['error'][$index] === UPLOAD_ERR_OK) {
                        $photo_name = $_FILES['cheque_photo']['name'][$index];
                        $photo_tmp = $_FILES['cheque_photo']['tmp_name'][$index];
                        $photo_path = "assets/images/" . time() . "_" . basename($photo_name);
                        
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Uploading file: {$photo_name} to {$photo_path}\n", FILE_APPEND);
                        
                        if (!move_uploaded_file($photo_tmp, $photo_path)) {
                            file_put_contents($logFile, date('Y-m-d H:i:s') . " - File upload failed: " . error_get_last()['message'] . "\n", FILE_APPEND);
                            $error_messages[] = "Failed to upload file: " . $photo_name;
                            $success = false;
                            $photo_path = "";
                        }
                    } else {
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - File upload error: " . $_FILES['cheque_photo']['error'][$index] . "\n", FILE_APPEND);
                        $error_messages[] = "Error with file upload for entry #" . ($index + 1);
                        $success = false;
                    }

                    // Calculate is_cleared and is_bounced based on status
                    $is_cleared = ($status === 'cleared') ? 1 : 0;
                    $is_bounced = ($status === 'bounced') ? 1 : 0;

                    // Insert into database with new fields
                    try {
                        $stmt = $conn->prepare("INSERT INTO cheque_details (
                            cheque_given_date, cheque_number, cheque_amount, cheque_photo, 
                            cheque_handover_teacher, cheque_year, cheque_month, 
                            bank_name, is_cleared, is_bounced, created_by, created_on
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        
                        if (!$stmt) {
                            throw new Exception("Prepare failed: " . $conn->error);
                        }
                        
                        $stmt->bind_param(
                            "ssssssssiiis", 
                            $date, $cheque_number, $amount, $photo_path, 
                            $cheque_handover_teacher, $cheque_year, $cheque_month, 
                            $bank_name, $is_cleared, $is_bounced, $created_by, $created_on
                        );
                        
                        $result = $stmt->execute();
                        
                        if (!$result) {
                            throw new Exception("Execute failed: " . $stmt->error);
                        }
                        
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database insert successful for entry #{$index}\n", FILE_APPEND);
                    } catch (Exception $e) {
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database error: " . $e->getMessage() . "\n", FILE_APPEND);
                        $error_messages[] = "Database error for entry #" . ($index + 1) . ": " . $e->getMessage();
                        $success = false;
                    }
                }

                if ($success) {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - All operations completed successfully\n", FILE_APPEND);
                    header("Location: cheque_details.php");
                    echo "success";
                } else {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Errors occurred: " . implode("; ", $error_messages) . "\n", FILE_APPEND);
                    echo implode("<br>", $error_messages);
                }
            } catch (Exception $e) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Exception: " . $e->getMessage() . "\n", FILE_APPEND);
                echo "An error occurred: " . $e->getMessage();
            }
            break;

        case "add_maintenance":
            $created_by = $_SESSION['user_id'];
            $created_on = date("Y-m-d H:i:s");

            foreach ($_POST['category'] as $index => $category) {
                $amount = $_POST['amount'][$index];
                $comment = isset($_POST['comment'][$index]) ? $_POST['comment'][$index] : '';

                $stmt = $conn->prepare("INSERT INTO maintenance (category, amount, comment, created_by, created_on) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sdsis", $category, $amount, $comment, $created_by, $created_on);
                $stmt->execute();
            }

            echo "success";
            break;

        default:
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Invalid action: {$action}\n", FILE_APPEND);
            echo "Invalid action!";
            break;
    }
} else {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Invalid request method\n", FILE_APPEND);
    echo "No data received.";
}
?>
