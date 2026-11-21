<?php
/**
 * index.php - Entry point. Sends visitors to the dashboard or login page.
 */
require_once 'includes/functions.php';

redirect(!empty($_SESSION['admin_id']) ? 'dashboard.php' : 'login.php');
