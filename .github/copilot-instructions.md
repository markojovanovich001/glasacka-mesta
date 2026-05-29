# Codebase Instructions — RIK Glasačka mesta monitor

## Što je ovaj projekat

PHP 8.2 + Bootstrap 5.3 admin panel koji prati izmene Word dokumenata (glasačka mesta) na RIK sajtu. Scrape-uje listu fajlova, preuzima `.doc`/`.docx`, konvertuje u PDF, parsira tabelu GM-ova, čuva historiju verzija i prikazuje razlike (diff).

---

## Stack

| Sloj | Tehnologija |
|---|---|
| Backend | PHP 8.2 (XAMPP Windows, `C:\xampp\php\php.exe`) |
| Baza | MariaDB 10.4.32, baza `birackamesta` |
| Frontend | Bootstrap 5.3.3 + Bootstrap Icons 1.11.3 (CDN) |
| Word parsing | `phpoffice/phpword` (Composer, `vendor/`) |
| Doc→Docx/PDF | LibreOffice headless via `proc_open`, **uvek array args + `['bypass_shell' => true]`** |
| Lokacija projekta | `C:\xampp\htdocs\ORG\biracka-mesta\` |

---

## Struktura fajlova

```
db.php              Konekcija na bazu (getDB() → mysqli singleton)
helpers.php         latinize(), normalizeName(), logMessage(), ensureDir(), storagePath(), projectRoot()
scraper.php         RikScraper — preuzima listu fajlova sa RIK sajta, download, hash provjera
converter.php       DocConverter — convert() (.doc→.docx), convertToPdf() (.doc/.docx→.pdf)
parser.php          GmParser — parsira Word tabelu, upisuje glasacka_mesta u bazu
cron.php            Orchestrator — scrape → download → konverzija → PDF → parsiranje
api.php             Jedina API tačka (GET/POST), match($action) dispatch
layout.php          Shared HTML layout: layout_start() / layout_end($js)
index.php           Dashboard — hierarhija opstina/gradova sa GM statistikama
schema.sql          DDL + idempotentne migracije (ALTER TABLE ... IF NOT EXISTS / IF EXISTS)
pages/
  detail.php        Detalji jednog fajla — diff verzija, GM lista, PDF prikaz
  log.php           Cron log viewer
  sistem.php        Sistemski status (LibreOffice, baza, storage)
storage/
  fajlovi/          Preuzeti .doc/.docx/.pdf fajlovi (organizovani po opstini)
  logs/cron.log     Cron log
```

---

## Baza podataka

### Tabele (ne diraj `opstine` i `gradovi`)

- **`rik_fajlovi`** — jedan red po linku sa RIK stranice (`tip`: osnovno/izmena/dopuna/ostalo, `zanemari`, `has_changes`, `napomena`)
- **`rik_fajlovi_verzije`** — svaka preuzeta verzija; `file_path`, `conv_path` (docx), `pdf_path` (pdf), `file_hash` (SHA256 sadržaja fajla), `parsed`
- **`glasacka_mesta`** — parsovani GM-ovi po fajlu; `broj_gm`, `naziv`, `adresa`, `fajl_id`, `opstina_id`, `grad_id`

### Važna pravila
- `file_hash` (na `rik_fajlovi_verzije`) je SHA256 **binarnog sadržaja fajla** — jedina svrha je detekcija izmene fajla
- `word_hash` (na `rik_fajlovi`) je SHA256 sadržaja Word fajla, upisuje se posle downloada
- **`podrucje_hash` ne postoji** — kolona nikad nije dodana
- Sve vrednosti u bazi su **latinica** (prošle kroz `latinize()`)
- Za brisanje GM-a: `DELETE FROM glasacka_mesta WHERE id=?` — FK cascade automatski briše `glasacka_mesta_podrucja`

---

## API (`api.php`)

Sve akcije idu kroz `match($action)`. Greške se vraćaju kao `{"success": false, "error": "..."}`.

| Akcija | Metod | Opis |
|---|---|---|
| `status` | GET | Lista fajlova za dashboard |
| `verzije` | GET `?fajl_id=N` | Poslednje dve verzije + GM podaci za diff |
| `gm_list` | GET `?fajl_id=N` | Svi GM-ovi jednog fajla |
| `update_gm` | POST | Inline edit `naziv`, `adresa`, `broj_gm` |
| `delete_gm` | POST `{id}` | Brisanje GM |
| `add_gm` | POST | Ručno dodavanje GM |
| `toggle_zanemari` | POST `{fajl_id}` | Preklop zanemari flaga |
| `prihvati_promenu` | POST `{fajl_id}` | Resetuj `has_changes=0` |
| `update_fajl` | POST | Update napomene fajla |
| `run_check` | POST | Pokreni cron u pozadini |
| `cron_log` | GET `?from_byte=N` | Novi redovi iz cron.log (polling) |
| `pause_check` / `resume_check` | POST | Pauziraj/nastavi scraping |
| `reset_db` | POST | Reset baze i storage (destruktivno!) |

---

## Konvencije

### PHP
- `helpers.php` se uključuje svuda — `latinize()`, `logMessage()`, `projectRoot()`, `ensureDir()`, `storagePath()`
- LibreOffice se poziva **isključivo** sa array-form `proc_open` + `['bypass_shell' => true]` — nikad `shell_exec` ili string komanda
- PDF konverzija je **nekritična** — greška se loguje kao WARN i cron nastavlja
- `layout_end($js)` **ne poziva `exit()`** — uvek dodati `exit;` odmah posle poziva u fajlovima koji koriste layout
- Gotovo svi fajlovi u `pages/` počinju sa `require_once '../db.php'` i sl.

### JavaScript (detail.php)
- `allGmRows` — globalni niz svih GM redova, koristi se za filtriranje i broj
- `escHtml(s)` / `escAttr(s)` — uvek koristiti za user data u string konkatenaciji
- Sve fetch pozive raditi prema `'../api.php'` (iz `pages/` podfolder)
- Toast notifikacije: `showToast(msg, 'success'|'danger'|'warning')`

### Terminologija
- Uvek **"glasačko mesto"** (ne "biračko mesto")
- Tabela u Word dokumentu ima kolone: Br. GM, Naziv, Adresa, Područje

---

## Cron tok obrade jednog fajla

```
download → .doc? → DocConverter::convert() (→.docx, snimi conv_path)
                → DocConverter::convertToPdf() (→.pdf, snimi pdf_path, nekritično)
         → GmParser::parse() (parsira tabelu, upsert glasacka_mesta + glasacka_mesta_podrucja)
```

Fajlovi tipa `izmena`/`dopuna` se **ne parsiraju** — samo se čuva napomena.

---

## Diff logika

GM lista se prikazuje po `fajl_id`. Promena se detektuje u `cron.php` poređenjem `word_hash`.

GM diff između verzija poredi `naziv` i `adresa` direktno.

---

## Beograd FK rešavanje

Opstine u formatu `"Beograd - Barajevo"` se rešavaju splitovanjem po ` - ` — leva strana je grad (`gradovi`), desna je opstina (`opstine`).

---

## Česte greške koje treba izbegavati

- Ne dodavati `podrucje_hash` — kolona ne postoji u bazi
- Ne koristiti string format u `proc_open` za LibreOffice — koristiti array + `bypass_shell`
- Ne echo-ovati nešto posle `layout_end()` bez `exit;` — cela strana će se prikazati duplo
- Sve vrednosti iz Word fajlova proći kroz `latinize()` pre upisa u bazu
- `cleanText()` u `parser.php` uklanja tipografske navodnike i tačku-zarez — primeniti na `naziv` i `adresa`
