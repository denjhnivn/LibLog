<?php
require_once __DIR__ . '/db.php';

const SETTING_DEFAULTS = [
    'library_name' => 'Library',
    'opening_time' => '08:00',
    'closing_time' => '20:00',
    'max_session_minutes' => '180',
    'logs_per_page' => '25',
];

function setting_time_is_valid(string $value): bool
{
    return preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/', $value) === 1;
}

function setting_text_length(string $value): int
{
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    $count = preg_match_all('/./us', $value);
    return $count === false ? -1 : $count;
}

function setting_library_name_is_valid(string $value): bool
{
    $value = trim($value);
    $length = setting_text_length($value);
    return $length >= 1 && $length <= 60;
}

/** Returns all settings in one query, falling back safely if storage is unavailable. */
function get_settings(): array
{
    static $settings = null;
    if ($settings !== null) return $settings;

    $settings = SETTING_DEFAULTS;
    try {
        $query = db()->prepare('SELECT setting_key, setting_value FROM settings');
        $query->execute();
        $stored = $query->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $exception) {
        return $settings;
    }

    if (isset($stored['library_name']) && is_string($stored['library_name'])) {
        $value = trim($stored['library_name']);
        if (setting_library_name_is_valid($value)) $settings['library_name'] = $value;
    }
    if (isset($stored['opening_time']) && is_string($stored['opening_time']) && setting_time_is_valid($stored['opening_time'])) {
        $settings['opening_time'] = $stored['opening_time'];
    }
    if (isset($stored['closing_time']) && is_string($stored['closing_time']) && setting_time_is_valid($stored['closing_time'])) {
        $settings['closing_time'] = $stored['closing_time'];
    }
    if ($settings['closing_time'] <= $settings['opening_time']) {
        $settings['opening_time'] = SETTING_DEFAULTS['opening_time'];
        $settings['closing_time'] = SETTING_DEFAULTS['closing_time'];
    }

    if (isset($stored['max_session_minutes'])) {
        $minutes = filter_var($stored['max_session_minutes'], FILTER_VALIDATE_INT);
        if ($minutes !== false && $minutes >= 15 && $minutes <= 720) $settings['max_session_minutes'] = (string) $minutes;
    }
    if (isset($stored['logs_per_page']) && in_array((string) $stored['logs_per_page'], ['10', '25', '50', '100'], true)) {
        $settings['logs_per_page'] = (string) $stored['logs_per_page'];
    }
    return $settings;
}

function get_setting(string $key): string
{
    $settings = get_settings();
    return (string) ($settings[$key] ?? SETTING_DEFAULTS[$key] ?? '');
}

/** Validates and saves all settings atomically. Returns field errors, or an empty array. */
function save_settings(array $values): array
{
    $normalized = [];
    $errors = [];

    $libraryName = is_string($values['library_name'] ?? null) ? trim($values['library_name']) : '';
    if (!setting_library_name_is_valid($libraryName)) $errors['library_name'] = 'Enter a library name using 1 to 60 characters.';
    $normalized['library_name'] = $libraryName;

    $openingTime = is_string($values['opening_time'] ?? null) ? trim($values['opening_time']) : '';
    if (!setting_time_is_valid($openingTime)) $errors['opening_time'] = 'Enter a valid time in 24-hour HH:MM format.';
    $normalized['opening_time'] = $openingTime;

    $closingTime = is_string($values['closing_time'] ?? null) ? trim($values['closing_time']) : '';
    if (!setting_time_is_valid($closingTime)) {
        $errors['closing_time'] = 'Enter a valid time in 24-hour HH:MM format.';
    } elseif (setting_time_is_valid($openingTime) && $closingTime <= $openingTime) {
        $errors['closing_time'] = 'Closing time must be later than opening time.';
    }
    $normalized['closing_time'] = $closingTime;

    $minuteValue = is_string($values['max_session_minutes'] ?? null) || is_int($values['max_session_minutes'] ?? null)
        ? (string) $values['max_session_minutes'] : '';
    $minutes = filter_var($minuteValue, FILTER_VALIDATE_INT);
    if ($minutes === false || $minutes < 15 || $minutes > 720) {
        $errors['max_session_minutes'] = 'Enter a whole number from 15 to 720 minutes.';
    }
    $normalized['max_session_minutes'] = $minutes === false ? '' : (string) $minutes;

    $logsPerPage = is_string($values['logs_per_page'] ?? null) ? $values['logs_per_page'] : (is_int($values['logs_per_page'] ?? null) ? (string) $values['logs_per_page'] : '');
    if (!in_array($logsPerPage, ['10', '25', '50', '100'], true)) $errors['logs_per_page'] = 'Choose 10, 25, 50, or 100 rows per page.';
    $normalized['logs_per_page'] = $logsPerPage;

    if ($errors) return $errors;

    try {
        $database = db();
        $database->beginTransaction();
        $save = $database->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach (SETTING_DEFAULTS as $key => $_default) $save->execute([$key, $normalized[$key]]);
        $database->commit();
        return [];
    } catch (Throwable $exception) {
        if (isset($database) && $database->inTransaction()) $database->rollBack();
        return ['_form' => 'Unable to save settings. Check that the settings migration has been applied, then try again.'];
    }
}
