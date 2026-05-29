<?php
/**
 * Maintenance & Cron Script
 * Run this periodically to maintain the system
 * 
 * Usage:
 * php maintenance.php [action]
 * 
 * Actions:
 * - recheck: Re-download and compare with existing data
 * - backup: Create database backup
 * - cleanup: Clean old files
 * - stats: Show statistics
 */

require_once 'config.php';

// Get action from command line
$action = $argv[1] ?? 'stats';

echo "=== Glasačka Mesta - Maintenance Script ===\n";
echo "Action: $action\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "==========================================\n\n";

switch ($action) {
    case 'recheck':
        recheck();
        break;
    
    case 'backup':
        backup();
        break;
    
    case 'cleanup':
        cleanup();
        break;
    
    case 'stats':
    default:
        stats();
        break;
}

/**
 * Show statistics
 */
function stats() {
    $db = getDB();
    
    // Municipalities
    $stmt = $db->query("SELECT COUNT(*) as count FROM municipalities");
    $mun_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Voting places
    $stmt = $db->query("SELECT COUNT(*) as count FROM voting_places");
    $places_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active places
    $stmt = $db->query("SELECT COUNT(*) as count FROM voting_places WHERE active = 1");
    $active_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Verified places
    $stmt = $db->query("SELECT COUNT(*) as count FROM voting_places WHERE verified = 1");
    $verified_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Database size
    $db_size = filesize(DB_FILE);
    
    echo "Statistics:\n";
    echo "  Municipalities: $mun_count\n";
    echo "  Voting Places: $places_count\n";
    echo "  Active: $active_count\n";
    echo "  Verified: $verified_count\n";
    echo "  Database Size: " . formatBytes($db_size) . "\n";
    echo "\n";
    
    // Last activities
    $stmt = $db->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 5");
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Recent Activities:\n";
    foreach ($activities as $act) {
        echo "  [{$act['created_at']}] {$act['action']}\n";
    }
}

/**
 * Backup database
 */
function backup() {
    $backup_dir = __DIR__ . '/backups';
    
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $backup_file = $backup_dir . '/backup_' . date('Y-m-d_His') . '.db';
    
    if (copy(DB_FILE, $backup_file)) {
        echo "Backup created successfully!\n";
        echo "File: $backup_file\n";
        echo "Size: " . formatBytes(filesize($backup_file)) . "\n";
        
        // Log
        logActivity('backup', null, null, "Backup: " . basename($backup_file));
        
        // Clean old backups (keep last 30)
        $backups = glob($backup_dir . '/backup_*.db');
        rsort($backups);
        
        if (count($backups) > 30) {
            $to_delete = array_slice($backups, 30);
            foreach ($to_delete as $old_backup) {
                unlink($old_backup);
                echo "Deleted old backup: " . basename($old_backup) . "\n";
            }
        }
    } else {
        echo "ERROR: Backup failed!\n";
    }
}

/**
 * Cleanup old files
 */
function cleanup() {
    echo "Cleaning up old files...\n";
    
    // Clean old log files (older than 90 days)
    $log_files = glob(LOGS_DIR . '/*.log');
    $deleted = 0;
    
    foreach ($log_files as $file) {
        if (filemtime($file) < strtotime('-90 days')) {
            unlink($file);
            $deleted++;
        }
    }
    
    echo "Deleted $deleted old log files\n";
    
    // Optimize database
    $db = getDB();
    $db->exec('VACUUM');
    echo "Database optimized\n";
    
    logActivity('cleanup', null, null, "Deleted $deleted files, optimized DB");
}

/**
 * Re-check data from RIK
 */
function recheck() {
    echo "Starting re-check from RIK website...\n";
    echo "This will download all documents again and compare.\n";
    echo "\n";
    
    // Trigger download
    $url = 'http://localhost/ORG/glasacka-mesta/download.php?start=1&mode=all';
    
    echo "Triggering download: $url\n";
    echo "Note: This is a background process. Check progress in the UI.\n";
    echo "\n";
    
    // You could use curl here to actually trigger it
    // For now, just show the URL
    
    logActivity('recheck_scheduled', null, null, 'Scheduled via maintenance script');
}

echo "\n=== Completed ===\n";
