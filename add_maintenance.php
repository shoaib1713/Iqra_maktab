<?php
session_start();
require_once 'config.php';
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Check if maintenance data exists
    if (isset($_POST['month']) && is_array($_POST['month']) && 
        isset($_POST['year']) && is_array($_POST['year']) && 
        isset($_POST['category']) && is_array($_POST['category']) && 
        isset($_POST['amount']) && is_array($_POST['amount'])) {
            
        $stmt = $conn->prepare("INSERT INTO maintenance (month, year, category, amount, comment, created_by, created_on) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisdis", $month, $year, $category, $amount, $comment, $user_id);
        
        $success = true;
        $entries_saved = 0;
        
        for ($i = 0; $i < count($_POST['month']); $i++) {
            $month = $_POST['month'][$i];
            $year = $_POST['year'][$i];
            $category = $_POST['category'][$i];
            $amount = $_POST['amount'][$i];
            $comment = isset($_POST['comment'][$i]) ? $_POST['comment'][$i] : '';
            
            // Validate required fields
            if (empty($month) || empty($year) || empty($category) || empty($amount)) {
                continue; // Skip incomplete entries
            }
            
            if ($stmt->execute()) {
                $entries_saved++;
            } else {
                $success = false;
                $error_message = "Error: " . $stmt->error;
                break;
            }
        }
        
        if ($success && $entries_saved > 0) {
            $success_message = "$entries_saved maintenance entries added successfully!";
        } elseif ($entries_saved === 0) {
            $error_message = "No valid entries were found to save.";
        }
        
        $stmt->close();
    } else {
        $error_message = "Invalid form submission.";
    }
}

