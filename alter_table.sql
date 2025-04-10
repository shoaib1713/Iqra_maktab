-- SQL statements for attendance module alterations
-- Add new columns to support multiple shifts

-- Add shift start and end time columns to attendance_logs
ALTER TABLE attendance_logs
ADD COLUMN IF NOT EXISTS shift_start TIME AFTER status,
ADD COLUMN IF NOT EXISTS shift_end TIME AFTER shift_start;

-- Add new attendance settings
INSERT INTO attendance_settings (setting_key, setting_value, description)
SELECT 'work_shifts', '[{"start":"09:00","end":"17:00","min_hours":8}]', 'Multiple work shifts configuration'
WHERE NOT EXISTS (SELECT 1 FROM attendance_settings WHERE setting_key = 'work_shifts');

INSERT INTO attendance_settings (setting_key, setting_value, description)
SELECT 'multiple_shifts_enabled', '1', 'Enable multiple punch in/out per day'
WHERE NOT EXISTS (SELECT 1 FROM attendance_settings WHERE setting_key = 'multiple_shifts_enabled');

INSERT INTO attendance_settings (setting_key, setting_value, description)
SELECT 'warn_incomplete_hours', '1', 'Warn users when punching out with incomplete hours'
WHERE NOT EXISTS (SELECT 1 FROM attendance_settings WHERE setting_key = 'warn_incomplete_hours');

-- Update existing attendance_logs to set default shift times for existing records
UPDATE attendance_logs
SET shift_start = '09:00', shift_end = '17:00'
WHERE shift_start IS NULL OR shift_end IS NULL;

-- Create new table for storing individual shift configurations (if needed)
CREATE TABLE IF NOT EXISTS work_shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shift_name VARCHAR(50) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL, 
  min_hours DECIMAL(4,2) NOT NULL DEFAULT 8.0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Ensure attendance_summary table has work_hours column for tracking total daily hours
ALTER TABLE attendance_summary
MODIFY COLUMN IF EXISTS work_hours DECIMAL(5,2) DEFAULT 0.00; 

-- Salary module fixes
-- Fix field names in teacher_salary_calculations
ALTER TABLE teacher_salary_calculations
ADD COLUMN IF NOT EXISTS teacher_id INT AFTER id,
MODIFY COLUMN IF EXISTS user_id INT,
ADD COLUMN IF NOT EXISTS period_id INT AFTER teacher_id,
ADD COLUMN IF NOT EXISTS hourly_rate DECIMAL(10,2) DEFAULT 0.00 AFTER period_id,
ADD COLUMN IF NOT EXISTS total_hours DECIMAL(10,2) DEFAULT 0.00 AFTER hourly_rate,
ADD COLUMN IF NOT EXISTS expected_hours DECIMAL(10,2) DEFAULT 0.00 AFTER total_hours,
ADD COLUMN IF NOT EXISTS total_working_hours DECIMAL(10,2) DEFAULT 0.00 AFTER hourly_rate,
ADD COLUMN IF NOT EXISTS expected_working_hours DECIMAL(10,2) DEFAULT 0.00 AFTER total_working_hours,
ADD COLUMN IF NOT EXISTS base_salary DECIMAL(10,2) DEFAULT 0.00 AFTER expected_hours,
ADD COLUMN IF NOT EXISTS deductions DECIMAL(10,2) DEFAULT 0.00 AFTER base_salary,
ADD COLUMN IF NOT EXISTS deduction_amount DECIMAL(10,2) DEFAULT 0.00 AFTER base_salary,
ADD COLUMN IF NOT EXISTS bonuses DECIMAL(10,2) DEFAULT 0.00 AFTER deductions,
ADD COLUMN IF NOT EXISTS bonus_amount DECIMAL(10,2) DEFAULT 0.00 AFTER deduction_amount,
ADD COLUMN IF NOT EXISTS final_salary DECIMAL(10,2) DEFAULT 0.00 AFTER bonuses,
ADD COLUMN IF NOT EXISTS notes TEXT AFTER final_salary,
ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'processed' AFTER notes,
ADD COLUMN IF NOT EXISTS payment_date DATE NULL AFTER status,
ADD COLUMN IF NOT EXISTS payment_method VARCHAR(30) NULL AFTER payment_date,
ADD COLUMN IF NOT EXISTS reference_number VARCHAR(50) NULL AFTER payment_method,
ADD COLUMN IF NOT EXISTS payment_notes TEXT NULL AFTER reference_number,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER payment_notes,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Data synchronization to fix column naming
UPDATE teacher_salary_calculations 
SET teacher_id = user_id 
WHERE teacher_id IS NULL AND user_id IS NOT NULL;

