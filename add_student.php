<?php
session_start();
require 'config/db.php';

$user_id = $_SESSION['user_id'];
// Fetch teachers from the database
$teacherQuery = $conn->query("SELECT id, name FROM users where role='teacher'");
$teachers = $teacherQuery->fetch_all(MYSQLI_ASSOC);

include 'student_history.php';
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $name = $_POST['name'];
    $class = $_POST['class'];
    $annual_fees = $_POST['annual_fees'];
    $phone = $_POST['phone'];
    $teacher_id = $_POST['assigned_teacher'];

    // File Upload Handling
    $target_dir = "assets/images/";
    $photo_name = basename($_FILES["photo"]["name"]);
    $target_file = $target_dir . time() . "_" . $photo_name;
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if image file is valid
    if (isset($_FILES["photo"])) {
        $check = getimagesize($_FILES["photo"]["tmp_name"]);
        if ($check === false) {
            die("File is not an image.");
        }
    }

    // Allow only certain file formats
    $allowed_types = ["jpg", "jpeg", "png", "gif"];
    if (!in_array($imageFileType, $allowed_types)) {
        die("Only JPG, JPEG, PNG & GIF files are allowed.");
    }

    // Move uploaded file
    if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
        // Insert student data into database
        $query = "INSERT INTO students (name, photo, class, annual_fees, phone, assigned_teacher) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssi", $name, $target_file, $class, $annual_fees, $phone, $teacher_id);
        if ($stmt->execute()) {
            $student_id = $stmt->insert_id; // Get last inserted meeting ID
            updateStudentStatusHistory($conn,$user_id, $student_id,date('Y'),$teacher_id,$annual_fees,'active');
            echo "Student added successfully!";
            header("Location: students.php");
            exit();
        } else {
            echo "Error: " . $stmt->error;
        }
    } else {
        echo "File upload failed.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 rounded">
                <div class="container-fluid">
                    <button class="btn" id="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-brand ms-2">Add New Student</span>
                    <div class="d-flex align-items-center">
                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="me-2"><i class="fas fa-user-circle fs-5"></i> <?php echo $_SESSION['user_name']; ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser">
                                <li><a class="dropdown-item" href="modules/logout.php">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
            
            <div class="container-fluid px-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">Add New Student</h4>
                    <a href="students.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Students
                    </a>
                </div>
                
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form action="add_student.php" method="POST" enctype="multipart/form-data" class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">
                                    <i class="fas fa-user me-1"></i> Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="name" name="name" placeholder="Enter student full name" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="phone" class="form-label">
                                    <i class="fas fa-phone me-1"></i> Phone Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="phone" name="phone" placeholder="Enter phone number" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="class" class="form-label">
                                    <i class="fas fa-book me-1"></i> Class <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="class" name="class" required>
                                    <option value="">Select Class</option>
                                    <option value="1">Class 1</option>
                                    <option value="2">Class 2</option>
                                    <option value="3">Class 3</option>
                                    <option value="4">Class 4</option>
                                    <option value="5">Class 5</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="assigned_teacher" class="form-label">
                                    <i class="fas fa-chalkboard-teacher me-1"></i> Assigned Ulma <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="assigned_teacher" name="assigned_teacher" required>
                                    <option value="">Select Ulma</option>
                                    <?php foreach($teachers as $teacher){ ?>
                                        <option value="<?= $teacher['id']; ?>"><?= $teacher['name']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="annual_fees" class="form-label">
                                    <i class="fas fa-rupee-sign me-1"></i> Salana Fees <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control" id="annual_fees" name="annual_fees" value="2000" required>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="photo" class="form-label">
                                    <i class="fas fa-camera me-1"></i> Photo <span class="text-danger">*</span>
                                </label>
                                <input type="file" class="form-control" id="photo" name="photo" required>
                                <div class="form-text">Upload a clear photo of the student. Allowed formats: JPG, JPEG, PNG, GIF.</div>
                            </div>
                            
                            <div class="col-md-12 mt-4">
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="students.php" class="btn btn-light px-4">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="fas fa-save me-1"></i> Save Student
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menu-toggle');
            const sidebarWrapper = document.getElementById('sidebar-wrapper');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            
            menuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                sidebarWrapper.classList.toggle('toggled');
            });
            
            sidebarOverlay.addEventListener('click', function() {
                sidebarWrapper.classList.remove('toggled');
            });
            
            // Preview image before upload
            document.getElementById('photo').addEventListener('change', function(e) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if(document.getElementById('preview')) {
                        document.getElementById('preview').src = e.target.result;
                    } else {
                        const img = document.createElement('img');
                        img.id = 'preview';
                        img.src = e.target.result;
                        img.className = 'img-thumbnail mt-2';
                        img.style.height = '150px';
                        img.style.width = 'auto';
                        document.getElementById('photo').parentNode.appendChild(img);
                    }
                }
                reader.readAsDataURL(e.target.files[0]);
            });
        });
    </script>
</body>
</html>

