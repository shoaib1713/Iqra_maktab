<?php
session_start();
require_once 'config.php';
require 'config/db.php';
require_once 'includes/time_utils.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: restrict_user.php?page=Manage Class Assignments&message=This page is restricted to administrators only.");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'add_assignment' || $action === 'update_assignment') {
        $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
        $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;
        $class_name = isset($_POST['class_name']) ? trim($_POST['class_name']) : '';
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
        $class_hours = isset($_POST['class_hours']) ? floatval($_POST['class_hours']) : 1.0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validate input
        $errors = [];
        
        if (empty($teacher_id)) {
            $errors[] = "Teacher is required";
        }
        
        if (empty($class_name)) {
            $errors[] = "Class name is required";
        }
        
        if (empty($subject)) {
            $errors[] = "Subject is required";
        }
        
        if ($class_hours <= 0) {
            $errors[] = "Class hours must be greater than 0";
        }
        
        if (empty($errors)) {
            if ($action === 'add_assignment') {
                // Insert new assignment
                $sql = "INSERT INTO teacher_class_assignments 
                        (teacher_id, class_name, subject, class_hours, is_active) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issdi", $teacher_id, $class_name, $subject, $class_hours, $is_active);
                $result = $stmt->execute();
                
                if ($result) {
                    $_SESSION['success_message'] = "Class assignment added successfully";
                } else {
                    $_SESSION['error_message'] = "Failed to add class assignment";
                }
            } else {
                // Update existing assignment
                $sql = "UPDATE teacher_class_assignments 
                        SET teacher_id = ?, 
                            class_name = ?, 
                            subject = ?, 
                            class_hours = ?, 
                            is_active = ? 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issdii", $teacher_id, $class_name, $subject, $class_hours, $is_active, $assignment_id);
                $result = $stmt->execute();
                
                if ($result) {
                    $_SESSION['success_message'] = "Class assignment updated successfully";
                } else {
                    $_SESSION['error_message'] = "Failed to update class assignment";
                }
            }
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
        
        header("Location: manage_class_assignments.php");
        exit();
    }
    
    if ($action === 'delete_assignment') {
        $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
        
        // Delete assignment
        $sql = "DELETE FROM teacher_class_assignments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $assignment_id);
        $result = $stmt->execute();
        
        if ($result) {
            $_SESSION['success_message'] = "Class assignment deleted successfully";
        } else {
            $_SESSION['error_message'] = "Failed to delete class assignment";
        }
        
        header("Location: manage_class_assignments.php");
        exit();
    }
}

// Get all teachers
$teachersSql = "SELECT id, name, email FROM users WHERE role = 'teacher' ORDER BY name";
$teachersResult = $conn->query($teachersSql);
$teachers = [];
while ($teacher = $teachersResult->fetch_assoc()) {
    $teachers[] = $teacher;
}

// Get filter values
$filter_teacher = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

// Build query for assignments
$assignmentsSql = "SELECT a.*, u.name as teacher_name, u.email as username 
                  FROM teacher_class_assignments a
                  JOIN users u ON a.teacher_id = u.id
                  WHERE 1=1";
$params = [];
$types = "";

if ($filter_teacher > 0) {
    $assignmentsSql .= " AND a.teacher_id = ?";
    $params[] = $filter_teacher;
    $types .= "i";
}

$assignmentsSql .= " ORDER BY u.name, a.class_name";

// Prepare and execute query
if (!empty($params)) {
    $assignmentsStmt = $conn->prepare($assignmentsSql);
    $assignmentsStmt->bind_param($types, ...$params);
    $assignmentsStmt->execute();
    $assignmentsResult = $assignmentsStmt->get_result();
} else {
    $assignmentsResult = $conn->query($assignmentsSql);
}

