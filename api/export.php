<?php
/**
 * API: Export Data
 */
require_once '../config.php';

$format = $_GET['format'] ?? 'json';

try {
    $db = getDB();
    
    // Get all data
    $stmt = $db->query("
        SELECT 
            vp.*,
            m.name as municipality_name,
            m.slug as municipality_slug
        FROM voting_places vp
        JOIN municipalities m ON vp.municipality_id = m.id
        WHERE vp.active = 1
        ORDER BY m.name, vp.place_number
    ");
    
    $places = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    switch ($format) {
        case 'json':
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="glasacka_mesta_' . date('Y-m-d') . '.json"');
            echo json_encode($places, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="glasacka_mesta_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // BOM for UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Header
            fputcsv($output, ['Општина', 'Број', 'Назив', 'Адреса', 'Подручје', 'Адресе у Подручју', 'Верификовано']);
            
            // Data
            foreach ($places as $place) {
                fputcsv($output, [
                    $place['municipality_name'],
                    $place['place_number'],
                    $place['place_name'],
                    $place['address'],
                    $place['area'],
                    $place['area_addresses'],
                    $place['verified'] ? 'Да' : 'Не'
                ]);
            }
            
            fclose($output);
            break;
            
        case 'sql':
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="glasacka_mesta_wordpress_' . date('Y-m-d') . '.sql"');
            
            echo "-- WordPress SQL Export for Glasačka Mesta\n";
            echo "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            
            echo "-- Create table for voting places\n";
            echo "CREATE TABLE IF NOT EXISTS wp_glasacka_mesta (\n";
            echo "  id bigint(20) NOT NULL AUTO_INCREMENT,\n";
            echo "  municipality varchar(255) NOT NULL,\n";
            echo "  municipality_slug varchar(255) NOT NULL,\n";
            echo "  place_number int(11),\n";
            echo "  place_name varchar(500),\n";
            echo "  address varchar(500),\n";
            echo "  area varchar(500),\n";
            echo "  area_addresses text,\n";
            echo "  verified tinyint(1) DEFAULT 0,\n";
            echo "  created_at datetime DEFAULT CURRENT_TIMESTAMP,\n";
            echo "  updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
            echo "  PRIMARY KEY (id),\n";
            echo "  KEY municipality_slug (municipality_slug),\n";
            echo "  KEY verified (verified)\n";
            echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";
            
            echo "-- Insert data\n";
            foreach ($places as $place) {
                $municipality = $db->quote($place['municipality_name']);
                $slug = $db->quote($place['municipality_slug']);
                $number = $place['place_number'] ?: 'NULL';
                $name = $db->quote($place['place_name'] ?: '');
                $address = $db->quote($place['address'] ?: '');
                $area = $db->quote($place['area'] ?: '');
                $area_addresses = $db->quote($place['area_addresses'] ?: '');
                $verified = $place['verified'] ? 1 : 0;
                
                echo "INSERT INTO wp_glasacka_mesta (municipality, municipality_slug, place_number, place_name, address, area, area_addresses, verified) VALUES\n";
                echo "($municipality, $slug, $number, $name, $address, $area, $area_addresses, $verified);\n";
            }
            break;
            
        default:
            throw new Exception('Unknown format');
    }
    
    logActivity('export_data', null, null, "Format: $format, Records: " . count($places));
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
