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

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

function getUserName($userId, $conn) {
    if (!$userId) return "N/A"; // If no user assigned, return "N/A"
    
    $query = $conn->query("SELECT name FROM users WHERE id = '$userId' LIMIT 1");
    $user = $query->fetch_assoc();
    
    return $user ? $user['name'] : "Unknown";
}

// Get current year and month for default filters
$currentYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('n');

// Build search query
$searchQuery = "";
if (isset($_GET['search']) || (isset($_GET['year']) && isset($_GET['month']))) {
    $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
    $month = isset($_GET['month']) ? $_GET['month'] : date('n');
    $searchQuery = "WHERE YEAR(meeting_date) = '$year' AND MONTH(meeting_date) = '$month'";
}

// Calculate total collected fees for the selected period
$feesQuery = "SELECT SUM(amount) as total FROM meeting_fees_collection mfc
              JOIN meeting_details md ON mfc.meeting_id = md.id";
if (!empty($searchQuery)) {
    $feesQuery .= " " . $searchQuery;
}
$feesResult = $conn->query($feesQuery);
$totalFees = $feesResult->fetch_assoc()['total'] ?? 0;

// Get meeting data with pagination
$query = "SELECT id, meeting_date, student_responsibility, namaz_responsibility, 
          visit_fajar, visit_asar, visit_magrib, maktab_lock, cleanliness_ethics, 
          food_responsibility, created_at
          FROM meeting_details 
          $searchQuery
          ORDER BY meeting_date DESC";
$result = $conn->query($query);

// Count total meetings
$totalMeetings = $result->num_rows;

// Get all meetings for the period
$meetings = [];
while ($row = $result->fetch_assoc()) {
    $meetings[] = $row;
}

$page_title = "Meeting List";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting List - MAKTAB-E-IQRA</title>
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
                    <span class="navbar-brand ms-2">Meeting Management</span>
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
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <i class="fas fa-filter me-2"></i> Filter Meetings
                                </h5>
                            </div>
                            <div class="card-body">
                                <form action="" method="GET" class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Year</label>
                                        <select name="year" class="form-select">
                                            <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                                                <option value="<?php echo $y; ?>" <?php echo ($currentYear == $y) ? 'selected' : '' ?>>
                                                    <?php echo $y; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Month</label>
                                        <select name="month" class="form-select">
                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                <option value="<?php echo $m; ?>" <?php echo ($currentMonth == $m) ? 'selected' : '' ?>>
                                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <div class="d-grid gap-2 w-100">
                                            <button type="submit" name="search" value="1" class="btn btn-primary">
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
                        <div class="card p-3 text-center h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="fw-bold text-dark"><?php echo $totalMeetings; ?></h5>
                                <p class="text-muted mb-0">Total Meetings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3 text-center h-100 shadow-sm bg-primary bg-opacity-75 text-white">
                            <div class="card-body">
                                <h5 class="fw-bold"><?php echo date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)); ?></h5>
                                <p class="mb-0">Selected Period</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3 text-center h-100 shadow-sm bg-success bg-opacity-75 text-white">
                            <div class="card-body">
                                <h5 class="fw-bold">₹ <?php echo number_format($totalFees); ?></h5>
                                <p class="mb-0">Total Committee Collection</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Meeting List -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="fas fa-calendar-alt me-2"></i> Meeting List
                        </h5>
                        <div>
                            <a href="add_meeting_details.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus me-1"></i> Add Meeting
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="meetingTable" class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Student Responsibility</th>
                                        <th>Namaz Responsibility</th>
                                        <th>Daily Visits</th>
                                        <th>Committee Collection</th>
                                        <th>Maktab Lock</th>
                                        <th>Cleanliness & Ethics</th>
                                        <th>Food Responsibility</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($meetings as $meeting): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold"><?= date('d M Y', strtotime($meeting['meeting_date'])) ?></span>
                                                <div class="small text-muted"><?= date('l', strtotime($meeting['meeting_date'])) ?></div>
                                            </td>
                                            <td><?= getUserName($meeting['student_responsibility'], $conn) ?></td>
                                            <td><?= getUserName($meeting['namaz_responsibility'], $conn) ?></td>
                                            <td>
                                                <div class="badge bg-info text-dark mb-1">Fajar: <?= getUserName($meeting['visit_fajar'], $conn) ?></div><br>
                                                <div class="badge bg-warning text-dark mb-1">Asar: <?= getUserName($meeting['visit_asar'], $conn) ?></div><br>
                                                <div class="badge bg-secondary text-white">Magrib: <?= getUserName($meeting['visit_magrib'], $conn) ?></div>
                                            </td>
                                            <td>
                                                <?php
                                                $feesQuery = $conn->query("SELECT admin_id, amount FROM meeting_fees_collection WHERE meeting_id = " . $meeting['id']);
                                                $totalMeetingFees = 0;
                                                echo "<ul class='list-unstyled mb-0'>";
                                                while ($fee = $feesQuery->fetch_assoc()) {
                                                    $totalMeetingFees += $fee['amount'];
                                                    echo "<li><i class='fas fa-user-circle me-1 text-muted'></i> " . getUserName($fee['admin_id'], $conn) . " - ₹" . number_format($fee['amount']) . "</li>";
                                                }
                                                echo "</ul>";
                                                if ($totalMeetingFees > 0) {
                                                    echo "<hr class='my-1'><div class='fw-bold text-success'>Total: ₹" . number_format($totalMeetingFees) . "</div>";
                                                }
                                                ?>
                                            </td>
                                            <td><?= getUserName($meeting['maktab_lock'], $conn) ?></td>
                                            <td><?= getUserName($meeting['cleanliness_ethics'], $conn) ?></td>
                                            <td><?= getUserName($meeting['food_responsibility'], $conn) ?></td>
                                            <td>
                                                <a href="meeting_view.php?id=<?= $meeting['id'] ?>" class="btn btn-sm btn-info mb-1" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if($role === 'admin'): ?>
                                                <a href="add_meeting_details.php?edit=<?= $meeting['id'] ?>" class="btn btn-sm btn-primary mb-1" title="Edit Meeting">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php endif; ?>
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
            
            // Initialize DataTable
            $('#meetingTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel me-1"></i> Excel',
                        className: 'btn btn-sm btn-success',
                        title: 'Meetings_<?= date('F_Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)) ?>'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                        className: 'btn btn-sm btn-danger',
                        title: 'Meetings_<?= date('F_Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)) ?>'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print me-1"></i> Print',
                        className: 'btn btn-sm btn-secondary',
                        title: 'Meetings_<?= date('F_Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)) ?>'
                    }
                ],
                "order": [[0, "desc"]],
                "pageLength": 10,
                "responsive": true
            });
        });
    </script>
</body>
</html>