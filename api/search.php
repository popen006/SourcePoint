<?php
// =============================================
// API: Search Suggestions
// SourcePoint - CamNorte Event Aggregator
// Returns events, categories, organizations matching query
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

try {
    $query = trim($_GET['q'] ?? '');
    $limit = intval($_GET['limit'] ?? 10);

    if (empty($query)) {
        jsonResponse([
            'success' => true,
            'suggestions' => [],
            'message' => 'No query provided'
        ]);
        exit;
    }

    $likeQuery = '%' . $query . '%';
    $suggestions = [];

    // 1. Search events by title (upcoming only)
    $stmt = $pdo->prepare("
        SELECT id, title AS label, 'event' AS type, cover_image AS image, 
               location AS subtitle, event_date AS date_info,
               '' AS extra
        FROM events 
        WHERE title LIKE ? AND status = 'upcoming'
        LIMIT ?
    ");
    $stmt->execute([$likeQuery, $limit]);
    $eventResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($eventResults as $r) {
        $suggestions[] = [
            'label' => $r['label'],
            'type' => 'event',
            'image' => $r['image'] ? '../' . $r['image'] : '',
            'url' => 'event_detail.html?id=' . $r['id'],
            'subtitle' => $r['subtitle'] ?? ''
        ];
    }

    // 2. Search categories (only those with upcoming events)
    $stmt = $pdo->prepare("
        SELECT id, name AS label, 
               (SELECT cover_image FROM events WHERE category_id = c.id AND cover_image IS NOT NULL AND status = 'upcoming' LIMIT 1) AS image
        FROM categories c
        WHERE name LIKE ?
        LIMIT ?
    ");
    $stmt->execute([$likeQuery, $limit]);
    $catResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($catResults as $r) {
        $suggestions[] = [
            'label' => $r['label'],
            'type' => 'category',
            'image' => $r['image'] ? '../' . $r['image'] : '',
            'url' => 'homepage.html?category=' . urlencode($r['label']),
            'subtitle' => 'Category'
        ];
    }

    // 3. Search organizers / organizations (upcoming events only)
    $stmt = $pdo->prepare("
        SELECT DISTINCT organizer AS label,
               (SELECT cover_image FROM events WHERE organizer = e.organizer AND cover_image IS NOT NULL AND status = 'upcoming' LIMIT 1) AS image
        FROM events e
        WHERE organizer LIKE ? AND e.status = 'upcoming'
        LIMIT ?
    ");
    $stmt->execute([$likeQuery, $limit]);
    $orgResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orgResults as $r) {
        $suggestions[] = [
            'label' => $r['label'],
            'type' => 'organization',
            'image' => $r['image'] ? '../' . $r['image'] : '',
            'url' => 'homepage.html?org=' . urlencode($r['label']),
            'subtitle' => 'Organization'
        ];
    }

    // 4. (Removed) Search partner organizations


    // 5. Search static Facebook pages directory (same list as homepage)
    $fbPages = [
        ['name' => 'Camarines Norte News', 'url' => 'https://www.facebook.com/camnortenews', 'imgId' => 'camnortenews'],
        ['name' => 'Community Affairs Office', 'url' => 'https://www.facebook.com/cao.camnorte', 'imgId' => 'cao.camnorte'],
        ['name' => 'CANORECO', 'url' => 'https://www.facebook.com/canorecobalitaofficial', 'imgId' => 'canorecobalitaofficial'],
        ['name' => 'The Future University of Camarines Norte', 'url' => 'https://www.facebook.com/cnscfutureuni', 'imgId' => 'cnscfutureuni'],
        ['name' => 'Dong Padilla', 'url' => 'https://www.facebook.com/dongpadilla001', 'imgId' => 'dongpadilla001'],
        ['name' => 'Camarines Norte Barangay Affairs Office', 'url' => 'https://www.facebook.com/profile.php?id=61570734971805', 'imgId' => '61570734971805'],
        ['name' => 'Joseph Ascutia', 'url' => 'https://www.facebook.com/joseph.ascutia', 'imgId' => '61564716686592'],

        ['name' => 'Provincial Youth Development Office - Camarines Norte', 'url' => 'https://www.facebook.com/pydocamnorte', 'imgId' => 'pydocamnorte'],
        ['name' => 'Camarines Norte Provincial Library', 'url' => 'https://www.facebook.com/camnorteprovlibrary', 'imgId' => 'camnorteprovlibrary'],
        ['name' => 'Camarines Norte Provincial Information Office', 'url' => 'https://www.facebook.com/piocamnorte', 'imgId' => 'piocamnorte'],
        ['name' => 'Camarines Norte Provincial Hospital', 'url' => 'https://www.facebook.com/CNProvincialHospital', 'imgId' => 'CNProvincialHospital'],
        ["name" => "Governor's Office Anti-Corruption Team", 'url' => 'https://www.facebook.com/profile.php?id=61564716686592', 'imgId' => '61564716686592'],
        ["name" => "I Text Mo Si Dong - Camarines Norte Citizen's Complaint Hotline", 'url' => 'https://www.facebook.com/iTextMosiDongOfficial', 'imgId' => 'iTextMosiDongOfficial'],
        ['name' => 'Provincial Agricultural and Fishery Council - CAMARINES NORTE', 'url' => 'https://www.facebook.com/profile.php?id=61572925644090', 'imgId' => '61572925644090'],
        ['name' => 'Provincial Social Welfare and Development Office - Camarines Norte', 'url' => 'https://www.facebook.com/pswdocamnorte', 'imgId' => 'pswdocamnorte'],
        ['name' => 'Provincial Planning and Development Office - Camarines Norte', 'url' => 'https://www.facebook.com/PPDOCamNorte', 'imgId' => 'PPDOCamNorte'],
        ['name' => 'Provincial Disaster Risk Reduction and Management Office - Camarines Norte', 'url' => 'https://www.facebook.com/pdrrmocamnorte', 'imgId' => 'pdrrmocamnorte'],
        ['name' => 'Daet Municipal Tourism Office', 'url' => 'https://www.facebook.com/daettourism2019', 'imgId' => 'daettourism2019'],
        ['name' => 'Camarines Norte PDAO', 'url' => 'https://www.facebook.com/camarinesnorte.pdao', 'imgId' => '61564716686592'],

    ];

    $qLower = mb_strtolower($query);
    foreach ($fbPages as $p) {
        if (mb_stripos(mb_strtolower($p['name']), $qLower) === false) {
            continue;
        }
        $suggestions[] = [
            'label' => $p['name'],
            'type' => 'organization',
            'image' => 'https://graph.facebook.com/' . $p['imgId'] . '/picture?type=large',
            'url' => $p['url'],
            'subtitle' => 'Facebook Page'
        ];
        if (count($suggestions) >= ($limit * 3)) {
            break;
        }
    }

    // Sort: events first, then categories, then organizations
    usort($suggestions, function($a, $b) {
        $order = ['event' => 0, 'category' => 1, 'organization' => 2];
        $aOrder = $order[$a['type']] ?? 3;
        $bOrder = $order[$b['type']] ?? 3;
        if ($aOrder !== $bOrder) return $aOrder - $bOrder;
        return strcmp($a['label'], $b['label']);
    });


    // Limit total results
    $suggestions = array_slice($suggestions, 0, $limit);

    jsonResponse([
        'success' => true,
        'suggestions' => $suggestions,
        'query' => $query
    ]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
?>