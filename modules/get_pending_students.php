<?php
require '../config/db.php';
require_once '../config.php';

$year = date("Y");
$startYear = $year - 1;
$endYear = $year;
$startMonth = ACEDEMIC_START_MONTH;
$endMonth = ACEDEMIC_END_MONTH;

$query = "SELECT 
            s.id, 
            s.name, 
            s.class, 
            s.phone, 
            COALESCE(SUM(f.amount), 0) AS paid_amount 
        FROM students s 
        LEFT JOIN fees f ON s.id = f.student_id 
            AND ( (f.year = $startYear AND f.month >= $startMonth) OR (f.year = $endYear AND f.month <= $endMonth) ) 
        GROUP BY s.id, s.name, s.class, s.phone";

$result = $conn->query($query);
$output = "";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pending_amount = STUDENT_MONTHLY_FEES - $row['paid_amount'];
        if($pending_amount > 0 ){
        $output .= "<tr>
                        <td>{$row['name']}</td>
                        <td>{$row['class']}</td>
                        <td>₹ {$pending_amount}</td>
                        <td>₹ {$row['paid_amount']}</td>
                    </tr>";
        }
    }
} else {
    $output .= "<tr><td colspan='4' class='text-center'>No pending fees</td></tr>";
}

echo $output;
$conn->close();
?>
