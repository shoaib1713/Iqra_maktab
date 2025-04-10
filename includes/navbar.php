<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 rounded">
    <div class="container-fluid">
        <button class="btn" id="menu-toggle">
            <i class="fas fa-bars fs-5 text-primary"></i>
        </button>
        <?php if (isset($page_title)): ?>
        <span class="navbar-brand ms-2"><?php echo $page_title; ?></span>
        <?php elseif (isset($announcements) && !empty($announcements)): ?>
        <div class="ms-2 d-none d-sm-block">
            <marquee class="d-block w-100 text-secondary fw-bold"><?php echo htmlspecialchars($announcements[0]['content']); ?></marquee>
        </div>
        <span class="navbar-brand ms-2 d-sm-none"><?php echo ucfirst(str_replace('_', ' ', basename($_SERVER['PHP_SELF'], '.php'))); ?></span>
        <?php else: ?>
        <span class="navbar-brand ms-2"><?php echo ucfirst(str_replace('_', ' ', basename($_SERVER['PHP_SELF'], '.php'))); ?></span>
        <?php endif; ?>
        
        <div class="d-flex align-items-center">
            <!-- Notification Icon -->
            <?php include_once 'includes/notification_bell.php'; ?>
            
            <!-- User Dropdown -->
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="me-2"><i class="fas fa-user-circle fs-5"></i> <span class="d-none d-md-inline"><?php echo $_SESSION['user_name']; ?></span></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser">
                    <li><a class="dropdown-item" href="change_password.php">Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="modules/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<?php 
// Include notification scripts if available
if (file_exists('includes/notification_scripts.php')) {
    include_once 'includes/notification_scripts.php';
}
?> 