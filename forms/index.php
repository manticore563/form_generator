<?php
/**
 * Forms Directory Index
 * Handles routing for public form access
 */

// Check if a share link is provided
if (isset($_GET['link'])) {
    // Redirect to the view page with the link parameter
    header('Location: view.php?link=' . urlencode($_GET['link']));
    exit;
}

// If no link provided, redirect to main site
header('Location: ../index.php');
exit;
?>