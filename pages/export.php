<?php
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers.php';
require_once dirname(__DIR__) . '/layout.php';

$db = getDB();

$EXPORT_TABLES  = ['gradovi', 'opstine', 'glasacka_mesta', 'glasacka_mesta_podrucja'];
$EXPORT_FORMATS = ['csv', 'json', 'mysql'];

// ── Fetch rows (with spatial column handling for gradovi/opstine) ──────────────
function fetchRows(mysqli $db, string $table): array
{
    if ($table === 'gradovi') {
        $sql = 'SELECT id, id_od, name, geometry, ST_AsText(geometry_geojson) AS geometry_geojson
                FROM gradovi ORDER BY id';
    } elseif ($table === 'opstine') {
        $sql = 'SELECT id, id_gradovi, id_od, name, geometry, ST_AsText(geometry_geojson) AS geometry_geojson
                FROM opstine ORDER BY id';
    } else {
        $sql = "SELECT * FROM `{$table}` ORDER BY id";
    }
    $res  = $db->query($sql);
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

// ── CSV ───────────────────────────────────────────────────────────────────────
function sendCsv(array $rows, string $filename): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM per Excel
    if (!empty($rows)) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
    }
    fclose($out);
}

// ── JSON ──────────────────────────────────────────────────────────────────────
function sendJson(array $rows, string $filename): void
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

// ── MySQL dump (one or all tables) ────────────────────────────────────────────
function buildTableSql(mysqli $db, string $table, array $rows): string
{
    $isGeo = in_array($table, ['gradovi', 'opstine'], true);
    $buf   = '';

    $createRes = $db->query("SHOW CREATE TABLE `{$table}`");
    $createRow = $createRes->fetch_assoc();
    $buf .= "-- -----------------------------------------------------------\n";
    $buf .= "-- Tabela: {$table}\n";
    $buf .= "-- -----------------------------------------------------------\n";
    $buf .= "DROP TABLE IF EXISTS `{$table}`;\n";
    $buf .= $createRow['Create Table'] . ";\n\n";

    if (empty($rows)) {
        return $buf;
    }

    $cols     = array_keys($rows[0]);
    $colsList = implode(', ', array_map(fn ($c) => "`{$c}`", $cols));

    foreach (array_chunk($rows, 500) as $chunk) {
        $buf .= "INSERT INTO `{$table}` ({$colsList}) VALUES\n";
        $groups = [];
        foreach ($chunk as $row) {
            $parts = [];
            foreach ($row as $col => $val) {
                if ($val === null) {
                    $parts[] = 'NULL';
                } elseif ($isGeo && $col === 'geometry_geojson') {
                    $esc     = $db->real_escape_string((string)$val);
                    $parts[] = "ST_GeomFromText('{$esc}')";
                } else {
                    $esc     = $db->real_escape_string((string)$val);
                    $parts[] = "'{$esc}'";
                }
            }
            $groups[] = '(' . implode(', ', $parts) . ')';
        }
        $buf .= implode(",\n", $groups) . ";\n\n";
    }

    return $buf;
}

function sendMysql(mysqli $db, string $table, array $rows, string $filename): void
{
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.sql"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    echo "-- GM Monitor – Export: {$table}\n";
    echo '-- Generisano: ' . date('Y-m-d H:i:s') . "\n\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET foreign_key_checks = 0;\n\n";
    echo buildTableSql($db, $table, $rows);
    echo "SET foreign_key_checks = 1;\n";
}

function sendMysqlAll(mysqli $db, array $tables): void
{
    $filename = 'glasacka_mesta_export_' . date('Ymd_His');
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.sql"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    echo "-- GM Monitor – Export svih tabela\n";
    echo '-- Generisano: ' . date('Y-m-d H:i:s') . "\n\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET foreign_key_checks = 0;\n\n";

    foreach ($tables as $table) {
        echo buildTableSql($db, $table, fetchRows($db, $table));
        echo "\n";
    }

    echo "SET foreign_key_checks = 1;\n";
}

// ── Download handler ──────────────────────────────────────────────────────────
if (!empty($_GET['dl'])) {
    set_time_limit(0);

    $table  = trim($_GET['table']  ?? '');
    $format = trim($_GET['format'] ?? '');

    // Export svih tabela (samo MySQL)
    if ($table === '_all') {
        if ($format !== 'mysql') {
            http_response_code(400);
            exit('Neispravan zahtev.');
        }
        sendMysqlAll($db, $EXPORT_TABLES);
        exit;
    }

    if (!in_array($table, $EXPORT_TABLES, true) || !in_array($format, $EXPORT_FORMATS, true)) {
        http_response_code(400);
        exit('Neispravan zahtev.');
    }

    $rows     = fetchRows($db, $table);
    $filename = $table . '_' . date('Ymd_His');

    match ($format) {
        'csv'   => sendCsv($rows, $filename),
        'json'  => sendJson($rows, $filename),
        'mysql' => sendMysql($db, $table, $rows, $filename),
    };
    exit;
}

