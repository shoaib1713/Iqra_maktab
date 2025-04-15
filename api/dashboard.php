<?php
// Disable error output - very important to prevent PHP errors from breaking JSON
error_reporting(0);
ini_set('display_errors', 0);

// Set proper headers
header('Content-Type: application/json');
ob_clean(); // Clean any previous output buffer

require_once './config.php';
require_once __DIR__ . '/../config/db.php';
// Check authorization
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$authHeader) {
    echo json_encode([
        'success' => false,
        'message' => 'No authorization token provided',
        'data' => null
    ]);
    exit;
}

// Extract token
$token = null;
if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

if (!$token) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid token format',
        'data' => null
    ]);
    exit;
}

// Validate token and get user info
$userSql = "SELECT id, role FROM users WHERE token = ? AND token_expiry > NOW()";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("s", $token);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or expired token',
        'data' => null
    ]);
    exit;
}

// Get user data
$userData = $userResult->fetch_assoc();
$userId = $userData['id'];
$userRole = $userData['role'];

// Get academic year
$year = date("Y");
$startYear = $year - 1;
$endYear = $year;
$startMonth = ACEDEMIC_START_MONTH;
$endMonth = ACEDEMIC_END_MONTH;
$strWhereClause = '';
// echo json_encode([
//         'success' => $userData
//     ]);
//     exit;
if($userRole == 'teacher'){
    $strWhereClause = ' and s.assigned_teacher = '.$userId;
}
// Get total yearly fees
$sql_yearly = "SELECT SUM(s.annual_fees) AS total_yearly FROM students s LEFT JOIN student_status_history sst on sst.student_id = s.id WHERE sst.status in ('active','transferred') and (
    (sst.year = $startYear AND sst.month >= $startMonth)
    OR 
    (sst.year = $endYear AND sst.month <= $endMonth)
) and sst.current_active_record = 0".$strWhereClause;

$stmt = $conn->prepare($sql_yearly);
$stmt->execute();
$stmt->bind_result($total_yearly);
$stmt->fetch();
$stmt->close();

// Get total collected fees
$sql_collected = "SELECT SUM(amount) AS total_collected FROM fees 
    left join students s on student_id = s.id WHERE status = 'paid' AND (
    (year = $startYear AND month >= $startMonth)
    OR 
    (year = $endYear AND month <= $endMonth)
)". $strWhereClause;

$stmt = $conn->prepare($sql_collected);
$stmt->execute();
$stmt->bind_result($total_collected);
$stmt->fetch();
$stmt->close();

// Get total students
$countQuery = "SELECT COUNT(*) as total FROM students s WHERE 1=1".$strWhereClause;

$countStmt = $conn->prepare($countQuery);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalStudents = $countResult->fetch_assoc()['total'];

// Calculate pending fees and percentages
$total_pending = $total_yearly - $total_collected;
$collected_percentage = ($total_yearly > 0) ? ($total_collected / $total_yearly) * 100 : 0;
$pending_percentage = ($total_yearly > 0) ? ($total_pending / $total_yearly) * 100 : 0;

// Get active announcements
$announcementQuery = "SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5";
$announcementStmt = $conn->prepare($announcementQuery);
$announcementStmt->execute();
$announcementResult = $announcementStmt->get_result();
$announcements = [];
while ($row = $announcementResult->fetch_assoc()) {
    $announcements[] = [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'content' => $row['content'],
        'created_at' => $row['created_at']
    ];
}

// Get collected fees percentage per teacher
$sql_teacher_fees = "SELECT 
    u.name, 
    COALESCE(SUM(sst.salana_fees), 0) AS total_fees,
    COALESCE(SUM(f.amount), 0) AS collected_fees
FROM users u 
LEFT JOIN students s ON u.id = s.assigned_teacher 

LEFT JOIN student_status_history sst 
    ON s.id = sst.student_id 
    AND sst.current_active_record = 0 
    AND ((sst.year = $startYear AND sst.month >= $startMonth) OR (sst.year = $endYear AND sst.month <= $endMonth))
    AND sst.status in ('active','transferred')

