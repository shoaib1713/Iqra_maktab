<?php
session_start();
require_once 'config.php';
require 'config/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: restrict_user.php?page=Salary Periods&message=This page is restricted to administrators only.");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'add_period') {
        $period_name = isset($_POST['period_name']) ? trim($_POST['period_name']) : '';
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
        
        // Validate input
        $errors = [];
        
        if (empty($period_name)) {
            $errors[] = "Period name is required";
        }
        
        if (empty($start_date) || !strtotime($start_date)) {
            $errors[] = "Please enter a valid start date";
        }
        
        if (empty($end_date) || !strtotime($end_date)) {
            $errors[] = "Please enter a valid end date";
        }
        
        if (strtotime($end_date) < strtotime($start_date)) {
            $errors[] = "End date cannot be earlier than start date";
        }
        
        // Check for overlapping periods
        $checkSql = "SELECT COUNT(*) as count FROM salary_periods 
                    WHERE (start_date <= ? AND end_date >= ?) 
                    OR (start_date <= ? AND end_date >= ?) 
                    OR (start_date >= ? AND end_date <= ?)";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ssssss", $end_date, $start_date, $end_date, $start_date, $start_date, $end_date);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $overlapping = $checkResult->fetch_assoc()['count'];
        
        if ($overlapping > 0) {
            $errors[] = "This period overlaps with an existing period";
        }
        
        if (empty($errors)) {
            // Insert new period
            $sql = "INSERT INTO salary_periods (period_name, start_date, end_date) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $period_name, $start_date, $end_date);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success_message'] = "New salary period added successfully";
            } else {
                $_SESSION['error_message'] = "Failed to add salary period: " . $conn->error;
            }
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
        
        header("Location: salary_periods.php");
        exit();
    }
    
    if ($action === 'lock_period') {
        $period_id = isset($_POST['period_id']) ? intval($_POST['period_id']) : 0;
        
        // Validate input
        if ($period_id <= 0) {
            $_SESSION['error_message'] = "Invalid period ID";
        } else {
            // Lock period
            $sql = "UPDATE salary_periods SET is_locked = 1 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $period_id);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success_message'] = "Salary period locked successfully";
            } else {
                $_SESSION['error_message'] = "Failed to lock salary period: " . $conn->error;
            }
        }
        
        header("Location: salary_periods.php");
        exit();
    }
    
    if ($action === 'unlock_period') {
        $period_id = isset($_POST['period_id']) ? intval($_POST['period_id']) : 0;
        
        // Validate input
        if ($period_id <= 0) {
            $_SESSION['error_message'] = "Invalid period ID";
        } else {
            // Unlock period
            $sql = "UPDATE salary_periods SET is_locked = 0 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $period_id);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success_message'] = "Salary period unlocked successfully";
            } else {
                $_SESSION['error_message'] = "Failed to unlock salary period: " . $conn->error;
            }
        }
        
        header("Location: salary_periods.php");
        exit();
    }
    
    if ($action === 'delete_period') {
        $period_id = isset($_POST['period_id']) ? intval($_POST['period_id']) : 0;
        
        // Check if there are any salary calculations for this period
        $checkSql = "SELECT COUNT(*) as count FROM teacher_salary_calculations WHERE period_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $period_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $calculationCount = $checkResult->fetch_assoc()['count'];
        
        if ($calculationCount > 0) {
            $_SESSION['error_message'] = "Cannot delete this period as there are salary calculations associated with it";
        } else {
            // Delete period
            $sql = "DELETE FROM salary_periods WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $period_id);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success_message'] = "Salary period deleted successfully";
            } else {
                $_SESSION['error_message'] = "Failed to delete salary period: " . $conn->error;
            }
        }
        
        header("Location: salary_periods.php");
        exit();
    }
    
    if ($action === 'process_period') {
        $period_id = isset($_POST['period_id']) ? intval($_POST['period_id']) : 0;
        $force = isset($_POST['force']) ? true : false;
        
        // Redirect to process_salary.php with period_id
        header("Location: process_salary.php?period_id={$period_id}" . ($force ? "&force=true" : ""));
        exit();
    }
}

// Get all salary periods
$periodsSql = "SELECT sp.*, 
              (SELECT COUNT(*) FROM teacher_salary_calculations WHERE period_id = sp.id) as calculation_count 
              FROM salary_periods sp 
              ORDER BY start_date DESC";
