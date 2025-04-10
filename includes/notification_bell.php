<?php
// Don't start session here as it will be started in the parent file
// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Get notification count directly from database
    if (!isset($conn)) {
        require_once(dirname(__FILE__) . '/../config/db.php');
    }
    
    $notification_count = 0;
    
    try {
        // First check if the notifications table exists
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
            
            // Generate initial announcements as notifications for this user
            $announceQuery = "SELECT * FROM announcements WHERE is_active = 1";
            $announceStmt = $conn->prepare($announceQuery);
            $announceStmt->execute();
            $announceResult = $announceStmt->get_result();
            
            if ($announceResult->num_rows > 0) {
                $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, is_read, created_at) VALUES (?, 'announcement', ?, ?, 0, NOW())");
                
                while ($announcement = $announceResult->fetch_assoc()) {
                    $notifStmt->bind_param("iis", $user_id, $announcement['id'], $announcement['title']);
                    $notifStmt->execute();
                    $notification_count++;
                }
            }
        } else {
            // Get unread count
            $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $countStmt->bind_param("i", $user_id);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            if ($countRow = $countResult->fetch_assoc()) {
                $notification_count = $countRow['count'];
            }
            
            // If user has no notifications but there are active announcements, create them
            if ($notification_count == 0) {
                $checkNotifStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?");
                $checkNotifStmt->bind_param("i", $user_id);
                $checkNotifStmt->execute();
                $checkResult = $checkNotifStmt->get_result();
                $hasAnyNotifications = $checkResult->fetch_assoc()['count'] > 0;
                
                if (!$hasAnyNotifications) {
                    // Add existing announcements as notifications
                    $announceQuery = "SELECT * FROM announcements WHERE is_active = 1";
                    $announceStmt = $conn->prepare($announceQuery);
                    $announceStmt->execute();
                    $announceResult = $announceStmt->get_result();
                    
                    if ($announceResult->num_rows > 0) {
                        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, is_read, created_at) VALUES (?, 'announcement', ?, ?, 0, NOW())");
                        
                        while ($announcement = $announceResult->fetch_assoc()) {
                            $notifStmt->bind_param("iis", $user_id, $announcement['id'], $announcement['title']);
                            $notifStmt->execute();
                            $notification_count++;
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error handling notifications: " . $e->getMessage());
    }
?>
<!-- Notification Icon -->
<div class="dropdown me-3 position-relative">
    <a class="btn btn-outline-secondary position-relative" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge <?php echo $notification_count > 0 ? '' : 'd-none'; ?>" id="notification-count">
            <?php echo $notification_count; ?>
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
            <a href="view_all_notifications.php" class="text-decoration-none small view-all-notifications-link">View all notifications</a>
        </div>
    </div>
</div>
<?php } ?> 