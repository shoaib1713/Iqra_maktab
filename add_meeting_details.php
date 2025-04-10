<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Fetch users for dropdowns
$teachers = $conn->query("SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name");
$admins = $conn->query("SELECT id, name FROM users WHERE role = 'admin' ORDER BY name");

// Initialize variables for edit mode
$isEdit = false;
$meeting = array();
$meeting_fees = array();
$page_title = "Add Meeting";

// Check if we're editing an existing meeting
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $meeting_id = $_GET['edit'];
    $page_title = "Edit Meeting";
    $isEdit = true;
    
    // Fetch meeting details
    $meetingQuery = "SELECT * FROM meeting_details WHERE id = ?";
    $stmt = $conn->prepare($meetingQuery);
    $stmt->bind_param("i", $meeting_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $meeting = $result->fetch_assoc();
        
        // Fetch fees collection details
        $feesQuery = "SELECT * FROM meeting_fees_collection WHERE meeting_id = ?";
        $feesStmt = $conn->prepare($feesQuery);
        $feesStmt->bind_param("i", $meeting_id);
        $feesStmt->execute();
        $feesResult = $feesStmt->get_result();
        
        while ($fee = $feesResult->fetch_assoc()) {
            $meeting_fees[] = $fee;
        }
    } else {
        // Meeting not found
        $_SESSION['error'] = "Meeting not found.";
        header("Location: meeting_list.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $meeting_date = $_POST['meeting_date'] ?? date('Y-m-d');
    $student_responsibility = $_POST['student_responsibility'];
    $namaz_responsibility = $_POST['namaz_responsibility'];
    $visit_fajar = $_POST['visit_fajar'];
    $visit_asar = $_POST['visit_asar'];
    $visit_magrib = $_POST['visit_magrib'];
    $maktab_lock = $_POST['maktab_lock'];
    $cleanliness_ethics = $_POST['cleanliness_ethics'];
    $food_responsibility = $_POST['food_responsibility'];
    
    if ($isEdit) {
        // Update existing meeting
        $stmt = $conn->prepare("UPDATE meeting_details SET 
                              meeting_date = ?, 
                              student_responsibility = ?, 
                              namaz_responsibility = ?, 
                              visit_fajar = ?, 
                              visit_asar = ?, 
                              visit_magrib = ?, 
                              maktab_lock = ?, 
                              cleanliness_ethics = ?, 
                              food_responsibility = ?,
                              updated_at = NOW(),
                              updated_by = ?
                              WHERE id = ?");
        $stmt->bind_param("siiiiiiiiii", 
                       $meeting_date, 
                       $student_responsibility, 
                       $namaz_responsibility, 
                       $visit_fajar, 
                       $visit_asar, 
                       $visit_magrib, 
                       $maktab_lock, 
                       $cleanliness_ethics, 
                       $food_responsibility,
                       $user_id,
                       $meeting['id']);
        $stmt->execute();
        
        // Delete existing fees records and add new ones
        $deleteFees = $conn->prepare("DELETE FROM meeting_fees_collection WHERE meeting_id = ?");
        $deleteFees->bind_param("i", $meeting['id']);
        $deleteFees->execute();
        $deleteFees->close();
        
        $meeting_id = $meeting['id'];
    } else {
        // Insert new meeting details
        $stmt = $conn->prepare("INSERT INTO meeting_details (
                              meeting_date,
                              student_responsibility, 
                              namaz_responsibility, 
                              visit_fajar, 
                              visit_asar, 
                              visit_magrib, 
                              maktab_lock, 
                              cleanliness_ethics, 
                              food_responsibility,
                              created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siiiiiiiii", 
                       $meeting_date,
                       $student_responsibility, 
                       $namaz_responsibility, 
                       $visit_fajar, 
                       $visit_asar, 
                       $visit_magrib, 
                       $maktab_lock, 
                       $cleanliness_ethics, 
                       $food_responsibility,
                       $user_id);
        $stmt->execute();
        $meeting_id = $stmt->insert_id; // Get last inserted meeting ID
    }
    
    $stmt->close();

    // Insert fees collection records
    if (!empty($_POST['fees_admin']) && !empty($_POST['fees_amount'])) {
        $stmt = $conn->prepare("INSERT INTO meeting_fees_collection (meeting_id, admin_id, amount) VALUES (?, ?, ?)");

        for ($i = 0; $i < count($_POST['fees_admin']); $i++) {
            $admin_id = $_POST['fees_admin'][$i];
            $amount = $_POST['fees_amount'][$i];

            if (!empty($admin_id) && !empty($amount)) {
                $stmt->bind_param("iid", $meeting_id, $admin_id, $amount);
                $stmt->execute();
            }
        }

        $stmt->close();
    }

    $_SESSION['success'] = ($isEdit) ? "Meeting updated successfully." : "Meeting added successfully.";
    header("Location: meeting_list.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - MAKTAB-E-IQRA</title>
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
                    <span class="navbar-brand ms-2"><?= $page_title ?></span>
                    <div class="d-flex align-items-center">
                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="me-2"><i class="fas fa-user-circle fs-5"></i> <?php echo $user_name; ?></span>
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
                    <h4 class="mb-0">
                        <?php if ($isEdit): ?>
                            <i class="fas fa-edit me-2"></i> Edit Meeting for <?= date('d F Y', strtotime($meeting['meeting_date'])) ?>
                        <?php else: ?>
                            <i class="fas fa-plus-circle me-2"></i> Add New Meeting
                        <?php endif; ?>
                    </h4>
                    <a href="meeting_list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Meetings
                    </a>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i> Meeting Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="meeting_date" class="form-control" value="<?= $isEdit ? $meeting['meeting_date'] : date('Y-m-d') ?>" required>
                            </div>
                            
                            <div class="col-md-12 mt-4">
                                <h5 class="border-bottom pb-2 mb-3"><i class="fas fa-tasks me-1"></i> Responsibilities</h5>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Student Responsibility <span class="text-danger">*</span></label>
                                <select name="student_responsibility" class="form-select" required>
                                    <option value="">Select Teacher</option>
                                    <?php 
                                    $teachers->data_seek(0);
                                    while ($teacher = $teachers->fetch_assoc()): 
                                        $selected = ($isEdit && $meeting['student_responsibility'] == $teacher['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $teacher['id'] ?>" <?= $selected ?>><?= $teacher['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Namaz Responsibility <span class="text-danger">*</span></label>
                                <select name="namaz_responsibility" class="form-select" required>
                                    <option value="">Select Teacher</option>
                                    <?php 
                                    $teachers->data_seek(0);
                                    while ($teacher = $teachers->fetch_assoc()): 
                                        $selected = ($isEdit && $meeting['namaz_responsibility'] == $teacher['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $teacher['id'] ?>" <?= $selected ?>><?= $teacher['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Maktab Lock <span class="text-danger">*</span></label>
                                <select name="maktab_lock" class="form-select" required>
                                    <option value="">Select Teacher</option>
                                    <?php 
                                    $teachers->data_seek(0);
                                    while ($teacher = $teachers->fetch_assoc()): 
                                        $selected = ($isEdit && $meeting['maktab_lock'] == $teacher['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $teacher['id'] ?>" <?= $selected ?>><?= $teacher['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Cleanliness & Ethics <span class="text-danger">*</span></label>
                                <select name="cleanliness_ethics" class="form-select" required>
                                    <option value="">Select Teacher</option>
                                    <?php 
                                    $teachers->data_seek(0);
                                    while ($teacher = $teachers->fetch_assoc()): 
                                        $selected = ($isEdit && $meeting['cleanliness_ethics'] == $teacher['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $teacher['id'] ?>" <?= $selected ?>><?= $teacher['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Food Responsibility <span class="text-danger">*</span></label>
                                <select name="food_responsibility" class="form-select" required>
                                    <option value="">Select Committee Member</option>
                                    <?php 
                                    $admins->data_seek(0);
                                    while ($admin = $admins->fetch_assoc()): 
                                        $selected = ($isEdit && $meeting['food_responsibility'] == $admin['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $admin['id'] ?>" <?= $selected ?>><?= $admin['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-12 mt-4">
                                <h5 class="border-bottom pb-2 mb-3"><i class="fas fa-clock me-1"></i> Daily Visits</h5>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">After Fajar <span class="text-danger">*</span></label>
                                <select name="visit_fajar" class="form-select" required>
                                    <option value="">Select Committee Member</option>
                                    <?php 
                                    $admins->data_seek(0);
                                    while ($admin = $admins->fetch_assoc()): 
                                        $selected = ($isEdit && $meeting['visit_fajar'] == $admin['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $admin['id'] ?>" <?= $selected ?>><?= $admin['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">After Asar <span class="text-danger">*</span></label>
                                <select name="visit_asar" class="form-select" required>
                                    <option value="">Select Committee Member</option>
                                    <?php 
                                    $admins->data_seek(0);
                                    while ($admin = $admins->fetch_assoc()): 
                                        $selected = ($isEdit && $meeting['visit_asar'] == $admin['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $admin['id'] ?>" <?= $selected ?>><?= $admin['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">After Magrib <span class="text-danger">*</span></label>
                                <select name="visit_magrib" class="form-select" required>
                                    <option value="">Select Committee Member</option>
                                    <?php 
                                    $admins->data_seek(0);
                                    while ($admin = $admins->fetch_assoc()): 
                                        $selected = ($isEdit && $meeting['visit_magrib'] == $admin['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $admin['id'] ?>" <?= $selected ?>><?= $admin['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-12 mt-4">
                                <h5 class="border-bottom pb-2 mb-3">
                                    <i class="fas fa-money-bill-wave me-1"></i> Committee Fees Collection
                                    <button type="button" class="btn btn-sm btn-success float-end add-fees">
                                        <i class="fas fa-plus"></i> Add Contribution
                                    </button>
                                </h5>
                            </div>

                            <div class="col-md-12" id="feesCollectionContainer">
                                <?php if ($isEdit && count($meeting_fees) > 0): ?>
                                    <?php foreach ($meeting_fees as $fee): ?>
                                        <div class="row mb-3 fees-collection-entry">
                                            <div class="col-md-6">
                                                <select name="fees_admin[]" class="form-select" required>
                                                    <option value="">Select Committee Member</option>
                                                    <?php 
                                                    $admins->data_seek(0);
                                                    while ($admin = $admins->fetch_assoc()): 
                                                        $selected = ($fee['admin_id'] == $admin['id']) ? 'selected' : '';
                                                    ?>
                                                        <option value="<?= $admin['id'] ?>" <?= $selected ?>><?= $admin['name'] ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" name="fees_amount[]" class="form-control" placeholder="Enter Amount" value="<?= $fee['amount'] ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" class="btn btn-danger w-100 remove-fees">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="row mb-3 fees-collection-entry">
                                        <div class="col-md-6">
                                            <select name="fees_admin[]" class="form-select" required>
                                                <option value="">Select Committee Member</option>
                                                <?php 
                                                $admins->data_seek(0);
                                                while ($admin = $admins->fetch_assoc()): 
                                                ?>
                                                    <option value="<?= $admin['id'] ?>"><?= $admin['name'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" name="fees_amount[]" class="form-control" placeholder="Enter Amount" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-danger w-100 remove-fees">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 mt-4">
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="meeting_list.php" class="btn btn-light px-4">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary px-4">
                                        <?php if ($isEdit): ?>
                                            <i class="fas fa-save me-1"></i> Update Meeting
                                        <?php else: ?>
                                            <i class="fas fa-save me-1"></i> Save Meeting
                                        <?php endif; ?>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const menuToggle = document.getElementById('menu-toggle');
            const sidebarWrapper = document.getElementById('sidebar-wrapper');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            
            menuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                sidebarWrapper.classList.toggle('toggled');
            });
            
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebarWrapper.classList.remove('toggled');
                });
            }
        });
        
        // Fees collection entry management
        $(document).ready(function () {
            $(".add-fees").click(function () {
                // Clone the first fees entry
                var newEntry = $(".fees-collection-entry:first").clone();
                
                // Reset values
                newEntry.find('select').val('');
                newEntry.find('input').val('');
                
                // Append to container
                $("#feesCollectionContainer").append(newEntry);
            });

            // Event delegation for dynamically added elements
            $(document).on("click", ".remove-fees", function () {
                // Only remove if there's more than one fees entry
                if ($(".fees-collection-entry").length > 1) {
                    $(this).closest(".fees-collection-entry").remove();
                } else {
                    alert("At least one committee member contribution is required.");
                }
            });
        });
    </script>
</body>
</html>

