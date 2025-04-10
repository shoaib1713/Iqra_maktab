<?php
session_start();
require_once 'config.php';

// Check if user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Get the referring page
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$dashboard_link = '';

// Set dashboard link based on user role
switch ($role) {
    case 'admin':
        $dashboard_link = 'dashboard.php';
        break;
    case 'teacher':
        $dashboard_link = 'teacher_dashboard.php';
        break;
    case 'staff':
        $dashboard_link = 'staff_dashboard.php';
        break;
    default:
        $dashboard_link = 'index.php';
}

// Get restricted page and any custom message if provided
$restricted_page = isset($_GET['page']) ? htmlspecialchars($_GET['page']) : 'the requested page';
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'You do not have sufficient permissions to access this page.';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Restricted - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .restricted-container {
            max-width: 600px;
            text-align: center;
        }
        .icon-restricted {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 1.5rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            border-radius: 15px 15px 0 0 !important;
            background-color: #dc3545;
            color: white;
        }
        .btn-back {
            background-color: #dc3545;
            border-color: #dc3545;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            background-color: #c82333;
            border-color: #bd2130;
            transform: translateY(-2px);
        }
        .btn-dashboard {
            transition: all 0.3s ease;
        }
        .btn-dashboard:hover {
            transform: translateY(-2px);
        }
        .user-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="restricted-container">
            <div class="card">
                <div class="card-header py-3">
                    <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Access Restricted</h4>
                </div>
                <div class="card-body p-4">
                    <div class="icon-restricted">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h2 class="mb-3">Permission Denied</h2>
                    <p class="lead mb-4"><?php echo $message; ?></p>
                    <p class="mb-4">You are trying to access <strong><?php echo $restricted_page; ?></strong> but you don't have the required permissions based on your current role.</p>
                    
                    <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                        <?php if (!empty($referer)): ?>
                            <a href="<?php echo $referer; ?>" class="btn btn-back btn-lg px-4 me-sm-3">
                                <i class="fas fa-arrow-left me-2"></i> Go Back
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo $dashboard_link; ?>" class="btn btn-outline-secondary btn-lg px-4">
                            <i class="fas fa-home me-2"></i> Go to Dashboard
                        </a>
                    </div>
                    
                    <div class="user-info mt-4">
                        <p class="mb-1">
                            <small>
                                <strong>User:</strong> <?php echo htmlspecialchars($user_name); ?> 
                                <strong>Role:</strong> <?php echo ucfirst(htmlspecialchars($role)); ?>
                            </small>
                        </p>
                        <p class="mb-0">
                            <small>If you believe this is an error, please contact your administrator for assistance.</small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 