<?php
// =============================================
// API: Resident Login
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
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Email and password are required'], 400);
    }

    // Find user by email or username
    $stmt = $pdo->prepare("SELECT id, fullname, username, email, mobile, organization, role, password FROM users WHERE (email = ? OR username = ?) AND role = 'resident'");
    $stmt->execute([$email, $email]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Invalid email or password'], 401);
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid email or password'], 401);
    }

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['fullname'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role'] = $user['role'];

    jsonResponse([
        'success' => true,
        'message' => 'Login successful!',
        'user' => [
            'id' => $user['id'],
            'fullname' => $user['fullname'],
            'email' => $user['email'],
            'mobile' => $user['mobile'],
            'organization' => $user['organization'],
            'role' => $user['role']
        ]
    ]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error'], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Server error'], 500);
}
?>