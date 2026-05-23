<?php
// =============================================
// API: User Registration
// SourcePoint - CamNorte Event Aggregator
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/session.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $fullname = trim($input['fullname'] ?? '');
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $mobile = trim($input['mobile'] ?? '');
    $password = $input['password'] ?? '';
    $organization = trim($input['organization'] ?? '');

    if (empty($fullname) || empty($username) || empty($email) || empty($mobile) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Full name, username, email, mobile, and password are required'], 400);
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
    }

    // Validate mobile number (Philippine format: 09XX XXX XXXX)
    if (!preg_match('/^09\d{9}$/', $mobile)) {
        jsonResponse(['success' => false, 'message' => 'Invalid mobile number. Must be 11 digits starting with 09'], 400);
    }

    // Validate password length
    if (strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
    }

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Username already taken'], 409);
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Email already registered'], 409);
    }

    // Check if mobile already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
    $stmt->execute([$mobile]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Mobile number already registered'], 409);
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user (with username and role defaults to 'resident')
    $stmt = $pdo->prepare("INSERT INTO users (fullname, username, email, mobile, organization, password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$fullname, $username, $email, $mobile, $organization, $hashedPassword]);

    $userId = $pdo->lastInsertId();

    // Auto-login the user after registration
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $fullname;
    $_SESSION['user_email'] = $email;
    $_SESSION['role'] = 'resident';

    jsonResponse([
        'success' => true,
        'message' => 'Registration successful! You are now subscribed to SourcePoint.',
        'user' => [
            'id' => $userId,
            'fullname' => $fullname,
            'username' => $username,
            'email' => $email,
            'mobile' => $mobile,
            'organization' => $organization,
            'role' => 'resident'
        ]
    ]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
?>