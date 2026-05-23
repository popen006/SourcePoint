<?php
// =============================================
// UniSMS Helper Functions
// SourcePoint - CamNorte Event Aggregator
// =============================================

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/sms_config.php';

/**
 * Format Philippine mobile number to E.164 (+639XXXXXXXXX) as expected by UniSMS
 * 
 * @param string $mobile Raw mobile number
 * @return string Formatted mobile number
 */
function formatMobileForUniSMS(string $mobile): string {
    // Strip non-digit characters
    $clean = preg_replace('/\D/', '', $mobile);
    
    // Philippines mobile numbers:
    // 09XXXXXXXXX (11 digits) -> +639XXXXXXXXX
    if (preg_match('/^09\d{9}$/', $clean)) {
        return '+63' . substr($clean, 1);
    }
    
    // 639XXXXXXXXX (12 digits) -> +639XXXXXXXXX
    if (preg_match('/^639\d{9}$/', $clean)) {
        return '+' . $clean;
    }
    
    // Already has +639XXXXXXXXX
    if (preg_match('/^\+639\d{9}$/', $mobile)) {
        return $mobile;
    }
    
    // If not matching standard PH format, just return the numbers digits prepended with '+' if long enough
    if (strlen($clean) >= 10) {
        return '+' . $clean;
    }
    
    return $mobile;
}

/**
 * Send SMS using UniSMS REST API and record it to sms_log
 * 
 * @param string $mobile Recipient mobile number
 * @param string $message Message content
 * @param int|null $userId User ID associated with the recipient (optional)
 * @return bool True if successfully sent, false otherwise
 */
function sendUniSMS(string $mobile, string $message, ?int $userId = null): bool {
    global $pdo;
    
    $formattedMobile = formatMobileForUniSMS($mobile);
    $apiKey = UNISMS_API_KEY;
    $endpoint = UNISMS_ENDPOINT;
    $sender = UNISMS_SENDER_ID;
    
    // Check if UniSMS API key is set/configured
    if (empty($apiKey) || $apiKey === 'your_actual_unisms_api_key_here') {
        // Fallback simulation: log in the database as 'failed' (due to missing API key)
        // This prevents the application from breaking while indicating a configuration is missing.
        $stmt = $pdo->prepare("INSERT INTO sms_log (user_id, mobile, message, status) VALUES (?, ?, ?, 'failed')");
        $stmt->execute([$userId, $mobile, $message]);
        return false;
    }
    
    // Prepare API request payload
    $payload = [
        'recipient' => $formattedMobile,
        'content'   => $message
    ];
    
    if (!empty($sender)) {
        $payload['sender'] = $sender;
    }
    
    // Initialize cURL request
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":"); // Basic Authentication (API Key as Username, empty password)
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $success = false;
    
    if (empty($curlErr) && $httpCode >= 200 && $httpCode < 300) {
        $resObj = json_decode($response, true);
        // UniSMS API typically returns success status. 
        // We will consider a successful HTTP response code (2xx) as successfully accepted.
        $success = true;
    }
    
    $status = $success ? 'sent' : 'failed';
    
    // Log the transaction in the database
    $stmt = $pdo->prepare("INSERT INTO sms_log (user_id, mobile, message, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $mobile, $message, $status]);
    
    return $success;
}

/**
 * Trigger SMS broadcast to all active residents (all users with role = 'resident')
 * Used for newly posted events.
 * 
 * @param string $message Message to send
 * @return int Number of sent SMS
 */
function broadcastSMSToAllResidents(string $message): int {
    global $pdo;
    
    $stmt = $pdo->query("SELECT id, fullname, mobile FROM users WHERE role = 'resident' AND mobile IS NOT NULL AND mobile != ''");
    $residents = $stmt->fetchAll();
    
    $sentCount = 0;
    foreach ($residents as $resident) {
        if (sendUniSMS($resident['mobile'], $message, $resident['id'])) {
            $sentCount++;
        }
    }
    return $sentCount;
}

/**
 * Trigger SMS broadcast to all users registered for a specific event
 * Used for event updates and reminders.
 * 
 * @param int $eventId Event ID
 * @param string $message Message to send
 * @return int Number of sent SMS
 */
function broadcastSMSToEventParticipants(int $eventId, string $message): int {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.fullname, u.mobile 
        FROM event_registrations er 
        JOIN users u ON er.user_id = u.id 
        WHERE er.event_id = ? AND u.mobile IS NOT NULL AND u.mobile != ''
    ");
    $stmt->execute([$eventId]);
    $participants = $stmt->fetchAll();
    
    $sentCount = 0;
    foreach ($participants as $participant) {
        if (sendUniSMS($participant['mobile'], $message, $participant['id'])) {
            $sentCount++;
        }
    }
    return $sentCount;
}
?>
