<?php
// logout.php
require_once __DIR__ . '/includes/auth.php';
Auth::logout();
session_start();
$_SESSION['flash_success'] = "You have been logged out successfully.";
header("Location: /GabayKarera/login.php");
exit;
?>



