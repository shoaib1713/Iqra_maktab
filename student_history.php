<?php 
function updateStudentStatusHistory($conn, $user_id, $student_id, $year, $assigned_teacher=null, $salana_fees=0, $status = 'active') {

    $student = array();
    if (isset($student_id)) {
        $query = "SELECT * FROM students WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
    }

    if(empty($student)){
        echo "Student Not found";
        return;
    }
    if(is_null($assigned_teacher)){
        $assigned_teacher = $student['assigned_teacher'];
    }
    if(is_null($salana_fees)){
        $salana_fees = $student['annual_fees'];
    }
    // Step 1: Mark previous record as inactive (if any)
    $month = date('m');
    $updateQuery = "UPDATE student_status_history 
                    SET current_active_record = 1 , updated_by = $user_id , updated_at = now()
                    WHERE student_id = ? AND current_active_record = 0";
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("i", $student_id);
    $updateStmt->execute();
    $updateStmt->close();

    // Step 2: Insert new entry into student_status_history
    $insertQuery = "INSERT INTO student_status_history (student_id, year, month, assigned_teacher, salana_fees, status, current_active_record,created_by)
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?)";
    
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param("iiiidsi", $student_id, $year,$month ,$assigned_teacher, $salana_fees, $status, $user_id);
    $insertStmt->execute();
    $insertStmt->close();
}

