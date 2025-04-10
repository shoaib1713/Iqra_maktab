<?php
session_start();
require_once 'config.php';
require 'config/db.php';

$startMonth = ACEDEMIC_START_MONTH;
$endMonth = ACEDEMIC_END_MONTH;

$year = date("Y");
$startYear = $year - 1;
$endYear = $year;

// Allow filtering by academic year
if (isset($_GET['academic_year']) && !empty($_GET['academic_year'])) {
    list($startYear, $endYear) = explode('-', $_GET['academic_year']);
}

$students = $conn->query("SELECT id, name, phone, annual_fees FROM students WHERE is_deleted = 0");
$studentsArray = $students->fetch_all(MYSQLI_ASSOC); // Convert result to array

$studentPayments = [];
$selectedStudent = null;
$intTotalFeesOfStudent = 0;
$intCollectedFeesOfStudentTillNow = 0;
$intPendingFeesOfStudent = 0;

// Fetch Payment History if Student is Selected
if (isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    
    // Get student details
    $studentQuery = $conn->prepare("SELECT s.*, u.name as teacher_name FROM students s LEFT JOIN users u ON s.assigned_teacher = u.id WHERE s.id = ?");
    $studentQuery->bind_param("i", $student_id);
    $studentQuery->execute();
    $selectedStudent = $studentQuery->get_result()->fetch_assoc();
    
    // Get payment history
    $historyQuery = $conn->prepare("SELECT f.*, u.name as created_by_name 
                                    FROM fees f 
                                    LEFT JOIN users u ON f.created_by = u.id 
                                    WHERE f.student_id = ? 
                                    ORDER BY f.Year DESC, f.month DESC");
    $historyQuery->bind_param("i", $student_id);
    $historyQuery->execute();
    $studentPayments = $historyQuery->get_result()->fetch_all(MYSQLI_ASSOC);

    // Filter payments by academic year for current view
    $filteredPayments = array_filter($studentPayments, function ($payment) use ($startYear, $endYear, $startMonth, $endMonth) {
        return 
            ($payment['Year'] == $startYear && $payment['month'] >= $startMonth) ||  
            ($payment['Year'] == $endYear && $payment['month'] <= $endMonth);
    });

    // Calculate collected fees
    $intCollectedFeesOfStudentTillNow = array_reduce($filteredPayments, function ($sum, $payment) {
        return $sum + $payment['amount'];
    }, 0);

    // Get student's annual fees
    $intTotalFeesOfStudent = $selectedStudent['annual_fees'];
    
    // Calculate pending fees
    $intPendingFeesOfStudent = $intTotalFeesOfStudent - $intCollectedFeesOfStudentTillNow;

    // Show warning if fees are already collected
    if (isset($_POST['amount']) && ($intCollectedFeesOfStudentTillNow + $_POST['amount'] > $intTotalFeesOfStudent)) {
        echo "<script>
            alert('{$selectedStudent['name']}\'s Total Fee for the Year: $startYear-$endYear is already collected');
            window.location.href = 'fees_collection.php?student_id=$student_id&academic_year=$startYear-$endYear';
        </script>";
    } 
}

// Handle form submission for fee collection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['student_id'])) {
    $student_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    $created_by = $_SESSION['user_id']; // Logged-in user
    $status = 'pending';

    // Insert new fees record
    $stmt = $conn->prepare("INSERT INTO fees (student_id, amount, month, Year, created_by, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iidiis", $student_id, $amount, $month, $year, $created_by, $status);
    
    if ($stmt->execute()) {
        $success = "Fees added successfully!";
        // Redirect to update the view
        header("Location: fees_collection.php?student_id=$student_id&academic_year=$startYear-$endYear");
        exit();
    } else {
        $error = "Error adding fees: " . $stmt->error;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fees Collection - MAKTAB-E-IQRA</title>
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
                    <span class="navbar-brand ms-2">Fees Collection</span>
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
                    <h4 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Fees Collection</h4>
                    
                    <form method="GET" action="fees_collection.php" class="d-flex align-items-center">
                        <?php if (isset($_GET['student_id'])): ?>
                            <input type="hidden" name="student_id" value="<?= $_GET['student_id'] ?>">
                        <?php endif; ?>
                        <select name="academic_year" class="form-select me-2" style="width:auto;">
                            <option value="">Select Academic Year</option>
                            <?php
                            $currentYear = date('Y');
                            for ($i = 0; $i < 5; $i++) {
                                $yr = $currentYear - $i;
                                $nextYr = $yr + 1;
                                $selected = (isset($_GET['academic_year']) && $_GET['academic_year'] == "$yr-$nextYr") ? 'selected' : '';
                                echo "<option value='$yr-$nextYr' $selected>$yr-$nextYr</option>";
                            }
                            ?>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                    </form>
                </div>
                
                <?php if (isset($success)) echo "<div class='alert alert-success'><i class='fas fa-check-circle me-2'></i>$success</div>"; ?>
                <?php if (isset($error)) echo "<div class='alert alert-danger'><i class='fas fa-exclamation-circle me-2'></i>$error</div>"; ?>

                <div class="row g-4">
                    <!-- Left Side: Student Selection & Fee Collection Form -->
                    <div class="col-lg-5">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-user-graduate me-2"></i>Select Student</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Search Student</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" id="search" class="form-control" placeholder="Search by name or phone" onkeyup="filterStudents()">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Select Student</label>
                                    <select id="studentList" class="form-select" onchange="loadStudentHistory(this.value)">
                                        <option value="">Select a student</option>
                                        <?php foreach ($studentsArray as $row) { ?>
                                            <option value="<?= $row['id'] ?>" <?= (isset($_GET['student_id']) && $_GET['student_id'] == $row['id']) ? 'selected' : '' ?>>
                                                <?= $row['name'] ?> (<?= $row['phone'] ?>)
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                
                                <?php if ($selectedStudent): ?>
                                <div class="mt-4">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="flex-shrink-0">
                                                    <img src="<?= $selectedStudent['photo'] ?>" alt="Student" class="rounded-circle" width="60" height="60" style="object-fit: cover;">
                                                </div>
                                                <div class="ms-3">
                                                    <h5 class="mb-0"><?= $selectedStudent['name'] ?></h5>
                                                    <p class="text-muted mb-0 small">Class: <?= $selectedStudent['class'] ?> | Phone: <?= $selectedStudent['phone'] ?></p>
                                                </div>
                                            </div>
                                            
                                            <div class="progress mb-2" style="height: 0.5rem;">
                                                <?php 
                                                $percentage = ($intTotalFeesOfStudent > 0) ? 
                                                    min(100, round(($intCollectedFeesOfStudentTillNow / $intTotalFeesOfStudent) * 100)) : 0;
                                                ?>
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?= $percentage ?>%;" 
                                                     aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                                </div>
                                            </div>
                                            
                                            <div class="row text-center g-2 mb-2">
                                                <div class="col-4">
                                                    <div class="border rounded bg-white p-2">
                                                        <p class="text-muted mb-0 small">Total Fees</p>
                                                        <h6 class="mb-0">₹<?= number_format($intTotalFeesOfStudent) ?></h6>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="border rounded bg-white p-2">
                                                        <p class="text-muted mb-0 small">Collected</p>
                                                        <h6 class="mb-0 text-success">₹<?= number_format($intCollectedFeesOfStudentTillNow) ?></h6>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="border rounded bg-white p-2">
                                                        <p class="text-muted mb-0 small">Pending</p>
                                                        <h6 class="mb-0 text-danger">₹<?= number_format($intPendingFeesOfStudent) ?></h6>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Middle: Fees Collection Form -->
                    <div class="col-lg-3">
                        <?php if ($selectedStudent): ?>
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-money-check-alt me-2"></i>Collect Fees</h5>
                            </div>
                            <div class="card-body">
                                <form action="fees_collection.php" method="POST">
                                    <input type="hidden" name="student_id" value="<?= $selectedStudent['id'] ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-rupee-sign me-1"></i>Amount</label>
                                        <input type="number" name="amount" class="form-control" required min="1" max="<?= $intPendingFeesOfStudent ?>" value="<?= min(1800, $intPendingFeesOfStudent) ?>">
                                        <div class="form-text">Max: ₹<?= number_format($intPendingFeesOfStudent) ?></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-calendar-alt me-1"></i>Month</label>
                                        <select name="month" class="form-select" required>
                                            <?php for ($i = 1; $i <= 12; $i++) { 
                                                $selected = ($i == date('n')) ? 'selected' : '';
                                            ?>
                                                <option value="<?= $i ?>" <?= $selected ?>><?= date("F", mktime(0, 0, 0, $i, 1)) ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-calendar-day me-1"></i>Year</label>
                                        <select name="year" class="form-select" required>
                                            <?php for ($y = date("Y") - 2; $y <= date("Y") + 1; $y++) { 
                                                $selected = ($y == date('Y')) ? 'selected' : '';
                                            ?>
                                                <option value="<?= $y ?>" <?= $selected ?>><?= $y ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-plus-circle me-1"></i> Collect Fees
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="card shadow-sm h-100">
                            <div class="card-body d-flex align-items-center justify-content-center text-center text-muted py-5">
                                <div>
                                    <i class="fas fa-user-graduate fa-3x mb-3"></i>
                                    <h5>Please Select a Student</h5>
                                    <p class="mb-0">Select a student to view and collect fees</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Right Side: Payment History -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Payment History</h5>
                                
                                <?php if ($selectedStudent && !empty($studentPayments)): ?>
                                <a href="fee_receipt.php?student_id=<?= $selectedStudent['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="fas fa-print me-1"></i> Print Receipt
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($selectedStudent && !empty($studentPayments)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover table-sm">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Amount</th>
                                                    <th>Month/Year</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                // Group payments by academic year
                                                $groupedPayments = [];
                                                foreach ($studentPayments as $payment) {
                                                    $py = $payment['Year'];
                                                    $pm = $payment['month'];
                                                    
                                                    // Determine academic year
                                                    $academicStartYear = ($pm >= ACEDEMIC_START_MONTH) ? $py : $py - 1;
                                                    $academicEndYear = $academicStartYear + 1;
                                                    $academicYearKey = "$academicStartYear-$academicEndYear";
                                                    
                                                    if (!isset($groupedPayments[$academicYearKey])) {
                                                        $groupedPayments[$academicYearKey] = [];
                                                    }
                                                    
                                                    $groupedPayments[$academicYearKey][] = $payment;
                                                }
                                                
                                                // Sort academic years in descending order
                                                krsort($groupedPayments);
                                                
                                                foreach ($groupedPayments as $academicYear => $payments):
                                                    $yearTotal = array_reduce($payments, function($sum, $p) { 
                                                        return $sum + $p['amount']; 
                                                    }, 0);
                                                ?>
                                                    <tr class="table-primary">
                                                        <td colspan="4" class="fw-bold">
                                                            Academic Year: <?= $academicYear ?> 
                                                            <span class="float-end">Total: ₹<?= number_format($yearTotal) ?></span>
                                                        </td>
                                                    </tr>
                                                    <?php foreach ($payments as $payment): ?>
                                                        <tr>
                                                            <td><?= date('d M Y', strtotime($payment['created_at'])) ?></td>
                                                            <td>₹<?= number_format($payment['amount']) ?></td>
                                                            <td><?= date("M", mktime(0, 0, 0, $payment['month'], 1)) ?> <?= $payment['Year'] ?></td>
                                                            <td>
                                                                <span class="badge bg-<?= $payment['status'] == 'paid' ? 'success' : 'warning' ?>">
                                                                    <?= ucfirst($payment['status']) ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php elseif ($selectedStudent): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-receipt fa-3x mb-3 text-muted"></i>
                                        <h5 class="text-muted">No Payments Found</h5>
                                        <p class="text-muted mb-0">This student has no payment records yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-info-circle fa-3x mb-3 text-muted"></i>
                                        <h5 class="text-muted">No Student Selected</h5>
                                        <p class="text-muted mb-0">Please select a student to view payment history.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
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
        });
        
        // Filter students by name or phone
        function filterStudents() {
            let input = document.getElementById("search").value.toLowerCase();
            let options = document.getElementById("studentList").options;
            
            for (let i = 1; i < options.length; i++) {
                let text = options[i].text.toLowerCase();
                if (text.includes(input)) {
                    options[i].style.display = "";
                } else {
                    options[i].style.display = "none";
                }
            }
        }
        
        // Load student history when selected
        function loadStudentHistory(studentId) {
            if (studentId) {
                // Preserve the academic year filter if it exists
                const urlParams = new URLSearchParams(window.location.search);
                const academicYear = urlParams.get('academic_year');
                
                let redirectUrl = 'fees_collection.php?student_id=' + studentId;
                if (academicYear) {
                    redirectUrl += '&academic_year=' + academicYear;
                }
                
                window.location.href = redirectUrl;
            }
        }
    </script>
</body>
</html>
