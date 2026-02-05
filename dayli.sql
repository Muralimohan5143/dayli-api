-- MySQL dump 10.13  Distrib 8.0.42, for Win64 (x86_64)
--
-- Host: localhost    Database: dayli
-- ------------------------------------------------------
-- Server version	8.0.42

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `addresses`
--

DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `addressable_id` bigint unsigned DEFAULT NULL,
  `addressable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `line1` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pincode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lat` decimal(10,6) DEFAULT NULL,
  `lng` decimal(10,6) DEFAULT NULL,
  `nagar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g. Sindhu Estate, Malla Reddy Venture',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `zone_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `addresses_user_id_foreign` (`user_id`),
  KEY `addresses_owner_idx` (`addressable_type`,`addressable_id`),
  KEY `addresses_zone_id_foreign` (`zone_id`),
  CONSTRAINT `addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `addresses_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `zone` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addresses`
--

LOCK TABLES `addresses` WRITE;
/*!40000 ALTER TABLE `addresses` DISABLE KEYS */;
/*!40000 ALTER TABLE `addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audits`
--

DROP TABLE IF EXISTS `audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_id` bigint unsigned NOT NULL,
  `old_values` text COLLATE utf8mb4_unicode_ci,
  `new_values` text COLLATE utf8mb4_unicode_ci,
  `url` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(1023) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tags` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audits_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audits_user_id_user_type_index` (`user_id`,`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audits`
--

LOCK TABLES `audits` WRITE;
/*!40000 ALTER TABLE `audits` DISABLE KEYS */;
/*!40000 ALTER TABLE `audits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Food','Find our recipies','2025-05-07 04:45:09','2025-05-07 04:45:09'),(2,'Home','Find the latest trends in interior desgin','2025-05-07 04:45:09','2025-05-07 04:45:09'),(3,'Fashion','Find the latest trends','2025-05-07 04:45:09','2025-05-07 04:45:09');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `handle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tags` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `img_src` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_tag`
--

