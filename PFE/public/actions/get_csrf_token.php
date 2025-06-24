<?php
require '../../config/config.php';

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

header('Content-Type: application/json');

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode([
    'csrf_token' => $_SESSION['csrf_token'],
    'user_logged_in' => isset($_SESSION['user']),
    'user_role' => $_SESSION['user']['role'] ?? null
]);
?>
