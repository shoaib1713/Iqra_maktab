<?php
session_start();
require_once 'config.php';
require 'config/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Process settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Work shifts (multiple shifts per day)
    $shifts = [];
    if (isset($_POST['shift_start']) && isset($_POST['shift_end'])) {
        foreach ($_POST['shift_start'] as $key => $start_time) {
            if (!empty($start_time) && !empty($_POST['shift_end'][$key])) {
                $shifts[] = [
                    'start' => $start_time,
                    'end' => $_POST['shift_end'][$key],
                    'min_hours' => isset($_POST['shift_min_hours'][$key]) ? floatval($_POST['shift_min_hours'][$key]) : 0
                ];
            }
        }
    }
    $shifts_json = json_encode($shifts);
    
    // Thresholds
    $late_threshold = isset($_POST['late_threshold']) ? intval($_POST['late_threshold']) : 15;
    $early_exit_threshold = isset($_POST['early_exit_threshold']) ? intval($_POST['early_exit_threshold']) : 15;
    
    // Weekend days (convert array to comma-separated string)
    $weekend_days = isset($_POST['weekend_days']) ? implode(',', $_POST['weekend_days']) : '0,6';
    
    // Features
    $geofencing_enabled = isset($_POST['geofencing_enabled']) ? 1 : 0;
    $auto_punch_out = isset($_POST['auto_punch_out']) ? 1 : 0;
    $multiple_shifts_enabled = isset($_POST['multiple_shifts_enabled']) ? 1 : 0;
    $warn_incomplete_hours = isset($_POST['warn_incomplete_hours']) ? 1 : 0;
    
    // Update settings
    $settings = [
        'work_shifts' => $shifts_json,
        'late_threshold_minutes' => $late_threshold,
        'early_exit_threshold_minutes' => $early_exit_threshold,
        'weekend_days' => $weekend_days,
        'geofencing_enabled' => $geofencing_enabled,
        'auto_punch_out' => $auto_punch_out,
        'multiple_shifts_enabled' => $multiple_shifts_enabled,
        'warn_incomplete_hours' => $warn_incomplete_hours
    ];
    
    // Update each setting
    $success = true;
    foreach ($settings as $key => $value) {
        // Check if setting exists
        $checkSql = "SELECT setting_key FROM attendance_settings WHERE setting_key = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("s", $key);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update existing setting
            $sql = "UPDATE attendance_settings SET setting_value = ? WHERE setting_key = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $value, $key);
            $result = $stmt->execute();
        } else {
            // Insert new setting
            $description = "";
            switch ($key) {
                case 'work_shifts':
                    $description = "Multiple work shifts configuration";
                    break;
                case 'multiple_shifts_enabled':
                    $description = "Enable multiple punch in/out per day";
                    break;
                case 'warn_incomplete_hours':
                    $description = "Warn users when punching out with incomplete hours";
                    break;
                default:
                    $description = "Attendance setting";
            }
            
            $sql = "INSERT INTO attendance_settings (setting_key, setting_value, description) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $key, $value, $description);
            $result = $stmt->execute();
        }
        
        if (!$result) {
            $success = false;
        }
    }
    
    if ($success) {
        $_SESSION['success_message'] = "Attendance settings updated successfully";
    } else {
        $_SESSION['error_message'] = "Failed to update some settings";
    }
    
    // Redirect to prevent form resubmission
    header("Location: attendance_settings.php");
    exit();
}

// Get current settings
$sql = "SELECT setting_key, setting_value, description FROM attendance_settings";
$result = $conn->query($sql);

$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = [
        'value' => $row['setting_value'],
        'description' => $row['description']
    ];
}

// Default values if settings are not found
$default_settings = [
    'work_shifts' => ['value' => '[{"start":"09:00","end":"17:00","min_hours":8}]', 'description' => 'Work shifts configuration'],
    'late_threshold_minutes' => ['value' => '15', 'description' => 'Minutes after shift start time to mark as late'],
    'early_exit_threshold_minutes' => ['value' => '15', 'description' => 'Minutes before shift end time to mark as early exit'],
    'weekend_days' => ['value' => '0,6', 'description' => 'Days of week that are weekends (0=Sunday, 6=Saturday)'],
    'geofencing_enabled' => ['value' => '1', 'description' => 'Whether location-based attendance is enforced'],
    'auto_punch_out' => ['value' => '1', 'description' => 'Automatically punch out users at shift end time if not done manually'],
    'multiple_shifts_enabled' => ['value' => '1', 'description' => 'Enable multiple punch in/out per day'],
    'warn_incomplete_hours' => ['value' => '1', 'description' => 'Warn users when punching out with incomplete hours']
];

