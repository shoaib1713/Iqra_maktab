<?php
session_start();
require_once 'config.php';
require 'config/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: restrict_user.php?page=Manage Salary Rates&message=This page is restricted to administrators only.");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'add_rate') {
        $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;
        $hourly_rate = isset($_POST['hourly_rate']) ? floatval($_POST['hourly_rate']) : 0;
        $minimum_working_hours = isset($_POST['minimum_working_hours']) ? floatval($_POST['minimum_working_hours']) : 3.0;
        $effective_from = isset($_POST['effective_from']) ? $_POST['effective_from'] : date('Y-m-d');
        
        // Validate input
        $errors = [];
        
        if ($teacher_id <= 0) {
            $errors[] = "Please select a valid teacher";
        }
        
        if ($hourly_rate <= 0) {
            $errors[] = "Hourly rate must be greater than zero";
        }
        
        if ($minimum_working_hours <= 0) {
            $errors[] = "Minimum working hours must be greater than zero";
        }
        
        if (empty($effective_from) || !strtotime($effective_from)) {
            $errors[] = "Please enter a valid effective date";
        }
        
        if (empty($errors)) {
            // Deactivate any existing active rates for this teacher
            $deactivateSql = "UPDATE teacher_salary_rates 
                             SET is_active = 0, 
                                 effective_to = ? 
                             WHERE user_id = ? AND is_active = 1";
            $deactivateStmt = $conn->prepare($deactivateSql);
            $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($effective_from)));
            $deactivateStmt->bind_param("si", $yesterday, $teacher_id);
            $deactivateStmt->execute();
            
            // Insert new rate
            $sql = "INSERT INTO teacher_salary_rates 
                    (user_id, hourly_rate, minimum_working_hours, effective_from, is_active, created_by) 
                    VALUES (?, ?, ?, ?, 1, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iddsi", $teacher_id, $hourly_rate, $minimum_working_hours, $effective_from, $user_id);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success_message'] = "New salary rate added successfully";
            } else {
                $_SESSION['error_message'] = "Failed to add salary rate: " . $conn->error;
            }
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
        
        header("Location: manage_salary_rates.php");
        exit();
    }
    
    if ($action === 'update_rate') {
        $rate_id = isset($_POST['rate_id']) ? intval($_POST['rate_id']) : 0;
        $hourly_rate = isset($_POST['hourly_rate']) ? floatval($_POST['hourly_rate']) : 0;
        $minimum_working_hours = isset($_POST['minimum_working_hours']) ? floatval($_POST['minimum_working_hours']) : 3.0;
        $effective_from = isset($_POST['effective_from']) ? $_POST['effective_from'] : date('Y-m-d');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;
        
        // Validate input
        $errors = [];
        
        if ($rate_id <= 0) {
            $errors[] = "Invalid rate ID";
        }
        
        if ($hourly_rate <= 0) {
            $errors[] = "Hourly rate must be greater than zero";
        }
        
        if ($minimum_working_hours <= 0) {
            $errors[] = "Minimum working hours must be greater than zero";
        }
        
        if (empty($effective_from) || !strtotime($effective_from)) {
            $errors[] = "Please enter a valid effective date";
        }
        
        if (empty($errors)) {
            // If this rate is being activated, deactivate any other active rates for this teacher
            if ($is_active == 1) {
                $deactivateSql = "UPDATE teacher_salary_rates 
                                 SET is_active = 0, 
                                     effective_to = ? 
                                 WHERE user_id = ? AND is_active = 1 AND id != ?";
                $deactivateStmt = $conn->prepare($deactivateSql);
                $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($effective_from)));
                $deactivateStmt->bind_param("sii", $yesterday, $teacher_id, $rate_id);
                $deactivateStmt->execute();
            }
            
            // Update rate
            $updateSql = "UPDATE teacher_salary_rates 
                         SET hourly_rate = ?, 
                             minimum_working_hours = ?,
                             effective_from = ?, 
                             is_active = ? 
                         WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ddsii", $hourly_rate, $minimum_working_hours, $effective_from, $is_active, $rate_id);
            $result = $updateStmt->execute();
            
            if ($result) {
                $_SESSION['success_message'] = "Salary rate updated successfully";
            } else {
                $_SESSION['error_message'] = "Failed to update salary rate: " . $conn->error;
            }
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
        
        header("Location: manage_salary_rates.php");
        exit();
    }
    
    if ($action === 'delete_rate') {
        $rate_id = isset($_POST['rate_id']) ? intval($_POST['rate_id']) : 0;
        
        // Check if any salary calculations depend on this rate
        $checkSql = "SELECT COUNT(*) as count FROM teacher_salary_calculations tsc
                     JOIN salary_periods sp ON tsc.period_id = sp.id
                     JOIN teacher_salary_rates tsr ON tsr.user_id = tsc.user_id
                     WHERE tsr.id = ? AND sp.start_date >= tsr.effective_from";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $rate_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $dependentCount = $checkResult->fetch_assoc()['count'];
        
        if ($dependentCount > 0) {
            $_SESSION['error_message'] = "Cannot delete this rate as it is used in salary calculations. You can deactivate it instead.";
        } else {
            // Delete rate
            $sql = "DELETE FROM teacher_salary_rates WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $rate_id);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success_message'] = "Salary rate deleted successfully";
            } else {
                $_SESSION['error_message'] = "Failed to delete salary rate: " . $conn->error;
            }
        }
        
        header("Location: manage_salary_rates.php");
        exit();
    }
}

