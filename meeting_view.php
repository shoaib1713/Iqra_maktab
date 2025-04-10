<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Check if meeting ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid meeting ID";
    header("Location: meeting_list.php");
    exit();
}

$meeting_id = $_GET['id'];

// Function to get user name by ID
function getUserName($userId, $conn) {
    if (!$userId) return "N/A"; // If no user assigned, return "N/A"
    
    $query = $conn->query("SELECT name FROM users WHERE id = '$userId' LIMIT 1");
    $user = $query->fetch_assoc();
    
    return $user ? $user['name'] : "Unknown";
}

// Fetch meeting details
$meetingQuery = "SELECT * FROM meeting_details WHERE id = ?";
$stmt = $conn->prepare($meetingQuery);
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Meeting not found";
    header("Location: meeting_list.php");
    exit();
}

$meeting = $result->fetch_assoc();

// Fetch fees collection details
$feesQuery = "SELECT mfc.*, u.name as admin_name 
              FROM meeting_fees_collection mfc 
              LEFT JOIN users u ON mfc.admin_id = u.id 
              WHERE meeting_id = ?";
$feesStmt = $conn->prepare($feesQuery);
$feesStmt->bind_param("i", $meeting_id);
$feesStmt->execute();
$feesResult = $feesStmt->get_result();

$meeting_fees = [];
$total_fees = 0;
while ($fee = $feesResult->fetch_assoc()) {
    $meeting_fees[] = $fee;
    $total_fees += $fee['amount'];
}

// Get creator and updater information
$creator_name = getUserName($meeting['created_by'], $conn);
$updater_name = $meeting['updated_by'] ? getUserName($meeting['updated_by'], $conn) : null;

$page_title = "Meeting Details";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Details - MAKTAB-E-IQRA</title>
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
                    <span class="navbar-brand ms-2">Meeting Details</span>
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
                        <i class="fas fa-calendar-check me-2"></i> Meeting Details for <?= date('d F Y', strtotime($meeting['meeting_date'])) ?>
                    </h4>
                    <div>
                        <?php if($role === 'admin'): ?>
                        <a href="add_meeting_details.php?edit=<?= $meeting_id ?>" class="btn btn-primary me-2">
                            <i class="fas fa-edit me-1"></i> Edit Meeting
                        </a>
                        <?php endif; ?>
                        <a href="meeting_list.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Meetings
                        </a>
                    </div>
                </div>
                
                <!-- Meeting Overview Card -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Meeting Overview</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6 class="fw-bold text-primary">Date & Time</h6>
                                    <p class="mb-0">
                                        <i class="fas fa-calendar-day me-1"></i> 
                                        <?= date('d M Y', strtotime($meeting['meeting_date'])) ?>
                                        <span class="badge bg-info ms-2"><?= date('l', strtotime($meeting['meeting_date'])) ?></span>
                                    </p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="fw-bold text-primary">Total Committee Collection</h6>
                                    <p class="mb-0 fs-4 fw-bold text-success">
                                        <i class="fas fa-rupee-sign me-1"></i> <?= number_format($total_fees, 2) ?>
                                    </p>
                                </div>
                                
                                <div>
                                    <h6 class="fw-bold text-primary">Audit Information</h6>
                                    <p class="mb-0 small">
                                        <i class="fas fa-user-edit me-1"></i> Created by: <?= $creator_name ?>
                                        <br>
                                        <i class="fas fa-clock me-1"></i> Created on: <?= date('d M Y, h:i A', strtotime($meeting['created_at'])) ?>
                                    </p>
                                    <?php if($updater_name): ?>
                                    <p class="mb-0 small mt-2">
                                        <i class="fas fa-edit me-1"></i> Last updated by: <?= $updater_name ?>
                                        <br>
                                        <i class="fas fa-clock me-1"></i> Updated on: <?= date('d M Y, h:i A', strtotime($meeting['updated_at'])) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-info text-dark">
                                <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Committee Fees Collection</h5>
                            </div>
                            <div class="card-body">
                                <?php if(count($meeting_fees) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th width="10%">#</th>
                                                    <th width="50%">Committee Member</th>
                                                    <th width="40%">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($meeting_fees as $index => $fee): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td>
                                                        <i class="fas fa-user-circle me-1 text-primary"></i>
                                                        <?= $fee['admin_name'] ?>
                                                    </td>
                                                    <td class="fw-bold">₹ <?= number_format($fee['amount'], 2) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="table-primary">
                                                    <th colspan="2" class="text-end">Total Collection:</th>
                                                    <th>₹ <?= number_format($total_fees, 2) ?></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning text-center mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        No committee fees collection records found for this meeting.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Responsibilities Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Responsibilities</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title border-bottom pb-2">
                                            <i class="fas fa-user-graduate me-1"></i> Student Responsibility
                                        </h6>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle me-2 fs-4 text-primary"></i>
                                            <span class="fs-5"><?= getUserName($meeting['student_responsibility'], $conn) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title border-bottom pb-2">
                                            <i class="fas fa-pray me-1"></i> Namaz Responsibility
                                        </h6>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle me-2 fs-4 text-primary"></i>
                                            <span class="fs-5"><?= getUserName($meeting['namaz_responsibility'], $conn) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title border-bottom pb-2">
                                            <i class="fas fa-broom me-1"></i> Cleanliness & Ethics
                                        </h6>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle me-2 fs-4 text-primary"></i>
                                            <span class="fs-5"><?= getUserName($meeting['cleanliness_ethics'], $conn) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title border-bottom pb-2">
                                            <i class="fas fa-lock me-1"></i> Maktab Lock
                                        </h6>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle me-2 fs-4 text-primary"></i>
                                            <span class="fs-5"><?= getUserName($meeting['maktab_lock'], $conn) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title border-bottom pb-2">
                                            <i class="fas fa-utensils me-1"></i> Food Responsibility
                                        </h6>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle me-2 fs-4 text-primary"></i>
                                            <span class="fs-5"><?= getUserName($meeting['food_responsibility'], $conn) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Daily Visits Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Daily Visits</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card mb-3 text-center bg-light">
                                    <div class="card-body">
                                        <div class="display-6 text-info mb-2">
                                            <i class="fas fa-sun"></i>
                                        </div>
                                        <h5 class="card-title">After Fajar Visit</h5>
                                        <p class="card-text fs-5 fw-bold"><?= getUserName($meeting['visit_fajar'], $conn) ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card mb-3 text-center bg-light">
                                    <div class="card-body">
                                        <div class="display-6 text-warning mb-2">
                                            <i class="fas fa-cloud-sun"></i>
                                        </div>
                                        <h5 class="card-title">After Asar Visit</h5>
                                        <p class="card-text fs-5 fw-bold"><?= getUserName($meeting['visit_asar'], $conn) ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card mb-3 text-center bg-light">
                                    <div class="card-body">
                                        <div class="display-6 text-secondary mb-2">
                                            <i class="fas fa-moon"></i>
                                        </div>
                                        <h5 class="card-title">After Magrib Visit</h5>
                                        <p class="card-text fs-5 fw-bold"><?= getUserName($meeting['visit_magrib'], $conn) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
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
    </script>
</body>
</html>
