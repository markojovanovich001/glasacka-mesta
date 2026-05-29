<?php
/**
 * api.php
 * JSON REST endpoint za sve AJAX pozive iz UI-ja.
 *
 * Akcije:
 *   GET  ?action=status            – lista fajlova sa statusom
 *   GET  ?action=verzije&fajl_id=X – prethodna i nova verzija za diff
 *   POST ?action=run_check          – manuelno pokretanje cron logike
 *   POST ?action=prihvati_promenu   – body: {fajl_id}
 *   POST ?action=update_gm          – body: {id, polje, vrednost}
 *   GET  ?action=podrucja_za_gm&gm_id=X – područja za GM
 *   POST ?action=add_podrucje       – body: {gm_id, naziv}
 *   POST ?action=update_podrucje    – body: {id, naziv}
 *   POST ?action=delete_podrucje    – body: {id}
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// SSE stream – mora biti pre JSON headera
if (($_GET['action'] ?? '') === 'run_check_stream') {
    _actionRunCheckStream();
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Sprečiti XSS na JSON output-u
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'];

// Prihvati i JSON body (za fetch API pozive)
$body = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $body = json_decode($raw, true) ?? [];
    }
    // Merge sa $_POST (POST wins over JSON za iste ključeve)
    $body = array_merge($body, $_POST);
}

// Action: URL param ima prioritet, zatim JSON/POST body
$action = $_GET['action'] ?? $body['action'] ?? '';

try {
    $db = getDB();
    $result = match($action) {
        'status'           => actionStatus($db),
        'verzije'          => actionVerzije($db),
        'run_check'        => actionRunCheck(),
        'cron_log'         => actionCronLog(),        'prihvati_promenu' => actionPrihvatiPromenu($db, $body),
        'update_gm'        => actionUpdateGm($db, $body),
        'gm_list'          => actionGmList($db),
        'podrucja_za_gm'   => actionPodrucjaZaGm($db),
        'add_podrucje'     => actionAddPodrucje($db, $body),
        'update_podrucje'  => actionUpdatePodrucje($db, $body),
        'delete_podrucje'  => actionDeletePodrucje($db, $body),
        'reset_db'         => actionResetDb($db),
        'pause_check'      => actionPauseCheck(),
        'resume_check'     => actionResumeCheck(),
        'stop_check'       => actionStopCheck(),
        'toggle_zanemari'  => actionToggleZanemari($db, $body),
        'add_gm'           => actionAddGm($db, $body),
        'update_fajl'      => actionUpdateFajl($db, $body),
        'delete_gm'        => actionDeleteGm($db, $body),
        default            => throw new InvalidArgumentException("Nepoznata akcija: {$action}")
    };
    echo json_encode(['success' => true, 'data' => $result]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Action handlers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Lista svih fajlova sa statusom za dashboard.
 */