// Get default rate and default working hours from settings
$settingsSql = "SELECT setting_key, setting_value FROM salary_settings 
               WHERE setting_key IN ('default_hourly_rate', 'default_minimum_working_hours')";
$settingsResult = $conn->query($settingsSql);
$defaultRate = 20;
$defaultWorkingHours = 3.0;

if ($settingsResult && $settingsResult->num_rows > 0) {
    while ($row = $settingsResult->fetch_assoc()) {
        if ($row['setting_key'] == 'default_hourly_rate') {
            $defaultRate = $row['setting_value'];
        } else if ($row['setting_key'] == 'default_minimum_working_hours') {
            $defaultWorkingHours = $row['setting_value'];
        }
    }
}

// Get all teachers
$teachersSql = "SELECT id, email, name as full_name 
               FROM users WHERE role = 'teacher' ORDER BY id";
$teachersResult = $conn->query($teachersSql);
$teachers = [];
while ($teacher = $teachersResult->fetch_assoc()) {
    $teachers[] = $teacher;
}

// Get all salary rates with teacher info
$ratesSql = "SELECT tsr.*, u.email, u.name as full_name 
            FROM teacher_salary_rates tsr
            JOIN users u ON tsr.user_id = u.id
            ORDER BY u.email, tsr.effective_from DESC";
$ratesResult = $conn->query($ratesSql);
$rates = [];
while ($rate = $ratesResult->fetch_assoc()) {
    $rates[] = $rate;
}

