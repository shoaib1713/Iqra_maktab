<?php
session_start();
require_once 'config.php';
require 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    echo '<div class="alert alert-danger">Unauthorized access or invalid request</div>';
    exit;
}

$cheque_id = $_GET['id'];

// Fetch cheque details
$sql = "SELECT cd.*, u.name as handover_teacher_name, s.name as student_name
        FROM cheque_details cd
        LEFT JOIN users u ON cd.cheque_handover_teacher = u.id
        LEFT JOIN students s ON cd.student_id = s.id
        WHERE cd.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cheque_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-warning">Cheque details not found</div>';
    exit;
}

$cheque = $result->fetch_assoc();

// Get status text
$status = "Pending";
if ($cheque['is_cleared'] == 1) {
    $status = "Cleared";
} elseif ($cheque['is_bounced'] == 1) {
    $status = "Bounced";
}

// Format dates
$givenDate = date("d M Y", strtotime($cheque['cheque_given_date']));
$clearDate = ($cheque['clear_date']) ? date("d M Y", strtotime($cheque['clear_date'])) : "N/A";
$bounceDate = ($cheque['bounce_date']) ? date("d M Y", strtotime($cheque['bounce_date'])) : "N/A";

// Get image path
$imagePath = "assets/images/cheque_default.png"; // Default image
if (!empty($cheque['cheque_image']) && file_exists($cheque['cheque_image'])) {
    $imagePath = $cheque['cheque_image'];
}
?>

<div class="table-responsive">
    <table class="table table-bordered">
        <tr>
            <th class="bg-light">Cheque Number</th>
            <td><?php echo htmlspecialchars($cheque['cheque_number']); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Bank Name</th>
            <td><?php echo htmlspecialchars($cheque['bank_name']); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Amount</th>
            <td><span class="fw-bold">₹ <?php echo number_format($cheque['cheque_amount'], 2); ?></span></td>
        </tr>
        <tr>
            <th class="bg-light">Student</th>
            <td><?php echo htmlspecialchars($cheque['student_name'] ?: 'N/A'); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Handover Teacher</th>
            <td><?php echo htmlspecialchars($cheque['handover_teacher_name'] ?: 'N/A'); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Status</th>
            <td>
                <?php if ($cheque['is_cleared'] == 1): ?>
                    <span class="badge bg-success">Cleared</span>
                <?php elseif ($cheque['is_bounced'] == 1): ?>
                    <span class="badge bg-danger">Bounced</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark">Pending</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th class="bg-light">Given Date</th>
            <td><?php echo $givenDate; ?></td>
        </tr>
        <?php if ($cheque['is_cleared'] == 1): ?>
        <tr>
            <th class="bg-light">Clear Date</th>
            <td><?php echo $clearDate; ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($cheque['is_bounced'] == 1): ?>
        <tr>
            <th class="bg-light">Bounce Date</th>
            <td><?php echo $bounceDate; ?></td>
        </tr>
        <tr>
            <th class="bg-light">Bounce Reason</th>
            <td><?php echo htmlspecialchars($cheque['bounce_reason'] ?: 'Not specified'); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th class="bg-light">Notes</th>
            <td><?php echo htmlspecialchars($cheque['notes'] ?: 'No notes available'); ?></td>
        </tr>
    </table>
</div>

<div class="text-center mt-3">
    <img src="<?php echo $imagePath; ?>" class="img-fluid img-thumbnail" style="max-height: 200px;" alt="Cheque Image">
</div> 