<?php
session_start();
require_once 'config.php';
require 'config/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: restrict_user.php?page=Salary Settings&message=This page is restricted to administrators only.");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Process settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'update_settings') {
        // Update each setting
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $settingKey = str_replace('setting_', '', $key);
                $settingValue = trim($value);
                
                $updateSql = "UPDATE salary_settings SET setting_value = ? WHERE setting_key = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("ss", $settingValue, $settingKey);
                $updateStmt->execute();
            }
        }
        
        $_SESSION['success_message'] = "Salary settings updated successfully.";
        header("Location: salary_settings.php");
        exit();
    }
    
    if ($action === 'add_deduction_rule' || $action === 'update_deduction_rule') {
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        $rule_name = isset($_POST['rule_name']) ? trim($_POST['rule_name']) : '';
        $percentage = isset($_POST['percentage']) ? floatval($_POST['percentage']) : 0;
        $hours_threshold = isset($_POST['hours_threshold']) ? floatval($_POST['hours_threshold']) : null;
        $deduction_type = isset($_POST['deduction_type']) ? trim($_POST['deduction_type']) : 'percentage';
        $fixed_amount = isset($_POST['fixed_amount']) ? floatval($_POST['fixed_amount']) : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validate input
        $errors = [];
        
        if (empty($rule_name)) {
            $errors[] = "Rule name is required";
        }
        
        if ($deduction_type === 'percentage' && ($percentage <= 0 || $percentage > 100)) {
            $errors[] = "Percentage must be between 0 and 100";
        }
        
        if ($deduction_type === 'fixed' && $fixed_amount <= 0) {
            $errors[] = "Fixed amount must be greater than 0";
        }
        
        if (empty($errors)) {
            if ($action === 'add_deduction_rule') {
                // Insert new rule
                $sql = "INSERT INTO salary_deduction_rules 
                        (rule_name, percentage, hours_threshold, deduction_type, fixed_amount, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sddsdi", $rule_name, $percentage, $hours_threshold, $deduction_type, $fixed_amount, $is_active);
                $result = $stmt->execute();
                
                if ($result) {
                    $_SESSION['success_message'] = "Deduction rule added successfully";
                } else {
                    $_SESSION['error_message'] = "Failed to add deduction rule";
                }
            } else {
                // Update existing rule
                $sql = "UPDATE salary_deduction_rules 
                        SET rule_name = ?, 
                            percentage = ?, 
                            hours_threshold = ?, 
                            deduction_type = ?, 
                            fixed_amount = ?, 
                            is_active = ? 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sddsdii", $rule_name, $percentage, $hours_threshold, $deduction_type, $fixed_amount, $is_active, $rule_id);
                $result = $stmt->execute();
                
                if ($result) {
                    $_SESSION['success_message'] = "Deduction rule updated successfully";
                } else {
                    $_SESSION['error_message'] = "Failed to update deduction rule";
                }
            }
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
        
        header("Location: salary_settings.php");
        exit();
    }
    
    if ($action === 'delete_deduction_rule') {
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        
        // Delete rule
        $sql = "DELETE FROM salary_deduction_rules WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $rule_id);
        $result = $stmt->execute();
        
        if ($result) {
            $_SESSION['success_message'] = "Deduction rule deleted successfully";
        } else {
            $_SESSION['error_message'] = "Failed to delete deduction rule";
        }
        
        header("Location: salary_settings.php");
        exit();
    }
}

// Get current settings
$settingsSql = "SELECT * FROM salary_settings ORDER BY id";
$settingsResult = $conn->query($settingsSql);
$settings = [];
while ($setting = $settingsResult->fetch_assoc()) {
    $settings[] = $setting;
}

// Get deduction rules
$rulesSql = "SELECT * FROM salary_deduction_rules ORDER BY hours_threshold";
$rulesResult = $conn->query($rulesSql);
$deductionRules = [];
while ($rule = $rulesResult->fetch_assoc()) {
    $deductionRules[] = $rule;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Settings - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .settings-card {
            transition: all 0.3s ease;
        }
        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .setting-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        .setting-item:last-child {
            border-bottom: none;
        }
        .setting-description {
            color: #6c757d;
            font-size: 0.85rem;
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
                    <div class="col-md-12">
                        <h5 class="fw-bold text-primary"><i class="fas fa-cogs me-2"></i> Salary Settings</h5>
                        <p class="text-muted">Configure global salary settings for all teachers.</p>
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
                
                <!-- General Settings -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card shadow-sm settings-card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-sliders-h me-2"></i> General Salary Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_settings">
                                    
                                    <?php foreach ($settings as $setting): ?>
                                    <div class="setting-item">
                                        <div class="row align-items-center">
                                            <div class="col-md-5">
                                                <label for="setting_<?php echo $setting['setting_key']; ?>" class="form-label fw-bold">
                                                    <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                                </label>
                                                <p class="setting-description mb-0"><?php echo $setting['description']; ?></p>
                                            </div>
                                            <div class="col-md-7">
                                                <?php if ($setting['setting_key'] === 'enable_deductions' || $setting['setting_key'] === 'notification_enabled' || $setting['setting_key'] === 'auto_process_salary'): ?>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" 
                                                        id="setting_<?php echo $setting['setting_key']; ?>" 
                                                        name="setting_<?php echo $setting['setting_key']; ?>" 
                                                        value="1" <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="setting_<?php echo $setting['setting_key']; ?>">
                                                        <?php echo $setting['setting_value'] == '1' ? 'Enabled' : 'Disabled'; ?>
                                                    </label>
                                                </div>
                                                <?php elseif ($setting['setting_key'] === 'salary_period_type'): ?>
                                                <select class="form-select" id="setting_<?php echo $setting['setting_key']; ?>" name="setting_<?php echo $setting['setting_key']; ?>">
                                                    <option value="monthly" <?php echo $setting['setting_value'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                                    <option value="bi-weekly" <?php echo $setting['setting_value'] === 'bi-weekly' ? 'selected' : ''; ?>>Bi-Weekly</option>
                                                    <option value="weekly" <?php echo $setting['setting_value'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                                </select>
                                                <?php elseif ($setting['setting_key'] === 'default_monthly_days' || $setting['setting_key'] === 'deduction_per_minute'): ?>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" 
                                                        id="setting_<?php echo $setting['setting_key']; ?>" 
                                                        name="setting_<?php echo $setting['setting_key']; ?>" 
                                                        value="<?php echo $setting['setting_value']; ?>"
                                                        min="1" step="1">
                                                    <span class="input-group-text">
                                                        <?php echo $setting['setting_key'] === 'default_monthly_days' ? 'days' : '₹/min'; ?>
                                                    </span>
                                                </div>
                                                <?php else: ?>
                                                <input type="text" class="form-control" 
                                                    id="setting_<?php echo $setting['setting_key']; ?>" 
                                                    name="setting_<?php echo $setting['setting_key']; ?>" 
                                                    value="<?php echo $setting['setting_value']; ?>">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Save Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card shadow-sm settings-card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-info-circle me-2"></i> Settings Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-lightbulb me-2"></i> Changing these settings will affect how salaries are calculated for all teachers.
                                </div>
                                
                                <h6 class="fw-bold mb-3">Important Notes:</h6>
                                <ul class="small">
                                    <li><strong>Salary Calculation Day:</strong> Day of the month when automatic salary calculations are triggered.</li>
                                    <li><strong>Minimum Working Hours:</strong> Required hours per day for full salary.</li>
                                    <li><strong>Working Days per Week:</strong> Number of workdays in a week (typically 5).</li>
                                    <li><strong>Overtime Multiplier:</strong> Rate for hours worked beyond standard hours.</li>
                                    <li><strong>Salary Period Type:</strong> Defines how often salaries are calculated and paid.</li>
                                </ul>
                                
                                <div class="alert alert-warning mt-3 mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Changes to these settings will only affect future salary calculations, not past or current ones.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Deduction Rules -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-minus-circle me-2"></i> Salary Deduction Rules
                                </h5>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                                    <i class="fas fa-plus me-1"></i> Add New Rule
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Rule Name</th>
                                                <th>Hours Threshold</th>
                                                <th>Deduction Type</th>
                                                <th>Deduction Value</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($deductionRules)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No deduction rules found</td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($deductionRules as $rule): ?>
                                                <tr>
                                                    <td><?php echo $rule['rule_name']; ?></td>
                                                    <td>
                                                        <?php 
                                                        if ($rule['hours_threshold'] !== null) {
                                                            echo 'Under ' . $rule['hours_threshold'] . ' hours';
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo ucfirst($rule['deduction_type']); ?></td>
                                                    <td>
                                                        <?php 
                                                        if ($rule['deduction_type'] === 'percentage') {
                                                            echo $rule['percentage'] . '%';
                                                        } else {
                                                            echo '₹' . number_format($rule['fixed_amount'], 2);
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($rule['is_active']): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary edit-rule-btn" 
                                                                data-bs-toggle="modal" data-bs-target="#editRuleModal"
                                                                data-rule='<?php echo json_encode($rule); ?>'>
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                data-bs-toggle="modal" data-bs-target="#deleteRuleModal<?php echo $rule['id']; ?>">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                
                                                <!-- Delete Modal -->
                                                <div class="modal fade" id="deleteRuleModal<?php echo $rule['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Delete Deduction Rule</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <form method="POST" action="">
                                                                <div class="modal-body">
                                                                    <p>Are you sure you want to delete the rule: <strong><?php echo $rule['rule_name']; ?></strong>?</p>
                                                                    <p class="text-danger">
                                                                        <i class="fas fa-exclamation-triangle me-1"></i> 
                                                                        This action cannot be undone.
                                                                    </p>
                                                                    <input type="hidden" name="action" value="delete_deduction_rule">
                                                                    <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-danger">Delete Rule</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Rule Modal -->
    <div class="modal fade" id="addRuleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Deduction Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_deduction_rule">
                        
                        <div class="mb-3">
                            <label class="form-label">Rule Name</label>
                            <input type="text" class="form-control" name="rule_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Hours Threshold</label>
                            <input type="number" class="form-control" name="hours_threshold" step="0.5" min="0.5">
                            <small class="text-muted">Applied when working hours are under this threshold</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Deduction Type</label>
                            <select class="form-select" name="deduction_type" id="add_deduction_type">
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="add_percentage_div">
                            <label class="form-label">Percentage (%)</label>
                            <input type="number" class="form-control" name="percentage" step="0.01" min="0" max="100" value="0">
                        </div>
                        
                        <div class="mb-3 d-none" id="add_fixed_div">
                            <label class="form-label">Fixed Amount (₹)</label>
                            <input type="number" class="form-control" name="fixed_amount" step="0.01" min="0" value="0">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="add_is_active" checked>
                            <label class="form-check-label" for="add_is_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Rule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Rule Modal -->
    <div class="modal fade" id="editRuleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Deduction Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_deduction_rule">
                        <input type="hidden" name="rule_id" id="edit_rule_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Rule Name</label>
                            <input type="text" class="form-control" name="rule_name" id="edit_rule_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Hours Threshold</label>
                            <input type="number" class="form-control" name="hours_threshold" id="edit_hours_threshold" step="0.5" min="0.5">
                            <small class="text-muted">Applied when working hours are under this threshold</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Deduction Type</label>
                            <select class="form-select" name="deduction_type" id="edit_deduction_type">
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="edit_percentage_div">
                            <label class="form-label">Percentage (%)</label>
                            <input type="number" class="form-control" name="percentage" id="edit_percentage" step="0.01" min="0" max="100">
                        </div>
                        
                        <div class="mb-3 d-none" id="edit_fixed_div">
                            <label class="form-label">Fixed Amount (₹)</label>
                            <input type="number" class="form-control" name="fixed_amount" id="edit_fixed_amount" step="0.01" min="0">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                            <label class="form-check-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Rule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle deduction type fields
            $('#add_deduction_type').change(function() {
                if ($(this).val() === 'percentage') {
                    $('#add_percentage_div').removeClass('d-none');
                    $('#add_fixed_div').addClass('d-none');
                } else {
                    $('#add_percentage_div').addClass('d-none');
                    $('#add_fixed_div').removeClass('d-none');
                }
            });
            
            $('#edit_deduction_type').change(function() {
                if ($(this).val() === 'percentage') {
                    $('#edit_percentage_div').removeClass('d-none');
                    $('#edit_fixed_div').addClass('d-none');
                } else {
                    $('#edit_percentage_div').addClass('d-none');
                    $('#edit_fixed_div').removeClass('d-none');
                }
            });
            
            // Edit rule button
            $('.edit-rule-btn').click(function() {
                const ruleData = $(this).data('rule');
                
                $('#edit_rule_id').val(ruleData.id);
                $('#edit_rule_name').val(ruleData.rule_name);
                $('#edit_hours_threshold').val(ruleData.hours_threshold);
                $('#edit_deduction_type').val(ruleData.deduction_type);
                $('#edit_percentage').val(ruleData.percentage);
                $('#edit_fixed_amount').val(ruleData.fixed_amount);
                $('#edit_is_active').prop('checked', ruleData.is_active == 1);
                
                // Show/hide appropriate fields
                if (ruleData.deduction_type === 'percentage') {
                    $('#edit_percentage_div').removeClass('d-none');
                    $('#edit_fixed_div').addClass('d-none');
                } else {
                    $('#edit_percentage_div').addClass('d-none');
                    $('#edit_fixed_div').removeClass('d-none');
                }
            });
            
            // Toggle switch labels
            $('.form-check-input').change(function() {
                if ($(this).is(':checked')) {
                    $(this).next('.form-check-label').text('Enabled');
                } else {
                    $(this).next('.form-check-label').text('Disabled');
                }
            });
            
            // Delete rule
            $('.delete-rule').click(function() {
                if (confirm('Are you sure you want to delete this rule?')) {
                    var ruleId = $(this).data('id');
                    $('#delete_rule_id').val(ruleId);
                    $('#deleteRuleForm').submit();
                }
            });
        });
    </script>
</body>
</html> 