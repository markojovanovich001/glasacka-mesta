<?php
/**
 * API: Verify Data
 */
require_once '../config.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Total municipalities
    $stmt = $db->query("SELECT COUNT(*) as count FROM municipalities");
    $municipalities_total = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total voting places
    $stmt = $db->query("SELECT COUNT(*) as count FROM voting_places");
    $places_total = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Places with missing data
    $stmt = $db->query("SELECT COUNT(*) as count FROM voting_places WHERE place_name IS NULL OR place_name = '' OR address IS NULL OR address = ''");
    $missing_data = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Municipalities without places
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM municipalities m 
        LEFT JOIN voting_places vp ON m.id = vp.municipality_id 
        WHERE vp.id IS NULL AND m.parsed_at IS NOT NULL
    ");
    $municipalities_no_places = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    logActivity('verify_data', null, null, "Total: $places_total, Missing: $missing_data");
    
    echo json_encode([
        'success' => true,
        'municipalities_total' => $municipalities_total,
        'places_total' => $places_total,
        'missing_data' => $missing_data,
        'municipalities_no_places' => $municipalities_no_places
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
