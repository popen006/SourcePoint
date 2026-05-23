<?php
// =============================================
// API: Event Categories with Event Counts
// SourcePoint - CamNorte Event Aggregator
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT 
            c.id, 
            c.name, 
            c.description,
            COUNT(e.id) as event_count
        FROM categories c
        LEFT JOIN events e ON e.category_id = c.id AND e.status IN ('upcoming', 'ongoing')
        GROUP BY c.id, c.name, c.description
        ORDER BY c.name ASC
    ");
    $categories = $stmt->fetchAll();

    // Get total upcoming events count for "All" option
    $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status IN ('upcoming', 'ongoing')");
    $totalEvents = $totalStmt->fetch()['total'];

    echo json_encode([
        'success' => true, 
        'categories' => $categories,
        'total_events' => (int)$totalEvents
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>