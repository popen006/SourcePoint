<?php
// =============================================
// API: Event Registrations (RSVP)
// SourcePoint - CamNorte Event Aggregator
// POST   = Register/Join an event
// GET    = List registrations (user's or event's)
// DELETE = Cancel registration
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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

        // =============================================
        // POST: Register for an event
        // =============================================
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $eventId = intval($input['event_id'] ?? 0);

            if ($eventId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Event ID is required'], 400);
            }

            // Check if event exists and is joinable
            $stmt = $pdo->prepare("SELECT id, status FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch();

            if (!$event) {
                jsonResponse(['success' => false, 'message' => 'Event not found'], 404);
            }

            if ($event['status'] === 'cancelled') {
                jsonResponse(['success' => false, 'message' => 'This event has been cancelled'], 400);
            }

            // Check if event date is in the past
            $stmt = $pdo->prepare("SELECT event_date FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
            $eventInfo = $stmt->fetch();
            if ($eventInfo) {
                $eventDate = strtotime($eventInfo['event_date']);
                $now = time();
                // Compare dates at day level (ignore time) - if event date is before today, reject
                $eventDayStart = strtotime(date('Y-m-d', $eventDate));
                $todayStart = strtotime(date('Y-m-d', $now));
                if ($eventDayStart < $todayStart) {
                    jsonResponse(['success' => false, 'message' => 'Cannot register for a past event'], 400);
                }
            }

            // Check if already registered
            $stmt = $pdo->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$eventId, $userId]);
            if ($stmt->fetch()) {
                jsonResponse(['success' => false, 'message' => 'You are already registered for this event'], 409);
            }

            // Register
            $stmt = $pdo->prepare("INSERT INTO event_registrations (event_id, user_id) VALUES (?, ?)");
            $stmt->execute([$eventId, $userId]);

            jsonResponse([
                'success' => true,
                'message' => 'Successfully registered for the event!',
                'registration_id' => $pdo->lastInsertId()
            ], 201);
            break;

        // =============================================
        // GET: List registrations
        // =============================================
        case 'GET':
            $eventId = intval($_GET['event_id'] ?? 0);
            $type = $_GET['type'] ?? 'my'; // 'my' or 'event'

            if ($type === 'event') {
                // Admin or event owner: get all registrations for a specific event
                if ($eventId <= 0) {
                    jsonResponse(['success' => false, 'message' => 'Event ID is required'], 400);
                }

                $stmt = $pdo->prepare("
                    SELECT er.id, er.registered_at, u.id as user_id, u.fullname, u.email, u.mobile 
                    FROM event_registrations er 
                    JOIN users u ON er.user_id = u.id 
                    WHERE er.event_id = ? 
                    ORDER BY er.registered_at DESC
                ");
                $stmt->execute([$eventId]);
                $registrations = $stmt->fetchAll();

                jsonResponse([
                    'success' => true,
                    'registrations' => $registrations,
                    'total' => count($registrations)
                ]);

            } else {
                // Get current user's registered events
                $stmt = $pdo->prepare("
                    SELECT er.id as registration_id, er.registered_at, 
                           e.id as event_id, e.title, e.description, e.location, e.event_date, e.organizer, e.status,
                           c.name as category_name
                    FROM event_registrations er 
                    JOIN events e ON er.event_id = e.id 
                    LEFT JOIN categories c ON e.category_id = c.id 
                    WHERE er.user_id = ? 
                    ORDER BY e.event_date ASC
                ");
                $stmt->execute([$userId]);
                $registrations = $stmt->fetchAll();

                jsonResponse([
                    'success' => true,
                    'registrations' => $registrations,
                    'total' => count($registrations)
                ]);
            }
            break;

        // =============================================
        // DELETE: Cancel registration
        // =============================================
        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $eventId = intval($input['event_id'] ?? 0);

            if ($eventId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Event ID is required'], 400);
            }

            $stmt = $pdo->prepare("DELETE FROM event_registrations WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$eventId, $userId]);

            if ($stmt->rowCount() > 0) {
                jsonResponse([
                    'success' => true,
                    'message' => 'Registration cancelled successfully'
                ]);
            } else {
                jsonResponse(['success' => false, 'message' => 'Registration not found'], 404);
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