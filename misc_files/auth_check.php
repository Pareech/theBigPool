<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Dynamically set APP_BASE_URL based on environment path ---
$scriptPath = realpath(__DIR__); // Full path to this file
$dirParts = explode(DIRECTORY_SEPARATOR, $scriptPath); // Break into folder names

// Look for current_season*, and optionally raw_hockeypool
$projectFolder = '';
$prefixFolder = '';

// Reverse to find matches from the end
for ($i = count($dirParts) - 1; $i >= 0; $i--) {
    if (preg_match('/^current_season(_dev|_pre_prod)?$/', $dirParts[$i])) {
        $projectFolder = $dirParts[$i];
        $prefixFolder = $dirParts[$i - 1] ?? '';
        break;
    }
}

if ($prefixFolder === 'raw_hockeypool') {
    define('APP_BASE_URL', '/raw_hockeypool/' . $projectFolder);
} else {
    define('APP_BASE_URL', '/' . $projectFolder);
}

// --- Session timeout logic ---
$sessionTimeout = 3600;

if (!isset($_SESSION['gm_name'])) {
    redirectToLogin();
} elseif (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $sessionTimeout)) {
    // Mark session expired flag WITHOUT destroying session
    $_SESSION['session_expired'] = true;

    redirectToLogin();
} else {
    $_SESSION['login_time'] = time();
}

// --- Redirect helper ---
function redirectToLogin()
{
    // $url = APP_BASE_URL . '/index.php';
    $url = APP_BASE_URL;
    header("Location: $url");
    exit();
}

// --- Prevent caching ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// --- Check if Users Have Access to Pool Administration Menus ---
$username = $_SESSION['gm_name'] ?? '';
$privilegedUsers = ['eric', 'ian', 'stef'];
$isPrivileged = in_array(strtolower($username), $privilegedUsers);

$justMeSees = ['ian'];
$justMe = in_array(strtolower($username), $justMeSees);
