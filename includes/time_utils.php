<?php
/**
 * Time Utility Functions
 * Common time formatting functions to maintain consistency across the application
 */

/**
 * Format minutes as hours and minutes (e.g., "2 hr 30 mins" or "45 mins")
 * 
 * @param float|int $minutes Number of minutes or decimal hours to format
 * @param bool $isHours Whether the input is in hours (true) or minutes (false)
 * @return string Formatted time string
 */
function formatTime($minutes, $isHours = false) {
    if ($isHours) {
        // Convert hours to minutes
        $minutes = $minutes * 60;
    }
    
    $hours = floor($minutes / 60);
    $mins = round($minutes % 60);
    
    if ($hours < 1) {
        return $mins . " mins";
    } else if ($mins > 0) {
        return $hours . " hr " . $mins . " mins";
    } else {
        return $hours . " hr";
    }
}

/**
 * Format decimal hours (e.g., 1.5 hours) as hours and minutes
 * 
 * @param float $decimalHours Number of hours in decimal format
 * @return string Formatted time string
 */
function formatHours($decimalHours) {
    return formatTime($decimalHours, true);
}

/**
 * Format time stored in database for display
 * 
 * @param string $dbTime Time string from database (HH:MM:SS)
 * @return string Formatted time string (HH:MM AM/PM)
 */
function formatDbTime($dbTime) {
    if (empty($dbTime)) {
        return '-';
    }
    return date('h:i A', strtotime($dbTime));
}

/**
 * Calculate duration between two timestamps in minutes
 * 
 * @param string $startTime Start time (timestamp or MySQL datetime)
 * @param string|null $endTime End time (timestamp or MySQL datetime), null means current time
 * @return int Duration in minutes
 */
function calculateDuration($startTime, $endTime = null) {
    $start = is_numeric($startTime) ? $startTime : strtotime($startTime);
    $end = $endTime ? (is_numeric($endTime) ? $endTime : strtotime($endTime)) : time();
    return max(0, ($end - $start) / 60);
}
?> 