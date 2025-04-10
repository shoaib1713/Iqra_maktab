-- Attendance module tables for MAKTAB-E-IQRA

-- Table for office locations
CREATE TABLE IF NOT EXISTS office_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    radius_meters INT NOT NULL DEFAULT 100, -- Geofencing radius in meters
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for holidays
CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_name VARCHAR(100) NOT NULL,
    holiday_date DATE NOT NULL,
    description TEXT,
    is_full_day TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_holiday_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for leave types
CREATE TABLE IF NOT EXISTS leave_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(50) NOT NULL,
    description TEXT,
    is_paid TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_type_name (type_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for attendance settings
CREATE TABLE IF NOT EXISTS attendance_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for attendance logs
CREATE TABLE IF NOT EXISTS attendance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('teacher', 'student', 'admin', 'staff') NOT NULL,
    punch_in_time DATETIME,
    punch_out_time DATETIME,
    punch_in_location_id INT,
    punch_out_location_id INT,
    punch_in_latitude DECIMAL(10, 8),
    punch_in_longitude DECIMAL(11, 8),
    punch_out_latitude DECIMAL(10, 8),
    punch_out_longitude DECIMAL(11, 8),
    punch_in_ip VARCHAR(45),
    punch_out_ip VARCHAR(45),
    total_hours DECIMAL(5, 2),
    status ENUM('present', 'absent', 'leave', 'holiday', 'weekend', 'late', 'early_exit') DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (punch_in_location_id) REFERENCES office_locations(id) ON DELETE SET NULL,
    FOREIGN KEY (punch_out_location_id) REFERENCES office_locations(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_user_type (user_type),
    INDEX idx_attendance_date (punch_in_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for leave requests
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('teacher', 'student', 'admin', 'staff') NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    attachment VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_leave_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for attendance summary (to make reporting faster)
CREATE TABLE IF NOT EXISTS attendance_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('teacher', 'student', 'admin', 'staff') NOT NULL,
    summary_date DATE NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    status ENUM('present', 'absent', 'leave', 'holiday', 'weekend', 'late', 'early_exit') DEFAULT 'present',
    work_hours DECIMAL(5, 2) DEFAULT 0,
    is_late TINYINT(1) DEFAULT 0,
    is_early_exit TINYINT(1) DEFAULT 0,
    leave_type_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_date (user_id, user_type, summary_date),
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_summary_date (summary_date),
    INDEX idx_month_year (month, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO attendance_settings (setting_key, setting_value, description) VALUES
('work_start_time', '09:00:00', 'Default work start time'),
('work_end_time', '17:00:00', 'Default work end time'),
('late_threshold_minutes', '15', 'Minutes after work start time to mark as late'),
('early_exit_threshold_minutes', '15', 'Minutes before work end time to mark as early exit'),
('weekend_days', '0,6', 'Days of week that are weekends (0=Sunday, 6=Saturday)'),
('geofencing_enabled', '1', 'Whether location-based attendance is enforced'),
('auto_punch_out', '1', 'Automatically punch out users at work end time if not done manually');

-- Insert default leave types
INSERT INTO leave_types (type_name, description, is_paid) VALUES
('Sick Leave', 'Leave taken due to illness or medical appointments', 1),
('Casual Leave', 'Leave taken for personal reasons', 1),
('Vacation', 'Annual vacation leave', 1),
('Unpaid Leave', 'Leave without pay', 0),
('Maternity Leave', 'Leave for female employees before and after childbirth', 1),
('Paternity Leave', 'Leave for male employees after childbirth', 1),
('Emergency Leave', 'Leave for family emergencies', 1);