// Fetch assignments
$assignments = [];
while ($assignment = $assignmentsResult->fetch_assoc()) {
    $assignments[] = $assignment;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Class Assignments - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <?php include 'includes/navbar.php'; ?>
            
            <div class="container-fluid px-4 py-4">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5 class="fw-bold text-primary"><i class="fas fa-chalkboard-teacher me-2"></i> Manage Class Assignments</h5>
                        <p class="text-muted">Assign classes and subjects to teachers and set hours per class.</p>
                    </div>
                </div>
                
                <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <!-- Filter -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Filter by Teacher</label>
                                        <select name="teacher_id" class="form-select">
                                            <option value="">All Teachers</option>
                                            <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?php echo $teacher['id']; ?>" <?php echo ($filter_teacher == $teacher['id']) ? 'selected' : ''; ?>>
                                                <?php echo $teacher['name']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="fas fa-filter me-1"></i> Filter
                                        </button>
                                        <a href="manage_class_assignments.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-redo me-1"></i> Reset
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Assignments List -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-list me-2"></i> Class Assignments
                                </h5>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                                    <i class="fas fa-plus me-1"></i> Add New Assignment
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Teacher</th>
                                                <th>Class</th>
                                                <th>Subject</th>
                                                <th>Hours per Day</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($assignments)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No class assignments found</td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($assignments as $assignment): ?>
                                                <tr>
                                                    <td><?php echo $assignment['teacher_name']; ?> (<?php echo $assignment['username']; ?>)</td>
                                                    <td><?php echo $assignment['class_name']; ?></td>
                                                    <td><?php echo $assignment['subject']; ?></td>
                                                    <td><?php echo formatHours($assignment['class_hours']); ?></td>
                                                    <td>
                                                        <?php if ($assignment['is_active']): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary edit-assignment-btn" 
                                                                data-bs-toggle="modal" data-bs-target="#editAssignmentModal"
                                                                data-assignment='<?php echo json_encode($assignment); ?>'>
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                data-bs-toggle="modal" data-bs-target="#deleteAssignmentModal<?php echo $assignment['id']; ?>">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                
                                                <!-- Delete Modal -->
                                                <div class="modal fade" id="deleteAssignmentModal<?php echo $assignment['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Delete Class Assignment</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <form method="POST" action="">
                                                                <div class="modal-body">
                                                                    <p>Are you sure you want to delete the class assignment:</p>
                                                                    <p><strong><?php echo $assignment['class_name']; ?> - <?php echo $assignment['subject']; ?></strong> for <strong><?php echo $assignment['teacher_name']; ?></strong>?</p>
                                                                    <p class="text-danger">
                                                                        <i class="fas fa-exclamation-triangle me-1"></i> 
                                                                        This action cannot be undone.
                                                                    </p>
                                                                    <input type="hidden" name="action" value="delete_assignment">
                                                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-danger">Delete Assignment</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Teacher Summary -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-chart-pie me-2"></i> Teacher Assignment Summary
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Teacher</th>
                                                <th>Total Classes</th>
                                                <th>Total Hours</th>
                                                <th>Active Classes</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Group assignments by teacher
                                            $teacherSummary = [];
                                            foreach ($assignments as $assignment) {
                                                $teacherId = $assignment['teacher_id'];
                                                if (!isset($teacherSummary[$teacherId])) {
                                                    $teacherSummary[$teacherId] = [
                                                        'teacher_name' => $assignment['teacher_name'],
                                                        'username' => $assignment['username'],
                                                        'total_classes' => 0,
                                                        'total_hours' => 0,
                                                        'active_classes' => 0
                                                    ];
                                                }
                                                
                                                $teacherSummary[$teacherId]['total_classes']++;
                                                $teacherSummary[$teacherId]['total_hours'] += $assignment['class_hours'];
                                                if ($assignment['is_active']) {
                                                    $teacherSummary[$teacherId]['active_classes']++;
                                                }
                                            }
                                            
                                            if (empty($teacherSummary)): 
                                            ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No teacher assignments found</td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($teacherSummary as $teacherId => $summary): ?>
                                                <tr>
                                                    <td><?php echo $summary['teacher_name']; ?> (<?php echo $summary['username']; ?>)</td>
                                                    <td><?php echo $summary['total_classes']; ?></td>
                                                    <td><?php echo formatHours($summary['total_hours']); ?></td>
                                                    <td><?php echo $summary['active_classes']; ?></td>
                                                    <td>
                                                        <a href="manage_class_assignments.php?teacher_id=<?php echo $teacherId; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye me-1"></i> View Assignments
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Assignment Modal -->
    <div class="modal fade" id="addAssignmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Class Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_assignment">
                        
                        <div class="mb-3">
                            <label class="form-label">Teacher</label>
                            <select name="teacher_id" class="form-select" required>
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo $teacher['name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Class Name</label>
                            <input type="text" class="form-control" name="class_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Hours per Day</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="class_hours" step="0.5" min="0.5" value="1.0" required>
                                <span class="input-group-text">hr</span>
                            </div>
                            <div class="form-text">You can use decimals (e.g. 1.5 for 1 hour 30 minutes)</div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="add_is_active" checked>
                            <label class="form-check-label" for="add_is_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Assignment Modal -->
    <div class="modal fade" id="editAssignmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Class Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_assignment">
                        <input type="hidden" name="assignment_id" id="edit_assignment_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Teacher</label>
                            <select name="teacher_id" id="edit_teacher_id" class="form-select" required>
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo $teacher['name']; ?> (<?php echo $teacher['username']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Class Name</label>
                            <input type="text" class="form-control" name="class_name" id="edit_class_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" id="edit_subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Hours per Day</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="class_hours" id="edit_class_hours" step="0.5" min="0.5" required>
                                <span class="input-group-text">hr</span>
                            </div>
                            <div class="form-text">You can use decimals (e.g. 1.5 for 1 hour 30 minutes)</div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                            <label class="form-check-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Edit assignment button
            $('.edit-assignment-btn').click(function() {
                const assignmentData = $(this).data('assignment');
                
                $('#edit_assignment_id').val(assignmentData.id);
                $('#edit_teacher_id').val(assignmentData.teacher_id);
                $('#edit_class_name').val(assignmentData.class_name);
                $('#edit_subject').val(assignmentData.subject);
                $('#edit_class_hours').val(assignmentData.class_hours);
                $('#edit_is_active').prop('checked', assignmentData.is_active == 1);
            });
        });
    </script>
</body>
</html> 