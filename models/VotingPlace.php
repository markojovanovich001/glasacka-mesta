<?php
/**
 * VotingPlace Model
 * Handles all database operations for voting places
 */

class VotingPlace {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get all voting places
     */
    public function getAll($municipalityId = null, $activeOnly = false) {
        $sql = "SELECT vp.*, m.name as municipality_name FROM voting_places vp 
                JOIN municipalities m ON vp.municipality_id = m.id";
        
        $where = [];
        if ($municipalityId) {
            $where[] = "vp.municipality_id = " . intval($municipalityId);
        }
        if ($activeOnly) {
            $where[] = "vp.active = 1";
        }
        
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY m.name, vp.place_number";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get voting place by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM voting_places WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Save voting place
     */
    public function save($data) {
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO voting_places 
            (municipality_id, place_number, place_name, address, area, area_addresses, source_file, active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        
        return $stmt->execute([
            $data['municipality_id'],
            $data['place_number'],
            $data['place_name'],
            $data['address'],
            $data['area'],
            $data['area_addresses'],
            $data['source_file']
        ]);
    }
    
    /**
     * Update voting place
     */
    public function update($id, $data) {
        $stmt = $this->db->prepare("UPDATE voting_places SET 
            place_name = ?, address = ?, area = ?, area_addresses = ?, 
            verified = ?, notes = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?");
        
        return $stmt->execute([
            $data['place_name'],
            $data['address'],
            $data['area'],
            $data['area_addresses'],
            $data['verified'] ?? 0,
            $data['notes'] ?? '',
            $id
        ]);
    }
    
    /**
     * Delete all voting places
     */
    public function deleteAll() {
        return $this->db->exec("DELETE FROM voting_places");
    }
    
    /**
     * Delete by municipality
     */
    public function deleteByMunicipality($municipalityId) {
        $stmt = $this->db->prepare("DELETE FROM voting_places WHERE municipality_id = ?");
        return $stmt->execute([$municipalityId]);
    }
}
