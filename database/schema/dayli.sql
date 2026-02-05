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
INSERT INTO `categories` VALUES (1,'Food','Find our recipies','2024-10-17 20:39:09','2024-10-17 20:39:09'),(2,'Home','Find the latest trends in interior desgin','2024-10-17 20:39:09','2024-10-17 20:39:09'),(3,'Fashion','Find the latest trends','2024-10-17 20:39:09','2024-10-17 20:39:09');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
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
INSERT INTO `items` VALUES (1,'Alchimia Chair','/home-decor-1.jpg','This is the excerpt for Alchimia Chair','This is the description for Alchimia Chair',0,NULL,NULL,2,NULL,'2024-10-17 20:39:09','2024-10-17 20:39:09'),(2,'Master Bed','/home-decor-2.jpg','This is the excerpt for Master Bed','This is the description for Master Bed',0,NULL,NULL,2,NULL,'2024-10-17 20:39:10','2024-10-17 20:39:10'),(3,'Fancy T-Shirt','/jordan.jpg','This is the excerpt for Fancy T-Shirt','This is the description for Fancy T-Shirt',0,NULL,NULL,3,NULL,'2024-10-17 20:39:10','2024-10-17 20:39:10');
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_10_12_100000_create_password_resets_table',1),(2,'2019_08_19_000000_create_failed_jobs_table',1),(3,'2021_06_04_084747_create_roles_table',1),(4,'2021_06_05_000000_create_users_table',1),(5,'2021_06_08_110000_create_categories_table',1),(6,'2021_06_08_125113_create_tags_table',1),(7,'2021_06_09_061813_create_items_table',1),(8,'2021_06_09_064213_item_tag',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
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
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `product_id` bigint unsigned NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Dayli',
  `product_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'daily-need',
  `handle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'empty-handle',
  `tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '""',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '""',
  `img_src` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '""',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (8402735726866,'Ash gourd','Dayli','Vegetable','ash-gourd','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_8_3d0eae09-10e8-4f9f-9d31-a83d1d3c9267.png?v=1699433161','2024-10-20 06:59:35','2024-10-20 06:59:35'),(8381720723730,'Beans','Dayli','Vegetable','beans','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/6_ffee638c-bd24-49ff-8b8f-5806f65f550a.png?v=1699517317','2024-10-20 06:59:35','2024-10-20 06:59:35'),(8381724918034,'Beetroot','Dayli','Vegetable','beetroot','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/9.png?v=1699434163','2024-10-20 06:59:35','2024-10-20 06:59:35'),(8386934309138,'Bitter Gourd (Kakarakaya)','Dayli','Vegetable','bitter-gord','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/5_68282033-87d0-425a-8549-07ef46323601.png?v=1699517356','2024-10-20 06:59:35','2024-10-20 06:59:35'),(8389904072978,'Bottle Gourd','Dayli','Vegetable','bottle-gourd-1','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/4_0f5e8f0a-6289-4375-b0a0-ecfd6041a74a.png?v=1699517393','2024-10-20 06:59:35','2024-10-20 06:59:35'),(8384318275858,'Brinjals','Dayli','Vegetable','brinjal','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/8_3b90998e-6334-41c9-a425-29d5f0b9431c.png?v=1699514789','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8436600635666,'Broad Beans (Chikkudu)','Dayli','Vegetable','broad-beans-చిక్కుడు-కాయలు','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/6.png?v=1699434241','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8405690581266,'Broccoli','Dayli','Vegetable','briccoli','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_13_87160a9a-096d-4261-b749-b9cdcf69beb2.png?v=1699688016','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384297009426,'Cabbage','Dayli','Vegetable','cabbage','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/3_e5f22674-e66c-48cc-ac28-2ad902bc2492.png?v=1699517427','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388239982866,'Capsicum','Dayli','Vegetable','capsicum','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/5.png?v=1699434366','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384761364754,'Carrot','Dayli','Vegetable','carrot','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/2_64e89771-157b-42fc-b8e2-fce2993ebf99.png?v=1699517250','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8386965012754,'Cauliflower','Dayli','Vegetable','cauliflower','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/8.png?v=1699434409','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8402741035282,'Cluster Beans (Chawlakaya)','Dayli','Vegetable','cluster-beans','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/7.png?v=1699434460','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8406321004818,'Colocasia','Dayli','Vegetable','colocasia','veg','draft','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_14.png?v=1699688284','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388244930834,'Corn','Dayli','Vegetable','corn','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/1.png?v=1699434534','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388188274962,'Cucumber','Dayli','Vegetable','cucumber','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/2.png?v=1699434578','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388256465170,'Drumstick','Dayli','Vegetable','drumstick','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/1_78772fe7-6e3f-47d6-83b0-7ef1a43001bd.png?v=1699517496','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8436666335506,'Elephant Yam','Dayli','Vegetable','elephant-yam-కందగడ్డలు','veg','draft','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_15.png?v=1699688553','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8380311863570,'Garlic','Dayli','Vegetable','garlic','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/4.png?v=1699434629','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384891322642,'Ginger (Allam)','Dayli','Vegetable','ginger','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign10.png?v=1699518056','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384267092242,'Green Chillies','Dayli','Vegetable','green-chillies','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_9.png?v=1699517885','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388201087250,'Green Peas','Dayli','Vegetable','green-peas','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/3.png?v=1699434682','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8439642358034,'Hyacinth Bean','Dayli','Vegetable','hyacinth-bean-అనపకాయ','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_16.png?v=1699688843','2024-10-20 06:59:36','2024-10-20 06:59:36'),(9743288533266,'Hyacinth beans','Dayli','Vegetable','anapakaya-seeds','','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/AnapakayaSeeds.jpg?v=1723270512','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8389909578002,'Ivy Gourd (Dondakaya)','Dayli','Vegetable','ivy-gourd','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_11.png?v=1699518950','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384845381906,'Ladies Finger','Dayli','Vegetable','lady-finger','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/7_287f3141-d932-407a-ab8e-e8ee3e3fecfd.png?v=1699514854','2024-10-20 06:59:36','2024-10-20 06:59:36'),(8396622102802,'Lemon','Dayli','Vegetable','lemon','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/6_d93349d2-a9a5-4eed-a4b3-f0ccd466e36a.png?v=1699514908','2024-10-20 06:59:36','2024-10-20 06:59:36'),(9445320458514,'Maharashtra Onions','Dayli','Vegetable','maharashtra-onions','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/35_21c331d6-8682-4f42-b4a1-7b3c91ecea8b.png?v=1717664702','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403017138450,'Mango Raw','Dayli','Vegetable','mango-raw','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/4_88878888-92d5-4041-8d0d-357fb6ba2cc0.png?v=1699692331','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8388267573522,'Mushrooms','Dayli','Vegetable','mushroom','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_12.png?v=1699519556','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403094110482,'Onion Green','Dayli','Vegetable','onion-green','veg','draft','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_17.png?v=1699692743','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8378085343506,'Onions','Dayli','Vegetable','onions','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/5_bb625300-948f-4deb-9b54-8eb29bc32e4d.png?v=1699514959','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403079954706,'Polur Brinjals (Vankaya)','Dayli','Vegetable','brinjal-big','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/9_1394f52f-6248-49b3-9a32-d17bd179b491.png?v=1699514752','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8380226502930,'Potato (Aalu)','Dayli','Vegetable','patato','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/3_3089f53b-81db-435b-b818-79edc29f6fad.png?v=1699515011','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8388260725010,'Pumpkin','Dayli','Vegetable','pumpkin','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/3_16da38a3-2ca6-4200-a403-5827a31c1924.png?v=1699692377','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8388192567570,'Radish (Mulangi)','Dayli','Vegetable','radish','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/28_6fcdaa2a-398e-40f5-a1ca-e767de772802.png?v=1711527996','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8402727108882,'Raw Banana','Dayli','Vegetable','raw-banana','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/2_f51849d8-9777-45ef-ad5e-4d88f1f0a911.png?v=1699692468','2024-10-20 06:59:37','2024-10-20 06:59:37'),(9445246107922,'Red Capsicum','Dayli','Vegetable','red-capsicum','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/33_5dfb873a-1c7b-4f64-9e8c-00bb794d2a84.png?v=1717664540','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403038306578,'Ridge Gourd (Beerakaya)','Dayli','Vegetable','rigdge-gourd','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/4_75ba7c99-b440-4d31-b741-4242256cc774.png?v=1699515123','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403054035218,'Snake Gourd','Dayli','Vegetable','snake-gourd','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_13.png?v=1699520425','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8418618278162,'Sweet Potato','Dayli','Vegetable','sweet-potato-చిలగడదుంప','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/1_04990a39-a3ab-469c-b73b-5d64290640cb.png?v=1699692261','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8381713056018,'Tomato','Dayli','Vegetable','tomato','veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/1_1e74cd20-ca00-4211-a2be-7807da1c8424.png?v=1699514712','2024-10-20 06:59:37','2024-10-20 06:59:37'),(8436747927826,'Amaranth leaves (Thotakura)','Dayli','Leafy Veg','amaranth-leaves-తోటకూర','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign13.png?v=1712815859','2024-10-20 06:59:38','2024-10-20 06:59:38'),(8439650287890,'Betel Leaf (Thamalapaku)','Dayli','Leafy Veg','betel-leaf-తమలపాకు','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/4_dd73da11-87be-446d-b7f9-9e943dc515f9.png?v=1699856666','2024-10-20 06:59:38','2024-10-20 06:59:38'),(8406312485138,'Colocasia Leaves (Chamakura)','Dayli','Leafy Veg','colocasia-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/3_ec6f8586-59de-411b-97bf-cebdf4f30b14.png?v=1699857464','2024-10-20 06:59:38','2024-10-20 06:59:38'),(8406675587346,'Copper Leaves (Ponaganti)','Dayli','Leafy Veg','copper-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/1_c17a95f3-3d4e-4e79-8d53-93ded3e653b1.png?v=1699856790','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8403120324882,'Coriander Leaves (Kottimira)','Dayli','Leafy Veg','coriander-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_9_2d768b62-6b38-4a23-907b-32ba1288d466.png?v=1699694563','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405482242322,'Curry Leaves','Dayli','Leafy Veg','curry-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign1.png?v=1699694742','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405516124434,'Dill Leaves (Menthulu)','Dayli','Leafy Veg','dill-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/1_a2f9b031-9f1a-47e2-a5cc-482c00b55d08.png?v=1699857389','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406687940882,'Drumsticks Leaves','Dayli','Leafy Veg','drumsticks-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/2_d4acc631-63aa-4f94-967e-c38e39ef42ec.png?v=1699856748','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405486272786,'Fenugreek Leaves (Methi)','Dayli','Leafy Veg','fenugreek-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/6_a736f22a-9ee0-4320-9588-3432bc5d7bd8.png?v=1699856595','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406695215378,'Green Sorrel Leaves (Chukka kura)','Dayli','Leafy Veg','chukka-kura-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/15_3dbeb2e0-a2f0-4652-b3e1-beb85938e4b5.png?v=1712817377','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8449160216850,'Kulfa Leaves (Gangavayala)','Dayli','Leafy Veg','kulfa-leaves-గంగవల్లి-కూర','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/3_d11e68a5-1a5e-4c77-b781-6c6a0ba11a58.png?v=1699856701','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405711323410,'Lettuce Leaves (Palakura)','Dayli','Leafy Veg','lettuce-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Lettuce-Lobjoits-Green-Cos-RHS-0002641-600x400.jpg?v=1688367344','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405490827538,'Mint Leaves','Dayli','Leafy Veg','mint-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/7_bc222d68-f498-490f-884a-7f2bb2e21018.png?v=1699856550','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406301671698,'Mustard Leaves (Aavaalu Aaku)','Dayli','Leafy Veg','mustard-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/18_3a46e3d9-8fe0-4b49-85e7-45413f0b3aaf.png?v=1712820091','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405571469586,'Punarnava Leaves (Galijeru Aaku)','Dayli','Leafy Veg','pnarnava-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/2_4610ace5-92ae-4f46-8af9-5453718984f9.png?v=1699857425','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405548630290,'Sorrel leaves (Gongura)','Dayli','Leafy Veg','gongura-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/8_a6ec8d5a-0bbe-4d1a-9053-21668c2782df.png?v=1699856515','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405494726930,'Sorrel Leaves (Palakura)','Dayli','Leafy Veg','sorrel-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/sorrel_60104ca2-4a4a-4fce-a434-4f0675eac22a.png?v=1692792308','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406336307474,'Spinach Leaves (Palakura/Palak)','Dayli','Leafy Veg','spinach-leaves-1','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign12.png?v=1712815353','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405612757266,'Tamarind Leaves (Chinthapandu Aaku)','Dayli','Leafy Veg','tamarind-leaves','leafy veg','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/44.png?v=1712816395','2024-10-20 06:59:39','2024-10-20 06:59:39'),(8396599460114,'Apple','Dayli','Fruit','apple','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_8_d2f80ec3-9bc6-4e89-94b6-7064590d29fa.png?v=1699426231','2024-10-20 06:59:41','2024-10-20 06:59:41'),(8662321824018,'Avacado','Dayli','Fruit','avacado','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_7_e95f5471-5174-49c0-97dc-6519fb595ac3.png?v=1699353880','2024-10-20 06:59:41','2024-10-20 06:59:41'),(8396609257746,'Baby Orange(Imported)','Dayli','Fruit','orange','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_8_22af7633-fb3c-4fab-8620-e6a115e92086.png?v=1699429949','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8381753393426,'Banana','Dayli','Fruit','banana','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_7_0dbc3619-8793-4d16-b687-5bd70915c0da.png?v=1699354782','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8497380557074,'Black Grapes','Dayli','Fruit','black-grapes','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_7_508e76db-d439-43bd-8984-176dd9b4db41.png?v=1699354551','2024-10-20 06:59:42','2024-10-20 06:59:42'),(9706598236434,'Coconut','Dayli','Fruit','coconut-1','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/cocnut.jpg?v=1721540842','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8396617416978,'Coconut / టెంకాయ','Dayli','Fruit','coconut','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_8_83cd9816-4d0c-45b4-98a9-7b7ffe8229ac.png?v=1699429554','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8413381689618,'Dragon Fruit','Dayli','Fruit','dragan-fruit-డ్రాగన్-ఫ్రూట్','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_8_9c365597-6806-4851-b1be-6940369e0da7.png?v=1699357527','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8396651659538,'Grapes','Dayli','Fruit','grapes','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_6_b74fb738-d119-4d79-a0ed-e2d62cabff3f.png?v=1699332754','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8413251895570,'Guava (Seasonal - Winter)','Dayli','Fruit','guava-జామకాయ','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/kg-guava_123.webp?v=1728995846','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8455953711378,'Java Plum','Dayli','Fruit','java-plum-అల్లనేరేడుపండు','fruit','archived','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Benefits-of-Jamun-photo-stock-1024x683.jpg?v=1728993188','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8413293216018,'Kiwi','Dayli','Fruit','kiwi-కివీపండు','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_7.png?v=1699353186','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8396611748114,'Mango (Summer)','Dayli','Fruit','mango','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/hand-holding-a-mango-fruit.jpg?v=1728996176','2024-10-20 06:59:42','2024-10-20 06:59:42'),(9001587245330,'Nagpur Orange','Dayli','Fruit','nagpur-orange','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_13_d7789d9f-9b88-4f35-bf76-79d01b423fd0.png?v=1717742062','2024-10-20 06:59:42','2024-10-20 06:59:42'),(9430116598034,'Palmyra Palm /Thatimunjal  (Summer)','Dayli','Fruit','palmyra-palm-fruit','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/man-hand-holding-palmyra-palm-fruit-asian-thai-bunch-toddy-147691155.webp?v=1728995505','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8449094025490,'Papaya','Dayli','Fruit','papaya-బొప్పయిపందు','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_8.png?v=1699356315','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8398855340306,'Pineapple','Dayli','Fruit','pineapple','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/images_16.jpg?v=1688032363','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8953505906962,'PineApple','Dayli','Fruit','pineapple-1','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/pinaapple.png?v=1702892468','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8413228925202,'Pomegranate','Dayli','Fruit','pomegranate-దానిమ్మపండు','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_8_f0755b96-be9a-4713-b5b3-bf34631b7217.png?v=1699356738','2024-10-20 06:59:42','2024-10-20 06:59:42'),(8449119420690,'Sapota','Dayli','Fruit','sapota-సపోటపండు','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/1558445608-5254.jpg?v=1689745662','2024-10-20 06:59:43','2024-10-20 06:59:43'),(9654731538706,'Strawberry','Dayli','Fruit','strawberry','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/17_cb4de10b-2bfe-4efa-9f73-6d8331cec191.png?v=1722421729','2024-10-20 06:59:43','2024-10-20 06:59:43'),(8477349249298,'Sweet Lime','Dayli','Fruit','sweet-lime-bttaayipnddu','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_7_f6d72196-30ab-4b7d-a40a-0a7e936d12e9.png?v=1699354354','2024-10-20 06:59:43','2024-10-20 06:59:43'),(8396619219218,'Watermelon (Seasonal - Summer)','Dayli','Fruit','watermelon','fruit','active','https://cdn.shopify.com/s/files/1/0775/1506/3570/files/Untitleddesign_14_fca64f5b-91fa-4df8-a2ee-4fa2295fe669.png?v=1717742363','2024-10-20 06:59:43','2024-10-20 06:59:43');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products_orig`
