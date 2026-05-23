<?php
// =============================================
// API: Forgot Password
// SourcePoint - CamNorte Event Aggregator
// POST = Request password reset via SMS
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/sms_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $step = $input['step'] ?? 'request'; // 'request' or 'reset'
    $mobile = trim($input['mobile'] ?? '');

    if (empty($mobile)) {
        jsonResponse(['success' => false, 'message' => 'Mobile number is required'], 400);
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, fullname FROM users WHERE mobile = ? OR email = ?");
    $stmt->execute([$mobile, $mobile]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'No account found with that mobile number'], 404);
    }

    if ($step === 'request') {
        // Generate a 6-digit reset code
        $resetCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Save OTP to the database, invalidate old unused codes for this user
        $pdo->prepare("UPDATE otp_codes SET is_used = 1 WHERE user_id = ?")->execute([$user['id']]);
        
        $stmt = $pdo->prepare("INSERT INTO otp_codes (user_id, mobile, otp_code, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user['id'], $mobile, $resetCode, $expiresAt]);

        // Send OTP via UniSMS
        $message = "Your SourcePoint Verification PIN is {$resetCode}. Please enter this to continue.";
        $smsSent = sendUniSMS($mobile, $message, $user['id']);

        if (!$smsSent) {
             jsonResponse(['success' => false, 'message' => 'Failed to send SMS code. Please try again later.']);
        }

        jsonResponse([
            'success' => true,
            'message' => 'A verification code has been sent to your mobile number via SMS.'
        ]);

    } elseif ($step === 'reset') {
        $code = trim($input['code'] ?? '');
        $newPassword = $input['new_password'] ?? '';

        if (empty($code) || empty($newPassword)) {
            jsonResponse(['success' => false, 'message' => 'Code and new password are required'], 400);
        }

        if (strlen($newPassword) < 6) {
            jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
        }

        // Verify OTP from database
        $stmt = $pdo->prepare("SELECT * FROM otp_codes WHERE user_id = ? AND otp_code = ? AND is_used = 0 ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user['id'], $code]);
        $otpRecord = $stmt->fetch();

        if (!$otpRecord) {
            jsonResponse(['success' => false, 'message' => 'Invalid or expired verification code.'], 400);
        }

        if (strtotime($otpRecord['expires_at']) < time()) {
            // Mark as expired/used
            $pdo->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?")->execute([$otpRecord['id']]);
            jsonResponse(['success' => false, 'message' => 'This verification code has expired. Please request a new one.'], 400);
        }

        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $user['id']]);

        // Mark OTP as used
        $pdo->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?")->execute([$otpRecord['id']]);

        jsonResponse([
            'success' => true,
            'message' => 'Password has been reset successfully! You can now log in with your new password.'
        ]);
    }

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Server error'], 500);
}
?>