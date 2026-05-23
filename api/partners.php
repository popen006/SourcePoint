<?php
// =============================================
// API: Partner Organizations List
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

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    // Get unique organizers and partner organizations from events
    $stmt = $pdo->query("
        SELECT DISTINCT organizer as name FROM events 
        WHERE organizer IS NOT NULL AND organizer != ''
        UNION
        SELECT DISTINCT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(partner_organizations, ',', n.n), ',', -1)) as name
        FROM events 
        CROSS JOIN (
            SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8
        ) n
        WHERE partner_organizations IS NOT NULL AND partner_organizations != ''
        ORDER BY name
    ");
    
    $dbPartners = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Clean up partner names (trim whitespace)
    $dbPartners = array_map('trim', $dbPartners);
    $dbPartners = array_filter($dbPartners, function($name) {
        return !empty($name);
    });
    $dbPartners = array_unique($dbPartners);
    sort($dbPartners);

    // Define known Camarines Norte organizations with icons
    // These supplement what's already in the database
    $knownOrganizations = [
        [
            'name' => 'Provincial Government of CamNorte',
            'short_name' => 'Province of\nCamNorte',
            'icon' => '🏛️',
            'category' => 'Government'
        ],
        [
            'name' => 'LGU Daet',
            'short_name' => 'LGU\nDaet',
            'icon' => '🏛️',
            'category' => 'Government'
        ],
        [
            'name' => 'DENR CamNorte',
            'short_name' => 'DENR\nCamNorte',
            'icon' => '🌿',
            'category' => 'Environment'
        ],
        [
            'name' => 'DTI CamNorte',
            'short_name' => 'DTI\nCamNorte',
            'icon' => '📊',
            'category' => 'Government'
        ],
        [
            'name' => 'DOST CamNorte',
            'short_name' => 'DOST\nCamNorte',
            'icon' => '🔬',
            'category' => 'Government'
        ],
        [
            'name' => 'DepEd CamNorte',
            'short_name' => 'DepEd\nCamNorte',
            'icon' => '📚',
            'category' => 'Education'
        ],
        [
            'name' => 'PNP CamNorte',
            'short_name' => 'PNP\nCamNorte',
            'icon' => '👮',
            'category' => 'Government'
        ],
        [
            'name' => 'Provincial Health Office',
            'short_name' => 'Provincial\nHealth Office',
            'icon' => '🏥',
            'category' => 'Health'
        ],
        [
            'name' => 'CamNorte State College',
            'short_name' => 'CNSC',
            'icon' => '🎓',
            'category' => 'Education'
        ],
        [
            'name' => 'Red Cross CamNorte',
            'short_name' => 'Red Cross\nCamNorte',
            'icon' => '⛑️',
            'category' => 'Health'
        ],
        [
            'name' => 'Philippine Coast Guard',
            'short_name' => 'Coast\nGuard',
            'icon' => '🚢',
            'category' => 'Government'
        ],
        [
            'name' => 'Boy Scouts of the Philippines',
            'short_name' => 'BSP\nCamNorte',
            'icon' => '⚜️',
            'category' => 'Youth'
        ],
        [
            'name' => 'LGU Daet Environment Office',
            'short_name' => 'LGU Enviro\nOffice',
            'icon' => '🌿',
            'category' => 'Environment'
        ],
        [
            'name' => 'CSC CamNorte',
            'short_name' => 'CSC\nCamNorte',
            'icon' => '⚖️',
            'category' => 'Government'
        ],
        [
            'name' => 'Local Hospitals',
            'short_name' => 'Hospitals\nCamNorte',
            'icon' => '🏥',
            'category' => 'Health'
        ],
        [
            'name' => 'Local Schools',
            'short_name' => 'Schools\nCamNorte',
            'icon' => '🏫',
            'category' => 'Education'
        ]
    ];
    
    // For each organization in known, check if it exists in dbPartners
    // If not, still include it (for aggregator comprehensiveness)
    // But also add any new ones from DB not in known list
    $existingNames = array_map('strtolower', $dbPartners);
    $existingNames = array_map(function($n) { return preg_replace('/\s+/', '', $n); }, $existingNames);
    
    $finalPartners = [];
    $addedNames = [];
    
    // First add known orgs that exist in DB
    foreach ($knownOrganizations as $org) {
        $searchKey = preg_replace('/\s+/', '', strtolower($org['name']));
        $matchFound = false;
        
        foreach ($dbPartners as $dbName) {
            $dbKey = preg_replace('/\s+/', '', strtolower($dbName));
            if (stripos($dbKey, $searchKey) !== false || stripos($searchKey, $dbKey) !== false) {
                $matchFound = true;
                break;
            }
        }
        
        // Add the org
        $finalPartners[] = $org;
        $addedNames[] = $searchKey;
    }
    
    // Add any DB orgs not in known list
    foreach ($dbPartners as $dbName) {
        $dbKey = preg_replace('/\s+/', '', strtolower($dbName));
        $alreadyAdded = false;
        
        foreach ($addedNames as $added) {
            if (stripos($dbKey, $added) !== false || stripos($added, $dbKey) !== false) {
                $alreadyAdded = true;
                break;
            }
        }
        
        if (!$alreadyAdded) {
            $finalPartners[] = [
                'name' => $dbName,
                'short_name' => strlen($dbName) > 15 ? substr($dbName, 0, 13) . '...' : $dbName,
                'icon' => '🤝',
                'category' => 'Partner'
            ];
            $addedNames[] = $dbKey;
        }
    }

    jsonResponse([
        'success' => true,
        'partners' => $finalPartners,
        'total' => count($finalPartners)
    ]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
?>