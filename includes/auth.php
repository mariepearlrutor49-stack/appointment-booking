<?php
/**
 * includes/auth.php
 * ---------------------------------------------------------------
 * Include this at the top of every page that requires login.
 * It loads the DB connection + helpers and blocks guests.
 */
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['admin_id'])) {
    flash('Please log in first.', 'warning');
    redirect('login.php');
}
