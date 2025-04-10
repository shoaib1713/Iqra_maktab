<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["photo"])) {
    $photo = $_FILES["photo"]["name"];
    $photo_tmp = $_FILES["photo"]["tmp_name"];
    $upload_dir = "uploads/";

    if (move_uploaded_file($photo_tmp, $upload_dir . $photo)) {
        echo json_encode(["status" => "success", "message" => "File uploaded successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "File upload failed."]);
    }
}
?>
