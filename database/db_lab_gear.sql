-- ============================================================================
-- Database: db_lab_gear
-- Tema: Sistem Manajemen Peminjaman Alat Laboratorium Komputer
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `db_lab_gear`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `db_lab_gear`;

-- Hapus tabel dengan urutan tabel anak terlebih dahulu, baru tabel induk
DROP TABLE IF EXISTS `borrowings`;
DROP TABLE IF EXISTS `equipments`;
DROP TABLE IF EXISTS `borrowers`;
DROP TABLE IF EXISTS `categories`;

-- ----------------------------------------------------------------------------
-- 1. Tabel categories (Induk dari equipments)
-- ----------------------------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_code` VARCHAR(20) NOT NULL,
  `category_name` VARCHAR(100) NOT NULL,
  `lab_room` VARCHAR(60) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_code` (`category_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Tabel equipments (Anak dari categories, Induk dari borrowings)
-- ----------------------------------------------------------------------------
CREATE TABLE `equipments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `asset_code` VARCHAR(30) NOT NULL,
  `equipment_name` VARCHAR(120) NOT NULL,
  `brand` VARCHAR(80) NOT NULL,
  `stock` INT UNSIGNED NOT NULL DEFAULT 0,
  `item_condition` ENUM('Baik', 'Perlu Kalibrasi', 'Rusak Ringan') NOT NULL DEFAULT 'Baik',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_equipments_asset_code` (`asset_code`),
  KEY `idx_equipments_category_id` (`category_id`),
  CONSTRAINT `fk_equipments_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Tabel borrowers (Induk dari borrowings)
-- ----------------------------------------------------------------------------
CREATE TABLE `borrowers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identity_number` VARCHAR(30) NOT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `study_program` VARCHAR(80) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_borrowers_identity` (`identity_number`),
  UNIQUE KEY `uq_borrowers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Tabel borrowings (Anak dari equipments dan borrowers)
-- ----------------------------------------------------------------------------
CREATE TABLE `borrowings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `equipment_id` INT UNSIGNED NOT NULL,
  `borrower_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `borrow_date` DATE NOT NULL,
  `return_date` DATE DEFAULT NULL,
  `status` ENUM('Dipinjam', 'Dikembalikan') NOT NULL DEFAULT 'Dipinjam',
  `purpose` VARCHAR(200) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_borrowings_equipment_id` (`equipment_id`),
  KEY `idx_borrowings_borrower_id` (`borrower_id`),
  CONSTRAINT `fk_borrowings_equipment`
    FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `fk_borrowings_borrower`
    FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATA AWAL (Konsisten dengan logika stok)
-- ============================================================================

INSERT INTO `categories` (`id`, `category_code`, `category_name`, `lab_room`, `description`) VALUES
(1, 'CAT-IOT', 'Perangkat IoT & Mikrokontroler', 'Lab Sistem Tertanam Lantai 2', 'Board mikrokontroler, sensor, dan modul komunikasi nirkabel.'),
(2, 'CAT-NET', 'Perangkat Jaringan Komputer', 'Lab Jaringan & Keamanan Lantai 3', 'Router, switch manageable, access point, dan crimping toolkit.'),
(3, 'CAT-VR', 'Multimedia & Virtual Reality', 'Lab Grafika & Multimedia Lantai 1', 'Headset VR, kamera aksi, tripod, dan perangkat capture grafis.'),
(4, 'CAT-RBT', 'Robotika & Aktuator', 'Lab Robotika Lantai 2', 'Kit lengan robot, motor servo presisi, dan lidar scanner.');

-- Catatan konsistensi stok:
-- Equipment ID 1 (ESP32): kapasitas awal 10, sedang dipinjam 2 (Borrowing #1) -> stok saat ini = 8
-- Equipment ID 2 (Cisco Router): kapasitas awal 5, sedang dipinjam 1 (Borrowing #2) -> stok saat ini = 4
-- Equipment ID 3 (Meta Quest 3): kapasitas awal 4, pernah dipinjam 1 tapi sudah Dikembalikan (Borrowing #3) -> stok saat ini = 4
-- Equipment ID 4 (Raspberry Pi 5): kapasitas awal 6, sedang dipinjam 1 (Borrowing #4) -> stok saat ini = 5
-- Equipment ID 5 (RPLIDAR A1M8): kapasitas awal 3, belum ada peminjaman aktif -> stok saat ini = 3
INSERT INTO `equipments` (`id`, `category_id`, `asset_code`, `equipment_name`, `brand`, `stock`, `item_condition`) VALUES
(1, 1, 'INV-IOT-001', 'ESP32-S3 DevKitC Dual USB', 'Espressif', 8, 'Baik'),
(2, 2, 'INV-NET-001', 'Router ISR 4321 Gigabit', 'Cisco', 4, 'Baik'),
(3, 3, 'INV-VR-001', 'Quest 3 128GB Mixed Reality Headset', 'Meta', 4, 'Baik'),
(4, 1, 'INV-IOT-002', 'Raspberry Pi 5 8GB RAM Kit', 'Raspberry Pi', 5, 'Baik'),
(5, 4, 'INV-RBT-001', 'LIDAR 360 Laser Range Scanner A1M8', 'Slamtec', 3, 'Perlu Kalibrasi');

INSERT INTO `borrowers` (`id`, `identity_number`, `full_name`, `study_program`, `email`, `phone`) VALUES
(1, '2301041', 'Raka Pratama Wijaya', 'Ilmu Komputer', 'raka.pratama@example.edu', '081234567801'),
(2, '2302088', 'Nadia Maharani Putri', 'Pendidikan Ilmu Komputer', 'nadia.maharani@example.edu', '081234567802'),
(3, '2204119', 'Dimas Arya Kusuma', 'Ilmu Komputer', 'dimas.arya@example.edu', '081234567803'),
(4, '19880512201504', 'Dr. Hendra Gunawan, M.Kom.', 'Dosen Ilmu Komputer', 'hendra.gunawan@example.edu', '081234567804');

INSERT INTO `borrowings` (`id`, `equipment_id`, `borrower_id`, `quantity`, `borrow_date`, `return_date`, `status`, `purpose`) VALUES
(1, 1, 1, 2, '2026-11-05', NULL, 'Dipinjam', 'Praktikum Sistem Tertanam Kelompok 4'),
(2, 2, 2, 1, '2026-11-07', NULL, 'Dipinjam', 'Simulasi Routing OSPF Jaringan Komputer'),
(3, 3, 3, 1, '2026-11-01', '2026-11-04', 'Dikembalikan', 'Pengujian Aplikasi Tur Virtual Kampus'),
(4, 4, 4, 1, '2026-11-08', NULL, 'Dipinjam', 'Riset Edge AI Deteksi Objek Real-time');
