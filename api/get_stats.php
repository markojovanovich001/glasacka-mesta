<?php
/**
 * API: Get Statistics
 */
require_once '../config.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Total municipalities
    $stmt = $db->query("SELECT COUNT(*) as count FROM municipalities");
    $municipalities = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total voting places
    $stmt = $db->query("SELECT COUNT(*) as count FROM voting_places");
    $voting_places = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active voting places
    $stmt = $db->query("SELECT COUNT(*) as count FROM voting_places WHERE active = 1");
    $active_places = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Verified voting places
    $stmt = $db->query("SELECT COUNT(*) as count FROM voting_places WHERE verified = 1");
    $verified_places = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Downloaded municipalities
    $stmt = $db->query("SELECT COUNT(*) as count FROM municipalities WHERE doc_file IS NOT NULL");
    $downloaded = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Parsed municipalities
    $stmt = $db->query("SELECT COUNT(*) as count FROM municipalities WHERE parsed_at IS NOT NULL");
    $parsed = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'municipalities' => $municipalities,
        'voting_places' => $voting_places,
        'active_places' => $active_places,
        'verified_places' => $verified_places,
        'downloaded' => $downloaded,
        'parsed' => $parsed
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
