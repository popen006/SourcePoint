<?php
// =============================================
// API: User Management (Admin)
// SourcePoint - CamNorte Event Aggregator
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require admin login for all operations
requireAdminLogin();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Fetch users with optional role filter
            $role = $_GET['role'] ?? 'all';

            $users = [];

            if ($role === 'all') {
                $stmt = $pdo->query("SELECT id, fullname, username, email, mobile, role, organization, created_at FROM users ORDER BY created_at DESC");
                $users = $stmt->fetchAll();
            } elseif (in_array($role, ['admin', 'resident'])) {
                $stmt = $pdo->prepare("SELECT id, fullname, username, email, mobile, role, organization, created_at FROM users WHERE role = ? ORDER BY created_at DESC");
                $stmt->execute([$role]);
                $users = $stmt->fetchAll();
            } else {
                jsonResponse(['success' => false, 'message' => 'Invalid role filter'], 400);
            }

            jsonResponse([
                'success' => true,
                'users' => $users,
                'total' => count($users)
            ]);
            break;

        case 'POST':
            // Create a new user (admin or resident)
            $input = json_decode(file_get_contents('php://input'), true);

            $fullname = trim($input['fullname'] ?? '');
            $username = trim($input['username'] ?? '');
            $email = trim($input['email'] ?? '');
            $mobile = trim($input['mobile'] ?? '');
            $password = $input['password'] ?? '';
            $role = trim($input['role'] ?? 'resident');
            $organization = trim($input['organization'] ?? '');

            // Validate required fields
            if (empty($fullname) || empty($username) || empty($email) || empty($mobile) || empty($password)) {
                jsonResponse(['success' => false, 'message' => 'Full name, username, email, mobile, and password are required'], 400);
            }

            // Validate role
            if (!in_array($role, ['resident', 'admin'])) {
                jsonResponse(['success' => false, 'message' => 'Role must be resident or admin'], 400);
            }

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
            }

            // Validate mobile number (Philippine format)
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

            // Begin transaction
            $pdo->beginTransaction();

            // Insert into users table
            $stmt = $pdo->prepare("INSERT INTO users (fullname, username, email, mobile, role, organization, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fullname, $username, $email, $mobile, $role, $organization, $hashedPassword]);

            $userId = $pdo->lastInsertId();

            // If creating an admin, also insert into the admins table for FK references
            if ($role === 'admin') {
                $stmt = $pdo->prepare("INSERT INTO admins (username, email, password, fullname) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashedPassword, $fullname]);
            }

            $pdo->commit();

            $roleLabel = ($role === 'admin') ? 'Admin' : 'Resident';

            jsonResponse([
                'success' => true,
                'message' => $roleLabel . ' account created successfully!',
                'user' => [
                    'id' => $userId,
                    'fullname' => $fullname,
                    'username' => $username,
                    'email' => $email,
                    'mobile' => $mobile,
                    'role' => $role,
                    'organization' => $organization
                ]
            ]);
            break;

        case 'PUT':
            // Update an existing user
            $input = json_decode(file_get_contents('php://input'), true);

            $id = (int)($input['id'] ?? 0);
            $fullname = trim($input['fullname'] ?? '');
            $username = trim($input['username'] ?? '');
            $email = trim($input['email'] ?? '');
            $mobile = trim($input['mobile'] ?? '');
            $role = trim($input['role'] ?? 'resident');
            $organization = trim($input['organization'] ?? '');
            $password = $input['password'] ?? '';

            if (!$id || empty($fullname) || empty($username) || empty($email) || empty($mobile)) {
                jsonResponse(['success' => false, 'message' => 'ID, full name, username, email, and mobile are required'], 400);
            }

            if (!in_array($role, ['resident', 'admin'])) {
                jsonResponse(['success' => false, 'message' => 'Role must be resident or admin'], 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
            }

            if (!preg_match('/^09\d{9}$/', $mobile)) {
                jsonResponse(['success' => false, 'message' => 'Invalid mobile number. Must be 11 digits starting with 09'], 400);
            }

            if (!empty($password) && strlen($password) < 6) {
                jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
            }

            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $existingUser = $stmt->fetch();

            if (!$existingUser) {
                jsonResponse(['success' => false, 'message' => 'User not found'], 404);
            }

            // Check uniqueness (exclude current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $id]);
            if ($stmt->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Username already taken'], 409);
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Email already registered'], 409);
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? AND id != ?");
            $stmt->execute([$mobile, $id]);
            if ($stmt->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Mobile number already registered'], 409);
            }

            $pdo->beginTransaction();

            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET fullname = ?, username = ?, email = ?, mobile = ?, role = ?, organization = ?, password = ? WHERE id = ?");
                $stmt->execute([$fullname, $username, $email, $mobile, $role, $organization, $hashedPassword, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET fullname = ?, username = ?, email = ?, mobile = ?, role = ?, organization = ? WHERE id = ?");
                $stmt->execute([$fullname, $username, $email, $mobile, $role, $organization, $id]);
            }

            // Sync with admins table if role is admin
            if ($role === 'admin') {
                $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                $adminExists = $stmt->fetch();

                if ($adminExists) {
                    if (!empty($password)) {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE admins SET username = ?, email = ?, fullname = ?, password = ? WHERE id = ?");
                        $stmt->execute([$username, $email, $fullname, $hashedPassword, $adminExists['id']]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE admins SET username = ?, email = ?, fullname = ? WHERE id = ?");
                        $stmt->execute([$username, $email, $fullname, $adminExists['id']]);
                    }
                } else {
                    $hashedPassword = password_hash($password ?: bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO admins (username, email, password, fullname) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashedPassword, $fullname]);
                }
            } else {
                // If changing from admin to resident, remove from admins table
                if ($existingUser['role'] === 'admin') {
                    $pdo->prepare("DELETE FROM admins WHERE username = ? OR email = ?")->execute([$username, $email]);
                }
            }

            $pdo->commit();

            jsonResponse([
                'success' => true,
                'message' => 'User updated successfully!'
            ]);
            break;

        case 'DELETE':
            // Delete a user
            $input = json_decode(file_get_contents('php://input'), true);
            $id = (int)($input['id'] ?? 0);

            if (!$id) {
                jsonResponse(['success' => false, 'message' => 'User ID is required'], 400);
            }

            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();

            if (!$user) {
                jsonResponse(['success' => false, 'message' => 'User not found'], 404);
            }

            $pdo->beginTransaction();

            // If admin, delete from admins table first
            if ($user['role'] === 'admin') {
                $stmt = $pdo->prepare("DELETE FROM admins WHERE username IN (SELECT username FROM users WHERE id = ?)");
                $stmt->execute([$id]);
            }

            // Delete from users table
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->commit();

            jsonResponse([
                'success' => true,
                'message' => 'User deleted successfully!'
            ]);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
?>