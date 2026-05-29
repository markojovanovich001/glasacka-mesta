<?php
/**
 * API: Update Voting Place
 */
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        throw new Exception('Missing place ID');
    }
    
    $db = getDB();
    
    // Build update query
    $fields = [];
    $params = [];
    
    $allowed_fields = ['place_number', 'place_name', 'address', 'area', 'area_addresses', 'active', 'verified', 'notes'];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($fields)) {
        throw new Exception('No fields to update');
    }
    
    $fields[] = "updated_at = CURRENT_TIMESTAMP";
    $params[] = $data['id'];
    
    $sql = "UPDATE voting_places SET " . implode(', ', $fields) . " WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    logActivity('update_voting_place', 'voting_place', $data['id'], json_encode($data));
    
    echo json_encode([
        'success' => true,
        'message' => 'Voting place updated successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
