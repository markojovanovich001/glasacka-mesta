<?php
/**
 * API: Bulk Verify
 */
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();
    
    // Mark all active places as verified
    $stmt = $db->prepare("UPDATE voting_places SET verified = 1, updated_at = CURRENT_TIMESTAMP WHERE active = 1");
    $stmt->execute();
    
    $count = $stmt->rowCount();
    
    logActivity('bulk_verify', 'voting_place', null, "Verified $count places");
    
    echo json_encode([
        'success' => true,
        'count' => $count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
