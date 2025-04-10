-- SQL statements for salary module tables

-- Teacher salary configurations
CREATE TABLE IF NOT EXISTS salary_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(50) NOT NULL UNIQUE,
  setting_value TEXT NOT NULL,
  description VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Teacher salary rates
CREATE TABLE IF NOT EXISTS teacher_salary_rates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  user_id INT NOT NULL,
  hourly_rate DECIMAL(10,2) NOT NULL,
  effective_date DATE NOT NULL,
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_teacher (teacher_id),
  CONSTRAINT fk_salary_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

-- Salary periods
CREATE TABLE IF NOT EXISTS salary_periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  period_name VARCHAR(50) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_processed TINYINT(1) NOT NULL DEFAULT 0,
  is_locked TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_period_dates (start_date, end_date)
);

-- Salary deduction rules
CREATE TABLE IF NOT EXISTS salary_deduction_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rule_name VARCHAR(100) NOT NULL,
  deduction_value DECIMAL(5,2) NOT NULL,
  percentage DECIMAL(5,2) NULL,
  threshold_hours DECIMAL(5,2) NULL,
  hours_threshold DECIMAL(5,2) NULL,
  deduction_type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
  fixed_amount DECIMAL(10,2) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Teacher salary calculations
CREATE TABLE IF NOT EXISTS teacher_salary_calculations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  user_id INT NOT NULL,
  period_id INT NOT NULL,
  hourly_rate DECIMAL(10,2) NOT NULL,
  total_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
  total_working_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
  expected_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
  expected_working_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
  base_salary DECIMAL(10,2) NOT NULL,
  deductions DECIMAL(10,2) NOT NULL DEFAULT 0,
  deduction_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  bonuses DECIMAL(10,2) NOT NULL DEFAULT 0,
  bonus_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  final_salary DECIMAL(10,2) NOT NULL,
  notes TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'processed',
  payment_date DATE NULL,
  payment_method VARCHAR(30) NULL,
  reference_number VARCHAR(50) NULL,
  payment_notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_teacher (teacher_id),
  INDEX idx_period (period_id),
  CONSTRAINT fk_salary_calc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_salary_calc_period FOREIGN KEY (period_id) REFERENCES salary_periods(id) ON DELETE RESTRICT,
  UNIQUE KEY unique_teacher_period (teacher_id, period_id),
  UNIQUE KEY unique_user_period (user_id, period_id)
);

-- Salary calculation logs
CREATE TABLE IF NOT EXISTS salary_calculation_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  salary_id INT NOT NULL,
  log_type ENUM('calculation', 'adjustment', 'payment', 'notification') NOT NULL,
  log_details TEXT NOT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_salary_log_calc FOREIGN KEY (salary_id) REFERENCES teacher_salary_calculations(id) ON DELETE CASCADE
);

-- Salary notifications
CREATE TABLE IF NOT EXISTS salary_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  user_id INT NOT NULL,
  period_id INT NOT NULL,
  salary_calculation_id INT NOT NULL,
  notification_type VARCHAR(30) NOT NULL,
  notification_title VARCHAR(100) NULL,
  notification_text TEXT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_teacher (teacher_id),
  INDEX idx_period (period_id),
  INDEX idx_calculation (salary_calculation_id),
  CONSTRAINT fk_salary_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_salary_notif_calc FOREIGN KEY (salary_calculation_id) REFERENCES teacher_salary_calculations(id) ON DELETE CASCADE
);

-- Insert default settings
INSERT INTO salary_settings (setting_key, setting_value, description) VALUES
('salary_calculation_day', '1', 'Day of the month when salary calculation is triggered'),
('minimum_working_hours_per_day', '8', 'Minimum working hours required per day'),
('working_days_per_week', '5', 'Number of working days per week (typically 5 for Monday-Friday)'),
('overtime_multiplier', '1.5', 'Multiplier for overtime hours'),
('enable_deductions', '1', 'Enable deductions for incomplete hours (1=Yes, 0=No)'),
('notification_enabled', '1', 'Enable salary notifications (1=Yes, 0=No)'),
('default_hourly_rate', '20', 'Default hourly rate for new teachers'),
('salary_period_type', 'monthly', 'Salary period type (monthly, bi-weekly, weekly)'),
('auto_process_salary', '1', 'Automatically process salary at period end (1=Yes, 0=No)');

-- Insert default deduction rules
INSERT INTO salary_deduction_rules (rule_name, deduction_value, percentage, threshold_hours, hours_threshold, deduction_type) VALUES
('Minor Incomplete Hours', 5.00, 5.00, 1, 1, 'percentage'),
('Moderate Incomplete Hours', 10.00, 10.00, 2, 2, 'percentage'),
('Major Incomplete Hours', 15.00, 15.00, 4, 4, 'percentage'); 