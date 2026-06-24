-- ============================================================
--  DATABASE GALLERY HMSI UNIPEM
--  Jalankan file ini di phpMyAdmin atau MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS `hmsi_unipem`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `hmsi_unipem`;

-- --------------------------------------------------------
--  Tabel: gallery_categories
--  Menyimpan kategori/filter gallery
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gallery_categories` (
  `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(60)      NOT NULL UNIQUE,
  `label`      VARCHAR(100)     NOT NULL,
  `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
--  Tabel: gallery_items
--  Menyimpan setiap foto/item gallery
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gallery_items` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED     NOT NULL,
  `title`       VARCHAR(200)     NOT NULL,
  `description` TEXT,
  `image_path`  VARCHAR(500)     NOT NULL,        -- path relatif, misal: img/workshop/foto1.jpg
  `image_height` ENUM('sm','md','lg') NOT NULL DEFAULT 'md',  -- untuk masonry layout
  `is_active`   TINYINT(1)       NOT NULL DEFAULT 1,
  `sort_order`  INT              NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_gallery_category` (`category_id`),
  CONSTRAINT `fk_gallery_category`
    FOREIGN KEY (`category_id`)
    REFERENCES `gallery_categories` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
--  Tabel: gallery_admin
--  Akun admin untuk halaman kelola gallery
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gallery_admin` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(60)   NOT NULL UNIQUE,
  `password`   VARCHAR(255)  NOT NULL,   -- bcrypt hash
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SEED DATA — Kategori
-- ============================================================
INSERT INTO `gallery_categories` (`slug`, `label`) VALUES
  ('event',      'Event Donor Darah HMSI x BEM UNIPI'),
  ('workshop',   'Workshop'),
  ('pelantikan', 'Pelantikan'),
  ('kegiatan',   'Kegiatan'),
  ('prestasi',   'Prestasi');

-- ============================================================
--  SEED DATA — Contoh item gallery
--  (sesuaikan image_path dengan file foto yang ada)
-- ============================================================
INSERT INTO `gallery_items`
  (`category_id`, `title`, `description`, `image_path`, `image_height`, `sort_order`)
VALUES
  (2, 'Penyerahan Sertifikat Workshop SolveLab',
   'Penyerahan sertifikat kepada pembicara Dr. Yoga Prihastomo, ST., M.KOM dalam workshop Transform Your Ideas into Innovative Systems.',
   'img/workshop/workshop1.jpeg', 'lg', 1),

  (2, 'Foto Bersama Peserta & Pengurus',
   'Foto bersama peserta workshop dan pengurus HMSI bersama pembicara.',
   'img/workshop/workshop2.jpeg', 'md', 2),

  (1, 'Donor Darah HMSI x BEM UNIPI',
   'Kegiatan donor darah bersama BEM UNIPI sebagai bentuk kepedulian sosial.',
   'img/event/donor1.jpeg', 'lg', 1),

  (3, 'Pelantikan Pengurus HMSI',
   'Momen pelantikan resmi pengurus HMSI periode aktif.',
   'img/pelantikan/pelantikan1.jpeg', 'md', 1),

  (4, 'Rapat Koordinasi Divisi',
   'Rapat koordinasi antar divisi dalam rangka persiapan program kerja.',
   'img/kegiatan/rapat1.jpeg', 'sm', 1),

  (5, 'Prestasi Mahasiswa SI',
   'Penghargaan yang diraih mahasiswa Sistem Informasi dalam kompetisi nasional.',
   'img/prestasi/prestasi1.jpeg', 'md', 1);

-- ============================================================
--  SEED DATA — Admin
--  Password default: admin123  (ganti setelah deploy!)
--  Hash di bawah = bcrypt($2y$10$...) dari "admin123"
-- ============================================================
INSERT INTO `gallery_admin` (`username`, `password`) VALUES
  ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- Untuk generate hash baru: php -r "echo password_hash('passwordbaru', PASSWORD_DEFAULT);"

-- ============================================================
--  VIEW HELPER — gallery lengkap dengan nama kategori
-- ============================================================
CREATE OR REPLACE VIEW `v_gallery_full` AS
SELECT
  gi.id,
  gi.title,
  gi.description,
  gi.image_path,
  gi.image_height,
  gi.is_active,
  gi.sort_order,
  gi.created_at,
  gc.id   AS category_id,
  gc.slug AS category_slug,
  gc.label AS category_label
FROM `gallery_items` gi
JOIN `gallery_categories` gc ON gi.category_id = gc.id
WHERE gi.is_active = 1
ORDER BY gi.sort_order ASC, gi.created_at DESC;
