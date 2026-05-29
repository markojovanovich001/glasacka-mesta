<?php
// ── Database connection ───────────────────────────────────────────────────────
function getDB(): mysqli
{
    static $conn = null;
    if ($conn !== null) return $conn;

    // $conn = new mysqli('localhost', 'gridelic_glasacka_mesta', 'IU~!B!b2EPGDQBat', 'gridelic_glasacka_mesta');
    $conn = new mysqli('localhost', 'root', '', 'birackamesta');
    if ($conn->connect_error) {
        http_response_code(500);
        header('Content-Type: application/json');
        exit(json_encode(['error' => 'Greška pri konekciji na bazu']));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
