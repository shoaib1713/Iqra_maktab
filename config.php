<?php
// Database Connection Constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'maktab_a_ekra');

define('STUDENT_MONTHLY_FEES',2000);
define('ACEDEMIC_START_MONTH',6);
define('ACEDEMIC_END_MONTH',5);

// Google Maps API Key
define('GOOGLE_MAPS_API_KEY', 'YOUR_ACTUAL_API_KEY_HERE');

// Base URL (Modify it according to your project folder structure)
define('BASE_URL', 'http://localhost/maktab-a-ekra/');

// Other Global Constants
define('SITE_NAME', 'Maktab-a-Ekra');
define('ADMIN_EMAIL', 'admin@maktab-a-ekra.com');

// Set timezone
date_default_timezone_set('Asia/Kolkata'); // Indian Standard Time (IST)

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
