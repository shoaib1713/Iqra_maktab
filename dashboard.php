<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$year = date("Y");

$startYear = $year - 1;
$endYear = $year;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['academic_year']) && $_POST['academic_year'] !== '') {
    $selectedYear = $_POST['academic_year'];
    list($startYear, $endYear) = explode("-", $selectedYear);
}
$startMonth = ACEDEMIC_START_MONTH;
$endMonth = ACEDEMIC_END_MONTH;

// Get total yearly fees
$sql_yearly = "SELECT SUM(s.annual_fees) AS total_yearly FROM students s LEFT JOIN student_status_history sst on sst.student_id = s.id WHERE sst.status in ('active','transferred') and (
    (sst.year = $startYear AND sst.month >= $startMonth)
    OR 
    (sst.year = $endYear AND sst.month <= $endMonth)
) and sst.current_active_record = 0";
$stmt = $conn->prepare($sql_yearly);
$stmt->execute();
$stmt->bind_result($total_yearly);
$stmt->fetch();
$stmt->close();

// Get total collected fees
$sql_collected = "SELECT SUM(amount) AS total_collected FROM fees WHERE status = 'paid' AND (
    (year = $startYear AND month >= $startMonth)
    OR 
    (year = $endYear AND month <= $endMonth)
)";
$stmt = $conn->prepare($sql_collected);
$stmt->execute();
$stmt->bind_result($total_collected);
$stmt->fetch();
$stmt->close();

$countQuery = "SELECT COUNT(*) as total FROM students";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalStudents = $countResult->fetch_assoc()['total'];

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
    $announcements[] = $row;
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
    $teachers[] = $row;
}

//committe result
$startDate = $startYear.'-'.ACEDEMIC_START_MONTH.'-01';
$endDate = $endYear.'-'.ACEDEMIC_END_MONTH.'-31';
$committeeFeesQuery = "SELECT SUM(mfc.amount) as committee_fees FROM `meeting_details` md
LEFT JOIN `meeting_fees_collection` mfc ON mfc.meeting_id = md.id
WHERE meeting_date >= '$startDate' and meeting_date <= '$endDate'";

//echo $committeeFeesQuery; exit;

$stmt = $conn->prepare($committeeFeesQuery);
$stmt->execute();
$stmt->bind_result($committeeCollectedFess);
$stmt->fetch();
$stmt->close();

//maintenance result.
$maintenanceQuery = "SELECT SUM(amount) FROM `maintenance` WHERE ((year = $startYear AND month >= $startMonth) OR (year = $endYear AND month <= $endMonth))";

$stmt = $conn->prepare($maintenanceQuery);
$stmt->execute();
$stmt->bind_result($maintenanceResult);
$stmt->fetch();
$stmt->close();

