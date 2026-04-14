-- Attendance Table for Employee Attendance System
-- Run this SQL to create the attendance table in your database

CREATE TABLE IF NOT EXISTS attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_username VARCHAR(100) NOT NULL,
    clock_in DATETIME NOT NULL,
    clock_out DATETIME NULL,
    date DATE NOT NULL,
    break_started DATETIME NULL,
    break_total INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee_date (employee_username, date),
    INDEX idx_date (date)
);

-- The table tracks employee attendance with the following features:
-- - clock_in: When employee starts their shift
-- - clock_out: When employee ends their shift (NULL if still working)
-- - break_started: When current break started (NULL if not on break)
-- - break_total: Total break time in seconds accumulated
-- - date: The work date for easy querying

-- Sample data (optional)
-- INSERT INTO attendance (employee_username, clock_in, date) VALUES
-- ('john_doe', '2024-01-15 09:00:00', '2024-01-15'),
-- ('jane_smith', '2024-01-15 08:45:00', '2024-01-15');