<?php
/**
 * logout.php - Destroys the session and returns to the login page.
 */
require_once 'includes/functions.php';

$_SESSION = [];
session_destroy();

redirect('login.php');
