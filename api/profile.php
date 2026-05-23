<?php
// =============================================
// API: User Profile (GET = fetch, PUT = update)
// SourcePoint - CamNorte Event Aggregator
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

requireUserLogin();

try {
    $userId = $_SESSION['user_id'];

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $stmt = $pdo->prepare("SELECT id, fullname, username, email, mobile, organization, role, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                jsonResponse(['success' => false, 'message' => 'User not found'], 404);
            }

            jsonResponse([
                'success' => true,
                'user' => $user
            ]);
            break;

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);

            $fullname = trim($input['fullname'] ?? '');
            $username = trim($input['username'] ?? '');
            $email = trim($input['email'] ?? '');
            $mobile = trim($input['mobile'] ?? '');
            $organization = trim($input['organization'] ?? '');
            $password = $input['password'] ?? '';

            if (empty($fullname) || empty($email) || empty($mobile)) {
                jsonResponse(['success' => false, 'message' => 'Full name, email, and mobile are required'], 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
            }

            if (!preg_match('/^09\d{9}$/', $mobile)) {
                jsonResponse(['success' => false, 'message' => 'Invalid mobile number. Must be 11 digits starting with 09'], 400);
            }

            // Check if username is taken by another user
            if (!empty($username)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $userId]);
                if ($stmt->fetch()) {
                    jsonResponse(['success' => false, 'message' => 'Username already taken by another account'], 409);
                }
            }

            // Check if email is taken by another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Email already used by another account'], 409);
            }

            // Check if mobile is taken by another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? AND id != ?");
            $stmt->execute([$mobile, $userId]);
            if ($stmt->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Mobile number already used by another account'], 409);
            }

            if (!empty($password)) {
                if (strlen($password) < 6) {
                    jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
                }
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET fullname = ?, username = ?, email = ?, mobile = ?, organization = ?, password = ? WHERE id = ?");
                $stmt->execute([$fullname, $username, $email, $mobile, $organization, $hashedPassword, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET fullname = ?, username = ?, email = ?, mobile = ?, organization = ? WHERE id = ?");
                $stmt->execute([$fullname, $username, $email, $mobile, $organization, $userId]);
            }

            // Update session
            $_SESSION['user_name'] = $fullname;
            $_SESSION['user_email'] = $email;

            jsonResponse([
                'success' => true,
                'message' => 'Profile updated successfully!'
            ]);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error'], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Server error'], 500);
}
?>