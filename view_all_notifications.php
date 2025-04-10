<?php
session_start();
require_once 'config.php';
require 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['role'];

// Set default filter values
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_read = isset($_GET['read_status']) ? $_GET['read_status'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build the SQL query based on filters
$countSql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
$countParams = [$user_id];
$countTypes = "i";

$sql = "SELECT n.*, 
        a.title as announcement_title,
        a.content as announcement_content
        FROM notifications n
        LEFT JOIN announcements a ON n.type = 'announcement' AND n.reference_id = a.id
        WHERE n.user_id = ?";

$queryParams = [$user_id];
$queryTypes = "i";

// Apply type filter
if ($filter_type !== 'all') {
    $sql .= " AND n.type = ?";
    $countSql .= " AND type = ?";
    $queryParams[] = $filter_type;
    $countParams[] = $filter_type;
    $queryTypes .= "s";
    $countTypes .= "s";
}

// Apply read status filter
if ($filter_read !== 'all') {
    $is_read = ($filter_read === 'read') ? 1 : 0;
    $sql .= " AND n.is_read = ?";
    $countSql .= " AND is_read = ?";
    $queryParams[] = $is_read;
    $countParams[] = $is_read;
    $queryTypes .= "i";
    $countTypes .= "i";
}

// Execute count query
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$countResult = $countStmt->get_result();
$countRow = $countResult->fetch_assoc();
$total_notifications = $countRow['total'];
$total_pages = ceil($total_notifications / $per_page);

// Add order and pagination to main query
$sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
$queryParams[] = $per_page;
$queryParams[] = $offset;
$queryTypes .= "ii";

// Execute main query
$stmt = $conn->prepare($sql);
$stmt->bind_param($queryTypes, ...$queryParams);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];

while ($row = $result->fetch_assoc()) {
    // If this is an announcement notification and we have the announcement data
    if ($row['type'] === 'announcement' && !empty($row['announcement_title'])) {
        $row['title'] = $row['announcement_title'];
        $row['content'] = $row['announcement_content'];
    }
    
    // Remove unnecessary fields
    unset($row['announcement_title']);
    unset($row['announcement_content']);
    
    $notifications[] = $row;
}

// Get notification types for filter dropdown
$typeStmt = $conn->prepare("SELECT DISTINCT type FROM notifications WHERE user_id = ?");
$typeStmt->bind_param("i", $user_id);
$typeStmt->execute();
$typeResult = $typeStmt->get_result();
$notificationTypes = [];

