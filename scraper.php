<?php
/**
 * scraper.php
 * Klasa RikScraper:
 *   – preuzima listing stranicu RIK-a
 *   – parsira sve linkove na fajlove opština
 *   – preuzima fajlove (2s delay između downloada)
 *   – čuva fajlove lokalno na disku
 *   – matchuje opštine/gradove sa FK u bazi
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class RikScraper
{
    private const LISTING_URL = 'https://www.rik.parlament.gov.rs/tekst/sr/12021/glasacka-mesta.php';
    private const USER_AGENT  = 'Mozilla/5.0 (compatible; GlasackaMestaMonitor/1.0)';
    private const DOWNLOAD_DELAY_SEC = 2;

    private mysqli $db;

    /** Keš normalizovanih naziva iz baze */
    private array $opstineMap = [];
    private array $gradoviMap = [];

    public function __construct()
    {
        $this->db = getDB();
        $this->buildNameMaps();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Javni API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Preuzima listing stranicu i vraća parsed listu fajlova.
     *
     * @return array<int, array{naziv: string, naziv_clean: string, url: string, tip: string}>
     */
    public function fetchList(): array
    {
        $html = $this->curlGet(self::LISTING_URL);
        if ($html === false) {
            throw new RuntimeException('Nije moguće preuzeti listing stranicu RIK-a.');
        }
        return $this->parseListingHtml($html);
    }

    /**
     * Preuzima jedan fajl i čuva ga na disk.
     * Dodaje 2s delay pre downloada (pozvati samo iz petlje).
     *
     * @param string $url    URL fajla
     * @param string $slug   Slug opštine (za direktorijum)
     * @param bool   $delay  Dodati sleep pre downloada (default true)
     * @return array{path: string, ext: string, hash: string, size: int}|null null ako download nije uspeo
     */
    public function downloadFile(string $url, string $slug, bool $delay = true): ?array
    {
        if ($delay) {
            sleep(self::DOWNLOAD_DELAY_SEC);
        }

        $content = $this->curlGet($url);
        if ($content === false || strlen($content) < 100) {
            logMessage("Download failed: {$url}", 'ERROR');
            return null;
        }

        $ext  = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, ['doc', 'docx'], true)) {
            $ext = 'doc'; // fallback
        }

        $hash    = hash('sha256', $content);
        $dir     = storagePath('fajlovi', $slug);
        ensureDir($dir);

        $fileName = date('Y-m-d') . '_' . substr($hash, 0, 12) . '.' . $ext;
        $filePath = $dir . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($filePath)) {
            file_put_contents($filePath, $content);
        }

        return [
            'path' => 'storage/fajlovi/' . $slug . '/' . $fileName,
            'ext'  => $ext,
            'hash' => $hash,
            'size' => strlen($content),
        ];
    }

    /**
     * Upisuje ili ažurira rik_fajlovi red u bazu.
     * Vraća ID reda.
     */
    public function upsertFajl(array $item): int
    {
        $opstinaId = $this->resolveOpstina($item['naziv_clean']);
        $gradId    = $opstinaId === null ? $this->resolveGrad($item['naziv_clean']) : null;

        $stmt = $this->db->prepare(
            'SELECT id FROM rik_fajlovi WHERE url = ?'
        );
        $stmt->bind_param('s', $item['url']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            // Ažuriraj naziv i FK (mogu se promeniti)
            $stmt = $this->db->prepare(
                'UPDATE rik_fajlovi SET naziv=?, naziv_clean=?, tip=?, opstina_id=?, grad_id=?, aktivan=1, updated_at=NOW()
                 WHERE id=?'
            );
            $stmt->bind_param('sssiii',
                $item['naziv'], $item['naziv_clean'], $item['tip'], $opstinaId, $gradId, $row['id']
            );
            $stmt->execute();
            $stmt->close();
            return (int)$row['id'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO rik_fajlovi (naziv, naziv_clean, url, opstina_id, grad_id, tip)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $url = $item['url'];
        $stmt->bind_param('sssiis',
            $item['naziv'], $item['naziv_clean'], $url, $opstinaId, $gradId, $item['tip']
        );
        $stmt->execute();
        $id = (int)$this->db->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Vraća poslednju verziju fajla iz baze (hash).
     * Vraća null ako fajl još nema ni jednu verziju.
     */
    public function getLastVersionHash(int $fajlId): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT file_hash FROM rik_fajlovi_verzije
             WHERE fajl_id = ?
             ORDER BY detected_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->bind_param('i', $fajlId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $row['file_hash'] : null;
    }

    /**
     * Upisuje novu verziju u bazu.
     * Vraća ID nove verzije.
     */
    public function insertVersion(int $fajlId, array $fileInfo): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO rik_fajlovi_verzije (fajl_id, file_hash, file_path, file_ext, file_size)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssi',
            $fajlId, $fileInfo['hash'], $fileInfo['path'], $fileInfo['ext'], $fileInfo['size']
        );
        $stmt->execute();
        $id = (int)$this->db->insert_id;
        $stmt->close();

        // Setuj has_changes flag i word_hash na fajlu
        $stmt = $this->db->prepare(
            'UPDATE rik_fajlovi SET has_changes=1, word_hash=?, last_error=NULL, updated_at=NOW() WHERE id=?'
        );
        $stmt->bind_param('si', $fileInfo['hash'], $fajlId);
        $stmt->execute();
        $stmt->close();

        return $id;
    }

    /**
     * Upisuje last_error na rik_fajlovi red.
     */
    public function setError(int $fajlId, string $error): void
    {
        $stmt = $this->db->prepare(
            'UPDATE rik_fajlovi SET last_error=?, updated_at=NOW() WHERE id=?'
        );
        $stmt->bind_param('si', $error, $fajlId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Upisuje napomenu na rik_fajlovi red (za amandman/izmenu fajlove).
     */
    public function setNapomena(int $fajlId, string $napomena): void
    {
        $stmt = $this->db->prepare(
            'UPDATE rik_fajlovi SET napomena=?, updated_at=NOW() WHERE id=?'
        );
        $stmt->bind_param('si', $napomena, $fajlId);
        $stmt->execute();
        $stmt->close();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parsira HTML listinga i vraća array stavki.
     */
    private function parseListingHtml(string $html): array
    {
        $items = [];
        $seen  = [];

        // Pronađi sve <a> tagove koji sadrže /extfile/ u href-u.
        // Naziv opštine je u title="" atributu, NE u tekstu linka
        // (unutrašnjost je samo <span class="download-btn ..."></span>).
        preg_match_all('/<a([^>]+\/extfile\/[^>]+)>/is', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $attrs = $m[1];

            if (!preg_match('/href=["\']([^"\']+)["\']/i', $attrs, $hm)) continue;
            $url = trim(html_entity_decode($hm[1], ENT_QUOTES, 'UTF-8'));

            // Naziv iz title="..." atributa
            if (!preg_match('/title=["\']([^"\']+)["\']/i', $attrs, $tm)) continue;
            $naziv = trim(html_entity_decode($tm[1], ENT_QUOTES, 'UTF-8'));
            $naziv = latinize($naziv);
            $naziv = preg_replace('/\s+/', ' ', $naziv);

            if (empty($url) || empty($naziv)) continue;

            // Relativni URL → apsolutni
            if (!str_starts_with($url, 'http')) {
                $url = 'https://www.rik.parlament.gov.rs' . $url;
            }

            // Enkoduj razmake i ne-ASCII znakove (ćirilica) u putanji URL-a
            $parsedUrl = parse_url($url);
            if (!empty($parsedUrl['path'])) {
                $encodedPath = implode('/', array_map(
                    fn($seg) => rawurlencode(rawurldecode($seg)),
                    explode('/', $parsedUrl['path'])
                ));
                $url = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $encodedPath;
            }

            $urlHash = hash('sha256', $url);
            if (isset($seen[$urlHash])) continue;
            $seen[$urlHash] = true;

            $nazivClean = latinize(extractCleanName($naziv));
            $tip        = detectTip($naziv);

            $items[] = [
                'naziv'       => $naziv,
                'naziv_clean' => $nazivClean,
                'url'         => $url,
                'tip'         => $tip,
            ];
        }

        return $items;
    }

    /**
     * Puni lokalne mape za matching sa opstine/gradovi.
     */
    private function buildNameMaps(): void
    {
        $res = $this->db->query('SELECT id, name FROM opstine');
        while ($row = $res->fetch_assoc()) {
            $key = normalizeName(latinize($row['name']));
            $this->opstineMap[$key] = (int)$row['id'];
        }

        $res = $this->db->query('SELECT id, name FROM gradovi');
        while ($row = $res->fetch_assoc()) {
            $key = normalizeName(latinize($row['name']));
            $this->gradoviMap[$key] = (int)$row['id'];
        }
    }

    /**
     * Pronalazi ID opštine na osnovu čistog naziva.
     * Podržava i format "Grad - Opština" (npr. "Beograd - Barajevo").
     */
    private function resolveOpstina(string $nazivClean): ?int
    {
        $key = normalizeName($nazivClean);
        if (isset($this->opstineMap[$key])) return $this->opstineMap[$key];

        // Fallback za format "Grad - Opština" – probaj deo posle " - "
        if (str_contains($nazivClean, ' - ')) {
            $parts  = explode(' - ', $nazivClean, 2);
            $suffix = normalizeName(trim($parts[1]));
            if (isset($this->opstineMap[$suffix])) return $this->opstineMap[$suffix];
        }

        return null;
    }

    /**
     * Pronalazi ID grada na osnovu čistog naziva.
     * Podržava i format "Grad - Opština" (npr. "Beograd - Barajevo").
     */
    private function resolveGrad(string $nazivClean): ?int
    {
        $key = normalizeName($nazivClean);
        if (isset($this->gradoviMap[$key])) return $this->gradoviMap[$key];

        // Fallback za format "Grad - Opština" – probaj deo posle " - "
        if (str_contains($nazivClean, ' - ')) {
            $parts  = explode(' - ', $nazivClean, 2);
            $suffix = normalizeName(trim($parts[1]));
            if (isset($this->gradoviMap[$suffix])) return $this->gradoviMap[$suffix];
        }

        return null;
    }

    /**
     * Izvršava HTTP GET zahtev putem cURL.
     * Vraća string sadržaj ili false na grešku.
     */
    private function curlGet(string $url): string|false
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false, // RIK može imati self-signed cert
            CURLOPT_ENCODING       => '',     // prihvati gzip/deflate
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return false;
        }
        return $response;
    }
}
