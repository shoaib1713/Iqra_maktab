<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['role'];

// Get current page to highlight active menu item
$current_page = basename($_SERVER['PHP_SELF']);

// Include notification styles globally
include_once 'notification_styles.php';
?>

<style>
/* Modern Sidebar Styling */
:root {
    --primary-color: #4e73df;
    --primary-hover: #3a5fc8;
    --active-item: #f8f9fe;
    --hover-bg: #f1f5ff;
    --text-primary: #2e384d;
    --text-secondary: #6c757d;
    --icon-color: #7c8db5;
    --transition-speed: 0.3s;
    --sidebar-width: 250px;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideInLeft {
    from { transform: translateX(-20px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.sidebar {
    animation: fadeIn 0.5s;
    transition: all var(--transition-speed);
    min-height: 100vh;
    background: linear-gradient(180deg, #ffffff 0%, #f8f9fe 100%);
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    z-index: 1000;
    overflow-y: auto;
    scrollbar-width: thin;
    width: var(--sidebar-width);
    position: fixed;
    top: 0;
    left: 0;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-thumb {
    background-color: rgba(0,0,0,0.1);
    border-radius: 10px;
}

.logo-container {
    padding: 1.5rem 1rem;
    text-align: center;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.logo {
    height: 50px;
    transition: transform 0.3s;
}

.logo:hover {
    transform: scale(1.05);
}

.sidebar-heading {
    color: var(--text-primary);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.8rem;
    padding: 1.5rem 1rem 0.75rem;
    margin-bottom: 0;
}

.nav-category {
    padding: 0.5rem 1.5rem;
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

.list-group-item {
    border: none;
    color: var(--text-primary);
    background-color: transparent;
    padding: 0.75rem 1.25rem;
    margin: 0 0.5rem;
    border-radius: 0.5rem;
    transition: all var(--transition-speed);
    font-weight: 500;
    position: relative;
    display: flex;
    align-items: center;
}

.list-group-item i {
    color: var(--icon-color);
    margin-right: 10px;
    width: 20px;
    text-align: center;
    font-size: 1rem;
    transition: all var(--transition-speed);
}

.list-group-item:hover {
    background-color: var(--hover-bg);
    color: var(--primary-color);
    transform: translateX(5px);
}

.list-group-item:hover i {
    color: var(--primary-color);
}

.list-group-item.active {
    background-color: var(--active-item);
    color: var(--primary-color);
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.list-group-item.active i {
    color: var(--primary-color);
}

.list-group-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 4px;
    background-color: var(--primary-color);
    border-radius: 0 4px 4px 0;
}

.sidebar-footer {
    padding: 1.5rem 1rem;
    text-align: center;
    font-size: 0.8rem;
    color: var(--text-secondary);
    border-top: 1px solid rgba(0,0,0,0.05);
    margin-top: 2rem;
}

.menu-item-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

#sidebar-overlay {
    display: none;
}

/* Responsive Sidebar */
@media (max-width: 992px) {
    .sidebar {
        margin-left: -250px;
    }
    
    .sidebar.show {
        margin-left: 0;
    }
    
    #page-content-wrapper {
        margin-left: 0 !important;
        width: 100%;
    }
}

/* Ensure animation for each menu item */
.list-group-item {
    animation: slideInLeft 0.3s;
    animation-fill-mode: both;
}

.list-group-item:nth-child(1) { animation-delay: 0.05s; }
.list-group-item:nth-child(2) { animation-delay: 0.1s; }
.list-group-item:nth-child(3) { animation-delay: 0.15s; }
.list-group-item:nth-child(4) { animation-delay: 0.2s; }
.list-group-item:nth-child(5) { animation-delay: 0.25s; }
.list-group-item:nth-child(6) { animation-delay: 0.3s; }
.list-group-item:nth-child(7) { animation-delay: 0.35s; }
.list-group-item:nth-child(8) { animation-delay: 0.4s; }
.list-group-item:nth-child(9) { animation-delay: 0.45s; }
.list-group-item:nth-child(10) { animation-delay: 0.5s; }
</style>

<div id="sidebar-overlay"></div>
<div class="sidebar" id="sidebar-wrapper">
    <div class="logo-container">
        <img src="<?php echo ((dirname($_SERVER['PHP_SELF']) != '/' && dirname($_SERVER['PHP_SELF']) != '\\') ? '../' : ''); ?>assets/images/logo.png" alt="MAKTAB-E-IQRA Logo" class="img-fluid logo" width="80">
        <h5 class="mt-3 mb-0 fw-bold text-primary">MAKTAB-E-IQRA</h5>
    </div>
    
    <div class="nav-category">Main Navigation</div>
    <div class="list-group list-group-flush">
        <a href="<?php echo ($role == 'teacher') ? 'teacher_dashboard.php': 'dashboard.php' ?>" 
           class="list-group-item list-group-item-action <?php echo (in_array($current_page, ['dashboard.php', 'teacher_dashboard.php'])) ? 'active' : ''; ?>">
           <i class="fas fa-home"></i> <span class="menu-item-text">Dashboard</span>
        </a>
        
        <a href="students.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'students.php') ? 'active' : ''; ?>">
           <i class="fas fa-user-graduate"></i> <span class="menu-item-text">Students</span>
        </a>
        
        <div class="nav-category">Financial Management</div>
        <a href="fees_collection.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'fees_collection.php') ? 'active' : ''; ?>">
           <i class="fas fa-money-bill-wave"></i> <span class="menu-item-text">Fees Collection</span>
        </a>
        
        <a href="pending_fees.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'pending_fees.php') ? 'active' : ''; ?>">
           <i class="fas fa-file-invoice-dollar"></i> <span class="menu-item-text">Student Fees</span>
        </a>
        
        <?php if($role=='admin') { ?>
        <a href="cheque_details.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'cheque_details.php') ? 'active' : ''; ?>">
           <i class="fas fa-money-check"></i> <span class="menu-item-text">Cheque Details</span>
        </a>
        
        <a href="approve_fee.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'approve_fee.php') ? 'active' : ''; ?>">
           <i class="fas fa-check-circle"></i> <span class="menu-item-text">Fees Approval</span>
        </a>
        <?php } ?>
        
        <!-- Attendance Section -->
        <div class="nav-category">Attendance Management</div>
        <a href="attendance.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>">
           <i class="fas fa-clock"></i> <span class="menu-item-text">Punch In/Out</span>
        </a>
        
        <a href="attendance_report.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'attendance_report.php') ? 'active' : ''; ?>">
           <i class="fas fa-chart-bar"></i> <span class="menu-item-text">Attendance Reports</span>
        </a>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="manage_leaves.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'manage_leaves.php') ? 'active' : ''; ?>">
           <i class="fas fa-calendar-minus"></i> <span class="menu-item-text">Leave Management</span>
        </a>
        
        <a href="manage_locations.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'manage_locations.php') ? 'active' : ''; ?>">
           <i class="fas fa-map-marker-alt"></i> <span class="menu-item-text">Office Locations</span>
        </a>
        
        <a href="attendance_settings.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'attendance_settings.php') ? 'active' : ''; ?>">
           <i class="fas fa-cog"></i> <span class="menu-item-text">Attendance Settings</span>
        </a>
        <?php endif; ?>

        <!-- Salary Module -->
        <div class="nav-category">Salary Management</div>
        <a href="daily_salary_calculations.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'daily_salary_calculations.php') ? 'active' : ''; ?>">
           <i class="fas fa-calendar-day"></i> <span class="menu-item-text">Daily Salary</span>
        </a>
        
        <a href="teacher_salary.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'teacher_salary.php') ? 'active' : ''; ?>">
           <i class="fas fa-wallet"></i> <span class="menu-item-text">My Salary</span>
        </a>
        
        <a href="salary_reports.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'salary_reports.php') ? 'active' : ''; ?>">
           <i class="fas fa-chart-pie"></i> <span class="menu-item-text">Salary Reports</span>
        </a>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="manage_class_assignments.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'manage_class_assignments.php') ? 'active' : ''; ?>">
           <i class="fas fa-chalkboard-teacher"></i> <span class="menu-item-text">Class Assignments</span>
        </a>
        
        <a href="manage_salary_rates.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'manage_salary_rates.php') ? 'active' : ''; ?>">
           <i class="fas fa-sliders-h"></i> <span class="menu-item-text">Salary Rates</span>
        </a>
        
        <a href="manage_salary_calculations.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'manage_salary_calculations.php') ? 'active' : ''; ?>">
           <i class="fas fa-calculator"></i> <span class="menu-item-text">Salary Calculations</span>
        </a>
        
        <a href="salary_periods.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'salary_periods.php') ? 'active' : ''; ?>">
           <i class="fas fa-calendar-alt"></i> <span class="menu-item-text">Salary Periods</span>
        </a>
        
        <a href="salary_settings.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'salary_settings.php') ? 'active' : ''; ?>">
           <i class="fas fa-cogs"></i> <span class="menu-item-text">Salary Settings</span>
        </a>
        <?php endif; ?>

        <div class="nav-category">Administration</div>
        <a href="meeting_list.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'meeting_list.php') ? 'active' : ''; ?>">
           <i class="fas fa-calendar-alt"></i> <span class="menu-item-text">Meetings</span>
        </a>

        
        <a href="maintenance_list.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'maintenance_list.php') ? 'active' : ''; ?>">
           <i class="fas fa-tools"></i> <span class="menu-item-text">Maintenance</span>
        </a>
        
        <a href="view_all_notifications.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'view_all_notifications.php') ? 'active' : ''; ?>">
           <i class="fas fa-bell"></i> <span class="menu-item-text">Notifications</span>
        </a>
        
        <?php if($role=='admin') { ?>
        <a href="manage_announcements.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'manage_announcements.php') ? 'active' : ''; ?>">
           <i class="fas fa-bullhorn"></i> <span class="menu-item-text">Announcements</span>
        </a>
        <?php } ?>
        
        <a href="users.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
           <i class="fas fa-users"></i> <span class="menu-item-text">Users</span>
        </a>
        
        <a href="change_password.php" 
           class="list-group-item list-group-item-action <?php echo ($current_page == 'change_password.php') ? 'active' : ''; ?>">
           <i class="fas fa-key"></i> <span class="menu-item-text">Change Password</span>
        </a>
    </div>
    
    <div class="sidebar-footer">
        <p class="mb-0">MAKTAB-E-IQRA &copy; <?php echo date('Y'); ?></p>
        <small>Version 1.0</small>
    </div>
</div>

<script>
// Check if jQuery is loaded, if not load it
if (typeof jQuery === 'undefined') {
    document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
}

// Handle sidebar toggle for mobile
$(document).ready(function() {
    $('#menu-toggle').click(function(e) {
        e.preventDefault();
        $('#sidebar-wrapper').toggleClass('show');
        $('#sidebar-overlay').toggleClass('show');
    });
    
    $('#sidebar-overlay').click(function() {
        $('#sidebar-wrapper').removeClass('show');
        $('#sidebar-overlay').removeClass('show');
    });
});
</script>
