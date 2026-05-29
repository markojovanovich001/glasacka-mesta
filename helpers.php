<?php
/**
 * helpers.php
 * Pomoćne funkcije za normalizaciju teksta.
 * Sve vrednosti koje ulaze u bazu ili se koriste za poređenje
 * moraju proći kroz latinize() a opciono i normalizeName().
 */

/**
 * Kapitalizuje prvu slovo svake reči (Title Case, UTF-8).
 * Koristi se pred upisom naziv/adresa u bazu radi konzistentnosti.
 * Primer: "OSNOVNA SKOLA VASA PELAGIC" → "Osnovna Škola Vasa Pelagić"
 */
function capitalizeWords(string $s): string
{
    return mb_convert_case(mb_strtolower($s, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

/**
 * Mapa ćirilice na latinicu (srpski jezik).
 */
const CYRILLIC_TO_LATIN = [
    'А' => 'A',  'Б' => 'B',  'В' => 'V',  'Г' => 'G',  'Д' => 'D',
    'Ђ' => 'Đ',  'Е' => 'E',  'Ж' => 'Ž',  'З' => 'Z',  'И' => 'I',
    'Ј' => 'J',  'К' => 'K',  'Л' => 'L',  'Љ' => 'Lj', 'М' => 'M',
    'Н' => 'N',  'Њ' => 'Nj', 'О' => 'O',  'П' => 'P',  'Р' => 'R',
    'С' => 'S',  'Т' => 'T',  'Ћ' => 'Ć',  'У' => 'U',  'Ф' => 'F',
    'Х' => 'H',  'Ц' => 'C',  'Ч' => 'Č',  'Џ' => 'Dž', 'Ш' => 'Š',
    'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'g',  'д' => 'd',
    'ђ' => 'đ',  'е' => 'e',  'ж' => 'ž',  'з' => 'z',  'и' => 'i',
    'ј' => 'j',  'к' => 'k',  'л' => 'l',  'љ' => 'lj', 'м' => 'm',
    'н' => 'n',  'њ' => 'nj', 'о' => 'o',  'п' => 'p',  'р' => 'r',
    'с' => 's',  'т' => 't',  'ћ' => 'ć',  'у' => 'u',  'ф' => 'f',
    'х' => 'h',  'ц' => 'c',  'ч' => 'č',  'џ' => 'dž', 'ш' => 'š',
];

/**
 * Konvertuje ćirilični tekst u latinicu.
 * Ako je već latinica, vraća nepromenjen string.
 */
function latinize(string $s): string
{
    return strtr($s, CYRILLIC_TO_LATIN);
}

/**
 * Uklanja dijakritike sa latiničnih slova (za poređenje/matching).
 * đ→d, š→s, č→c, ć→c, ž→z itd.
 * NE menja zapis u bazi — samo za internu normalizaciju pri FK matching-u.
 */
function stripDiacritics(string $s): string
{
    $from = ['Š','š','Đ','đ','Č','č','Ć','ć','Ž','ž','Lj','lj','Nj','nj','Dž','dž'];
    $to   = ['S','s','D','d','C','c','C','c','Z','z','Lj','lj','Nj','nj','Dz','dz'];
    return str_replace($from, $to, $s);
}

/**
 * Normalizuje ime za FK matching:
 * 1. latinize (ćirilica → latinica)
 * 2. mb_strtolower
 * 3. stripDiacritics
 * 4. trim višestruke razmake
 */
function normalizeName(string $s): string
{
    $s = latinize($s);
    $s = mb_strtolower($s, 'UTF-8');
    $s = stripDiacritics($s);
    $s = preg_replace('/\s+/', ' ', trim($s));
    return $s;
}

/**
 * Izvlači "čisto" ime opštine iz naziva sa RIK stranice.
 * Uklanja suffixe tipa "(izmena resenja)", "(dopuna resenja)" itd.
 *
 * Primeri:
 *   "Aleksinac (izmena resenja)" → "Aleksinac"
 *   "Babusnica (dopuna resenja)" → "Babusnica"
 *   "Ada"                        → "Ada"
 */
function extractCleanName(string $naziv): string
{
    // Ukloni sve u zagradama (i same zagrade)
    $clean = preg_replace('/\s*\(.*?\)\s*/u', '', $naziv);
    return trim($clean);
}

/**
 * Detektuje tip fajla na osnovu naziva.
 * Vraća jedan od: 'osnovno', 'izmena', 'dopuna', 'ostalo'
 */
function detectTip(string $naziv): string
{
    $low = mb_strtolower(latinize($naziv), 'UTF-8');
    if (str_contains($low, 'izmena')) return 'izmena';
    if (str_contains($low, 'dopuna')) return 'dopuna';
    // Nazivi van standardne liste (zavodi, inostranstvo itd.)
    if (str_contains($low, 'zavod') || str_contains($low, 'inostranstv') || str_contains($low, 'vojsk')) {
        return 'ostalo';
    }
    return 'osnovno';
}

/**
 * Pravi URL-friendly slug od naziva opštine (za direktorijum na disku).
 * Primer: "Bajina Bašta" → "bajina-basta"
 */
function makeSlug(string $name): string
{
    $s = latinize($name);
    $s = mb_strtolower($s, 'UTF-8');
    $s = stripDiacritics($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s;
}

/**
 * Vraća apsolutnu putanju do root direktorijuma projekta.
 */
function projectRoot(): string
{
    return rtrim(dirname(__FILE__), '/\\');
}

/**
 * Vraća putanju do storage direktorijuma.
 */
function storagePath(string ...$parts): string
{
    return projectRoot() . DIRECTORY_SEPARATOR . 'storage'
        . (count($parts) ? DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) : '');
}

/**
 * Kreira direktorijum ako ne postoji.
 */
function ensureDir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

/**
 * Loguje poruku u storage/logs/cron.log.
 */
function logMessage(string $message, string $level = 'INFO'): void
{
    $logDir = storagePath('logs');
    ensureDir($logDir);
    $line = date('[Y-m-d H:i:s]') . " [{$level}] {$message}" . PHP_EOL;
    file_put_contents($logDir . DIRECTORY_SEPARATOR . 'cron.log', $line, FILE_APPEND | LOCK_EX);
    // Ispis u CLI modu
    if (php_sapi_name() === 'cli') {
        echo $line;
    }
}
