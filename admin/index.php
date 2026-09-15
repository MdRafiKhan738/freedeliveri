<?php
session_start();

if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: admin-login.php');
    exit;
}

header('Location: admin-dashboard.php');
exit;

