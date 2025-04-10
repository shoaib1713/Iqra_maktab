<?php
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            require_once 'config.php';
            require 'config/db.php';
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit();
        }
        // Fetch teachers from the database
$teacherQuery = $conn->prepare("SELECT id, name FROM users WHERE role='teacher' AND is_active = 1 AND is_deleted = 0");
$teacherQuery->execute();
$result = $teacherQuery->get_result();
$teachers = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Cheque Details - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        .required-field::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        .cheque-entry {
            transition: all 0.3s ease;
            border-left: 4px solid #0d6efd !important;
        }
        .cheque-entry:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1) !important;
        }
        .custom-file-upload {
            position: relative;
            overflow: hidden;
        }
        .custom-file-upload input[type="file"] {
            position: absolute;
            font-size: 100px;
            opacity: 0;
            right: 0;
            top: 0;
            cursor: pointer;
        }
        .preview-container {
            width: 100%;
            height: 150px;
            border: 1px dashed #dee2e6;
            margin-top: 10px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            overflow: hidden;
        }
        .preview-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
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
                    <span class="navbar-brand ms-2">Cheque Management</span>
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
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="mb-0 fw-bold text-primary">
                                            <i class="fas fa-money-check me-2"></i> Add New Cheque Details
                                        </h5>
                                    </div>
                                    <div class="col-auto">
                                        <a href="cheque_details.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-list me-1"></i> View All Cheques
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <form id="chequeForm" method="POST" action="insert.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                                    <input type="hidden" name="action" value="add_cheque">
                                    <div id="chequeEntries">
                                        <div class="cheque-entry mb-4 p-4 rounded">
                                            <div class="row g-3">
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label required-field">Cheque Year</label>
                                                    <select name="cheque_year[]" class="form-select" required>
                                                        <option value="">Select Year</option>
                                                        <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                                                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                    <div class="invalid-feedback">Please select a year</div>
                                                </div>
                                                
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label required-field">Cheque Month</label>
                                                    <select name="cheque_month[]" class="form-select" required>
                                                        <option value="">Select Month</option>
                                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                                        <option value="<?php echo $m; ?>"><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                    <div class="invalid-feedback">Please select a month</div>
                                                </div>
                                                
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label required-field">Cheque Given Date</label>
                                                    <input type="date" name="cheque_given_date[]" class="form-control" required>
                                                    <div class="invalid-feedback">Please select a date</div>
                                                </div>
                                                
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label required-field">Bank Name</label>
                                                    <input type="text" name="bank_name[]" class="form-control" placeholder="Enter bank name" required>
                                                    <div class="invalid-feedback">Please enter bank name</div>
                                                </div>
                                                
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label required-field">Cheque Number</label>
                                                    <input type="text" name="cheque_number[]" class="form-control" placeholder="Enter cheque number" required>
                                                    <div class="invalid-feedback">Please enter cheque number</div>
                                                </div>
                                                
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label required-field">Amount (₹)</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">₹</span>
                                                        <input type="number" name="cheque_amount[]" class="form-control" placeholder="0.00" step="0.01" min="1" required>
                                                    </div>
                                                    <div class="invalid-feedback">Please enter a valid amount</div>
                                                </div>
                                                
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label required-field">Given To</label>
                                                    <select class="form-select" name="cheque_handover_teacher[]" required>
                                                        <option value="">Select Teacher</option>
                                                        <?php foreach($teachers as $teacher): ?>
                                                        <option value="<?= $teacher['id']; ?>"><?= $teacher['name']; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="invalid-feedback">Please select a teacher</div>
                                                </div>
                                                
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status[]" class="form-select">
                                                        <option value="pending">Pending</option>
                                                        <option value="cleared">Cleared</option>
                                                        <option value="bounced">Bounced</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label required-field">Cheque Photo</label>
                                                    <input type="file" name="cheque_photo[]" class="form-control cheque-photo" accept="image/*" required>
                                                    <div class="preview-container mt-2">
                                                        <span class="text-muted">Image preview will appear here</span>
                                                    </div>
                                                    <div class="invalid-feedback">Please upload a cheque image</div>
                                                </div>
                                                
                                                <div class="col-12 text-end">
                                                    <button type="button" class="btn btn-outline-danger remove-entry">
                                                        <i class="fas fa-trash me-1"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex mb-4">
                                        <button type="button" id="addMore" class="btn btn-secondary me-2">
                                            <i class="fas fa-plus me-1"></i> Add Another Cheque
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Save All Cheques
                                        </button>
                                    </div>
                                </form>
                                
                                <div id="responseMessage"></div>
                                <div id="debug" class="mt-4 d-none">
                                    <div class="card">
                                        <div class="card-header bg-dark text-white">
                                            Debug Information
                                        </div>
                                        <div class="card-body">
                                            <pre id="debugInfo" class="bg-light p-3 rounded"></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Sidebar toggle functionality
        $("#menu-toggle").click(function(e) {
            e.preventDefault();
            $("#wrapper").toggleClass("toggled");
        });
        
        // Add more entries
        $("#addMore").click(function() {
            let newEntry = $(".cheque-entry:first").clone();
            newEntry.find("input").val("");
            newEntry.find("select").val("");
            newEntry.find("input[type='file']").val("");
            newEntry.find(".preview-container").html('<span class="text-muted">Image preview will appear here</span>');
            $("#chequeEntries").append(newEntry);
            
            // Reinitialize event listeners for the new entry
            initializeFilePreviewListeners();
        });

        // Remove entry
        $(document).on("click", ".remove-entry", function() {
            if ($(".cheque-entry").length > 1) {
                $(this).closest(".cheque-entry").remove();
            } else {
                alert("At least one entry is required!");
            }
        });

        // Image preview functionality
        function initializeFilePreviewListeners() {
            $(".cheque-photo").off('change').on('change', function(e) {
                const file = e.target.files[0];
                const previewContainer = $(this).closest('.col-md-12').find('.preview-container');
                
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewContainer.html('<img src="' + e.target.result + '" alt="Cheque Preview" style="max-height: 150px;">');
                    }
                    reader.readAsDataURL(file);
                } else {
                    previewContainer.html('<span class="text-muted">Image preview will appear here</span>');
                }
            });
        }
        
        initializeFilePreviewListeners();
    });
    </script>
</body>

</html>