while ($typeRow = $typeResult->fetch_assoc()) {
    $notificationTypes[] = $typeRow['type'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Notifications - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        .notification-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s ease;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.6rem;
            padding: 0.2rem 0.4rem;
        }
        .notification-dropdown {
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
        }
        .notification-item {
            border-left: 3px solid #0d6efd;
            transition: background-color 0.2s;
        }
        .notification-item:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .notification-item.unread {
            background-color: rgba(13, 110, 253, 0.1);
        }
        .notification-header {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 1020;
        }
        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
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
                    <span class="navbar-brand ms-2">All Notifications</span>
                    <div class="d-flex align-items-center">
                        <!-- Notification Icon -->
                        <div class="dropdown me-3 position-relative">
                            <a class="btn btn-outline-secondary position-relative" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge" id="notification-count">
                                    0
                                </span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end shadow notification-dropdown p-0" aria-labelledby="notificationDropdown">
                                <div class="notification-header d-flex justify-content-between align-items-center p-3 border-bottom">
                                    <h6 class="mb-0 fw-bold">Notifications</h6>
                                    <a href="#" class="text-decoration-none small mark-all-read">Mark all as read</a>
                                </div>
                                <div class="notifications-list p-0" id="notifications-container">
                                    <div class="text-center p-3 text-muted">
                                        <i class="fas fa-bell-slash me-2"></i> No notifications
                                    </div>
                                </div>
                                <div class="p-2 border-top text-center">
                                    <a href="view_all_notifications.php" class="text-decoration-none small">View all notifications</a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- User Dropdown -->
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
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-bold text-primary">
                                        <i class="fas fa-bell me-2"></i> Notification Filters
                                    </h5>
                                    <button type="button" class="btn btn-sm btn-primary mark-all-read">
                                        <i class="fas fa-check-double me-1"></i> Mark All as Read
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-5">
                                        <label for="type" class="form-label">Notification Type</label>
                                        <select class="form-select" id="type" name="type">
                                            <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                            <?php foreach ($notificationTypes as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filter_type === $type ? 'selected' : ''; ?>>
                                                <?php echo ucfirst(htmlspecialchars($type)); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label for="read_status" class="form-label">Read Status</label>
                                        <select class="form-select" id="read_status" name="read_status">
                                            <option value="all" <?php echo $filter_read === 'all' ? 'selected' : ''; ?>>All</option>
                                            <option value="unread" <?php echo $filter_read === 'unread' ? 'selected' : ''; ?>>Unread</option>
                                            <option value="read" <?php echo $filter_read === 'read' ? 'selected' : ''; ?>>Read</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-filter me-1"></i> Filter
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-bold text-primary">
                                        <i class="fas fa-list me-2"></i> Your Notifications
                                    </h5>
                                    <span class="badge bg-primary rounded-pill">
                                        <?php echo $total_notifications; ?> Total
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($notifications)): ?>
                                <div class="text-center p-5">
                                    <i class="fas fa-bell-slash text-muted fa-3x mb-3"></i>
                                    <h5 class="text-muted">No notifications found</h5>
                                    <p class="text-muted">There are no notifications matching your filter criteria.</p>
                                    <a href="view_all_notifications.php" class="btn btn-outline-primary">
                                        <i class="fas fa-sync-alt me-1"></i> Reset Filters
                                    </a>
                                </div>
                                <?php else: ?>
                                <div class="list-group list-group-flush" id="notifications-list">
                                    <?php foreach ($notifications as $notification): ?>
                                    <div class="list-group-item list-group-item-action notification-item <?php echo $notification['is_read'] == 0 ? 'unread' : ''; ?>" data-id="<?php echo $notification['id']; ?>">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <div>
                                                <small class="text-muted me-3"><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></small>
                                                <span class="badge bg-<?php echo $notification['is_read'] == 0 ? 'danger' : 'secondary'; ?> rounded-pill">
                                                    <?php echo $notification['is_read'] == 0 ? 'Unread' : 'Read'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <p class="mb-1 small">
                                            <?php 
                                                $type = $notification['type'];
                                                echo $type === 'announcement' ? 'New announcement from administration' : ucfirst($type); 
                                            ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <a href="view_notification.php?id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i> View Details
                                            </a>
                                            <a href="#" class="mark-read text-primary" data-id="<?php echo $notification['id']; ?>">
                                                <i class="fas fa-<?php echo $notification['is_read'] == 0 ? 'envelope-open' : 'envelope'; ?> me-1"></i>
                                                <?php echo $notification['is_read'] == 0 ? 'Mark as read' : 'Mark as unread'; ?>
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="d-flex justify-content-center py-3">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination">
                                            <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?type=<?php echo $filter_type; ?>&read_status=<?php echo $filter_read; ?>&page=1" aria-label="First">
                                                    <i class="fas fa-angle-double-left"></i>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?type=<?php echo $filter_type; ?>&read_status=<?php echo $filter_read; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                                    <i class="fas fa-angle-left"></i>
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            
                                            <?php
                                            $start_page = max(1, $page - 2);
                                            $end_page = min($total_pages, $page + 2);
                                            
                                            for ($i = $start_page; $i <= $end_page; $i++):
                                            ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?type=<?php echo $filter_type; ?>&read_status=<?php echo $filter_read; ?>&page=<?php echo $i; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?type=<?php echo $filter_type; ?>&read_status=<?php echo $filter_read; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                                                    <i class="fas fa-angle-right"></i>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?type=<?php echo $filter_type; ?>&read_status=<?php echo $filter_read; ?>&page=<?php echo $total_pages; ?>" aria-label="Last">
                                                    <i class="fas fa-angle-double-right"></i>
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                                <?php endif; ?>
                                
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/notification_styles.php'; ?>
    <?php include 'includes/notification_scripts.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle
            const menuToggle = document.getElementById('menu-toggle');
            const wrapper = document.getElementById('wrapper');
            
            if (menuToggle) {
                menuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    wrapper.classList.toggle('toggled');
                });
            }
            
            // Mark notification as read/unread in the main list
            $(document).on('click', '.mark-read', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $this = $(this);
                const notificationId = $this.data('id');
                const $notificationItem = $this.closest('.notification-item');
                const currentStatus = $notificationItem.hasClass('unread') ? 0 : 1;
                const newStatus = currentStatus === 0 ? 1 : 0;
                
                $.ajax({
                    url: 'api/update_notification.php',
                    type: 'POST',
                    data: {
                        notification_id: notificationId,
                        is_read: newStatus
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // If we're on the all notifications page, update the UI
                            if ($notificationItem.length) {
                                if (newStatus === 1) {
                                    $notificationItem.removeClass('unread');
                                    $this.html('<i class="fas fa-envelope me-1"></i> Mark as unread');
                                    $notificationItem.find('.badge').removeClass('bg-danger').addClass('bg-secondary').text('Read');
                                } else {
                                    $notificationItem.addClass('unread');
                                    $this.html('<i class="fas fa-envelope-open me-1"></i> Mark as read');
                                    $notificationItem.find('.badge').removeClass('bg-secondary').addClass('bg-danger').text('Unread');
                                }
                            }
                            
                            // Update the dropdown
                            fetchNotifications();
                        } else {
                            console.error('Error updating notification:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                    }
                });
            });
            
            // Handle mark all as read button in the all notifications view
            $(document).on('click', '.mark-all-read', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: 'api/mark_all_read.php',
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Refresh the page after marking all as read
                            window.location.reload();
                        } else {
                            console.error('Error marking all as read:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                    }
                });
            });
        });
    </script>
</body>
</html> 