// Get distinct categories for dropdown
$categoriesQuery = "SELECT DISTINCT category FROM maintenance ORDER BY category";
$categoriesResult = $conn->query($categoriesQuery);
$categories = [];
while ($category = $categoriesResult->fetch_assoc()) {
    $categories[] = $category['category'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Maintenance - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .entry-card {
            transition: all 0.3s ease;
        }
        .entry-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
        .required-field::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        #entryContainer {
            max-height: 70vh;
            overflow-y: auto;
            padding-right: 10px;
        }
        #entryContainer::-webkit-scrollbar {
            width: 6px;
        }
        #entryContainer::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        #entryContainer::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        #entryContainer::-webkit-scrollbar-thumb:hover {
            background: #555;
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
                    <span class="navbar-brand ms-2">Add Maintenance Record</span>
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
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-12 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <i class="fas fa-plus-circle me-2"></i> Add Maintenance Records
                                </h5>
                                <div>
                                    <a href="maintenance_list.php" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-arrow-left me-1"></i> Back to List
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <form action="" method="POST" id="maintenanceForm">
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <p class="text-muted mb-0">
                                                    <i class="fas fa-info-circle me-1"></i> Add multiple maintenance entries by clicking "Add Another Entry"
                                                </p>
                                                <button type="button" id="addEntryBtn" class="btn btn-primary">
                                                    <i class="fas fa-plus me-1"></i> Add Another Entry
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div id="entryContainer">
                                        <!-- Initial entry form template -->
                                        <div class="entry-card card mb-3 shadow-sm">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label required-field">Month</label>
                                                        <select name="month[]" class="form-select" required>
                                                            <option value="">Select Month</option>
                                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                                <option value="<?php echo $m; ?>"<?php echo (date('n') == $m) ? ' selected' : ''; ?>>
                                                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                                </option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label required-field">Year</label>
                                                        <select name="year[]" class="form-select" required>
                                                            <option value="">Select Year</option>
                                                            <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                                                                <option value="<?php echo $y; ?>"<?php echo (date('Y') == $y) ? ' selected' : ''; ?>>
                                                                    <?php echo $y; ?>
                                                                </option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label required-field">Category</label>
                                                        <select name="category[]" class="form-select category-select" required>
                                                            <option value="">Select Category</option>
                                                            <option value="Electricity">Electricity</option>
                                                            <option value="Water">Water</option>
                                                            <option value="Internet">Internet</option>
                                                            <option value="Rent">Rent</option>
                                                            <option value="Staff Salary">Staff Salary</option>
                                                            <option value="Repairs">Repairs</option>
                                                            <option value="Stationery">Stationery</option>
                                                            <option value="Equipment">Equipment</option>
                                                            <option value="Miscellaneous">Miscellaneous</option>
                                                            <?php foreach ($categories as $cat): 
                                                                // Skip if already in default options
                                                                if (!in_array($cat, ['Electricity', 'Water', 'Internet', 'Rent', 'Staff Salary', 'Repairs', 'Stationery', 'Equipment', 'Miscellaneous'])): 
                                                            ?>
                                                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                                            <?php 
                                                                endif;
                                                            endforeach; 
                                                            ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label required-field">Amount (₹)</label>
                                                        <input type="number" name="amount[]" class="form-control" min="0" step="0.01" placeholder="Enter amount" required>
                                                    </div>
                                                </div>
                                                
                                                <div class="row comment-container" style="display: none;">
                                                    <div class="col-md-12 mb-3">
                                                        <label class="form-label">Comment <small class="text-muted">(Required for Miscellaneous category)</small></label>
                                                        <textarea name="comment[]" class="form-control" rows="2" placeholder="Enter details about this expense"></textarea>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-12 d-flex justify-content-end">
                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-entry-btn" style="display: none;">
                                                            <i class="fas fa-trash-alt me-1"></i> Remove Entry
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4 d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="button" class="btn btn-secondary me-md-2" onclick="window.location.href='maintenance_list.php'">
                                            <i class="fas fa-times me-1"></i> Cancel
                                        </button>
                                        <button type="submit" name="submit" class="btn btn-success">
                                            <i class="fas fa-save me-1"></i> Save All Entries
                                        </button>
                                    </div>
                                </form>
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
            
            // Form handling
            const addEntryBtn = document.getElementById('addEntryBtn');
            const entryContainer = document.getElementById('entryContainer');
            const maintenanceForm = document.getElementById('maintenanceForm');
            
            // Show/hide comment field based on category selection
            $(document).on('change', '.category-select', function() {
                const category = $(this).val();
                const commentContainer = $(this).closest('.entry-card').find('.comment-container');
                const commentTextarea = commentContainer.find('textarea');
                
                if (category === 'Miscellaneous') {
                    commentContainer.show();
                    commentTextarea.attr('required', true);
                } else {
                    commentContainer.hide();
                    commentTextarea.attr('required', false);
                }
            });
            
            // Initialize first entry
            $('.category-select').trigger('change');
            
            // Add new entry
            addEntryBtn.addEventListener('click', function() {
                const entries = document.querySelectorAll('.entry-card');
                const lastEntry = entries[entries.length - 1];
                const newEntry = lastEntry.cloneNode(true);
                
                // Reset form fields
                const inputs = newEntry.querySelectorAll('input');
                inputs.forEach(input => input.value = '');
                
                const textareas = newEntry.querySelectorAll('textarea');
                textareas.forEach(textarea => textarea.value = '');
                
                // Show remove button for all entries
                const removeButtons = document.querySelectorAll('.remove-entry-btn');
                removeButtons.forEach(button => {
                    button.style.display = 'block';
                });
                
                newEntry.querySelector('.remove-entry-btn').style.display = 'block';
                
                // Check/set default month and year values
                const monthSelect = newEntry.querySelector('select[name="month[]"]');
                const yearSelect = newEntry.querySelector('select[name="year[]"]');
                if (monthSelect && yearSelect) {
                    monthSelect.value = new Date().getMonth() + 1;
                    yearSelect.value = new Date().getFullYear();
                }
                
                // Reset category-related display
                const categorySelect = newEntry.querySelector('.category-select');
                categorySelect.value = '';
                newEntry.querySelector('.comment-container').style.display = 'none';
                
                // Add the new entry to the container
                entryContainer.appendChild(newEntry);
                
                // Add remove functionality
                addRemoveEntryListeners();
                
                // Scroll to the bottom to show the new entry
                entryContainer.scrollTop = entryContainer.scrollHeight;
            });
            
            // Function to add event listeners to remove buttons
            function addRemoveEntryListeners() {
                const removeButtons = document.querySelectorAll('.remove-entry-btn');
                removeButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const entry = this.closest('.entry-card');
                        entry.classList.add('animate__fadeOut');
                        setTimeout(() => {
                            entry.remove();
                            
                            // If only one entry left, hide its remove button
                            const remainingEntries = document.querySelectorAll('.entry-card');
                            if (remainingEntries.length === 1) {
                                remainingEntries[0].querySelector('.remove-entry-btn').style.display = 'none';
                            }
                        }, 300);
                    });
                });
            }
            
            // Initialize remove buttons
            addRemoveEntryListeners();
            
            // Form validation
            maintenanceForm.addEventListener('submit', function(e) {
                let isValid = true;
                const entries = document.querySelectorAll('.entry-card');
                
                entries.forEach(entry => {
                    const month = entry.querySelector('select[name="month[]"]').value;
                    const year = entry.querySelector('select[name="year[]"]').value;
                    const category = entry.querySelector('select[name="category[]"]').value;
                    const amount = entry.querySelector('input[name="amount[]"]').value;
                    
                    if (!month || !year || !category || !amount) {
                        isValid = false;
                    }
                    
                    if (category === 'Miscellaneous') {
                        const comment = entry.querySelector('textarea[name="comment[]"]').value;
                        if (!comment.trim()) {
                            isValid = false;
                        }
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields. Comments are required for Miscellaneous category.');
                }
            });
        });
    </script>
</body>
</html>