// Merge with defaults for any missing settings
foreach ($default_settings as $key => $default) {
    if (!isset($settings[$key])) {
        $settings[$key] = $default;
    }
}

// Parse work shifts from JSON
$work_shifts = json_decode($settings['work_shifts']['value'], true);
if (!$work_shifts || !is_array($work_shifts) || empty($work_shifts)) {
    $work_shifts = [
        ['start' => '09:00', 'end' => '17:00', 'min_hours' => 8]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Settings - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        .settings-card {
            transition: all 0.3s ease;
        }
        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .shift-row {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            position: relative;
        }
        .remove-shift {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #dc3545;
            cursor: pointer;
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
                        <h5 class="fw-bold text-primary"><i class="fas fa-cog me-2"></i> Attendance Settings</h5>
                        <p class="text-muted">Configure attendance rules and parameters.</p>
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
                
                <!-- Settings Form -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow-sm settings-card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-sliders-h me-2"></i> Configure Attendance Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <div class="d-flex align-items-center justify-content-between mb-3">
                                                <h6 class="fw-bold text-primary mb-0">
                                                    <i class="fas fa-clock me-2"></i> Work Shifts
                                                </h6>
                                                <button type="button" id="addShiftBtn" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-plus-circle me-1"></i> Add Shift
                                                </button>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" name="multiple_shifts_enabled" 
                                                       id="multiple_shifts_enabled" <?php echo isset($settings['multiple_shifts_enabled']) && $settings['multiple_shifts_enabled']['value'] == '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="multiple_shifts_enabled">
                                                    Enable Multiple Shifts (Multiple Punch In/Out Per Day)
                                                </label>
                                                <div>
                                                    <small class="text-muted">When enabled, users can punch in and out multiple times per day for different shifts</small>
                                                </div>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" name="warn_incomplete_hours" 
                                                       id="warn_incomplete_hours" <?php echo isset($settings['warn_incomplete_hours']) && $settings['warn_incomplete_hours']['value'] == '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="warn_incomplete_hours">
                                                    Warn Users About Incomplete Hours
                                                </label>
                                                <div>
                                                    <small class="text-muted">Alert users when they're punching out before completing the minimum required hours</small>
                                                </div>
                                            </div>
                                            
                                            <div id="shiftsContainer">
                                                <?php foreach ($work_shifts as $index => $shift): ?>
                                                <div class="shift-row">
                                                    <?php if ($index > 0): ?>
                                                    <span class="remove-shift" title="Remove Shift"><i class="fas fa-times-circle"></i></span>
                                                    <?php endif; ?>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Shift Start Time</label>
                                                                <input type="time" class="form-control" name="shift_start[]" 
                                                                       value="<?php echo $shift['start']; ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Shift End Time</label>
                                                                <input type="time" class="form-control" name="shift_end[]" 
                                                                       value="<?php echo $shift['end']; ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Minimum Hours Required</label>
                                                                <input type="number" class="form-control" name="shift_min_hours[]" 
                                                                       value="<?php echo isset($shift['min_hours']) ? $shift['min_hours'] : '8'; ?>"
                                                                       step="0.5" min="0.5" max="12" required>
                                                                <small class="text-muted">Minimum hours to complete (used for warning)</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <h6 class="fw-bold text-primary mb-3">
                                                <i class="fas fa-exclamation-triangle me-2"></i> Attendance Thresholds
                                            </h6>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Late Arrival Threshold (minutes)</label>
                                                <input type="number" class="form-control" name="late_threshold" 
                                                       value="<?php echo $settings['late_threshold_minutes']['value']; ?>" 
                                                       min="1" max="120" required>
                                                <small class="text-muted"><?php echo $settings['late_threshold_minutes']['description']; ?></small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Early Exit Threshold (minutes)</label>
                                                <input type="number" class="form-control" name="early_exit_threshold" 
                                                       value="<?php echo $settings['early_exit_threshold_minutes']['value']; ?>" 
                                                       min="1" max="120" required>
                                                <small class="text-muted"><?php echo $settings['early_exit_threshold_minutes']['description']; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <h6 class="fw-bold text-primary mb-3">
                                                <i class="fas fa-calendar-week me-2"></i> Weekend Configuration
                                            </h6>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Weekend Days</label>
                                                <div class="d-flex flex-wrap">
                                                    <?php 
                                                    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                                    $weekend_days_array = explode(',', $settings['weekend_days']['value']);
                                                    
                                                    foreach ($days as $key => $day):
                                                    ?>
                                                    <div class="form-check me-4 mb-2">
                                                        <input class="form-check-input" type="checkbox" name="weekend_days[]" 
                                                               value="<?php echo $key; ?>" id="day<?php echo $key; ?>"
                                                               <?php echo in_array((string)$key, $weekend_days_array) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="day<?php echo $key; ?>">
                                                            <?php echo $day; ?>
                                                        </label>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <small class="text-muted"><?php echo $settings['weekend_days']['description']; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <h6 class="fw-bold text-primary mb-3">
                                                <i class="fas fa-toggle-on me-2"></i> Additional Features
                                            </h6>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" name="geofencing_enabled" 
                                                       id="geofencing_enabled" <?php echo $settings['geofencing_enabled']['value'] == '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="geofencing_enabled">
                                                    Enable Geofencing for Attendance
                                                </label>
                                                <div>
                                                    <small class="text-muted"><?php echo $settings['geofencing_enabled']['description']; ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" name="auto_punch_out" 
                                                       id="auto_punch_out" <?php echo $settings['auto_punch_out']['value'] == '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="auto_punch_out">
                                                    Auto Punch-Out at End of Shift
                                                </label>
                                                <div>
                                                    <small class="text-muted"><?php echo $settings['auto_punch_out']['description']; ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                <button type="reset" class="btn btn-outline-secondary">
                                                    <i class="fas fa-undo me-2"></i> Reset
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save me-2"></i> Save Settings
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Help Section -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card shadow-sm bg-light">
                            <div class="card-body">
                                <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i> About Multiple Shifts Attendance</h6>
                                <ul class="mb-0">
                                    <li><strong>Multiple Shifts:</strong> Configure multiple work shifts in a single day. Users can punch in and out multiple times.</li>
                                    <li><strong>Minimum Hours:</strong> Set minimum required hours for each shift to ensure compliance.</li>
                                    <li><strong>Early Exit Warning:</strong> Alert users when they try to punch out before completing their required hours.</li>
                                    <li><strong>Attendance Tracking:</strong> The system tracks total hours worked across multiple shifts per day.</li>
                                    <li><strong>Shift Assignment:</strong> Punch in/out is automatically assigned to the closest matching shift time.</li>
                                </ul>
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
            const form = document.querySelector('form');
            const shiftsContainer = document.getElementById('shiftsContainer');
            const addShiftBtn = document.getElementById('addShiftBtn');
            
            // Add new shift
            addShiftBtn.addEventListener('click', function() {
                const newShift = document.createElement('div');
                newShift.className = 'shift-row';
                newShift.innerHTML = `
                    <span class="remove-shift" title="Remove Shift"><i class="fas fa-times-circle"></i></span>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Shift Start Time</label>
                                <input type="time" class="form-control" name="shift_start[]" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Shift End Time</label>
                                <input type="time" class="form-control" name="shift_end[]" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Minimum Hours Required</label>
                                <input type="number" class="form-control" name="shift_min_hours[]" value="8" 
                                       step="0.5" min="0.5" max="12" required>
                                <small class="text-muted">Minimum hours to complete (used for warning)</small>
                            </div>
                        </div>
                    </div>
                `;
                shiftsContainer.appendChild(newShift);
                
                // Add event listener to new remove button
                const removeBtn = newShift.querySelector('.remove-shift');
                removeBtn.addEventListener('click', function() {
                    newShift.remove();
                });
            });
            
            // Remove shift (for existing remove buttons)
            document.querySelectorAll('.remove-shift').forEach(btn => {
                btn.addEventListener('click', function() {
                    this.closest('.shift-row').remove();
                });
            });
            
            // Form validation
            form.addEventListener('submit', function(event) {
                // Validate weekend days
                const weekendDays = document.querySelectorAll('input[name="weekend_days[]"]:checked');
                if (weekendDays.length === 0) {
                    alert('Please select at least one weekend day');
                    event.preventDefault();
                    return;
                }
                
                // Validate shifts
                const shifts = document.querySelectorAll('.shift-row');
                if (shifts.length === 0) {
                    alert('Please add at least one work shift');
                    event.preventDefault();
                    return;
                }
                
                // Validate shift times
                let hasTimeError = false;
                shifts.forEach(shift => {
                    const startTime = shift.querySelector('input[name="shift_start[]"]').value;
                    const endTime = shift.querySelector('input[name="shift_end[]"]').value;
                    
                    if (startTime >= endTime) {
                        hasTimeError = true;
                    }
                });
                
                if (hasTimeError) {
                    alert('Shift end time must be after shift start time');
                    event.preventDefault();
                    return;
                }
            });
            
            // Toggle shift functionality
            document.getElementById('multiple_shifts_enabled').addEventListener('change', function() {
                document.getElementById('shiftSettingsSection').style.display = this.checked ? 'block' : 'none';
            });
        });
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html> 