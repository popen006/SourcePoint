<?php
// =============================================
// API: Dashboard Statistics
// SourcePoint - CamNorte Event Aggregator
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    // Total events (upcoming)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status = 'upcoming'");
    $totalEvents = $stmt->fetch()['total'];

    // Total registered users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch()['total'];

    // Total organizations (distinct organizers)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT organizer) as total FROM events");
    $totalOrgs = $stmt->fetch()['total'];

    // Total pending submissions
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM submissions WHERE status = 'pending'");
    $totalPendingSubmissions = $stmt->fetch()['total'];

    // Total SMS sent
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sms_log");
    $totalSMS = $stmt->fetch()['total'];

    // Events per category
    $stmt = $pdo->query("SELECT c.name, COUNT(e.id) as count 
                         FROM categories c 
                         LEFT JOIN events e ON c.id = e.category_id 
                         GROUP BY c.id, c.name 
                         ORDER BY count DESC");
    $eventsByCategory = $stmt->fetchAll();

    // Recent events (last 5)
    $stmt = $pdo->query("SELECT e.id, e.title, e.event_date, e.status, c.name as category_name 
                         FROM events e 
                         LEFT JOIN categories c ON e.category_id = c.id 
                         ORDER BY e.created_at DESC 
                         LIMIT 5");
    $recentEvents = $stmt->fetchAll();

    // Event status distribution
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM events GROUP BY status");
    $eventStatus = $stmt->fetchAll();

    // Count completed events
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status = 'completed'");
    $completedEvents = $stmt->fetch()['total'];

    // Next upcoming event
    $stmt = $pdo->query("SELECT title, event_date, location FROM events WHERE status = 'upcoming' ORDER BY event_date ASC LIMIT 1");
    $nextEvent = $stmt->fetch();

    jsonResponse([
        'success' => true,
        'stats' => [
            'total_events' => (int)$totalEvents,
            'total_users' => (int)$totalUsers,
            'total_organizations' => (int)$totalOrgs,
            'pending_submissions' => (int)$totalPendingSubmissions,
            'total_sms' => (int)$totalSMS,
            'completed_events' => (int)$completedEvents,
            'next_event' => $nextEvent ?: null,
            'events_by_category' => $eventsByCategory,
            'recent_events' => $recentEvents,
            'event_status' => $eventStatus
        ]
    ]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error'], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Server error'], 500);
}
?>