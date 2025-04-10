<?php
session_start();
require_once 'config.php';
require 'config/db.php';
require_once 'includes/time_utils.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: restrict_user.php?page=Manage Salary Calculations&message=This page is restricted to administrators only.");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Process calculation request
if (isset($_POST['calculate_date'])) {
    $date = $_POST['calculate_date'];
    
    // Validate date format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        // Redirect to the processing script with the date
        header("Location: process_daily_salary.php?date=$date");
        exit();
    } else {
        $_SESSION['error_message'] = "Invalid date format. Please use YYYY-MM-DD format.";
    }
}

// Calculate date ranges for recent days
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$this_week_start = date('Y-m-d', strtotime('monday this week'));
$this_week_end = date('Y-m-d', strtotime('sunday this week'));
$last_week_start = date('Y-m-d', strtotime('monday last week'));
$last_week_end = date('Y-m-d', strtotime('sunday last week'));
$this_month_start = date('Y-m-01');
$this_month_end = date('Y-m-t');

// Get calculation statistics
$statsSql = "SELECT 
             COUNT(DISTINCT teacher_id) as total_teachers,
             COUNT(DISTINCT calculation_date) as total_days,
             SUM(base_amount) as total_salary,
             SUM(deduction_amount) as total_deductions,
             SUM(final_amount) as total_final,
             MAX(calculation_date) as last_calculation_date
             FROM daily_salary_calculations";
$statsResult = $conn->query($statsSql);
$stats = $statsResult->fetch_assoc();

// Get recent calculations
$recentSql = "SELECT 
              calculation_date, 
              COUNT(DISTINCT teacher_id) as teachers_count,
              SUM(base_amount) as total_salary,
              SUM(deduction_amount) as total_deductions,
              SUM(final_amount) as total_final
              FROM daily_salary_calculations
              GROUP BY calculation_date
              ORDER BY calculation_date DESC
              LIMIT 10";
$recentResult = $conn->query($recentSql);
$recentCalculations = [];
while ($row = $recentResult->fetch_assoc()) {
    $recentCalculations[] = $row;
}

