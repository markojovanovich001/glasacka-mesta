<?php
/**
 * API: Health Check
 */
require_once '../config.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Check if tables exist
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('municipalities', 'voting_places', 'activity_log')");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $checks = [
        'database' => count($tables) === 3,
        'data_dir' => is_dir(DATA_DIR) && is_writable(DATA_DIR),
        'downloads_dir' => is_dir(DOWNLOADS_DIR) && is_writable(DOWNLOADS_DIR),
        'parsed_dir' => is_dir(PARSED_DIR) && is_writable(PARSED_DIR),
        'logs_dir' => is_dir(LOGS_DIR) && is_writable(LOGS_DIR)
    ];
    
    $all_ok = !in_array(false, $checks, true);
    
    echo json_encode([
        'success' => true,
        'healthy' => $all_ok,
        'checks' => $checks
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'healthy' => false,
        'error' => $e->getMessage()
    ]);
}
