<?php
/**
 * API: Get Voting Places
 */
require_once '../config.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    
    $municipality_id = $_GET['municipality_id'] ?? null;
    $active_only = isset($_GET['active_only']) && $_GET['active_only'] === '1';
    
    $sql = "
        SELECT 
            vp.*,
            m.name as municipality_name,
            m.slug as municipality_slug
        FROM voting_places vp
        JOIN municipalities m ON vp.municipality_id = m.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($municipality_id) {
        $sql .= " AND vp.municipality_id = ?";
        $params[] = $municipality_id;
    }
    
    if ($active_only) {
        $sql .= " AND vp.active = 1";
    }
    
    $sql .= " ORDER BY vp.place_number";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $places = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $places,
        'count' => count($places)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
