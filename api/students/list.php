<?php
// Disable error output - very important to prevent PHP errors from breaking JSON
error_reporting(0);
ini_set('display_errors', 0);

// Set proper headers
header('Content-Type: application/json');
ob_clean(); // Clean any previous output buffer

// Include dependencies
try {
    require_once '../config.php';
    require_once __DIR__ . '/../../config/db.php';
} catch (Exception $e) {
    // If includes fail, return a properly formatted JSON error
    echo json_encode([
        'success' => false,
        'message' => 'Server configuration error',
        'data' => [
            'students' => [],
            'pagination' => [
                'total' => 0,
                'page' => 1,
                'limit' => 10,
                'totalPages' => 1
            ]
        ]
    ]);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Validate authentication token
$token = getBearerToken();
if (!$token) {
    // If no token, return formatted JSON response expected by Android app
    echo json_encode([
        'success' => false,
        'message' => 'No token provided',
        'data' => [
            'students' => [],
            'pagination' => [
                'total' => 0,
                'page' => 1,
                'limit' => 10,
                'totalPages' => 1
            ]
        ]
    ]);
    exit;
}

// Get user information including role
$userSql = "SELECT id, role FROM users WHERE token = ? AND token_expiry > NOW()";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("s", $token);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows === 0) {
    // If invalid token, return formatted JSON response expected by Android app
    echo json_encode([
        'success' => false,
        'message' => 'Invalid token',
        'data' => [
            'students' => [],
            'pagination' => [
                'total' => 0,
                'page' => 1,
                'limit' => 10,
                'totalPages' => 1
            ]
        ]
    ]);
    exit;
}

// Get the user data
$userData = $userResult->fetch_assoc();
$userId = $userData['id'];
$userRole = $userData['role'];

// Get students with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Prepare query with filters
try {
    $sql = "SELECT s.*, t.name as teacher_name 
            FROM students s 
            LEFT JOIN users t ON s.assigned_teacher = t.id 
            WHERE 1=1 ";
    
    $params = [];
    $types = "";
    
    // Filter by teacher role - only show assigned students if the user is a teacher
    if ($userRole === 'teacher') {
        $sql .= " AND s.assigned_teacher = ? ";
        $params[] = $userId;
        $types .= "i";
    }
    
    // Add search filters if provided
    if (isset($_GET['search_name']) && !empty($_GET['search_name'])) {
        $sql .= " AND s.name LIKE ? ";
        $searchName = "%" . $_GET['search_name'] . "%";
        $params[] = $searchName;
        $types .= "s";
    }
    
    if (isset($_GET['search_phone']) && !empty($_GET['search_phone'])) {
        $sql .= " AND s.phone LIKE ? ";
        $searchPhone = "%" . $_GET['search_phone'] . "%";
        $params[] = $searchPhone;
        $types .= "s";
    }
    
    if (isset($_GET['search_teacher']) && !empty($_GET['search_teacher'])) {
        $sql .= " AND s.assigned_teacher = ? ";
        $params[] = (int)$_GET['search_teacher'];
        $types .= "i";
    }
    
    if (isset($_GET['search_status']) && !empty($_GET['search_status'])) {
        if ($_GET['search_status'] === 'active') {
            $sql .= " AND s.deleted_at IS NULL ";
        } else if ($_GET['search_status'] === 'inactive') {
            $sql .= " AND s.deleted_at IS NOT NULL ";
        }
    }
    
    // Add ordering and limit
    $sql .= " ORDER BY s.name LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    // Prepare and execute statement
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        // Only bind parameters if we have them
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        // Get student ID for fees calculation
        $studentId = $row['id'];
        
        // Calculate pending fees (annual_fees - sum of paid fees)
        $feesSql = "SELECT 
                        COALESCE(s.annual_fees, 0) as annual_fees,
                        COALESCE(SUM(f.amount), 0) as paid_amount
                    FROM 
                        students s
                    LEFT JOIN 
                        fees f ON s.id = f.student_id AND f.status = 'paid'
                    WHERE 
                        s.id = ?";
                        
        $feesStmt = $conn->prepare($feesSql);
        $feesStmt->bind_param("i", $studentId);
        $feesStmt->execute();
        $feesResult = $feesStmt->get_result()->fetch_assoc();
        
        $annualFees = (float)($feesResult['annual_fees'] ?? 0);
        $paidAmount = (float)($feesResult['paid_amount'] ?? 0);
        $pendingFees = $annualFees - $paidAmount;
        
        $students[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'className' => $row['class'] ?? null,
            'phone' => $row['phone'] ?? null,
            'annual_fees' => (float)($row['annual_fees'] ?? 0),
            'pending_fees' => $pendingFees > 0 ? $pendingFees : 0,
            'assigned_teacher' => $row['teacher_name'] ?? null,
            'teacher_id' => $row['assigned_teacher'] ? (int)$row['assigned_teacher'] : null,
            'photo' => $row['photo'] ?? null,
            'isDeleted' => $row['is_deleted'] ? true : false,
            'status' => $row['deleted_at'] ? 'inactive' : 'active',
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'deleted_at' => $row['deleted_at'] ?? null
        ];
    }
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM students";
    
    // Add role-based filtering to the count query 
    if ($userRole === 'teacher') {
        $countSql .= " WHERE assigned_teacher = " . (int)$userId;
    }
    
    $countResult = $conn->query($countSql);
    $total = $countResult->fetch_assoc()['total'];
    
    // Format the response according to the Android app's expected structure
    $response = [
        'success' => true,
        'message' => "",
        'data' => [
            'students' => $students,
            'pagination' => [
                'total' => (int)$total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'totalPages' => (int)ceil($total / $limit)
            ]
        ]
    ];
    
    // Ensure we're outputting valid JSON
    echo json_encode($response, JSON_NUMERIC_CHECK);
    exit;
} catch (Exception $e) {
    // Return a formatted error response
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'data' => [
            'students' => [],
            'pagination' => [
                'total' => 0,
                'page' => 1,
                'limit' => 10,
                'totalPages' => 1
            ]
        ]
    ]);
    exit;
} 