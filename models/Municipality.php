<?php
/**
 * Municipality Model
 * Handles all database operations for municipalities
 */

class Municipality {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get all municipalities
     */
    public function getAll($activeOnly = false) {
        $sql = "SELECT * FROM municipalities";
        if ($activeOnly) {
            $sql .= " WHERE active = 1";
        }
        $sql .= " ORDER BY name";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get municipality by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM municipalities WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get municipality by slug
     */
    public function getBySlug($slug) {
        $stmt = $this->db->prepare("SELECT * FROM municipalities WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Save or update municipality
     */
    public function save($data) {
        // Check if exists
        $stmt = $this->db->prepare("SELECT id FROM municipalities WHERE slug = ?");
        $stmt->execute([$data['slug']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update
            $stmt = $this->db->prepare("UPDATE municipalities SET name = ?, doc_url = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$data['name'], $data['url'], $existing['id']]);
            return ['id' => $existing['id'], 'updated' => true];
        } else {
            // Insert
            $stmt = $this->db->prepare("INSERT INTO municipalities (name, slug, doc_url, active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$data['name'], $data['slug'], $data['url']]);
            return ['id' => $this->db->lastInsertId(), 'updated' => false];
        }
    }
    
    /**
     * Update download info
     */
    public function updateDownload($id, $filename) {
        $stmt = $this->db->prepare("UPDATE municipalities SET doc_file = ?, downloaded_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$filename, $id]);
    }
    
    /**
     * Mark as parsed
     */
    public function markAsParsed($id) {
        $stmt = $this->db->prepare("UPDATE municipalities SET parsed_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get next municipality to download
     */
    public function getNextToDownload($mode = 'new') {
        if ($mode === 'all') {
            // Get first not downloaded, or restart from beginning
            $stmt = $this->db->query("SELECT * FROM municipalities WHERE doc_url IS NOT NULL AND (downloaded_at IS NULL OR doc_file IS NULL) ORDER BY name LIMIT 1");
            $mun = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mun) {
                // All done, restart from beginning
                $stmt = $this->db->query("SELECT * FROM municipalities WHERE doc_url IS NOT NULL ORDER BY name LIMIT 1");
                $mun = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return $mun;
        } else {
            // Only new ones (never attempted)
            $stmt = $this->db->query("SELECT * FROM municipalities WHERE doc_url IS NOT NULL AND downloaded_at IS NULL ORDER BY name LIMIT 1");
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    /**
     * Get download statistics
     */
    public function getStats() {
        $stats = [];
        
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM municipalities WHERE doc_url IS NOT NULL");
        $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $this->db->query("SELECT COUNT(*) as downloaded FROM municipalities WHERE downloaded_at IS NOT NULL AND doc_file IS NOT NULL");
        $stats['downloaded'] = $stmt->fetch(PDO::FETCH_ASSOC)['downloaded'];
        
        $stmt = $this->db->query("SELECT COUNT(*) as parsed FROM municipalities WHERE parsed_at IS NOT NULL");
        $stats['parsed'] = $stmt->fetch(PDO::FETCH_ASSOC)['parsed'];
        
        $stats['remaining'] = $stats['total'] - $stats['downloaded'];
        
        return $stats;
    }
    
    /**
     * Delete all municipalities
     */
    public function deleteAll() {
        return $this->db->exec("DELETE FROM municipalities");
    }
}
