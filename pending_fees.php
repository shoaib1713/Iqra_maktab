<?php
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            require_once 'config.php';
            require 'config/db.php';

            if (!isset($_SESSION['user_id'])) {
                header("Location: index.php");
                exit();
            }

            $user_role = $_SESSION['role']; // 'admin' or 'teacher'
            $user_id = $_SESSION['user_id'];
            $user_name = $_SESSION['user_name'];

            // Define academic year range dynamically
            $year = date("Y");
            $startYear = $year - 1;
            $endYear = $year;
            $startMonth = ACEDEMIC_START_MONTH;
            $endMonth = ACEDEMIC_END_MONTH;

            // Handle filters from advanced search
            $teacher_filter = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : '';
            $class_filter = isset($_GET['class']) ? $_GET['class'] : '';
            $amount_min = isset($_GET['amount_min']) ? $_GET['amount_min'] : '';
            $amount_max = isset($_GET['amount_max']) ? $_GET['amount_max'] : '';
            $academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : "$startYear-$endYear";

            // Override date range if academic year is set
            if (!empty($academic_year)) {
                list($startYear, $endYear) = explode('-', $academic_year);
            }

            // Get all teachers for filter dropdown
            $teachersQuery = "SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name";
            $teachersResult = $conn->query($teachersQuery);
            $teachers = [];
            while ($teacher = $teachersResult->fetch_assoc()) {
                $teachers[] = $teacher;
            }

            // Base query
            $query = "SELECT 
                s.id,
                u.name as teacher_name, 
                s.name, 
                s.class, 
                s.phone, 
                s.annual_fees,
                COALESCE(SUM(f.amount), 0) AS paid_amount 
            FROM students s 
            LEFT JOIN fees f ON s.id = f.student_id 
                AND ((f.year = ? AND f.month >= ?) OR (f.year = ? AND f.month <= ?)) 
                AND f.status = 'paid'
            LEFT JOIN users u ON u.id = s.assigned_teacher
            WHERE s.is_deleted = 0";

            // Add filters
            $params = [$startYear, $startMonth, $endYear, $endMonth];
            $types = "iiii";

            // Add teacher filter
            if ($user_role == 'teacher') {
                $query .= " AND s.assigned_teacher = ?";
                $params[] = $user_id;
                $types .= "i";
            } else if (!empty($teacher_filter)) {
                $query .= " AND s.assigned_teacher = ?";
                $params[] = $teacher_filter;
                $types .= "i";
            }

            // Add class filter
            if (!empty($class_filter)) {
                $query .= " AND s.class = ?";
                $params[] = $class_filter;
                $types .= "s";
            }

            $query .= " GROUP BY s.id, s.name, s.class, s.phone";

            // Add amount filter (apply after GROUP BY)
            if (!empty($amount_min) || !empty($amount_max)) {
                $query .= " HAVING 1=1";
                
                if (!empty($amount_min)) {
                    $query .= " AND (s.annual_fees - COALESCE(SUM(f.amount), 0)) >= ?";
                    $params[] = $amount_min;
                    $types .= "d";
                }
                
                if (!empty($amount_max)) {
                    $query .= " AND (s.annual_fees - COALESCE(SUM(f.amount), 0)) <= ?";
                    $params[] = $amount_max;
                    $types .= "d";
                }
            }

            // Order by pending amount (descending)
            $query .= " ORDER BY (s.annual_fees - COALESCE(SUM(f.amount), 0)) DESC";

            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            // Calculate total pending and total paid for summary
            $totalPending = 0;
            $totalPaid = 0;
            $totalStudentsPending = 0;
            $rows = [];

            while ($row = $result->fetch_assoc()) {
                $row['pending_amount'] = $row['annual_fees'] - $row['paid_amount'];
                $totalPending += $row['pending_amount'];
                $totalPaid += $row['paid_amount'];
                if ($row['pending_amount'] > 0) {
                    $totalStudentsPending++;
                }
                $rows[] = $row;
            }

            // Reset the result pointer
            $page_title = "Pending Fees";

            // Generate title for CSV export
            $export_title = "Pending_Fees_" . date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Fees - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
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
                    <span class="navbar-brand ms-2">Pending Fees Management</span>
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
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <i class="fas fa-filter me-2"></i> Advanced Search
                                </h5>
                            </div>
                            <div class="card-body">
                                <form action="" method="GET" class="row g-3">
                                    <!-- Academic Year Filter -->
                                    <div class="col-md-3">
                                        <label class="form-label">Academic Year</label>
                                        <select name="academic_year" class="form-select">
                                            <option value="">All Years</option>
                                            <?php
                                            $currentYear = date('Y');
                                            for ($i = 0; $i < 5; $i++) {
                                                $year = $currentYear - $i;
                                                $nextYear = $year + 1;
                                                $selected = ($academic_year == "$year-$nextYear") ? 'selected' : '';
                                                echo "<option value='$year-$nextYear' $selected>$year-$nextYear</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Teacher Filter -->
                                    <div class="col-md-3">
                                        <label class="form-label">Teacher</label>
                                        <select name="teacher_id" class="form-select">
                                            <option value="">All Teachers</option>
                                            <?php foreach ($teachers as $teacher): ?>
                                                <option value="<?= $teacher['id'] ?>" <?= ($teacher_filter == $teacher['id']) ? 'selected' : '' ?>>
                                                    <?= $teacher['name'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Class Filter -->
                                    <div class="col-md-2">
                                        <label class="form-label">Class</label>
                                        <select name="class" class="form-select">
                                            <option value="">All Classes</option>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <option value="<?= $i ?>" <?= ($class_filter == $i) ? 'selected' : '' ?>>
                                                    Class <?= $i ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Pending Amount Range -->
                                    <div class="col-md-2">
                                        <label class="form-label">Min Amount</label>
                                        <input type="number" name="amount_min" class="form-control" placeholder="Min" value="<?= $amount_min ?>">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Max Amount</label>
                                        <input type="number" name="amount_max" class="form-control" placeholder="Max" value="<?= $amount_max ?>">
                                    </div>
                                    
                                    <!-- Search Button -->
                                    <div class="col-12 mt-4">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <a href="pending_fees.php" class="btn btn-light">
                                                <i class="fas fa-sync-alt me-1"></i> Reset
                                            </a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search me-1"></i> Search
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card text-center h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="fw-bold text-dark">₹ <?php echo number_format($totalPending) ?></h5>
                                <p class="text-muted mb-0">Total Pending Amount</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center h-100 shadow-sm bg-danger bg-opacity-75 text-white">
                            <div class="card-body">
                                <h5 class="fw-bold"><?php echo $totalStudentsPending; ?></h5>
                                <p class="mb-0">Students with Pending Fees</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center h-100 shadow-sm bg-success bg-opacity-75 text-white">
                            <div class="card-body">
                                <h5 class="fw-bold">₹ <?php echo number_format($totalPaid); ?></h5>
                                <p class="mb-0">Total Collected</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Data Table -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="fas fa-rupee-sign me-2"></i> Pending Fees List
                        </h5>
                        <div>
                            <a href="fees_collection.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus me-1"></i> Collect Fees
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
               <div class="table-responsive">
                            <table id="pendingFeesTable" class="table table-striped table-hover">
                                <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                        <th>Student Name</th>
                                <th>Class</th>
                                <th>Phone</th>
                                        <th>Annual Fees</th>
                                <th>Paid Amount</th>
                                <th>Pending Amount</th>
                                        <th>Teacher</th>
                                        <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                                    <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td><?= $row['id'] ?></td>
                                        <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($row['class'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>₹ <?= number_format($row['annual_fees']) ?></td>
                                        <td>₹ <?= number_format($row['paid_amount']) ?></td>
                                        <td>
                                            <span class="badge rounded-pill p-2 <?= ($row['pending_amount'] > 0) ? 'bg-danger' : 'bg-success' ?>">
                                                ₹ <?= number_format($row['pending_amount']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($row['teacher_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <a href="fees_collection.php?student_id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </a>
                                            <a href="student_history.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-history"></i>
                                            </a>
                                        </td>
                                </tr>
                                    <?php endforeach; ?>
                        </tbody>
                    </table>
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
            
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebarWrapper.classList.remove('toggled');
                });
            }
            
            // Initialize DataTable
            $('#pendingFeesTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel me-1"></i> Excel',
                        className: 'btn btn-sm btn-success',
                        title: '<?= $export_title ?>'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                        className: 'btn btn-sm btn-danger',
                        title: '<?= $export_title ?>'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print me-1"></i> Print',
                        className: 'btn btn-sm btn-secondary',
                        title: '<?= $export_title ?>'
                    }
                ],
                "order": [[6, "desc"]], // Order by pending amount
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "pageLength": 25,
                "responsive": true
            });
        });
    </script>
</body>
</html>