$periodsResult = $conn->query($periodsSql);
$periods = [];
while ($period = $periodsResult->fetch_assoc()) {
    $periods[] = $period;
}

// Get the period type from settings
$settingsSql = "SELECT setting_value FROM salary_settings WHERE setting_key = 'salary_period_type'";
$settingsResult = $conn->query($settingsSql);
$periodType = ($settingsResult && $settingsResult->num_rows > 0) ? $settingsResult->fetch_assoc()['setting_value'] : 'monthly';

// Generate a suggested period name and dates based on the period type and last period
$suggestedPeriod = [
    'name' => '',
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+1 month -1 day'))
];

if (!empty($periods)) {
    $lastPeriod = $periods[0];
    $nextStart = date('Y-m-d', strtotime('+1 day', strtotime($lastPeriod['end_date'])));
    
    if ($periodType === 'monthly') {
        $monthName = date('F Y', strtotime($nextStart));
        $endDate = date('Y-m-t', strtotime($nextStart));
        $suggestedPeriod = [
            'name' => "Salary Period - {$monthName}",
            'start_date' => $nextStart,
            'end_date' => $endDate
        ];
    } elseif ($periodType === 'bi-weekly') {
        $endDate = date('Y-m-d', strtotime('+13 days', strtotime($nextStart)));
        $startFormatted = date('M d', strtotime($nextStart));
        $endFormatted = date('M d, Y', strtotime($endDate));
        $suggestedPeriod = [
            'name' => "Salary Period - {$startFormatted} to {$endFormatted}",
            'start_date' => $nextStart,
            'end_date' => $endDate
        ];
    } elseif ($periodType === 'weekly') {
        $endDate = date('Y-m-d', strtotime('+6 days', strtotime($nextStart)));
        $startFormatted = date('M d', strtotime($nextStart));
        $endFormatted = date('M d, Y', strtotime($endDate));
        $suggestedPeriod = [
            'name' => "Salary Period - {$startFormatted} to {$endFormatted}",
            'start_date' => $nextStart,
            'end_date' => $endDate
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Periods - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .period-card {
            transition: all 0.3s ease;
            border-left: 4px solid #4e73df;
        }
        .period-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-active {
            background-color: #1cc88a;
        }
        .status-upcoming {
            background-color: #4e73df;
        }
        .status-past {
            background-color: #858796;
        }
        .status-locked {
            background-color: #e74a3b;
        }
        .period-dates {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .period-status {
            font-size: 0.8rem;
            padding: 0.2rem 0.5rem;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <?php include 'includes/navbar.php'; ?>
            
            <div class="container-fluid px-4 py-4">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h5 class="fw-bold text-primary"><i class="fas fa-calendar-alt me-2"></i> Salary Periods</h5>
                        <p class="text-muted">Manage salary periods for teacher salary calculations.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPeriodModal">
                            <i class="fas fa-plus me-2"></i> Add New Period
                        </button>
                    </div>
                </div>
                
                <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <!-- Salary Periods List -->
                <div class="row">
                    <?php if (empty($periods)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No salary periods found. Click the "Add New Period" button to add one.
                        </div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($periods as $period): 
                            $today = date('Y-m-d');
                            $startDate = $period['start_date'];
                            $endDate = $period['end_date'];
                            
                            // Determine period status
                            $statusClass = '';
                            $statusText = '';
                            $statusBg = '';
                            
                            if ($period['is_locked']) {
                                $statusClass = 'status-locked';
                                $statusText = 'Locked';
                                $statusBg = 'bg-danger';
                            } elseif ($today >= $startDate && $today <= $endDate) {
                                $statusClass = 'status-active';
                                $statusText = 'Active';
                                $statusBg = 'bg-success';
                            } elseif ($today < $startDate) {
                                $statusClass = 'status-upcoming';
                                $statusText = 'Upcoming';
                                $statusBg = 'bg-primary';
                            } else {
                                $statusClass = 'status-past';
                                $statusText = 'Completed';
                                $statusBg = 'bg-secondary';
                            }
                        ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card shadow-sm period-card h-100">
                                <div class="card-header bg-white py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-bold text-primary">
                                            <?php echo htmlspecialchars($period['period_name']); ?>
                                        </h6>
                                        <span class="badge <?php echo $statusBg; ?> period-status">
                                            <span class="status-indicator <?php echo $statusClass; ?>"></span>
                                            <?php echo $statusText; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <p class="period-dates mb-1">
                                            <i class="fas fa-calendar-day me-2"></i> 
                                            <strong>Start:</strong> <?php echo date('M d, Y', strtotime($startDate)); ?>
                                        </p>
                                        <p class="period-dates mb-1">
                                            <i class="fas fa-calendar-day me-2"></i> 
                                            <strong>End:</strong> <?php echo date('M d, Y', strtotime($endDate)); ?>
                                        </p>
                                        <p class="period-dates mb-0">
                                            <i class="fas fa-calculator me-2"></i> 
                                            <strong>Calculations:</strong> 
                                            <?php echo $period['calculation_count']; ?>
                                            <?php if ($period['is_processed']): ?>
                                            <span class="badge bg-info ms-1">Processed</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-3">
                                        <div>
                                            <?php if (!$period['is_locked']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" data-bs-target="#lockPeriodModal<?php echo $period['id']; ?>">
                                                <i class="fas fa-lock me-1"></i> Lock
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    data-bs-toggle="modal" data-bs-target="#unlockPeriodModal<?php echo $period['id']; ?>">
                                                <i class="fas fa-unlock me-1"></i> Unlock
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($period['calculation_count'] == 0): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" data-bs-target="#deletePeriodModal<?php echo $period['id']; ?>">
                                                <i class="fas fa-trash-alt me-1"></i> Delete
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div>
                                            <?php if (!$period['is_locked'] && ($statusText === 'Completed' || $statusText === 'Active')): ?>
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                    data-bs-toggle="modal" data-bs-target="#processPeriodModal<?php echo $period['id']; ?>">
                                                <i class="fas fa-calculator me-1"></i> Process Salaries
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Lock Period Modal -->
                            <div class="modal fade" id="lockPeriodModal<?php echo $period['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Lock Salary Period</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <div class="modal-body">
                                                <p>Are you sure you want to lock the period <strong><?php echo htmlspecialchars($period['period_name']); ?></strong>?</p>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle me-2"></i> Locking a period prevents any further salary calculations or changes for this period.
                                                </div>
                                                <input type="hidden" name="action" value="lock_period">
                                                <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Lock Period</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Unlock Period Modal -->
                            <div class="modal fade" id="unlockPeriodModal<?php echo $period['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Unlock Salary Period</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <div class="modal-body">
                                                <p>Are you sure you want to unlock the period <strong><?php echo htmlspecialchars($period['period_name']); ?></strong>?</p>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i> Unlocking a period allows for salary calculations and adjustments to be made.
                                                </div>
                                                <input type="hidden" name="action" value="unlock_period">
                                                <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success">Unlock Period</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Delete Period Modal -->
                            <div class="modal fade" id="deletePeriodModal<?php echo $period['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Delete Salary Period</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete the period <strong><?php echo htmlspecialchars($period['period_name']); ?></strong>?</p>
                                                <div class="alert alert-danger">
                                                    <i class="fas fa-exclamation-triangle me-2"></i> This action cannot be undone. Only periods with no salary calculations can be deleted.
                                                </div>
                                                <input type="hidden" name="action" value="delete_period">
                                                <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Delete Period</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Process Period Modal -->
                            <div class="modal fade" id="processPeriodModal<?php echo $period['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Process Salaries</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <div class="modal-body">
                                                <p>Process salary calculations for the period <strong><?php echo htmlspecialchars($period['period_name']); ?></strong>?</p>
                                                
                                                <?php if ($period['is_processed']): ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle me-2"></i> This period has already been processed. Processing again will recalculate all salaries.
                                                    
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="checkbox" name="force" id="forceProcess<?php echo $period['id']; ?>">
                                                        <label class="form-check-label" for="forceProcess<?php echo $period['id']; ?>">
                                                            I understand, recalculate all salaries
                                                        </label>
                                                    </div>
                                                </div>
                                                <?php else: ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i> This will calculate salaries for all teachers based on their attendance during this period.
                                                </div>
                                                <?php endif; ?>
                                                
                                                <input type="hidden" name="action" value="process_period">
                                                <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Process Salaries</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Period Modal -->
    <div class="modal fade" id="addPeriodModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Salary Period</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_period">
                        
                        <div class="mb-3">
                            <label class="form-label">Period Name</label>
                            <input type="text" class="form-control" name="period_name" value="<?php echo htmlspecialchars($suggestedPeriod['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $suggestedPeriod['start_date']; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $suggestedPeriod['end_date']; ?>" required>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> The suggested dates are based on your salary period settings (<?php echo ucfirst($periodType); ?>).
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Period</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 