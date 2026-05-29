# 🗳️ Glasačka Mesta - Republika Srbija

## 📋 Opis
**Profesionalno dizajniran sistem** za upravljanje glasačkim mestima u Republici Srbiji. Automatski preuzima i parsuje DOC/DOCX fajlove sa zvaničnog sajta RIK-a, kreira strukturiranu bazu podataka i omogućava verifikaciju i ažuriranje.

**Napomena:** Sistem koristi termin "lokacija" jer su podaci na RIK sajtu organizovani po gradovima i opštinama (ne pravi se razlika). Tehnički naziv tabele u bazi je `municipalities` ali UI prikazuje "Lokacije".

**Dizajnirano za WordPress integraciju** - sistem se lako može integrisati kao custom post type ili plugin.

---

## ✨ Funkcionalnosti

### Core Features
- ✅ Automatsko preuzimanje DOC/DOCX fajlova sa RIK sajta
- ✅ Parsiranje Word tabela (broj, naziv, adresa, područje)
- ✅ SQLite baza podataka (lakša migracija na WordPress)
- ✅ Bootstrap 5 UI - modern, responsive dizajn
- ✅ Active/Inactive status za glasačka mesta
- ✅ Verifikacija i recheck sistem
- ✅ Export za WordPress (JSON, SQL)

### Download Management
- 📥 **Smart Download** - preuzimanje samo novih fajlova
- 🔄 **Re-download** - refresh svih dokumenata
- ⏸️ **Pause/Resume** - kontrola procesa
- 📊 **Real-time Progress** - live tracking

### Database Structure
- 🏛️ **Municipalities** - gradovi/opštine sa metadata
- 🗳️ **Voting Places** - glasačka mesta sa svim detaljima
- 📝 **Activity Log** - praćenje svih izmena
- ✅ **Verification System** - status verifikacije

### Admin Interface
- 👀 **Pregled opština** - lista svih gradova
- 📋 **Glasačka mesta** - detaljni pregled po opštini
- ✏️ **Edit/Update** - izmena podataka
- 🔄 **Recheck** - ponovni parsing i ažuriranje
- 📊 **Statistics** - kompletne statistike

### WordPress Ready
- 📦 Export u WordPress kompatibilne formate
- 🔌 Priprema za custom post type
- 🎨 Bootstrap stil kompatibilan sa WP temama
- 🔗 REST API endpoints za integraciju

---

## 🚀 Quick Start

### 1. Instalacija

```bash
# Kopirajte projekat u XAMPP
c:\xampp\htdocs\ORG\glasacka-mesta\

# Pokrenite Apache u XAMPP-u
```

### 2. Pristup

```
http://localhost/ORG/glasacka-mesta/
```

### 3. Inicijalizacija

1. **Prvi put se automatski kreira:**
   - SQLite baza (`data/glasacka_mesta.db`)
   - Potrebni direktorijumi
   - Tabele i indeksiLokacije":**
   - Parsuje RIK stranicu
   - Ekstraktuje sve lokacije (gradove i opštine)e":**
   - Parsuje RIK stranicu
   - Ekstraktuje sve opštine i DOC linkove
   - Čuva u bazu

3. **Kliknite "Preuzmi Sve":**
   - Preuzima sve DOC/DOCX fajlove
   - Automatsko parsiranje
   - Ekstrakcija glasačkih mesta

### 4. Upravljanje

**Pregled lokacija:**
```
Locations → Pregled svih gradova i opština sa statusima
```

**Glasačka mesta:**
```
Voting Places → Pregled po lokacijama
Edit → Izmena podataka
Mark Active/Inactive → Promena statusa
```

**Recheck:**
```
Download → Re-download → Preuzmi i parsiraj ponovo
```

---

## 📊 Database Schema

