<?php
// =============================================
// API: Events CRUD
// SourcePoint - CamNorte Event Aggregator
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/sms_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Determine the effective request method (support _method override for POST)
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST' && isset($_POST['_method'])) {
        $method = strtoupper($_POST['_method']);
    }

    switch ($method) {
        // =============================================
        // GET: Fetch Events
        // =============================================
        case 'GET':
            $category = $_GET['category'] ?? 'all';
            $org = $_GET['org'] ?? '';
            $limit = intval($_GET['limit'] ?? 50);
            $status = $_GET['status'] ?? 'upcoming';

            $sql = "SELECT DISTINCT e.*, c.name as category_name 
                    FROM events e 
                    LEFT JOIN categories c ON e.category_id = c.id 
                    WHERE 1=1";
            $params = [];

            if ($category !== 'all') {
                $sql .= " AND c.name = ?";
                $params[] = $category;
            }

            if (!empty($org)) {
                $sql .= " AND (e.organizer LIKE ? OR e.partner_organizations LIKE ?)";
                $orgParam = '%' . $org . '%';
                $params[] = $orgParam;
                $params[] = $orgParam;
            }

            if ($status !== 'all') {
                $sql .= " AND e.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY e.event_date ASC LIMIT ?";
            $params[] = $limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $events = $stmt->fetchAll();

            // Get total counts for stats
            $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status = 'upcoming'");
            $totalEvents = $totalStmt->fetch()['total'];

            $orgStmt = $pdo->query("SELECT COUNT(DISTINCT organizer) as total FROM events");
            $totalOrgs = $orgStmt->fetch()['total'];

            $userStmt = $pdo->query("SELECT COUNT(*) as total FROM users");
            $totalUsers = $userStmt->fetch()['total'];

            jsonResponse([
                'success' => true,
                'events' => $events,
                'stats' => [
                    'total_events' => $totalEvents,
                    'total_organizations' => $totalOrgs,
                    'total_users' => $totalUsers
                ]
            ]);
            break;

        // =============================================
        // POST: Create Event (Admin only)
        // =============================================
        case 'POST':
            requireAdminLogin();

            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category_id = intval($_POST['category_id'] ?? 0);
            $location = trim($_POST['location'] ?? '');
            $event_date = $_POST['event_date'] ?? '';
            $organizer = trim($_POST['organizer'] ?? '');
            $partner_organizations = trim($_POST['partner_organizations'] ?? '');

            if (empty($title) || empty($description) || empty($location) || empty($event_date) || empty($organizer)) {
                jsonResponse(['success' => false, 'message' => 'Title, description, location, date, and organizer are required'], 400);
            }

            // Handle image upload
            $cover_image = null;

            // Some browsers/servers may report multiple files or an empty upload;
            // normalize to accept a single file from FormData.
            $coverFile = $_FILES['cover_image'] ?? null;
            if ($coverFile) {
                // If multiple is used, take the first element.
                if (is_array($coverFile['name'])) {
                    $coverFile = [
                        'name' => $coverFile['name'][0] ?? '',
                        'type' => $coverFile['type'][0] ?? '',
                        'tmp_name' => $coverFile['tmp_name'][0] ?? '',
                        'error' => $coverFile['error'][0] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $coverFile['size'][0] ?? 0,
                    ];
                }

                if (isset($coverFile['error']) && $coverFile['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../uploads/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileExt = pathinfo($coverFile['name'], PATHINFO_EXTENSION);
                    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

                    if (!in_array(strtolower($fileExt), $allowedExts)) {
                        jsonResponse(['success' => false, 'message' => 'Only JPG, PNG, and WEBP files are allowed'], 400);
                    }

                    $filename = 'event_' . time() . '_' . uniqid() . '.' . $fileExt;
                    $filepath = $uploadDir . $filename;

                    if (move_uploaded_file($coverFile['tmp_name'], $filepath)) {
                        $cover_image = 'uploads/' . $filename;
                    } else {
                        jsonResponse([
                            'success' => false,
                            'message' => 'Failed to upload image. Check directory permissions and PHP upload_max_filesize/post_max_size.'
                        ], 500);
                    }
                }
            }

            $stmt = $pdo->prepare("INSERT INTO events (title, description, category_id, location, event_date, organizer, partner_organizations, cover_image, created_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $category_id, $location, $event_date, $organizer, $partner_organizations, $cover_image, $_SESSION['admin_id']]);

            $eventId = $pdo->lastInsertId();
            $sendSMS = ($_POST['send_sms'] ?? 'false') === 'true';
            $smsSentCount = 0;
            if ($sendSMS) {
                $formattedDate = date('M d, Y', strtotime($event_date));
                $smsMessage = "Hi! A new event \"{$title}\" has been scheduled on {$formattedDate} at {$location}. Please check out the SourcePoint app for details.";
                $smsSentCount = broadcastSMSToAllResidents($smsMessage);
            }

            jsonResponse([
                'success' => true,
                'message' => 'Event created successfully!',
                'event_id' => $eventId,
                'sms_sent_count' => $smsSentCount
            ], 201);
            break;

        // =============================================
        // PUT: Update Event (Admin only)
        // Uses POST with _method=PUT to support FormData file uploads
        // =============================================
        case 'PUT':
            requireAdminLogin();

            // Get data from $_POST (for method override via POST) or parse JSON body
            $eventId = intval($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category_id = intval($_POST['category_id'] ?? 0);
            $location = trim($_POST['location'] ?? '');
            $event_date = $_POST['event_date'] ?? '';
            $organizer = trim($_POST['organizer'] ?? '');
            $partner_organizations = trim($_POST['partner_organizations'] ?? '');
            $status = trim($_POST['status'] ?? '');

            // Fallback: try JSON input if $_POST is empty (direct PUT calls)
            if ($eventId <= 0) {
                $input = json_decode(file_get_contents('php://input'), true);
                if ($input) {
                    $eventId = intval($input['id'] ?? 0);
                    $title = trim($input['title'] ?? '');
                    $description = trim($input['description'] ?? '');
                    $category_id = intval($input['category_id'] ?? 0);
                    $location = trim($input['location'] ?? '');
                    $event_date = $input['event_date'] ?? '';
                    $organizer = trim($input['organizer'] ?? '');
                    $partner_organizations = trim($input['partner_organizations'] ?? '');
                    $status = trim($input['status'] ?? '');
                }
            }

            if ($eventId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Event ID is required'], 400);
            }

            // Check if event exists
            $stmt = $pdo->prepare("SELECT id, cover_image FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
            $existingEvent = $stmt->fetch();
            if (!$existingEvent) {
                jsonResponse(['success' => false, 'message' => 'Event not found'], 404);
            }

            // Build update query dynamically
            $fields = [];
            $params = [];

            if (!empty($title)) { $fields[] = "title = ?"; $params[] = $title; }
            if (!empty($description)) { $fields[] = "description = ?"; $params[] = $description; }
            if ($category_id > 0) { $fields[] = "category_id = ?"; $params[] = $category_id; }
            if (!empty($location)) { $fields[] = "location = ?"; $params[] = $location; }
            if (!empty($event_date)) { $fields[] = "event_date = ?"; $params[] = $event_date; }
            if (!empty($organizer)) { $fields[] = "organizer = ?"; $params[] = $organizer; }
            $fields[] = "partner_organizations = ?"; $params[] = $partner_organizations;
            if (!empty($status)) { $fields[] = "status = ?"; $params[] = $status; }

            // Handle file upload
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileExt = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array(strtolower($fileExt), $allowedExts)) {
                    jsonResponse(['success' => false, 'message' => 'Only JPG, PNG, and WEBP files are allowed'], 400);
                }

                // Delete old cover image if exists
                if ($existingEvent['cover_image'] && file_exists('../' . $existingEvent['cover_image'])) {
                    unlink('../' . $existingEvent['cover_image']);
                }

                $filename = 'event_' . time() . '_' . uniqid() . '.' . $fileExt;
                $filepath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $filepath)) {
                    $fields[] = "cover_image = ?";
                    $params[] = 'uploads/' . $filename;
                } else {
                    jsonResponse(['success' => false, 'message' => 'Failed to upload image. Check directory permissions.'], 500);
                }
            }

            if (empty($fields)) {
                jsonResponse(['success' => false, 'message' => 'No fields to update'], 400);
            }

            $params[] = $eventId;
            $sql = "UPDATE events SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Fetch update toggle
            $sendSMS = false;
            if (isset($_POST['send_sms'])) {
                $sendSMS = $_POST['send_sms'] === 'true';
            } elseif (isset($input['send_sms'])) {
                $sendSMS = $input['send_sms'] === true || $input['send_sms'] === 'true';
            }

            $smsSentCount = 0;
            if ($sendSMS) {
                // Fetch the current updated details
                $infoStmt = $pdo->prepare("SELECT title, event_date, location FROM events WHERE id = ?");
                $infoStmt->execute([$eventId]);
                $eventInfo = $infoStmt->fetch();
                if ($eventInfo) {
                    $formattedDate = date('M d, Y', strtotime($eventInfo['event_date']));
                    $smsMessage = "Hi! There is an update for \"{$eventInfo['title']}\". It will be held on {$formattedDate} at {$eventInfo['location']}. See you there!";
                    $smsSentCount = broadcastSMSToEventParticipants($eventId, $smsMessage);
                }
            }

            jsonResponse([
                'success' => true,
                'message' => 'Event updated successfully!',
                'sms_sent_count' => $smsSentCount
            ]);
            break;

        // =============================================
        // DELETE: Delete Event (Admin only)
        // =============================================
        case 'DELETE':
            requireAdminLogin();

            $input = json_decode(file_get_contents('php://input'), true);
            $eventId = intval($input['id'] ?? $_GET['id'] ?? 0);

            if ($eventId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Event ID is required'], 400);
            }

            // Check if event exists
            $stmt = $pdo->prepare("SELECT id, cover_image FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch();

            if (!$event) {
                jsonResponse(['success' => false, 'message' => 'Event not found'], 404);
            }

            // Delete cover image if exists
            if ($event['cover_image'] && file_exists('../' . $event['cover_image'])) {
                unlink('../' . $event['cover_image']);
            }

            // Delete event
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$eventId]);

            jsonResponse([
                'success' => true,
                'message' => 'Event deleted successfully'
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