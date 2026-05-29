# Glasačka Mesta - Детаљна Документација

## 📚 Садржај

1. [Увод](#увод)
2. [Инсталација](#инсталација)
3. [Структура Пројекта](#структура-пројекта)
4. [База Података](#база-података)
5. [Функционалности](#функционалности)
6. [API Документација](#api-документација)
7. [WordPress Интеграција](#wordpress-интеграција)
8. [Напредне Могућности](#напредне-могућности)

---

## Увод

**Glasačka Mesta** је професионални систем за управљање гласачким местима у Републици Србији. Систем аутоматски преузима DOC/DOCX фајлове са званичног сајта RIK-а, парсује табеле са подацима о гласачким местима и омогућава управљање, верификацију и експорт података.

### Кључне Карактеристике

- ✅ **Аутоматско преузимање** - Преузима све DOC фајлове са RIK сајта
- ✅ **Парсирање докумената** - Чита Word табеле и екстрактује податке
- ✅ **SQLite база** - Лака за backup, погодна за миграцију
- ✅ **Bootstrap UI** - Modern, responsive, Serbian локализација
- ✅ **Верификација** - Систем за провеу и означавање верификованих места
- ✅ **Active/Inactive** - Контрола видљивости места
- ✅ **Export** - JSON, CSV, SQL за WordPress
- ✅ **REST API** - За интеграцију са другим системима

---

## Инсталација

### Системски Захтеви

- PHP 7.4 или новији
- PDO SQLite extension
- Apache/Nginx web server
- XAMPP (за локални развој)
- Минимум 256MB RAM за PHP
- 500MB слободног простора

### Кораци Инсталације

#### 1. Копирање Фајлова

```bash
# Копирај пројекат у XAMPP htdocs
c:\xampp\htdocs\ORG\glasacka-mesta\
```

#### 2. Покретање Apache-а

- Покрени XAMPP Control Panel
- Start Apache

#### 3. Отварање Апликације

```
http://localhost/ORG/glasacka-mesta/
```

#### 4. Аутоматска Иницијализација

При првом отварању, систем аутоматски:
- Креира `data/` директоријум
- Креира SQLite базу `glasacka_mesta.db`
- Креира све потребне табеле
- Поставља индексе

---

## Структура Пројекта

```
glasacka-mesta/
│
├── index.php                    # Главна страница - Dashboard
├── config.php                   # Конфигурација и DB setup
├── download.php                 # Download manager
├── municipalities.php           # Управљање општинама
├── voting_places.php           # Управљање гласачким местима
├── verify.php                  # Верификација и провера
├── README.md                   # Основна документација
├── DOCUMENTATION.md            # Детаљна документација (овај фајл)
│
├── api/                        # REST API endpoints
│   ├── get_stats.php           # Статистика
│   ├── health_check.php        # Health check
│   ├── get_municipalities.php  # Списак општина
│   ├── get_voting_places.php   # Гласачка места
│   ├── update_place.php        # Update места
│   ├── verify_data.php         # Верификација података
│   ├── bulk_verify.php         # Масовна верификација
│   ├── get_activity_log.php    # Log активности
│   └── export.php              # Export у различитим форматима
│
├── data/                       # Data директоријум (auto-created)
│   ├── glasacka_mesta.db       # SQLite база
│   ├── municipalities.json     # Кеш општина
│   ├── progress.json           # Progress трекинг
│   ├── control.json            # Контролни фајл
│   ├── stats.json              # Статистика
│   ├── downloads/              # Преузети DOC фајлови
│   ├── parsed/                 # Парсовани подаци
│   └── logs/                   # Log фајлови
│
└── reference/                  # Reference пројекат (zakoni)
    └── ...                     # Референтни фајлови
```

---

## База Података

### Шема База

#### Табела: `municipalities`

```sql
CREATE TABLE municipalities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,              -- Назив општине
    slug TEXT NOT NULL UNIQUE,              -- URL-friendly назив
    doc_url TEXT,                           -- Link ка DOC фајлу
    doc_file TEXT,                          -- Локална путања фајла
    downloaded_at DATETIME,                 -- Време преузимања
    parsed_at DATETIME,                     -- Време парсирања
    active INTEGER DEFAULT 1,               -- Активна (1) или не (0)
    notes TEXT,                             -- Напомене
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### Табела: `voting_places`

```sql
CREATE TABLE voting_places (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    municipality_id INTEGER NOT NULL,       -- FK ка општинама
    place_number INTEGER,                   -- Број гласачког места
    place_name TEXT,                        -- Назив (нпр. "ОШ Вук Караџић")
    address TEXT,                           -- Адреса
    area TEXT,                              -- Подручје
    area_addresses TEXT,                    -- Адресе у подручју
    active INTEGER DEFAULT 1,               -- Активно (1) или не (0)
    verified INTEGER DEFAULT 0,             -- Верификовано (1) или не (0)
    notes TEXT,                             -- Напомене
    source_file TEXT,                       -- Извор (DOC фајл)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (municipality_id) REFERENCES municipalities(id) ON DELETE CASCADE
);
```

#### Табела: `activity_log`

```sql
CREATE TABLE activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action TEXT NOT NULL,                   -- Акција (нпр. "download_doc")
    entity_type TEXT,                       -- Тип ентитета
    entity_id INTEGER,                      -- ID ентитета
    details TEXT,                           -- Детаљи (JSON)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### Индекси

```sql
CREATE INDEX idx_municipality_slug ON municipalities(slug);
CREATE INDEX idx_municipality_active ON municipalities(active);
CREATE INDEX idx_voting_place_municipality ON voting_places(municipality_id);
CREATE INDEX idx_voting_place_active ON voting_places(active);
CREATE INDEX idx_voting_place_verified ON voting_places(verified);
```

---

## Функционалности

### 1. Dashboard (index.php)

**Функције:**
- Преглед статистике у реалном времену
- Брзи линкови ка свим модулима
- Health check система
- Quick guide за нове кориснике

**Статистика:**
- Број општина
- Укупно гласачких места
- Активних места
- Верификованих места

### 2. Download Manager (download.php)

**Функције:**
- Fetch општине са RIK сајта
- Преузимање DOC/DOCX фајлова
- Аутоматско парсирање
- Pause/Resume/Stop контроле
- Real-time progress

**Режими:**
- **Само Нови** - Преузима само нове фајлове
- **Сви (Re-download)** - Поново преузима све

**Throttling:**
- 1.5s delay између захтева
- 3s delay након грешке
- Спречава блокирање сервера

### 3. Municipalities (municipalities.php)

**Функције:**
- DataTables преглед свих општина
- Статуси преузимања и парсирања
- Број гласачких места по општини
- Линкови ка DOC фајловима
- Филтрирање и претрага

**Колоне:**
- Назив и slug
- Број гласачких места (укупно/активна)
- Датум преузимања
- Датум парсирања
- Active/Inactive статус
- Акције (Преглед, Download)

### 4. Voting Places (voting_places.php)

**Функције:**
- Преглед свих гласачких места
- Филтрирање по општини
- Филтрирање по статусу (активно/неактивно)
- Филтрирање по верификацији
- Inline измена
- Брзо активирање/деактивирање
- Export (JSON, CSV)

**Edit Modal:**
- Број места
- Назив
- Адреса
- Подручје
- Адресе у подручју
- Active/Inactive checkbox
- Verified checkbox
- Напомене

### 5. Verification (verify.php)

**Функције:**

#### Провера Података
- Провера недостајућих података
- Провера општина без гласачких места
- Провера некомплетних записа
- Генерисање извештаја

#### Рехек
- Поново преузимање са RIK-а
- Поређење са постојећим подацима
- Означавање промена

#### Масовна Верификација
- Верификација свих активних места одједном

#### Извоз Извештаја
- JSON формат
- CSV формат
- SQL за WordPress

#### Activity Log
- Последњих 10 активности
- Филтрирање по типу
- Детаљи о изменама

---

## API Документација

Сви API endpoints враћају JSON са структуром:

```json
{
    "success": true/false,
    "data": {...},
    "error": "Error message" (if success=false)
}
```

### GET /api/get_stats.php

Враћа статистику система.

**Response:**
```json
{
    "success": true,
    "municipalities": 150,
    "voting_places": 8523,
    "active_places": 8300,
    "verified_places": 7500,
    "downloaded": 145,
    "parsed": 140
}
```

### GET /api/health_check.php

Проверава здравље система.

**Response:**
```json
{
    "success": true,
    "healthy": true,
    "checks": {
        "database": true,
        "data_dir": true,
        "downloads_dir": true,
        "parsed_dir": true,
        "logs_dir": true
    }
}
```

### GET /api/get_municipalities.php

Враћа све општине са статистиком.

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Београд",
            "slug": "beograd",
            "doc_url": "https://...",
            "places_count": 500,
            "active_count": 490,
            ...
        }
    ]
}
```

### GET /api/get_voting_places.php

Враћа гласачка места са филтрирањем.

**Query Parameters:**
- `municipality_id` - Филтер по општини
- `active_only=1` - Само активна места

**Response:**
```json
{
    "success": true,
    "data": [...],
    "count": 523
}
```

### POST /api/update_place.php

Update гласачког места.

**Request Body:**
```json
{
    "id": 123,
    "place_name": "New name",
    "address": "New address",
    "active": 1,
    "verified": 1
}
```

**Response:**
```json
{
    "success": true,
    "message": "Voting place updated successfully"
}
```

### GET /api/verify_data.php

Провера података.

**Response:**
```json
{
    "success": true,
    "municipalities_total": 150,
    "places_total": 8523,
    "missing_data": 23,
    "municipalities_no_places": 5
}
```

### POST /api/bulk_verify.php

Масовна верификација.

**Response:**
```json
{
    "success": true,
    "count": 8300
}
```

### GET /api/export.php?format={json|csv|sql}

Export података.

**Formats:**
- `json` - JSON фајл
- `csv` - CSV фајл (UTF-8 BOM)
- `sql` - WordPress SQL

---

## WordPress Интеграција

### Approach 1: Import SQL

1. Export SQL:
```
verify.php → Export → SQL за WordPress
```

2. Креирај tabelu у WordPress:
```sql
-- Табела се креира аутоматски из SQL-а
```

3. Query у WordPress:
```php
global $wpdb;
$places = $wpdb->get_results("
    SELECT * FROM wp_glasacka_mesta 
    WHERE municipality_slug = 'beograd'
");
```

### Approach 2: Custom Post Type

```php
// functions.php
function register_glasacko_mesto_cpt() {
    register_post_type('glasacko_mesto', [
        'labels' => [
            'name' => 'Гласачка Места',
            'singular_name' => 'Гласачко Место'
        ],
        'public' => true,
        'has_archive' => true,
        'supports' => ['title', 'custom-fields']
    ]);
    
    register_taxonomy('opstina', 'glasacko_mesto', [
        'label' => 'Општине',
        'hierarchical' => true
    ]);
}
add_action('init', 'register_glasacko_mesto_cpt');
```

### Import Script

```php
// import-glasacka-mesta.php
require_once 'wp-load.php';

$json = file_get_contents('glasacka_mesta.json');
$places = json_decode($json, true);

foreach ($places as $place) {
    $post_id = wp_insert_post([
        'post_title' => $place['place_name'],
        'post_type' => 'glasacko_mesto',
        'post_status' => 'publish'
    ]);
    
    update_post_meta($post_id, 'place_number', $place['place_number']);
    update_post_meta($post_id, 'address', $place['address']);
    update_post_meta($post_id, 'area', $place['area']);
    
    wp_set_object_terms($post_id, $place['municipality_name'], 'opstina');
}
```

### Search Integration

```php
// Template file
<?php
$args = [
    'post_type' => 'glasacko_mesto',
    's' => $_GET['search'],
    'tax_query' => [[
        'taxonomy' => 'opstina',
        'field' => 'slug',
        'terms' => $_GET['municipality']
    ]]
];

$query = new WP_Query($args);
while ($query->have_posts()) {
    $query->the_post();
    // Display place
}
?>
```

---

## Напредне Могућности

### Periodic Re-check (Cron)

```php
// cron-recheck.php
<?php
require_once 'config.php';

// Fetch latest data
$result = file_get_contents('http://localhost/ORG/glasacka-mesta/download.php?start=1&mode=new');

// Send email notification
if ($new_changes > 0) {
    mail('admin@example.com', 'Glasačka Mesta Updates', "Found $new_changes new changes");
}
?>
```

### Windows Task Scheduler

```batch
# Svake nedelje u 2AM
schtasks /create /tn "GlasackaMestaRecheck" /tr "php c:\xampp\htdocs\ORG\glasacka-mesta\cron-recheck.php" /sc weekly /d SUN /st 02:00
```

### Backup Script

```php
// backup.php
<?php
$db_file = __DIR__ . '/data/glasacka_mesta.db';
$backup_dir = __DIR__ . '/backups';

if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

$backup_file = $backup_dir . '/backup_' . date('Y-m-d_His') . '.db';
copy($db_file, $backup_file);

echo "Backup created: $backup_file\n";
?>
```

### REST API Authentication

```php
// config.php - add
define('API_KEY', 'your-secret-key');

// api/auth.php
function check_api_key() {
    $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($key !== API_KEY) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}
```

---

## Troubleshooting

### Database Locked

```php
// config.php - додај
$db->exec('PRAGMA journal_mode = WAL');
```

### Memory Limit

```php
// php.ini
memory_limit = 512M
```

### Parsing Timeout

```php
// download.php - додај
set_time_limit(3600); // 1 hour
```

---

## Changelog

### Version 1.0 (2026-01-31)
- Initial release
- Complete system with all features
- Bootstrap 5 UI
- SQLite database
- REST API
- WordPress export

---

## Support & Contact

За питања и подршку:
- GitHub Issues
- Email: admin@example.com

**Developed for RIK Integration - January 2026**