// Only execute the following code if this file is accessed directly (not included in another file)
if (basename($_SERVER['PHP_SELF']) == 'student_history.php') {
    session_start();
    require 'config/db.php';
    
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }
    
    $student = array();
    $history = array();
    
    if (isset($_GET['id'])) {
        $student_id = $_GET['id'];
        
        // Get student details
        $query = "SELECT s.*, u.name as teacher_name 
                  FROM students s 
                  LEFT JOIN users u ON s.assigned_teacher = u.id 
                  WHERE s.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        
        // Get student history
        $historyQuery = "SELECT ssh.*, u.name as teacher_name, 
                          creator.name as created_by_name, 
                          updater.name as updated_by_name
                        FROM student_status_history ssh
                        LEFT JOIN users u ON ssh.assigned_teacher = u.id
                        LEFT JOIN users creator ON ssh.created_by = creator.id
                        LEFT JOIN users updater ON ssh.updated_by = updater.id
                        WHERE ssh.student_id = ? 
                        ORDER BY ssh.created_at DESC";
        $historyStmt = $conn->prepare($historyQuery);
        $historyStmt->bind_param("i", $student_id);
        $historyStmt->execute();
        $historyResult = $historyStmt->get_result();
        
        while ($row = $historyResult->fetch_assoc()) {
            $history[] = $row;
        }
        
        // Get fee payment history
        $paymentQuery = "SELECT f.*, u.name as created_by_name 
                        FROM fees f 
                        LEFT JOIN users u ON f.created_by = u.id 
                        WHERE f.student_id = ? 
                        ORDER BY f.created_at DESC";
        $paymentStmt = $conn->prepare($paymentQuery);
        $paymentStmt->bind_param("i", $student_id);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();
        
        $payments = array();
        while ($row = $paymentResult->fetch_assoc()) {
            $payments[] = $row;
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student History - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline:before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: var(--primary-color);
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -25px;
            top: 5px;
            height: 12px;
            width: 12px;
            border-radius: 50%;
            background-color: var(--primary-color);
        }
        .timeline-date {
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
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
                    <span class="navbar-brand ms-2">Student History</span>
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
                <?php if (!empty($student)): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">History for <?= $student['name'] ?></h4>
                        <a href="students.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Students
                        </a>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card shadow-sm">
                                <div class="card-body text-center">
                                    <img src="<?= $student['photo']; ?>" alt="Student Photo" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                                    <h5 class="mb-0"><?= $student['name'] ?></h5>
                                    <p class="text-muted small mb-0">Class <?= $student['class'] ?></p>
                                    <p class="text-muted small"><?= $student['phone'] ?></p>
                                    
                                    <hr>
                                    
                                    <div class="row text-start">
                                        <div class="col-6">
                                            <p class="mb-0 text-muted small">Annual Fees</p>
                                            <p class="fw-bold">₹ <?= number_format($student['annual_fees']) ?></p>
                                        </div>
                                        <div class="col-6">
                                            <p class="mb-0 text-muted small">Teacher</p>
                                            <p class="fw-bold"><?= $student['teacher_name'] ?? 'Not Assigned' ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-3">
                                        <a href="edit_student.php?id=<?= $student['id'] ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-edit me-1"></i> Edit Student
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-8">
                            <ul class="nav nav-tabs mb-4" id="historyTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="status-tab" data-bs-toggle="tab" data-bs-target="#status-content" type="button" role="tab" aria-controls="status-content" aria-selected="true">
                                        <i class="fas fa-history me-1"></i> Status History
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment-content" type="button" role="tab" aria-controls="payment-content" aria-selected="false">
                                        <i class="fas fa-money-bill-wave me-1"></i> Payment History
                                    </button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="historyTabContent">
                                <div class="tab-pane fade show active" id="status-content" role="tabpanel" aria-labelledby="status-tab">
                                    <div class="card shadow-sm">
                                        <div class="card-header bg-white">
                                            <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Status History</h5>
                                        </div>
                                        <div class="card-body">
                                            <?php if (!empty($history)): ?>
                                                <div class="timeline">
                                                    <?php foreach ($history as $item): ?>
                                                        <div class="timeline-item">
                                                            <div class="timeline-date">
                                                                <?= date('d M Y, h:i A', strtotime($item['created_at'])) ?>
                                                            </div>
                                                            <div class="card mt-1 shadow-sm">
                                                                <div class="card-body">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <h6 class="mb-0">
                                                                            <span class="badge bg-<?= $item['status'] == 'active' ? 'success' : ($item['status'] == 'transferred' ? 'warning' : 'danger') ?>">
                                                                                <?= ucfirst($item['status']) ?>
                                                                            </span>
                                                                        </h6>
                                                                        <span class="text-muted small">
                                                                            Year: <?= $item['year'] ?>, Month: <?= date("F", mktime(0, 0, 0, $item['month'], 1)) ?>
                                                                        </span>
                                                                    </div>
                                                                    <hr class="my-2">
                                                                    <div class="row">
                                                                        <div class="col-md-6">
                                                                            <p class="mb-1 small"><strong>Teacher:</strong> <?= $item['teacher_name'] ?? 'Not Assigned' ?></p>
                                                                            <p class="mb-1 small"><strong>Salana Fees:</strong> ₹ <?= number_format($item['salana_fees']) ?></p>
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <p class="mb-1 small"><strong>Created By:</strong> <?= $item['created_by_name'] ?></p>
                                                                            <?php if (!empty($item['updated_by_name'])): ?>
                                                                                <p class="mb-1 small"><strong>Updated By:</strong> <?= $item['updated_by_name'] ?> (<?= date('d M Y', strtotime($item['updated_at'])) ?>)</p>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center py-4">
                                                    <i class="fas fa-info-circle text-muted fa-2x mb-3"></i>
                                                    <p class="mb-0 text-muted">No status history found for this student.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="payment-content" role="tabpanel" aria-labelledby="payment-tab">
                                    <div class="card shadow-sm">
                                        <div class="card-header bg-white">
                                            <h5 class="mb-0"><i class="fas fa-money-check-alt me-2"></i>Payment History</h5>
                                        </div>
                                        <div class="card-body">
                                            <?php if (!empty($payments)): ?>
                                                <div class="table-responsive">
                                                    <table class="table table-striped table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Date</th>
                                                                <th>Amount</th>
                                                                <th>Month/Year</th>
                                                                <th>Status</th>
                                                                <th>Recorded By</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($payments as $payment): ?>
                                                                <tr>
                                                                    <td><?= date('d M Y', strtotime($payment['created_at'])) ?></td>
                                                                    <td>₹ <?= number_format($payment['amount']) ?></td>
                                                                    <td><?= date('F', mktime(0, 0, 0, $payment['month'], 1)) ?> <?= $payment['Year'] ?></td>
                                                                    <td>
                                                                        <span class="badge bg-<?= $payment['status'] == 'paid' ? 'success' : 'warning' ?>">
                                                                            <?= ucfirst($payment['status']) ?>
                                                                        </span>
                                                                    </td>
                                                                    <td><?= $payment['created_by_name'] ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center py-4">
                                                    <i class="fas fa-info-circle text-muted fa-2x mb-3"></i>
                                                    <p class="mb-0 text-muted">No payment history found for this student.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Student not found or no ID provided.
                    </div>
                <?php endif; ?>
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
        });
    </script>
</body>
</html>
<?php } ?>