// Group rates by teacher
$ratesByTeacher = [];
foreach ($rates as $rate) {
    $teacherId = $rate['user_id'];
    if (!isset($ratesByTeacher[$teacherId])) {
        $ratesByTeacher[$teacherId] = [
            'teacher_id' => $teacherId,
            'username' => $rate['email'],
            'full_name' => $rate['full_name'],
            'rates' => []
        ];
    }
    $ratesByTeacher[$teacherId]['rates'][] = $rate;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Salary Rates - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .rate-card {
            transition: all 0.3s ease;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border-radius: 0.5rem;
            border: none;
            margin-bottom: 1.5rem;
        }
        .rate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .card-header {
            border-radius: 0.5rem 0.5rem 0 0 !important;
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,0.075);
        }
        .teacher-name {
            font-weight: 600;
            color: #4e73df;
        }
        .rate-badge {
            font-size: 0.8rem;
        }
        .rate-item {
            border-bottom: 1px solid #f8f9fc;
            padding: 0.75rem 0;
        }
        .rate-item:last-child {
            border-bottom: none;
        }
        .rate-value {
            font-weight: 600;
            font-size: 1.1rem;
            color: #2e384d;
        }
        .rate-date {
            color: #6c757d;
            font-size: 0.85rem;
        }
        .btn-rate-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
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
                        <h5 class="fw-bold text-primary"><i class="fas fa-sliders-h me-2"></i> Manage Teacher Salary Rates</h5>
                        <p class="text-muted">Set and manage hourly rates for teachers. These rates are used to calculate salaries.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRateModal">
                            <i class="fas fa-plus me-2"></i> Add New Rate
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
                
                <!-- Teacher Salary Rates -->
                <div class="row">
                    <?php if (empty($ratesByTeacher)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No teacher salary rates found. Click the "Add New Rate" button to add one.
                        </div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($ratesByTeacher as $teacherData): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card rate-card">
                                <div class="card-header d-flex justify-content-between align-items-center py-3">
                                    <h6 class="mb-0 teacher-name">
                                        <i class="fas fa-user-tie me-2"></i> <?php echo htmlspecialchars($teacherData['full_name']); ?>
                                    </h6>
                                    <span class="text-muted small"><?php echo htmlspecialchars($teacherData['username']); ?></span>
                                </div>
                                <div class="card-body p-0">
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($teacherData['rates'] as $index => $rate): ?>
                                        <li class="list-group-item rate-item">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <span class="rate-value">₹<?php echo number_format($rate['hourly_rate'], 2); ?>/hr</span>
                                                    <?php if ($rate['is_active']): ?>
                                                    <span class="badge bg-success ms-2 rate-badge">Active</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-secondary ms-2 rate-badge">Inactive</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-rate-action edit-rate-btn" 
                                                            data-bs-toggle="modal" data-bs-target="#editRateModal"
                                                            data-rate='<?php echo json_encode($rate); ?>'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($index > 0 || !$rate['is_active']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-rate-action" 
                                                            data-bs-toggle="modal" data-bs-target="#deleteRateModal<?php echo $rate['id']; ?>">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="rate-date">
                                                <i class="fas fa-calendar-alt me-1"></i> Effective from: <?php echo date('M d, Y', strtotime($rate['effective_from'])); ?>
                                                <?php if (!is_null($rate['effective_to'])): ?>
                                                <span class="mx-1">to</span> <?php echo date('M d, Y', strtotime($rate['effective_to'])); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-1 small text-muted">
                                                <i class="fas fa-clock me-1"></i> Min. Working: <?php echo number_format($rate['minimum_working_hours'] ?? 3.0, 1); ?> hrs/day
                                            </div>
                                        </li>
                                        
                                        <!-- Delete Rate Modal -->
                                        <div class="modal fade" id="deleteRateModal<?php echo $rate['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Delete Salary Rate</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete this salary rate for <strong><?php echo htmlspecialchars($teacherData['full_name']); ?></strong>?</p>
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle me-2"></i> This action cannot be undone. If this rate is used in any salary calculations, it cannot be deleted.
                                                            </div>
                                                            <input type="hidden" name="action" value="delete_rate">
                                                            <input type="hidden" name="rate_id" value="<?php echo $rate['id']; ?>">
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Delete Rate</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Rate Modal -->
    <div class="modal fade" id="addRateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Salary Rate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_rate">
                        
                        <div class="mb-3">
                            <label class="form-label">Teacher</label>
                            <select class="form-select" name="teacher_id" required>
                                <option value="">-- Select Teacher --</option>
                                <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['full_name'] . ' (' . $teacher['email'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">
                                This will become the active rate for the selected teacher
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Hourly Rate (₹)</label>
                            <input type="number" class="form-control" name="hourly_rate" step="0.01" min="0" value="<?php echo $defaultRate; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Minimum Working Hours (per day)</label>
                            <input type="number" class="form-control" name="minimum_working_hours" step="0.5" min="0.5" value="<?php echo $defaultWorkingHours; ?>" required>
                            <small class="text-muted">
                                The minimum number of hours a teacher is expected to work each day
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Effective From</label>
                            <input type="date" class="form-control" name="effective_from" value="<?php echo date('Y-m-d'); ?>" required>
                            <small class="text-muted">
                                This is the date from which this rate will be applied
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Rate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Rate Modal -->
    <div class="modal fade" id="editRateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Salary Rate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_rate">
                        <input type="hidden" name="rate_id" id="edit_rate_id">
                        <input type="hidden" name="teacher_id" id="edit_teacher_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Teacher</label>
                            <input type="text" class="form-control" id="edit_teacher_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Hourly Rate (₹)</label>
                            <input type="number" class="form-control" name="hourly_rate" id="edit_hourly_rate" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Minimum Working Hours (per day)</label>
                            <input type="number" class="form-control" name="minimum_working_hours" id="edit_minimum_working_hours" step="0.5" min="0.5" required>
                            <small class="text-muted">
                                The minimum number of hours a teacher is expected to work each day
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Effective From</label>
                            <input type="date" class="form-control" name="effective_from" id="edit_effective_from" required>
                            <small class="text-muted">
                                This is the date from which this rate will be applied
                            </small>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                            <label class="form-check-label" for="edit_is_active">Active</label>
                            <div class="form-text">
                                If checked, this will become the active rate and any other active rates for this teacher will be deactivated
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Rate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Edit rate button
            $('.edit-rate-btn').click(function() {
                const rateData = $(this).data('rate');
                
                $('#edit_rate_id').val(rateData.id);
                $('#edit_teacher_id').val(rateData.user_id);
                $('#edit_teacher_name').val(rateData.full_name + ' (' + rateData.username + ')');
                $('#edit_hourly_rate').val(rateData.hourly_rate);
                $('#edit_minimum_working_hours').val(rateData.minimum_working_hours || 3.0);
                $('#edit_effective_from').val(rateData.effective_from);
                $('#edit_is_active').prop('checked', rateData.is_active == 1);
            });
            
            $('#editRateForm').on('hidden.bs.modal', function () {
                $('#edit_user_id, #edit_hourly_rate, #edit_minimum_working_hours, #edit_effective_from').val('');
                $('#edit_rate_id').val('');
            });
        });
    </script>
</body>
</html> 