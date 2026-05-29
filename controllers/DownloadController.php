<?php
/**
 * Download Controller
 * Handles downloading and parsing of municipality documents
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../models/Municipality.php';
require_once __DIR__ . '/../models/VotingPlace.php';

class DownloadController {
    private $db;
    private $municipalityModel;
    private $votingPlaceModel;
    
    public function __construct() {
        $this->db = getDB();
        $this->municipalityModel = new Municipality($this->db);
        $this->votingPlaceModel = new VotingPlace($this->db);
    }
    
    /**
     * Fetch municipalities from RIK website
     */
    public function fetchMunicipalities() {
        $html = @file_get_contents(SOURCE_URL);
        if (!$html) {
            return ['success' => false, 'message' => 'Failed to fetch RIK page'];
        }
        
        $municipalities = [];
        
        // Split by download-link-wrap divs (using single quotes)
        $sections = explode("<div class='download-link-wrap'>", $html);
        
        // Process each section (skip index 0 which is before first match)
        for ($i = 1; $i < count($sections); $i++) {
            $section = $sections[$i];
            
            // Extract name from downloads-title span
            if (preg_match("/<div class='downloads-title[^']*'[^>]*>.*?<span[^>]*>([^<]+)<\/span>/isu", $section, $nameMatch)) {
                $name = trim($nameMatch[1]);
                
                // Extract URL from file-holder a href (href uses double quotes)
                if (preg_match('/<a[^>]+href="([^"]+\.docx?)"[^>]*>/i', $section, $urlMatch)) {
                    $url = $urlMatch[1];
                    
                    // Make URL absolute if it's relative
                    if (strpos($url, 'http') !== 0) {
                        $url = 'https://www.rik.parlament.gov.rs' . $url;
                    }
                    
                    // Skip if it's a special file (not municipality)
                    if (stripos($name, 'rešenje') !== false || stripos($name, 'resenje') !== false) {
                        continue;
                    }
                    
                    // Clean name (remove notes like "izmena rešenja")
                    $clean_name = preg_replace('/\s*\([^)]*\)\s*/u', '', $name);
                    $clean_name = trim($clean_name);
                    
                    if ($clean_name) {
                        $municipalities[] = [
                            'name' => $clean_name,
                            'url' => $url,
                            'slug' => createSlug($clean_name)
                        ];
                    }
                }
            }
        }
        
        return ['success' => true, 'municipalities' => $municipalities];
    }
    
    /**
     * Save municipalities to database
     */
    public function saveMunicipalities($municipalities) {
        $saved = 0;
        $updated = 0;
        
        foreach ($municipalities as $mun) {
            $result = $this->municipalityModel->save($mun);
            if ($result['updated']) {
                $updated++;
            } else {
                $saved++;
            }
        }
        
        logActivity('fetch_municipalities', 'municipality', null, "Saved: $saved, Updated: $updated");
        
        return ['success' => true, 'saved' => $saved, 'updated' => $updated];
    }
    
    /**
     * Download DOC file for municipality
     */
    public function downloadDocFile($municipality_id, $url) {
        $mun = $this->municipalityModel->getById($municipality_id);
        
        if (!$mun) {
            return ['success' => false, 'message' => 'Municipality not found'];
        }
        
        // Encode URL properly (RIK URLs have spaces and special chars)
        $parsed = parse_url($url);
        $path_parts = explode('/', $parsed['path']);
        $last_part = array_pop($path_parts);
        $encoded_last = rawurlencode($last_part);
        $path_parts[] = $encoded_last;
        $encoded_url = $parsed['scheme'] . '://' . $parsed['host'] . implode('/', $path_parts);
        
        // Create filename
        $ext = (stripos($url, '.docx') !== false) ? 'docx' : 'doc';
        $filename = $mun['slug'] . '.' . $ext;
        $filepath = DOWNLOADS_DIR . '/' . $filename;
        
        // Download file with proper headers and error handling
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n",
                'timeout' => 30
            ]
        ]);
        
        $content = @file_get_contents($encoded_url, false, $context);
        if (!$content || strlen($content) < 100) {
            // Mark as attempted even if failed, to avoid infinite loop
            $this->municipalityModel->updateDownload($municipality_id, 'FAILED');
            usleep(THROTTLE_ERROR);
            logActivity('download_error', 'municipality', $municipality_id, "Failed to download from: $encoded_url");
            return ['success' => false, 'message' => 'Failed to download file or file too small'];
        }
        
        file_put_contents($filepath, $content);
        
        // Update database with success
        $this->municipalityModel->updateDownload($municipality_id, $filename);
        
        logActivity('download_doc', 'municipality', $municipality_id, "Downloaded: $filename (" . strlen($content) . " bytes)");
        
        usleep(THROTTLE_DELAY);
        
        return ['success' => true, 'file' => $filename, 'size' => strlen($content)];
    }
    
    /**
     * Parse DOC file - Extract voting places from Word document
     */
    public function parseDocFile($municipality_id) {
        $mun = $this->municipalityModel->getById($municipality_id);
        
        if (!$mun || !$mun['doc_file']) {
            return ['success' => false, 'message' => 'File not downloaded'];
        }
        
        $filepath = DOWNLOADS_DIR . '/' . $mun['doc_file'];
        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'File does not exist'];
        }
        
        try {
            // Parse the DOC/DOCX file
            $places = $this->extractVotingPlaces($filepath, $mun);
            
            // Save voting places to database
            if (count($places) > 0) {
                foreach ($places as $place) {
                    $this->votingPlaceModel->save([
                        'municipality_id' => $municipality_id,
                        'place_number' => $place['number'],
                        'place_name' => $place['name'],
                        'address' => $place['address'],
                        'area' => $place['area'],
                        'area_addresses' => $place['area_addresses'],
                        'source_file' => $mun['doc_file']
                    ]);
                }
            }
            
            // Mark as parsed
            $this->municipalityModel->markAsParsed($municipality_id);
            
            logActivity('parse_doc', 'municipality', $municipality_id, 'Parsed ' . count($places) . ' voting places');
            
            return ['success' => true, 'places_found' => count($places)];
            
        } catch (Exception $e) {
            // Even if parsing fails, mark as attempted to avoid reprocessing
            $this->municipalityModel->markAsParsed($municipality_id);
            logActivity('parse_error', 'municipality', $municipality_id, 'Parse error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'places_found' => 0];
        }
    }
    
    /**
     * Extract voting places from DOC/DOCX file
     */
    private function extractVotingPlaces($filepath, $municipality) {
        $places = [];
        
        // Try to convert DOC to text
        $text = $this->docToText($filepath);
        
        if (empty($text)) {
            return [];
        }
        
        // Parse text to extract voting places
        $lines = explode("\n", $text);
        
        $currentPlace = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Try to detect voting place entries
            if (preg_match('/^(\d+)\s*[\.\s]*(.+)$/u', $line, $match)) {
                if ($currentPlace) {
                    $places[] = $currentPlace;
                }
                
                $currentPlace = [
                    'number' => intval($match[1]),
                    'name' => trim($match[2]),
                    'address' => '',
                    'area' => '',
                    'area_addresses' => ''
                ];
            } else if ($currentPlace) {
                // Accumulate additional info
                if (empty($currentPlace['address'])) {
                    $currentPlace['address'] = $line;
                } else {
                    $currentPlace['area_addresses'] .= ($currentPlace['area_addresses'] ? '; ' : '') . $line;
                }
            }
        }
        
        // Don't forget the last one
        if ($currentPlace) {
            $places[] = $currentPlace;
        }
        
        return $places;
    }
    
    /**
     * Convert DOC/DOCX to plain text
     */
    private function docToText($filepath) {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        
        if ($ext === 'docx') {
            return $this->docxToText($filepath);
        } else {
            // DOC format: Try simple text extraction
            return $this->docSimpleExtract($filepath);
        }
    }
    
    /**
     * Simple text extraction from binary DOC files
     * Note: This is NOT a full parser, just extracts readable text
     */
    private function docSimpleExtract($filepath) {
        $content = file_get_contents($filepath);
        
        // Remove binary junk, keep only printable chars
        // This is a very rough extraction
        $text = '';
        $len = strlen($content);
        
        for ($i = 0; $i < $len; $i++) {
            $char = $content[$i];
            $ord = ord($char);
            
            // Keep printable ASCII, Cyrillic (UTF-8), newlines, tabs
            if (($ord >= 32 && $ord <= 126) || // ASCII printable
                $ord == 9 || $ord == 10 || $ord == 13 || // Tab, LF, CR
                ($ord >= 192 && $ord <= 255)) { // Extended ASCII/Cyrillic
                $text .= $char;
            } else {
                // Replace non-printable with space
                if ($ord == 0) {
                    if (strlen($text) > 0 && $text[strlen($text)-1] != ' ') {
                        $text .= ' ';
                    }
                }
            }
        }
        
        // Clean up multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        $text = str_replace('\r\n', "\n", $text);
        
        return $text;
    }
    
    /**
     * Extract text from DOCX file
     */
    private function docxToText($filepath) {
        $text = '';
        
        try {
            $zip = new ZipArchive();
            if ($zip->open($filepath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                
                if ($xml) {
                    // Remove XML tags and extract text
                    $xml = str_replace('</w:p>', "\n", $xml);
                    $xml = strip_tags($xml);
                    $text = trim($xml);
                }
            }
        } catch (Exception $e) {
            return '';
        }
        
        return $text;
    }
    
    /**
     * Clear all data
     */
    public function clearAllData() {
        try {
            // Delete from database
            $this->votingPlaceModel->deleteAll();
            $this->municipalityModel->deleteAll();
            
            // Delete downloaded files
            $files = glob(DOWNLOADS_DIR . '/*.{doc,docx}', GLOB_BRACE);
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            
            // Reset progress
            if (file_exists(PROGRESS_JSON)) {
                unlink(PROGRESS_JSON);
            }
            if (file_exists(CONTROL_JSON)) {
                unlink(CONTROL_JSON);
            }
            
            logActivity('clear_all', 'system', null, 'All data cleared');
            
            return ['success' => true, 'message' => 'All data cleared'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