UPDATE teacher_salary_calculations 
SET total_hours = total_working_hours 
WHERE total_hours IS NULL AND total_working_hours IS NOT NULL;

UPDATE teacher_salary_calculations 
SET expected_hours = expected_working_hours 
WHERE expected_hours IS NULL AND expected_working_hours IS NOT NULL;

UPDATE teacher_salary_calculations 
SET deductions = deduction_amount 
WHERE deductions IS NULL AND deduction_amount IS NOT NULL;

UPDATE teacher_salary_calculations 
SET bonuses = bonus_amount 
WHERE bonuses IS NULL AND bonus_amount IS NOT NULL;

-- Create necessary tables for the salary module if they don't exist
CREATE TABLE IF NOT EXISTS salary_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT,
  user_id INT,
  period_id INT,
  salary_calculation_id INT,
  notification_type VARCHAR(30) NOT NULL,
  message TEXT,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_teacher (teacher_id),
  INDEX idx_user (user_id),
  INDEX idx_period (period_id),
  INDEX idx_calculation (salary_calculation_id)
);

-- Data synchronization for user_id/teacher_id in salary_notifications
UPDATE salary_notifications 
SET teacher_id = user_id 
WHERE teacher_id IS NULL AND user_id IS NOT NULL;

UPDATE salary_notifications 
SET user_id = teacher_id 
WHERE user_id IS NULL AND teacher_id IS NOT NULL;

-- Ensure notifications table has necessary columns
ALTER TABLE notifications
ADD COLUMN IF NOT EXISTS message TEXT AFTER title,
ADD COLUMN IF NOT EXISTS content TEXT AFTER title;

-- Synchronize notifications message/content field
UPDATE notifications 
SET message = content 
WHERE message IS NULL AND content IS NOT NULL;

UPDATE notifications 
SET content = message 
WHERE content IS NULL AND message IS NOT NULL;

-- Ensure teacher_salary_rates has consistent column naming
ALTER TABLE teacher_salary_rates 
ADD COLUMN IF NOT EXISTS teacher_id INT AFTER id,
MODIFY COLUMN IF EXISTS user_id INT,
ADD COLUMN IF NOT EXISTS effective_date DATE AFTER hourly_rate,
ADD COLUMN IF NOT EXISTS effective_from DATE AFTER hourly_rate,
ADD COLUMN IF NOT EXISTS effective_to DATE AFTER effective_from,
ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER effective_to,
ADD COLUMN IF NOT EXISTS created_by INT AFTER is_active,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER created_by,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Data synchronization for teacher_salary_rates
UPDATE teacher_salary_rates 
SET teacher_id = user_id 
WHERE teacher_id IS NULL AND user_id IS NOT NULL;

UPDATE teacher_salary_rates 
SET effective_date = effective_from 
WHERE effective_date IS NULL AND effective_from IS NOT NULL;

UPDATE teacher_salary_rates 
SET effective_from = effective_date 
WHERE effective_from IS NULL AND effective_date IS NOT NULL;

-- Fix salary_notifications foreign key constraint issues
-- This will drop the old constraint that's causing errors and add a new one
ALTER TABLE salary_notifications
DROP FOREIGN KEY IF EXISTS fk_salary_notif_calc;

-- Add proper foreign key referencing salary_calculation_id
ALTER TABLE salary_notifications
ADD CONSTRAINT fk_salary_notif_calc 
FOREIGN KEY (salary_calculation_id) 
REFERENCES teacher_salary_calculations(id) 
ON DELETE CASCADE;

-- For backward compatibility, add missing field if needed
ALTER TABLE salary_notifications
ADD COLUMN IF NOT EXISTS salary_id INT AFTER user_id;

-- Update data for backward compatibility
UPDATE salary_notifications 
SET salary_calculation_id = salary_id 
WHERE salary_calculation_id IS NULL AND salary_id IS NOT NULL;

UPDATE salary_notifications 
SET salary_id = salary_calculation_id 
WHERE salary_id IS NULL AND salary_calculation_id IS NOT NULL;


-- 09-04-2025

ALTER TABLE users
ADD COLUMN IF NOT EXISTS token VARCHAR(100) DEFAULT NULL AFTER id,
ADD COLUMN IF NOT EXISTS token_expiry DATETIME DEFAULT NULL AFTER token,
ADD COLUMN IF NOT EXISTS last_login DATETIME DEFAULT NULL AFTER token_expiry;










