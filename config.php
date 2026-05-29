<?php
/**
 * Configuration file for Voting Places Management System
 * Glasačka Mesta - Republika Srbija
 * 
 * @version 1.0
 * @date 2026-01-31
 */

// Database configuration (SQLite for simplicity)
define('DB_FILE', __DIR__ . '/data/glasacka_mesta.db');

// Data directories
define('DATA_DIR', __DIR__ . '/data');
define('DOWNLOADS_DIR', DATA_DIR . '/downloads');
define('PARSED_DIR', DATA_DIR . '/parsed');
define('LOGS_DIR', DATA_DIR . '/logs');

// JSON storage files
define('MUNICIPALITIES_JSON', DATA_DIR . '/municipalities.json');
define('PROGRESS_JSON', DATA_DIR . '/progress.json');
define('CONTROL_JSON', DATA_DIR . '/control.json');
define('STATS_JSON', DATA_DIR . '/stats.json');

// Source URL
define('SOURCE_URL', 'https://www.rik.parlament.gov.rs/tekst/sr/12021/glasacka-mesta.php');

// Throttling settings (microseconds)
define('THROTTLE_DELAY', 1500000); // 1.5s between requests
define('THROTTLE_ERROR', 3000000); // 3s after error

// Create necessary directories
$dirs = [DATA_DIR, DOWNLOADS_DIR, PARSED_DIR, LOGS_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

/**
 * Initialize SQLite database
 * 
 * Note: Table is named "municipalities" for technical reasons,
 * but represents both cities (градови) and municipalities (општине)
 * because RIK doesn't distinguish between them.
 * UI labels use "Локација" (Location) as a generic term.
 */
function initDatabase() {
    try {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create municipalities table (stores both cities and municipalities)
        $db->exec("CREATE TABLE IF NOT EXISTS municipalities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            slug TEXT NOT NULL UNIQUE,
            doc_url TEXT,
            doc_file TEXT,
            downloaded_at DATETIME,
            parsed_at DATETIME,
            active INTEGER DEFAULT 1,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create voting_places table
        $db->exec("CREATE TABLE IF NOT EXISTS voting_places (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            municipality_id INTEGER NOT NULL,
            place_number INTEGER,
            place_name TEXT,
            address TEXT,
            area TEXT,
            area_addresses TEXT,
            active INTEGER DEFAULT 1,
            verified INTEGER DEFAULT 0,
            notes TEXT,
            source_file TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (municipality_id) REFERENCES municipalities(id) ON DELETE CASCADE
        )");
        
        // Create indexes
        $db->exec("CREATE INDEX IF NOT EXISTS idx_municipality_slug ON municipalities(slug)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_municipality_active ON municipalities(active)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_voting_place_municipality ON voting_places(municipality_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_voting_place_active ON voting_places(active)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_voting_place_verified ON voting_places(verified)");
        
        // Create activity log table
        $db->exec("CREATE TABLE IF NOT EXISTS activity_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT NOT NULL,
            entity_type TEXT,
            entity_id INTEGER,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        return $db;
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

/**
 * Get database connection
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = initDatabase();
    }
    return $db;
}

/**
 * Log activity
 */
function logActivity($action, $entity_type = null, $entity_id = null, $details = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_log (action, entity_type, entity_id, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$action, $entity_type, $entity_id, $details]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Create slug from text
 */
function createSlug($text) {
    // Cyrillic to Latin transliteration
    $cyrillic = ['А','Б','В','Г','Д','Ђ','Е','Ж','З','И','Ј','К','Л','Љ','М','Н','Њ','О','П','Р','С','Т','Ћ','У','Ф','Х','Ц','Ч','Џ','Ш','а','б','в','г','д','ђ','е','ж','з','и','ј','к','л','љ','м','н','њ','о','п','р','с','т','ћ','у','ф','х','ц','ч','џ','ш'];
    $latin = ['A','B','V','G','D','Dj','E','Z','Z','I','J','K','L','Lj','M','N','Nj','O','P','R','S','T','C','U','F','H','C','C','Dz','S','a','b','v','g','d','dj','e','z','z','i','j','k','l','lj','m','n','nj','o','p','r','s','t','c','u','f','h','c','c','dz','s'];
    
    $text = str_replace($cyrillic, $latin, $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    
    return $text;
}