### municipalities
| Kolona | Tip | Opis |
|--------|-----|------|
| id | INTEGER PK | Jedinstveni ID |
| name | TEXT | Naziv lokacije (grad ili opština, ćirilica) |
| slug | TEXT | URL-friendly naziv |
| doc_url | TEXT | Link ka DOC fajlu |
| doc_file | TEXT | Lokalna putanja |
| downloaded_at | DATETIME | Vreme preuzimanja |
| parsed_at | DATETIME | Vreme parsiranja |
| active | INTEGER | Aktivna (1) ili ne (0) |
| notes | TEXT | Dodatne napomene |

### voting_places
| Kolona | Tip | Opis |
|--------|-----|------|
| id | INTEGER PK | Jedinstveni ID |
| municipality_id | INTEGER FK | Veza sa lokacijom (gradom/opštinom) |
| place_number | INTEGER | Broj glasačkog mesta |
| place_name | TEXT | Naziv mesta |
| address | TEXT | Adresa |
| area | TEXT | Područje |
| area_addresses | TEXT | Adrese u području |
| active | INTEGER | Aktivno (1) ili ne (0) |
| verified | INTEGER | Verifikovano (1) ili ne (0) |
| notes | TEXT | Napomene |
| source_file | TEXT | Izvorni DOC fajl |

---

## 🔄 WordPress Integracija

### Plan integracije:

1. **Export iz SQLite:**
```php
// Export u WordPress SQL format
Export → WordPress SQL
```

2. **Custom Post Type:**
```php
// Glasačka mesta kao CPT
Post Type: glasacko_mesto
Taxonomy: opstina (municipality)
```

3. **Meta Fields:**
```php
place_number
address
area
area_addresses
active (visibility)
verified
```

4. **Search Integration:**
```php
// Pretraga po adresi, opštini, nazivu
// Filter po aktivnim/neaktivnim
```

---

## 🛠️ Maintenance

### Periodični Re-check

```php
1. Download → Recheck All
2. Sistem preuzima nove verzije
3. Poredi sa postojećim
4. Označava razlike
5. Admin pregleda i potvrđuje
```

### Verifikacija

```php
1. Voting Places → Filter: Not Verified
2. Pregled svakog mesta
3. Potvrda ili izmena
4. Mark as Verified
```

### Deaktivacija

```php
1. Find voting place
2. Mark as Inactive
3. Neće se prikazivati na sajtu
4. Ali ostaje u bazi za istoriju
```

---

## 📁 Struktura Projekta

```
glasacka-mesta/
├── config.php              # Konfiguracija i DB setup
├── index.php               # Glavna stranica - Dashboard
├── download.php            # Download manager
├── municipalities.php      # Upravljanje opštinama
├── voting_places.php       # Upravljanje glasačkim mestima
├── verify.php              # Sistem verifikacije
├── api/
│   ├── get_municipalities.php
│   ├── get_voting_places.php
│   ├── update_place.php
│   └── export.php
├── data/
│   ├── glasacka_mesta.db   # SQLite baza
│   ├── downloads/          # DOC fajlovi
│   ├── parsed/             # Parsovani JSON
│   └── logs/               # Log fajlovi
└── assets/
    ├── css/
    └── js/
```

---

## 🎨 UI/UX

- **Bootstrap 5** - Modern, responsive
- **Bootstrap Icons** - Ikone
- **DataTables** - Napredne tabele
- **AJAX** - Real-time updates bez reload-a
- **Serbian (Cyrillic)** - Kompletan UI na srpskom

---

## 🔧 Tehnologije

- PHP 7.4+ (XAMPP compatible)
- SQLite 3
- Bootstrap 5.3
- jQuery 3.6
- DataTables
- Word Document Parsing (PHPWord)

---

## 📝 TODO

- [ ] PhpWord integracija za parsing
- [ ] Excel export
- [ ] WordPress plugin struktura
- [ ] REST API dokumentacija
- [ ] Admin dashboard statistika
- [ ] Email notifikacije za promene
- [ ] Backup/Restore sistem

---

## 📄 License

MIT License - Free to use and modify

---

## 👨‍💻 Support

Za pitanja i podršku kontaktirajte administratora sistema.

**Developed for RIK integration - January 2026**
