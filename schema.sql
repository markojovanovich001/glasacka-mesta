-- ============================================================
-- RIK Glasačka mesta monitor – DDL skripta
-- Pokrenuti u bazi: birackamesta
-- Postojeće tabele gradovi i opstine se NE diraju.
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ------------------------------------------------------------
-- Tabela: rik_fajlovi
-- Svaki link (fajl) sa RIK listing stranice
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rik_fajlovi` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `naziv`       VARCHAR(255) NOT NULL COMMENT 'Latinica, npr. Aleksinac (izmena resenja)',
    `naziv_clean` VARCHAR(255) NOT NULL COMMENT 'Latinica, samo ime opstine, bez dopuna/izmena',
    `url`         TEXT         NOT NULL,
    `word_hash`   CHAR(64)     NULL     COMMENT 'SHA256 sadržaja Word fajla (popunjava se nakon prvog download-a)',
    `opstina_id`  INT(11)      NULL     COMMENT 'FK → opstine.id  (int(11) da se poklopi s tipom PK)',
    `grad_id`     INT(11)      NULL     COMMENT 'FK → gradovi.id  (int(11) da se poklopi s tipom PK)',
    `tip`         ENUM('osnovno','izmena','dopuna','ostalo') NOT NULL DEFAULT 'osnovno',
    `aktivan`     TINYINT(1)   NOT NULL DEFAULT 1,
    `zanemari`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Preskoči ovaj fajl pri proveri',
    `has_changes` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'flag: ima nepregledanih izmena',
    `napomena`    TEXT         NULL     COMMENT 'Napomena za amandman fajlove',
    `last_error`  TEXT         NULL     COMMENT 'Poslednja greška pri obradi',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_word_hash` (`word_hash`),
    KEY `idx_opstina` (`opstina_id`),
    KEY `idx_grad` (`grad_id`),
    CONSTRAINT `fk_rikfajlovi_opstina`
        FOREIGN KEY (`opstina_id`) REFERENCES `opstine` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_rikfajlovi_grad`
        FOREIGN KEY (`grad_id`)    REFERENCES `gradovi` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: rik_fajlovi_verzije
-- Svaka preuzeta verzija fajla (historija)
-- Sadržaj se čuva lokalno na disku, ovde samo putanja + hash
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rik_fajlovi_verzije` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `fajl_id`     INT UNSIGNED NOT NULL,
    `file_hash`   CHAR(64)     NOT NULL COMMENT 'SHA256 binarnog sadržaja fajla',
    `file_path`   VARCHAR(500) NOT NULL COMMENT 'Relativna putanja od root projekta, npr. storage/fajlovi/ada/abc123.docx',
    `conv_path`   VARCHAR(500) NULL     COMMENT 'Putanja do konvertovanog .docx (ako je original .doc)',
    `file_ext`    ENUM('doc','docx')   NOT NULL,
    `file_size`   INT UNSIGNED NULL     COMMENT 'Veličina u bajtovima',
    `parsed`      TINYINT(1)   NOT NULL DEFAULT 0,
    `parse_error` TEXT         NULL,
    `detected_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fajl_hash` (`fajl_id`, `file_hash`),
    KEY `idx_fajl_id` (`fajl_id`),
    CONSTRAINT `fk_verzije_fajl`
        FOREIGN KEY (`fajl_id`) REFERENCES `rik_fajlovi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: glasacka_mesta
-- Parsovane stavke iz Word tabele po verziji fajla
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `glasacka_mesta` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `fajl_id`    INT UNSIGNED NOT NULL,
    `broj_gm`    VARCHAR(20)  NOT NULL COMMENT 'Broj glasačkog mesta (БРОЈ ГМ)',
    `naziv`      VARCHAR(500) NOT NULL COMMENT 'Naziv GM (latinica)',
    `adresa`     TEXT         NULL     COMMENT 'Adresa glasačkog mesta (latinica)',
    `opstina_id` INT(11)      NULL     COMMENT 'FK → opstine.id',
    `grad_id`    INT(11)      NULL     COMMENT 'FK → gradovi.id',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fajl` (`fajl_id`),
    KEY `idx_opstina` (`opstina_id`),
    KEY `idx_broj_gm` (`fajl_id`, `broj_gm`),
    CONSTRAINT `fk_gm_fajl`
        FOREIGN KEY (`fajl_id`)    REFERENCES `rik_fajlovi` (`id`)     ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_gm_opstina`
        FOREIGN KEY (`opstina_id`) REFERENCES `opstine` (`id`)         ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_gm_grad`
        FOREIGN KEY (`grad_id`)    REFERENCES `gradovi` (`id`)         ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabela: glasacka_mesta_podrucja
