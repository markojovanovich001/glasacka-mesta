<?php
/**
 * cron.php
 * Orchestrator za proveru izmena fajlova sa RIK sajta.
 *
 * Pokretanje:
 *   CLI:  php cron.php
 *   HTTP: pokretanje preko UI dugmeta "Pokreni proveru" (SSE stream u api.php)
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/scraper.php';
require_once __DIR__ . '/parser.php';
require_once __DIR__ . '/converter.php';

// ── Main ──────────────────────────────────────────────────────────────────────
$isCli = (php_sapi_name() === 'cli');
set_time_limit(0);
ini_set('memory_limit', '256M');

$startTime = microtime(true);
$stats     = ['processed' => 0, 'new_versions' => 0, 'errors' => 0, 'skipped' => 0];

logMessage("=== Cron pokrenut ===", 'INFO');

// Zapamti vreme pokretanja (za prikaz "Poslednja provera" u UI)
file_put_contents(storagePath('logs', 'last_run.txt'), date('Y-m-d H:i:s'));

// Cleanup: ukloni stari stop fajl ako postoji
@unlink(storagePath('cron.stop'));

try {
    $scraper = new RikScraper();
    $parser  = new GmParser();

    // 1. Preuzmi listing
    logMessage('Preuzimanje listinga sa RIK-a...', 'INFO');
    $items = $scraper->fetchList();
    $total = count($items);
    logMessage("Pronađeno {$total} fajlova na listi.", 'INFO');
    logMessage("=== CRON_TOTAL:{$total} ===", 'INFO');

    // 2. Obrada svakog fajla
    $handled   = 0;
    $isFirst   = true;
    $pauseFile = storagePath('cron.pause');
    foreach ($items as $item) {
        // Provjeri zahtev za zaustavljanje
        $stopFile = storagePath('cron.stop');
        if (file_exists($stopFile)) {
            @unlink($stopFile);
            logMessage('⏹ Zaustavljeno od strane korisnika.', 'INFO');
            break;
        }

        // Provjeri pauzu između stavki
        if (file_exists($pauseFile)) {
            logMessage('⏸ Pauzirano – čekam na nastavak...', 'INFO');
            while (file_exists($pauseFile)) {
                sleep(1);
            }
            logMessage('▶ Nastavljam...', 'INFO');
        }
        try {
            processItem($item, $scraper, $parser, $isFirst, $stats);
        } catch (Throwable $e) {
            logMessage("Greška pri obradi [{$item['naziv']}]: " . $e->getMessage(), 'ERROR');
            $stats['errors']++;
        }
        $handled++;
        logMessage(
            "=== CRON_STATUS {$handled}/{$total} Nova:{$stats['new_versions']}" .
            " Preskoceno:{$stats['skipped']} Greske:{$stats['errors']} ===",
            'INFO'
        );
        $isFirst = false;
    }

} catch (Throwable $e) {
    logMessage('FATALNA GREŠKA: ' . $e->getMessage(), 'ERROR');
    $stats['errors']++;
}

$elapsed = round(microtime(true) - $startTime, 2);

// Cleanup: ukloni pause i stop fajl ako postoje
@unlink(storagePath('cron.pause'));
@unlink(storagePath('cron.stop'));
logMessage(
    "=== Cron završen za {$elapsed}s | " .
    "Obrađeno: {$stats['processed']}, " .
    "Novih verzija: {$stats['new_versions']}, " .
    "Preskočeno: {$stats['skipped']}, " .
    "Grešaka: {$stats['errors']} ===",
    'INFO'
);

if (!$isCli) {
    echo json_encode([
        'success' => true,
        'elapsed' => $elapsed,
        'stats'   => $stats,
    ]);
}

// ── Funkcija obrade jednog fajla ─────────────────────────────────────────────

function processItem(
    array      $item,
    RikScraper $scraper,
    GmParser   $parser,
    bool       $isFirst,
    array      &$stats
): void {
    $naziv = $item['naziv'];
    logMessage("Obrađujem: {$naziv}", 'INFO');

    $db   = getDB();
    $slug = makeSlug($item['naziv_clean']);

    // ── Da li zapis već postoji u bazi? ──────────────────────────────────────
    $stmtEx = $db->prepare('SELECT id, word_hash, zanemari FROM rik_fajlovi WHERE url = ?');
    $stmtEx->bind_param('s', $item['url']);
    $stmtEx->execute();
    $existing = $stmtEx->get_result()->fetch_assoc();
    $stmtEx->close();

    if ($existing !== null) {
        // ── Već importovano – samo provjeri izmenu fajla ─────────────────────
        // Zapamti vreme provere bez obzira na ishod
        $stmtChk = $db->prepare('UPDATE rik_fajlovi SET last_checked_at=NOW() WHERE id=?');
        $stmtChk->bind_param('i', $existing['id']);
        $stmtChk->execute();
        $stmtChk->close();

        if (!empty($existing['zanemari'])) {
            logMessage("  Zanemaren – preskačem: {$naziv}", 'INFO');
            $stats['skipped']++;
            return;
        }

        $fileInfo = $scraper->downloadFile($item['url'], $slug, !$isFirst);
        $stats['processed']++;

        if ($fileInfo === null) {
            logMessage("  Download nije uspeo: {$naziv}", 'ERROR');
            $stats['errors']++;
            return;
        }

        if ($existing['word_hash'] === $fileInfo['hash']) {
            logMessage("  Nema izmena (isti hash): {$naziv}", 'DEBUG');
            $stats['skipped']++;
            return;
        }

        // Fajl se izmenio – sačuvan na disk, bez DB importa
        logMessage("  Fajl izmenjen, sačuvan na disk (bez DB importa): {$naziv}", 'INFO');
        $stats['new_versions']++;
        return;
    }

    // ── Prvi put – pun import ────────────────────────────────────────────────
    $fajlId = $scraper->upsertFajl($item);

    // Provjeri da li je stavka označena kao "zanemari"
    $stmtZ = $db->prepare('SELECT zanemari FROM rik_fajlovi WHERE id=?');
    $stmtZ->bind_param('i', $fajlId);
    $stmtZ->execute();
    $zanRow = $stmtZ->get_result()->fetch_assoc();
    $stmtZ->close();
    if (!empty($zanRow['zanemari'])) {
        logMessage("  Zanemaren – preskačem: {$naziv}", 'INFO');
        $stats['skipped']++;
        return;
    }

    // Preuzmi fajl (2s delay između downloada, ali ne pre prvog)
    $fileInfo = $scraper->downloadFile($item['url'], $slug, !$isFirst);

    if ($fileInfo === null) {
        $scraper->setError($fajlId, 'Download nije uspeo');
        $stats['errors']++;
        return;
    }

    $stats['processed']++;

    // Proveri da li je hash isti kao poslednja verzija
    $lastHash = $scraper->getLastVersionHash($fajlId);
    if ($lastHash === $fileInfo['hash']) {
        logMessage("  Nema izmena (isti hash): {$naziv}", 'DEBUG');
        $stats['skipped']++;
        return;
    }

    // Nova verzija!
    logMessage("  Nova verzija detektovana: {$naziv}", 'INFO');
    $verzijaId = $scraper->insertVersion($fajlId, $fileInfo);
    $stats['new_versions']++;

    // ── Amandman/izmena fajlovi ───────────────────────────────────────────────
    if (in_array($item['tip'], ['izmena', 'dopuna'], true)) {
        $napomena = "Postoji dokument sa izmenama/dopunama: {$naziv} (fajl: {$fileInfo['path']})";
        $scraper->setNapomena($fajlId, $napomena);

        $absPath      = projectRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileInfo['path']);
        $parseAbsPath = $absPath;

        // doc → docx konverzija (za bolji PDF)
        if ($fileInfo['ext'] === 'doc') {
            $docxAbs = DocConverter::convert($absPath);
            if ($docxAbs !== null) {
                $convRel  = str_replace(projectRoot() . DIRECTORY_SEPARATOR, '', $docxAbs);
                $convRel  = str_replace(DIRECTORY_SEPARATOR, '/', $convRel);
                $stmtC    = $db->prepare('UPDATE rik_fajlovi_verzije SET conv_path=? WHERE id=?');
                $stmtC->bind_param('si', $convRel, $verzijaId);
                $stmtC->execute();
                $stmtC->close();
                $parseAbsPath = $docxAbs;
            }
        }

        // PDF (nekritično)
        try {
            $pdfAbs = DocConverter::convertToPdf($parseAbsPath);
            if ($pdfAbs !== null) {
                $pdfRel  = str_replace(projectRoot() . DIRECTORY_SEPARATOR, '', $pdfAbs);
                $pdfRel  = str_replace(DIRECTORY_SEPARATOR, '/', $pdfRel);
                $stmtPdf = $db->prepare('UPDATE rik_fajlovi_verzije SET pdf_path=? WHERE id=?');
                $stmtPdf->bind_param('si', $pdfRel, $verzijaId);
                $stmtPdf->execute();
                $stmtPdf->close();
            }
        } catch (Throwable $pdfEx) {
            logMessage("  PDF konverzija nije uspela: " . $pdfEx->getMessage(), 'WARN');
        }

        $stmtP = $db->prepare('UPDATE rik_fajlovi_verzije SET parsed=1 WHERE id=?');
        $stmtP->bind_param('i', $verzijaId);
        $stmtP->execute();
        $stmtP->close();
        logMessage("  Amandman sačuvan, parsiranje preskočeno: {$naziv}", 'INFO');
        return;
    }

    $absPath      = projectRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileInfo['path']);
    $parseAbsPath = $absPath;

    // 1. .doc → .docx (za bolji parsing)
    if ($fileInfo['ext'] === 'doc') {
        logMessage("  Konvertujem doc→docx: {$naziv}", 'INFO');
        $docxAbs = DocConverter::convert($absPath);
        if ($docxAbs !== null) {
            $convRel  = str_replace(projectRoot() . DIRECTORY_SEPARATOR, '', $docxAbs);
            $convRel  = str_replace(DIRECTORY_SEPARATOR, '/', $convRel);
            $stmtC    = $db->prepare('UPDATE rik_fajlovi_verzije SET conv_path=? WHERE id=?');
            $stmtC->bind_param('si', $convRel, $verzijaId);
            $stmtC->execute();
            $stmtC->close();
            $parseAbsPath = $docxAbs;
        }
    }

    // 2. → PDF (nekritično)
    try {
        $pdfAbs = DocConverter::convertToPdf($parseAbsPath);
        if ($pdfAbs !== null) {
            $pdfRel  = str_replace(projectRoot() . DIRECTORY_SEPARATOR, '', $pdfAbs);
            $pdfRel  = str_replace(DIRECTORY_SEPARATOR, '/', $pdfRel);
            $stmtPdf = $db->prepare('UPDATE rik_fajlovi_verzije SET pdf_path=? WHERE id=?');
            $stmtPdf->bind_param('si', $pdfRel, $verzijaId);
            $stmtPdf->execute();
            $stmtPdf->close();
        }
    } catch (Throwable $pdfEx) {
        logMessage("  PDF konverzija nije uspela: " . $pdfEx->getMessage(), 'WARN');
    }

    // Parsiranje
    try {
        $stmtFk = $db->prepare('SELECT opstina_id, grad_id FROM rik_fajlovi WHERE id=?');
        $stmtFk->bind_param('i', $fajlId);
        $stmtFk->execute();
        $fk = $stmtFk->get_result()->fetch_assoc();
        $stmtFk->close();

        $count = $parser->parse(
            $parseAbsPath,
            $fajlId,
            $verzijaId,
            $fk['opstina_id'] ?? null,
            $fk['grad_id']    ?? null
        );
        logMessage("  Parsovano {$count} glasačkih mesta za: {$naziv}", 'INFO');
    } catch (Throwable $e) {
        logMessage("  Parsiranje nije uspelo: " . $e->getMessage(), 'ERROR');
        $scraper->setError($fajlId, 'Parser: ' . $e->getMessage());
        $stats['errors']++;
    }
}
