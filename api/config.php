<?php
/* =========================================================
   config.php
   Shared by every page: session handling, auto-logout,
   login check, permissions, and the DB connection helpers.
   Include this FIRST, before any HTML output.
========================================================= */

/* ---------- SESSION ---------- */
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

/* ---------- AUTO LOGOUT AFTER 30 MINUTES (ABSOLUTE, NOT SLIDING) ----------
   ROOT CAUSE of "30 min timeout doesn't work while navigating": this used
   to reset $_SESSION['last_activity'] = time() on every single request
   (every page load AND every api/*.php AJAX call). Since navigating pages
   or the app's background AJAX calls happen more often than every 30
   minutes, the timer kept sliding forward and effectively never expired.
   Fix: record ONE fixed 'login_time' at login (see index.php) and always
   compare against that. Browsing pages no longer resets the clock -
   exactly 30 minutes after login, every page/API is locked out. */
$sessionLifetime = 1800; // 30 minutes

function isAjaxRequest() {
    // api/*.php and user_ajax.php are always called via fetch(), never by
    // direct navigation, so a redirect response is useless to them - they
    // need JSON back so the frontend can react (e.g. bounce to login).
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false
        || $script === 'user_ajax.php'
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

function denyExpiredOrLoggedOut($reason = 'unauthorized') {
    if (isAjaxRequest()) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $reason]);
    } else {
        header('Location: /' . ($reason === 'session_expired' ? '?timeout=1' : ''));
    }
    exit;
}

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (!isset($_SESSION['login_time'])) {
        // Sessions created before this fix won't have login_time - treat
        // "now" as the start rather than immediately booting everyone.
        $_SESSION['login_time'] = time();
    }
    if ((time() - $_SESSION['login_time']) > $sessionLifetime) {
        session_unset();
        session_destroy();
        denyExpiredOrLoggedOut('session_expired');
    }
}

/* ---------- LOGIN CHECK ---------- */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    denyExpiredOrLoggedOut('unauthorized');
}

/* ---------- PREVENT CACHING OF AUTHENTICATED, SESSION-DEPENDENT PAGES ----------
   Defense in depth alongside the service worker fix: makes sure neither the
   browser disk cache nor any intermediary ever serves one user's rendered
   sidebar/name/role to the next user who logs into the same browser. */
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
}

/* ---------- LOGGED-IN USER & PERMISSIONS ---------- */
$loggedInUser  = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$loggedInId    = isset($_SESSION['user_id'])  ? (int)$_SESSION['user_id'] : 0;
$isAdmin       = isset($_SESSION['is_admin']) ? (bool)$_SESSION['is_admin'] : false;
$permSettings  = $isAdmin ? true : ($_SESSION['perm_settings']  ?? false);
$permShopName  = $isAdmin ? true : ($_SESSION['perm_shop_name'] ?? false);
$permLogo      = $isAdmin ? true : ($_SESSION['perm_logo']      ?? false);
$permDataMang  = $isAdmin ? true : ($_SESSION['perm_data_mang'] ?? false);

/* ---------- DB CREDENTIALS (used by both connection styles below) ---------- */
require_once __DIR__ . '/db_credentials.php';

/* ---------- MYSQLI CONNECTION (getMysqli() / $conn) ----------
   Used directly by page files such as users.php.
   PERF FIX: this used to open unconditionally on every single request
   (including every api/*.php call, which only ever uses PDO via getDB()).
   That meant 2 DB connections per request instead of 1. Now it only
   connects the first time it's actually touched. $conn stays available
   for backward compatibility via a magic-ish pattern: pages that need it
   call getMysqli() (see users.php). */
function getMysqli() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }
    return $conn;
}

/* ---------- PDO CONNECTION (getDB()) ----------
   Used by the api/*.php endpoints (load.php, save.php, update.php,
   delete.php, categories.php, settings.php, user_ajax.php, ...).
   These were written against a PDO-style config.php - this restores
   that helper so those scripts keep working unchanged. */
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $db;
}

/* ---------- JSON RESPONSE HELPER (sendJSON()) ----------
   Used by the api/*.php endpoints to output a JSON response and stop. */
function sendJSON($data) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data);
    exit;
}

/* ---------- OTHER HELPERS ---------- */
function requireAdmin($isAdmin) {
    if (!$isAdmin) {
        if (isAjaxRequest()) {
            if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'forbidden']);
        } else {
            header("Location: barcode.php");
        }
        exit;
    }
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}