<?php
// =============================================
// API: Check Session Status
// SourcePoint - CamNorte Event Aggregator
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

jsonResponse([
    'success' => true,
    'logged_in' => isUserLoggedIn() || isAdminLoggedIn(),
    'role' => $_SESSION['role'] ?? null,
    'user' => isset($_SESSION['user_id']) ? [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['role'] ?? 'resident'
    ] : null,
    'admin' => isset($_SESSION['admin_id']) ? [
        'id' => $_SESSION['admin_id'],
        'name' => $_SESSION['admin_name'],
        'username' => $_SESSION['admin_username'],
        'role' => 'admin'
    ] : null
]);
?>