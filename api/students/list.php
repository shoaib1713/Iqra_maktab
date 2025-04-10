<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

$token = getBearerToken();
if (!$token) {
    sendError('No token provided', 401);
}

if (!validateToken($token)) {
    sendError('Invalid token', 401);
}

// Get students with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

$sql = "SELECT s.*, c.name as class_name 
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        WHERE s.is_active = 1 
        ORDER BY s.name 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'fatherName' => $row['father_name'],
        'classId' => $row['class_id'],
        'className' => $row['class_name'],
        'rollNumber' => $row['roll_number'],
        'phone' => $row['phone'],
        'address' => $row['address'],
        'isActive' => (bool)$row['is_active']
    ];
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM students WHERE is_active = 1";
$countResult = $conn->query($countSql);
$total = $countResult->fetch_assoc()['total'];

$response = [
    'students' => $students,
    'pagination' => [
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'totalPages' => ceil($total / $limit)
    ]
];

sendResponse($response); 