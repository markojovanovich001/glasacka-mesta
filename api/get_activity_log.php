<?php
/**
 * API: Get Activity Log
 */
require_once '../config.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    $stmt = $db->prepare("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $logs,
        'count' => count($logs)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
