<?php
// =============================================
// API: SMS Notifications
// SourcePoint - CamNorte Event Aggregator
// GET    = Fetch SMS logs (Admin)
// POST   = Send SMS to users (Admin)
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/sms_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        // =============================================
        // GET: Fetch SMS logs (Admin only)
        // =============================================
        case 'GET':
            requireAdminLogin();

            $limit = intval($_GET['limit'] ?? 50);
            $status = $_GET['status'] ?? 'all';

            $sql = "SELECT s.*, u.fullname as user_name, u.mobile as user_mobile 
                    FROM sms_log s 
                    LEFT JOIN users u ON s.user_id = u.id 
                    WHERE 1=1";
            $params = [];

            if ($status !== 'all') {
                $sql .= " AND s.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY s.sent_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll();

            // Get counts
            $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM sms_log");
            $sentStmt = $pdo->query("SELECT COUNT(*) as total FROM sms_log WHERE status = 'sent'");
            $failedStmt = $pdo->query("SELECT COUNT(*) as total FROM sms_log WHERE status = 'failed'");

            jsonResponse([
                'success' => true,
                'logs' => $logs,
                'stats' => [
                    'total' => (int)$totalStmt->fetch()['total'],
                    'sent' => (int)$sentStmt->fetch()['total'],
                    'failed' => (int)$failedStmt->fetch()['total']
                ]
            ]);
            break;

        // =============================================
        // POST: Send SMS notification (Admin only)
        // =============================================
        case 'POST':
            requireAdminLogin();

            $input = json_decode(file_get_contents('php://input'), true);
            $message = trim($input['message'] ?? '');
            $recipientType = $input['recipient_type'] ?? 'all'; // 'all', 'event', 'user'
            $eventId = intval($input['event_id'] ?? 0);
            $userId = intval($input['user_id'] ?? 0);

            if (empty($message)) {
                jsonResponse(['success' => false, 'message' => 'Message is required'], 400);
            }

            // Get recipients
            $recipients = [];

            if ($recipientType === 'all') {
                $stmt = $pdo->query("SELECT id, fullname, mobile FROM users WHERE mobile IS NOT NULL AND mobile != '' AND role = 'resident'");
                $recipients = $stmt->fetchAll();
            } elseif ($recipientType === 'event' && $eventId > 0) {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.fullname, u.mobile 
                    FROM event_registrations er 
                    JOIN users u ON er.user_id = u.id 
                    WHERE er.event_id = ? AND u.mobile IS NOT NULL AND u.mobile != ''
                ");
                $stmt->execute([$eventId]);
                $recipients = $stmt->fetchAll();
            } elseif ($recipientType === 'user' && $userId > 0) {
                $stmt = $pdo->prepare("SELECT id, fullname, mobile FROM users WHERE id = ? AND mobile IS NOT NULL AND mobile != ''");
                $stmt->execute([$userId]);
                $recipients = $stmt->fetchAll();
            } elseif ($recipientType === 'custom') {
                $customMobile = trim($input['mobile'] ?? '');
                $customName = trim($input['custom_name'] ?? 'Custom Number');
                if (!empty($customMobile)) {
                    $recipients = [[
                        'id' => null,
                        'fullname' => $customName,
                        'mobile' => $customMobile
                    ]];
                }
            }

            if (empty($recipients)) {
                jsonResponse(['success' => false, 'message' => 'No recipients found with valid mobile numbers'], 400);
            }

            $sentCount = 0;
            foreach ($recipients as $recipient) {
                if (sendUniSMS($recipient['mobile'], $message, $recipient['id'])) {
                    $sentCount++;
                }
            }

            jsonResponse([
                'success' => true,
                'message' => "SMS notification sent to {$sentCount} recipient(s)",
                'recipients_count' => $sentCount
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