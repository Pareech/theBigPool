<?php
function get_log_dir(): string
{
    $logDir = stripos(PHP_OS, 'Darwin') !== false
        ? '/Users/Ian/Sites/raw_hockeypool/log_stuff'
        : '/volume1/log_stuff';

    if (!file_exists($logDir)) {
        mkdir($logDir, 0775, true);
    }

    return $logDir;
}

function log_timestamp(): string
{
    return (new DateTime('now', new DateTimeZone('America/Toronto')))
        ->format('Y-m-d H:i:s');
}

function log_login_attempt($message, $includeIpAndUser = true, $username = null)
{
    $logDir = get_log_dir();
    $logFile = $logDir . '/login_activity.log';
    $date = log_timestamp();


    if ($includeIpAndUser && $username !== null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logEntry = "[$date] - IP: $ip - $message: $username\n";
    } else {
        $logEntry = "[$date] - $message\n";
    }

    if (file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
        file_put_contents($logDir . '/debug_log_check.txt', "[$date] Failed to write login_activity.log\n", FILE_APPEND);
    }
}

function log_honeypot_attempt($username = 'unknown')
{
    $logDir = get_log_dir();
    $logFile = $logDir . '/honeypot.log';
    $date = log_timestamp();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $honeypotInput = $_POST['email'] ?? '(empty)';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown UA';

    // Clean up input and user-agent for log readability
    $sanitizedInput = trim(preg_replace('/\s+/', ' ', $honeypotInput));
    $sanitizedUA = trim(preg_replace('/\s+/', ' ', $userAgent));

    $logEntry = "[$date] - IP: $ip - Honeypot triggered - Username: $username - Input: \"$sanitizedInput\" - UA: \"$sanitizedUA\"\n";

    if (file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
        file_put_contents($logDir . '/debug_log_check.txt', "[$date] Failed to write honeypot.log\n", FILE_APPEND);
    }
}

function log_password_reset($message, $includeIpAndUser = true, $username = null)
{
    $logDir = get_log_dir();
    $logFile = $logDir . '/password_reset.log';
    $date = log_timestamp();


    if ($includeIpAndUser && $username !== null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logEntry = "[$date] - IP: $ip - $message: $username\n";
    } else {
        $logEntry = "[$date] - $message\n";
    }

    if (file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
        file_put_contents($logDir . '/debug_log_check.txt', "[$date] Failed to write password_reset.log\n", FILE_APPEND);
    }
}

function log_deadline_email($message)
{
    $logDir = get_log_dir();
    $logFile = $logDir . '/rhcp_deadline_email.log';
    $date = log_timestamp();

    file_put_contents($logFile, "[$date] $message\n", FILE_APPEND | LOCK_EX);
}

function log_waiver_update($message)
{
    $logDir = get_log_dir();
    $logFile = $logDir . '/rhcp_waivers_updated.log';
    $date = log_timestamp();

    file_put_contents($logFile, "[$date] $message\n", FILE_APPEND | LOCK_EX);
}

function log_skater_stats($message)
{
    $logDir = get_log_dir();
    $logFile = $logDir . '/rhcp_get_skater_pts.log';
    $date = log_timestamp();

    $logEntry = "[$date] $message\n";

    if (file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
        file_put_contents(
            $logDir . '/debug_log_check.txt',
            "[$date] Failed to write rhcp_get_skater_pts.log\n",
            FILE_APPEND
        );
    }
}

function log_goalie_stats($message)
{
    $logDir = get_log_dir();
    $logFile = $logDir . '/rhcp_get_goalie_pts.log';
    $date = log_timestamp();

    $logEntry = "[$date] $message\n";

    if (file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
        file_put_contents(
            $logDir . '/debug_log_check.txt',
            "[$date] Failed to write rhcp_get_goalie_pts.log\n",
            FILE_APPEND
        );
    }
}

function log_daily_pts_change($message)
{
    $logDir = get_log_dir();
    $logFile = $logDir . '/rhcp_daily_pts_change.log';
    $date = log_timestamp();

    $logEntry = "[$date] $message\n";

    if (file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
        file_put_contents(
            $logDir . '/debug_log_check.txt',
            "[$date] Failed to write rhcp_daily_pts_change.log\n",
            FILE_APPEND
        );
    }
}