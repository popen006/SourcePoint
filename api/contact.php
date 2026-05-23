<?php
// =============================================
// API: Contact Form Messages
// SourcePoint - CamNorte Event Aggregator
// POST   = Submit contact message (Public)
// GET    = Fetch messages (Admin)
// PUT    = Mark message as read (Admin)
// DELETE = Delete message (Admin)
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        // =============================================
        // GET: Fetch messages (Admin only)
        // =============================================
        case 'GET':
            requireAdminLogin();

            $filter = $_GET['filter'] ?? 'all'; // all, unread, read
            $sql = "SELECT * FROM contact_messages";
            if ($filter === 'unread') {
                $sql .= " WHERE is_read = 0";
            } elseif ($filter === 'read') {
                $sql .= " WHERE is_read = 1";
            }
            $sql .= " ORDER BY created_at DESC";

            $stmt = $pdo->query($sql);
            $messages = $stmt->fetchAll();

            // Get unread count
            $unreadStmt = $pdo->query("SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0");
            $unreadCount = $unreadStmt->fetch()['count'];

            jsonResponse([
                'success' => true,
                'messages' => $messages,
                'unread_count' => (int)$unreadCount
            ]);
            break;

        // =============================================
        // POST: Submit contact message (Public)
        // =============================================
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);

            $name = trim($input['name'] ?? '');
            $email = trim($input['email'] ?? '');
            $subject = trim($input['subject'] ?? '');
            $message = trim($input['message'] ?? '');

            if (empty($name) || empty($email) || empty($message)) {
                jsonResponse(['success' => false, 'message' => 'Name, email, and message are required'], 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
            }

            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);

            jsonResponse([
                'success' => true,
                'message' => 'Your message has been sent! We will get back to you soon.'
            ], 201);
            break;

        // =============================================
        // PUT: Mark message as read (Admin only)
        // =============================================
        case 'PUT':
            requireAdminLogin();

            $input = json_decode(file_get_contents('php://input'), true);
            $messageId = intval($input['id'] ?? 0);
            $isRead = isset($input['is_read']) ? intval($input['is_read']) : 1;

            if ($messageId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Message ID is required'], 400);
            }

            $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = ? WHERE id = ?");
            $stmt->execute([$isRead, $messageId]);

            if ($stmt->rowCount() > 0) {
                jsonResponse([
                    'success' => true,
                    'message' => $isRead ? 'Message marked as read' : 'Message marked as unread'
                ]);
            } else {
                jsonResponse(['success' => false, 'message' => 'Message not found'], 404);
            }
            break;

        // =============================================
        // DELETE: Delete message (Admin only)
        // =============================================
        case 'DELETE':
            requireAdminLogin();

            $input = json_decode(file_get_contents('php://input'), true);
            $messageId = intval($input['id'] ?? $_GET['id'] ?? 0);

            if ($messageId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Message ID is required'], 400);
            }

            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->execute([$messageId]);

            if ($stmt->rowCount() > 0) {
                jsonResponse([
                    'success' => true,
                    'message' => 'Message deleted successfully'
                ]);
            } else {
                jsonResponse(['success' => false, 'message' => 'Message not found'], 404);
            }
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