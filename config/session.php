<?php
// =============================================
// Session Configuration
// SourcePoint - CamNorte Event Aggregator
// =============================================

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
// XAMPP/localhost stability
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');
ini_set('session.use_strict_mode', 0);
// Ensure a writable session save path
ini_set('session.save_path', __DIR__ . '/../tmp/sessions');

// Make sure session id cookie exists and is consistent
if (empty(session_id())) {
    session_name('PHPSESSID');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper function to check if user is logged in
function isUserLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper function to check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Helper function to get the current user's role
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

// Helper function to check if logged-in user has a specific role
function hasRole(string $role): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Helper function to require user login
function requireUserLogin() {
    if (!isUserLoggedIn()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Please log in first'
        ]);
        exit;
    }
}

// Helper function to require admin login
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Please log in as admin first'
        ]);
        exit;
    }
}

// Helper function to send JSON response
function jsonResponse(mixed $data, int $statusCode = 200): void {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}
?>