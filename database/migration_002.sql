CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(64) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
);

INSERT INTO settings (setting_key, setting_value) VALUES
    ('closing_time', '20:00'),
    ('max_session_minutes', '180')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
