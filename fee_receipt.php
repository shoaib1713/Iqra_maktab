<?php
session_start();
require_once 'config.php';
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;
$fee_id = isset($_GET['fee_id']) ? $_GET['fee_id'] : null;
$academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';

$student_data = null;
$fee_data = null;
$receipt_no = null;

if ($fee_id) {
    // Get fee details
    $stmt = $conn->prepare("
        SELECT f.*, s.name as student_name, s.phone, s.roll_no, s.address, s.guardian_name, s.class_name
        FROM fees f 
        JOIN students s ON f.student_id = s.id
        WHERE f.id = ? AND f.is_deleted = 0
    ");
    $stmt->bind_param("i", $fee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $fee_data = $result->fetch_assoc();
        $student_id = $fee_data['student_id'];
        
        // Generate receipt number (Fee ID + Year + Random numbers)
        $timestamp = strtotime($fee_data['created_at']);
        $year = date('Y', $timestamp);
        $receipt_no = 'RCPT-' . $year . '-' . str_pad($fee_id, 4, '0', STR_PAD_LEFT);
    }
} elseif ($student_id && !empty($academic_year)) {
    // Get student details first
    $stmt = $conn->prepare("
        SELECT s.*, 
            (SELECT SUM(amount) FROM fees WHERE student_id = s.id AND academic_year = ? AND is_deleted = 0) as total_paid
        FROM students s
        WHERE s.id = ? AND s.is_deleted = 0
    ");
    $stmt->bind_param("si", $academic_year, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $student_data = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Receipt - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            #receipt-container, #receipt-container * {
                visibility: visible;
            }
            #receipt-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
        
        .receipt-wrapper {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .receipt-header {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .receipt-logo {
            max-height: 80px;
        }
        
        .receipt-title {
            font-size: 24px;
            font-weight: 700;
            color: #0d6efd;
            margin-bottom: 0.5rem;
        }
        
        .receipt-subtitle {
            font-size: 14px;
            color: #6c757d;
        }
        
        .receipt-body {
            padding: 1rem 0;
        }
        
        .receipt-info {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .divider {
            height: 1px;
            background-color: #dee2e6;
            margin: 1.5rem 0;
        }
        
        .fee-details-table th {
            background-color: #f8f9fa;
        }
        
        .receipt-footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px dashed #dee2e6;
            font-size: 14px;
            color: #6c757d;
        }
        
        .signature-area {
            margin-top: 5rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(13, 110, 253, 0.05);
            z-index: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 rounded no-print">
                <div class="container-fluid">
                    <button class="btn" id="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-brand ms-2">Fee Receipt</span>
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
                <?php if (!$fee_data && !$student_data): ?>
                <!-- Search Form -->
                <div class="row mb-4 no-print">
                    <div class="col-lg-6 col-md-8 mx-auto">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <i class="fas fa-search me-2"></i> Search Student Receipt
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="fee_receipt.php">
                                    <div class="mb-3">
                                        <label for="student_id" class="form-label">Select Student</label>
                                        <select class="form-select" id="student_id" name="student_id" required>
                                            <option value="">Select Student</option>
                                            <?php
                                            $stmt = $conn->prepare("SELECT id, name, roll_no FROM students WHERE is_deleted = 0 ORDER BY name");
                                            $stmt->execute();
                                            $studentsResult = $stmt->get_result();
                                            while ($student = $studentsResult->fetch_assoc()) {
                                                echo "<option value='{$student['id']}'>{$student['name']} (Roll No: {$student['roll_no']})</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="academic_year" class="form-label">Academic Year</label>
                                        <select class="form-select" id="academic_year" name="academic_year" required>
                                            <option value="">Select Academic Year</option>
                                            <?php
                                            $currentYear = date("Y");
                                            $startYear = $currentYear - 5;
                                            $endYear = $currentYear + 1;
                                            for ($year = $startYear; $year <= $endYear; $year++) {
                                                $academicYear = $year . "-" . ($year + 1);
                                                echo "<option value='$academicYear'>$academicYear (June - May)</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i> Search
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php elseif($student_data && !$fee_data): ?>
                <!-- Student Fee History -->
                <div class="row mb-4 no-print">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-bold text-primary">
                                        <i class="fas fa-history me-2"></i> Fee Payment History for <?php echo $student_data['name']; ?>
                                    </h5>
                                    <a href="fee_receipt.php" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-1"></i> Back
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <p><strong>Student Name:</strong> <?php echo $student_data['name']; ?></p>
                                        <p><strong>Roll No:</strong> <?php echo $student_data['roll_no']; ?></p>
                                        <p><strong>Class:</strong> <?php echo $student_data['class_name']; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Guardian:</strong> <?php echo $student_data['guardian_name']; ?></p>
                                        <p><strong>Contact:</strong> <?php echo $student_data['phone']; ?></p>
                                        <p><strong>Academic Year:</strong> <?php echo $academic_year; ?></p>
                                    </div>
                                </div>
                                
                                <?php
                                // Fetch fee records for this student and academic year
                                $stmt = $conn->prepare("
                                    SELECT f.*, u.name as created_by_name 
                                    FROM fees f
                                    LEFT JOIN users u ON f.created_by = u.id
                                    WHERE f.student_id = ? AND f.academic_year = ? AND f.is_deleted = 0
                                    ORDER BY f.created_at DESC
                                ");
                                $stmt->bind_param("is", $student_id, $academic_year);
                                $stmt->execute();
                                $fees = $stmt->get_result();
                                
                                if ($fees->num_rows > 0):
                                ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Receipt #</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Payment Mode</th>
                                                <th>Collected By</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($fee = $fees->fetch_assoc()): 
                                                $timestamp = strtotime($fee['created_at']);
                                                $year = date('Y', $timestamp);
                                                $receipt_num = 'RCPT-' . $year . '-' . str_pad($fee['id'], 4, '0', STR_PAD_LEFT);
                                            ?>
                                            <tr>
                                                <td><?php echo $receipt_num; ?></td>
                                                <td><?php echo date('d M Y', strtotime($fee['created_at'])); ?></td>
                                                <td>₹<?php echo number_format($fee['amount'], 2); ?></td>
                                                <td><?php echo ucfirst($fee['payment_mode']); ?></td>
                                                <td><?php echo $fee['created_by_name']; ?></td>
                                                <td>
                                                    <a href="fee_receipt.php?fee_id=<?php echo $fee['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-print me-1"></i> Print
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                        <tfoot class="table-secondary">
                                            <tr>
                                                <th colspan="2">Total Paid</th>
                                                <th>₹<?php echo number_format($student_data['total_paid'], 2); ?></th>
                                                <th colspan="3"></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No fee payments found for this student in the selected academic year.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Print Receipt -->
                <div class="row mb-4">
                    <div class="col-12">
                        <!-- Print & Back Buttons -->
                        <div class="mb-4 text-end no-print">
                            <a href="fee_receipt.php" class="btn btn-secondary me-2">
                                <i class="fas fa-arrow-left me-1"></i> Back
                            </a>
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="fas fa-print me-1"></i> Print Receipt
                            </button>
                        </div>
                        
                        <!-- Receipt -->
                        <div id="receipt-container" class="receipt-wrapper position-relative">
                            <div class="watermark">PAID</div>
                            
                            <div class="receipt-header text-center">
                                <div class="row align-items-center">
                                    <div class="col-md-3 text-md-start">
                                        <img src="assets/images/logo.png" alt="Logo" class="receipt-logo">
                                    </div>
                                    <div class="col-md-6">
                                        <h1 class="receipt-title">MAKTAB-E-IQRA</h1>
                                        <p class="receipt-subtitle">Islamic Educational Institute</p>
                                        <p class="receipt-subtitle mb-0">Phone: 123-456-7890 | Email: info@maktab-e-iqra.com</p>
                                    </div>
                                    <div class="col-md-3 text-md-end">
                                        <div class="badge bg-primary p-2 fs-6 mb-2">RECEIPT</div>
                                        <p class="mb-0"><strong>Receipt No:</strong> <?php echo $receipt_no; ?></p>
                                        <p class="mb-0"><strong>Date:</strong> <?php echo date('d-m-Y', strtotime($fee_data['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="receipt-body">
                                <div class="receipt-info">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Student Name:</strong> <?php echo $fee_data['student_name']; ?></p>
                                            <p class="mb-1"><strong>Roll No:</strong> <?php echo $fee_data['roll_no']; ?></p>
                                            <p class="mb-1"><strong>Class:</strong> <?php echo $fee_data['class_name']; ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Guardian's Name:</strong> <?php echo $fee_data['guardian_name']; ?></p>
                                            <p class="mb-1"><strong>Contact:</strong> <?php echo $fee_data['phone']; ?></p>
                                            <p class="mb-1"><strong>Academic Year:</strong> <?php echo $fee_data['academic_year']; ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered fee-details-table">
                                        <thead>
                                            <tr>
                                                <th width="70%">Description</th>
                                                <th width="30%" class="text-end">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Fee Payment (<?php echo $fee_data['description'] ? $fee_data['description'] : 'Regular Fee'; ?>)</td>
                                                <td class="text-end">₹<?php echo number_format($fee_data['amount'], 2); ?></td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th class="text-end">Total Amount:</th>
                                                <th class="text-end">₹<?php echo number_format($fee_data['amount'], 2); ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    <p><strong>Amount in words:</strong> <?php echo getAmountInWords($fee_data['amount']); ?></p>
                                    <p><strong>Payment Mode:</strong> <?php echo ucfirst($fee_data['payment_mode']); ?></p>
                                    <?php if(!empty($fee_data['transaction_id'])): ?>
                                    <p><strong>Transaction ID:</strong> <?php echo $fee_data['transaction_id']; ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="signature-area">
                                    <div class="row">
                                        <div class="col-6 text-center">
                                            <p class="mb-0">_________________________</p>
                                            <p>Authorized Signature</p>
                                        </div>
                                        <div class="col-6 text-center">
                                            <p class="mb-0">_________________________</p>
                                            <p>Guardian's Signature</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="receipt-footer text-center">
                                <p>This is a computer-generated receipt and doesn't require a physical signature.</p>
                                <p class="mb-0">Thank you for your payment!</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const menuToggle = document.getElementById('menu-toggle');
            const wrapper = document.getElementById('wrapper');
            
            if (menuToggle) {
                menuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    wrapper.classList.toggle('toggled');
                });
            }
        });
    </script>
</body>
</html>

<?php
function getAmountInWords($amount) {
    $ones = array(
        0 => "Zero", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four", 5 => "Five", 
        6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine", 10 => "Ten", 
        11 => "Eleven", 12 => "Twelve", 13 => "Thirteen", 14 => "Fourteen", 15 => "Fifteen", 
        16 => "Sixteen", 17 => "Seventeen", 18 => "Eighteen", 19 => "Nineteen"
    );
    $tens = array(
        2 => "Twenty", 3 => "Thirty", 4 => "Forty", 5 => "Fifty", 
        6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
    );
    $hundreds = array(
        "Hundred", "Thousand", "Lakh", "Crore"
    );
    
    if ($amount == 0) return "Zero Rupees Only";
    
    // For Indian number system
    $amount_str = strval(round($amount, 2));
    $decimal = '';
    if (strpos($amount_str, '.') !== false) {
        list($amount_str, $decimal) = explode('.', $amount_str);
    }
    
    $amount_str = str_pad($amount_str, 1, '0', STR_PAD_LEFT);
    $amount_len = strlen($amount_str);
    
    $words = '';
    $crores = $lakhs = $thousands = $hundreds_val = $tens_ones = 0;
    
    if ($amount_len > 7) {
        $crores = intval(substr($amount_str, 0, $amount_len - 7));
        $amount_str = substr($amount_str, $amount_len - 7);
        $amount_len = strlen($amount_str);
    }
    
    if ($amount_len > 5) {
        $lakhs = intval(substr($amount_str, 0, $amount_len - 5));
        $amount_str = substr($amount_str, $amount_len - 5);
        $amount_len = strlen($amount_str);
    }
    
    if ($amount_len > 3) {
        $thousands = intval(substr($amount_str, 0, $amount_len - 3));
        $amount_str = substr($amount_str, $amount_len - 3);
    }
    
    $hundreds_val = intval(substr($amount_str, 0, 1));
    $tens_ones = intval(substr($amount_str, 1));
    
    if ($crores > 0) {
        if ($crores < 20) {
            $words .= $ones[$crores] . " Crore ";
        } else {
            $words .= convertTwoDigit($crores) . " Crore ";
        }
    }
    
    if ($lakhs > 0) {
        if ($lakhs < 20) {
            $words .= $ones[$lakhs] . " Lakh ";
        } else {
            $words .= convertTwoDigit($lakhs) . " Lakh ";
        }
    }
    
    if ($thousands > 0) {
        if ($thousands < 20) {
            $words .= $ones[$thousands] . " Thousand ";
        } else {
            $words .= convertTwoDigit($thousands) . " Thousand ";
        }
    }
    
    if ($hundreds_val > 0) {
        $words .= $ones[$hundreds_val] . " Hundred ";
    }
    
    if ($tens_ones > 0) {
        if ($tens_ones < 20) {
            $words .= $ones[$tens_ones];
        } else {
            $words .= convertTwoDigit($tens_ones);
        }
    }
    
    // Add the decimal part if exists
    if (!empty($decimal)) {
        $decimal = intval($decimal);
        if ($decimal > 0) {
            $words .= " and " . ($decimal < 20 ? $ones[$decimal] : convertTwoDigit($decimal)) . " Paise";
        }
    }
    
    return $words . " Rupees Only";
}

function convertTwoDigit($amount) {
    $ones = array(
        0 => "Zero", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four", 5 => "Five", 
        6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine", 10 => "Ten", 
        11 => "Eleven", 12 => "Twelve", 13 => "Thirteen", 14 => "Fourteen", 15 => "Fifteen", 
        16 => "Sixteen", 17 => "Seventeen", 18 => "Eighteen", 19 => "Nineteen"
    );
    $tens = array(
        2 => "Twenty", 3 => "Thirty", 4 => "Forty", 5 => "Fifty", 
        6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
    );
    
    if ($amount < 20) {
        return $ones[$amount];
    }
    
    $ten = intval($amount / 10);
    $one = $amount % 10;
    
    return $tens[$ten] . ($one > 0 ? "-" . $ones[$one] : "");
}
?>
