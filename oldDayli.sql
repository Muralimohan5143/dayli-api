-- MySQL dump 10.13  Distrib 8.0.35, for Win64 (x86_64)
--
-- Host: localhost    Database: dayli
-- ------------------------------------------------------
-- Server version	8.0.35

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
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
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
-- Table structure for table `draft_orders`
--

DROP TABLE IF EXISTS `draft_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `draft_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `draft_orders`
--

LOCK TABLES `draft_orders` WRITE;
/*!40000 ALTER TABLE `draft_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `draft_orders` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_10_12_000000_create_users_table',1),(2,'2014_10_12_100000_create_password_reset_tokens_table',1),(3,'2014_10_12_200000_add_two_factor_columns_to_users_table',1),(4,'2019_08_19_000000_create_failed_jobs_table',1),(5,'2019_12_14_000001_create_personal_access_tokens_table',1),(6,'2020_05_21_100000_create_teams_table',1),(7,'2020_05_21_200000_create_team_user_table',1),(8,'2020_05_21_300000_create_team_invitations_table',1),(9,'2024_01_12_103315_create_sessions_table',1),(10,'2024_01_15_021211_create_milk_types_table',2),(11,'2024_02_01_074346_create_zone_table',3),(12,'2024_09_08_182117_create_products_table',4),(13,'2024_09_08_182149_create_variants_table',4),(14,'2024_09_08_182223_create_zone_costs_table',4),(15,'2024_09_09_095054_create_zone_prices_table',4),(16,'2024_09_09_120004_create_customers_table',4),(17,'2024_09_09_120021_create_draft_orders_table',4),(18,'2024_09_09_120031_create_orders_table',4),(19,'2024_09_09_120729_create_vendors_table',4),(20,'2024_09_09_120738_create_workmen_table',4);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `milk_type`
--

DROP TABLE IF EXISTS `milk_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `milk_type` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MRP` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cost_price` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `milk_type`
--

LOCK TABLES `milk_type` WRITE;
/*!40000 ALTER TABLE `milk_type` DISABLE KEYS */;
INSERT INTO `milk_type` VALUES (1,'milk','vijaya','Vijaya-Gold','34','33',NULL,NULL),(2,'milk','vijaya','Vijaya-Gold-Small','10','9',NULL,NULL),(3,'curd','vijaya','Vijaya-Curd','33','30',NULL,NULL),(4,'curd','vijaya','Vijaya-Curd-Small','10','9',NULL,NULL),(5,'milk','vijaya','Vijaya-TM','27','26',NULL,NULL),(6,'milk','vijaya','Vijaya-TM-Small','10','9',NULL,NULL),(7,'milk','arokya','Arokya-Gold','37','35',NULL,NULL),(8,'milk','arokya','Arokya-TM','27','25',NULL,NULL),(9,'curd','arokya','Hatsun-Curd','40','38',NULL,NULL),(10,'curd','arokya','Hatsun-Curd-Small','10','9',NULL,NULL),(11,'curd','sangam','Sangam-Gold','34','33',NULL,NULL),(12,'test','test','test','12','12','2024-01-22 09:48:55','2024-01-22 09:48:55'),(13,'test','test','test','12','9','2024-01-22 09:50:08','2024-01-22 10:09:56'),(14,'aaa','aaa','aaa','123','123','2024-09-15 20:14:06','2024-09-15 20:14:06');
/*!40000 ALTER TABLE `milk_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
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
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
INSERT INTO `password_reset_tokens` VALUES ('admin@dayli.in','$2y$12$l2QcG916rRJlleL27RgdU.p9ZdPmBvTEpl6rEW7UC4yQ50EEE8Pou','2024-09-21 06:14:51');
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
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
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (8402735726866,'Ash gourd','Dayli','Vegetable','ash-gourd','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_8_3d0eae09-10e8-4f9f-9d31-a83d1d3c9267.png?v=1699433161','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8381720723730,'Beans','Dayli','Vegetable','beans','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/6_ffee638c-bd24-49ff-8b8f-5806f65f550a.png?v=1699517317','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8381724918034,'Beetroot','Dayli','Vegetable','beetroot','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/9.png?v=1699434163','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8386934309138,'Bitter Gourd','Dayli','Vegetable','bitter-gord','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/5_68282033-87d0-425a-8549-07ef46323601.png?v=1699517356','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8389904072978,'Bottle Gourd','Dayli','Vegetable','bottle-gourd-1','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/4_0f5e8f0a-6289-4375-b0a0-ecfd6041a74a.png?v=1699517393','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384318275858,'Brinjals','Dayli','Vegetable','brinjal','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/8_3b90998e-6334-41c9-a425-29d5f0b9431c.png?v=1699514789','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8436600635666,'Broad Beans','Dayli','Vegetable','broad-beans-చిక్కుడు-కాయలు','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/6.png?v=1699434241','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8405690581266,'Broccoli','Dayli','Vegetable','briccoli','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_13_87160a9a-096d-4261-b749-b9cdcf69beb2.png?v=1699688016','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384297009426,'Cabbage','Dayli','Vegetable','cabbage','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/3_e5f22674-e66c-48cc-ac28-2ad902bc2492.png?v=1699517427','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388239982866,'Capsicum','Dayli','Vegetable','capsicum','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/5.png?v=1699434366','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384761364754,'Carrot','Dayli','Vegetable','carrot','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/2_64e89771-157b-42fc-b8e2-fce2993ebf99.png?v=1699517250','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8386965012754,'Cauliflower','Dayli','Vegetable','cauliflower','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/8.png?v=1699434409','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8402741035282,'Cluster Beans','Dayli','Vegetable','cluster-beans','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/7.png?v=1699434460','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8406321004818,'Colocasia','Dayli','Vegetable','colocasia','veg','draft','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_14.png?v=1699688284','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388244930834,'Corn','Dayli','Vegetable','corn','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/1.png?v=1699434534','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388188274962,'Cucumber','Dayli','Vegetable','cucumber','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/2.png?v=1699434578','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388256465170,'Drumstick','Dayli','Vegetable','drumstick','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/1_78772fe7-6e3f-47d6-83b0-7ef1a43001bd.png?v=1699517496','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8436666335506,'Elephant Yam','Dayli','Vegetable','elephant-yam-కందగడ్డలు','veg','draft','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_15.png?v=1699688553','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8380311863570,'Garlic','Dayli','Vegetable','garlic','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/4.png?v=1699434629','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384891322642,'Ginger','Dayli','Vegetable','ginger','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign10.png?v=1699518056','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384267092242,'Green Chillies','Dayli','Vegetable','green-chillies','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_9.png?v=1699517885','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388201087250,'Green Peas','Dayli','Vegetable','green-peas','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/3.png?v=1699434682','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8439642358034,'Hyacinth Bean','Dayli','Vegetable','hyacinth-bean-అనపకాయ','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_16.png?v=1699688843','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8389909578002,'Ivy Gourd','Dayli','Vegetable','ivy-gourd','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_11.png?v=1699518950','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384845381906,'Ladies Finger','Dayli','Vegetable','lady-finger','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/7_287f3141-d932-407a-ab8e-e8ee3e3fecfd.png?v=1699514854','2024-09-13 00:35:36','2024-09-13 00:35:36'),(8396622102802,'Lemon','Dayli','Vegetable','lemon','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/6_d93349d2-a9a5-4eed-a4b3-f0ccd466e36a.png?v=1699514908','2024-09-13 00:35:37','2024-09-13 00:35:37'),(9445320458514,'Maharashtra Onions','Dayli','Vegetable','maharashtra-onions','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/35_21c331d6-8682-4f42-b4a1-7b3c91ecea8b.png?v=1717664702','2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403017138450,'Mango Raw','Dayli','Vegetable','mango-raw','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/4_88878888-92d5-4041-8d0d-357fb6ba2cc0.png?v=1699692331','2024-09-13 00:35:37','2024-09-13 00:35:37'),(8388267573522,'Mushrooms','Dayli','Vegetable','mushroom','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_12.png?v=1699519556','2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403094110482,'Onion Green','Dayli','Vegetable','onion-green','veg','draft','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_17.png?v=1699692743','2024-09-13 00:35:37','2024-09-13 00:35:37'),(8378085343506,'Onions','Dayli','Vegetable','onions','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/5_bb625300-948f-4deb-9b54-8eb29bc32e4d.png?v=1699514959','2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403079954706,'Polur Brinjals','Dayli','Vegetable','brinjal-big','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/9_1394f52f-6248-49b3-9a32-d17bd179b491.png?v=1699514752','2024-09-13 00:35:37','2024-09-13 00:35:37'),(8380226502930,'Potato','Dayli','Vegetable','patato','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/3_3089f53b-81db-435b-b818-79edc29f6fad.png?v=1699515011','2024-09-13 00:35:37','2024-09-13 00:35:37'),(8388260725010,'Pumpkin','Dayli','Vegetable','pumpkin','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/3_16da38a3-2ca6-4200-a403-5827a31c1924.png?v=1699692377','2024-09-13 00:35:37','2024-09-13 00:35:37'),(8388192567570,'Radish','Dayli','Vegetable','radish','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/28_6fcdaa2a-398e-40f5-a1ca-e767de772802.png?v=1711527996','2024-09-13 00:35:37','2024-09-13 00:35:37'),(8402727108882,'Raw Banana','Dayli','Vegetable','raw-banana','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/2_f51849d8-9777-45ef-ad5e-4d88f1f0a911.png?v=1699692468','2024-09-13 00:35:37','2024-09-13 00:35:37'),(9445246107922,'Red Capsicum','Dayli','Vegetable','red-capsicum','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/33_5dfb873a-1c7b-4f64-9e8c-00bb794d2a84.png?v=1717664540','2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403038306578,'Ridge Gourd','Dayli','Vegetable','rigdge-gourd','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/4_75ba7c99-b440-4d31-b741-4242256cc774.png?v=1699515123','2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403054035218,'Snake Gourd','Dayli','Vegetable','snake-gourd','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_13.png?v=1699520425','2024-09-13 00:35:37','2024-09-13 00:35:37'),(8418618278162,'Sweet Potato','Dayli','Vegetable','sweet-potato-చిలగడదుంప','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/1_04990a39-a3ab-469c-b73b-5d64290640cb.png?v=1699692261','2024-09-13 00:35:37','2024-09-13 00:35:37'),(8381713056018,'Tomato','Dayli','Vegetable','tomato','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/1_1e74cd20-ca00-4211-a2be-7807da1c8424.png?v=1699514712','2024-09-13 00:35:37','2024-09-13 00:35:37');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('0j6IumctH9EttwgfQloPeqpfwwz5sNg5TtUOitRd',3,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiN2d6Z00yN3JnR2sxbmh4aEtDR2F3TTlGVG9lWHhOSDV2aXJ3eG1reSI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MztzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czozMToiaHR0cDovLzEyNy4wLjAuMTo4MDAwL3ByaWNlbGlzdCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1726972499);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `team_invitations`
--

DROP TABLE IF EXISTS `team_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_invitations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_invitations_team_id_email_unique` (`team_id`,`email`),
  CONSTRAINT `team_invitations_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_invitations`
--

LOCK TABLES `team_invitations` WRITE;
/*!40000 ALTER TABLE `team_invitations` DISABLE KEYS */;
INSERT INTO `team_invitations` VALUES (1,5,'g.naveen@dayli.in','editor','2024-09-21 06:53:01','2024-09-21 06:53:01');
/*!40000 ALTER TABLE `team_invitations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `team_user`
--

DROP TABLE IF EXISTS `team_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_user_team_id_user_id_unique` (`team_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_user`
--

LOCK TABLES `team_user` WRITE;
/*!40000 ALTER TABLE `team_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `team_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teams`
--

DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `personal_team` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teams_user_id_index` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teams`
--

LOCK TABLES `teams` WRITE;
/*!40000 ALTER TABLE `teams` DISABLE KEYS */;
INSERT INTO `teams` VALUES (1,1,'Murali\'s Team',1,'2024-01-14 06:06:42','2024-01-14 06:06:42'),(2,1,'Elkur Estate',0,'2024-01-14 06:07:18','2024-01-14 06:07:18'),(3,1,'Elkur Estate',0,'2024-01-14 06:07:19','2024-01-14 06:07:19'),(4,2,'Dayli\'s Team',1,'2024-09-04 09:08:20','2024-09-04 09:08:20'),(5,3,'Dayli\'s Team',1,'2024-09-21 06:48:55','2024-09-21 06:48:55');
/*!40000 ALTER TABLE `teams` ENABLE KEYS */;
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
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_team_id` bigint unsigned DEFAULT NULL,
  `profile_photo_path` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Murali Mohan','admin@dayli.zone',NULL,'$2y$12$4DS.RArn2/tYkc0naOVvpeCfKRgwmFE2HJySXSp9M.BBNSJ1irkIe',NULL,NULL,NULL,NULL,3,NULL,'2024-01-14 06:06:41','2024-01-14 06:09:20'),(3,'Dayli Admin','admin@dayli.in',NULL,'$2y$12$rWgz1arya0qXeFk1VylVgu0NawOw91BYtqxrkFMf/jbH9DZaGjJ7a',NULL,NULL,NULL,'QrbCMljyK9uAcXeCVKtN5oTLoFtL8I78OiQZCnC1JGX8FTotFNQ4NPiLdd03',5,NULL,'2024-09-21 06:48:55','2024-09-21 06:49:47');
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
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `compare_at_price` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `img_src` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paf` double(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `variants`
--

LOCK TABLES `variants` WRITE;
/*!40000 ALTER TABLE `variants` DISABLE KEYS */;
INSERT INTO `variants` VALUES (8402735726866,45528326897938,'1 Kg','120.00','21.00','',1.00,'2024-09-13 00:35:36','2024-09-21 20:58:41'),(8381720723730,45440589234450,'1 kg','80.00','','',1.00,'2024-09-13 00:35:36','2024-09-21 21:00:19'),(8381720723730,45762025062674,'1/2 kg','45','','',1.00,'2024-09-13 00:35:36','2024-09-21 21:01:04'),(8381720723730,45921570750738,'1/4 kg','30','','',1.00,'2024-09-13 00:35:36','2024-09-21 21:01:08'),(8381724918034,45440595230994,'1 kg','60.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8381724918034,45781991751954,'1/2 kg','30.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8381724918034,47922282266898,'1/4','15.00','','',1.00,'2024-09-13 00:35:36','2024-09-21 21:04:44'),(8386934309138,45460591640850,'1 Kg','40.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8386934309138,45762129985810,'1/2 Kg','25.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8386934309138,46897026433298,'1/4 kg','12.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8389904072978,45472699580690,'1 Kg','20.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8389904072978,45762216460562,'1/2 Kg','15.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384318275858,45451617435922,'1 Kg','50.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384318275858,45762249523474,'1/2 Kg','25.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8436600635666,45672569962770,'1 Kg','110.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8436600635666,45762287436050,'1/2 Kg','55.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8436600635666,45921448853778,'1/4 Kg','27.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8405690581266,45539109077266,'1 kg','280.00','30.00','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8405690581266,49255107985682,'1/2 kg','140.00','30.00','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384297009426,45451561468178,'1 kg','45.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384297009426,45762366800146,'1/2 kg','30.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384297009426,49106528370962,'1/4','15.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388239982866,45528382079250,'1 kg','90.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388239982866,45762435514642,'1/2 kg','45.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388239982866,46826053042450,'1/4 kg','25.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384761364754,45453118734610,'1 kg','85.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384761364754,45762488795410,'1/2 kg','45.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384761364754,45952843481362,'1/4 kg','30.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8386965012754,45460649410834,'1 kg','70.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8386965012754,45762555642130,'1/2 kg','35.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8402741035282,45528354488594,'1 kg','45.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8402741035282,45762615542034,'1/2 kg','30.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8402741035282,46506869752082,'1/4 Kg','20.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8406321004818,45541648564498,'1 kg','70.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8406321004818,45762664300818,'1/2 kg','33.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388244930834,45465259180306,'1 Dozon','40.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388244930834,49858453111058,'1/2 Dozon','140.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388188274962,45554293801234,'1 kg','50.00','15.00','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388188274962,45762727477522,'1/2 kg','40.00','15.00','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388256465170,45869438894354,'1 Pieces','7.50','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388256465170,49251458777362,'4 Pcs','25.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8436666335506,45672725119250,'1 kg','60.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8436666335506,45762805858578,'1/2 kg','30.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8380311863570,46012902179090,'1 KG','150.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8380311863570,46012902211858,'1/2 KG','175.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8380311863570,46197297840402,'1/4 KG','50.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8380311863570,49909732638994,'100 Gms','40.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384891322642,45861591417106,'1 KG','150.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384891322642,45861642338578,'1/2 KG','75.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384891322642,45861652398354,'1/4 KG','45.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384891322642,46826115563794,'50 gms','25.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384891322642,46850980086034,'little','10.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384267092242,45451467587858,'1 kg','55.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384267092242,45763318186258,'1/2 kg','15.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384267092242,45841034510610,'1/4 kg','15.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388201087250,45529576997138,'1/2 kg','20.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388201087250,45529577029906,'1 kg','150.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388201087250,46792790212882,'100 gms','40.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8388201087250,48220856680722,'Packet','15.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8439642358034,45689748128018,'1 Kg','74.00','70.00','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8439642358034,45763455156498,'1/2 Kg','37.00','70.00','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8389909578002,45472719798546,'1 Kg','45.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8389909578002,45763526361362,'1/2 Kg','25.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8389909578002,46012706160914,'1/4 Kg','16.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384845381906,45453417873682,'1 Kg','45.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384845381906,45763800727826,'1/2 Kg','25.00','','',1.00,'2024-09-13 00:35:36','2024-09-13 00:35:36'),(8384845381906,45921515831570,'1/4 Kg','15.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8396622102802,45501809000722,'3 pcs','20.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8396622102802,49255143768338,'1 pc','5.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(9445320458514,49101369213202,'Default Title','0.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403017138450,45529373114642,'1','45.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8388267573522,45832559821074,'200 g','45.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403094110482,45529542197522,'1 Kg','40.00','42.00','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8378085343506,45428095549714,'1 Kgg','56.00','','',1.00,'2024-09-13 00:35:37','2024-09-15 20:19:58'),(8378085343506,45763992715538,'1/2 Kg','25.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403079954706,45529506578706,'1 Kg','50.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403079954706,45805216825618,'1/2 Kg','25.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403079954706,46012655763730,'1/4 Kg','10.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8380226502930,45434894778642,'1 Kg','60.00','','',1.00,'2024-09-13 00:35:37','2024-09-19 06:10:53'),(8380226502930,45781961539858,'1/2 Kg','30.00','','',1.00,'2024-09-13 00:35:37','2024-09-19 06:11:01'),(8380226502930,50152191230226,'1/4','15.00','','',1.00,'2024-09-13 00:35:37','2024-09-19 06:11:03'),(8388260725010,45465276416274,'1 Kg','30.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8388192567570,45782043689234,'1 Kg','50.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8388192567570,45782043722002,'1/2 Kg','25.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8388192567570,48167603077394,'1/4','15.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8402727108882,45528272699666,'1 dozon','60.00','9.00','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8402727108882,48002016411922,'1/2 dozon','20.00','9.00','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(9445246107922,49101290307858,'Default Title','0.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403038306578,45923611050258,'1 Kg','50.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403038306578,45923611083026,'1/2 Kg','35.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403038306578,46270877335826,'1/4 Kg','15.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403054035218,45529463324946,'1 Kg','34.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8403054035218,46536596783378,'1/2 Kg','17.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8418618278162,45594627277074,'1 kg','40.00','43.00','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8418618278162,48278247309586,'1/2','25.00','43.00','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8381713056018,45539017359634,'1 kg','25.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8381713056018,45802928701714,'1/2 kg','15.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37'),(8381713056018,45868905300242,'1/4 kg','10.00','','',1.00,'2024-09-13 00:35:37','2024-09-13 00:35:37');
/*!40000 ALTER TABLE `variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendors`
--

DROP TABLE IF EXISTS `vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendors`
--

LOCK TABLES `vendors` WRITE;
/*!40000 ALTER TABLE `vendors` DISABLE KEYS */;
/*!40000 ALTER TABLE `vendors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workmen`
--

DROP TABLE IF EXISTS `workmen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workmen` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workmen`
--

LOCK TABLES `workmen` WRITE;
/*!40000 ALTER TABLE `workmen` DISABLE KEYS */;
/*!40000 ALTER TABLE `workmen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zone`
--

DROP TABLE IF EXISTS `zone`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zone` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pin_codes` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `areas` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `focal_pt` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `focal_lon` double(10,8) NOT NULL,
  `focal_lat` double(10,8) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zone`
--

LOCK TABLES `zone` WRITE;
/*!40000 ALTER TABLE `zone` DISABLE KEYS */;
INSERT INTO `zone` VALUES (1,'Nandyal Checkpost','AP-KRNT-NDCP','518002','Elkur Estate,Srivari Sudarshanam(SVS),Joharapuram Housing Board(JHB),SreeRama Nagar,Urban County Villas(MallaReddy Venture),Pavan Residency,Sindhu Estate, Maruti Megacity, Apoorva Apartment Complex','Elkur Estate',78.06527428,15.79895266,NULL,NULL);
/*!40000 ALTER TABLE `zone` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zone_costs`
--

DROP TABLE IF EXISTS `zone_costs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zone_costs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `zone_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned NOT NULL,
  `cost` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cdf` double(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zone_costs`
--

LOCK TABLES `zone_costs` WRITE;
/*!40000 ALTER TABLE `zone_costs` DISABLE KEYS */;
/*!40000 ALTER TABLE `zone_costs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zone_prices`
--

DROP TABLE IF EXISTS `zone_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zone_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zone_prices`
--

LOCK TABLES `zone_prices` WRITE;
/*!40000 ALTER TABLE `zone_prices` DISABLE KEYS */;
/*!40000 ALTER TABLE `zone_prices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'dayli'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2024-09-23  8:08:48
