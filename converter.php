<?php
/**
 * converter.php
 * DocConverter: konvertuje Word fajlove putem LibreOffice headless.
 *   – convert()       .doc  → .docx   (za PHPWord parsing)
 *   – convertToPdf()  .doc/.docx → .pdf (nekritično – za browser prikaz)
 *
 * Svi proc_open pozivi MORAJU koristiti array-form + ['bypass_shell' => true].
 */

require_once __DIR__ . '/helpers.php';

class DocConverter
{
    /** Keš pronađene putanje do soffice; '' znači nije pronađen */
    private static string $_soffice = '';
    private static bool   $_searched = false;

    // ─────────────────────────────────────────────────────────────────────────
    // Pronalaženje LibreOffice-a
    // ─────────────────────────────────────────────────────────────────────────

    private static function findSoffice(): ?string
    {
        if (self::$_searched) {
            return self::$_soffice !== '' ? self::$_soffice : null;
        }
        self::$_searched = true;

        $candidates = [
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files\\LibreOffice 7\\program\\soffice.exe',
            'C:\\Program Files\\LibreOffice 6\\program\\soffice.exe',
            '/usr/bin/soffice',
            '/usr/local/bin/soffice',
            '/usr/lib/libreoffice/program/soffice',
        ];

        foreach ($candidates as $c) {
            if (is_file($c)) {
                self::$_soffice = $c;
                return $c;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Javni API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Konvertuje .doc fajl u .docx koristeći LibreOffice headless.
     * Ako je fajl već .docx, vraća originalnu putanju bez konverzije.
     *
     * @param  string $docPath  Apsolutna putanja do .doc fajla
     * @return string|null      Apsolutna putanja do .docx, ili null na grešku
     */
    public static function convert(string $docPath): ?string
    {
        $ext = strtolower(pathinfo($docPath, PATHINFO_EXTENSION));
        if ($ext === 'docx') {
            return $docPath; // već docx, nema šta da se radi
        }

        $soffice = self::findSoffice();
        if ($soffice === null) {
            logMessage('LibreOffice nije pronađen – konverzija doc→docx preskočena', 'WARN');
            return null;
        }

        $outDir  = dirname($docPath);
        $cmd     = [$soffice, '--headless', '--convert-to', 'docx', '--outdir', $outDir, $docPath];
        $desc    = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($cmd, $desc, $pipes, $outDir, null, ['bypass_shell' => true]);

        if (!is_resource($process)) {
            logMessage('proc_open za LibreOffice (doc→docx) nije uspeo', 'WARN');
            return null;
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $docxPath = $outDir . DIRECTORY_SEPARATOR . pathinfo($docPath, PATHINFO_FILENAME) . '.docx';
        if (!file_exists($docxPath)) {
            logMessage('LibreOffice doc→docx nije uspeo (exit:' . $exitCode . '): ' . trim($stderr), 'WARN');
            return null;
        }

        logMessage('  Konverzija doc→docx: ' . basename($docxPath), 'INFO');
        return $docxPath;
    }

    /**
     * Konvertuje .doc/.docx fajl u .pdf koristeći LibreOffice headless.
     * Ova konverzija je NEKRITIČNA – neuspeh se loguje kao WARN.
     *
     * @param  string $inputPath  Apsolutna putanja do .doc/.docx fajla
     * @return string|null        Apsolutna putanja do .pdf, ili null na grešku
     */
    public static function convertToPdf(string $inputPath): ?string
    {
        $soffice = self::findSoffice();
        if ($soffice === null) {
            logMessage('LibreOffice nije pronađen – PDF konverzija preskočena', 'WARN');
            return null;
        }

        $outDir  = dirname($inputPath);
        $cmd     = [$soffice, '--headless', '--convert-to', 'pdf', '--outdir', $outDir, $inputPath];
        $desc    = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($cmd, $desc, $pipes, $outDir, null, ['bypass_shell' => true]);

        if (!is_resource($process)) {
            logMessage('proc_open za LibreOffice (→pdf) nije uspeo', 'WARN');
            return null;
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $pdfPath = $outDir . DIRECTORY_SEPARATOR . pathinfo($inputPath, PATHINFO_FILENAME) . '.pdf';
        if (!file_exists($pdfPath)) {
            logMessage('LibreOffice →pdf nije uspeo (exit:' . $exitCode . '): ' . trim($stderr), 'WARN');
            return null;
        }

        logMessage('  PDF kreiran: ' . basename($pdfPath), 'INFO');
        return $pdfPath;
    }
}