// Get days without calculations for the current month
$missingDaysSql = "SELECT d.date
                   FROM (
                       SELECT DATE_FORMAT(DATE_ADD('$this_month_start', INTERVAL n DAY), '%Y-%m-%d') AS date
                       FROM (
                           SELECT a.N + b.N * 10 + c.N * 100 AS n
                           FROM (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
                                (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b,
                                (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c
                       ) numbers
                       WHERE DATE_ADD('$this_month_start', INTERVAL n DAY) <= '$this_month_end'
                   ) d
                   LEFT JOIN (
                       SELECT DISTINCT calculation_date
                       FROM daily_salary_calculations
                   ) c ON d.date = c.calculation_date
                   WHERE c.calculation_date IS NULL 
                   AND d.date <= CURDATE()
                   ORDER BY d.date DESC";
$missingDaysResult = $conn->query($missingDaysSql);

$missingDays = [];
while ($row = $missingDaysResult->fetch_assoc()) {
    $missingDays[] = $row['date'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Salary Calculations - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .quick-action-card {
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }
        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .stats-card {
            border-left: 4px solid #4e73df;
        }
        .stats-card.green {
            border-left-color: #1cc88a;
        }
        .stats-card.red {
            border-left-color: #e74a3b;
        }
        .stats-card.yellow {
            border-left-color: #f6c23e;
        }
        .stats-icon {
            font-size: 2rem;
            opacity: 0.2;
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fc;
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
                        <h5 class="fw-bold text-primary">
                            <i class="fas fa-calculator me-2"></i> Manage Salary Calculations
                        </h5>
                        <p class="text-muted">Run and monitor daily salary calculations for teachers.</p>
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
                
                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 fw-bold">
                                    <i class="fas fa-bolt me-2"></i> Quick Actions
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <form method="POST" action="">
                                            <input type="hidden" name="calculate_date" value="<?php echo $yesterday; ?>">
                                            <button type="submit" class="btn p-0 w-100">
                                                <div class="card quick-action-card h-100">
                                                    <div class="card-body text-center p-4">
                                                        <i class="fas fa-calendar-day fa-3x text-primary mb-3"></i>
                                                        <h6 class="fw-bold">Calculate Yesterday</h6>
                                                        <p class="text-muted small mb-0"><?php echo date('d M Y', strtotime($yesterday)); ?></p>
                                                    </div>
                                                </div>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <form method="POST" action="">
                                            <input type="hidden" name="calculate_date" value="<?php echo $today; ?>">
                                            <button type="submit" class="btn p-0 w-100">
                                                <div class="card quick-action-card h-100">
                                                    <div class="card-body text-center p-4">
                                                        <i class="fas fa-calendar-check fa-3x text-success mb-3"></i>
                                                        <h6 class="fw-bold">Calculate Today</h6>
                                                        <p class="text-muted small mb-0"><?php echo date('d M Y', strtotime($today)); ?></p>
                                                    </div>
                                                </div>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="card quick-action-card h-100" data-bs-toggle="modal" data-bs-target="#customDateModal">
                                            <div class="card-body text-center p-4">
                                                <i class="fas fa-calendar-alt fa-3x text-warning mb-3"></i>
                                                <h6 class="fw-bold">Calculate Custom Date</h6>
                                                <p class="text-muted small mb-0">Pick a specific date</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="daily_salary_calculations.php" class="text-decoration-none">
                                            <div class="card quick-action-card h-100">
                                                <div class="card-body text-center p-4">
                                                    <i class="fas fa-chart-line fa-3x text-info mb-3"></i>
                                                    <h6 class="fw-bold">View Daily Salaries</h6>
                                                    <p class="text-muted small mb-0">Detailed reports</p>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card shadow-sm stats-card h-100">
                            <div class="card-body position-relative">
                                <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Teachers</div>
                                <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format($stats['total_teachers']); ?></div>
                                <i class="fas fa-users position-absolute top-50 end-0 translate-middle-y me-3 stats-icon text-primary"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card shadow-sm stats-card green h-100">
                            <div class="card-body position-relative">
                                <div class="text-xs fw-bold text-success text-uppercase mb-1">Total Salary</div>
                                <div class="h5 mb-0 fw-bold text-gray-800">₹<?php echo number_format($stats['total_final'], 2); ?></div>
                                <i class="fas fa-money-bill-wave position-absolute top-50 end-0 translate-middle-y me-3 stats-icon text-success"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card shadow-sm stats-card red h-100">
                            <div class="card-body position-relative">
                                <div class="text-xs fw-bold text-danger text-uppercase mb-1">Total Deductions</div>
                                <div class="h5 mb-0 fw-bold text-gray-800">₹<?php echo number_format($stats['total_deductions'], 2); ?></div>
                                <i class="fas fa-minus-circle position-absolute top-50 end-0 translate-middle-y me-3 stats-icon text-danger"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card shadow-sm stats-card yellow h-100">
                            <div class="card-body position-relative">
                                <div class="text-xs fw-bold text-warning text-uppercase mb-1">Total Days Calculated</div>
                                <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format($stats['total_days']); ?></div>
                                <i class="fas fa-calendar position-absolute top-50 end-0 translate-middle-y me-3 stats-icon text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Calculations & Missing Days -->
                <div class="row">
                    <div class="col-md-8 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 fw-bold">
                                    <i class="fas fa-history me-2"></i> Recent Calculations
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Teachers</th>
                                                <th>Total Salary</th>
                                                <th>Deductions</th>
                                                <th>Final Amount</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recentCalculations)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No calculations found</td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($recentCalculations as $calculation): ?>
                                                <tr>
                                                    <td><?php echo date('d M Y', strtotime($calculation['calculation_date'])); ?></td>
                                                    <td><?php echo $calculation['teachers_count']; ?></td>
                                                    <td>₹<?php echo number_format($calculation['total_salary'], 2); ?></td>
                                                    <td>₹<?php echo number_format($calculation['total_deductions'], 2); ?></td>
                                                    <td>₹<?php echo number_format($calculation['total_final'], 2); ?></td>
                                                    <td>
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="calculate_date" value="<?php echo $calculation['calculation_date']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-sync-alt"></i> Recalculate
                                                            </button>
                                                        </form>
                                                        <a href="daily_salary_calculations.php?date=<?php echo $calculation['calculation_date']; ?>" class="btn btn-sm btn-outline-info">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 fw-bold">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Missing Calculations
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($missingDays)): ?>
                                <div class="alert alert-success mb-0">
                                    <i class="fas fa-check-circle me-2"></i> No missing calculations for this month!
                                </div>
                                <?php else: ?>
                                <p class="text-muted small mb-3">The following days in this month have no salary calculations:</p>
                                <ul class="list-group">
                                    <?php foreach ($missingDays as $index => $date): ?>
                                        <?php if ($index < 7): // Limit to 7 days ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo date('d M Y (D)', strtotime($date)); ?>
                                            <form method="POST" action="">
                                                <input type="hidden" name="calculate_date" value="<?php echo $date; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-calculator me-1"></i> Calculate
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if (count($missingDays) > 7): ?>
                                <div class="mt-2 text-center">
                                    <span class="badge bg-secondary"><?php echo count($missingDays) - 7; ?> more days not shown</span>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-calculator me-2"></i> Calculation Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md">
                                <div class="bg-light p-3 rounded text-center h-100">
                                    <h6 class="text-muted mb-2">Total Teachers</h6>
                                    <h3 class="mb-0 text-primary"><?php echo $stats['total_teachers'] ?? 0; ?></h3>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="bg-light p-3 rounded text-center h-100">
                                    <h6 class="text-muted mb-2">Total Days</h6>
                                    <h3 class="mb-0 text-info"><?php echo $stats['total_days'] ?? 0; ?></h3>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="bg-light p-3 rounded text-center h-100">
                                    <h6 class="text-muted mb-2">Total Salary</h6>
                                    <h3 class="mb-0 text-success">₹<?php echo number_format($stats['total_salary'] ?? 0, 2); ?></h3>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="bg-light p-3 rounded text-center h-100">
                                    <h6 class="text-muted mb-2">Total Deductions</h6>
                                    <h3 class="mb-0 text-danger">₹<?php echo number_format($stats['total_deductions'] ?? 0, 2); ?></h3>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="bg-light p-3 rounded text-center h-100">
                                    <h6 class="text-muted mb-2">Last Calculation</h6>
                                    <h3 class="mb-0 text-primary">
                                        <?php echo $stats['last_date'] ? date('d M', strtotime($stats['last_date'])) : '--'; ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-2">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-file-export me-1"></i> Export Data
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                                            <li><a class="dropdown-item" href="#" id="exportMonthBtn"><i class="fas fa-calendar-alt me-2"></i> Current Month</a></li>
                                            <li><a class="dropdown-item" href="#" id="exportDailyBtn"><i class="fas fa-calendar-day me-2"></i> Latest Daily</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="#" id="exportPeriodBtn"><i class="fas fa-calendar-week me-2"></i> Latest Period</a></li>
                                        </ul>
                                    </div>
                                    
                                    <a href="salary_periods.php" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-calendar-alt me-1"></i> Manage Periods
                                    </a>
                                    
                                    <a href="salary_settings.php" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-cog me-1"></i> Settings
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Custom Date Modal -->
    <div class="modal fade" id="customDateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Calculate Custom Date</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Date</label>
                            <input type="date" class="form-control" name="calculate_date" required max="<?php echo date('Y-m-d'); ?>">
                            <div class="form-text">
                                Select a date to calculate or recalculate salaries for all teachers
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Calculate Salaries</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#calculationsTable').DataTable({
                order: [[0, 'desc']]
            });
            
            // Handle date validation and form submission
            $('#calculateForm').on('submit', function() {
                var date = $('#calculate_date').val();
                if (!date.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    alert('Please enter a valid date in YYYY-MM-DD format');
                    return false;
                }
                return true;
            });
            
            // Export functions
            $('#exportMonthBtn').on('click', function(e) {
                e.preventDefault();
                var currentMonth = '<?php echo date('Y-m'); ?>';
                window.location.href = 'salary_export.php?type=monthly&month=' + currentMonth;
            });
            
            $('#exportDailyBtn').on('click', function(e) {
                e.preventDefault();
                var lastDate = '<?php echo $stats['last_date'] ?? date('Y-m-d'); ?>';
                window.location.href = 'salary_export.php?type=daily&date=' + lastDate;
            });
            
            $('#exportPeriodBtn').on('click', function(e) {
                e.preventDefault();
                <?php
                // Get latest salary period
                $latestPeriodSql = "SELECT id FROM salary_periods ORDER BY end_date DESC LIMIT 1";
                $latestPeriodResult = $conn->query($latestPeriodSql);
                $latestPeriod = $latestPeriodResult->fetch_assoc();
                $latestPeriodId = $latestPeriod ? $latestPeriod['id'] : 0;
                ?>
                var periodId = <?php echo $latestPeriodId; ?>;
                if (periodId > 0) {
                    window.location.href = 'salary_export.php?type=period&period_id=' + periodId;
                } else {
                    alert('No salary periods found');
                }
            });
        });
    </script>
</body>
</html> 