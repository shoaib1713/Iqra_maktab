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

// Function to get user name
function getUserName($userId, $conn) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['name'];
    }
    return "Unknown";
}

// Initialize filters
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$month = isset($_GET['month']) ? $_GET['month'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build search query conditions
$conditions = [];
if (!empty($year)) $conditions[] = "YEAR(cd.cheque_given_date) = '$year'";
if (!empty($month)) $conditions[] = "MONTH(cd.cheque_given_date) = '$month'";
if (!empty($status)) {
    if ($status == 'cleared') {
        $conditions[] = "cd.is_cleared = 1";
    } elseif ($status == 'uncleared') {
        $conditions[] = "cd.is_cleared = 0";
    } elseif ($status == 'bounced') {
        $conditions[] = "cd.is_bounced = 1";
    }
}
if (!empty($search)) {
    $conditions[] = "(cd.cheque_number LIKE '%$search%' OR cd.bank_name LIKE '%$search%' OR u.name LIKE '%$search%')";
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Pagination setup
$limit = 15; // Number of records per page
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch cheque details with pagination
$sql = "SELECT cd.*, u.name as handover_teacher_name
        FROM cheque_details cd
        LEFT JOIN users u ON cd.cheque_handover_teacher = u.id
        $whereClause
        ORDER BY cd.cheque_given_date DESC 
        LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Get total records for pagination
$totalSql = "SELECT COUNT(*) as total FROM cheque_details cd
             LEFT JOIN users u ON cd.cheque_handover_teacher = u.id
             $whereClause";
$totalResult = $conn->query($totalSql);
$total_records = $totalResult->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get summary statistics
$statsSql = "SELECT 
    COUNT(*) as total_cheques,
    SUM(cheque_amount) as total_amount,
    SUM(CASE WHEN is_cleared = 1 THEN 1 ELSE 0 END) as cleared_count,
    SUM(CASE WHEN is_bounced = 1 THEN 1 ELSE 0 END) as bounced_count,
    SUM(CASE WHEN is_cleared = 0 AND is_bounced = 0 THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN is_cleared = 1 THEN cheque_amount ELSE 0 END) as cleared_amount,
    SUM(CASE WHEN is_bounced = 1 THEN cheque_amount ELSE 0 END) as bounced_amount,
    SUM(CASE WHEN is_cleared = 0 AND is_bounced = 0 THEN cheque_amount ELSE 0 END) as pending_amount
FROM cheque_details cd
$whereClause";
$statsResult = $conn->query($statsSql);
$stats = $statsResult->fetch_assoc();

// Generate title for export
$export_title = "Cheque_Records_" . date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cheque Details - MAKTAB-E-IQRA</title>
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
    <style>
        .cheque-image {
            max-width: 100px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .cheque-image:hover {
            transform: scale(1.05);
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 5px 10px;
        }
        .modal-body img {
            max-width: 100%;
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
                                    <i class="fas fa-filter me-2"></i> Filter Cheque Records
                                </h5>
                            </div>
                            <div class="card-body">
                                <form action="" method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Year</label>
                                        <select name="year" class="form-select">
                                            <option value="">All Years</option>
                                            <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                                                <option value="<?php echo $y; ?>" <?php echo ($year == $y) ? 'selected' : '' ?>>
                                                    <?php echo $y; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Month</label>
                                        <select name="month" class="form-select">
                                            <option value="">All Months</option>
                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                <option value="<?php echo $m; ?>" <?php echo ($month == $m) ? 'selected' : '' ?>>
                                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="">All Statuses</option>
                                            <option value="cleared" <?php echo ($status == 'cleared') ? 'selected' : '' ?>>Cleared</option>
                                            <option value="uncleared" <?php echo ($status == 'uncleared') ? 'selected' : '' ?>>Pending</option>
                                            <option value="bounced" <?php echo ($status == 'bounced') ? 'selected' : '' ?>>Bounced</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Search (Cheque No/Bank/Teacher)</label>
                                        <input type="text" name="search" class="form-control" placeholder="Enter search term" value="<?php echo $search; ?>">
                                    </div>
                                    <div class="col-md-12 d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-1"></i> Apply Filters
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Stats Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card text-center h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="fw-bold text-dark"><?php echo $stats['total_cheques']; ?></h5>
                                <p class="text-muted mb-0">Total Cheques</p>
                                <h6 class="fw-bold mt-2">₹ <?php echo number_format($stats['total_amount'] ?? 0); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center h-100 shadow-sm bg-success bg-opacity-75 text-white">
                            <div class="card-body">
                                <h5 class="fw-bold"><?php echo $stats['cleared_count']; ?></h5>
                                <p class="mb-0">Cleared Cheques</p>
                                <h6 class="fw-bold mt-2">₹ <?php echo number_format($stats['cleared_amount'] ?? 0); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center h-100 shadow-sm bg-warning bg-opacity-75 text-dark">
                            <div class="card-body">
                                <h5 class="fw-bold"><?php echo $stats['pending_count']; ?></h5>
                                <p class="mb-0">Pending Cheques</p>
                                <h6 class="fw-bold mt-2">₹ <?php echo number_format($stats['pending_amount'] ?? 0); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center h-100 shadow-sm bg-danger bg-opacity-75 text-white">
                            <div class="card-body">
                                <h5 class="fw-bold"><?php echo $stats['bounced_count']; ?></h5>
                                <p class="mb-0">Bounced Cheques</p>
                                <h6 class="fw-bold mt-2">₹ <?php echo number_format($stats['bounced_amount'] ?? 0); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Cheque Records Table -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="fas fa-money-check-alt me-2"></i> Cheque Records
                        </h5>
                        <div>
                            <a href="add_cheque_details.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus me-1"></i> Add New Cheque
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="chequeTable" class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Handover Teacher</th>
                                        <th>Cheque No.</th>
                                        <th>Bank</th>
                                        <th>Amount</th>
                                        <th>Cheque Date</th>
                                        <th>Image</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): 
                                        $statusBadge = '';
                                        if ($row['is_cleared'] == 1) {
                                            $statusBadge = '<span class="badge bg-success status-badge">Cleared</span>';
                                        } elseif ($row['is_bounced'] == 1) {
                                            $statusBadge = '<span class="badge bg-danger status-badge">Bounced</span>';
                                        } else {
                                            $statusBadge = '<span class="badge bg-warning text-dark status-badge">Pending</span>';
                                        }
                                        
                                        $imagePath = !empty($row['cheque_image']) ? $row['cheque_photo'] : 'assets/images/no-image.png';
                                    ?>
                                    <tr id="row_<?= $row['id'] ?>">
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold"><?php echo $row['handover_teacher_name'] ?? 'N/A'; ?></span>
                                                <small class="text-muted">Handover Teacher</small>
                                            </div>
                                        </td>
                                        <td><?php echo $row['cheque_number']; ?></td>
                                        <td><?php echo $row['bank_name']; ?></td>
                                        <td><span class="fw-bold">₹ <?php echo number_format($row['cheque_amount'], 2); ?></span></td>
                                        <td><?php echo date("d M Y", strtotime($row['cheque_given_date'])); ?></td>
                                        <td>
                                            <img src="<?php echo $imagePath; ?>" class="cheque-image img-thumbnail" data-bs-toggle="modal" data-bs-target="#imageModal" data-src="<?php echo $imagePath; ?>" alt="Cheque Image">
                                        </td>
                                        <td><?php echo $statusBadge; ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($row['is_cleared'] == 0 && $row['is_bounced'] == 0): ?>
                                                <button class="btn btn-success btn-sm mark-cleared" data-id="<?= $row['id'] ?>" title="Mark as Cleared">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm mark-bounced" data-id="<?= $row['id'] ?>" title="Mark as Bounced">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-info btn-sm view-details" data-id="<?= $row['id'] ?>" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm delete-btn" data-id="<?= $row['id'] ?>" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cheque Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" id="modalImage" class="img-fluid" alt="Cheque Image">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cheque Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cheque Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    <!-- Details will be loaded here via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
            $('#chequeTable').DataTable({
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
                "pageLength": 15,
                "responsive": true
            });
            
            // Image modal handler
            document.querySelectorAll('.cheque-image').forEach(img => {
                img.addEventListener('click', function() {
                    document.getElementById('modalImage').src = this.getAttribute('data-src');
                });
            });
            
            // Mark cheque as cleared
            document.querySelectorAll('.mark-cleared').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    if (confirm('Are you sure you want to mark this cheque as cleared?')) {
                        updateChequeStatus(id, 'cleared');
                    }
                });
            });
            
            // Mark cheque as bounced
            document.querySelectorAll('.mark-bounced').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    if (confirm('Are you sure you want to mark this cheque as bounced?')) {
                        updateChequeStatus(id, 'bounced');
                    }
                });
            });
            
            // View cheque details
            document.querySelectorAll('.view-details').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    fetch(`get_cheque_details.php?id=${id}`)
                        .then(response => response.text())
                        .then(data => {
                            document.getElementById('detailsContent').innerHTML = data;
                            const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
                            detailsModal.show();
                        })
                        .catch(error => console.error('Error:', error));
                });
            });
            
            // Delete cheque
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    if (confirm('Are you sure you want to delete this cheque record?')) {
                        fetch('delete_cheque.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `id=${id}`
                        })
                        .then(response => response.text())
                        .then(data => {
                            if (data.trim() === 'success') {
                                document.getElementById(`row_${id}`).remove();
                                alert('Cheque record deleted successfully.');
                            } else {
                                alert('Failed to delete the record.');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred during deletion.');
                        });
                    }
                });
            });
            
            // Function to update cheque status
            function updateChequeStatus(id, status) {
                fetch('update_table.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `cheque_id=${id}&status=${status}&action=update_cheque_clear_bounce_status`
                })
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === 'success') {
                        alert(`Cheque marked as ${status} successfully.`);
                        location.reload();
                    } else {
                        alert('Failed to update the status.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating status.');
                });
            }
        });
    </script>
</body>
</html>