function actionStatus(mysqli $db): array
{
    $sql = "
        SELECT
            f.id,
            f.naziv,
            f.naziv_clean,
            f.tip,
            f.aktivan,
            f.zanemari,
            f.has_changes,
            f.last_error,
            f.opstina_id,
            f.grad_id,
            o.name  AS opstina_naziv,
            g.name  AS grad_naziv,
            COALESCE(f.last_checked_at, v.detected_at) AS poslednja_provera,
            v.file_ext,
            v.file_path,
            v.parsed,
            v.parse_error,
            (SELECT COUNT(*) FROM glasacka_mesta gm WHERE gm.fajl_id = f.id) AS gm_count
        FROM rik_fajlovi f
        LEFT JOIN opstine  o ON o.id = f.opstina_id
        LEFT JOIN gradovi  g ON g.id = f.grad_id
        LEFT JOIN rik_fajlovi_verzije v ON v.id = (
            SELECT id FROM rik_fajlovi_verzije
            WHERE fajl_id = f.id
            ORDER BY detected_at DESC, id DESC
            LIMIT 1
        )
        WHERE f.aktivan = 1
        ORDER BY f.naziv_clean
    ";
    $rows = [];
    $res  = $db->query($sql);
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Vraća podatke za diff dve verzije jednog fajla.
 */
function actionVerzije(mysqli $db): array
{
    $fajlId = (int)($_GET['fajl_id'] ?? 0);
    if ($fajlId <= 0) throw new InvalidArgumentException('fajl_id je obavezan');

    // Poslednje dve verzije
    $stmt = $db->prepare(
        'SELECT * FROM rik_fajlovi_verzije
         WHERE fajl_id = ?
         ORDER BY detected_at DESC, id DESC
         LIMIT 2'
    );
    $stmt->bind_param('i', $fajlId);
    $stmt->execute();
    $verzije = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($verzije)) {
        return ['nova' => null, 'prethodna' => null];
    }

    $nova       = $verzije[0];
    $prethodna  = $verzije[1] ?? null;

    // Priloži GM podatke za svaku verziju
    $nova['glasacka_mesta']      = getGmZaVerziju($db, $nova['id']);
    if ($prethodna) {
        $prethodna['glasacka_mesta'] = getGmZaVerziju($db, $prethodna['id']);
    }

    return ['nova' => $nova, 'prethodna' => $prethodna];
}

