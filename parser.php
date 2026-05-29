<?php
/**
 * parser.php
 * Klasa GmParser: parsira .docx fajlove glasačkih mesta (PHPWord)
 * i upisuje rezultate u bazu (glasacka_mesta + glasacka_mesta_podrucja).
 *
 * Struktura Word tabele:
 *   Kolona 0: БРОЈ ГМ
 *   Kolona 1: НАЗИВ ГЛАСАЧКОГ МЕСТА
 *   Kolona 2: АДРЕСА ГЛАСАЧКОГ МЕСТА
 *   Kolona 3: ПОДРУЧЈЕ КОЈЕ ОБУХВАТА ГЛАСАЧКО МЕСТО
 *
 * Sve vrednosti se konvertuju u latinicu pre upisa.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// PHPWord autoload – composer
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    throw new RuntimeException(
        'PHPWord nije instaliran. Pokrenite: composer install'
    );
}
require_once $autoload;

use PhpOffice\PhpWord\IOFactory;

class GmParser
{
    private mysqli $db;

    /**
     * Index kolona u Word tabeli (0-based).
     * Može varirati – auto-detekcija na osnovu header reda.
     */
    private array $colIndex = [
        'broj_gm'  => 0,
        'naziv'    => 1,
        'adresa'   => 2,
        'podrucje' => 3,
    ];

    public function __construct()
    {
        $this->db = getDB();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Javni API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parsira .docx fajl i upisuje GM u bazu.
     *
     * @param  string $docxPath   Apsolutna putanja do .docx fajla
     * @param  int    $fajlId     ID rik_fajlovi reda
     * @param  int    $verzijaId  ID rik_fajlovi_verzije reda (za oznaku parsed=1)
     * @param  int|null $opstinaId FK opštine (može biti null)
     * @param  int|null $gradId    FK grada
     * @return int Broj parsovanih GM
     */
    public function parse(
        string $filePath,
        int    $fajlId,
        int    $verzijaId,
        ?int   $opstinaId = null,
        ?int   $gradId    = null
    ): int {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Fajl ne postoji: {$filePath}");
        }

        $rows = $this->extractRows($filePath);
        if (empty($rows)) {
            logMessage("Parser: nema podataka u fajlu {$filePath}", 'WARN');
            return 0;
        }

        $count = 0;
        $this->db->begin_transaction();

        try {
            foreach ($rows as $row) {
                if (empty($row['broj_gm'])) continue;

                $gmId = $this->upsertGm($row, $fajlId, $opstinaId, $gradId);
                $this->parsePodrucja($gmId, $row['podrucje']);
                $count++;
            }

            // Markiraj verziju kao parsovanu
            $stmt = $this->db->prepare(
                'UPDATE rik_fajlovi_verzije SET parsed=1, parse_error=NULL WHERE id=?'
            );
            $stmt->bind_param('i', $verzijaId);
            $stmt->execute();
            $stmt->close();

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            $err = $e->getMessage();
            // Snimi parse_error
            $stmt = $this->db->prepare(
                'UPDATE rik_fajlovi_verzije SET parse_error=? WHERE id=?'
            );
            $stmt->bind_param('si', $err, $verzijaId);
            $stmt->execute();
            $stmt->close();
            throw $e;
        }

        return $count;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private – ekstrakcija redova iz .doc/.docx
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array<int, array{broj_gm:string, naziv:string, adresa:string, podrucje:string}>
     */
    private function extractRows(string $path): array
    {
        try {
            // .doc se čita direktno MsDoc readerom — bez međufajla da se ne bi
            // izgubile tabele pri roundtrip-u kroz Word2007 writer.
            $ext        = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $readerType = ($ext === 'doc') ? 'MsDoc' : 'Word2007';
            $phpWord    = IOFactory::load($path, $readerType);
        } catch (Throwable $e) {
            throw new RuntimeException("PHPWord ne može da učita fajl: " . $e->getMessage());
        }

        $allRows = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                    $tableRows = $this->parseTable($element);
                    if (!empty($tableRows)) {
                        $allRows = array_merge($allRows, $tableRows);
                    }
                }
            }
        }

        return $allRows;
    }

    /**
     * @return array<int, array{broj_gm:string, naziv:string, adresa:string, podrucje:string}>
     */
    private function parseTable(\PhpOffice\PhpWord\Element\Table $table): array
    {
        $rows   = $table->getRows();
        $result = [];

        // Resetujemo kolIndex na default
        $this->colIndex = ['broj_gm' => 0, 'naziv' => 1, 'adresa' => 2, 'podrucje' => 3];

        foreach ($rows as $rowIdx => $row) {
            $cells     = $row->getCells();
            $cellCount = count($cells);

            if ($cellCount < 2) continue;

            // Izvuci tekst iz svake ćelije
            $cellTexts = [];
            foreach ($cells as $cell) {
                $cellTexts[] = $this->extractCellText($cell);
            }

            // Header red – prepoznaj i ažuriraj mapiranje kolona.
            // Row 0 se tretira kao header SAMO ako zaista izgleda kao header;
            // inače se obrađuje kao podatak.
            if ($this->isHeaderRow($cellTexts)) {
                $this->detectColumnOrder($cellTexts);
                continue;
            }

            // Preskočiti prazne redove
            $flatText = implode('', $cellTexts);
            if (empty(trim($flatText))) continue;

            $get = function (string $key) use ($cellTexts): string {
                $idx = $this->colIndex[$key] ?? null;
                return ($idx !== null && isset($cellTexts[$idx])) ? trim($cellTexts[$idx]) : '';
            };

            $brojGm  = latinize($get('broj_gm'));
            $naziv   = capitalizeWords($this->cleanText(latinize($get('naziv'))));
            $adresa  = capitalizeWords($this->cleanText(latinize($get('adresa'))));
            $podrucje = latinize($get('podrucje'));

            // Preskoči ako nema ni broja ni naziva
            if (empty($brojGm) && empty($naziv)) continue;

            $result[] = [
                'broj_gm'  => $this->cleanGmBroj($brojGm),
                'naziv'    => $naziv,
                'adresa'   => $adresa,
                'podrucje' => $podrucje,
            ];
        }

        return $result;
    }

    /**
     * Izvlači ceo tekst iz ćelije (sve pasuse, SVE runs).
     * Pasusi se spajaju razmakom (ne newline) kako bi se izbeglo
     * upisivanje newline karaktera u vrednosti u bazi.
     */
    private function extractCellText(\PhpOffice\PhpWord\Element\Cell $cell): string
    {
        $parts = [];
        foreach ($cell->getElements() as $elem) {
            if ($elem instanceof \PhpOffice\PhpWord\Element\TextRun ||
                $elem instanceof \PhpOffice\PhpWord\Element\Paragraph) {
                $line = $this->extractTextRunText($elem);
                if ($line !== '') $parts[] = $line;
            } elseif ($elem instanceof \PhpOffice\PhpWord\Element\Text) {
                $t = trim((string)$elem->getText());
                if ($t !== '') $parts[] = $t;
            }
        }
        // Koristi \n kao separator između paragrafa – čuva granice redova u PODRUČJE koloni
        // cleanText() će \n → razmak za naziv/adresa; parsePodrucja() deli po \n
        return implode("\n", $parts);
    }

    private function extractTextRunText($elem): string
    {
        $parts = [];
        if (!method_exists($elem, 'getElements')) return '';
        foreach ($elem->getElements() as $child) {
            if ($child instanceof \PhpOffice\PhpWord\Element\Text) {
                $t = trim((string)$child->getText());
                if ($t !== '') $parts[] = $t;
            } elseif ($child instanceof \PhpOffice\PhpWord\Element\TextRun) {
                $t = $this->extractTextRunText($child);
                if ($t !== '') $parts[] = $t;
            }
        }
        return implode(' ', $parts);
    }

    /**
     * Proverava da li je red header (sadrži "BROJ", "NAZIV" i "ADRESA").
     * Zahteva SVA tri ključna polja kako bi se smanjio broj false pozitiva
     * (npr. GM red koji slučajno sadrži reč "naziv" ili "adresa").
     */
    private function isHeaderRow(array $cellTexts): bool
    {
        $joined = normalizeName(latinize(implode(' ', $cellTexts)));
        // Prihvati i skraćenicu "br." (česta u zaglavlju: "BR. GM") pored pune reči "broj"
        $hasBroj   = str_contains($joined, 'broj') || preg_match('/\bbr\./', $joined);
        $hasNaziv  = str_contains($joined, 'naziv');
        $hasAdresa = str_contains($joined, 'adresa');
        return $hasBroj && ($hasNaziv || $hasAdresa);
    }

    /**
     * Auto-detekcija redosled kolona iz header reda.
     */
    private function detectColumnOrder(array $cellTexts): void
    {
        foreach ($cellTexts as $idx => $text) {
            $norm = normalizeName(latinize($text));
            // Prioritetni uslovi: specifičniji pre opštijih.
            // Isključuje ćelije koje sadrže ključne reči područja (npr. "кућни број" u naslovu kolone 3).
            $isAreaCell = str_contains($norm, 'podrucj') || str_contains($norm, 'obuhvat') ||
                          str_contains($norm, 'ulica') || str_contains($norm, 'naselje');
            if (!$isAreaCell &&
                (str_contains($norm, 'br.') || str_contains($norm, 'broj gm') ||
                 (preg_match('/\bbr(oj)?\b/', $norm) && !str_contains($norm, 'naziv')))) {
                $this->colIndex['broj_gm'] = $idx;
            } elseif (str_contains($norm, 'naziv')) {
                $this->colIndex['naziv'] = $idx;
            } elseif (str_contains($norm, 'adresa')) {
                $this->colIndex['adresa'] = $idx;
            } elseif (str_contains($norm, 'podrucj') || str_contains($norm, 'obuhat') ||
                      str_contains($norm, 'podrucje') || str_contains($norm, 'naselja')) {
                $this->colIndex['podrucje'] = $idx;
            }
        }
    }

    /**
     * Uklanja HTML entitete, tipografske navodnike i tačku-zarez sa teksta ćelije.
     * Normalizuje višestruke razmake (nastale iz multi-line ćelija).
     */
    private function cleanText(string $s): string
    {
        // Decode HTML entities (npr. &quot; → ", &amp; → & itd.)
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Normalizuj razmake / newline-ove koji dolaze iz višerednih ćelija
        $s = preg_replace('/[\r\n\t]+/', ' ', $s);
        $s = preg_replace('/\s{2,}/', ' ', $s);
        // Normalizuj SVE tipografske navodnike u standardni ASCII "
        // – čuva zatvarajući navodnik u imenima npr. OŠ "Vasa Stajić"
        $s = preg_replace('/[\x{201C}\x{201D}\x{201E}\x{2018}\x{2019}\x{00AB}\x{00BB}]/u', '"', $s);
        // Ukloni tačku-zarez sa ivica (Word artefakti)
        $s = trim($s, ';');
        // Ukloni lone " (jedini u stringu) sa ivica – samo artefakt, ne par navodnika
        if (substr_count($s, '"') === 1) {
            $s = trim($s, '"');
        }
        return trim($s);
    }

    /**
     * Čisti broj GM i validira da je kratki numerički identifikator (1–4 cifre, opcioni sufiks).
     * Vraća prazan string ako vrednost izgleda kao adresa/naziv (spašava od krive mape kolona).
     */
    private function cleanGmBroj(string $s): string
    {
        $clean = preg_replace('/\s+/', '', trim($s));
        // Ukloni tačku na kraju (čest ordinal format u Word tabelama: "1.", "2." itd.)
        $clean = rtrim($clean, '.');
        // Prihvati: "12", "5", "123a", "42B" – max 6 znakova, samo cifre + opciona slova
        if ($clean === '' || !preg_match('/^\d{1,4}[a-zA-Z]?$/', $clean)) {
            return '';
        }
        return $clean;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private – DB operacije
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * INSERT ili UPDATE glasacka_mesta.
     * Matchuje po fajl_id + broj_gm.
     */
    private function upsertGm(
        array $row,
        int   $fajlId,
        ?int  $opstinaId,
        ?int  $gradId
    ): int {
        $stmt = $this->db->prepare(
            'SELECT id FROM glasacka_mesta WHERE fajl_id=? AND broj_gm=? LIMIT 1'
        );
        $stmt->bind_param('is', $fajlId, $row['broj_gm']);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $stmt = $this->db->prepare(
                'UPDATE glasacka_mesta
                 SET naziv=?, adresa=?,
                     opstina_id=?, grad_id=?, updated_at=NOW()
                 WHERE id=?'
            );
            $stmt->bind_param(
                'sssii',
                $row['naziv'], $row['adresa'],
                $opstinaId, $gradId, $existing['id']
            );
            $stmt->execute();
            $stmt->close();
            return (int)$existing['id'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO glasacka_mesta
             (fajl_id, broj_gm, naziv, adresa, opstina_id, grad_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'isssii',
            $fajlId, $row['broj_gm'], $row['naziv'],
            $row['adresa'], $opstinaId, $gradId
        );
        $stmt->execute();
        $id = (int)$this->db->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Parsira područje string i kreira stavke u glasacka_mesta_podrucja.
     * Svaka adresa-broj kombinacija = jedan zapis. Koristi tokenizeAddressString()
     * za razdvajanje ulice od kućnog broja.
     */
    private function parsePodrucja(int $gmId, string $podrucje): void
    {
        if (empty(trim($podrucje))) return;

        // Obriši stara područja
        $stmt = $this->db->prepare('DELETE FROM glasacka_mesta_podrucja WHERE gm_id=?');
        $stmt->bind_param('i', $gmId);
        $stmt->execute();
        $stmt->close();

        // Split po ; ili newline – svaka linija = jedan "blok" ulice sa brojevima
        $lines = preg_split('/[;\n]+/', $podrucje);
        foreach ($lines as $line) {
            $line = trim(latinize($line));
            if (empty($line) || mb_strlen($line) < 2) continue;

            // Ukloni strukturne labele iz Word dokumenata:
            // "Ulica : Suvoborska" → "Suvoborska"
            // "Ul. : Cara Lazara" → "Cara Lazara"
            $line = preg_replace('/^(Ulica|Ul)\.?\s*:?\s*/iu', '', trim($line));
            // " Brojevi" / " Br." kao sufiks ulice (nastaje spajanjem text runova)
            $line = preg_replace('/\s+(Brojevi|Br\.?)\s*$/iu', '', $line);
            // Preskoči linije koje su samo labele: "Brojevi", "Br", "Br."
            if (preg_match('/^(Brojevi|Br\.?)\s*$/iu', $line)) continue;

            $line = trim($line);
            if (empty($line) || mb_strlen($line) < 2) continue;

            $tokens = $this->tokenizeAddressString($line);
            foreach ($tokens as $tok) {
                $naziv      = trim($tok['naziv']);
                $brojAdrese = $tok['broj_adrese'] !== '' ? $tok['broj_adrese'] : null;
                if ($naziv === '') continue;

                $stmt = $this->db->prepare(
                    'INSERT INTO glasacka_mesta_podrucja (gm_id, naziv, broj_adrese) VALUES (?, ?, ?)'
                );
                $stmt->bind_param('iss', $gmId, $naziv, $brojAdrese);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    /**
     * Tokenizuje string adresa u parove {naziv, broj_adrese}.
     *
     * Primer ulaza:
     *   "Lazara Jankovica 2,3,4,6, 9,10,12, Milosava Ivkovica 1,5,6,7-A"
     *
     * Rezultat:
     *   [{naziv:"Lazara Jankovica", broj_adrese:"2"}, {naziv:"Lazara Jankovica", broj_adrese:"3"}, ...]
     *
     * Algoritam:
     *   1. Normalizuj – ukloni prelome linija koji nastaju usled word-wrap-a (word-\nA → word-A)
     *   2. Split po zarezu → tokeni
     *   3. State machine: token koji počinje cifrom ili je "bb" = broj za tekuću ulicu
     *                     token koji počinje slovom = nova ulica (izvuci trailing broj ako postoji)
     *   4. "i" / "и" kao terminal token (kraj ulice bez broja → flush samo naziv)
     *
     * @return array<int, array{naziv: string, broj_adrese: string}>
     */
    private function tokenizeAddressString(string $s): array
    {
        // Normalizuj: ukloni hard-wrapped prelome (npr. "18-\nA" → "18-A")
        $s = preg_replace('/[\r\n]+/', ' ', $s);
        // Spoji ostatke word-wrapa: cifra/slovo-crtica-razmak-slovo/cifra npr. "18- A" → "18-A"
        $s = preg_replace('/([A-Za-z0-9])-\s+([A-Za-z0-9])/', '$1-$2', $s);
        $s = preg_replace('/\s{2,}/', ' ', trim($s));

        if ($s === '') return [];

        // Split po zarezu
        $rawTokens = preg_split('/\s*,\s*/', $s);

        $result       = [];
        $currentStreet = '';

        foreach ($rawTokens as $tok) {
            $tok = trim($tok);
            if ($tok === '') continue;

            // Separator "i" / "и" – kraj tekuće ulice bez konkretnog broja
            if (preg_match('/^(i|и)$/iu', $tok)) {
                // flush bez broja (naziv-only red)
                if ($currentStreet !== '') {
                    $result[] = ['naziv' => capitalizeWords($currentStreet), 'broj_adrese' => ''];
                    $currentStreet = '';
                }
                continue;
            }

            // Token koji počinje cifrom ili je "bb" / "0" → kućni broj za tekuću ulicu
            if (preg_match('/^\d/u', $tok) || preg_match('/^bb$/iu', $tok) || $tok === '0') {
                $result[] = [
                    'naziv'       => $currentStreet !== '' ? capitalizeWords($currentStreet) : '?',
                    'broj_adrese' => $tok,
                ];
                continue;
            }

            // Token počinje slovom → nova ulica
            // Pokušaj izvući trailing broj iz naziva (npr. "Svetogorska 12" ili "Ul. Cara Lazara 5-A")
            if (preg_match('/^(.*?)\s+(\d[\w\-]*)$/u', $tok, $m)) {
                $newStreet = trim($m[1]);
                $number    = trim($m[2]);
                // Provera da je levi deo stvarno naziv (sadrži bar jedno slovo)
                if (preg_match('/[A-Za-zА-Яа-яЂЉЊЋЏђљњћџА-ЩЪЫЬЭЮЯa-zа-я]/u', $newStreet)) {
                    // Flush prethodne ulice ako nema nijednog upisa za nju
                    if ($currentStreet !== '') {
                        $norm = capitalizeWords($currentStreet);
                        if (!array_filter($result, fn($r) => $r['naziv'] === $norm)) {
                            $result[] = ['naziv' => $norm, 'broj_adrese' => ''];
                        }
                    }
                    $currentStreet = $newStreet;
                    $result[] = [
                        'naziv'       => capitalizeWords($currentStreet),
                        'broj_adrese' => $number,
                    ];
                    continue;
                }
            }

            // Čist naziv bez broja – nova ulica / lokalitet
            if ($currentStreet !== '') {
                $norm = capitalizeWords($currentStreet);
                if (!array_filter($result, fn($r) => $r['naziv'] === $norm)) {
                    $result[] = ['naziv' => $norm, 'broj_adrese' => ''];
                }
            }
            $currentStreet = $tok;
        }

        // Ako je ostao naziv bez ijednog broja – upiši ga
        if ($currentStreet !== '') {
            $hasMatchForStreet = false;
            foreach ($result as $r) {
                if ($r['naziv'] === capitalizeWords($currentStreet)) {
                    $hasMatchForStreet = true;
                    break;
                }
            }
            if (!$hasMatchForStreet) {
                $result[] = ['naziv' => capitalizeWords($currentStreet), 'broj_adrese' => ''];
            }
        }

        return $result;
    }

    /**
     * Pokušava da parsira adresu u komponente.
     * Heuristički, nije savršen — za precizno parsiranje treba manuelna provera.
     *
     * @return array{string, string, string} [ulica, broj, naselje]
     */
    private function parseAddressLine(string $line): array
    {
        // Primeri:
        //   "Vojvode Misica 12, Beograd"
        //   "Cara Lazara bb"
        //   "MZ Stari grad - ul. Svetogorska 12-24"

        $ulica   = '';
        $broj    = '';
        $naselje = '';

        // Pokušaj split po poslednjem zarezu: desno = naselje
        if (str_contains($line, ',')) {
            $pos     = strrpos($line, ',');
            $left    = trim(substr($line, 0, $pos));
            $naselje = trim(substr($line, $pos + 1));
            $line    = $left;
        }

        // U levom delu: poslednja "reč" koja počinje cifrom ili "bb" = broj
        if (preg_match('/^(.*?)\s+(\d[\w\/\-]*)$/', $line, $m)) {
            $ulica = trim($m[1]);
            $broj  = trim($m[2]);
        } elseif (preg_match('/^(.*?)\s+(bb)$/i', $line, $m)) {
            $ulica = trim($m[1]);
            $broj  = 'bb';
        } else {
            $ulica = $line;
        }

        return [$ulica, $broj, $naselje];
    }
}