--

DROP TABLE IF EXISTS `products_orig`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products_orig` (
  `product_id` bigint unsigned NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Dayli',
  `product_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'daily-need',
  `handle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'empty-handle',
  `tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '""',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '""',
  `img_src` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '""',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products_orig`
--

LOCK TABLES `products_orig` WRITE;
/*!40000 ALTER TABLE `products_orig` DISABLE KEYS */;
/*!40000 ALTER TABLE `products_orig` ENABLE KEYS */;
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
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin','Admin user has full access','2024-10-17 20:39:09','2024-10-17 20:39:09'),(2,'Creator','Creator user can add new users','2024-10-17 20:39:09','2024-10-17 20:39:09'),(3,'Member','Member user has minimal access','2024-10-17 20:39:09','2024-10-17 20:39:09');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
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
INSERT INTO `tags` VALUES (1,'Trending','#cb0c9f','2024-10-17 20:39:09','2024-10-17 20:39:09'),(2,'Hot','#ea0606','2024-10-17 20:39:09','2024-10-17 20:39:09'),(3,'New','#17c1e8','2024-10-17 20:39:09','2024-10-17 20:39:09');
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
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
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phoneNo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `first_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `twitter` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facebook` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instagram` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `public_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_foreign` (`role_id`),
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin Admin','Admin','Admin','admin@softui.com','2024-10-17 20:39:09','$2y$10$gXOcpAWbgnHzFw4ncgSkjuxjWBugdbBwPmMcwY0Lb0mJ6YVRyelnq',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'/team-1.jpg',NULL,'2024-10-17 20:39:09','2024-10-17 20:39:09',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1),(2,'Creator Creator','Creator','Creator','creator@softui.com','2024-10-17 20:39:09','$2y$10$2iGP3sC6V9Qyijlvf6yc.OH2GJ9.3Y3OlLwfLB.BqTYo84OTpdati',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'/team-2.jpg',NULL,'2024-10-17 20:39:09','2024-10-17 20:39:09',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2),(3,'Member Member','Member','Member','member@softui.com','2024-10-17 20:39:09','$2y$10$V/5tmiyQwT9IZ1npnmBp0eEENKXxzMnzpAoS76lqZgzfE1VcaRm5q',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'/team-3.jpg',NULL,'2024-10-17 20:39:09','2024-10-17 20:39:09',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,3),(4,'Nasseer Basha',NULL,NULL,'nasseer@dayli.in',NULL,'$2y$10$v9wRzyv0gKhEnsWHQsrrtON9WuOoMqMgPdzwxnwYa3T0chNdDEuli',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'0J7MQYyZ5DxZ5YrOlruqBf7tVjzGFNMU3hJ4rvurwut6gK2k7XqDH9qq6MTT','2024-10-17 20:40:43','2024-10-17 20:40:43',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,3),(5,'Dayli Admin',NULL,NULL,'admin@dayli.in',NULL,'$2y$10$d3.Y/Tu9WEKVFaiyIYNX6enqpgmdWjqgC6TDzMFDJTsDs2s5AZ1rm',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2024-10-18 16:44:21','2024-10-18 16:44:21',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1),(6,'Naseer Basha',NULL,NULL,'naseer@dayli.in',NULL,'$2y$10$emqVolK9glumJCkBz2y9kOzxzA1yfw7CXRJglrO6BbT2e4oE59rvO',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'GBoJx9RNtejfq7wohccI6K8QqH6DoPL72VlKBUoNKK8eOuvvgnxdfJpEe9cF','2024-10-18 18:24:56','2024-10-18 18:24:56',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,3);
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
INSERT INTO `variants` VALUES (8402735726866,45528326897938,'1 Kg','120.00','21.00','',1.00,'2024-10-20 06:59:35','2024-10-20 06:59:35'),(8402735726866,50387754909970,'1/2 Kg','60.00','21.00','',1.00,'2024-10-20 06:59:35','2024-10-20 06:59:35'),(8402735726866,50387754942738,'1/4 Kg','30.00','21.00','',1.00,'2024-10-20 06:59:35','2024-10-20 06:59:35'),(8381720723730,45440589234450,'1 Kg','60.00','','',1.00,'2024-10-20 06:59:35','2024-10-20 06:59:35'),(8381720723730,45762025062674,'1/2 Kg','60.00','','',1.00,'2024-10-20 06:59:35','2024-10-20 06:59:35'),(8381720723730,45921570750738,'1/4 Kg','30.00','','',1.00,'2024-10-20 06:59:35','2024-10-20 06:59:35'),(8381724918034,45440595230994,'1 Kg','50.00','','',1.00,'2024-10-20 06:59:35','2024-10-20 06:59:35'),(8381724918034,45781991751954,'1/2 Kg','25.00','','',1.00,'2024-10-20 06:59:35','2024-10-20 06:59:35'),(8381724918034,47922282266898,'1/4 Kg','15.00','','',1.00,'2024-10-20 06:59:35','2024-10-20 06:59:35'),(8386934309138,45460591640850,'1 Kg','65.00','','',1.00,'2024-10-20 06:59:35','2024-10-20 06:59:35'),(8386934309138,45762129985810,'1/2 Kg','35.00','','',1.00,'2024-10-20 06:59:35','2024-10-20 06:59:35'),(8386934309138,46897026433298,'1/4 Kg','20.00','','',1.00,'2024-10-20 06:59:35','2024-10-20 06:59:35'),(8389904072978,45472699580690,'1 Kg','30.00','','',1.00,'2024-10-20 06:59:35','2024-10-20 06:59:35'),(8389904072978,45762216460562,'1/2 Kg','15.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384318275858,45451617435922,'1 Kg','65.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384318275858,45762249523474,'1/2 Kg','35.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384318275858,50387833454866,'1/4 Kg','20.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8436600635666,45672569962770,'1 Kg','65.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8436600635666,45762287436050,'1/2 Kg','35.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8436600635666,45921448853778,'1/4 Kg','20.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8405690581266,45539109077266,'1 Kg','280.00','30.00','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8405690581266,49255107985682,'1/2 Kg','140.00','30.00','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384297009426,45451561468178,'1 Kg','45.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384297009426,45762366800146,'1/2 Kg','25.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384297009426,49106528370962,'1/4 Kg','15.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388239982866,45528382079250,'1 Kg','80.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388239982866,45762435514642,'1/2 Kg','50.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388239982866,46826053042450,'1/4 Kg','25.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384761364754,45453118734610,'1 Kg','65.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384761364754,45762488795410,'1/2 Kg','35.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384761364754,45952843481362,'1/4 Kg','20.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8386965012754,45460649410834,'1 Kg','60.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8386965012754,45762555642130,'1/2 Kg','30.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8402741035282,45528354488594,'1 Kg','55.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8402741035282,45762615542034,'1/2 Kg','35.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8402741035282,46506869752082,'1/4 Kg','20.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8406321004818,45541648564498,'1 kg','70.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8406321004818,45762664300818,'1/2 kg','35.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388244930834,45465259180306,'1 Dozon','140.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388244930834,49858453111058,'1/2 Dozon','70.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388188274962,45554293801234,'1 Kg','50.00','15.00','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388188274962,45762727477522,'1/2 Kg','25.00','15.00','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388256465170,45869438894354,'1 Pieces','10.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388256465170,49251458777362,'3 Pcs','30.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8436666335506,45672725119250,'1 Kg','60.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8436666335506,45762805858578,'1/2 Kg','30.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8380311863570,46012902179090,'1 Kg','330.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8380311863570,46012902211858,'1/2 Kg','165.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8380311863570,46197297840402,'1/4 Kg','83.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8380311863570,49909732638994,'100 Gms','40.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384891322642,45861591417106,'1 Kg','110.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384891322642,45861642338578,'1/2 Kg','55.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384891322642,45861652398354,'1/4 Kg','30.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384891322642,46826115563794,'100 Gms','15.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384891322642,46850980086034,'little','10.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384267092242,45451467587858,'1 Kg','70.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384267092242,45763318186258,'1/2 Kg','35.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384267092242,45841034510610,'1/4 Kg','20.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388201087250,45529576997138,'1/2 kg','75.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388201087250,45529577029906,'1 kg','150.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388201087250,46792790212882,'100 gms','30.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8388201087250,48220856680722,'Packet','15.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8439642358034,45689748128018,'1 Kg','74.00','70.00','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8439642358034,45763455156498,'1/2 Kg','37.00','70.00','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(9743288533266,50362286965010,'1 Kg','140.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(9743288533266,50362286997778,'1/2 Kg','75.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(9743288533266,50362287030546,'1/4 Kg','40.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8389909578002,45472719798546,'1 Kg','50.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8389909578002,45763526361362,'1/2 Kg','25.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8389909578002,46012706160914,'1/4 Kg','15.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384845381906,45453417873682,'1 Kg','65.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384845381906,45763800727826,'1/2 Kg','35.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8384845381906,45921515831570,'1/4 Kg','20.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8396622102802,45501809000722,'3 pcs','15.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8396622102802,49255143768338,'1 pc','5.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8396622102802,50406864355602,'2 Pcs','10.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8396622102802,50406864388370,'4 Pcs','20.00','','',1.00,'2024-10-20 06:59:36','2024-10-20 06:59:36'),(8396622102802,50406864421138,'1/2 Dozen','30.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8396622102802,50406864453906,'1 Dozen','60.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(9445320458514,50362314522898,'1 Kg','60.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(9445320458514,50362314555666,'1/2 Kg','30.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(9445320458514,50362314588434,'1/4 Kg','20.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403017138450,45529373114642,'1 Pc','45.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8388267573522,45832559821074,'200 Gms','50.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8388267573522,50387955155218,'100  Gms','25.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403094110482,45529542197522,'1 Kg','40.00','42.00','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403094110482,50387956531474,'1/2 Kg','20.00','42.00','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403094110482,50387956564242,'1/4 Kg','15.00','42.00','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8378085343506,45428095549714,'1 Kg','65.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8378085343506,45763992715538,'1/2 Kg','35.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8378085343506,50387959054610,'1/4 Kg','16.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403079954706,45529506578706,'1 Kg','65.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403079954706,45805216825618,'1/2 Kg','35.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403079954706,46012655763730,'1/4 Kg','20.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8380226502930,45434894778642,'1 Kg','65.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8380226502930,45781961539858,'1/2 Kg','35.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8380226502930,50152191230226,'1/4 Kg','18.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8388260725010,45465276416274,'1 Kg','30.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8388192567570,45782043689234,'1 Kg','65.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8388192567570,45782043722002,'1/2 Kg','35.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8388192567570,48167603077394,'1/4 Kg','18.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8402727108882,45528272699666,'1 dozon','60.00','9.00','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8402727108882,48002016411922,'1/2 dozon','20.00','9.00','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(9445246107922,49101290307858,'Default Title','0.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403038306578,45923611050258,'1 Kg','70.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403038306578,45923611083026,'1/2 Kg','35.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403038306578,46270877335826,'1/4 Kg','20.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403054035218,45529463324946,'1 Kg','34.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8403054035218,46536596783378,'1/2 Kg','17.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8418618278162,45594627277074,'1 Kg','50.00','43.00','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8418618278162,48278247309586,'1/2 Kg','25.00','43.00','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8381713056018,45539017359634,'1 Kg','50.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8381713056018,45802928701714,'1/2 Kg','25.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8381713056018,45868905300242,'1/4 Kg','15.00','','',1.00,'2024-10-20 06:59:37','2024-10-20 06:59:37'),(8436747927826,45673001353490,'3 Bunches','15.00','','',1.00,'2024-10-20 06:59:38','2024-10-20 06:59:38'),(8436747927826,50360914510098,'2 Bunches','10.00','','',1.00,'2024-10-20 06:59:38','2024-10-20 06:59:38'),(8436747927826,50360914542866,'1 Bunch','5.00','','',1.00,'2024-10-20 06:59:38','2024-10-20 06:59:38'),(8439650287890,45689779552530,'1 Bunch','18.00','','',1.00,'2024-10-20 06:59:38','2024-10-20 06:59:38'),(8439650287890,50383032418578,'3 Bunches','54.00','','',1.00,'2024-10-20 06:59:38','2024-10-20 06:59:38'),(8439650287890,50383032746258,'2 Bunches','36.00','','',1.00,'2024-10-20 06:59:38','2024-10-20 06:59:38'),(8406312485138,45541592957202,'1 Bunch','10.00','10.00','',1.00,'2024-10-20 06:59:38','2024-10-20 06:59:38'),(8406312485138,50383039070482,'2  Bunches','20.00','10.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406312485138,50383039103250,'3  Bunches','30.00','10.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406675587346,46292949860626,'1 Bunch','10.00','9.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406675587346,50383037038866,'2 Bunches','20.00','9.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406675587346,50383037071634,'3 Bunches','30.00','9.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8403120324882,45529614024978,'1 Bunch Big','20.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8403120324882,45862083559698,'1 Bunch Small','10.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405482242322,45538646098194,'1 Bunch Big','20.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405482242322,45862042337554,'1 Bunch Small','10.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405516124434,45538764161298,'1 Bunch','10.00','9.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405516124434,50383036383506,'2 Bunches','20.00','9.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405516124434,50383036416274,'3 Bunches','30.00','9.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406687940882,45543470203154,'1 pice','6.00','23.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406687940882,50383038087442,'2 pices','12.00','23.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406687940882,50383038120210,'3 pices','18.00','23.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405486272786,45538663104786,'1 Bunch','7.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405486272786,50378429235474,'2 Bunches','14.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405486272786,50378429268242,'3 Bunches','20.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406695215378,46307661119762,'3 Bunches','15.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406695215378,50383053095186,'2 Bunches','10.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406695215378,50383053127954,'1 Bunch','5.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8449160216850,45757627334930,'1 Bunch','10.00','5.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8449160216850,50383034745106,'2 Bunches','20.00','5.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8449160216850,50383034777874,'3 Bunches','30.00','5.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405711323410,45539145875730,'1 Bunch','10.00','18.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405711323410,50388170866962,'2 Bunches','10.00','18.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405711323410,50388170899730,'3 Bunches','10.00','18.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405490827538,45538688958738,'1 Bunch Big','20.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405490827538,45862134022418,'1 Bunch Small','10.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405490827538,50388186267922,'2 Bunches','40.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405490827538,50388186300690,'3 Bunches','60.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406301671698,45541512642834,'1 Bunch','10.00','14.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406301671698,50388173422866,'2 Bunches','20.00','14.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406301671698,50388173455634,'3 Bunches','30.00','14.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405571469586,46283453202706,'1 Bunch','20.00','10.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405571469586,50383035367698,'2 Bunches','40.00','10.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405571469586,50383035400466,'3 Bunches','60.00','10.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405548630290,46307674521874,'1 Bunch','5.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405548630290,50360919359762,'2 Bunches','10.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405548630290,50360919392530,'3 Bunches','15.00','','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405494726930,45538703409426,'2 Bunches','5.00','5.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405494726930,45538703442194,'1 Bunch','10.00','5.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405494726930,50388163854610,'3 Bunches','15.00','5.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406336307474,46283483873554,'3 Bunches','15.00','9.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406336307474,50378440212754,'2 Bunches','10.00','9.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406336307474,50378440245522,'1 Bunch','5.00','9.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8406336307474,50398197776658,'4  Bunches','20.00','9.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405612757266,45538953756946,'1/2 Kg','13.00','10.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405612757266,45538953789714,'1 Kg','25.00','10.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8405612757266,50388168409362,'1/4 Kg','7.00','10.00','',1.00,'2024-10-20 06:59:39','2024-10-20 06:59:39'),(8396599460114,45501735895314,'1 Kg','180.00','','',1.00,'2024-10-20 06:59:41','2024-10-20 06:59:41'),(8396599460114,45762327019794,'1/2 Kg','90.00','','',1.00,'2024-10-20 06:59:41','2024-10-20 06:59:41'),(8396599460114,50416049226002,'1 Pc','30.00','','',1.00,'2024-10-20 06:59:41','2024-10-20 06:59:41'),(8662321824018,50416047915282,'1 Pc','110.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8396609257746,46035992314130,'1 Kg','200.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8396609257746,46315298259218,'1/2 Kg','100.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8396609257746,50416049193234,'1 Pc','15.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8381753393426,45440632979730,'1 Dozen Big','75.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8381753393426,45938448990482,'1/2 Dozen Big','35.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8381753393426,45804907200786,'1 Dozen Small','60.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8381753393426,46301405413650,'1/2 Dozen Small','30.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8381753393426,47766661988626,'1 Pc','5.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8497380557074,45988043686162,'1 Kg','160.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8497380557074,45988043718930,'1/2 Kg','80.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8497380557074,45988043751698,'1/4 Kg','40.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(9706598236434,50362356039954,'1 Pc','60.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8396617416978,45501791535378,'1','20.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8413381689618,50416168861970,'1 Pc','80.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8396651659538,45501869326610,'1 Kg','160.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8396651659538,45763250848018,'1/2 Kg','80.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8413251895570,45575813890322,'1 Kg','50.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8413251895570,45763391357202,'1/2 Kg','25.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8455953711378,50416048046354,'2 Pc','135.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8455953711378,50416048079122,'3 Pc','68.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8455953711378,50416048111890,'4 Pc','35.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8413293216018,47705825542418,'1 Pc','40.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8396611748114,45501773218066,'1 Kg','140.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8396611748114,50388026687762,'1/2 Kg','70.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(9001587245330,48040111931666,'1 Kg','140.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(9001587245330,48040111964434,'1/2 Kg','70.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(9430116598034,49143737614610,'1 Kg','120.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(9430116598034,49143737647378,'1/2 Kg','60.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8449094025490,50416048275730,'1 Pc - Big','90.00','40.00','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8449094025490,50416048308498,'1 Pc - Small','60.00','30.00','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8398855340306,45514690691346,'1 Kg','130.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8398855340306,45764343333138,'1/2 Kg','65.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8398855340306,50416048505106,'1 Pc','33.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8953505906962,50362371899666,'1 Pc','90.00','','',1.00,'2024-10-20 06:59:42','2024-10-20 06:59:42'),(8413228925202,45575638057234,'1 Kg','200.00','','',1.00,'2024-10-20 06:59:43','2024-10-20 06:59:43'),(8413228925202,45803116527890,'1/2 Kg','100.00','','',1.00,'2024-10-20 06:59:43','2024-10-20 06:59:43'),(8413228925202,50416048439570,'1 Pc - Small','25.00','','',1.00,'2024-10-20 06:59:43','2024-10-20 06:59:43'),(8413228925202,50416048472338,'1 Pc - Big','65.00','','',1.00,'2024-10-20 06:59:43','2024-10-20 06:59:43'),(8449119420690,50416048177426,'5 Piece','140.00','35.00','',1.00,'2024-10-20 06:59:43','2024-10-20 06:59:43'),(8449119420690,50416048210194,'6 Piece','70.00','35.00','',1.00,'2024-10-20 06:59:43','2024-10-20 06:59:43'),(9654731538706,49848795037970,'1 Kg','430.00','','',1.00,'2024-10-20 06:59:43','2024-10-20 06:59:43'),(9654731538706,50383063253266,'1/2 Kg','215.00','','',1.00,'2024-10-20 06:59:43','2024-10-20 06:59:43'),(8477349249298,45862189498642,'1 Kg','70.00','','',1.00,'2024-10-20 06:59:43','2024-10-20 06:59:43'),(8477349249298,45862189531410,'1/2 Kg','35.00','','',1.00,'2024-10-20 06:59:43','2024-10-20 06:59:43'),(8477349249298,50416048013586,'1 Pc','25.00','','',1.00,'2024-10-20 06:59:43','2024-10-20 06:59:43'),(8396619219218,45501795959058,'1 Kg','60.00','0.00','',1.00,'2024-10-20 06:59:43','2024-10-20 06:59:43'),(8396619219218,48177789174034,'1/2  Kg','30.00','0.00','',1.00,'2024-10-20 06:59:43','2024-10-20 06:59:43');
/*!40000 ALTER TABLE `variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `variants_old`
--

DROP TABLE IF EXISTS `variants_old`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `variants_old` (
  `product_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `compare_at_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `img_src` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `paf` double(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `variants_old`
--

LOCK TABLES `variants_old` WRITE;
/*!40000 ALTER TABLE `variants_old` DISABLE KEYS */;
/*!40000 ALTER TABLE `variants_old` ENABLE KEYS */;
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
  `pin_codes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `areas` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `focal_pt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `focal_lon` double(10,8) NOT NULL,
  `focal_lat` double(10,8) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zone`
--

LOCK TABLES `zone` WRITE;
/*!40000 ALTER TABLE `zone` DISABLE KEYS */;
/*!40000 ALTER TABLE `zone` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2024-10-20 22:41:43
