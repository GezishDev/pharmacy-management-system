

<?php
session_start();

/* Unset session variables */
$_SESSION = [];

/* Destroy the session */
session_destroy();

/* Prevent browser caching */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

/* Redirect to login */
header("Location: login.php");
exit;
