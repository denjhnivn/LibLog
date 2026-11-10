ALTER TABLE usage_sessions
    ADD COLUMN time_in_dt DATETIME NULL,
    ADD COLUMN time_out_dt DATETIME NULL,
    ADD COLUMN duration_minutes INT NULL,
    ADD COLUMN purpose VARCHAR(50) NULL,
    ADD COLUMN ended_by ENUM('student','admin','auto') NULL;

UPDATE usage_sessions
SET time_in_dt = TIMESTAMP(date, time_in)
WHERE time_in_dt IS NULL AND date IS NOT NULL AND time_in IS NOT NULL;

UPDATE usage_sessions
SET time_out_dt = TIMESTAMP(date, time_out)
WHERE time_out_dt IS NULL AND date IS NOT NULL AND time_out IS NOT NULL;

ALTER TABLE usage_sessions
    ADD INDEX idx_usage_sessions_student_id (student_id),
    ADD INDEX idx_usage_sessions_pc_id (pc_id),
    ADD INDEX idx_usage_sessions_date (date),
    ADD INDEX idx_usage_sessions_time_out_dt (time_out_dt);