CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

SET @settings_updated_at_sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'settings'
          AND COLUMN_NAME = 'updated_at'
    ),
    'SELECT 1',
    'ALTER TABLE settings ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
);
PREPARE settings_updated_at_statement FROM @settings_updated_at_sql;
EXECUTE settings_updated_at_statement;
DEALLOCATE PREPARE settings_updated_at_statement;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('library_name', 'Library'),
    ('opening_time', '08:00'),
    ('closing_time', '20:00'),
    ('max_session_minutes', '180'),
    ('logs_per_page', '25');

DELETE FROM settings WHERE setting_key = 'cron_secret_token';
