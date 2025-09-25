<?php
/**
 * Admin Index - Redirects to Dashboard
 */

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/simple-login.php');
    exit;
}

// Redirect to dashboard
header('Location: dashboard.php');
exit;
?>