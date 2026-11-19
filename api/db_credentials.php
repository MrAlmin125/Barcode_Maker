<?php
/* =========================================================
   db_credentials.php
   The ONE place the database credentials are defined.
   api/config.php and index.php both include this instead of
   each hardcoding the same host/user/pass/db name separately.
========================================================= */

if (!defined('DB_HOST')) define('DB_HOST', 'Host Name');
if (!defined('DB_USER')) define('DB_USER', 'User Name');
if (!defined('DB_PASS')) define('DB_PASS', 'Password');
if (!defined('DB_NAME')) define('DB_NAME', 'Database Name');