//Available Balance
$availableBalance = ($total_collected + $committeeCollectedFess) - $maintenanceResult;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js" integrity="sha384-+3hSuUQXGvDDLqZA3hYZLhhDmOuUPD5VuXdHu9Y5Mz3RbYNmvPVzXRCdwPvKcXP8" crossorigin="anonymous"></script>
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha384-vtXRMe3mGCbOeY7l30aIg8H9p3GdeSe4IFlP6G8JMa7o7lXvnz3GFKzPxzJdPfGK" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div id="page-content-wrapper">
            <?php include 'includes/navbar.php'; ?>

            <div class="container-fluid px-4">
                <div class="row mb-4">
                    <div class="col-12">
                        <form method="POST" action="" class="bg-white p-3 rounded shadow-sm">
                            <div class="row g-2">
                                <div class="col-md-10">
                                    <select name="academic_year" class="form-select">
                                        <option value="">Select Academic Year</option>
                                        <?php
                                        $currentYear = date('Y');
                                        for ($i = 0; $i < 5; $i++) {
                                            $year = $currentYear - $i;
                                            $nextYear = $year + 1;
                                            $selected = ($startYear == $year) ? 'selected' : '';
                                            echo "<option value='$year-$nextYear' $selected>$year-$nextYear</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="card p-3 text-center h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="fw-bold text-dark">₹ <?php echo number_format($total_yearly) ?></h5>
                                <p class="text-muted mb-0">Total Fees</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="card p-3 text-center h-100 shadow-sm bg-success bg-opacity-75 text-white">
                            <div class="card-body">
                                <h5 class="fw-bold">₹ <?php echo number_format($total_collected); ?></h5>
                                <p class="mb-0">Collected</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="card p-3 text-center h-100 shadow-sm bg-danger bg-opacity-75 text-white cursor-pointer" id="pendingBox">
                            <div class="card-body">
                                <h5 class="fw-bold">₹ <?php echo number_format($total_pending); ?></h5>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="card p-3 text-center h-100 shadow-sm bg-primary bg-opacity-75 text-white">
                            <div class="card-body">
                                <h5 class="fw-bold"><?php echo round($collected_percentage,2); ?>%</h5>
                                <p class="mb-0">Collected %</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card p-3 text-center h-100 shadow-sm bg-light">
                            <div class="card-body">
                                <h5 class="fw-bold">₹ <?php echo number_format($committeeCollectedFess); ?></h5>
                                <p class="text-muted mb-0">Committee Collection</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3 text-center h-100 shadow-sm bg-info bg-opacity-75 text-white">
                            <div class="card-body">
                                <h5 class="fw-bold">₹ <?php echo number_format($maintenanceResult); ?></h5>
                                <p class="mb-0">Maintenance</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3 text-center h-100 shadow-sm bg-secondary bg-opacity-75 text-white">
                            <div class="card-body">
                                <h5 class="fw-bold">₹ <?php echo number_format($availableBalance); ?></h5>
                                <p class="mb-0">Available Balance</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Collected Fees Per Teacher</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped border">
                                        <thead>
                                            <tr>
                                                <th>Teacher Name</th>
                                                <th>Collected Fees</th>
                                                <th>% Collected</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $totalPercentage = 0;
                                            $teacherCount = count($teachers);
                                            
                                            foreach ($teachers as $teacher) {
                                                $percentage = ($teacher['total_fees'] > 0) ? ($teacher['collected_fees'] / $teacher['total_fees']) * 100 : 0;
                                                $totalPercentage += $percentage;
                                                $color = '';
                                                if ($percentage < 40) $color = 'text-danger';
                                                else if ($percentage < 70) $color = 'text-warning';
                                                else $color = 'text-success';
                                                
                                                echo "<tr>
                                                        <td>{$teacher['name']}</td>
                                                        <td>₹ " . number_format($teacher['collected_fees']) . " / ₹ " . number_format($teacher['total_fees']) . "</td>
                                                        <td class='$color'>" . number_format($percentage, 2) . "%</td>
                                                    </tr>";
                                            }
                                            ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-primary">
                                                <th>Average</th>
                                                <th></th>
                                                <th><?php echo ($teacherCount > 0) ? number_format($totalPercentage / $teacherCount, 2) : 0; ?>%</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Collection Statistics</h5>
                            </div>
                            <div class="card-body d-flex align-items-center justify-content-center">
                                <div style="position: relative; height:250px; width:100%">
                                    <canvas id="feesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Announcements Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-bold text-primary">
                                        <i class="fas fa-bullhorn me-2"></i> Announcements
                                    </h5>
                                    <a href="manage_announcements.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-plus me-1"></i> Manage Announcements
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($announcements)): ?>
                                    <p class="text-muted text-center">No announcements available</p>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($announcements as $announcement): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card h-100 shadow-sm border-start border-primary border-4">
                                                    <div class="card-body">
                                                        <h5 class="card-title"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                                        <p class="card-text"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                                        <p class="card-text">
                                                            <small class="text-muted">
                                                                <i class="fas fa-calendar-alt me-1"></i> 
                                                                <?php echo date('d M Y', strtotime($announcement['created_at'])); ?>
                                                            </small>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS with integrity check -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    <?php include 'includes/notification_styles.php'; ?>
    <?php include 'includes/notification_scripts.php'; ?>
    <script>
        $(document).ready(function() {
            $('#menu-toggle').click(function(e) {
                e.preventDefault();
                $('#wrapper').toggleClass('toggled');
            });
            
            // Create pie chart for fees collection
            const ctx = document.getElementById('feesChart').getContext('2d');
            const feesChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Collected', 'Pending'],
                    datasets: [{
                        data: [<?php echo $total_collected; ?>, <?php echo $total_pending; ?>],
                        backgroundColor: [
                            'rgba(46, 204, 113, 0.8)',
                            'rgba(231, 76, 60, 0.8)'
                        ],
                        borderColor: [
                            'rgba(46, 204, 113, 1)',
                            'rgba(231, 76, 60, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                boxWidth: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== undefined) {
                                        label += new Intl.NumberFormat('en-IN', { 
                                            style: 'currency', 
                                            currency: 'INR',
                                            maximumFractionDigits: 0
                                        }).format(context.parsed);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>