-- Svaka stavka/linija u koloni PODRUČJE Word tabele.
-- Jedan GM ima N područja, svako područje ima N adresa.
-- Hijerarhija: glasacka_mesta → podrucja → adrese
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `glasacka_mesta_podrucja` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `gm_id`       INT UNSIGNED NOT NULL,
    `naziv`       TEXT         NOT NULL COMMENT 'Naziv ulice/lokaliteta (latinica)',
    `broj_adrese` VARCHAR(20)  NULL     COMMENT 'Kućni broj adrese (može biti 0, prazan, 12-A, 52-B...)',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_gm_podrucja` (`gm_id`),
    CONSTRAINT `fk_podrucje_gm`
        FOREIGN KEY (`gm_id`) REFERENCES `glasacka_mesta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- ── Migracije (idempotentne) ──────────────────────────────────────────────────
ALTER TABLE `rik_fajlovi`
    ADD COLUMN IF NOT EXISTS `zanemari`  TINYINT(1) NOT NULL DEFAULT 0   COMMENT 'Preskoči ovaj fajl pri proveri' AFTER `aktivan`,
    ADD COLUMN IF NOT EXISTS `napomena`  TEXT        NULL                 COMMENT 'Napomena za amandman fajlove'  AFTER `last_error`;
ALTER TABLE `rik_fajlovi_verzije`
    ADD COLUMN IF NOT EXISTS `pdf_path` VARCHAR(500) NULL
    COMMENT 'Putanja do PDF fajla za pregled u browseru' AFTER `conv_path`;

-- Migracija: ukloni stare kolone iz glasacka_mesta
ALTER TABLE `glasacka_mesta`
    DROP COLUMN IF EXISTS `podrucje_hash`,
    DROP COLUMN IF EXISTS `podrucje_string`;

-- Migracija: ukloni verzija_id iz glasacka_mesta (jednom)
-- Pažnja: pre DROP-a, MariaDB zahteva brisanje FK i indeksa:
--   ALTER TABLE `glasacka_mesta` DROP FOREIGN KEY `fk_gm_verzija`;
--   ALTER TABLE `glasacka_mesta` DROP INDEX `idx_verzija`;
--   ALTER TABLE `glasacka_mesta` DROP COLUMN `verzija_id`;

-- Migracija: preimenuj adresa_raw → adresa u glasacka_mesta (jednom, MariaDB 10.4.3+)
--   ALTER TABLE `glasacka_mesta` RENAME COLUMN `adresa_raw` TO `adresa`;

-- Migracija: vreme poslednje provere (bez obzira na izmenu hash-a)
ALTER TABLE `rik_fajlovi`
    ADD COLUMN IF NOT EXISTS `last_checked_at` TIMESTAMP NULL
    COMMENT 'Poslednji put kada je fajl proveren (bez obzira na izmenu)'
    AFTER `updated_at`;

-- Migracija: ručno definisan "root" dokument za izmena/dopuna fajlove
ALTER TABLE `rik_fajlovi`
    ADD COLUMN IF NOT EXISTS `roditelj_fajl_id` INT UNSIGNED NULL
    COMMENT 'Ručno definisan "root" dokument (za izmena/dopuna fajlove koji nisu auto-matchovani)'
    AFTER `napomena`;

-- Migracija: preimenuj url_hash → word_hash u rik_fajlovi i promeni semantiku
-- (jednom; word_hash sada čuva SHA256 sadržaja Word fajla, ne URL-a)
--   ALTER TABLE `rik_fajlovi` RENAME COLUMN `url_hash` TO `word_hash`;
--   ALTER TABLE `rik_fajlovi` MODIFY COLUMN `word_hash` CHAR(64) NULL COMMENT 'SHA256 sadržaja Word fajla';
--   ALTER TABLE `rik_fajlovi` DROP INDEX `uq_url_hash`;
--   ALTER TABLE `rik_fajlovi` ADD INDEX `idx_word_hash` (`word_hash`);

-- Migracija: ukloni redosled iz glasacka_mesta_podrucja
ALTER TABLE `glasacka_mesta_podrucja`
    DROP COLUMN IF EXISTS `redosled`;

-- Migracija: dodaj broj_adrese u glasacka_mesta_podrucja
ALTER TABLE `glasacka_mesta_podrucja`
    ADD COLUMN IF NOT EXISTS `broj_adrese` VARCHAR(20) NULL
    COMMENT 'Kućni broj adrese (može biti 0, prazan, 12-A, 52-B...)' AFTER `naziv`;

-- Migracija: ukloni tabelu glasacka_mesta_adrese (svi podaci se gube!)
DROP TABLE IF EXISTS `glasacka_mesta_adrese`;