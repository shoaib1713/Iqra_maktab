-- Add minimum_working_hours column to teacher_salary_rates table if not exists
ALTER TABLE teacher_salary_rates 
ADD COLUMN minimum_working_hours DECIMAL(5,2) DEFAULT 3.00 
COMMENT 'Minimum working hours per day for the teacher';

-- Create teacher_class_assignments table
CREATE TABLE IF NOT EXISTS teacher_class_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL COMMENT 'Teacher ID from users table',
    class_name VARCHAR(100) NOT NULL COMMENT 'Name of the class',
    subject VARCHAR(100) NOT NULL COMMENT 'Subject taught',
    class_hours DECIMAL(5,2) NOT NULL DEFAULT 1.0 COMMENT 'Hours allocated for this class per day',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether this assignment is active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create daily_salary_calculations table to store daily salary amounts
CREATE TABLE IF NOT EXISTS daily_salary_calculations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL COMMENT 'Teacher ID from users table',
    calculation_date DATE NOT NULL COMMENT 'Date of salary calculation',
    base_amount DECIMAL(10,2) NOT NULL COMMENT 'Base salary amount for the day',
    deduction_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Deduction amount for the day',
    final_amount DECIMAL(10,2) NOT NULL COMMENT 'Final salary amount after deductions',
    working_hours DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Actual hours worked',
    required_hours DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Required minimum hours',
    minutes_short INT DEFAULT 0 COMMENT 'Minutes short of required hours',
    notes TEXT COMMENT 'Additional notes or explanation',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_teacher_date (teacher_id, calculation_date),
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add new settings to salary_settings table for monthly config
INSERT INTO salary_settings (setting_key, setting_value, description) 
VALUES 
('default_monthly_days', '30', 'Default number of days in a month for salary calculation'),
('deduction_per_minute', '2', 'Deduction amount per minute of missed work (in INR)'); 