// ── Stats for UI ──────────────────────────────────────────────────────────────
$tableInfo = [
    'gradovi'                 => ['label' => 'Gradovi',                   'icon' => 'bi-buildings',      'color' => 'primary'],
    'opstine'                 => ['label' => 'Opštine',                   'icon' => 'bi-geo-alt',         'color' => 'info'],
    'glasacka_mesta'          => ['label' => 'Glasačka mesta',            'icon' => 'bi-ballot',          'color' => 'success'],
    'glasacka_mesta_podrucja' => ['label' => 'Glasačka mesta – područja', 'icon' => 'bi-signpost-split',  'color' => 'warning'],
];

$counts = [];
foreach ($EXPORT_TABLES as $tbl) {
    $counts[$tbl] = (int)($db->query("SELECT COUNT(*) c FROM `{$tbl}`")->fetch_assoc()['c'] ?? 0);
}

$totalRows = array_sum($counts);

layout_start('Export podataka', 'export', [['Export', null]]);
?>

<!-- ── Stat cards ──────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <?php foreach ($EXPORT_TABLES as $tbl):
      $info = $tableInfo[$tbl]; ?>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon bg-<?= $info['color'] ?>-subtle text-<?= $info['color'] ?>">
        <i class="bi <?= $info['icon'] ?>"></i>
      </div>
      <div>
        <div class="stat-value"><?= number_format($counts[$tbl], 0, ',', '.') ?></div>
        <div class="stat-label"><?= htmlspecialchars($info['label']) ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Export tabela ───────────────────────────────────────────────────────── -->
<div class="table-card mb-4">
  <div class="table-card-header">
    <i class="bi bi-download text-secondary"></i>
    <span class="fw-semibold">Preuzmi tabelu</span>
    <span class="text-muted ms-1" style="font-size:.8rem;">— klikni na dugme da preuzmeš fajl</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th style="min-width:220px;">Tabela</th>
          <th class="text-center" style="width:80px;">Redova</th>
          <th class="text-center" style="width:110px;">
            <i class="bi bi-filetype-csv me-1 text-success"></i>CSV
          </th>
          <th class="text-center" style="width:110px;">
            <i class="bi bi-braces me-1 text-info"></i>JSON
          </th>
          <th class="text-center" style="width:110px;">
            <i class="bi bi-server me-1 text-primary"></i>MySQL
          </th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($EXPORT_TABLES as $tbl):
            $info = $tableInfo[$tbl];
            $fmts = [
                'csv'   => ['success', 'bi-filetype-csv',  '.csv'],
                'json'  => ['info',    'bi-braces',        '.json'],
                'mysql' => ['primary', 'bi-server',        '.sql'],
            ];
        ?>
        <tr>
          <td>
            <i class="bi <?= $info['icon'] ?> me-1 text-<?= $info['color'] ?>"></i>
            <code><?= htmlspecialchars($tbl) ?></code>
            <small class="text-muted d-none d-sm-inline ms-1" style="font-size:.75rem;">
              — <?= htmlspecialchars($info['label']) ?>
            </small>
          </td>
          <td class="text-center">
            <span class="badge bg-secondary rounded-pill" style="font-variant-numeric:tabular-nums;">
              <?= number_format($counts[$tbl], 0, ',', '.') ?>
            </span>
          </td>
          <?php foreach ($fmts as $fmt => [$color, $icon, $ext]): ?>
          <td class="text-center">
            <a href="?dl=1&table=<?= urlencode($tbl) ?>&format=<?= $fmt ?>"
               class="btn btn-sm btn-outline-<?= $color ?> d-inline-flex align-items-center gap-1"
               title="Preuzmi <?= htmlspecialchars($tbl) ?><?= $ext ?>">
              <i class="bi <?= $icon ?>"></i>
              <span class="d-none d-lg-inline"><?= $ext ?></span>
            </a>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Export sve ──────────────────────────────────────────────────────────── -->
<div class="table-card">
  <div class="table-card-header">
    <i class="bi bi-archive text-secondary"></i>
    <span class="fw-semibold">Export svih tabela</span>
  </div>
  <div class="p-4">
    <div class="row g-3 align-items-center">
      <div class="col-md">
        <p class="mb-1 fw-semibold" style="font-size:.9rem;">Sve 4 tabele u jednom fajlu</p>
        <p class="mb-0 text-muted" style="font-size:.82rem;">
          Uključuje:
          <?php foreach ($EXPORT_TABLES as $i => $tbl): ?>
            <code><?= htmlspecialchars($tbl) ?></code><?= $i < count($EXPORT_TABLES) - 1 ? ', ' : '' ?>
          <?php endforeach; ?>
          &mdash; ukupno <strong><?= number_format($totalRows, 0, ',', '.') ?></strong> redova.
        </p>
        <p class="mb-0 text-muted mt-1" style="font-size:.78rem;">
          <i class="bi bi-info-circle me-1"></i>Za tabele
          <code>gradovi</code> / <code>opstine</code> prostorni tip <code>geometry_geojson</code>
          se konvertuje u WKT i restaurira sa <code>ST_GeomFromText()</code>.
        </p>
      </div>
      <div class="col-md-auto d-flex flex-wrap gap-2">
        <a href="?dl=1&table=_all&format=mysql"
           class="btn btn-primary d-flex align-items-center gap-2">
          <i class="bi bi-server"></i>
          MySQL dump
        </a>
      </div>
    </div>
  </div>
</div>

<?php
layout_end('');
exit;