DROP TABLE IF EXISTS `item_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_tag` (
  `item_id` bigint unsigned NOT NULL,
  `tag_id` bigint unsigned NOT NULL,
  KEY `item_tag_item_id_foreign` (`item_id`),
  KEY `item_tag_tag_id_foreign` (`tag_id`),
  CONSTRAINT `item_tag_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `item_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_tag`
--

LOCK TABLES `item_tag` WRITE;
/*!40000 ALTER TABLE `item_tag` DISABLE KEYS */;
INSERT INTO `item_tag` VALUES (1,1),(1,2),(2,3),(3,1),(3,2),(3,3);
/*!40000 ALTER TABLE `item_tag` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `show_on_homepage` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `options` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` bigint unsigned NOT NULL,
  `date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `items_name_unique` (`name`),
  KEY `items_category_id_foreign` (`category_id`),
  CONSTRAINT `items_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items`
--

LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES (1,'Alchimia Chair','/home-decor-1.jpg','This is the excerpt for Alchimia Chair','This is the description for Alchimia Chair',0,NULL,NULL,2,NULL,'2025-05-07 04:45:09','2025-05-07 04:45:09'),(2,'Master Bed','/home-decor-2.jpg','This is the excerpt for Master Bed','This is the description for Master Bed',0,NULL,NULL,2,NULL,'2025-05-07 04:45:09','2025-05-07 04:45:09'),(3,'Fancy T-Shirt','/jordan.jpg','This is the excerpt for Fancy T-Shirt','This is the description for Fancy T-Shirt',0,NULL,NULL,3,NULL,'2025-05-07 04:45:09','2025-05-07 04:45:09');
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leads`
--

DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alternate_phone` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lang_locale` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pincode` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lead_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dayli',
  `zone` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `collected_by` bigint unsigned NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `follow_up_date` date DEFAULT NULL,
  `collected_lat` decimal(10,7) DEFAULT NULL,
  `collected_lng` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leads_collected_by_foreign` (`collected_by`),
  CONSTRAINT `leads_collected_by_foreign` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leads`
--

LOCK TABLES `leads` WRITE;
/*!40000 ALTER TABLE `leads` DISABLE KEYS */;
INSERT INTO `leads` VALUES (1,'Murali Mohan',NULL,'8790638406',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'dayli',NULL,NULL,4,NULL,'new',NULL,NULL,NULL,'2025-05-12 00:38:21','2025-05-12 00:38:21',NULL),(2,'Murali','Mohan','6364168111',NULL,NULL,'Telugu',NULL,NULL,NULL,'Andhra Pradesh','518001','Dayli',NULL,NULL,4,NULL,'New',NULL,15.7962715,78.0885167,'2025-05-12 04:04:35','2025-05-12 04:04:35',NULL),(3,'A','B','6364168111',NULL,NULL,'hi','d',NULL,'kurnool','ap','518001','dayli','checkpost',NULL,4,NULL,'new',NULL,15.7908992,78.0828672,'2025-05-12 04:07:40','2025-05-12 04:07:40',NULL),(4,'a','a','999','99',NULL,NULL,NULL,NULL,NULL,'as','518001','dd','gg',NULL,4,NULL,'f',NULL,15.7908992,78.0828672,'2025-05-12 04:10:05','2025-05-12 04:10:05',NULL),(5,'Murali','Mohan','6364168111',NULL,NULL,NULL,'18, SreeHari Homes','Nandanapally','Yemmiganuru','Andhra Pradesh','518360','test','checkpost',NULL,1,NULL,'new',NULL,15.7581312,77.4832128,'2025-05-23 02:41:24','2025-05-23 02:41:24',NULL);
/*!40000 ALTER TABLE `leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_10_12_100000_create_password_resets_table',1),(2,'2019_08_19_000000_create_failed_jobs_table',1),(3,'2021_06_04_084747_create_roles_table',1),(4,'2021_06_05_000000_create_users_table',1),(5,'2021_06_08_110000_create_categories_table',1),(6,'2021_06_08_125113_create_tags_table',1),(7,'2021_06_09_061813_create_items_table',1),(8,'2021_06_09_064213_item_tag',1),(9,'2024_10_27_072534_create_customers_table',1),(10,'2024_10_31_054654_create_user_otps_table',1),(11,'2024_10_31_054737_create_permissions_table',1),(12,'2024_10_31_054930_create_change_requests_table',1),(13,'2024_10_31_055030_create_subscr_types_table',1),(14,'2024_10_31_102951_create_user_attr_types_table',1),(15,'2024_11_01_003845_create_subscr_2004_table',1),(16,'2024_11_01_010559_create_audits_table',1),(17,'2024_11_17_212157_create_user_attr_types_table',1),(18,'2024_11_17_212223_create_user_roles_table',1),(19,'2024_11_17_212256_create_role_permissions_table',1),(20,'2025_05_12_030711_create_leads_table',2),(21,'2025_07_30_012129_create_addresses_table',3),(22,'2025_07_30_015150_change_users_table_drop_address_fields',4),(23,'2025_07_31_072939_create_products_table',5),(25,'2025_08_02_110905_make_password_nullable_on_users_table',5),(26,'2025_07_31_075456_create_sub_change_requests_table',6),(27,'2025_08_06_172941_create_orders_table',6),(28,'2025_08_06_173055_create_order_line_items_table',6),(29,'2025_08_06_173216_create_sub_change_requests_table',7),(31,'2025_08_08_063759_create_permission_tables',8),(32,'2025_08_09_102822_create_subscr_zone_table',9),(33,'2025_08_09_134318_add_polymorphic_to_addresses',9),(34,'2025_08_09_134435_backfill_addresses_owner_and_drop_user_id',9),(35,'2025_08_09_140041_create_vendor_zone_subscr_table',10),(36,'2025_08_09_142618_create_zone_pincodes_table',11),(37,'2025_08_09_144318_rename_areas_to_nagars_in_zone',11),(38,'2025_08_09_151940_make_pin_code_globally_unique',12),(39,'2025_08_09_155805_add_status_to_zone_table',13),(40,'2025_08_09_160143_drop_pin_codes_from_zone',14);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (6,'App\\Models\\User',1),(6,'App\\Models\\User',2),(6,'App\\Models\\User',3),(1,'App\\Models\\User',4),(6,'App\\Models\\User',5),(6,'App\\Models\\User',6),(6,'App\\Models\\User',7),(6,'App\\Models\\User',8),(6,'App\\Models\\User',9),(6,'App\\Models\\User',10);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_line_items`
--

DROP TABLE IF EXISTS `order_line_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_line_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `properties` json DEFAULT NULL,
  `fulfillment_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_line_items_order_id_foreign` (`order_id`),
  CONSTRAINT `order_line_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_line_items`
--

LOCK TABLES `order_line_items` WRITE;
/*!40000 ALTER TABLE `order_line_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_line_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financial_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fulfillment_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `subtotal_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_tax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_discounts` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_weight` int DEFAULT NULL,
  `source_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `processed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `billing_address` json DEFAULT NULL,
  `shipping_address` json DEFAULT NULL,
  `tags` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'view dashboard','web','2025-08-08 01:56:50','2025-08-08 01:56:50'),(2,'manage change requests','web','2025-08-08 01:56:50','2025-08-08 01:56:50'),(3,'view delivery actuals','web','2025-08-08 01:56:51','2025-08-08 01:56:51'),(4,'manage users','web','2025-08-08 02:30:19','2025-08-08 02:30:19'),(5,'manage subscriptions','web','2025-08-08 02:30:19','2025-08-08 02:30:19'),(6,'view orders','web','2025-08-08 02:30:19','2025-08-08 02:30:19'),(7,'edit change requests','web','2025-08-08 02:30:19','2025-08-08 02:30:19'),(8,'edit users','web','2025-08-08 02:57:00','2025-08-08 02:57:00');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `product_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Dayli',
  `product_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'daily-need',
  `handle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'empty-handle',
  `tags` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '""',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '""',
  `img_src` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '""',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (1,1),(2,1),(8,1),(1,2),(6,2),(2,3),(3,4),(1,7),(6,7);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','web','2025-08-08 01:56:51','2025-08-08 01:56:51'),(2,'zones-director','web','2025-08-08 01:56:51','2025-08-08 01:56:51'),(3,'zone-manager','web','2025-08-08 01:56:51','2025-08-08 01:56:51'),(4,'vendor','web','2025-08-08 01:56:51','2025-08-08 01:56:51'),(5,'workman','web','2025-08-08 01:56:51','2025-08-08 01:56:51'),(6,'customer','web','2025-08-08 01:56:51','2025-08-08 01:56:51'),(7,'zones-head','web','2025-08-08 02:30:19','2025-08-08 02:30:19'),(8,'vendor-milk','web','2025-08-08 03:12:11','2025-08-08 03:12:11'),(9,'vendor-vegetable','web','2025-08-08 03:12:12','2025-08-08 03:12:12'),(10,'vendor-meat','web','2025-08-08 03:12:12','2025-08-08 03:12:12'),(11,'vendor-grocery','web','2025-08-08 03:12:12','2025-08-08 03:12:12'),(12,'workman-delivery-boy','web','2025-08-08 03:12:12','2025-08-08 03:12:12'),(13,'workman-washerman','web','2025-08-08 03:12:12','2025-08-08 03:12:12'),(14,'workman-plumber','web','2025-08-08 03:12:12','2025-08-08 03:12:12'),(15,'workman-delivery-boy-milk','web','2025-08-08 03:12:51','2025-08-08 03:12:51'),(16,'workman-delivery-boy-grocery','web','2025-08-08 03:12:51','2025-08-08 03:12:51'),(17,'workman-delivery-boy-puja-items','web','2025-08-08 03:12:51','2025-08-08 03:12:51');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sub_change_requests`
--

DROP TABLE IF EXISTS `sub_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_change_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `for_user_id` bigint unsigned NOT NULL,
  `by_user_id` bigint unsigned NOT NULL,
  `from_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned NOT NULL,
  `frequency_type` enum('daily','alternate_days','weekdays','weekends','sat','sun','custom','on_demand') COLLATE utf8mb4_unicode_ci NOT NULL,
  `custom_frequency_format` text COLLATE utf8mb4_unicode_ci,
  `change_reason` enum('self_service','user-error','staff-error') COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sub_change_requests_for_user_id_foreign` (`for_user_id`),
  KEY `sub_change_requests_by_user_id_foreign` (`by_user_id`),
  KEY `sub_change_requests_from_id_foreign` (`from_id`),
  KEY `sub_change_requests_order_id_foreign` (`order_id`),
  CONSTRAINT `sub_change_requests_by_user_id_foreign` FOREIGN KEY (`by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sub_change_requests_for_user_id_foreign` FOREIGN KEY (`for_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sub_change_requests_from_id_foreign` FOREIGN KEY (`from_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sub_change_requests_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sub_change_requests`
--

LOCK TABLES `sub_change_requests` WRITE;
/*!40000 ALTER TABLE `sub_change_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `sub_change_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sub_delivery_actuals`
--

DROP TABLE IF EXISTS `sub_delivery_actuals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_delivery_actuals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `for_user_id` bigint unsigned NOT NULL,
  `by_user_id` bigint unsigned NOT NULL,
  `from_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `product_count` smallint NOT NULL,
  `status` enum('pending_approval','approved','rejected') NOT NULL DEFAULT 'pending_approval',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sub_delivery_actuals`
--

LOCK TABLES `sub_delivery_actuals` WRITE;
/*!40000 ALTER TABLE `sub_delivery_actuals` DISABLE KEYS */;
/*!40000 ALTER TABLE `sub_delivery_actuals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscr_types`
--

DROP TABLE IF EXISTS `subscr_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscr_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `img_src` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decommissioned_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscr_types_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscr_types`
--

LOCK TABLES `subscr_types` WRITE;
/*!40000 ALTER TABLE `subscr_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscr_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscr_zone`
--

DROP TABLE IF EXISTS `subscr_zone`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscr_zone` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `zone_id` bigint unsigned NOT NULL,
  `subscr_type_id` bigint unsigned NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `available_from` date DEFAULT NULL,
  `available_to` date DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscr_zone_zone_id_subscr_type_id_unique` (`zone_id`,`subscr_type_id`),
  KEY `subscr_zone_subscr_type_id_foreign` (`subscr_type_id`),
  CONSTRAINT `subscr_zone_subscr_type_id_foreign` FOREIGN KEY (`subscr_type_id`) REFERENCES `subscr_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscr_zone_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `zone` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscr_zone`
--

LOCK TABLES `subscr_zone` WRITE;
/*!40000 ALTER TABLE `subscr_zone` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscr_zone` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tags`
--

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
INSERT INTO `tags` VALUES (1,'Trending','#cb0c9f','2025-05-07 04:45:09','2025-05-07 04:45:09'),(2,'Hot','#ea0606','2025-05-07 04:45:09','2025-05-07 04:45:09'),(3,'New','#17c1e8','2025-05-07 04:45:09','2025-05-07 04:45:09');
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_otps`
--

DROP TABLE IF EXISTS `user_otps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_otps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `otp` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expire_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_otps`
--

LOCK TABLES `user_otps` WRITE;
/*!40000 ALTER TABLE `user_otps` DISABLE KEYS */;
INSERT INTO `user_otps` VALUES (1,6,'49266','2025-08-05 12:40:36','2025-08-05 12:35:36','2025-08-05 12:35:36'),(2,7,'39173','2025-08-05 12:41:17','2025-08-05 12:36:17','2025-08-05 12:36:17'),(3,8,'91415','2025-08-05 12:42:04','2025-08-05 12:37:04','2025-08-05 12:37:04'),(4,8,'31571','2025-08-05 12:44:39','2025-08-05 12:39:39','2025-08-05 12:39:39'),(5,8,'56543','2025-08-05 13:13:30','2025-08-05 13:08:30','2025-08-05 13:08:30'),(6,8,'39572','2025-08-05 13:16:49','2025-08-05 13:11:49','2025-08-05 13:11:49'),(7,8,'54343','2025-08-05 13:27:53','2025-08-05 13:22:53','2025-08-05 13:22:53'),(8,8,'25587','2025-08-05 13:29:35','2025-08-05 13:24:35','2025-08-05 13:24:35'),(9,8,'42411','2025-08-05 13:31:04','2025-08-05 13:26:04','2025-08-05 13:26:04'),(10,8,'26483','2025-08-05 13:44:06','2025-08-05 13:39:06','2025-08-05 13:39:06'),(11,8,'59331','2025-08-05 13:44:57','2025-08-05 13:39:57','2025-08-05 13:39:57'),(12,8,'70365','2025-08-05 13:45:33','2025-08-05 13:40:33','2025-08-05 13:40:33'),(13,8,'67375','2025-08-05 13:46:18','2025-08-05 13:41:18','2025-08-05 13:41:18'),(14,8,'16468','2025-08-05 13:46:55','2025-08-05 13:41:55','2025-08-05 13:41:55'),(15,8,'62047','2025-08-05 13:47:31','2025-08-05 13:42:31','2025-08-05 13:42:31'),(16,8,'92307','2025-08-05 13:48:04','2025-08-05 13:43:04','2025-08-05 13:43:04'),(17,8,'57421','2025-08-05 13:48:36','2025-08-05 13:43:36','2025-08-05 13:43:36'),(18,8,'79467','2025-08-05 14:04:36','2025-08-05 13:59:36','2025-08-05 13:59:36'),(19,8,'73264','2025-08-05 14:05:13','2025-08-05 14:00:13','2025-08-05 14:00:13'),(20,8,'56555','2025-08-05 14:22:04','2025-08-05 14:17:04','2025-08-05 14:17:04'),(21,8,'25571','2025-08-05 14:26:34','2025-08-05 14:21:34','2025-08-05 14:21:34'),(22,8,'41709','2025-08-05 14:36:40','2025-08-05 14:31:40','2025-08-05 14:31:40'),(23,8,'68866','2025-08-05 14:37:44','2025-08-05 14:32:44','2025-08-05 14:32:44'),(24,8,'31058','2025-08-05 14:49:28','2025-08-05 14:44:28','2025-08-05 14:44:28'),(25,8,'26476','2025-08-05 14:58:34','2025-08-05 14:53:34','2025-08-05 14:53:34'),(26,8,'13515','2025-08-05 14:59:34','2025-08-05 14:54:34','2025-08-05 14:54:34'),(27,8,'47024','2025-08-05 20:35:06','2025-08-05 20:30:06','2025-08-05 20:30:06'),(28,9,'95470','2025-08-05 21:16:51','2025-08-05 21:11:51','2025-08-05 21:11:51'),(29,4,'90350','2025-08-05 21:17:50','2025-08-05 21:12:50','2025-08-05 21:12:50'),(30,6,'94169','2025-08-06 12:50:14','2025-08-06 12:45:14','2025-08-06 12:45:14'),(31,6,'81933','2025-08-06 12:51:22','2025-08-06 12:46:22','2025-08-06 12:46:22'),(32,6,'73972','2025-08-06 12:52:29','2025-08-06 12:47:29','2025-08-06 12:47:29'),(33,6,'84434','2025-08-06 12:53:07','2025-08-06 12:48:07','2025-08-06 12:48:07'),(34,6,'32055','2025-08-06 12:54:01','2025-08-06 12:49:01','2025-08-06 12:49:01'),(35,6,'90985','2025-08-07 03:54:31','2025-08-07 03:49:31','2025-08-07 03:49:31'),(36,6,'89704','2025-08-07 03:55:52','2025-08-07 03:50:52','2025-08-07 03:50:52'),(37,9,'11900','2025-08-07 03:58:00','2025-08-07 03:53:00','2025-08-07 03:53:00'),(38,10,'79473','2025-08-07 04:00:09','2025-08-07 03:55:09','2025-08-07 03:55:09'),(39,4,'38504','2025-08-07 04:01:57','2025-08-07 03:56:57','2025-08-07 03:56:57'),(40,4,'84456','2025-08-07 08:23:33','2025-08-07 08:18:33','2025-08-07 08:18:33'),(41,8,'41241','2025-08-07 08:26:14','2025-08-07 08:21:14','2025-08-07 08:21:14'),(42,8,'22497','2025-08-07 08:43:16','2025-08-07 08:38:16','2025-08-07 08:38:16'),(43,8,'55011','2025-08-08 03:29:35','2025-08-08 03:24:35','2025-08-08 03:24:35'),(44,8,'61240','2025-08-08 03:30:39','2025-08-08 03:25:39','2025-08-08 03:25:39'),(45,4,'81208','2025-08-08 03:48:00','2025-08-08 03:43:00','2025-08-08 03:43:00'),(46,4,'60945','2025-08-08 04:42:29','2025-08-08 04:37:29','2025-08-08 04:37:29'),(47,8,'72234','2025-08-08 04:47:01','2025-08-08 04:42:01','2025-08-08 04:42:01'),(48,4,'77522','2025-08-08 04:58:58','2025-08-08 04:53:58','2025-08-08 04:53:58'),(49,4,'35993','2025-08-08 05:07:37','2025-08-08 05:02:37','2025-08-08 05:02:37'),(50,4,'34162','2025-08-08 06:05:53','2025-08-08 06:00:53','2025-08-08 06:00:53'),(51,8,'67014','2025-08-08 07:35:53','2025-08-08 07:30:53','2025-08-08 07:30:53'),(52,4,'89765','2025-08-09 02:46:33','2025-08-09 02:41:33','2025-08-09 02:41:33'),(53,4,'45524','2025-08-09 08:43:13','2025-08-09 08:38:13','2025-08-09 08:38:13'),(54,4,'36348','2025-08-11 01:33:50','2025-08-11 01:28:50','2025-08-11 01:28:50'),(55,4,'28553','2025-08-11 02:03:21','2025-08-11 01:58:21','2025-08-11 01:58:21'),(56,4,'59433','2025-08-11 02:26:58','2025-08-11 02:21:58','2025-08-11 02:21:58');
/*!40000 ALTER TABLE `user_otps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phoneNo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `day` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `month` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `skills` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `twitter` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facebook` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instagram` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `public_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `role_id` bigint DEFAULT '3',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_foreign` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin Admin','Admin','Admin','admin@softui.com','2025-05-07 04:45:08','$2y$10$zzpskoEphPkV3ZI0dC962ez5nnQqg8/Y9qxKrPAgExIw8CpNUQhfq',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'/team-1.jpg',NULL,'2025-05-07 04:45:08','2025-05-07 04:45:08',NULL,NULL,NULL,NULL,NULL,NULL,1),(2,'Creator Creator','Creator','Creator','creator@softui.com','2025-05-07 04:45:09','$2y$10$TOMgyrO843oTVlH2U2hdo.HChfu3xNL21nq.8KbtyRIrbLGKJVQpu',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'/team-2.jpg',NULL,'2025-05-07 04:45:09','2025-05-07 04:45:09',NULL,NULL,NULL,NULL,NULL,NULL,2),(3,'Member Member','Member','Member','member@softui.com','2025-05-07 04:45:09','$2y$10$NP6lqihEsvQV828kBlx3BuFFq3a4A.nPRe4np4t/dmI.fIl7wPvk.',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'/team-3.jpg',NULL,'2025-05-07 04:45:09','2025-05-07 04:45:09',NULL,NULL,NULL,NULL,NULL,NULL,3),(4,'Murali Mohan',NULL,NULL,'admin@dayli.in',NULL,'$2y$10$SW8eKjdA09qHENLF/i1t8u7/Ngwbe1GBnRRPqjYhU3pJZXMsm0CI.',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'mVtuZrsdSLeo8dFzkPrYWUHTHS8WH3NmHRBR9KUNilwuZPqf0GieLUCyiTkh','2025-05-07 04:57:43','2025-05-07 04:57:43',NULL,NULL,NULL,NULL,NULL,NULL,1),(5,'Naveen',NULL,NULL,'naveen@dayli.in',NULL,'$2y$10$DL13Ax26MJwxjgJUsVutjeNGojbQFxaohaHp3UEmFjglLq4D0M5..',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-05-22 00:58:19','2025-05-22 00:58:19',NULL,NULL,NULL,NULL,NULL,NULL,3),(6,'New User',NULL,NULL,'8790638406',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-08-05 12:35:36','2025-08-05 12:35:36',NULL,NULL,NULL,NULL,NULL,NULL,3),(7,'New User',NULL,NULL,'0909090909',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-08-05 12:36:17','2025-08-05 12:36:17',NULL,NULL,NULL,NULL,NULL,NULL,3),(8,'New User',NULL,NULL,'1212121212',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-08-05 12:37:04','2025-08-05 12:37:04',NULL,NULL,NULL,NULL,NULL,NULL,3),(9,'New User',NULL,NULL,'murali.mohan@omnea.in',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-08-05 21:11:51','2025-08-05 21:11:51',NULL,NULL,NULL,NULL,NULL,NULL,3),(10,'New User',NULL,NULL,'lionsrk2223@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-08-07 03:55:09','2025-08-07 03:55:09',NULL,NULL,NULL,NULL,NULL,NULL,3);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `variants`
--

DROP TABLE IF EXISTS `variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `variants` (
  `product_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `compare_at_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `img_src` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `paf` double(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `variants`
--

LOCK TABLES `variants` WRITE;
/*!40000 ALTER TABLE `variants` DISABLE KEYS */;
INSERT INTO `variants` VALUES (8402735726866,50387754909970,'1 Piece Small','150.00','21.00','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8402735726866,45528326897938,'1 Piece Big','200.00','21.00','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8381720723730,45440589234450,'1 Kg','70.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8381720723730,45762025062674,'1/2 Kg','37.00','','',1.00,'2025-01-31 23:59:12','2025-04-25 02:03:09'),(8381720723730,45921570750738,'1/4 Kg','22.00','','',1.00,'2025-01-31 23:59:12','2025-04-25 02:03:16'),(8381724918034,45440595230994,'1 Kg','50.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8381724918034,45781991751954,'1/2 Kg','25.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8381724918034,47922282266898,'1/4 Kg','15.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8386934309138,45460591640850,'1 Kg','50.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8386934309138,45762129985810,'1/2 Kg','25.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8386934309138,46897026433298,'1/4 Kg','15.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8389904072978,45472699580690,'1 Pc Big','40.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8389904072978,50503974191378,'1 Pc Medium','30.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8389904072978,45762216460562,'1 Pc Small','20.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384318275858,45451617435922,'1 Kg','20.00','','',1.00,'2025-01-31 23:59:12','2025-04-25 02:03:10'),(8384318275858,45762249523474,'1/2 Kg','10.00','','',1.00,'2025-01-31 23:59:12','2025-04-25 02:03:26'),(8384318275858,50387833454866,'1/4 Kg','10.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8436600635666,45672569962770,'1 Kg','70.00','','',1.00,'2025-01-31 23:59:12','2025-04-25 02:03:46'),(8436600635666,45762287436050,'1/2 Kg','35.00','','',1.00,'2025-01-31 23:59:12','2025-04-25 02:04:24'),(8436600635666,45921448853778,'1/4 Kg','15.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8405690581266,45539109077266,'1 Kg','280.00','30.00','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8405690581266,49255107985682,'1/2 Kg','140.00','30.00','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8405690581266,50419969491218,'1/4 Kg','70.00','30.00','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384297009426,45451561468178,'1 Kg','30.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384297009426,45762366800146,'1/2 Kg','15.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384297009426,49106528370962,'1/4 Kg','10.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8388239982866,45528382079250,'1 Kg','80.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8388239982866,45762435514642,'1/2 Kg','40.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8388239982866,46826053042450,'1/4 Kg','20.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384761364754,45453118734610,'1 Kg','50.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384761364754,45762488795410,'1/2 Kg','25.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384761364754,45952843481362,'1/4 Kg','15.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8386965012754,45460649410834,'1 Kg','40.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8386965012754,45762555642130,'1/2 Kg','20.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8402741035282,45528354488594,'1 Kg','46.00','','',1.00,'2025-01-31 23:59:12','2025-04-25 02:05:53'),(8402741035282,45762615542034,'1/2 Kg','25.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8402741035282,46506869752082,'1/4 Kg','15.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8406321004818,45541648564498,'1 Kg','70.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8406321004818,45762664300818,'1/2 Kg','35.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8388244930834,50501576163602,'1 Piece','30.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8388244930834,49858453111058,'1/2 Dozen','180.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8388244930834,45465259180306,'1 Dozen','360.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8388188274962,45554293801234,'1 Kg','30.00','15.00','',1.00,'2025-01-31 23:59:12','2025-04-25 02:06:03'),(8388188274962,45762727477522,'1/2 Kg','15.00','15.00','',1.00,'2025-01-31 23:59:12','2025-04-25 02:07:42'),(8388256465170,45869438894354,'1 Pc','15.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8388256465170,49251458777362,'3 Pcs','40.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8388256465170,50459886354706,'6 Pcs','80.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8436666335506,45672725119250,'1 Kg','60.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8436666335506,45762805858578,'1/2 Kg','30.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8380311863570,46012902179090,'1 Kg','400.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8380311863570,46012902211858,'1/2 Kg','200.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8380311863570,46197297840402,'1/4 Kg','100.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8380311863570,49909732638994,'100 Gms','40.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384891322642,45861591417106,'1 Kg','100.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384891322642,45861642338578,'1/2 Kg','50.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384891322642,45861652398354,'1/4 Kg','30.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384891322642,46850980086034,'1 Small Bit','20.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384267092242,45451467587858,'1 Kg','50.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384267092242,45763318186258,'1/2 Kg','25.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384267092242,45841034510610,'1/4 Kg','15.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8388201087250,45529577029906,'1 Kg','150.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8388201087250,45529576997138,'1/2 Kg','75.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8388201087250,48220856680722,'Packet','15.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(9743288533266,50362286965010,'1 Kg','120.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(9743288533266,50362286997778,'1/2 Kg','60.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(9743288533266,50362287030546,'1/4 Kg','30.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8389909578002,45472719798546,'1 Kg','60.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8389909578002,45763526361362,'1/2 Kg','30.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8389909578002,46012706160914,'1/4 Kg','20.00','','',1.00,'2025-01-31 23:59:12','2025-01-31 23:59:12'),(8384845381906,45453417873682,'1 Kg','30.00','','',1.00,'2025-01-31 23:59:12','2025-04-25 02:08:06'),(8384845381906,45763800727826,'1/2 Kg','20.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8384845381906,45921515831570,'1/4 Kg','10.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8396622102802,49255143768338,'1 pc','5.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8396622102802,50406864421138,'1/2 Dozen','30.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8396622102802,50406864453906,'1 Dozen','60.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(9445320458514,50362314522898,'1 Kg','60.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(9445320458514,50362314555666,'1/2 Kg','30.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(9445320458514,50362314588434,'1/4 Kg','20.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8403017138450,45529373114642,'1 Pc','50.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8388267573522,50387955155218,'1 Packet (200 gms)','45.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8403094110482,45529542197522,'1 Kg','40.00','42.00','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8403094110482,50387956531474,'1/2 Kg','20.00','42.00','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8403094110482,50387956564242,'1/4 Kg','15.00','42.00','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8378085343506,45428095549714,'1 Kg','30.00','','',1.00,'2025-01-31 23:59:13','2025-04-25 02:09:10'),(8378085343506,45763992715538,'1/2 Kg','15.00','','',1.00,'2025-01-31 23:59:13','2025-04-25 02:09:19'),(8378085343506,50387959054610,'1/4 Kg','10.00','','',1.00,'2025-01-31 23:59:13','2025-04-25 02:09:39'),(8403079954706,45529506578706,'1 Kg','26.00','','',1.00,'2025-01-31 23:59:13','2025-04-25 02:09:51'),(8403079954706,45805216825618,'1/2 Kg','13.00','','',1.00,'2025-01-31 23:59:13','2025-04-25 02:09:57'),(8403079954706,46012655763730,'1/4 Kg','10.00','','',1.00,'2025-01-31 23:59:13','2025-04-25 02:10:27'),(8380226502930,45434894778642,'1 Kg','50.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8380226502930,45781961539858,'1/2 Kg','25.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8380226502930,50152191230226,'1/4 Kg','15.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8388260725010,50503534838034,'1 Piece Small','125.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8388260725010,45465276416274,'1 Piece Big','250.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8388192567570,45782043689234,'1 Kg','30.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8388192567570,45782043722002,'1/2 Kg','15.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8388192567570,48167603077394,'1/4 Kg','8.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8402727108882,45528272699666,'1 Dozen','220.00','9.00','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8402727108882,48002016411922,'1/2 Dozen','110.00','9.00','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8402727108882,50567101546770,'3 Pieces','50.00','9.00','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(9445246107922,50501540872466,'1 Kg','150.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8403038306578,45923611050258,'1 Kg','45.00','','',1.00,'2025-01-31 23:59:13','2025-04-25 02:10:38'),(8403038306578,45923611083026,'1/2 Kg','25.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8403038306578,46270877335826,'1/4 Kg','15.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8403054035218,45529463324946,'1 Kg','40.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8403054035218,46536596783378,'1/2 Kg','20.00','','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8418618278162,45594627277074,'1 Kg','70.00','43.00','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8418618278162,48278247309586,'1/2 Kg','35.00','43.00','',1.00,'2025-01-31 23:59:13','2025-01-31 23:59:13'),(8381713056018,45539017359634,'1 Kg','20.00','','',1.00,'2025-01-31 23:59:13','2025-04-25 02:11:08'),(8381713056018,45802928701714,'1/2 Kg','10.00','','',1.00,'2025-01-31 23:59:13','2025-04-25 02:11:15'),(8381713056018,45868905300242,'1/4 Kg','5.00','','',1.00,'2025-01-31 23:59:13','2025-04-25 02:12:06'),(8436747927826,50360914542866,'1 Bunch','5.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8436747927826,50360914510098,'2 Bunches','10.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8436747927826,45673001353490,'3+ Bunches','20.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8439650287890,45689779552530,'1 Bunch','20.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8439650287890,50383032746258,'2 Bunches','40.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8439650287890,50383032418578,'3 Bunches','60.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406312485138,45541592957202,'1 Bunch','10.00','10.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406312485138,50383039070482,'2  Bunches','20.00','10.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406312485138,50383039103250,'3  Bunches','30.00','10.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406675587346,46292949860626,'1 Bunch','10.00','9.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406675587346,50383037038866,'2 Bunches','20.00','9.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406675587346,50383037071634,'3 Bunches','30.00','9.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8403120324882,45862083559698,'1 Bunch Small','10.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8403120324882,45529614024978,'1 Bunch Big','20.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405482242322,45862042337554,'1 Bunch Small','10.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405482242322,45538646098194,'1 Bunch Big','20.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405516124434,45538764161298,'1 Bunch','10.00','9.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405516124434,50383036383506,'2 Bunches','20.00','9.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405516124434,50383036416274,'3 Bunches','30.00','9.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406687940882,45543470203154,'1 bunch','20.00','23.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406687940882,50383038087442,'2 bunches','30.00','23.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406687940882,50383038120210,'3 bunches','40.00','23.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405486272786,45538663104786,'1 Bunch','7.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405486272786,50378429235474,'2 Bunches','14.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405486272786,50378429268242,'3 Bunches','20.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406695215378,50383053127954,'1 Bunch','5.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406695215378,50383053095186,'2 Bunches','10.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406695215378,46307661119762,'3+ Bunches','20.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8449160216850,45757627334930,'1 Bunch','10.00','5.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8449160216850,50383034745106,'2 Bunches','20.00','5.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8449160216850,50383034777874,'3 Bunches','30.00','5.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405711323410,45539145875730,'1 Bunch','50.00','18.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405711323410,50388170866962,'2 Bunches','100.00','18.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405711323410,50388170899730,'3 Bunches','150.00','18.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405490827538,45862134022418,'1 Bunch Small','10.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405490827538,45538688958738,'1 Bunch Big','20.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405490827538,50388186267922,'2 Bunches','40.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405490827538,50388186300690,'3 Bunches','60.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406301671698,45541512642834,'1 Bunch','10.00','14.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406301671698,50388173422866,'2 Bunches','20.00','14.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406301671698,50388173455634,'3 Bunches','30.00','14.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405571469586,46283453202706,'1 Bunch','20.00','10.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405571469586,50383035367698,'2 Bunches','40.00','10.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405571469586,50383035400466,'3 Bunches','60.00','10.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405548630290,46307674521874,'1 Bunch','5.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405548630290,50360919359762,'2 Bunches','10.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405548630290,50360919392530,'3 Bunches','15.00','','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405494726930,45538703442194,'1 Bunch','5.00','5.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405494726930,45538703409426,'2 Bunches','10.00','5.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405494726930,50388163854610,'3 Bunches','15.00','5.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406336307474,50378440245522,'1 Bunch','5.00','9.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406336307474,50378440212754,'2 Bunches','10.00','9.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406336307474,46283483873554,'3 Bunches','15.00','9.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8406336307474,50398197776658,'4  Bunches','20.00','9.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405612757266,50388168409362,'1/4 Kg','7.00','10.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405612757266,45538953756946,'1/2 Kg','13.00','10.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8405612757266,45538953789714,'1 Kg','25.00','10.00','',1.00,'2025-01-31 23:59:14','2025-01-31 23:59:14'),(8396599460114,45501735895314,'1 Kg','220.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8396599460114,45762327019794,'1/2 Kg','110.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8396599460114,50416049226002,'1 Piece','50.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8662321824018,50416047915282,'1 Piece','110.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8396609257746,46035992314130,'1 Kg','270.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8396609257746,46315298259218,'1/2 Kg','135.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8396609257746,50416049193234,'1 Piece','30.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8381753393426,45804907200786,'1 Dozen Small','60.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8381753393426,46301405413650,'1/2 Dozen Small','30.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8381753393426,45440632979730,'1 Dozen Big','70.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8381753393426,45938448990482,'1/2 Dozen Big','35.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8381753393426,47766661988626,'1 Piece','5.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8497380557074,45988043686162,'1 Kg','120.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8497380557074,45988043718930,'1/2 Kg','60.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8497380557074,45988043751698,'1/4 Kg','30.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(9706598236434,50362356039954,'1 Piece','60.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(9706598236434,50550725509394,'1 Litre','120.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8413381689618,50416168861970,'1 Kg','210.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8413381689618,50453152497938,'1/2 Kg','120.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8413381689618,50590695817490,'1 Piece','90.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8396651659538,45501869326610,'1 Kg','140.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8396651659538,45763250848018,'1/2 Kg','70.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8396651659538,50501281775890,'1/4 Kg','35.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8413251895570,45575813890322,'1 Kg','60.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8413251895570,45763391357202,'1/2 Kg','30.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8455953711378,50416048046354,'1 Kg','135.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8455953711378,50416048079122,'1/2 Kg','70.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8455953711378,50416048111890,'1/4 Kg','40.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8413293216018,47705825542418,'3 Pieces(Box)','130.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8396611748114,50388026687762,'1/2 Kg','70.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8396611748114,45501773218066,'1 Kg','140.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(9001587245330,48040111931666,'1 Kg','120.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(9001587245330,48040111964434,'1/2 Kg','60.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(9430116598034,49143737614610,'1 Kg','120.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(9430116598034,49143737647378,'1/2 Kg','60.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8449094025490,50416048275730,'1 Kg','60.00','40.00','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8449094025490,50483043631378,'1/2 Kg','30.00','30.00','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8398855340306,50501178949906,'1 Piece Small','100.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8398855340306,50501178917138,'1 Piece Big','160.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8398855340306,45764343333138,'1/2 Kg','60.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8398855340306,45514690691346,'1 Kg','110.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8413228925202,45575638057234,'1 Kg','240.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8413228925202,45803116527890,'1/2 Kg','120.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8413228925202,50416048472338,'1 Piece - Big','55.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8413228925202,50416048439570,'1 Piece - Small','35.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8449119420690,50416048177426,'1 Kg','60.00','35.00','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8449119420690,50416048210194,'1/2 Kg','30.00','35.00','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8449119420690,50870341075218,'1 doz','60.00','35.00','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(9654731538706,49848795037970,'1 Kg','430.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(9654731538706,50383063253266,'1/2 Kg','215.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8477349249298,45862189498642,'1 Kg','110.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8477349249298,45862189531410,'1/2 Kg','55.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8477349249298,50416048013586,'1 Piece','10.00','','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8396619219218,45501795959058,'1 Kg','40.00','0.00','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15'),(8396619219218,48177789174034,'1/2  Kg','20.00','0.00','',1.00,'2025-01-31 23:59:15','2025-01-31 23:59:15');
/*!40000 ALTER TABLE `variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendor_zone_subscr`
--

DROP TABLE IF EXISTS `vendor_zone_subscr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_zone_subscr` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendor_zone_subscr`
--

LOCK TABLES `vendor_zone_subscr` WRITE;
/*!40000 ALTER TABLE `vendor_zone_subscr` DISABLE KEYS */;
/*!40000 ALTER TABLE `vendor_zone_subscr` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zone`
--

DROP TABLE IF EXISTS `zone`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zone` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nagars` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `focal_pt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `focal_lon` double(10,8) NOT NULL,
  `focal_lat` double(10,8) NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zone`
--

LOCK TABLES `zone` WRITE;
/*!40000 ALTER TABLE `zone` DISABLE KEYS */;
INSERT INTO `zone` VALUES (1,'Kukatpally Zone','zone_kuk','Allwyn Colony, Kukatpally, Pragathi Nagar','KPHB Metro',78.39943200,17.49456100,'active','2025-08-09 10:32:17','2025-08-09 10:32:17'),(2,'Ameerpet Zone','zone_ameerpet','Ameerpet, SR Nagar, Yousufguda','Ameerpet Metro',78.44828800,17.43746200,'active','2025-08-09 10:32:17','2025-08-09 10:32:17'),(3,'Gachibowli Zone','zone_gachibowli','Gachibowli, Kondapur, Nanakramguda','Gachibowli DLF',78.35640000,17.44346400,'inactive','2025-08-09 10:32:17','2025-08-09 10:32:17'),(4,'Kurnool Checkpost Zone','zone_kurnool_checkpost','Brindavan Nagar, Dhanalaxmi Nagar, Elkur Estate, Sree Ramanagar','Nandyal Checkpost',78.03636400,15.82812600,'active','2025-08-09 10:32:17','2025-08-09 10:32:17'),(5,'Kurnool VRC Zone','zone_kurnool_vrc','Mamta Nagar, Nehru Auto Nagar, Venkata Ramana Colony','VRC Center',78.04041200,15.83312800,'active','2025-08-09 10:32:17','2025-08-09 10:32:17');
/*!40000 ALTER TABLE `zone` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zone_pincodes`
--

DROP TABLE IF EXISTS `zone_pincodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zone_pincodes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `zone_id` bigint unsigned NOT NULL,
  `pin_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zone_pincodes_pin_code_unique` (`pin_code`),
  KEY `zone_pincodes_pin_code_index` (`pin_code`),
  KEY `zone_pincodes_zone_id_index` (`zone_id`),
  CONSTRAINT `zone_pincodes_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `zone` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zone_pincodes`
--

LOCK TABLES `zone_pincodes` WRITE;
/*!40000 ALTER TABLE `zone_pincodes` DISABLE KEYS */;
INSERT INTO `zone_pincodes` VALUES (1,1,'500072','2025-08-09 10:32:17','2025-08-09 10:32:17'),(2,1,'500090','2025-08-09 10:32:17','2025-08-09 10:32:17'),(3,1,'500085','2025-08-09 10:32:17','2025-08-09 10:32:17'),(6,3,'500032','2025-08-09 10:32:17','2025-08-09 10:32:17'),(7,3,'500084','2025-08-09 10:32:17','2025-08-09 10:32:17'),(8,4,'518002','2025-08-09 10:32:17','2025-08-09 10:32:17'),(9,5,'518001','2025-08-09 10:32:17','2025-08-09 10:32:17'),(15,2,'500016','2025-08-11 03:17:16','2025-08-11 03:17:16'),(16,2,'500033','2025-08-11 03:17:16','2025-08-11 03:17:16');
/*!40000 ALTER TABLE `zone_pincodes` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-11 14:26:21
