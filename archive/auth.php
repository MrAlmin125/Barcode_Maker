<?php
session_start();

/* =========================
   CONFIG
========================= */

$timeout = 1800; // 30 minutes

/* =========================
   CHECK LOGIN
========================= */

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.php");
    exit;
}

/* =========================
   AUTO LOGOUT TIMER
========================= */

if (isset($_SESSION['last_activity'])) {

    if (time() - $_SESSION['last_activity'] > $timeout) {

        session_unset();
        session_destroy();

        header("Location: index.php?msg=timeout");
        exit;
    }
}

/* =========================
   UPDATE ACTIVITY
========================= */

$_SESSION['last_activity'] = time();
?>