function getGmZaVerziju(mysqli $db, int $verzijaId): array
{
    $stmt = $db->prepare(
        'SELECT id, broj_gm, naziv, adresa
         FROM glasacka_mesta
         WHERE fajl_id = (SELECT fajl_id FROM rik_fajlovi_verzije WHERE id = ?)
         ORDER BY CAST(REGEXP_REPLACE(broj_gm, "[^0-9]", "") AS UNSIGNED)'
    );
    $stmt->bind_param('i', $verzijaId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Pokreće cron u background mode-u.
 * Vraća log_byte = veličina log fajla pre pokretanja,
 * tako da frontend može pratiti samo nove redove.
 */
function actionRunCheck(): array
{
    $cronFile = __DIR__ . DIRECTORY_SEPARATOR . 'cron.php';

    // Zapamti veličinu log fajla pre pokretanja
    $logFile = storagePath('logs', 'cron.log');
    $logByte = is_file($logFile) ? (int)filesize($logFile) : 0;

    $php = PHP_BINARY;
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($cronFile);

    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = 'start /B "" ' . $cmd . ' > NUL 2>&1';
        pclose(popen($cmd, 'r'));
    } else {
        exec($cmd . ' > /dev/null 2>&1 &');
    }

    return ['message' => 'Provera pokrenuta', 'log_byte' => $logByte];
}

/**
 * Vraća nove redove iz cron log fajla od zadatog byte offseta.
 * GET ?action=cron_log&from_byte=N
 */
function actionCronLog(): array
{
    $fromByte = max(0, (int)($_GET['from_byte'] ?? 0));
    $logFile  = storagePath('logs', 'cron.log');

    if (!is_file($logFile)) {
        return ['lines' => [], 'size' => 0, 'done' => false];
    }

    $size = (int)filesize($logFile);

    $lines = [];
    if ($fromByte < $size) {
        $fh      = fopen($logFile, 'rb');
        fseek($fh, $fromByte);
        $content = fread($fh, $size - $fromByte);
        fclose($fh);
        $lines = explode("\n", rtrim($content, "\n\r"));
    }

    $done = false;
    foreach ($lines as $line) {
        if (str_contains($line, '=== Cron završen')) {
            $done = true;
            break;
        }
    }

    return ['lines' => $lines, 'size' => $size, 'done' => $done];
}

/**
 * Markira fajl kao pregledan (has_changes = 0).
 */
function actionPrihvatiPromenu(mysqli $db, array $body): array
{
    $fajlId = (int)($body['fajl_id'] ?? 0);
    if ($fajlId <= 0) throw new InvalidArgumentException('fajl_id je obavezan');

    $stmt = $db->prepare('UPDATE rik_fajlovi SET has_changes=0, updated_at=NOW() WHERE id=?');
    $stmt->bind_param('i', $fajlId);
    $stmt->execute();
    $stmt->close();
    return ['updated' => true];
}

/**
 * Inline update jednog polja glasacka_mesta.
 */
function actionUpdateGm(mysqli $db, array $body): array
{
    $id      = (int)($body['id'] ?? 0);
    $polje   = $body['polje']   ?? '';
    $vrednost = $body['vrednost'] ?? '';

    if ($id <= 0) throw new InvalidArgumentException('id je obavezan');

    $allowed = ['broj_gm', 'naziv', 'adresa'];
    if (!in_array($polje, $allowed, true)) {
        throw new InvalidArgumentException("Polje '{$polje}' nije dozvoljeno");
    }

    // Latinize i capitalize vrednost
    $vrednost = latinize($vrednost);
    if (in_array($polje, ['naziv', 'adresa'], true)) {
        $vrednost = capitalizeWords($vrednost);
    }

    $stmt = $db->prepare(
        "UPDATE glasacka_mesta SET {$polje}=?, updated_at=NOW() WHERE id=?"
    );
    $stmt->bind_param('si', $vrednost, $id);

    $stmt->execute();
    $stmt->close();
    return ['updated' => true, 'vrednost' => $vrednost];
}

/**
 * Inline update jednog polja glasacka_mesta_adrese.
 * @deprecated Tabela glasacka_mesta_adrese je uklonjena.
 */
function actionUpdateAdresa(mysqli $db, array $body): array
{
    throw new InvalidArgumentException('Akcija update_adresa više nije podržana');
}

/**
 * Brisanje adrese.
 * @deprecated Tabela glasacka_mesta_adrese je uklonjena.
 */
function actionDeleteAdresa(mysqli $db, array $body): array
{
    throw new InvalidArgumentException('Akcija delete_adresa više nije podržana');
}

/**
 * Brisanje glasačkog mesta (kaskadno briše i adrese).
 */
function actionDeleteGm(mysqli $db, array $body): array
{
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new InvalidArgumentException('id je obavezan');

    $stmt = $db->prepare('DELETE FROM glasacka_mesta WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    return ['deleted' => true];
}

/**
 * Dodavanje adrese uz područje.
 * @deprecated Tabela glasacka_mesta_adrese je uklonjena.
 */
function actionAddAdresa(mysqli $db, array $body): array
{
    throw new InvalidArgumentException('Akcija add_adresa više nije podržana');
}

// ── Područja ────────────────────────────────────────────────────────────────

/**
 * Sva područja za jedan GM.
 */
function actionPodrucjaZaGm(mysqli $db): array
{
    $gmId = (int)($_GET['gm_id'] ?? 0);
    if ($gmId <= 0) throw new InvalidArgumentException('gm_id je obavezan');

    $stmt = $db->prepare(
        'SELECT id, gm_id, naziv, broj_adrese FROM glasacka_mesta_podrucja
         WHERE gm_id = ? ORDER BY id'
    );
    $stmt->bind_param('i', $gmId);
    $stmt->execute();
    $podrucja = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['podrucja' => $podrucja];
}

/**
 * Dodaj ručno područje za GM.
 */
function actionAddPodrucje(mysqli $db, array $body): array
{
    $gmId       = (int)($body['gm_id']       ?? 0);
    $naziv      = capitalizeWords(latinize(trim($body['naziv']      ?? '')));
    $brojAdrese = latinize(trim($body['broj_adrese'] ?? '')) ?: null;
    if ($gmId <= 0)    throw new InvalidArgumentException('gm_id je obavezan');
    if ($naziv === '') throw new InvalidArgumentException('naziv je obavezan');

    $stmt = $db->prepare('INSERT INTO glasacka_mesta_podrucja (gm_id, naziv, broj_adrese) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $gmId, $naziv, $brojAdrese);
    $stmt->execute();
    $newId = (int)$db->insert_id;
    $stmt->close();
    return ['id' => $newId, 'naziv' => $naziv, 'broj_adrese' => $brojAdrese];
}

/**
 * Inline update naziva područja.
 */
function actionUpdatePodrucje(mysqli $db, array $body): array
{
    $id         = (int)($body['id']         ?? 0);
    $naziv      = capitalizeWords(latinize(trim($body['naziv']      ?? '')));
    $brojAdrese = array_key_exists('broj_adrese', $body)
        ? (latinize(trim($body['broj_adrese'])) ?: null)
        : false; // false = nije prosleđen, nećemo ažurirati
    if ($id <= 0)      throw new InvalidArgumentException('id je obavezan');
    if ($naziv === '') throw new InvalidArgumentException('naziv je obavezan');

    if ($brojAdrese !== false) {
        $stmt = $db->prepare('UPDATE glasacka_mesta_podrucja SET naziv=?, broj_adrese=?, updated_at=NOW() WHERE id=?');
        $stmt->bind_param('ssi', $naziv, $brojAdrese, $id);
    } else {
        $stmt = $db->prepare('UPDATE glasacka_mesta_podrucja SET naziv=?, updated_at=NOW() WHERE id=?');
        $stmt->bind_param('si', $naziv, $id);
    }
    $stmt->execute();
    $stmt->close();
    return ['updated' => true, 'naziv' => $naziv, 'broj_adrese' => $brojAdrese === false ? null : $brojAdrese];
}

/**
 * Brisanje područja (kaskadno briše adrese).
 */
function actionDeletePodrucje(mysqli $db, array $body): array
{
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) throw new InvalidArgumentException('id je obavezan');

    $stmt = $db->prepare('DELETE FROM glasacka_mesta_podrucja WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    return ['deleted' => true];
}

/**
 * Lista GM za jedan fajl (za Tab 2 u detail.php).
 */
function actionGmList(mysqli $db): array
{
    $fajlId = (int)($_GET['fajl_id'] ?? 0);
    if ($fajlId <= 0) throw new InvalidArgumentException('fajl_id je obavezan');

    $search = trim($_GET['search'] ?? '');

    $sql = 'SELECT id, broj_gm, naziv, adresa, updated_at
            FROM glasacka_mesta
            WHERE fajl_id = ?';
    $params = [$fajlId];
    $types  = 'i';

    if (!empty($search)) {
        $like  = '%' . $db->real_escape_string(latinize($search)) . '%';
        $sql  .= ' AND (naziv LIKE ? OR broj_gm LIKE ? OR adresa LIKE ?)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types   .= 'sss';
    }

    $sql .= ' ORDER BY CAST(REGEXP_REPLACE(broj_gm, "[^0-9]", "") AS UNSIGNED)';

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Priloži područja za svaki GM u jednom upitu
    if (!empty($rows)) {
        $gmIds = array_column($rows, 'id');
        $ph    = implode(',', array_fill(0, count($gmIds), '?'));
        $stmtP = $db->prepare(
            "SELECT id, gm_id, naziv, broj_adrese FROM glasacka_mesta_podrucja WHERE gm_id IN ({$ph}) ORDER BY id"
        );
        $stmtP->bind_param(str_repeat('i', count($gmIds)), ...$gmIds);
        $stmtP->execute();
        $allPodrucja = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtP->close();

        $byGm = [];
        foreach ($allPodrucja as $p) {
            $byGm[$p['gm_id']][] = $p;
        }
        foreach ($rows as &$row) {
            $row['podrucja'] = $byGm[$row['id']] ?? [];
        }
        unset($row);
    }

    return $rows;
}

/**
 * Adrese za jedno glasačko mesto ili za jedno područje.
 * GET ?gm_id=X ili ?podrucje_id=X
 * @deprecated Tabela glasacka_mesta_adrese je uklonjena.
 */
function actionAdreseZaGm(mysqli $db): array
{
    return [];
}

/**
 * Prebacuje zanemari flag (0↔1) za jedan fajl.
 */
function actionToggleZanemari(mysqli $db, array $body): array
{
    $fajlId = (int)($body['fajl_id'] ?? 0);
    if ($fajlId <= 0) throw new InvalidArgumentException('fajl_id je obavezan');

    $stmt = $db->prepare('UPDATE rik_fajlovi SET zanemari = 1 - zanemari, updated_at=NOW() WHERE id=?');
    $stmt->bind_param('i', $fajlId);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare('SELECT zanemari FROM rik_fajlovi WHERE id=?');
    $stmt->bind_param('i', $fajlId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ['zanemari' => (int)($row['zanemari'] ?? 0)];
}

/**
 * Ažurira mapiranje opštine/grada za jedan fajl.
 */
function actionUpdateFajl(mysqli $db, array $body): array
{
    $fajlId    = (int)($body['fajl_id'] ?? 0);
    if ($fajlId <= 0) throw new InvalidArgumentException('fajl_id je obavezan');

    $allowed = ['opstina_id', 'grad_id', 'roditelj_fajl_id'];
    $polje   = $body['polje'] ?? '';
    if (!in_array($polje, $allowed, true)) throw new InvalidArgumentException("Polje '{$polje}' nije dozvoljeno");

    $vrednost = ($body['vrednost'] !== '' && $body['vrednost'] !== null) ? (int)$body['vrednost'] : null;

    $stmt = $db->prepare("UPDATE rik_fajlovi SET {$polje}=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param('ii', $vrednost, $fajlId);
    $stmt->execute();
    $stmt->close();
    return ['updated' => true];
}

/**
 * Ručno dodavanje glasačkog mesta za dati fajl.
 */
function actionAddGm(mysqli $db, array $body): array
{
    $fajlId  = (int)($body['fajl_id'] ?? 0);
    if ($fajlId <= 0) throw new InvalidArgumentException('fajl_id je obavezan');

    $brojGm   = latinize(trim($body['broj_gm']   ?? ''));
    $naziv    = capitalizeWords(latinize(trim($body['naziv']      ?? '')));
    $adresa   = capitalizeWords(latinize(trim($body['adresa']     ?? '')));

    if (empty($brojGm)) throw new InvalidArgumentException('broj_gm je obavezan');

    // Uzmi FK opštine/grada
    $stmt = $db->prepare('SELECT opstina_id, grad_id FROM rik_fajlovi WHERE id=?');
    $stmt->bind_param('i', $fajlId);
    $stmt->execute();
    $fk = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $opstinaId = $fk['opstina_id'] ?? null;
    $gradId    = $fk['grad_id']    ?? null;

    $stmt = $db->prepare(
        'INSERT INTO glasacka_mesta (fajl_id, broj_gm, naziv, adresa, opstina_id, grad_id)
         VALUES (?,?,?,?,?,?)'
    );
    $stmt->bind_param('isssii',
        $fajlId, $brojGm, $naziv, $adresa, $opstinaId, $gradId
    );
    $stmt->execute();
    $newId = (int)$db->insert_id;
    $stmt->close();
    return ['id' => $newId];
}

function actionResetDb(mysqli $db): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST metod obavezan');
    }

    $db->query('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['glasacka_mesta_podrucja', 'glasacka_mesta', 'rik_fajlovi_verzije', 'rik_fajlovi'] as $t) {
        $db->query("TRUNCATE TABLE `{$t}`");
        if ($db->errno) {
            throw new RuntimeException("Greška pri TRUNCATE {$t}: " . $db->error);
        }
    }
    $db->query('SET FOREIGN_KEY_CHECKS = 1');

    // Obriši sve fajlove u storage/fajlovi/ (ali ne sam direktorijum)
    $dir = storagePath('fajlovi');
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
    }

    // Ukloni flagove i logove
    foreach ([storagePath('cron.pause'), storagePath('cron.stop')] as $f) {
        if (file_exists($f)) @unlink($f);
    }
    foreach (['cron.log', 'last_run.txt'] as $logFile) {
        $f = storagePath('logs', $logFile);
        if (file_exists($f)) @unlink($f);
    }

    return ['message' => 'Baza, storage i logovi su uspešno resetovani'];
}

/**
 * Kreira fajl-zastavicu za pauziranje cron procesa.
 */
function actionPauseCheck(): array
{
    file_put_contents(storagePath('cron.pause'), date('Y-m-d H:i:s'));
    return ['paused' => true];
}

/**
 * Uklanja fajl-zastavicu (nastavlja cron proces).
 */
function actionResumeCheck(): array
{
    $f = storagePath('cron.pause');
    if (file_exists($f)) {
        unlink($f);
    }
    return ['resumed' => true];
}

/**
 * Kreira stop zastavicu – cron loop će prekinuti na sledećem koraku.
 */
function actionStopCheck(): array
{
    // Ukloni pause da bi loop mogao da stigne do stop provjere
    $pauseFile = storagePath('cron.pause');
    if (file_exists($pauseFile)) {
        unlink($pauseFile);
    }
    file_put_contents(storagePath('cron.stop'), date('Y-m-d H:i:s'));
    return ['stopped' => true];
}

/**
 * SSE endpoint: pokreće cron sinhronizovano i strimuje svaki redak odmah.
 * GET api.php?action=run_check_stream
 */
function _actionRunCheckStream(): void
{
    while (ob_get_level()) {
        ob_end_clean();
    }
    ini_set('output_buffering', '0');
    ini_set('zlib.output_compression', '0');

    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('X-Accel-Buffering: no');
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
        @apache_setenv('dont-vary', '1');
    }

    set_time_limit(0);
    ignore_user_abort(true);

    $cronFile = __DIR__ . DIRECTORY_SEPARATOR . 'cron.php';

    if (!is_file($cronFile)) {
        _sendSse(['error' => 'cron.php nije pronađen']);
        _sendSse(['done' => true, 'success' => false]);
        return;
    }

    // PHP_BINARY pod mod_php pokazuje na Apache (httpd.exe), ne na php.exe.
    // Koristimo više kandidata dok ne nađemo pravi php.exe.
    if (PHP_OS_FAMILY === 'Windows') {
        $candidates = [
            PHP_BINDIR . DIRECTORY_SEPARATOR . 'php.exe',
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php.exe',
            // XAMPP: httpd.exe je u xampp/apache/bin → xampp/php/php.exe
            dirname(dirname(dirname(PHP_BINARY))) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe',
            'C:\\xampp\\php\\php.exe',
            'C:\\php\\php.exe',
        ];
        $phpCli  = 'php.exe'; // PATH fallback
        foreach ($candidates as $c) {
            if (is_file($c)) { $phpCli = $c; break; }
        }
        $nullDev = 'NUL';
    } else {
        $phpCli  = PHP_BINARY;
        $nullDev = '/dev/null';
    }

    $cmd = escapeshellarg($phpCli) . ' -d output_buffering=0 ' . escapeshellarg($cronFile);

    $desc = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', $nullDev, 'w'], // odbaciti Apache/system stderr
    ];

    $process = proc_open($cmd, $desc, $pipes, __DIR__);

    if (!is_resource($process)) {
        _sendSse(['error' => 'proc_open nije uspeo (PHP CLI: ' . $phpCli . ')']);
        _sendSse(['done' => true, 'success' => false]);
        return;
    }

    fclose($pipes[0]);

    // Blokujući read – svaki echo u cron.php stiže odmah
    while (!feof($pipes[1])) {
        $line = fgets($pipes[1]);
        if ($line !== false) {
            $line = rtrim($line, "\r\n");
            if ($line !== '') {
                _sendSse(['line' => $line]);
            }
        }
    }

    fclose($pipes[1]);
    proc_close($process);

    _sendSse(['done' => true, 'success' => true]);
}

function _sendSse(array $data): void
{
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}
