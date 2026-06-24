-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 19, 2026 at 12:13 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ims`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `auditable_type` varchar(255) NOT NULL,
  `auditable_id` bigint(20) UNSIGNED NOT NULL,
  `before_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_values`)),
  `after_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `batches`
--

CREATE TABLE `batches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_id` bigint(20) UNSIGNED DEFAULT NULL,
  `purchase_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `batch_number` varchar(100) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `storage_location` varchar(150) DEFAULT NULL,
  `storage_location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `unit_cost` bigint(20) NOT NULL DEFAULT 0,
  `selling_price` bigint(20) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `available_quantity` int(11) NOT NULL DEFAULT 0,
  `source` varchar(50) NOT NULL DEFAULT 'purchase',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Material Dasar', 'material-dasar', 'Bahan bangunan utama seperti pasir, semen, batu, bata, hebel, dan besi beton.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(2, 'Kayu & Atap', 'kayu-atap', 'Material kayu, triplek, kaso, serta penutup atap seperti genteng, asbes, seng, dan terpal.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(3, 'Cat & Finishing', 'cat-finishing', 'Segala jenis cat (tembok/kayu/besi), thinner, pelapis anti bocor (no drop), dan lem.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(4, 'Lantai & Dinding', 'lantai-dinding', 'Penutup lantai dan dinding termasuk keramik, granit, plint, dan lis profil (kuku macan).', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(5, 'Pipa & Listrik', 'pipa-listrik', 'Instalasi air (pipa PVC, kran, toren) dan instalasi listrik (kabel, lampu, saklar).', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(6, 'Paku & Alat', 'paku-alat', 'Barang kecil/receh seperti paku, baut, sekrup, engsel, gembok, dan peralatan tukang.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `address`, `notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Ryley Torp', 'mayer.alvina@example.net', '(530) 516-6745', '214 Padberg View Apt. 836\nSouth Kareem, PA 85543', 'Sapiente atque et doloribus sit.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(2, 'Elenor Hudson III', 'blanda.verlie@example.org', '+1-801-973-7703', '9943 Vanessa Vista Apt. 799\nLake Kristin, IA 07140', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(3, 'Mr. Braden West I', 'yesenia27@example.net', '(814) 595-5524', '869 Kennedi Shores\nWest Marcusfort, OH 40998', 'Quos nihil iusto ipsum id ipsam aspernatur ut esse.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(4, 'Josie Bauch', 'annalise.hahn@example.org', '516.294.7347', '20339 Bertha Ferry Suite 829\nLake Jadyn, SC 67927-0230', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(5, 'Prof. Rae Kulas III', 'lucinda.pagac@example.net', '405-577-0566', '63243 Schoen Route\nMillsmouth, NH 74964', 'Similique laudantium cum exercitationem quo eum.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(6, 'Dr. Maximilian Cormier', 'schinner.katelin@example.com', '(407) 478-7567', '524 Dare Bridge\nPaucekshire, AL 63568-8332', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(7, 'Dr. Darryl Satterfield', 'willie30@example.net', '531.288.4076', '2789 O\'Connell Ferry\nMurazikfort, PA 57895-3451', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(8, 'Prof. Daija Oberbrunner I', 'joana08@example.com', '509.701.7959', '8719 Hayley Branch\nGoodwinchester, SC 61207', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(9, 'Miss Cynthia Harber DVM', 'gulgowski.jeremy@example.com', '971.682.3008', '27621 Bernadine Drive Suite 788\nShanelfort, KS 57225-6463', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(10, 'Emely Wisozk', 'rreichel@example.org', '+17264850953', '72920 Meredith Walks\nLake Arneland, FL 23575', 'Ratione ipsa sint et.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(11, 'Walton Kshlerin', 'retha28@example.net', '530.298.0779', '5446 Russel Bypass Apt. 354\nWest Keaganville, PA 55083', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(12, 'Lelia Blanda', 'dejuan.schaefer@example.com', '657-403-5140', '869 Stroman Lock Suite 752\nEast Heath, CA 71067-3817', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(13, 'Mrs. Deja Gibson I', 'camren.schowalter@example.org', '+18597167100', '966 Myles Hollow Suite 606\nNorth Ravenport, NV 58095', 'Eligendi non dolorem natus consequatur ipsa deserunt nihil.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(14, 'Gerard Heidenreich DVM', 'kuphal.annette@example.com', '720-860-9814', '2481 Carleton Coves Apt. 817\nWillmsport, MD 49570', 'Veritatis accusamus iusto quidem dolore et iusto.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(15, 'Adrian Hand', 'sweissnat@example.org', '+16899193700', '771 Rusty Drive\nO\'Reillyburgh, CT 13164-2901', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(16, 'Daron Zboncak', 'willy15@example.org', '989.740.4216', '11547 Welch Walk Suite 063\nBalistrerimouth, PA 13591-9541', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(17, 'Mrs. Elissa Moore MD', 'anabelle.hagenes@example.net', '1-231-265-4856', '930 Schinner Underpass\nWillview, IA 87983', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(18, 'Mrs. Vincenza Wolf III', 'wschuppe@example.net', '+1-551-859-0505', '182 Braeden Track\nJamirbury, MS 85981', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(19, 'Miss Samantha Walsh', 'blanda.augustine@example.com', '657.790.0714', '123 Elliot Lock Suite 631\nNorth Werner, WA 67171-5360', 'Vitae consequatur nobis neque eum.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(20, 'Arthur Schneider', 'bradtke.louvenia@example.com', '+1.801.320.7195', '837 Casper Extensions\nWest Kennethton, WV 12023-4330', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(21, 'Haylie Wolf Sr.', 'virginia.wehner@example.org', '+15406918806', '306 Hackett Stravenue Suite 283\nWest Brianborough, NE 02188', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(22, 'Chanel Hyatt', 'vena.schroeder@example.net', '+1 (870) 414-6204', '93809 Pierce Cliffs Suite 842\nAnnamarieberg, CO 52839', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(23, 'Christian Gerlach', 'leuschke.stephon@example.net', '+1-541-681-7137', '380 Shanahan Mill Apt. 123\nElainashire, UT 45634', 'Mollitia distinctio est quidem.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(24, 'Alvina Feest', 'waelchi.patrick@example.com', '(828) 885-8048', '799 Taya Stream Suite 333\nMcLaughlinborough, TN 71290', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(25, 'Ophelia Osinski', 'cgrimes@example.net', '1-802-216-0859', '3424 Rudolph Burg\nSouth Nick, SD 50804-9650', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `finance_categories`
--

CREATE TABLE `finance_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'expense',
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `finance_categories`
--

INSERT INTO `finance_categories` (`id`, `name`, `slug`, `type`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Penjualan Produk', 'penjualan-produk', 'income', 'Pendapatan langsung dari penjualan produk toko.', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
(2, 'Layanan Jasa', 'layanan-jasa', 'income', 'Pendapatan dari layanan jasa service atau konsultasi.', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
(3, 'Investasi', 'investasi', 'income', 'Dividen atau bunga dari investasi modal.', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
(4, 'Pendapatan Lain-lain', 'pendapatan-lain-lain', 'income', 'Pendapatan di luar operasional utama.', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
(5, 'Gaji Karyawan', 'gaji-karyawan', 'expense', 'Biaya gaji bulanan dan tunjangan karyawan.', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
(6, 'Sewa Gedung', 'sewa-gedung', 'expense', 'Biaya sewa toko atau gudang operasional.', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
(7, 'Listrik & Air', 'listrik-air', 'expense', 'Tagihan utilitas bulanan.', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
(8, 'Internet & Telepon', 'internet-telepon', 'expense', 'Biaya komunikasi dan koneksi internet.', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
(9, 'Pemasaran & Iklan', 'pemasaran-iklan', 'expense', 'Biaya promosi, iklan sosial media, dan cetak.', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
(10, 'Perawatan & Perbaikan', 'perawatan-perbaikan', 'expense', 'Biaya maintenance aset dan peralatan.', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
(11, 'Transportasi & Logistik', 'transportasi-logistik', 'expense', 'Biaya bensin, pengiriman, dan perjalanan dinas.', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
(12, 'Pembelian Stok', 'pembelian-stok', 'expense', 'Biaya pembelian barang dagangan (HPP).', '2026-06-19 00:58:25', '2026-06-19 00:58:25');

-- --------------------------------------------------------

--
-- Table structure for table `finance_transactions`
--

CREATE TABLE `finance_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(255) NOT NULL,
  `transaction_date` date NOT NULL,
  `finance_category_id` bigint(20) UNSIGNED NOT NULL,
  `amount` bigint(20) NOT NULL,
  `description` text DEFAULT NULL,
  `external_reference` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reference_type` varchar(255) DEFAULT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `purchase_id` bigint(20) UNSIGNED DEFAULT NULL,
  `purchase_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `movement_type` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `quantity_before` int(11) NOT NULL DEFAULT 0,
  `quantity_after` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_01_06_142223_create_customers_table', 1),
(5, '2026_01_07_023726_create_suppliers_table', 1),
(6, '2026_01_07_043653_create_units_table', 1),
(7, '2026_01_07_055728_create_categories_table', 1),
(8, '2026_01_07_062203_create_products_table', 1),
(9, '2026_01_07_081258_create_purchases_table', 1),
(10, '2026_01_07_081259_create_purchase_items_table', 1),
(11, '2026_01_08_030943_create_sales_table', 1),
(12, '2026_01_08_030944_create_sale_items_table', 1),
(13, '2026_02_02_072243_create_finance_categories_table', 1),
(14, '2026_02_02_102421_create_finance_transactions_table', 1),
(15, '2026_02_03_033839_create_settings_table', 1),
(16, '2026_02_03_124644_add_polymorphic_reference_to_finance_transactions_table', 1),
(17, '2026_02_19_064807_add_global_discount_to_sales_table', 1),
(18, '2026_04_24_000001_create_batches_table', 1),
(19, '2026_04_24_000002_create_inventory_logs_table', 1),
(20, '2026_04_24_000003_create_sale_item_batches_table', 1),
(21, '2026_04_24_000004_add_batch_fields_to_purchase_items_table', 1),
(22, '2026_04_24_000005_add_total_cost_to_sale_items_table', 1),
(23, '2026_04_24_000006_seed_legacy_batches_from_products_table', 1),
(24, '2026_04_27_000001_add_item_code_ierp_to_products_table', 1),
(25, '2026_05_07_053752_alter_purchase_date_to_datetime', 1),
(26, '2026_06_18_121900_add_rni_fields_to_sales_table', 1),
(27, '2026_06_18_122000_add_role_to_users_table', 1),
(28, '2026_06_18_151500_add_soft_deletes_to_master_data_tables', 1),
(29, '2026_06_18_151600_create_audit_logs_table', 1),
(30, '2026_06_18_161000_add_rni_requirement_fields', 1),
(31, '2026_06_18_170000_create_storage_locations_table', 1),
(32, '2026_06_18_170100_add_purchase_context_and_storage_location_ids', 1);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `unit_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sku` varchar(50) NOT NULL,
  `item_code_ierp` varchar(100) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `physical_form` varchar(50) DEFAULT NULL,
  `purchase_price` bigint(20) NOT NULL,
  `selling_price` bigint(20) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `unit_id`, `supplier_id`, `sku`, `item_code_ierp`, `name`, `physical_form`, `purchase_price`, `selling_price`, `quantity`, `min_stock`, `is_active`, `description`, `notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 6, NULL, 'P.260619.UL0Q', NULL, 'Semen Tiga Roda (50kg)', NULL, 59500, 70000, 93, 5, 1, 'Stok tersedia untuk Semen Tiga Roda (50kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(2, 1, 6, NULL, 'P.260619.TARV', NULL, 'Semen Dynamix (50kg)', NULL, 55250, 65000, 96, 5, 1, 'Stok tersedia untuk Semen Dynamix (50kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(3, 1, 6, NULL, 'P.260619.CYNZ', NULL, 'Semen Rajawali (50kg)', NULL, 50150, 59000, 10, 5, 1, 'Stok tersedia untuk Semen Rajawali (50kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(4, 1, 6, NULL, 'P.260619.CWAE', NULL, 'Semen Merdeka (50kg)', NULL, 45475, 53500, 71, 5, 1, 'Stok tersedia untuk Semen Merdeka (50kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(5, 1, 6, NULL, 'P.260619.4STN', NULL, 'Semen Best (50kg)', NULL, 44200, 52000, 58, 5, 1, 'Stok tersedia untuk Semen Best (50kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(6, 1, 6, NULL, 'P.260619.C4IL', NULL, 'Acian TR-30 Tiga Roda (40kg)', NULL, 93500, 110000, 12, 5, 1, 'Stok tersedia untuk Acian TR-30 Tiga Roda (40kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(7, 1, 6, NULL, 'P.260619.JDEC', NULL, 'Semen Putih Tiga Roda (40kg)', NULL, 93500, 110000, 37, 5, 1, 'Stok tersedia untuk Semen Putih Tiga Roda (40kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(8, 1, 6, NULL, 'P.260619.DCEL', NULL, 'Semen Hebel SCG (40kg)', NULL, 55250, 65000, 70, 5, 1, 'Stok tersedia untuk Semen Hebel SCG (40kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(9, 1, 6, NULL, 'P.260619.WPIJ', NULL, 'Acian Plester Putih Maxson MC-270', NULL, 53125, 62500, 22, 5, 1, 'Stok tersedia untuk Acian Plester Putih Maxson MC-270', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(10, 1, 6, NULL, 'P.260619.PQ7L', NULL, 'Casting / Tepung Gipsum (20kg)', NULL, 31450, 37000, 89, 5, 1, 'Stok tersedia untuk Casting / Tepung Gipsum (20kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(11, 1, 6, NULL, 'P.260619.RDTF', NULL, 'Kapur Mill (20kg)', NULL, 15300, 18000, 68, 5, 1, 'Stok tersedia untuk Kapur Mill (20kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(12, 1, 6, NULL, 'P.260619.4YGW', NULL, 'Kompon A+ (20kg)', NULL, 51000, 60000, 87, 5, 1, 'Stok tersedia untuk Kompon A+ (20kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(13, 1, 6, NULL, 'P.260619.LH1O', NULL, 'TR-15 Tiga Roda Perekat Hebel (40kg)', NULL, 68000, 80000, 23, 5, 1, 'Stok tersedia untuk TR-15 Tiga Roda Perekat Hebel (40kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(14, 1, 6, NULL, 'P.260619.UM1W', NULL, 'TR-20 Tiga Roda Plester Hebel (40kg)', NULL, 68000, 80000, 82, 5, 1, 'Stok tersedia untuk TR-20 Tiga Roda Plester Hebel (40kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(15, 1, 6, NULL, 'P.260619.K0IO', NULL, 'MU-301 Tiga Roda Plester Hebel (40kg)', NULL, 82450, 97000, 11, 5, 1, 'Stok tersedia untuk MU-301 Tiga Roda Plester Hebel (40kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(16, 1, 9, NULL, 'P.260619.E6BU', NULL, 'Pasir Jumbo (1 Truk)', NULL, 935000, 1100000, 21, 5, 1, 'Stok tersedia untuk Pasir Jumbo (1 Truk)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(17, 1, 9, NULL, 'P.260619.2QOI', NULL, 'Pasir Cor (1 Truk)', NULL, 1147500, 1350000, 79, 5, 1, 'Stok tersedia untuk Pasir Cor (1 Truk)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(18, 1, 9, NULL, 'P.260619.Z0S4', NULL, 'Pasir Cuci (1 Truk)', NULL, 1275000, 1500000, 58, 5, 1, 'Stok tersedia untuk Pasir Cuci (1 Truk)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(19, 1, 9, NULL, 'P.260619.PIPR', NULL, 'Batu Pondasi Hitam (1 Truk)', NULL, 1020000, 1200000, 38, 5, 1, 'Stok tersedia untuk Batu Pondasi Hitam (1 Truk)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(20, 1, 9, NULL, 'P.260619.H6HW', NULL, 'Batu Pondasi Putih (1 Truk)', NULL, 765000, 900000, 37, 5, 1, 'Stok tersedia untuk Batu Pondasi Putih (1 Truk)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(21, 1, 9, NULL, 'P.260619.HS3I', NULL, 'Batu Split - Rata Bak', NULL, 1530000, 1800000, 46, 5, 1, 'Stok tersedia untuk Batu Split - Rata Bak', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(22, 1, 9, NULL, 'P.260619.24P9', NULL, 'Batu Split - Full Bak', NULL, 1700000, 2000000, 77, 5, 1, 'Stok tersedia untuk Batu Split - Full Bak', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(23, 1, 9, NULL, 'P.260619.A2Y5', NULL, 'Sirtu Urug (1 Truk)', NULL, 595000, 700000, 97, 5, 1, 'Stok tersedia untuk Sirtu Urug (1 Truk)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(24, 1, 9, NULL, 'P.260619.PLGS', NULL, 'Tanah Urug (1 Truk)', NULL, 467500, 550000, 82, 5, 1, 'Stok tersedia untuk Tanah Urug (1 Truk)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(25, 1, 1, NULL, 'P.260619.UUJ6', NULL, 'Bata (K) - Kecil', NULL, 638, 750, 13, 5, 1, 'Stok tersedia untuk Bata (K) - Kecil', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(26, 1, 1, NULL, 'P.260619.S0IN', NULL, 'Bata (S) - Sedang', NULL, 808, 950, 88, 5, 1, 'Stok tersedia untuk Bata (S) - Sedang', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(27, 1, 1, NULL, 'P.260619.CPYP', NULL, 'Bata (B) - Besar', NULL, 850, 1000, 97, 5, 1, 'Stok tersedia untuk Bata (B) - Besar', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(28, 2, 5, NULL, 'P.260619.8M7Z', NULL, 'Triplek 3mm Tunas', NULL, 39100, 46000, 99, 5, 1, 'Stok tersedia untuk Triplek 3mm Tunas', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(29, 2, 5, NULL, 'P.260619.N1A2', NULL, 'Triplek 4mm Tunas', NULL, 45475, 53500, 34, 5, 1, 'Stok tersedia untuk Triplek 4mm Tunas', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(30, 2, 5, NULL, 'P.260619.EF1O', NULL, 'Triplek 6mm Tunas', NULL, 57375, 67500, 73, 5, 1, 'Stok tersedia untuk Triplek 6mm Tunas', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(31, 2, 5, NULL, 'P.260619.DIMW', NULL, 'Triplek 6mm MC', NULL, 80750, 95000, 95, 5, 1, 'Stok tersedia untuk Triplek 6mm MC', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(32, 2, 5, NULL, 'P.260619.UZWA', NULL, 'Triplek 8mm MC', NULL, 69700, 82000, 99, 5, 1, 'Stok tersedia untuk Triplek 8mm MC', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(33, 2, 5, NULL, 'P.260619.K1IN', NULL, 'Triplek 12mm MC', NULL, 114750, 135000, 35, 5, 1, 'Stok tersedia untuk Triplek 12mm MC', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(34, 2, 5, NULL, 'P.260619.VHXC', NULL, 'Triplek 18mm MC', NULL, 178500, 210000, 22, 5, 1, 'Stok tersedia untuk Triplek 18mm MC', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(35, 2, 5, NULL, 'P.260619.MBQP', NULL, 'Triplek 9mm UT Better', NULL, 111350, 131000, 48, 5, 1, 'Stok tersedia untuk Triplek 9mm UT Better', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(36, 2, 5, NULL, 'P.260619.Z61W', NULL, 'Triplek 12mm UT Better', NULL, 136000, 160000, 41, 5, 1, 'Stok tersedia untuk Triplek 12mm UT Better', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(37, 2, 5, NULL, 'P.260619.UJGW', NULL, 'Triplek 15mm UT Better', NULL, 158950, 187000, 51, 5, 1, 'Stok tersedia untuk Triplek 15mm UT Better', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(38, 2, 5, NULL, 'P.260619.QJI6', NULL, 'Triplek 18mm UT Better', NULL, 195500, 230000, 54, 5, 1, 'Stok tersedia untuk Triplek 18mm UT Better', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(39, 2, 5, NULL, 'P.260619.A8KY', NULL, 'Triplek 3mm Alba', NULL, 36125, 42500, 14, 5, 1, 'Stok tersedia untuk Triplek 3mm Alba', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(40, 2, 5, NULL, 'P.260619.YLQD', NULL, 'Triplek 4mm Alba', NULL, 44625, 52500, 24, 5, 1, 'Stok tersedia untuk Triplek 4mm Alba', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(41, 2, 5, NULL, 'P.260619.EWWC', NULL, 'Triplek Melaminto Putih 3mm', NULL, 108375, 127500, 97, 5, 1, 'Stok tersedia untuk Triplek Melaminto Putih 3mm', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(42, 2, 5, NULL, 'P.260619.OELX', NULL, 'Triplek 15mm X Brasi', NULL, 174250, 205000, 26, 5, 1, 'Stok tersedia untuk Triplek 15mm X Brasi', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(43, 2, 1, NULL, 'P.260619.VHZZ', NULL, 'Terpal 2x3', NULL, 34000, 40000, 31, 5, 1, 'Stok tersedia untuk Terpal 2x3', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(44, 2, 1, NULL, 'P.260619.SNVI', NULL, 'Terpal 3x3', NULL, 51000, 60000, 95, 5, 1, 'Stok tersedia untuk Terpal 3x3', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(45, 2, 1, NULL, 'P.260619.ZK8H', NULL, 'Terpal 4x4', NULL, 79900, 94000, 89, 5, 1, 'Stok tersedia untuk Terpal 4x4', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(46, 2, 1, NULL, 'P.260619.6SWB', NULL, 'Terpal 4x6', NULL, 116875, 137500, 83, 5, 1, 'Stok tersedia untuk Terpal 4x6', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(47, 2, 1, NULL, 'P.260619.DCXA', NULL, 'Terpal 5x7', NULL, 165750, 195000, 18, 5, 1, 'Stok tersedia untuk Terpal 5x7', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(48, 2, 1, NULL, 'P.260619.2J3Q', NULL, 'Terpal 5x6', NULL, 178500, 210000, 41, 5, 1, 'Stok tersedia untuk Terpal 5x6', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(49, 4, 7, NULL, 'P.260619.ED4Y', NULL, 'Kuku Macan Fujimi (Dus)', NULL, 106250, 125000, 12, 5, 1, 'Stok tersedia untuk Kuku Macan Fujimi (Dus)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(50, 4, 3, NULL, 'P.260619.CHGK', NULL, 'Kuku Macan Fujimi (Meter)', NULL, 6375, 7500, 92, 5, 1, 'Stok tersedia untuk Kuku Macan Fujimi (Meter)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(51, 4, 7, NULL, 'P.260619.2ZVT', NULL, 'Kuku Macan Viva (Dus)', NULL, 63750, 75000, 91, 5, 1, 'Stok tersedia untuk Kuku Macan Viva (Dus)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(52, 4, 3, NULL, 'P.260619.I9UE', NULL, 'Kuku Macan Viva (Meter)', NULL, 4250, 5000, 46, 5, 1, 'Stok tersedia untuk Kuku Macan Viva (Meter)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(53, 4, 7, NULL, 'P.260619.X51S', NULL, 'Kuku Macan Marbel KW (Dus)', NULL, 140250, 165000, 32, 5, 1, 'Stok tersedia untuk Kuku Macan Marbel KW (Dus)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(54, 4, 3, NULL, 'P.260619.OPUM', NULL, 'Kuku Macan Marbel KW (Meter)', NULL, 8500, 10000, 76, 5, 1, 'Stok tersedia untuk Kuku Macan Marbel KW (Meter)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(55, 4, 7, NULL, 'P.260619.283K', NULL, 'Kuku Macan Silver/Gold (Dus)', NULL, 140250, 165000, 44, 5, 1, 'Stok tersedia untuk Kuku Macan Silver/Gold (Dus)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(56, 4, 3, NULL, 'P.260619.TO0G', NULL, 'Kuku Macan Silver/Gold (Meter)', NULL, 8500, 10000, 32, 5, 1, 'Stok tersedia untuk Kuku Macan Silver/Gold (Meter)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(57, 3, 6, NULL, 'P.260619.HNRG', NULL, 'Lemkra 5kg 101 (Pasang Kramik)', NULL, 34000, 40000, 79, 5, 1, 'Stok tersedia untuk Lemkra 5kg 101 (Pasang Kramik)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(58, 3, 6, NULL, 'P.260619.KAUN', NULL, 'Lemkra 5kg 111 (Dinding)', NULL, 38250, 45000, 43, 5, 1, 'Stok tersedia untuk Lemkra 5kg 111 (Dinding)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(59, 3, 6, NULL, 'P.260619.PIFT', NULL, 'Lemkra 5kg 105 (Beton)', NULL, 59500, 70000, 35, 5, 1, 'Stok tersedia untuk Lemkra 5kg 105 (Beton)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(60, 2, 4, NULL, 'P.260619.FAPQ', NULL, 'Lis Gipsum 13cm - Mata Sapi', NULL, 14025, 16500, 48, 5, 1, 'Stok tersedia untuk Lis Gipsum 13cm - Mata Sapi', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(61, 2, 4, NULL, 'P.260619.WAKW', NULL, 'Lis Gipsum 13cm - Mawar Besar', NULL, 14025, 16500, 75, 5, 1, 'Stok tersedia untuk Lis Gipsum 13cm - Mawar Besar', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(62, 2, 4, NULL, 'P.260619.WKF2', NULL, 'Lis Gipsum 13cm - Tombak Besar', NULL, 14025, 16500, 99, 5, 1, 'Stok tersedia untuk Lis Gipsum 13cm - Tombak Besar', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(63, 2, 4, NULL, 'P.260619.9WX4', NULL, 'Lis Gipsum 12cm - Minimalis Besar', NULL, 12750, 15000, 85, 5, 1, 'Stok tersedia untuk Lis Gipsum 12cm - Minimalis Besar', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(64, 2, 4, NULL, 'P.260619.W6IZ', NULL, 'Lis Gipsum 12cm - Kangkung', NULL, 12750, 15000, 44, 5, 1, 'Stok tersedia untuk Lis Gipsum 12cm - Kangkung', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(65, 2, 4, NULL, 'P.260619.PPLE', NULL, 'Lis Gipsum 12cm - Kupat', NULL, 12750, 15000, 49, 5, 1, 'Stok tersedia untuk Lis Gipsum 12cm - Kupat', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(66, 2, 4, NULL, 'P.260619.QIVX', NULL, 'Lis Gipsum 12cm - Bendera', NULL, 12750, 15000, 38, 5, 1, 'Stok tersedia untuk Lis Gipsum 12cm - Bendera', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(67, 2, 4, NULL, 'P.260619.0AVH', NULL, 'Lis Gipsum 8cm - Minimalis Kecil', NULL, 11050, 13000, 61, 5, 1, 'Stok tersedia untuk Lis Gipsum 8cm - Minimalis Kecil', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(68, 2, 4, NULL, 'P.260619.Y6ZY', NULL, 'Lis Gipsum 8cm - Renda', NULL, 11050, 13000, 35, 5, 1, 'Stok tersedia untuk Lis Gipsum 8cm - Renda', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(69, 2, 4, NULL, 'P.260619.NEUH', NULL, 'Lis Gipsum 8cm - Piano', NULL, 11050, 13000, 67, 5, 1, 'Stok tersedia untuk Lis Gipsum 8cm - Piano', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(70, 2, 4, NULL, 'P.260619.KCCW', NULL, 'Lis Biding 5cm - Polos Kecil', NULL, 11050, 13000, 44, 5, 1, 'Stok tersedia untuk Lis Biding 5cm - Polos Kecil', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(71, 2, 4, NULL, 'P.260619.RTZA', NULL, 'Lis Biding 5cm - Tambang', NULL, 11050, 13000, 55, 5, 1, 'Stok tersedia untuk Lis Biding 5cm - Tambang', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(72, 2, 4, NULL, 'P.260619.H9W4', NULL, 'Lis Biding 5cm - Melati', NULL, 11050, 13000, 13, 5, 1, 'Stok tersedia untuk Lis Biding 5cm - Melati', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(73, 2, 1, NULL, 'P.260619.SLNL', NULL, 'Tabok Lampu - Bulat Kecil', NULL, 34000, 40000, 42, 5, 1, 'Stok tersedia untuk Tabok Lampu - Bulat Kecil', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(74, 2, 1, NULL, 'P.260619.LCEU', NULL, 'Tabok Lampu - Sawi Bintang', NULL, 42500, 50000, 22, 5, 1, 'Stok tersedia untuk Tabok Lampu - Sawi Bintang', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(75, 2, 1, NULL, 'P.260619.MJHV', NULL, 'Tabok Lampu - Sarang Tawon', NULL, 42500, 50000, 72, 5, 1, 'Stok tersedia untuk Tabok Lampu - Sarang Tawon', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(76, 2, 1, NULL, 'P.260619.RKLY', NULL, 'Tabok Lampu - Oreo/Kotak', NULL, 42500, 50000, 91, 5, 1, 'Stok tersedia untuk Tabok Lampu - Oreo/Kotak', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(77, 2, 1, NULL, 'P.260619.2UI2', NULL, 'Tabok Lampu - Oval Kupu', NULL, 42500, 50000, 95, 5, 1, 'Stok tersedia untuk Tabok Lampu - Oval Kupu', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(78, 2, 1, NULL, 'P.260619.RECY', NULL, 'Tabok Lampu - Oval Batik', NULL, 59500, 70000, 87, 5, 1, 'Stok tersedia untuk Tabok Lampu - Oval Batik', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(79, 2, 1, NULL, 'P.260619.3AKB', NULL, 'Tabok Lampu - Batik Besar', NULL, 68000, 80000, 47, 5, 1, 'Stok tersedia untuk Tabok Lampu - Batik Besar', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(80, 2, 1, NULL, 'P.260619.HBEV', NULL, 'Tabok Lampu - Segi 8 Besar', NULL, 68000, 80000, 76, 5, 1, 'Stok tersedia untuk Tabok Lampu - Segi 8 Besar', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(81, 6, 2, NULL, 'P.260619.W2HF', NULL, 'Paku Kayu Ukuran 3/4 inch', NULL, 21250, 25000, 71, 5, 1, 'Stok tersedia untuk Paku Kayu Ukuran 3/4 inch', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(82, 6, 2, NULL, 'P.260619.LT1T', NULL, 'Paku Kayu Ukuran 1 inch', NULL, 20400, 24000, 49, 5, 1, 'Stok tersedia untuk Paku Kayu Ukuran 1 inch', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(83, 6, 2, NULL, 'P.260619.MAUR', NULL, 'Paku Kayu Ukuran 1-1/4 inch', NULL, 19550, 23000, 67, 5, 1, 'Stok tersedia untuk Paku Kayu Ukuran 1-1/4 inch', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(84, 6, 2, NULL, 'P.260619.FCB0', NULL, 'Paku Kayu Ukuran 1-1/2 inch (4cm)', NULL, 17850, 21000, 50, 5, 1, 'Stok tersedia untuk Paku Kayu Ukuran 1-1/2 inch (4cm)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(85, 6, 2, NULL, 'P.260619.VF8K', NULL, 'Paku Kayu Ukuran 5cm', NULL, 17000, 20000, 55, 5, 1, 'Stok tersedia untuk Paku Kayu Ukuran 5cm', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(86, 6, 2, NULL, 'P.260619.XMK8', NULL, 'Paku Kayu Ukuran 7cm', NULL, 17000, 20000, 41, 5, 1, 'Stok tersedia untuk Paku Kayu Ukuran 7cm', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(87, 6, 2, NULL, 'P.260619.EXFO', NULL, 'Paku Kayu Ukuran 10cm', NULL, 17000, 20000, 70, 5, 1, 'Stok tersedia untuk Paku Kayu Ukuran 10cm', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(88, 6, 2, NULL, 'P.260619.5IH6', NULL, 'Paku Kayu Ukuran 12cm', NULL, 17000, 20000, 55, 5, 1, 'Stok tersedia untuk Paku Kayu Ukuran 12cm', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(89, 6, 2, NULL, 'P.260619.W36U', NULL, 'Paku GRC / Jalusi', NULL, 21250, 25000, 46, 5, 1, 'Stok tersedia untuk Paku GRC / Jalusi', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(90, 6, 7, NULL, 'P.260619.B4HL', NULL, 'Paku Kayu 1 Dus (Isi 30kg)', NULL, 312800, 368000, 100, 5, 1, 'Stok tersedia untuk Paku Kayu 1 Dus (Isi 30kg)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(91, 6, 8, NULL, 'P.260619.OXLH', NULL, 'Kawat Tali / Bendrat (PT Family)', NULL, 255000, 300000, 56, 5, 1, 'Stok tersedia untuk Kawat Tali / Bendrat (PT Family)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL),
(92, 6, 8, NULL, 'P.260619.CEHJ', NULL, 'Kawat Tali / Bendrat (Biasa)', NULL, 221000, 260000, 65, 5, 1, 'Stok tersedia untuk Kawat Tali / Bendrat (Biasa)', NULL, '2026-06-19 00:58:25', '2026-06-19 00:58:25', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_number` varchar(255) DEFAULT NULL,
  `supplier_id` bigint(20) UNSIGNED DEFAULT NULL,
  `purchase_date` datetime NOT NULL,
  `due_date` date DEFAULT NULL,
  `total` bigint(20) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `entry_context` varchar(50) NOT NULL DEFAULT 'legacy_purchase',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `purchase_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `storage_location` varchar(150) DEFAULT NULL,
  `storage_location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` bigint(20) NOT NULL,
  `selling_price` bigint(20) DEFAULT NULL,
  `subtotal` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_number` varchar(255) NOT NULL,
  `transaction_type` varchar(255) NOT NULL DEFAULT 'sale',
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `sale_date` datetime NOT NULL,
  `usage_date` datetime DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `subtotal` bigint(20) NOT NULL DEFAULT 0,
  `global_discount` bigint(20) NOT NULL DEFAULT 0,
  `total_discount` bigint(20) NOT NULL DEFAULT 0,
  `total` bigint(20) NOT NULL DEFAULT 0,
  `cash_received` bigint(20) NOT NULL DEFAULT 0,
  `change` bigint(20) NOT NULL DEFAULT 0,
  `payment_method` varchar(255) NOT NULL DEFAULT 'cash',
  `purpose` varchar(255) DEFAULT NULL,
  `formula` varchar(255) DEFAULT NULL,
  `project` varchar(255) DEFAULT NULL,
  `requested_by` varchar(255) DEFAULT NULL,
  `issued_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL,
  `cost_price` bigint(20) NOT NULL,
  `total_cost` bigint(20) NOT NULL DEFAULT 0,
  `unit_price` bigint(20) NOT NULL,
  `discount` bigint(20) NOT NULL DEFAULT 0,
  `final_price` bigint(20) NOT NULL,
  `subtotal` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_item_batches`
--

CREATE TABLE `sale_item_batches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_item_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` bigint(20) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('AucAydeVqicEFH93ZBe60L5eibZi4FBqLyEHpvLM', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'YTo2OntzOjY6Il90b2tlbiI7czo0MDoiSldYTEx4eUllM2V4ZVdpcTJvMmIySDdaenVSbUM4eFBRcm9LbVJTRyI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjM3OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvbWFzdGVyL3Byb2R1Y3RzIjtzOjU6InJvdXRlIjtzOjE0OiJwcm9kdWN0cy5pbmRleCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7czoyMjoiUEhQREVCVUdCQVJfU1RBQ0tfREFUQSI7YTowOnt9fQ==', 1781856462);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key`, `value`, `created_at`, `updated_at`) VALUES
('batch_near_expiry_days', '30', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('currency_decimal_separator', ',', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('currency_fraction_digits', '0', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('currency_position', 'left', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('currency_symbol', 'Rp', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('currency_thousand_separator', '.', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('module_finance_enabled', '1', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('module_materials_enabled', '1', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('module_purchases_enabled', '1', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('module_reports_enabled', '1', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('module_rni_enabled', '1', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('module_sales_enabled', '1', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('module_users_enabled', '1', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('opening_balance_amount', '10000000', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('opening_balance_date', '2026-01-01', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('store_address', 'Pamijahan, Kec. Plumbon, Kabupaten Cirebon, Jawa Barat 45155', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('store_name', 'TB. Kencana Pamijahan', '2026-06-19 00:58:25', '2026-06-19 00:58:25'),
('store_phone', '081234567890', '2026-06-19 00:58:25', '2026-06-19 00:58:25');

-- --------------------------------------------------------

--
-- Table structure for table `storage_locations`
--

CREATE TABLE `storage_locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `email`, `phone`, `address`, `notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Robel Group', 'Alia Herzog DVM', 'wiza.samara@kshlerin.info', '+1 (586) 635-3001', '724 Willms Harbors Suite 493\nUllrichland, OR 23135-2989', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(2, 'Dare-Nienow', 'Eloise McGlynn', 'wyatt.wisoky@stamm.info', '629-775-6031', '61920 Vernice Lock Suite 653\nEast Devontefort, MS 42832', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(3, 'Conn-Casper', 'Prof. Vicky Hayes', 'quitzon.albertha@rolfson.info', '(224) 731-2375', '675 Elvera Crest Apt. 057\nNorth Destanyberg, TN 84534-8481', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(4, 'Blick, Krajcik and Carroll', 'Dr. Lyric Wyman V', 'jhackett@schaden.net', '+1 (458) 237-9269', '145 Marvin Drive Apt. 227\nNew Alexzander, NM 46268', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(5, 'Glover-Rice', 'Prof. Brent Bogisich', 'torphy.reina@reichert.com', '+19282168332', '42238 Constance Lane Suite 253\nNorth Polly, OH 36509-1036', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(6, 'Bauch Group', 'Prof. Modesto Corwin I', 'zella.casper@nitzsche.com', '+1-754-340-8270', '3955 Spinka Island Suite 860\nPourosland, WA 02321', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(7, 'McCullough-Collier', 'Golden Wehner', 'evie.schroeder@wolf.com', '484.957.2607', '32202 Lindgren Mills Apt. 501\nElwinstad, NV 90073-9773', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(8, 'Lubowitz, Ratke and Carroll', 'Melyssa Morissette', 'green.marcos@reinger.com', '+1 (580) 217-5927', '610 Champlin Island\nRossieborough, MS 27878', 'Rerum cupiditate rerum eum dolorum sapiente repellendus.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(9, 'Waters, Goldner and Buckridge', 'Jaren Heidenreich', 'wtowne@walsh.com', '+1-231-362-2991', '9370 Armstrong Forks Suite 611\nNew Joanie, WY 84928', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(10, 'Watsica PLC', 'Ms. Bernice Champlin', 'hstracke@wisoky.biz', '(661) 795-3438', '82905 Brittany Greens Suite 542\nBartonbury, IA 40722-8686', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(11, 'Collier-Kunze', 'Dr. Clara Yundt', 'derick76@pacocha.com', '1-267-999-5064', '568 Macy Field\nEast Columbus, MS 81463-3300', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(12, 'Johns LLC', 'Myles Boyer MD', 'uemmerich@turcotte.com', '(443) 456-8194', '5629 Dashawn Point Suite 885\nCoratown, AZ 55919-0697', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(13, 'Dickinson PLC', 'Gage Reynolds', 'reginald04@terry.com', '919.845.1398', '1324 Purdy Village Apt. 967\nLolitaville, NE 27743', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(14, 'Rosenbaum-Simonis', 'Rachael Kessler', 'uhudson@hill.com', '906-998-6461', '594 McGlynn Underpass Suite 377\nEast Dillan, DE 15466', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(15, 'Greenholt-Koch', 'Mustafa Crist', 'kirsten00@reinger.net', '+1-762-983-7544', '435 Donnelly Springs\nPort Dedricview, TX 90527', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(16, 'Herman-Ondricka', 'Dr. Torrey Pagac', 'renner.scarlett@oreilly.biz', '445.372.9065', '4128 Kihn Plaza\nLinniebury, TN 22441', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(17, 'Waters, Weimann and Kuvalis', 'Audra Schinner', 'bailee00@mayer.com', '(352) 243-9578', '206 Norberto Falls\nFaheyville, MN 46623-2418', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(18, 'Wisozk and Sons', 'Neoma Nader IV', 'jonatan43@treutel.com', '442-497-0745', '552 Evie Junctions Suite 865\nRitchiebury, ND 59276', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(19, 'Ziemann Group', 'Dannie Weber', 'dankunding@grimes.org', '+1 (606) 291-1894', '4339 Kub Valley\nRubyeport, OH 95825', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(20, 'Hauck, Rau and Robel', 'Cristal Schuppe', 'kris.meredith@oconnell.com', '+1-207-260-7554', '550 Tillman Prairie Apt. 261\nNew Liamborough, LA 55015-0635', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(21, 'Bruen-Boyer', 'Carter Ullrich', 'jabbott@hermann.com', '+1 (212) 895-6473', '631 Roderick Brooks\nLake Brandoside, NJ 91130', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(22, 'Kunze, Zieme and Hagenes', 'Rosalyn Bins', 'randal.ward@prohaska.com', '1-959-625-1788', '52437 Faustino Village\nLake Dannyfort, MD 99978-6905', 'Praesentium molestiae dignissimos nihil nulla sapiente aut.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(23, 'Block, Kreiger and Stroman', 'Ms. Burnice Wintheiser', 'orn.khalil@kerluke.com', '248.560.3976', '4647 Reilly Plaza\nPfannerstillborough, IL 54145-4515', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(24, 'Bechtelar-Dickens', 'Ollie Kiehn', 'vterry@rolfson.com', '+15417469692', '824 Morar Place Apt. 692\nEast Alessandraton, SC 66530-7238', 'Iusto voluptates magnam aut corrupti aperiam consequatur.', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(25, 'Spinka-Dare', 'Marielle Trantow Jr.', 'kip.hauck@beer.org', '669.318.3895', '6248 Sporer Divide Suite 860\nLake Quentinfurt, NE 55009-6882', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `name`, `symbol`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Pcs', 'pcs', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(2, 'Kilogram', 'kg', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(3, 'Meter', 'm', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(4, 'Batang', 'btg', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(5, 'Lembar', 'lbr', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(6, 'Sak', 'sak', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(7, 'Dus', 'dus', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(8, 'Roll', 'roll', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(9, 'Rit', 'rit', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL),
(10, 'Liter', 'ltr', '2026-06-19 00:58:24', '2026-06-19 00:58:24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'admin_rni',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `role`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin', 'admin@admin.com', 'admin_rni', NULL, '$2y$12$Am/xngnN88qJePtS6InPtu03y4q.owcDxmvFf66y7OXb.mSi2shEK', NULL, '2026-06-19 00:58:24', '2026-06-19 00:58:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `audit_logs_user_id_foreign` (`user_id`),
  ADD KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  ADD KEY `audit_logs_action_index` (`action`);

--
-- Indexes for table `batches`
--
ALTER TABLE `batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batches_batch_number_unique` (`batch_number`),
  ADD KEY `batches_purchase_id_foreign` (`purchase_id`),
  ADD KEY `batches_purchase_item_id_foreign` (`purchase_item_id`),
  ADD KEY `batches_product_id_available_quantity_index` (`product_id`,`available_quantity`),
  ADD KEY `batches_product_id_expiry_date_received_at_index` (`product_id`,`expiry_date`,`received_at`),
  ADD KEY `batches_expiry_date_index` (`expiry_date`),
  ADD KEY `batches_received_at_index` (`received_at`),
  ADD KEY `batches_source_index` (`source`),
  ADD KEY `batches_storage_location_index` (`storage_location`),
  ADD KEY `batches_storage_location_id_foreign` (`storage_location_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categories_slug_unique` (`slug`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customers_name_index` (`name`),
  ADD KEY `customers_email_index` (`email`),
  ADD KEY `customers_phone_index` (`phone`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `finance_categories`
--
ALTER TABLE `finance_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `finance_categories_slug_unique` (`slug`);

--
-- Indexes for table `finance_transactions`
--
ALTER TABLE `finance_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `finance_transactions_code_unique` (`code`),
  ADD KEY `finance_transactions_finance_category_id_foreign` (`finance_category_id`),
  ADD KEY `finance_transactions_created_by_foreign` (`created_by`),
  ADD KEY `finance_transactions_reference_type_reference_id_index` (`reference_type`,`reference_id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_logs_batch_id_foreign` (`batch_id`),
  ADD KEY `inventory_logs_purchase_id_foreign` (`purchase_id`),
  ADD KEY `inventory_logs_purchase_item_id_foreign` (`purchase_item_id`),
  ADD KEY `inventory_logs_sale_id_foreign` (`sale_id`),
  ADD KEY `inventory_logs_sale_item_id_foreign` (`sale_item_id`),
  ADD KEY `inventory_logs_product_id_movement_type_index` (`product_id`,`movement_type`),
  ADD KEY `inventory_logs_movement_type_index` (`movement_type`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `products_sku_unique` (`sku`),
  ADD KEY `products_category_id_foreign` (`category_id`),
  ADD KEY `products_unit_id_foreign` (`unit_id`),
  ADD KEY `products_name_index` (`name`),
  ADD KEY `products_item_code_ierp_index` (`item_code_ierp`),
  ADD KEY `products_supplier_id_foreign` (`supplier_id`),
  ADD KEY `products_physical_form_index` (`physical_form`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `purchases_invoice_number_unique` (`invoice_number`),
  ADD KEY `purchases_created_by_foreign` (`created_by`),
  ADD KEY `purchases_purchase_date_index` (`purchase_date`),
  ADD KEY `purchases_status_index` (`status`),
  ADD KEY `purchases_supplier_id_foreign` (`supplier_id`),
  ADD KEY `purchases_entry_context_index` (`entry_context`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_items_product_id_foreign` (`product_id`),
  ADD KEY `purchase_items_purchase_id_product_id_index` (`purchase_id`,`product_id`),
  ADD KEY `purchase_items_batch_number_index` (`batch_number`),
  ADD KEY `purchase_items_expiry_date_index` (`expiry_date`),
  ADD KEY `purchase_items_storage_location_index` (`storage_location`),
  ADD KEY `purchase_items_storage_location_id_foreign` (`storage_location_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sales_invoice_number_unique` (`invoice_number`),
  ADD KEY `sales_customer_id_foreign` (`customer_id`),
  ADD KEY `sales_created_by_foreign` (`created_by`),
  ADD KEY `sales_sale_date_index` (`sale_date`),
  ADD KEY `sales_status_index` (`status`),
  ADD KEY `sales_issued_by_foreign` (`issued_by`),
  ADD KEY `sales_transaction_type_index` (`transaction_type`),
  ADD KEY `sales_usage_date_index` (`usage_date`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_items_sale_id_foreign` (`sale_id`),
  ADD KEY `sale_items_product_id_foreign` (`product_id`);

--
-- Indexes for table `sale_item_batches`
--
ALTER TABLE `sale_item_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sale_item_batches_sale_item_id_batch_id_unique` (`sale_item_id`,`batch_id`),
  ADD KEY `sale_item_batches_batch_id_foreign` (`batch_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `storage_locations`
--
ALTER TABLE `storage_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `storage_locations_code_unique` (`code`),
  ADD KEY `storage_locations_parent_id_foreign` (`parent_id`),
  ADD KEY `storage_locations_name_is_active_index` (`name`,`is_active`),
  ADD KEY `storage_locations_type_index` (`type`),
  ADD KEY `storage_locations_is_active_index` (`is_active`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `suppliers_name_index` (`name`),
  ADD KEY `suppliers_contact_person_index` (`contact_person`),
  ADD KEY `suppliers_email_index` (`email`),
  ADD KEY `suppliers_phone_index` (`phone`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `units_name_unique` (`name`),
  ADD UNIQUE KEY `units_symbol_unique` (`symbol`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_username_unique` (`username`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `finance_categories`
--
ALTER TABLE `finance_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `finance_transactions`
--
ALTER TABLE `finance_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_item_batches`
--
ALTER TABLE `sale_item_batches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `storage_locations`
--
ALTER TABLE `storage_locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `batches`
--
ALTER TABLE `batches`
  ADD CONSTRAINT `batches_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `batches_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `batches_purchase_item_id_foreign` FOREIGN KEY (`purchase_item_id`) REFERENCES `purchase_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `batches_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `finance_transactions`
--
ALTER TABLE `finance_transactions`
  ADD CONSTRAINT `finance_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `finance_transactions_finance_category_id_foreign` FOREIGN KEY (`finance_category_id`) REFERENCES `finance_categories` (`id`);

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `inventory_logs_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_logs_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `inventory_logs_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_logs_purchase_item_id_foreign` FOREIGN KEY (`purchase_item_id`) REFERENCES `purchase_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_logs_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_logs_sale_item_id_foreign` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `products_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `products_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`);

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchases_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `purchase_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `purchase_items_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_items_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `sales_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `sales_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `sale_items_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`);

--
-- Constraints for table `sale_item_batches`
--
ALTER TABLE `sale_item_batches`
  ADD CONSTRAINT `sale_item_batches_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`),
  ADD CONSTRAINT `sale_item_batches_sale_item_id_foreign` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `storage_locations`
--
ALTER TABLE `storage_locations`
  ADD CONSTRAINT `storage_locations_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
