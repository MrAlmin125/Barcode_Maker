<?php
/* =========================================================
   db_credentials.php
   The ONE place the database credentials are defined.
   api/config.php and index.php both include this instead of
   each hardcoding the same host/user/pass/db name separately.
========================================================= */

if (!defined('DB_HOST')) define('DB_HOST', 'sql110.infinityfree.com');
if (!defined('DB_USER')) define('DB_USER', 'if0_40706792');
if (!defined('DB_PASS')) define('DB_PASS', 'NewTree1');
if (!defined('DB_NAME')) define('DB_NAME', 'if0_40706792_barcodes');
