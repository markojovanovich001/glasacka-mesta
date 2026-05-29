<?php
/**
 * Save Progress API
 * Handles saving progress to JSON file
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $progress = [
        'current' => intval($_POST['current'] ?? 0),
        'total' => intval($_POST['total'] ?? 0),
        'percentage' => floatval($_POST['percentage'] ?? 0),
        'message' => $_POST['message'] ?? '',
        'type' => $_POST['type'] ?? 'info',
        'paused' => isset($_POST['paused']) && $_POST['paused'] === 'true',
        'timestamp' => time(),
        'datetime' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents(PROGRESS_JSON, json_encode($progress, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
