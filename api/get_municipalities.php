<?php
/**
 * API: Get Municipalities
 */
require_once '../config.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Get all municipalities with voting places count
    $stmt = $db->query("
        SELECT 
            m.*,
            COUNT(vp.id) as places_count,
            SUM(CASE WHEN vp.active = 1 THEN 1 ELSE 0 END) as active_count
        FROM municipalities m
        LEFT JOIN voting_places vp ON m.id = vp.municipality_id
        GROUP BY m.id
        ORDER BY m.name
    ");
    
    $municipalities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $municipalities
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
