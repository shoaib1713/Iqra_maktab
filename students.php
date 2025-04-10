<?php
session_start();
require 'config/db.php';



// Get logged-in user details
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role']; // 'admin' or 'teacher'

$teacherQuery = $conn->query("SELECT id, name FROM users where role='teacher'");
$teachers = $teacherQuery->fetch_all(MYSQLI_ASSOC);

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search Filters
$search_query = "";
$params = [];
$param_types = "";

// Base Query
$query = "SELECT s.*, u.name as teacher_name FROM students s 
          LEFT JOIN users u ON u.id = s.assigned_teacher 
          WHERE 1=1";

if ($user_role == 'teacher') {
    $query .= " AND s.assigned_teacher = ?";
    $params[] = $user_id;
    $param_types .= "i";
}

// Check if search filters exist
if (!empty($_GET['search_name'])) {
    $search_query .= " AND s.name LIKE ?";
    $params[] = "%" . $_GET['search_name'] . "%";
    $param_types .= "s";
}

if (!empty($_GET['search_phone'])) {
    $search_query .= " AND s.phone LIKE ?";
    $params[] = "%" . $_GET['search_phone'] . "%";
    $param_types .= "s";
}

if (!empty($_GET['search_teacher'])) {
    $search_query .= " AND s.assigned_teacher = ?";
    $params[] = $_GET['search_teacher'];
    $param_types .= "i";
}

if (!empty($_GET['search_status'])) {
    $search_query .= " AND s.is_deleted = ?";
    $params[] = ($_GET['search_status'] == "active") ? 0 : 1;
    $param_types .= "i";
}





// Append search conditions
$query .= $search_query;
$query .= " LIMIT ?, ?";


// Add pagination parameters
$params[] = $offset;
$params[] = $limit;
$param_types .= "ii";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();


$countQuery = "SELECT COUNT(*) as total FROM students s WHERE 1=1 " . $search_query;

if ($user_role == 'teacher') {
    $countQuery .= " AND s.assigned_teacher = ?";
}
$countStmt = $conn->prepare($countQuery);

// Extract search parameters (excluding pagination params)
$search_params = array_slice($params, 0, -2);
$search_types = substr($param_types, 0, -2);


// Bind only if parameters exist
if (!empty($search_params)) {
    $countStmt->bind_param($search_types, ...$search_params);
}

$countStmt->execute();
$countResult = $countStmt->get_result();
$totalStudents = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalStudents / $limit);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/pagination.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 rounded">
                <div class="container-fluid">
                    <button class="btn" id="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-brand ms-2">Student Management</span>
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
                    <h4 class="mb-0">Total Students: <span class="badge bg-primary"><?php echo $totalStudents; ?></span></h4>
                    <a href='add_student.php' class="btn btn-primary">
                        <i class="fas fa-plus-circle me-1"></i> Add Student
                    </a>
                </div>
                
                <!-- Search Panel -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search Students</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="students.php" class="row g-3">
                            <!-- Search by Name -->
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label">Name</label>
                                <input type="text" name="search_name" class="form-control" placeholder="Search by Name"
                                    value="<?= htmlspecialchars($_GET['search_name'] ?? '') ?>">
                            </div>

                            <!-- Search by Phone -->
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="search_phone" class="form-control" placeholder="Search by Phone"
                                    value="<?= htmlspecialchars($_GET['search_phone'] ?? '') ?>">
                            </div>

                            <!-- Search by Assigned Ulma -->
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label">Assigned Ulma</label>
                                <select name="search_teacher" class="form-select">
                                    <option value="">Select Ulma</option>
                                    <?php
                                    foreach ($teachers as $teacher) {
                                        $selected = ($_GET['search_teacher'] ?? '') == $teacher['id'] ? 'selected' : '';
                                        echo "<option value='{$teacher['id']}' {$selected}>{$teacher['name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Search by Status -->
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label">Status</label>
                                <select name="search_status" class="form-select">
                                    <option value="">Select Status</option>
                                    <option value="active" <?= ($_GET['search_status'] ?? '') == 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= ($_GET['search_status'] ?? '') == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>

                            <!-- Search & Reset Buttons -->
                            <div class="col-12 d-flex">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i> Search
                                </button>
                                <a href="students.php" class="btn btn-secondary">
                                    <i class="fas fa-redo me-1"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Students Table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover border">
                                <thead class="table-light">
                                    <tr>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Class</th>
                                        <th>Phone</th>
                                        <th>Salana Fees</th>
                                        <th>Assigned Ulma</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows == 0): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">No students found</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php while ($row = $result->fetch_assoc()): 
                                            $statusBadge = $row['is_deleted'] ? '<span class="badge bg-danger">Inactive</span>' : '<span class="badge bg-success">Active</span>';
                                            $deleteRestore = $row['is_deleted'] 
                                                ? "<a href='restore_student.php?id={$row['id']}' class='btn btn-sm btn-info' title='Restore'><i class='fas fa-redo'></i></a>" 
                                                : "<a href='delete_student.php?id={$row['id']}' class='btn btn-sm btn-danger' title='Delete' onclick='return confirm(\"Are you sure?\")'><i class='fas fa-trash'></i></a>";
                                        ?>
                                            <tr>
                                                <td><img src="<?= $row['photo']; ?>" class="student-photo" alt="Student"></td>
                                                <td><?= $row['name']; ?></td>
                                                <td><?= $row['class']; ?></td>
                                                <td><?= $row['phone']; ?></td>
                                                <td>₹ <?= number_format($row['annual_fees']); ?></td>
                                                <td><?= $row['teacher_name']; ?></td>
                                                <td><?= $statusBadge; ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href='edit_student.php?id=<?= $row['id']; ?>' class='btn btn-sm btn-primary' title='Edit'><i class='fas fa-edit'></i></a>
                                                        <?= $deleteRestore; ?>
                                                        <a href='student_history.php?id=<?= $row['id']; ?>' class='btn btn-sm btn-secondary' title='History'><i class='fas fa-history'></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-4">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($_GET['search_name']) ? '&search_name=' . urlencode($_GET['search_name']) : '' ?><?= !empty($_GET['search_phone']) ? '&search_phone=' . urlencode($_GET['search_phone']) : '' ?><?= !empty($_GET['search_teacher']) ? '&search_teacher=' . urlencode($_GET['search_teacher']) : '' ?><?= !empty($_GET['search_status']) ? '&search_status=' . urlencode($_GET['search_status']) : '' ?>">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $startPage + 4);
                            if ($endPage - $startPage < 4 && $startPage > 1) {
                                $startPage = max(1, $endPage - 4);
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?><?= !empty($_GET['search_name']) ? '&search_name=' . urlencode($_GET['search_name']) : '' ?><?= !empty($_GET['search_phone']) ? '&search_phone=' . urlencode($_GET['search_phone']) : '' ?><?= !empty($_GET['search_teacher']) ? '&search_teacher=' . urlencode($_GET['search_teacher']) : '' ?><?= !empty($_GET['search_status']) ? '&search_status=' . urlencode($_GET['search_status']) : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($_GET['search_name']) ? '&search_name=' . urlencode($_GET['search_name']) : '' ?><?= !empty($_GET['search_phone']) ? '&search_phone=' . urlencode($_GET['search_phone']) : '' ?><?= !empty($_GET['search_teacher']) ? '&search_teacher=' . urlencode($_GET['search_teacher']) : '' ?><?= !empty($_GET['search_status']) ? '&search_status=' . urlencode($_GET['search_status']) : '' ?>">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
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
    </script>
</body>
</html>
