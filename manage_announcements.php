<?php
session_start();
require_once 'config.php';
require 'config/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: restrict_user.php?page=Announcement Management&message=This page is restricted to administrators only.");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$success_message = "";
$error_message = "";

// Function to create notifications for all users when a new announcement is made
function createAnnouncementNotifications($conn, $announcement_id, $title) {
    // Get all active users
    $userQuery = "SELECT id FROM users WHERE is_active = 1 AND is_deleted = 0";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    // Check if notifications table exists, create if it doesn't
    $tableCheckQuery = "SHOW TABLES LIKE 'notifications'";
    $tableCheckStmt = $conn->prepare($tableCheckQuery);
    $tableCheckStmt->execute();
    $tableExists = $tableCheckStmt->get_result()->num_rows > 0;
    
    if (!$tableExists) {
        // Create notifications table
        $createTableQuery = "CREATE TABLE `notifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `type` varchar(50) NOT NULL,
            `reference_id` int(11) DEFAULT NULL,
            `title` varchar(255) NOT NULL,
            `content` text DEFAULT NULL,
            `is_read` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `type_reference_id` (`type`, `reference_id`),
            KEY `is_read` (`is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        
        $conn->query($createTableQuery);
    }
    
    // First, delete any existing notifications for this announcement to prevent duplicates
    try {
        $deleteStmt = $conn->prepare("DELETE FROM notifications WHERE type = 'announcement' AND reference_id = ?");
        $deleteStmt->bind_param("i", $announcement_id);
        $deleteStmt->execute();
    } catch (Exception $e) {
        error_log("Error deleting existing notifications: " . $e->getMessage());
    }
    
    // Prepare notification insertion statement
    try {
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, is_read, created_at) VALUES (?, 'announcement', ?, ?, 0, NOW())");
        
        if (!$notifStmt) {
            error_log("Error preparing notification statement: " . $conn->error);
            return;
        }
        
        // Insert notification for each user
        $successCount = 0;
        while ($user = $userResult->fetch_assoc()) {
            try {
                $notifStmt->bind_param("iis", $user['id'], $announcement_id, $title);
                $result = $notifStmt->execute();
                if ($result) {
                    $successCount++;
                } else {
                    error_log("Failed to execute notification insert for user {$user['id']}: " . $notifStmt->error);
                }
            } catch (Exception $e) {
                error_log("Error creating notification for user {$user['id']}: " . $e->getMessage());
                continue;
            }
        }
        
        error_log("Successfully created $successCount notifications for announcement #$announcement_id");
    } catch (Exception $e) {
        error_log("Error in notification creation process: " . $e->getMessage());
    }
}

// Handle Delete Request
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $announcement_id = $_GET['delete'];
    
    // Soft delete the announcement (set is_active to 0)
    $deleteStmt = $conn->prepare("UPDATE announcements SET is_active = 0, updated_by = ?, updated_at = NOW() WHERE id = ?");
    $deleteStmt->bind_param("ii", $user_id, $announcement_id);
    
    if ($deleteStmt->execute()) {
        $success_message = "Announcement has been deleted successfully.";
    } else {
        $error_message = "Failed to delete announcement. Please try again.";
    }
}

// Handle Form Submission for Create/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate input
    if (empty($title) || empty($content)) {
        $error_message = "Title and content are required.";
    } else {
        // Handle update or create
        if (isset($_POST['announcement_id']) && !empty($_POST['announcement_id'])) {
            // Update existing announcement
            $announcement_id = $_POST['announcement_id'];
            $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssiii", $title, $content, $is_active, $user_id, $announcement_id);
            
            if ($stmt->execute()) {
                $success_message = "Announcement updated successfully.";
                
                // Only create notifications if announcement is active
                if ($is_active) {
                    // First delete existing notifications for this announcement
                    $deleteNotifStmt = $conn->prepare("DELETE FROM notifications WHERE type = 'announcement' AND reference_id = ?");
                    $deleteNotifStmt->bind_param("i", $announcement_id);
                    $deleteNotifStmt->execute();
                    
                    // Create new notifications
                    createAnnouncementNotifications($conn, $announcement_id, $title);
                }
            } else {
                $error_message = "Error updating announcement: " . $stmt->error;
            }
        } else {
            // Create new announcement
            $stmt = $conn->prepare("INSERT INTO announcements (title, content, is_active, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssii", $title, $content, $is_active, $user_id);
            
            if ($stmt->execute()) {
                $announcement_id = $conn->insert_id;
                $success_message = "Announcement created successfully.";
                
                // Only create notifications if announcement is active
                if ($is_active) {
                    createAnnouncementNotifications($conn, $announcement_id, $title);
                }
            } else {
                $error_message = "Error creating announcement: " . $stmt->error;
            }
        }
    }
}

// Fetch announcements for listing
$announcementQuery = "SELECT a.*, 
                     c.name as created_by_name, 
                     u.name as updated_by_name
                     FROM announcements a
                     LEFT JOIN users c ON a.created_by = c.id
                     LEFT JOIN users u ON a.updated_by = u.id
                     WHERE a.is_active = 1
                     ORDER BY a.created_at DESC";
$stmt = $conn->prepare($announcementQuery);
$stmt->execute();
$result = $stmt->get_result();
$announcements = [];
while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
}

// Fetch announcement for editing if ID is provided
$editAnnouncement = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $editStmt = $conn->prepare("SELECT * FROM announcements WHERE id = ? AND is_active = 1");
    $editStmt->bind_param("i", $edit_id);
    $editStmt->execute();
    $editResult = $editStmt->get_result();
    
    if ($editResult->num_rows > 0) {
        $editAnnouncement = $editResult->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .announcement-card {
            transition: all 0.3s ease;
            border-left: 4px solid #0d6efd;
        }
        .announcement-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1) !important;
        }
        .required-field::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        .inactive-announcement {
            opacity: 0.7;
            border-left-color: #6c757d;
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
                    <span class="navbar-brand ms-2">Announcement Management</span>
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
                    <div class="col-lg-4 order-lg-2 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <?php echo $editAnnouncement ? '<i class="fas fa-edit me-2"></i> Edit Announcement' : '<i class="fas fa-plus me-2"></i> Create Announcement'; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="needs-validation" novalidate>
                                    <?php if ($editAnnouncement): ?>
                                        <input type="hidden" name="announcement_id" value="<?php echo $editAnnouncement['id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="title" class="form-label required-field">Title</label>
                                        <input type="text" class="form-control" id="title" name="title" required
                                            value="<?php echo $editAnnouncement ? htmlspecialchars($editAnnouncement['title']) : ''; ?>">
                                        <div class="invalid-feedback">
                                            Please enter a title for the announcement.
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="content" class="form-label required-field">Content</label>
                                        <textarea class="form-control" id="content" name="content" rows="4" required><?php echo $editAnnouncement ? htmlspecialchars($editAnnouncement['content']) : ''; ?></textarea>
                                        <div class="invalid-feedback">
                                            Please enter the announcement content.
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                            <?php echo (!$editAnnouncement || $editAnnouncement['is_active'] == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> 
                                            <?php echo $editAnnouncement ? 'Update Announcement' : 'Create Announcement'; ?>
                                        </button>
                                        <?php if ($editAnnouncement): ?>
                                            <a href="manage_announcements.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-plus me-1"></i> Create New
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8 order-lg-1">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-bold text-primary">
                                        <i class="fas fa-bullhorn me-2"></i> Announcements
                                    </h5>
                                    <a href="dashboard.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($announcements)): ?>
                                    <p class="text-muted text-center">No active announcements available. Create your first announcement!</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="announcementsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Title</th>
                                                    <th>Content</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($announcements as $announcement): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                            $content = htmlspecialchars($announcement['content']);
                                                            echo (strlen($content) > 100) ? substr($content, 0, 100) . '...' : $content;
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <small><?php echo date('d M Y', strtotime($announcement['created_at'])); ?></small><br>
                                                        <small class="text-muted">by <?php echo htmlspecialchars($announcement['created_by_name']); ?></small>
                                                    </td>
                                                    <td>
                                                        <a href="manage_announcements.php?edit=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="#" class="btn btn-sm btn-outline-danger" 
                                                           onclick="confirmDelete(<?php echo $announcement['id']; ?>, '<?php echo addslashes($announcement['title']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the announcement <strong id="deleteAnnouncementTitle"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
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
            
            // DataTable initialization
            if ($.fn.DataTable) {
                $('#announcementsTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [5, 10, 25, 50],
                    responsive: true,
                    language: {
                        search: "<i class='fas fa-search'></i>",
                        searchPlaceholder: "Search announcements..."
                    }
                });
            }
            
            // Form validation
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
            
            // Notifications functionality
            function fetchNotifications() {
                $.ajax({
                    url: 'api/get_notifications.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            updateNotificationUI(response.data);
                        } else {
                            console.error('Error fetching notifications:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                    }
                });
            }
            
            function updateNotificationUI(notifications) {
                const container = $('#notifications-container');
                const countBadge = $('#notification-count');
                const unreadCount = notifications.filter(n => n.is_read === '0').length;
                
                // Update badge count
                countBadge.text(unreadCount);
                if (unreadCount > 0) {
                    countBadge.removeClass('d-none');
                } else {
                    countBadge.addClass('d-none');
                }
                
                // Clear container
                container.empty();
                
                // If no notifications
                if (notifications.length === 0) {
                    container.html(`<div class="text-center p-3 text-muted">
                                      <i class="fas fa-bell-slash me-2"></i> No notifications
                                    </div>`);
                    return;
                }
                
                // Add notifications to container
                notifications.slice(0, 5).forEach(function(notification) {
                    const isUnread = notification.is_read === '0' ? 'unread' : '';
                    const notificationTime = new Date(notification.created_at);
                    const timeAgo = formatTimeAgo(notificationTime);
                    
                    container.append(`
                        <div class="notification-item p-3 border-bottom ${isUnread}" data-id="${notification.id}">
                            <div class="d-flex justify-content-between">
                                <strong>${notification.title}</strong>
                                <small class="text-muted">${timeAgo}</small>
                            </div>
                            <p class="mb-0 small text-truncate">${notification.type === 'announcement' ? 'New announcement' : notification.type}</p>
                            <div class="mt-1">
                                <a href="#" class="mark-read small text-primary" data-id="${notification.id}">
                                    ${notification.is_read === '0' ? 'Mark as read' : 'Mark as unread'}
                                </a>
                            </div>
                        </div>
                    `);
                });
            }
            
            function formatTimeAgo(date) {
                const now = new Date();
                const diffInSeconds = Math.floor((now - date) / 1000);
                
                if (diffInSeconds < 60) {
                    return 'Just now';
                }
                
                const diffInMinutes = Math.floor(diffInSeconds / 60);
                if (diffInMinutes < 60) {
                    return `${diffInMinutes} min${diffInMinutes > 1 ? 's' : ''} ago`;
                }
                
                const diffInHours = Math.floor(diffInMinutes / 60);
                if (diffInHours < 24) {
                    return `${diffInHours} hour${diffInHours > 1 ? 's' : ''} ago`;
                }
                
                const diffInDays = Math.floor(diffInHours / 24);
                if (diffInDays < 30) {
                    return `${diffInDays} day${diffInDays > 1 ? 's' : ''} ago`;
                }
                
                const diffInMonths = Math.floor(diffInDays / 30);
                return `${diffInMonths} month${diffInMonths > 1 ? 's' : ''} ago`;
            }
            
            // Mark notification as read/unread
            $(document).on('click', '.mark-read', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const notificationId = $(this).data('id');
                const currentStatus = $(this).text().includes('Mark as read') ? 0 : 1;
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
            
            // Mark all notifications as read
            $(document).on('click', '.mark-all-read', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: 'api/mark_all_read.php',
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            fetchNotifications();
                        } else {
                            console.error('Error marking all as read:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                    }
                });
            });
            
            // Click on notification to view details
            $(document).on('click', '.notification-item', function(e) {
                if (!$(e.target).hasClass('mark-read')) {
                    const notificationId = $(this).data('id');
                    
                    // Mark as read first
                    $.ajax({
                        url: 'api/update_notification.php',
                        type: 'POST',
                        data: {
                            notification_id: notificationId,
                            is_read: 1
                        },
                        dataType: 'json',
                        success: function(response) {
                            // If announcement, show the announcement detail
                            window.location.href = 'view_notification.php?id=' + notificationId;
                        }
                    });
                }
            });
            
            // Initially fetch notifications
            fetchNotifications();
            
            // Periodically update notifications (every 30 seconds)
            setInterval(fetchNotifications, 30000);
        });
        
        // Delete confirmation
        function confirmDelete(id, title) {
            document.getElementById('deleteAnnouncementTitle').textContent = title;
            document.getElementById('confirmDeleteBtn').href = 'manage_announcements.php?delete=' + id;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html> 