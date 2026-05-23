<?php
// =============================================
// API: Admin Login (now uses users table with role='admin')
// SourcePoint - CamNorte Event Aggregator
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Username and password are required'], 400);
    }

    // Find user by username where role is admin
    $stmt = $pdo->prepare("SELECT id, fullname, username, email, role, password FROM users WHERE (username = ? OR email = ?) AND role = 'admin'");
    $stmt->execute([$username, $username]);
    $admin = $stmt->fetch();

    if (!$admin) {
        jsonResponse(['success' => false, 'message' => 'Invalid username or password'], 401);
    }

    // Verify password
    if (!password_verify($password, $admin['password'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid username or password'], 401);
    }

    // Set admin session
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['fullname'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['user_name'] = $admin['fullname'];
    $_SESSION['user_email'] = $admin['email'];
    $_SESSION['role'] = 'admin';

    jsonResponse([
        'success' => true,
        'message' => 'Admin login successful!',
        'admin' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'fullname' => $admin['fullname'],
            'email' => $admin['email'],
            'role' => 'admin'
        ]
    ]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error'], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Server error'], 500);
}
?>