LEFT JOIN (
    SELECT student_id, SUM(amount) AS amount 
    FROM fees 
    WHERE status = 'paid' 
    AND ((year = $startYear AND month >= $startMonth) OR (year = $endYear AND month <= $endMonth))
    GROUP BY student_id
) f ON s.id = f.student_id 

WHERE u.role = 'teacher'
GROUP BY u.id";

$teacherStmt = $conn->prepare($sql_teacher_fees);
$teacherStmt->execute();
$teacherResult = $teacherStmt->get_result();
$teachers = [];
while ($row = $teacherResult->fetch_assoc()) {
    $percentage = ($row['total_fees'] > 0) ? ($row['collected_fees'] / $row['total_fees']) * 100 : 0;
    $teachers[] = [
        'name' => $row['name'],
        'total_fees' => (float)$row['total_fees'],
        'collected_fees' => (float)$row['collected_fees'],
        'percentage' => (float)$percentage
    ];
}

// Get committee fees
$startDate = $startYear.'-'.ACEDEMIC_START_MONTH.'-01';
$endDate = $endYear.'-'.ACEDEMIC_END_MONTH.'-31';
$committeeFeesQuery = "SELECT SUM(mfc.amount) as committee_fees FROM `meeting_details` md
LEFT JOIN `meeting_fees_collection` mfc ON mfc.meeting_id = md.id
WHERE meeting_date >= '$startDate' and meeting_date <= '$endDate'";

$stmt = $conn->prepare($committeeFeesQuery);
$stmt->execute();
$stmt->bind_result($committeeCollectedFees);
$stmt->fetch();
$stmt->close();

// Get maintenance fees
$maintenanceQuery = "SELECT SUM(amount) FROM `maintenance` WHERE ((year = $startYear AND month >= $startMonth) OR (year = $endYear AND month <= $endMonth))";

$stmt = $conn->prepare($maintenanceQuery);
$stmt->execute();
$stmt->bind_result($maintenanceResult);
$stmt->fetch();
$stmt->close();

// Calculate available balance
$availableBalance = ($total_collected + $committeeCollectedFees) - $maintenanceResult;

// Check attendance status
$isPunchedIn = false;
$lastPunchTime = "";
$lastPunchType = "";

$attendanceSql = "SELECT * FROM attendance_logs WHERE user_id = ? AND DATE(created_at) = CURDATE() ORDER BY created_at DESC LIMIT 1";
$attendanceStmt = $conn->prepare($attendanceSql);
$attendanceStmt->bind_param("i", $userId);
$attendanceStmt->execute();
$attendanceResult = $attendanceStmt->get_result();

if ($attendanceResult->num_rows > 0) {
    $attendanceRow = $attendanceResult->fetch_assoc();
    $lastPunchTime = date("h:i A", strtotime($attendanceRow['timestamp']));
    $lastPunchType = $attendanceRow['action'];
    
    // Check if user is punched in (last action was 'in')
    if ($lastPunchType === 'in') {
        $isPunchedIn = true;
    }
}

// Build the response data
$responseData = [
    'totalStudents' => (int)$totalStudents,
    'totalFees' => (float)$total_yearly,
    'collectedFees' => (float)$total_collected,
    'pendingFees' => (float)$total_pending,
    'collected_percentage' => (float)$collected_percentage,
    'committee_fees' => (float)$committeeCollectedFees,
    'maintenance_fees' => (float)$maintenanceResult,
    'available_balance' => (float)$availableBalance,
    'teachers' => $teachers,
    'announcements' => $announcements,
    'attendanceStatus' => [
        'isPunchedIn' => $isPunchedIn,
        'lastPunchTime' => $lastPunchTime,
        'lastPunchType' => $lastPunchType
    ],
    'recentActivities' => [] // Empty array as we're removing this feature
];

// Return the JSON response
echo json_encode([
    'success' => true,
    'message' => 'Dashboard data fetched successfully',
    'data' => $responseData
], JSON_NUMERIC_CHECK); 