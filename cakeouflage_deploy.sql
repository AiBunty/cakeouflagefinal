-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: cakeouflage_dev
-- ------------------------------------------------------
-- Server version	8.0.45

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
-- Table structure for table `admin_action_logs`
--

DROP TABLE IF EXISTS `admin_action_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_action_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint unsigned NOT NULL,
  `action_type` varchar(120) NOT NULL,
  `target_type` varchar(120) NOT NULL,
  `target_id` bigint DEFAULT NULL,
  `entity_type` varchar(120) DEFAULT NULL,
  `entity_id` varchar(60) DEFAULT NULL,
  `metadata_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `admin_action_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_action_logs`
--

LOCK TABLES `admin_action_logs` WRITE;
/*!40000 ALTER TABLE `admin_action_logs` DISABLE KEYS */;
INSERT INTO `admin_action_logs` VALUES (1,1,'update_communication_template','communication_templates',1,NULL,NULL,'{\"channel\": \"email\", \"event_key\": \"build_your_cake_quote_email\", \"is_active\": 1}','2026-05-19 17:59:11');
/*!40000 ALTER TABLE `admin_action_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_permissions`
--

DROP TABLE IF EXISTS `admin_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_permissions` (
  `admin_id` bigint unsigned NOT NULL,
  `permission_key` varchar(80) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`,`permission_key`),
  CONSTRAINT `admin_permissions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_permissions`
--

LOCK TABLES `admin_permissions` WRITE;
/*!40000 ALTER TABLE `admin_permissions` DISABLE KEYS */;
INSERT INTO `admin_permissions` VALUES (2,'banners','2026-05-19 13:50:09'),(2,'build_your_cake','2026-05-19 13:50:09'),(2,'categories','2026-05-19 13:50:09'),(2,'coupons','2026-05-19 13:50:09'),(2,'crm_logs','2026-05-19 13:50:09'),(2,'crm_report','2026-05-19 13:50:09'),(2,'crm_settings','2026-05-19 13:50:09'),(2,'dashboard','2026-05-19 13:50:09'),(2,'follow_ups','2026-05-19 13:50:09'),(2,'import_products','2026-05-19 13:50:09'),(2,'manual_orders','2026-05-19 13:50:09'),(2,'order_credit','2026-05-19 13:50:09'),(2,'order_delete','2026-05-19 13:50:09'),(2,'order_edit','2026-05-19 13:50:09'),(2,'order_refund','2026-05-19 13:50:09'),(2,'order_reject','2026-05-19 13:50:09'),(2,'orders','2026-05-19 13:50:09'),(2,'payment_verification','2026-05-19 13:50:09'),(2,'production_plan','2026-05-19 13:50:09'),(2,'products','2026-05-19 13:50:09'),(2,'revenue_report','2026-05-19 13:50:09');
/*!40000 ALTER TABLE `admin_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','sales_manager','ops_manager','content_manager') NOT NULL DEFAULT 'admin',
  `department_label` varchar(40) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_admin_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'Dcore','aibuntysystems@gmail.com','$2y$10$afzwShu1KAIL4g19Ee8kruhCXV9C.12k7nFBi6kNt7ol8F79Cjmwm','super_admin',NULL,1,'2026-05-19 04:27:59','2026-05-20 03:45:21'),(2,'Ansh','cakeouflage@gmail.com','$2y$10$6Pw1QmjHrq8uroYAUrt3Jed5JEs3hASu79P6LQHGllESu6FfNcjKq','admin','',1,'2026-05-19 07:53:23','2026-05-20 03:45:21');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auth_rate_limits`
--

DROP TABLE IF EXISTS `auth_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_rate_limits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scope_key` varchar(80) NOT NULL,
  `bucket_key` varchar(190) NOT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `blocked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auth_rate_limit` (`scope_key`,`bucket_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auth_rate_limits`
--

LOCK TABLES `auth_rate_limits` WRITE;
/*!40000 ALTER TABLE `auth_rate_limits` DISABLE KEYS */;
/*!40000 ALTER TABLE `auth_rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_rules`
--

DROP TABLE IF EXISTS `automation_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `automation_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rule_key` varchar(80) NOT NULL,
  `channel` enum('email','whatsapp','internal') NOT NULL,
  `trigger_event` varchar(80) NOT NULL,
  `template_id` bigint unsigned DEFAULT NULL,
  `offset_days` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rule_key` (`rule_key`),
  KEY `template_id` (`template_id`),
  CONSTRAINT `automation_rules_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `communication_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_rules`
--

LOCK TABLES `automation_rules` WRITE;
/*!40000 ALTER TABLE `automation_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `automation_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `b2b_accounts`
--

DROP TABLE IF EXISTS `b2b_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `b2b_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `company_name` varchar(180) NOT NULL,
  `account_type` enum('corporate_client','business_buyer','reseller','cake_shop_owner') NOT NULL,
  `gst_number` varchar(40) DEFAULT NULL,
  `company_phone` varchar(25) NOT NULL,
  `company_email` varchar(190) NOT NULL,
  `approval_status` enum('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  `credit_limit` decimal(12,2) DEFAULT NULL,
  `assigned_admin_id` bigint unsigned DEFAULT NULL,
  `notes` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `assigned_admin_id` (`assigned_admin_id`),
  KEY `idx_b2b_status` (`approval_status`),
  CONSTRAINT `b2b_accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `b2b_accounts_ibfk_2` FOREIGN KEY (`assigned_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `b2b_accounts`
--

LOCK TABLES `b2b_accounts` WRITE;
/*!40000 ALTER TABLE `b2b_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `b2b_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `b2b_addresses`
--

DROP TABLE IF EXISTS `b2b_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `b2b_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `b2b_account_id` bigint unsigned NOT NULL,
  `address_type` enum('billing','shipping') NOT NULL,
  `recipient_name` varchar(120) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `line1` varchar(190) NOT NULL,
  `line2` varchar(190) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `postal_code` varchar(15) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `b2b_account_id` (`b2b_account_id`),
  CONSTRAINT `b2b_addresses_ibfk_1` FOREIGN KEY (`b2b_account_id`) REFERENCES `b2b_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `b2b_addresses`
--

LOCK TABLES `b2b_addresses` WRITE;
/*!40000 ALTER TABLE `b2b_addresses` DISABLE KEYS */;
/*!40000 ALTER TABLE `b2b_addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `b2b_documents`
--

DROP TABLE IF EXISTS `b2b_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `b2b_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `b2b_account_id` bigint unsigned NOT NULL,
  `document_type` enum('gst_certificate','trade_license','purchase_order','invoice','other') NOT NULL,
  `file_url` varchar(255) NOT NULL,
  `uploaded_by` enum('b2b_user','admin') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `b2b_account_id` (`b2b_account_id`),
  CONSTRAINT `b2b_documents_ibfk_1` FOREIGN KEY (`b2b_account_id`) REFERENCES `b2b_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `b2b_documents`
--

LOCK TABLES `b2b_documents` WRITE;
/*!40000 ALTER TABLE `b2b_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `b2b_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `b2b_order_items`
--

DROP TABLE IF EXISTS `b2b_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `b2b_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `b2b_order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  `customisation_note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `b2b_order_id` (`b2b_order_id`),
  KEY `product_id` (`product_id`),
  KEY `variant_id` (`variant_id`),
  CONSTRAINT `b2b_order_items_ibfk_1` FOREIGN KEY (`b2b_order_id`) REFERENCES `b2b_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `b2b_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `b2b_order_items_ibfk_3` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `b2b_order_items`
--

LOCK TABLES `b2b_order_items` WRITE;
/*!40000 ALTER TABLE `b2b_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `b2b_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `b2b_orders`
--

DROP TABLE IF EXISTS `b2b_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `b2b_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(40) NOT NULL,
  `b2b_account_id` bigint unsigned NOT NULL,
  `source_quote_id` bigint unsigned DEFAULT NULL,
  `fulfilment_mode` enum('delivery','pickup') NOT NULL,
  `order_status` enum('pending','confirmed','in_production','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` enum('pending','paid','part_paid','failed') NOT NULL DEFAULT 'pending',
  `subtotal` decimal(10,2) NOT NULL,
  `discount_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(10,2) NOT NULL,
  `internal_note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `b2b_account_id` (`b2b_account_id`),
  KEY `source_quote_id` (`source_quote_id`),
  KEY `idx_b2b_orders_number` (`order_number`),
  CONSTRAINT `b2b_orders_ibfk_1` FOREIGN KEY (`b2b_account_id`) REFERENCES `b2b_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `b2b_orders_ibfk_2` FOREIGN KEY (`source_quote_id`) REFERENCES `b2b_quotes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `b2b_orders`
--

LOCK TABLES `b2b_orders` WRITE;
/*!40000 ALTER TABLE `b2b_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `b2b_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `b2b_price_lists`
--

DROP TABLE IF EXISTS `b2b_price_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `b2b_price_lists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `b2b_account_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `min_quantity` int NOT NULL DEFAULT '1',
  `wholesale_price` decimal(10,2) NOT NULL,
  `retail_price_snapshot` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `b2b_account_id` (`b2b_account_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `b2b_price_lists_ibfk_1` FOREIGN KEY (`b2b_account_id`) REFERENCES `b2b_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `b2b_price_lists_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `b2b_price_lists`
--

LOCK TABLES `b2b_price_lists` WRITE;
/*!40000 ALTER TABLE `b2b_price_lists` DISABLE KEYS */;
/*!40000 ALTER TABLE `b2b_price_lists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `b2b_quote_items`
--

DROP TABLE IF EXISTS `b2b_quote_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `b2b_quote_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quote_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  `customisation_note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `quote_id` (`quote_id`),
  KEY `product_id` (`product_id`),
  KEY `variant_id` (`variant_id`),
  CONSTRAINT `b2b_quote_items_ibfk_1` FOREIGN KEY (`quote_id`) REFERENCES `b2b_quotes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `b2b_quote_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `b2b_quote_items_ibfk_3` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `b2b_quote_items`
--

LOCK TABLES `b2b_quote_items` WRITE;
/*!40000 ALTER TABLE `b2b_quote_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `b2b_quote_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `b2b_quotes`
--

DROP TABLE IF EXISTS `b2b_quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `b2b_quotes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quote_number` varchar(40) NOT NULL,
  `b2b_account_id` bigint unsigned NOT NULL,
  `event_type` varchar(120) DEFAULT NULL,
  `fulfilment_mode` enum('delivery','pickup') NOT NULL,
  `scheduled_date` date DEFAULT NULL,
  `scheduled_slot_id` bigint unsigned DEFAULT NULL,
  `status` enum('requested','drafted','sent','accepted','rejected','converted_to_order') NOT NULL DEFAULT 'requested',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `admin_note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quote_number` (`quote_number`),
  KEY `b2b_account_id` (`b2b_account_id`),
  KEY `scheduled_slot_id` (`scheduled_slot_id`),
  KEY `idx_b2b_quotes_number` (`quote_number`),
  CONSTRAINT `b2b_quotes_ibfk_1` FOREIGN KEY (`b2b_account_id`) REFERENCES `b2b_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `b2b_quotes_ibfk_2` FOREIGN KEY (`scheduled_slot_id`) REFERENCES `delivery_time_slots` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `b2b_quotes`
--

LOCK TABLES `b2b_quotes` WRITE;
/*!40000 ALTER TABLE `b2b_quotes` DISABLE KEYS */;
/*!40000 ALTER TABLE `b2b_quotes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_alert_utrs`
--

DROP TABLE IF EXISTS `bank_alert_utrs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_alert_utrs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source` enum('apps_script','customer_submit','admin_manual') NOT NULL DEFAULT 'apps_script',
  `parsed_utr` varchar(40) NOT NULL,
  `parsed_amount` decimal(12,2) DEFAULT NULL,
  `bank_sender` varchar(190) DEFAULT NULL,
  `email_subject` varchar(255) DEFAULT NULL,
  `alert_message` text,
  `event_time` datetime DEFAULT NULL,
  `status` enum('pending','matched_auto','confirmed','rejected','duplicate','ignored') NOT NULL DEFAULT 'pending',
  `match_confidence` enum('none','weak','strong') NOT NULL DEFAULT 'none',
  `customer_user_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `confirm_note` text,
  `confirmed_by_admin_id` bigint unsigned DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `raw_payload_json` longtext,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_alert_utr` (`parsed_utr`),
  KEY `idx_bank_alert_status` (`status`),
  KEY `idx_bank_alert_order` (`order_id`),
  KEY `idx_bank_alert_created` (`created_at`),
  KEY `customer_user_id` (`customer_user_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `payment_id` (`payment_id`),
  KEY `confirmed_by_admin_id` (`confirmed_by_admin_id`),
  CONSTRAINT `bank_alert_utrs_ibfk_1` FOREIGN KEY (`customer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_alert_utrs_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_alert_utrs_ibfk_3` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_alert_utrs_ibfk_4` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_alert_utrs_ibfk_5` FOREIGN KEY (`confirmed_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_alert_utrs`
--

LOCK TABLES `bank_alert_utrs` WRITE;
/*!40000 ALTER TABLE `bank_alert_utrs` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_alert_utrs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `banners`
--

DROP TABLE IF EXISTS `banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL,
  `subtitle` varchar(260) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `cta_label` varchar(80) DEFAULT NULL,
  `cta_url` varchar(190) DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `linked_coupon_id` bigint unsigned DEFAULT NULL,
  `page_scope` enum('all_pages','exclude_checkout_auth') NOT NULL DEFAULT 'all_pages',
  `placement` enum('home_hero','home_mid','site_top_offer','home_top_offer','shop_top','course_top','b2b_top') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_banners_offer_active_window` (`placement`,`is_active`,`starts_at`,`ends_at`),
  KEY `idx_banners_linked_coupon` (`linked_coupon_id`),
  CONSTRAINT `banners_ibfk_1` FOREIGN KEY (`linked_coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_banners_linked_coupon` FOREIGN KEY (`linked_coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `banners`
--

LOCK TABLES `banners` WRITE;
/*!40000 ALTER TABLE `banners` DISABLE KEYS */;
INSERT INTO `banners` VALUES (1,'10% Off Limited Time Offer','','','','/shop','2026-05-19 00:00:00','2026-05-21 23:59:59',1,'all_pages','site_top_offer',1,0,'2026-05-19 08:58:39','2026-05-19 13:46:05');
/*!40000 ALTER TABLE `banners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `byoc_quote_links`
--

DROP TABLE IF EXISTS `byoc_quote_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `byoc_quote_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `byoc_quote_id` bigint unsigned NOT NULL,
  `token` varchar(120) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_byoc_quote_links_quote` (`byoc_quote_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `byoc_quote_links`
--

LOCK TABLES `byoc_quote_links` WRITE;
/*!40000 ALTER TABLE `byoc_quote_links` DISABLE KEYS */;
INSERT INTO `byoc_quote_links` VALUES (1,1,'374b35cbfb5321233eb23dd04b2a8431075e8387a5b7f2e4','2026-05-22 16:52:37',NULL,0,'2026-05-19 16:52:37'),(2,2,'d0fb92c85ed7b1abc02f8014c13a111c21d84154e6b91a9a','2026-05-22 16:52:56',NULL,0,'2026-05-19 16:52:56'),(3,3,'0d49743ba87f8094eb8972516122ff88cb29316e0cc41ecc','2026-05-22 16:53:14','2026-05-19 17:02:32',0,'2026-05-19 16:53:14');
/*!40000 ALTER TABLE `byoc_quote_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `byoc_quotes`
--

DROP TABLE IF EXISTS `byoc_quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `byoc_quotes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inquiry_id` bigint unsigned NOT NULL,
  `quote_number` varchar(50) NOT NULL,
  `quote_subject` varchar(180) NOT NULL,
  `quote_message` text,
  `quote_amount` decimal(10,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'INR',
  `status` enum('sent','accepted','expired','cancelled') NOT NULL DEFAULT 'sent',
  `expires_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `created_by_admin_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quote_number` (`quote_number`),
  KEY `order_id` (`order_id`),
  KEY `created_by_admin_id` (`created_by_admin_id`),
  KEY `idx_byoc_quotes_inquiry` (`inquiry_id`),
  KEY `idx_byoc_quotes_status` (`status`),
  CONSTRAINT `byoc_quotes_ibfk_1` FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `byoc_quotes_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `byoc_quotes_ibfk_3` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `byoc_quotes`
--

LOCK TABLES `byoc_quotes` WRITE;
/*!40000 ALTER TABLE `byoc_quotes` DISABLE KEYS */;
INSERT INTO `byoc_quotes` VALUES (1,1,'BYOC-20260519-397005','Your Custom Birthday Cake Quote','Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!',2800.00,'INR','cancelled','2026-05-22 16:52:37',NULL,NULL,NULL,'2026-05-19 16:52:37','2026-05-19 16:52:56'),(2,1,'BYOC-20260519-103606','Your Custom Birthday Cake Quote','Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!',2800.00,'INR','cancelled','2026-05-22 16:52:56',NULL,NULL,NULL,'2026-05-19 16:52:56','2026-05-19 16:53:14'),(3,1,'BYOC-20260519-802327','Custom Cake Quote','3-tier chocolate cake',2800.00,'INR','accepted','2026-05-22 16:53:14','2026-05-19 17:02:32',1,NULL,'2026-05-19 16:53:14','2026-05-19 18:03:36');
/*!40000 ALTER TABLE `byoc_quotes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart_items`
--

DROP TABLE IF EXISTS `cart_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `variant_id` (`variant_id`),
  KEY `idx_cart_items_cart` (`cart_id`),
  CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `cart_items_ibfk_3` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_items`
--

LOCK TABLES `cart_items` WRITE;
/*!40000 ALTER TABLE `cart_items` DISABLE KEYS */;
INSERT INTO `cart_items` VALUES (1,9,24,139,1,915.00,915.00,'2026-05-19 14:02:40','2026-05-19 14:02:40'),(2,9,29,169,1,1090.00,1090.00,'2026-05-19 14:21:56','2026-05-19 14:21:56'),(3,10,25,145,1,950.00,950.00,'2026-05-19 16:20:55','2026-05-19 16:20:55');
/*!40000 ALTER TABLE `cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `carts`
--

DROP TABLE IF EXISTS `carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `carts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'INR',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_carts_user` (`user_id`),
  KEY `idx_carts_session` (`session_id`),
  CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carts`
--

LOCK TABLES `carts` WRITE;
/*!40000 ALTER TABLE `carts` DISABLE KEYS */;
INSERT INTO `carts` VALUES (1,NULL,'2c6610537028fbfe06d9f30c5be4d75a','INR','2026-05-19 04:01:04','2026-05-19 04:01:04'),(2,NULL,'9d13599beb69f0f781633e409c17a8b0','INR','2026-05-19 05:25:52','2026-05-19 05:25:52'),(3,NULL,'66ec0f374bf3205fe095e8dc1a2d9140','INR','2026-05-19 05:33:39','2026-05-19 05:33:39'),(4,NULL,'522ca7c8e0962b29c6cdfffa55c84cbc','INR','2026-05-19 08:28:16','2026-05-19 08:28:16'),(5,NULL,'fda09f918ed2ab2c4a8b26e9fddc61e1','INR','2026-05-19 09:05:03','2026-05-19 09:05:03'),(6,NULL,'e3022dc840ab03f55db3919f75bcc8ba','INR','2026-05-19 11:38:59','2026-05-19 11:38:59'),(7,NULL,'20610bd29187db87946f7fc4ba70d4da','INR','2026-05-19 11:58:15','2026-05-19 11:58:15'),(8,NULL,'f001b47128787d02e22992ff6555b444','INR','2026-05-19 12:22:48','2026-05-19 12:22:48'),(9,1,'6b37477c206884f10180f74b3a300f41','INR','2026-05-19 12:36:44','2026-05-19 14:21:15'),(10,NULL,'ed5e36a67aea48032b7c5bae07041b8d','INR','2026-05-19 16:20:37','2026-05-19 16:20:37'),(11,NULL,'1231e9d02cedccf166294f147c95d8d3','INR','2026-05-19 16:32:51','2026-05-19 16:32:51'),(12,NULL,'332d5668a8247e4cd58f06451a5dd0d2','INR','2026-05-20 05:44:21','2026-05-20 05:44:21');
/*!40000 ALTER TABLE `carts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text,
  `image` varchar(255) DEFAULT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `menu_icon` varchar(80) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `show_in_menu` tinyint(1) NOT NULL DEFAULT '1',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `seo_title` varchar(190) DEFAULT NULL,
  `seo_description` varchar(260) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_categories_parent_id` (`parent_id`),
  KEY `idx_categories_slug` (`slug`),
  KEY `idx_categories_menu` (`show_in_menu`,`is_active`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,NULL,'Opera Cakes','opera-cakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL),(2,1,'Chocolate Opera','chocolate-opera',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL),(3,1,'Coffee Opera','coffee-opera',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL),(4,1,'Fruit Opera','fruit-opera',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL),(5,1,'Signature Opera','signature-opera',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL),(6,NULL,'Decorated Cakes','decorated-cakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL),(7,6,'Birthday Decor','birthday-decor',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL),(8,6,'Anniversary Decor','anniversary-decor',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL),(9,6,'Kids Theme','kids-theme',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL),(10,6,'Wedding Decor','wedding-decor',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL),(11,NULL,'Classic Cakes','classic-cakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL),(12,11,'Chocolate Classics','chocolate-classics',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL),(13,11,'Vanilla Classics','vanilla-classics',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL),(14,11,'Fruit Classics','fruit-classics',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL),(15,11,'Tea Time Classics','tea-time-classics',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL),(16,NULL,'Cheesecakes','cheesecakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL),(17,16,'Baked Cheesecakes','baked-cheesecakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL),(18,16,'No Bake Cheesecakes','no-bake-cheesecakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL),(19,16,'Berry Cheesecakes','berry-cheesecakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL),(20,16,'Premium Cheesecakes','premium-cheesecakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL),(21,NULL,'Celebration Cakes','celebration-cakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL),(22,21,'Birthday Celebration','birthday-celebration',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL),(23,21,'Engagement Celebration','engagement-celebration',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL),(24,21,'Baby Shower','baby-shower',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL),(25,21,'Milestone Celebration','milestone-celebration',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL),(26,NULL,'Tea Cakes & Breads','teacakes-breads',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL),(27,26,'Loaf Cakes','loaf-cakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL),(28,26,'Bundt Cakes','bundt-cakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL),(29,26,'Artisan Breads','artisan-breads',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL),(30,26,'Quick Breads','quick-breads',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL),(31,NULL,'Dessert Jars','dessert-jars',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL),(32,31,'Chocolate Jars','chocolate-jars',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL),(33,31,'Fruit Jars','fruit-jars',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL),(34,31,'Mousse Jars','mousse-jars',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL),(35,31,'Seasonal Jars','seasonal-jars',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL),(36,NULL,'Brownies & Bars','brownies-bars',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL),(37,36,'Fudge Brownies','fudge-brownies',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL),(38,36,'Nutty Brownies','nutty-brownies',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL),(39,36,'Blondies','blondies',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL),(40,36,'Dessert Bars','dessert-bars',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL),(41,NULL,'Cupcakes','cupcakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL),(42,41,'Classic Cupcakes','classic-cupcakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL),(43,41,'Filled Cupcakes','filled-cupcakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL),(44,41,'Party Cupcakes','party-cupcakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL),(45,41,'Premium Cupcakes','premium-cupcakes',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL),(46,NULL,'Seasonal Specials','seasonal-specials',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL),(47,46,'Summer Specials','summer-specials',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL),(48,46,'Monsoon Specials','monsoon-specials',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL),(49,46,'Festive Specials','festive-specials',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL),(50,46,'Winter Specials','winter-specials',NULL,NULL,NULL,NULL,0,1,0,1,NULL,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `communication_logs`
--

DROP TABLE IF EXISTS `communication_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `communication_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `b2b_account_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `whatsapp_template_id` bigint unsigned DEFAULT NULL,
  `channel` enum('email','whatsapp','internal') NOT NULL,
  `event_key` varchar(80) NOT NULL,
  `recipient` varchar(190) NOT NULL,
  `status` enum('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `provider_message_id` varchar(190) DEFAULT NULL,
  `error_message` varchar(260) DEFAULT NULL,
  `payload_json` json DEFAULT NULL,
  `retry_count` int NOT NULL DEFAULT '0',
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `b2b_account_id` (`b2b_account_id`),
  KEY `order_id` (`order_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `whatsapp_template_id` (`whatsapp_template_id`),
  KEY `idx_comm_logs_status` (`status`),
  KEY `idx_comm_logs_channel` (`channel`),
  KEY `idx_comm_logs_event` (`event_key`),
  CONSTRAINT `communication_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `communication_logs_ibfk_2` FOREIGN KEY (`b2b_account_id`) REFERENCES `b2b_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `communication_logs_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `communication_logs_ibfk_4` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `communication_logs_ibfk_5` FOREIGN KEY (`whatsapp_template_id`) REFERENCES `whatsapp_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `communication_logs`
--

LOCK TABLES `communication_logs` WRITE;
/*!40000 ALTER TABLE `communication_logs` DISABLE KEYS */;
INSERT INTO `communication_logs` VALUES (1,NULL,NULL,NULL,NULL,NULL,'email','build_your_cake_quote_email','parin11@gmail.com','sent','smtp-20260519230415',NULL,'{\"name\": \"Parin Daulat\", \"email\": \"parin11@gmail.com\", \"phone\": \"+919330033000\", \"lead_id\": 1, \"subject\": \"Your Custom Birthday Cake Quote\", \"event_date\": \"2026-06-15\", \"first_name\": \"Parin\", \"inquiry_id\": 1, \"budget_range\": \"2000-3500\", \"quote_amount\": \"2800.00\", \"quote_number\": \"BYOC-20260519-397005\", \"body_template\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"byoc_quote_id\": 1, \"quote_message\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"quote_subject\": \"Your Custom Birthday Cake Quote\", \"reference_file\": \"\", \"diet_preference\": \"Veg\", \"quote_expiry_at\": \"2026-05-22 16:52:37\", \"event_information\": \"Birthday\", \"quote_accept_link\": \"http://localhost:8888/quote/accept/374b35cbfb5321233eb23dd04b2a8431075e8387a5b7f2e4\", \"design_breif_notes\": \"Chocolate truffle 3-tier cake, floral theme, pastel pink and white. Name: Happy Birthday Parin\", \"number_of_servings_guests\": \"30\"}',0,'2026-05-19 17:34:15','2026-05-19 16:52:37'),(2,NULL,NULL,NULL,NULL,NULL,'whatsapp','build_your_cake_quote_whatsapp','+919330033000','failed',NULL,'No active approved WhatsApp template mapping for event: build_your_cake_quote_whatsapp','{\"name\": \"Parin Daulat\", \"email\": \"parin11@gmail.com\", \"phone\": \"+919330033000\", \"lead_id\": 1, \"subject\": \"Your Custom Birthday Cake Quote\", \"event_date\": \"2026-06-15\", \"first_name\": \"Parin\", \"inquiry_id\": 1, \"budget_range\": \"2000-3500\", \"quote_amount\": \"2800.00\", \"quote_number\": \"BYOC-20260519-397005\", \"body_template\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"byoc_quote_id\": 1, \"quote_message\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"quote_subject\": \"Your Custom Birthday Cake Quote\", \"reference_file\": \"\", \"diet_preference\": \"Veg\", \"quote_expiry_at\": \"2026-05-22 16:52:37\", \"event_information\": \"Birthday\", \"quote_accept_link\": \"http://localhost:8888/quote/accept/374b35cbfb5321233eb23dd04b2a8431075e8387a5b7f2e4\", \"design_breif_notes\": \"Chocolate truffle 3-tier cake, floral theme, pastel pink and white. Name: Happy Birthday Parin\", \"number_of_servings_guests\": \"30\"}',0,NULL,'2026-05-19 16:52:37'),(3,NULL,NULL,NULL,NULL,NULL,'email','build_your_cake_quote_email','parin11@gmail.com','sent','smtp-20260519230418',NULL,'{\"name\": \"Parin Daulat\", \"email\": \"parin11@gmail.com\", \"phone\": \"+919330033000\", \"lead_id\": 1, \"subject\": \"Your Custom Birthday Cake Quote\", \"event_date\": \"2026-06-15\", \"first_name\": \"Parin\", \"inquiry_id\": 1, \"budget_range\": \"2000-3500\", \"quote_amount\": \"2800.00\", \"quote_number\": \"BYOC-20260519-103606\", \"body_template\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"byoc_quote_id\": 2, \"quote_message\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"quote_subject\": \"Your Custom Birthday Cake Quote\", \"reference_file\": \"\", \"diet_preference\": \"Veg\", \"quote_expiry_at\": \"2026-05-22 16:52:56\", \"event_information\": \"Birthday\", \"quote_accept_link\": \"http://localhost:8888/quote/accept/d0fb92c85ed7b1abc02f8014c13a111c21d84154e6b91a9a\", \"design_breif_notes\": \"Chocolate truffle 3-tier cake, floral theme, pastel pink and white. Name: Happy Birthday Parin\", \"number_of_servings_guests\": \"30\"}',0,'2026-05-19 17:34:18','2026-05-19 16:52:56'),(4,NULL,NULL,NULL,NULL,NULL,'whatsapp','build_your_cake_quote_whatsapp','+919330033000','failed',NULL,'No active approved WhatsApp template mapping for event: build_your_cake_quote_whatsapp','{\"name\": \"Parin Daulat\", \"email\": \"parin11@gmail.com\", \"phone\": \"+919330033000\", \"lead_id\": 1, \"subject\": \"Your Custom Birthday Cake Quote\", \"event_date\": \"2026-06-15\", \"first_name\": \"Parin\", \"inquiry_id\": 1, \"budget_range\": \"2000-3500\", \"quote_amount\": \"2800.00\", \"quote_number\": \"BYOC-20260519-103606\", \"body_template\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"byoc_quote_id\": 2, \"quote_message\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"quote_subject\": \"Your Custom Birthday Cake Quote\", \"reference_file\": \"\", \"diet_preference\": \"Veg\", \"quote_expiry_at\": \"2026-05-22 16:52:56\", \"event_information\": \"Birthday\", \"quote_accept_link\": \"http://localhost:8888/quote/accept/d0fb92c85ed7b1abc02f8014c13a111c21d84154e6b91a9a\", \"design_breif_notes\": \"Chocolate truffle 3-tier cake, floral theme, pastel pink and white. Name: Happy Birthday Parin\", \"number_of_servings_guests\": \"30\"}',0,NULL,'2026-05-19 16:52:57'),(5,NULL,NULL,NULL,NULL,NULL,'email','build_your_cake_quote_email','parin11@gmail.com','sent','smtp-20260519230421',NULL,'{\"name\": \"Parin Daulat\", \"email\": \"parin11@gmail.com\", \"phone\": \"+919330033000\", \"lead_id\": 1, \"subject\": \"Custom Cake Quote\", \"event_date\": \"2026-06-15\", \"first_name\": \"Parin\", \"inquiry_id\": 1, \"budget_range\": \"2000-3500\", \"quote_amount\": \"2800.00\", \"quote_number\": \"BYOC-20260519-802327\", \"body_template\": \"3-tier chocolate cake\", \"byoc_quote_id\": 3, \"quote_message\": \"3-tier chocolate cake\", \"quote_subject\": \"Custom Cake Quote\", \"reference_file\": \"\", \"diet_preference\": \"Veg\", \"quote_expiry_at\": \"2026-05-22 16:53:14\", \"event_information\": \"Birthday\", \"quote_accept_link\": \"http://localhost:8888/quote/accept/0d49743ba87f8094eb8972516122ff88cb29316e0cc41ecc\", \"design_breif_notes\": \"Chocolate truffle 3-tier cake, floral theme, pastel pink and white. Name: Happy Birthday Parin\", \"number_of_servings_guests\": \"30\"}',0,'2026-05-19 17:34:21','2026-05-19 16:53:14'),(6,NULL,NULL,NULL,NULL,NULL,'whatsapp','build_your_cake_quote_whatsapp','+919330033000','failed',NULL,'No active approved WhatsApp template mapping for event: build_your_cake_quote_whatsapp','{\"name\": \"Parin Daulat\", \"email\": \"parin11@gmail.com\", \"phone\": \"+919330033000\", \"lead_id\": 1, \"subject\": \"Custom Cake Quote\", \"event_date\": \"2026-06-15\", \"first_name\": \"Parin\", \"inquiry_id\": 1, \"budget_range\": \"2000-3500\", \"quote_amount\": \"2800.00\", \"quote_number\": \"BYOC-20260519-802327\", \"body_template\": \"3-tier chocolate cake\", \"byoc_quote_id\": 3, \"quote_message\": \"3-tier chocolate cake\", \"quote_subject\": \"Custom Cake Quote\", \"reference_file\": \"\", \"diet_preference\": \"Veg\", \"quote_expiry_at\": \"2026-05-22 16:53:14\", \"event_information\": \"Birthday\", \"quote_accept_link\": \"http://localhost:8888/quote/accept/0d49743ba87f8094eb8972516122ff88cb29316e0cc41ecc\", \"design_breif_notes\": \"Chocolate truffle 3-tier cake, floral theme, pastel pink and white. Name: Happy Birthday Parin\", \"number_of_servings_guests\": \"30\"}',0,NULL,'2026-05-19 16:53:14'),(7,NULL,NULL,1,NULL,NULL,'email','invoice_paid','parin11@gmail.com','sent','smtp-20260519234726',NULL,'{\"attachments\": [{\"filename\": \"invoice-BYOC-20260519-215201.html\", \"mime_type\": \"text/html\", \"content_base64\": \"PCFET0NUWVBFIGh0bWw+PGh0bWw+PGhlYWQ+PG1ldGEgY2hhcnNldD0iVVRGLTgiPjx0aXRsZT5JbnZvaWNlIEJZT0MtMjAyNjA1MTktMjE1MjAxPC90aXRsZT48c3R5bGU+DQogICAgICBib2R5e2ZvbnQtZmFtaWx5OkFyaWFsLEhlbHZldGljYSxzYW5zLXNlcmlmO2JhY2tncm91bmQ6I2ZmZjtjb2xvcjojMTExO21hcmdpbjowO3BhZGRpbmc6MThweH0NCiAgICAgIC5pbnZvaWNle21heC13aWR0aDo4NjBweDttYXJnaW46MCBhdXRvO2JvcmRlcjoycHggc29saWQgIzExMTtwYWRkaW5nOjIycHh9DQogICAgICAuaGVhZHtkaXNwbGF5OmZsZXg7anVzdGlmeS1jb250ZW50OnNwYWNlLWJldHdlZW47Z2FwOjIwcHg7Ym9yZGVyLWJvdHRvbToycHggc29saWQgIzExMTtwYWRkaW5nLWJvdHRvbToxNHB4O21hcmdpbi1ib3R0b206MTZweDthbGlnbi1pdGVtczpmbGV4LXN0YXJ0fQ0KICAgICAgLmJyYW5ke2Rpc3BsYXk6ZmxleDtmbGV4LWRpcmVjdGlvbjpjb2x1bW47Z2FwOjhweH0NCiAgICAgIC5icmFuZCBpbWd7aGVpZ2h0OjQ4cHg7d2lkdGg6YXV0bztkaXNwbGF5OmJsb2NrfQ0KICAgICAgLmJyYW5kLXRleHR7Zm9udC1zaXplOjEycHg7Y29sb3I6IzY2Nn0NCiAgICAgIC5tZXRhe2ZvbnQtc2l6ZToxM3B4O2xpbmUtaGVpZ2h0OjEuNX0NCiAgICAgIC5ncmlke2Rpc3BsYXk6Z3JpZDtncmlkLXRlbXBsYXRlLWNvbHVtbnM6MWZyIDFmcjtnYXA6MTZweDttYXJnaW4tYm90dG9tOjE2cHh9DQogICAgICAuYm94e2JvcmRlcjoxcHggc29saWQgIzExMTtwYWRkaW5nOjEwcHh9DQogICAgICAuYm94IGgze21hcmdpbjowIDAgOHB4O2ZvbnQtc2l6ZToxM3B4O3RleHQtdHJhbnNmb3JtOnVwcGVyY2FzZTtsZXR0ZXItc3BhY2luZzowLjA4ZW19DQogICAgICB0YWJsZXt3aWR0aDoxMDAlO2JvcmRlci1jb2xsYXBzZTpjb2xsYXBzZX0NCiAgICAgIHRoLHRke2JvcmRlcjoxcHggc29saWQgIzExMTtwYWRkaW5nOjhweDtmb250LXNpemU6MTNweH0NCiAgICAgIHRoe2JhY2tncm91bmQ6I2VmZWZlZjt0ZXh0LXRyYW5zZm9ybTp1cHBlcmNhc2U7bGV0dGVyLXNwYWNpbmc6MC4wNWVtO2ZvbnQtc2l6ZToxMXB4fQ0KICAgICAgLnRvdGFsc3ttYXJnaW4tdG9wOjEycHg7bWF4LXdpZHRoOjMyMHB4O21hcmdpbi1sZWZ0OmF1dG99DQogICAgICAudG90YWxzIHRhYmxlIHRke3BhZGRpbmc6N3B4fQ0KICAgICAgLmdyYW5kIHRke2ZvbnQtd2VpZ2h0OjcwMDtmb250LXNpemU6MTVweH0NCiAgICAgIC5mb290ZXJ7bWFyZ2luLXRvcDoxOHB4O2JvcmRlci10b3A6MXB4IHNvbGlkICMxMTE7cGFkZGluZy10b3A6MTBweDtmb250LXNpemU6MTJweDtsaW5lLWhlaWdodDoxLjZ9DQogICAgICBAbWVkaWEgcHJpbnR7Ym9keXtwYWRkaW5nOjB9Lmludm9pY2V7Ym9yZGVyOjFweCBzb2xpZCAjMTExO2JveC1zaGFkb3c6bm9uZX19DQogICAgPC9zdHlsZT48L2hlYWQ+PGJvZHk+PGRpdiBjbGFzcz0iaW52b2ljZSI+DQogICAgICA8ZGl2IGNsYXNzPSJoZWFkIj4NCiAgICAgICAgPGRpdiBjbGFzcz0iYnJhbmQiPjxpbWcgc3JjPSIvY2xpZW50L2Fzc2V0cy9pbWFnZXMvbWFpbmxvZ28uc3ZnIiBhbHQ9IkNha2VvdWZsYWdlIj48ZGl2IGNsYXNzPSJicmFuZC10ZXh0Ij5DYWtlb3VmbGFnZTwvZGl2PjwvZGl2Pg0KICAgICAgICA8ZGl2IGNsYXNzPSJtZXRhIj4NCiAgICAgICAgICA8ZGl2PjxzdHJvbmc+SW52b2ljZSAjOjwvc3Ryb25nPiBCWU9DLTIwMjYwNTE5LTIxNTIwMTwvZGl2Pg0KICAgICAgICAgIDxkaXY+PHN0cm9uZz5EYXRlOjwvc3Ryb25nPiAyMDI2LTA1LTE5IDE3OjAyOjMyPC9kaXY+DQogICAgICAgICAgPGRpdj48c3Ryb25nPlBheW1lbnQ6PC9zdHJvbmc+IENhc2g8L2Rpdj4NCiAgICAgICAgICA8ZGl2PjxzdHJvbmc+U3RhdHVzOjwvc3Ryb25nPiBQQUlEPC9kaXY+DQogICAgICAgIDwvZGl2Pg0KICAgICAgPC9kaXY+DQogICAgICA8ZGl2IGNsYXNzPSJncmlkIj4NCiAgICAgICAgPGRpdiBjbGFzcz0iYm94Ij48aDM+QmlsbCBUbzwvaDM+PGRpdj48c3Ryb25nPlBhcmluIERhdWxhdDwvc3Ryb25nPjwvZGl2PjxkaXY+UGhvbmU6IDkzMzAwMzMwMDA8L2Rpdj48ZGl2PkVtYWlsOiBwYXJpbjExQGdtYWlsLmNvbTwvZGl2PjxkaXYgc3R5bGU9Im1hcmdpbi10b3A6NnB4Ij48ZGl2PkFkZHJlc3Mgbm90IHByb3ZpZGVkPC9kaXY+PC9kaXY+PC9kaXY+DQogICAgICAgIDxkaXYgY2xhc3M9ImJveCI+PGgzPkJ1c2luZXNzPC9oMz48ZGl2PjxzdHJvbmc+Q2FrZW91ZmxhZ2U8L3N0cm9uZz48L2Rpdj48ZGl2PkFkZHJlc3Mgbm90IGNvbmZpZ3VyZWQ8L2Rpdj48ZGl2PlBob25lOiA8L2Rpdj48ZGl2PkVtYWlsOiA8L2Rpdj48L2Rpdj4NCiAgICAgIDwvZGl2Pg0KICAgICAgPHRhYmxlPjx0aGVhZD48dHI+PHRoPkl0ZW08L3RoPjx0aD5RdHk8L3RoPjx0aD5SYXRlPC90aD48dGg+QW1vdW50PC90aD48L3RyPjwvdGhlYWQ+PHRib2R5Pjx0cj48dGQ+Q3VzdG9tIENha2UgUXVvdGU8L3RkPjx0ZCBzdHlsZT0idGV4dC1hbGlnbjpjZW50ZXIiPjE8L3RkPjx0ZCBzdHlsZT0idGV4dC1hbGlnbjpyaWdodCI+UnMgMiw4MDAuMDA8L3RkPjx0ZCBzdHlsZT0idGV4dC1hbGlnbjpyaWdodCI+UnMgMiw4MDAuMDA8L3RkPjwvdHI+PC90Ym9keT48L3RhYmxlPg0KICAgICAgPGRpdiBjbGFzcz0idG90YWxzIj48dGFibGU+DQogICAgICAgIDx0cj48dGQ+U3VidG90YWw8L3RkPjx0ZCBzdHlsZT0idGV4dC1hbGlnbjpyaWdodCI+UnMgMiw4MDAuMDA8L3RkPjwvdHI+DQogICAgICAgIDx0cj48dGQ+RGlzY291bnQ8L3RkPjx0ZCBzdHlsZT0idGV4dC1hbGlnbjpyaWdodCI+UnMgMC4wMDwvdGQ+PC90cj4NCiAgICAgICAgPHRyPjx0ZD5UYXg8L3RkPjx0ZCBzdHlsZT0idGV4dC1hbGlnbjpyaWdodCI+UnMgMC4wMDwvdGQ+PC90cj4NCiAgICAgICAgPHRyPjx0ZD5EZWxpdmVyeTwvdGQ+PHRkIHN0eWxlPSJ0ZXh0LWFsaWduOnJpZ2h0Ij5ScyAwLjAwPC90ZD48L3RyPg0KICAgICAgICA8dHIgY2xhc3M9ImdyYW5kIj48dGQ+VG90YWwgUGFpZDwvdGQ+PHRkIHN0eWxlPSJ0ZXh0LWFsaWduOnJpZ2h0Ij5ScyAyLDgwMC4wMDwvdGQ+PC90cj4NCiAgICAgIDwvdGFibGU+PC9kaXY+DQogICAgICA8ZGl2IGNsYXNzPSJmb290ZXIiPlRoYW5rIHlvdSBmb3IgY2hvb3NpbmcgQ2FrZW91ZmxhZ2UuIFRoaXMgaXMgYSBzeXN0ZW0tZ2VuZXJhdGVkIHBhaWQgaW52b2ljZSBhbmQgZG9lcyBub3QgcmVxdWlyZSBhIHBoeXNpY2FsIHNpZ25hdHVyZS48L2Rpdj4NCiAgICA8L2Rpdj48L2JvZHk+PC9odG1sPg==\"}], \"invoice_html\": \"<!DOCTYPE html><html><head><meta charset=\\\"UTF-8\\\"><title>Invoice BYOC-20260519-215201</title><style>\\r\\n      body{font-family:Arial,Helvetica,sans-serif;background:#fff;color:#111;margin:0;padding:18px}\\r\\n      .invoice{max-width:860px;margin:0 auto;border:2px solid #111;padding:22px}\\r\\n      .head{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid #111;padding-bottom:14px;margin-bottom:16px;align-items:flex-start}\\r\\n      .brand{display:flex;flex-direction:column;gap:8px}\\r\\n      .brand img{height:48px;width:auto;display:block}\\r\\n      .brand-text{font-size:12px;color:#666}\\r\\n      .meta{font-size:13px;line-height:1.5}\\r\\n      .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}\\r\\n      .box{border:1px solid #111;padding:10px}\\r\\n      .box h3{margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:0.08em}\\r\\n      table{width:100%;border-collapse:collapse}\\r\\n      th,td{border:1px solid #111;padding:8px;font-size:13px}\\r\\n      th{background:#efefef;text-transform:uppercase;letter-spacing:0.05em;font-size:11px}\\r\\n      .totals{margin-top:12px;max-width:320px;margin-left:auto}\\r\\n      .totals table td{padding:7px}\\r\\n      .grand td{font-weight:700;font-size:15px}\\r\\n      .footer{margin-top:18px;border-top:1px solid #111;padding-top:10px;font-size:12px;line-height:1.6}\\r\\n      @media print{body{padding:0}.invoice{border:1px solid #111;box-shadow:none}}\\r\\n    </style></head><body><div class=\\\"invoice\\\">\\r\\n      <div class=\\\"head\\\">\\r\\n        <div class=\\\"brand\\\"><img src=\\\"/client/assets/images/mainlogo.svg\\\" alt=\\\"Cakeouflage\\\"><div class=\\\"brand-text\\\">Cakeouflage</div></div>\\r\\n        <div class=\\\"meta\\\">\\r\\n          <div><strong>Invoice #:</strong> BYOC-20260519-215201</div>\\r\\n          <div><strong>Date:</strong> 2026-05-19 17:02:32</div>\\r\\n          <div><strong>Payment:</strong> Cash</div>\\r\\n          <div><strong>Status:</strong> PAID</div>\\r\\n        </div>\\r\\n      </div>\\r\\n      <div class=\\\"grid\\\">\\r\\n        <div class=\\\"box\\\"><h3>Bill To</h3><div><strong>Parin Daulat</strong></div><div>Phone: 9330033000</div><div>Email: parin11@gmail.com</div><div style=\\\"margin-top:6px\\\"><div>Address not provided</div></div></div>\\r\\n        <div class=\\\"box\\\"><h3>Business</h3><div><strong>Cakeouflage</strong></div><div>Address not configured</div><div>Phone: </div><div>Email: </div></div>\\r\\n      </div>\\r\\n      <table><thead><tr><th>Item</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead><tbody><tr><td>Custom Cake Quote</td><td style=\\\"text-align:center\\\">1</td><td style=\\\"text-align:right\\\">Rs 2,800.00</td><td style=\\\"text-align:right\\\">Rs 2,800.00</td></tr></tbody></table>\\r\\n      <div class=\\\"totals\\\"><table>\\r\\n        <tr><td>Subtotal</td><td style=\\\"text-align:right\\\">Rs 2,800.00</td></tr>\\r\\n        <tr><td>Discount</td><td style=\\\"text-align:right\\\">Rs 0.00</td></tr>\\r\\n        <tr><td>Tax</td><td style=\\\"text-align:right\\\">Rs 0.00</td></tr>\\r\\n        <tr><td>Delivery</td><td style=\\\"text-align:right\\\">Rs 0.00</td></tr>\\r\\n        <tr class=\\\"grand\\\"><td>Total Paid</td><td style=\\\"text-align:right\\\">Rs 2,800.00</td></tr>\\r\\n      </table></div>\\r\\n      <div class=\\\"footer\\\">Thank you for choosing Cakeouflage. This is a system-generated paid invoice and does not require a physical signature.</div>\\r\\n    </div></body></html>\", \"order_number\": \"BYOC-20260519-215201\", \"customer_name\": \"Parin Daulat\"}',0,'2026-05-19 18:17:26','2026-05-19 18:03:36'),(8,NULL,NULL,1,NULL,NULL,'email','payment_confirmed_customer','parin11@gmail.com','sent','smtp-20260519234729',NULL,'{\"user_id\": 0, \"order_id\": 1, \"upi_link\": \"upi://pay?pa=test@upi&pn=Cakeouflage&am=2800.00\", \"first_name\": \"Parin\", \"item_names\": \"Custom Cake Quote\", \"grand_total\": \"2800.00\", \"contact.item\": \"Custom Cake Quote\", \"contact.name\": \"Parin Daulat\", \"order_number\": \"BYOC-20260519-215201\", \"contact.email\": \"parin11@gmail.com\", \"contact.phone\": \"9330033000\", \"customer_name\": \"Parin Daulat\", \"contact.amount\": \"2800.00\", \"contact.mobile\": \"9330033000\", \"customer_email\": \"parin11@gmail.com\", \"customer_phone\": \"9330033000\", \"contact.orderid\": \"BYOC-20260519-215201\", \"contact.upi_link\": \"upi://pay?pa=test@upi&pn=Cakeouflage&am=2800.00\", \"contact.first_name\": \"Parin\", \"trigger_resolved_key\": \"payment_confirmed_customer\", \"trigger_requested_key\": \"payment_confirmed_customer\"}',0,'2026-05-19 18:17:29','2026-05-19 18:03:37'),(9,NULL,NULL,1,NULL,NULL,'email','payment_confirmed_admin','cakeouflage@gmail.com','sent','smtp-20260519234732',NULL,'{\"user_id\": 0, \"order_id\": 1, \"upi_link\": \"upi://pay?pa=test@upi&pn=Cakeouflage&am=2800.00\", \"first_name\": \"Parin\", \"item_names\": \"Custom Cake Quote\", \"grand_total\": \"2800.00\", \"contact.item\": \"Custom Cake Quote\", \"contact.name\": \"Parin Daulat\", \"order_number\": \"BYOC-20260519-215201\", \"contact.email\": \"parin11@gmail.com\", \"contact.phone\": \"9330033000\", \"customer_name\": \"Parin Daulat\", \"contact.amount\": \"2800.00\", \"contact.mobile\": \"9330033000\", \"customer_email\": \"parin11@gmail.com\", \"customer_phone\": \"9330033000\", \"recipient_role\": \"admin_primary\", \"admin_cc_emails\": \"\", \"contact.orderid\": \"BYOC-20260519-215201\", \"contact.upi_link\": \"upi://pay?pa=test@upi&pn=Cakeouflage&am=2800.00\", \"contact.first_name\": \"Parin\", \"admin_primary_email\": \"cakeouflage@gmail.com\", \"trigger_resolved_key\": \"payment_confirmed_admin\", \"trigger_requested_key\": \"payment_confirmed_admin\"}',0,'2026-05-19 18:17:32','2026-05-19 18:03:37'),(10,NULL,NULL,NULL,NULL,NULL,'email','build_your_cake_quote_email','parin11@gmail.com','sent','smtp-20260519234735',NULL,'{\"name\": \"Parin Daulat\", \"event_date\": \"2026-06-10\", \"first_name\": \"Parin\", \"budget_range\": \"2000 - 2500\", \"quote_amount\": \"2200.00\", \"quote_number\": \"BYOC-TEST-001\", \"quote_message\": \"We have crafted a beautiful custom cake design just for you! Your order will feature a 2kg fondant cake with a personalised topper, matching the birthday theme you described. Freshly baked with premium ingredients and delivered with care.\", \"quote_subject\": \"Custom Birthday Cake Quote\", \"advance_amount\": \"1100.00\", \"diet_preference\": \"Eggless\", \"quote_expiry_at\": \"2026-05-26 17:00:00\", \"event_information\": \"Birthday\", \"quote_accept_link\": \"http://localhost:8888/quote/accept/TESTPREVIEWTOKEN123\", \"quote_expiry_display\": \"26 May 2026 at 05:00 PM\", \"number_of_servings_guests\": \"25\"}',0,'2026-05-19 18:17:35','2026-05-19 18:17:15');
/*!40000 ALTER TABLE `communication_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `communication_queue`
--

DROP TABLE IF EXISTS `communication_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `communication_queue` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `communication_log_id` bigint unsigned DEFAULT NULL,
  `channel` enum('email','whatsapp','internal') NOT NULL,
  `queue_status` enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  `payload_json` json DEFAULT NULL,
  `available_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `attempts` int NOT NULL DEFAULT '0',
  `last_error` varchar(260) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `communication_log_id` (`communication_log_id`),
  KEY `idx_comm_queue_status` (`queue_status`,`available_at`),
  CONSTRAINT `communication_queue_ibfk_1` FOREIGN KEY (`communication_log_id`) REFERENCES `communication_logs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `communication_queue`
--

LOCK TABLES `communication_queue` WRITE;
/*!40000 ALTER TABLE `communication_queue` DISABLE KEYS */;
INSERT INTO `communication_queue` VALUES (1,1,'email','completed','{\"name\": \"Parin Daulat\", \"email\": \"parin11@gmail.com\", \"phone\": \"+919330033000\", \"lead_id\": 1, \"subject\": \"Your Custom Birthday Cake Quote\", \"event_date\": \"2026-06-15\", \"first_name\": \"Parin\", \"inquiry_id\": 1, \"budget_range\": \"2000-3500\", \"quote_amount\": \"2800.00\", \"quote_number\": \"BYOC-20260519-397005\", \"body_template\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"byoc_quote_id\": 1, \"quote_message\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"quote_subject\": \"Your Custom Birthday Cake Quote\", \"reference_file\": \"\", \"diet_preference\": \"Veg\", \"quote_expiry_at\": \"2026-05-22 16:52:37\", \"event_information\": \"Birthday\", \"quote_accept_link\": \"http://localhost:8888/quote/accept/374b35cbfb5321233eb23dd04b2a8431075e8387a5b7f2e4\", \"design_breif_notes\": \"Chocolate truffle 3-tier cake, floral theme, pastel pink and white. Name: Happy Birthday Parin\", \"number_of_servings_guests\": \"30\"}','2026-05-19 16:52:37',0,NULL,'2026-05-19 16:52:37','2026-05-19 17:34:15'),(2,2,'whatsapp','failed','{\"name\": \"Parin Daulat\", \"email\": \"parin11@gmail.com\", \"phone\": \"+919330033000\", \"lead_id\": 1, \"subject\": \"Your Custom Birthday Cake Quote\", \"event_date\": \"2026-06-15\", \"first_name\": \"Parin\", \"inquiry_id\": 1, \"budget_range\": \"2000-3500\", \"quote_amount\": \"2800.00\", \"quote_number\": \"BYOC-20260519-397005\", \"body_template\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"byoc_quote_id\": 1, \"quote_message\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"quote_subject\": \"Your Custom Birthday Cake Quote\", \"reference_file\": \"\", \"diet_preference\": \"Veg\", \"quote_expiry_at\": \"2026-05-22 16:52:37\", \"event_information\": \"Birthday\", \"quote_accept_link\": \"http://localhost:8888/quote/accept/374b35cbfb5321233eb23dd04b2a8431075e8387a5b7f2e4\", \"design_breif_notes\": \"Chocolate truffle 3-tier cake, floral theme, pastel pink and white. Name: Happy Birthday Parin\", \"number_of_servings_guests\": \"30\"}','2026-05-19 16:52:37',2,'No active approved WhatsApp template mapping for event: build_your_cake_quote_whatsapp','2026-05-19 16:52:37','2026-05-19 18:17:21'),(3,3,'email','completed','{\"name\": \"Parin Daulat\", \"email\": \"parin11@gmail.com\", \"phone\": \"+919330033000\", \"lead_id\": 1, \"subject\": \"Your Custom Birthday Cake Quote\", \"event_date\": \"2026-06-15\", \"first_name\": \"Parin\", \"inquiry_id\": 1, \"budget_range\": \"2000-3500\", \"quote_amount\": \"2800.00\", \"quote_number\": \"BYOC-20260519-103606\", \"body_template\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"byoc_quote_id\": 2, \"quote_message\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"quote_subject\": \"Your Custom Birthday Cake Quote\", \"reference_file\": \"\", \"diet_preference\": \"Veg\", \"quote_expiry_at\": \"2026-05-22 16:52:56\", \"event_information\": \"Birthday\", \"quote_accept_link\": \"http://localhost:8888/quote/accept/d0fb92c85ed7b1abc02f8014c13a111c21d84154e6b91a9a\", \"design_breif_notes\": \"Chocolate truffle 3-tier cake, floral theme, pastel pink and white. Name: Happy Birthday Parin\", \"number_of_servings_guests\": \"30\"}','2026-05-19 16:52:57',0,NULL,'2026-05-19 16:52:57','2026-05-19 17:34:18'),(4,4,'whatsapp','failed','{\"name\": \"Parin Daulat\", \"email\": \"parin11@gmail.com\", \"phone\": \"+919330033000\", \"lead_id\": 1, \"subject\": \"Your Custom Birthday Cake Quote\", \"event_date\": \"2026-06-15\", \"first_name\": \"Parin\", \"inquiry_id\": 1, \"budget_range\": \"2000-3500\", \"quote_amount\": \"2800.00\", \"quote_number\": \"BYOC-20260519-103606\", \"body_template\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"byoc_quote_id\": 2, \"quote_message\": \"Dear Parin, we are delighted to create your 3-tier chocolate truffle floral cake for 30 guests. Pastel pink and white theme with your name. A stunning centerpiece for your birthday!\", \"quote_subject\": \"Your Custom Birthday Cake Quote\", \"reference_file\": \"\", \"diet_preference\": \"Veg\", \"quote_expiry_at\": \"2026-05-22 16:52:56\", \"event_information\": \"Birthday\", \"quote_accept_link\": \"http://localhost:8888/quote/accept/d0fb92c85ed7b1abc02f8014c13a111c21d84154e6b91a9a\", \"design_breif_notes\": \"Chocolate truffle 3-tier cake, floral theme, pastel pink and white. Name: Happy Birthday Parin\", \"number_of_servings_guests\": \"30\"}','2026-05-19 16:52:57',2,'No active approved WhatsApp template mapping for event: build_your_cake_quote_whatsapp','2026-05-19 16:52:57','2026-05-19 18:17:21'),(5,5,'email','completed','{\"name\": \"Parin Daulat\", \"email\": \"parin11@gmail.com\", \"phone\": \"+919330033000\", \"lead_id\": 1, \"subject\": \"Custom Cake Quote\", \"event_date\": \"2026-06-15\", \"first_name\": \"Parin\", \"inquiry_id\": 1, \"budget_range\": \"2000-3500\", \"quote_amount\": \"2800.00\", \"quote_number\": \"BYOC-20260519-802327\", \"body_template\": \"3-tier chocolate cake\", \"byoc_quote_id\": 3, \"quote_message\": \"3-tier chocolate cake\", \"quote_subject\": \"Custom Cake Quote\", \"reference_file\": \"\", \"diet_preference\": \"Veg\", \"quote_expiry_at\": \"2026-05-22 16:53:14\", \"event_information\": \"Birthday\", \"quote_accept_link\": \"http://localhost:8888/quote/accept/0d49743ba87f8094eb8972516122ff88cb29316e0cc41ecc\", \"design_breif_notes\": \"Chocolate truffle 3-tier cake, floral theme, pastel pink and white. Name: Happy Birthday Parin\", \"number_of_servings_guests\": \"30\"}','2026-05-19 16:53:14',0,NULL,'2026-05-19 16:53:14','2026-05-19 17:34:22'),(6,6,'whatsapp','failed','{\"name\": \"Parin Daulat\", \"email\": \"parin11@gmail.com\", \"phone\": \"+919330033000\", \"lead_id\": 1, \"subject\": \"Custom Cake Quote\", \"event_date\": \"2026-06-15\", \"first_name\": \"Parin\", \"inquiry_id\": 1, \"budget_range\": \"2000-3500\", \"quote_amount\": \"2800.00\", \"quote_number\": \"BYOC-20260519-802327\", \"body_template\": \"3-tier chocolate cake\", \"byoc_quote_id\": 3, \"quote_message\": \"3-tier chocolate cake\", \"quote_subject\": \"Custom Cake Quote\", \"reference_file\": \"\", \"diet_preference\": \"Veg\", \"quote_expiry_at\": \"2026-05-22 16:53:14\", \"event_information\": \"Birthday\", \"quote_accept_link\": \"http://localhost:8888/quote/accept/0d49743ba87f8094eb8972516122ff88cb29316e0cc41ecc\", \"design_breif_notes\": \"Chocolate truffle 3-tier cake, floral theme, pastel pink and white. Name: Happy Birthday Parin\", \"number_of_servings_guests\": \"30\"}','2026-05-19 16:53:14',2,'No active approved WhatsApp template mapping for event: build_your_cake_quote_whatsapp','2026-05-19 16:53:14','2026-05-19 18:17:21'),(7,7,'email','completed','{\"log_id\": 7}','2026-05-19 18:03:36',0,NULL,'2026-05-19 18:03:36','2026-05-19 18:17:26'),(8,8,'email','completed','{\"log_id\": 8}','2026-05-19 18:03:37',0,NULL,'2026-05-19 18:03:37','2026-05-19 18:17:29'),(9,9,'email','completed','{\"log_id\": 9}','2026-05-19 18:03:37',0,NULL,'2026-05-19 18:03:37','2026-05-19 18:17:32');
/*!40000 ALTER TABLE `communication_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `communication_templates`
--

DROP TABLE IF EXISTS `communication_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `communication_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `channel` enum('email','whatsapp') NOT NULL,
  `event_key` varchar(80) NOT NULL,
  `subject` varchar(190) DEFAULT NULL,
  `body_template` longtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comm_template` (`channel`,`event_key`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `communication_templates`
--

LOCK TABLES `communication_templates` WRITE;
/*!40000 ALTER TABLE `communication_templates` DISABLE KEYS */;
INSERT INTO `communication_templates` VALUES (1,'email','build_your_cake_quote_email','Your Custom Cake Quote is Ready - {{quote_subject}}','<div style=\"background:#f5eef2;padding:40px 20px;font-family:Arial,sans-serif;\"><div style=\"max-width:640px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><!-- HEADER --><div style=\"background:#80001F;padding:28px 32px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage\" style=\"height:80px;display:block;\"></div><p style=\"margin:8px 0 0;font-size:14px;opacity:0.9;letter-spacing:0.5px;\">Build Your Own Cake ΓÇö Custom Quote</p></div><!-- BODY --><div style=\"padding:36px 32px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:26px;\">Hi {{first_name}},</h2><p style=\"color:#5f4c55;font-size:15px;line-height:1.8;margin-bottom:28px;\">Great news! Your custom cake quote is ready. Review the details below and click <strong>Accept &amp; Pay</strong> to confirm your order.</p><!-- QUOTE SUMMARY CARD --><div style=\"background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px 28px;margin-bottom:24px;\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"font-size:14px;color:#3b252d;line-height:2;\"><tbody><tr><td width=\"45%\" style=\"color:#80001F;font-weight:bold;\">Quote</td><td>{{quote_subject}}</td></tr><tr><td style=\"color:#80001F;font-weight:bold;\">Event</td><td>{{event_information}}</td></tr><tr><td style=\"color:#80001F;font-weight:bold;\">Event Date</td><td>{{event_date}}</td></tr><tr><td style=\"color:#80001F;font-weight:bold;\">Guests</td><td>{{number_of_servings_guests}}</td></tr><tr><td style=\"color:#80001F;font-weight:bold;\">Budget Range</td><td>{{budget_range}}</td></tr><tr><td style=\"color:#80001F;font-weight:bold;\">Dietary Pref</td><td>{{diet_preference}}</td></tr></tbody></table></div><!-- QUOTE AMOUNT HIGHLIGHT --><div style=\"background:#80001F;border-radius:14px;padding:22px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;\"><div><p style=\"margin:0;color:#f9d7e2;font-size:13px;letter-spacing:0.4px;\">QUOTED AMOUNT</p><p style=\"margin:6px 0 0;color:#fff;font-size:28px;font-weight:bold;\">Γé╣{{quote_amount}}</p><p style=\"margin:4px 0 0;color:#f9d7e2;font-size:12px;\">50% advance (Γé╣{{advance_amount}}) to confirm order</p></div></div><!-- QUOTE MESSAGE --><div style=\"background:#f8f3f5;border-left:4px solid #80001F;border-radius:0 10px 10px 0;padding:16px 20px;margin-bottom:28px;font-size:14px;color:#3b252d;line-height:1.75;\"><strong style=\"display:block;margin-bottom:6px;color:#80001F;\">Message from Cakeouflage</strong>{{quote_message}}</div><!-- CTA BUTTON --><div style=\"text-align:center;margin:32px 0 16px;\"><a href=\"{{quote_accept_link}}\" style=\"display:inline-block;background:#80001F;color:#fff;font-size:16px;font-weight:bold;padding:16px 48px;border-radius:50px;text-decoration:none;letter-spacing:0.3px;\">Γ£ô&nbsp; Accept &amp; Pay</a></div><p style=\"text-align:center;color:#9c7b86;font-size:12px;margin-top:12px;\">Or copy this link into your browser:<br><span style=\"color:#80001F;word-break:break-all;\">{{quote_accept_link}}</span></p><div style=\"margin-top:28px;background:#fff5da;border-radius:10px;padding:14px 18px;color:#7a5300;font-size:13px;line-height:1.6;\"><strong>ΓÅ░ This quote link expires on <strong>{{quote_expiry_display}}</strong>. If you have any questions before accepting, reply to this email or contact us on WhatsApp.</div></div><!-- FOOTER --><div style=\"background:#140b0f;padding:28px 32px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:13px;margin:4px 0;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:13px;margin:4px 0;\">≡ƒîÉ www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:37','2026-05-19 18:15:48'),(2,'email','online_order_received_customer','Order Received - {{order_number}}','<div style=\"background:#f5eef2;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#80001F;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Online Order Received</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Thank you for your order with Cakeouflage. We have received it and our team is preparing your celebration.</p><div style=\"margin-top:28px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p><p><strong>Total:</strong> &#8377;{{grand_total}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">We will keep you posted as your order moves forward.</div></div><div style=\"background:#140b0f;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:42','2026-05-19 08:45:42'),(3,'email','online_order_received_admin','New Online Order - {{order_number}}','<div style=\"background:#f5eef2;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#80001F;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">New Online Order</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">A new online order has been received and is ready for team review.</p><div style=\"margin-top:28px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Email:</strong> {{customer_email}}</p><p><strong>Phone:</strong> {{customer_phone}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Please review fulfilment details and continue the workflow.</div></div><div style=\"background:#140b0f;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:42','2026-05-19 08:45:42'),(4,'email','manual_order_received_customer','Manual Order Received - {{order_number}}','<div style=\"background:#f5eef2;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#80001F;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Manual Order Received</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Your order has been recorded by the Cakeouflage team and is now in processing.</p><div style=\"margin-top:28px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p><p><strong>Total:</strong> &#8377;{{grand_total}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">If we need any clarification, we will contact you shortly.</div></div><div style=\"background:#140b0f;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:42','2026-05-19 08:45:42'),(5,'email','manual_order_received_admin','New Manual Order - {{order_number}}','<div style=\"background:#f5eef2;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#80001F;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Manual Order Alert</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">A manual order has been punched in from admin and needs fulfilment review.</p><div style=\"margin-top:28px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Email:</strong> {{customer_email}}</p><p><strong>Phone:</strong> {{customer_phone}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Please verify the order details and continue the workflow.</div></div><div style=\"background:#140b0f;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(6,'email','payment_confirmed_customer','Payment Confirmed - {{order_number}}','<div style=\"background:#eef8f1;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#166534;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Payment Confirmed</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">We have received your payment and your order is now confirmed.</p><div style=\"margin-top:28px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p><p><strong>Paid:</strong> &#8377;{{grand_total}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Our team has started preparing your order.</div></div><div style=\"background:#052e16;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(7,'email','payment_confirmed_admin','Payment Confirmed - {{order_number}}','<div style=\"background:#eef8f1;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#166534;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Payment Confirmed</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Payment has been confirmed and the order can move into production.</p><div style=\"margin-top:28px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Customer Email:</strong> {{customer_email}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Please update the operations timeline as needed.</div></div><div style=\"background:#052e16;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(8,'email','ready_order_customer','Order Ready - {{order_number}}','<div style=\"background:#eff6ff;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#1d4ed8;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Order Ready</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Great news, your Cakeouflage order is now ready.</p><div style=\"margin-top:28px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Your order is ready for pickup or delivery.</div></div><div style=\"background:#172554;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(9,'email','ready_order_admin','Order Ready - {{order_number}}','<div style=\"background:#eff6ff;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#1d4ed8;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Order Ready</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">The order is ready and the team should coordinate dispatch or pickup.</p><div style=\"margin-top:28px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Customer Email:</strong> {{customer_email}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Please update the fulfilment status in the admin flow.</div></div><div style=\"background:#172554;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(10,'email','order_delivered_customer','Order Delivered - {{order_number}}','<div style=\"background:#ecfdf5;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#0f766e;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Order Delivered</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Your Cakeouflage order has been delivered. Thank you for celebrating with us.</p><div style=\"margin-top:28px;background:#f0fdfa;border:1px solid #99f6e4;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">We hope you loved every bite.</div></div><div style=\"background:#134e4a;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(11,'email','order_delivered_admin','Order Delivered - {{order_number}}','<div style=\"background:#ecfdf5;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#0f766e;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Delivery Alert</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">The order is marked delivered and follow-up tracking can begin.</p><div style=\"margin-top:28px;background:#f0fdfa;border:1px solid #99f6e4;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Please make any follow-up updates required for operations.</div></div><div style=\"background:#134e4a;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(12,'email','reject_order_customer','Order Rejected - {{order_number}}','<div style=\"background:#fff1f2;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#991b1b;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Order Rejected</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">We could not verify your payment successfully, so the order could not be processed.</p><div style=\"margin-top:28px;background:#fef2f2;border:1px solid #fecaca;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Items:</strong> {{item_names}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">If you would like to place the order again, please try again when ready.</div><div style=\"margin-top:30px;\"><a href=\"https://cakeouflage.com\" style=\"background:#991b1b;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;\">Place Order Again</a></div></div><div style=\"background:#450a0a;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(13,'email','reject_order_admin','Order Rejected - {{order_number}}','<div style=\"background:#fff1f2;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#991b1b;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Order Rejected</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">The order was rejected after payment verification and needs admin visibility.</p><div style=\"margin-top:28px;background:#fef2f2;border:1px solid #fecaca;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Email:</strong> {{customer_email}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Please review the rejection details in the admin workflow.</div></div><div style=\"background:#450a0a;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(14,'email','follow_up_review_email','Share Your Cakeouflage Experience','<div style=\"background:#f5eef2;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#80001F;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Quarterly Follow-up Reminder</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">You last ordered in {{last_order_month}}. We will be happy to serve you again for your next celebration.</p><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">If you loved your order, please leave a quick Google review. It helps us grow and serve you better.</div><div style=\"margin-top:30px;\"><a href=\"{{google_review_link}}\" style=\"background:#80001F;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;\">Write Google Review</a></div></div><div style=\"background:#140b0f;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(15,'email','annual_reorder_email','Your Celebration Date Is Coming Soon','<div style=\"background:#eef8f1;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#166534;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Annual Celebration Reminder</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Your yearly celebration date is just one week away.</p><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">To avoid any last moment rush, order your celebration cake now and get it ready on your desired date.</div><div style=\"margin-top:30px;\"><a href=\"{{profile_link}}\" style=\"background:#166534;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;\">Book Your Cake Now</a></div></div><div style=\"background:#052e16;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(16,'email','birthday_greeting_email','Happy Birthday from Cakeouflage','<div style=\"background:#fff2f6;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#6d002f;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Birthday Wishes</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Wishing you a beautiful birthday filled with joy, warmth, and sweet memories.</p><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Reserve your signature Cakeouflage creation for a celebration made to remember.</div><div style=\"margin-top:30px;\"><a href=\"https://cakeouflage.com/shop\" style=\"background:#80001F;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;\">Design My Birthday Cake</a></div></div><div style=\"background:#2f0a18;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(17,'email','birthday_preorder_email','Your Birthday Celebration Is Near','<div style=\"background:#fff4f8;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#7a123a;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Birthday Preorder Reminder</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Your birthday date is approaching. Secure your preferred cake style and delivery slot in advance.</p><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Early booking helps us craft every detail exactly the way you want.</div><div style=\"margin-top:30px;\"><a href=\"https://cakeouflage.com/shop\" style=\"background:#80001F;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;\">Preorder Birthday Cake</a></div></div><div style=\"background:#3a1021;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(18,'email','anniversary_greeting_email','Happy Anniversary from Cakeouflage','<div style=\"background:#fff3f2;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#7a0017;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Anniversary Wishes</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Wishing you an elegant anniversary celebration filled with love and beautiful moments.</p><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">We would be delighted to craft a centerpiece cake for your special day.</div><div style=\"margin-top:30px;\"><a href=\"https://cakeouflage.com/shop\" style=\"background:#80001F;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;\">Explore Anniversary Cakes</a></div></div><div style=\"background:#3a0a0f;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(19,'email','anniversary_preorder_email','Plan Your Anniversary Cake In Advance','<div style=\"background:#fff4f4;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#6b1227;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Anniversary Preorder Reminder</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Your anniversary celebration is near. Book now to secure your preferred design and schedule.</p><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Advance planning ensures a seamless celebration experience.</div><div style=\"margin-top:30px;\"><a href=\"https://cakeouflage.com/shop\" style=\"background:#80001F;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;\">Preorder Anniversary Cake</a></div></div><div style=\"background:#35101a;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(20,'email','celebration_combined_email','Special Celebration Wishes from Cakeouflage','<div style=\"background:#fff1f6;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#5f0017;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Celebration Wishes</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Sending warm wishes for your special celebration from all of us at Cakeouflage.</p><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Let us craft a refined cake experience that matches your occasion perfectly.</div><div style=\"margin-top:30px;\"><a href=\"https://cakeouflage.com/shop\" style=\"background:#80001F;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;\">Plan My Celebration Cake</a></div></div><div style=\"background:#280811;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(21,'email','build_your_cake_inquiry_customer_email','We Received Your Custom Cake Inquiry #{{inquiry_id}}','<div style=\"background:#f5eef2;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#80001F;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Build Your Cake Inquiry</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Thank you for sharing your custom cake brief with Cakeouflage. Our design team will review your requirements and get back with a quote soon.</p><div style=\"margin-top:28px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Inquiry ID:</strong> {{inquiry_id}}</p><p><strong>Event:</strong> {{event_information}}</p><p><strong>Event Date:</strong> {{event_date}}</p><p><strong>Servings:</strong> {{number_of_servings_guests}}</p><p><strong>Budget Range:</strong> {{budget_range}}</p><p><strong>Diet Preference:</strong> {{diet_preference}}</p><p><strong>Design Notes:</strong> {{design_brief_notes}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Need to add more details? Reply to this email and our team will assist you.</div></div><div style=\"background:#140b0f;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(22,'email','build_your_cake_inquiry_admin_email','New BYOC Inquiry #{{inquiry_id}} - {{customer_name}}','<div style=\"background:#f5eef2;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#80001F;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">New Build Your Cake Lead</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">A new Build Your Cake inquiry has been submitted and is ready for quote action.</p><div style=\"margin-top:28px;background:#fff7f9;border:1px solid #f0d7df;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Inquiry ID:</strong> {{inquiry_id}}</p><p><strong>Name:</strong> {{customer_name}}</p><p><strong>Email:</strong> {{customer_email}}</p><p><strong>Mobile:</strong> {{phone_country_code}} {{customer_phone}}</p><p><strong>Event:</strong> {{event_information}}</p><p><strong>Event Date:</strong> {{event_date}}</p><p><strong>Servings:</strong> {{number_of_servings_guests}}</p><p><strong>Budget:</strong> {{budget_range}}</p><p><strong>Diet:</strong> {{diet_preference}}</p><p><strong>Quote Description:</strong> {{quote_description}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Review the lead quickly and send quote from tele-calling panel for best conversion.</div></div><div style=\"background:#140b0f;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(24,'email','password_reset','Password Reset Request','<div style=\"background:#f5eef2;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#80001F;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Password Reset</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">We received a request to reset your password. If this was you, use the secure link below.</p><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">If you did not request this, you can safely ignore this email.</div><div style=\"margin-top:30px;\"><a href=\"{{reset_link}}\" style=\"background:#80001F;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;\">Reset Password</a></div></div><div style=\"background:#140b0f;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(25,'email','invoice_paid','Invoice - {{order_number}}','<div style=\"background:#ecfdf5;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#0f766e;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">Invoice Paid</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Thank you for your payment. Your invoice is attached below.</p><div style=\"margin-top:28px;background:#f0fdfa;border:1px solid #99f6e4;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><div>{{invoice_html}}</div></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">If you need help with billing, please contact Team Cakeouflage.</div></div><div style=\"background:#134e4a;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 08:45:43','2026-05-19 08:45:43'),(69,'email','byoc_order_confirmed_customer','Your Custom Cake Order Is Confirmed - {{order_number}}','<div style=\"background:#f0fdf4;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#166534;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">BYOC Order Confirmed (Customer)</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">Your custom cake order has been confirmed by Cakeouflage.</p><div style=\"margin-top:28px;background:#dcfce7;border:1px solid #bbf7d0;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order Number:</strong> {{order_number}}</p><p><strong>Order Total:</strong> {{currency}} {{grand_total}}</p><p><strong>Advance Paid:</strong> {{currency}} {{advance_amount}}</p><p><strong>Remaining Balance:</strong> {{currency}} {{remaining_balance}}</p><p><strong>Delivery Address:</strong> {{delivery_address}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Our team will contact you to confirm delivery details.</div></div><div style=\"background:#14532d;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 17:30:18','2026-05-19 17:40:47'),(70,'email','byoc_order_confirmed_admin','New BYOC Order Received - {{order_number}} from {{customer_name}}','<div style=\"background:#f5f3ff;padding:40px;font-family:Arial,sans-serif;\"><div style=\"max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);\"><div style=\"background:#5b21b6;padding:28px;color:#fff;\"><div style=\"margin-bottom:12px;\"><img src=\"https://i.ibb.co/hRytXC3F/whitelogo.png\" alt=\"Cakeouflage Logo\" style=\"height:100px;display:block;\"></div><p style=\"margin-top:10px;font-size:14px;opacity:0.9;\">BYOC Order Confirmed (Admin)</p></div><div style=\"padding:40px;\"><h2 style=\"margin-top:0;color:#1d1115;font-size:30px;\">Hi {{customer_name}}</h2><p style=\"color:#5f4c55;font-size:16px;line-height:1.8;\">A BYOC quote has been accepted by a customer and converted to an order.</p><div style=\"margin-top:28px;background:#ede9fe;border:1px solid #ddd6fe;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;\"><p><strong>Order Number:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Email:</strong> {{customer_email}}</p><p><strong>Phone:</strong> {{customer_phone}}</p><p><strong>Order Total:</strong> {{currency}} {{grand_total}}</p><p><strong>Advance Paid:</strong> {{currency}} {{advance_amount}}</p><p><strong>Payment Status:</strong> {{payment_status}}</p><p><strong>Delivery Address:</strong> {{delivery_address}}</p></div><div style=\"margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;\">Please review and confirm this order in the admin panel.</div></div><div style=\"background:#4c1d95;padding:30px;color:#fff;\"><h3 style=\"margin-top:0;font-family:Georgia,serif;\">Team Cakeouflage</h3><p style=\"color:#d7c6cc;font-size:14px;\">Premium Designer Cakes crafted with elegance and creativity.</p><p style=\"color:#d7c6cc;font-size:14px;\">&#127760; www.cakeouflage.com</p></div></div></div>',1,'2026-05-19 17:30:18','2026-05-19 17:40:47');
/*!40000 ALTER TABLE `communication_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupon_redemptions`
--

DROP TABLE IF EXISTS `coupon_redemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupon_redemptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `code_snapshot` varchar(50) NOT NULL,
  `discount_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_order` (`coupon_id`,`order_id`),
  KEY `idx_coupon_redemption_user` (`coupon_id`,`user_id`),
  KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `coupon_redemptions_ibfk_1` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `coupon_redemptions_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `coupon_redemptions_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupon_redemptions`
--

LOCK TABLES `coupon_redemptions` WRITE;
/*!40000 ALTER TABLE `coupon_redemptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `coupon_redemptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupon_target_users`
--

DROP TABLE IF EXISTS `coupon_target_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupon_target_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_target_user` (`coupon_id`,`user_id`),
  KEY `idx_coupon_target_user` (`user_id`),
  CONSTRAINT `coupon_target_users_ibfk_1` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `coupon_target_users_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupon_target_users`
--

LOCK TABLES `coupon_target_users` WRITE;
/*!40000 ALTER TABLE `coupon_target_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `coupon_target_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('flat','percentage') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `min_order_amount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int DEFAULT NULL,
  `per_user_usage_limit` int DEFAULT NULL,
  `usage_count` int NOT NULL DEFAULT '0',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime NOT NULL,
  `target_mode` enum('all_users','specific_users') NOT NULL DEFAULT 'all_users',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
INSERT INTO `coupons` VALUES (1,'CKF8JVFQ9D','percentage',10.00,100.00,500.00,10,1,0,'2026-05-19 00:00:00','2026-05-21 23:59:59','all_users',1,0,NULL,'2026-05-19 08:27:05','2026-05-19 08:27:05');
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_batches`
--

DROP TABLE IF EXISTS `course_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint unsigned NOT NULL,
  `batch_name` varchar(120) NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date DEFAULT NULL,
  `seats_total` int NOT NULL,
  `seats_available` int NOT NULL,
  `fee_amount` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `course_batches_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_batches`
--

LOCK TABLES `course_batches` WRITE;
/*!40000 ALTER TABLE `course_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `course_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `short_description` varchar(260) NOT NULL,
  `description` longtext NOT NULL,
  `modules` longtext,
  `duration_text` varchar(120) DEFAULT NULL,
  `mode` enum('online','offline','hybrid') NOT NULL DEFAULT 'offline',
  `fee_amount` decimal(10,2) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `cta_label` varchar(80) DEFAULT NULL,
  `cta_url` varchar(190) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_push_logs`
--

DROP TABLE IF EXISTS `crm_push_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_push_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `crm_setting_id` bigint unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `mobile` varchar(25) NOT NULL,
  `status` enum('success','fail') NOT NULL,
  `response` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_crm_push_logs_created_at` (`created_at`),
  KEY `idx_crm_push_logs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_push_logs`
--

LOCK TABLES `crm_push_logs` WRITE;
/*!40000 ALTER TABLE `crm_push_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_push_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_settings`
--

DROP TABLE IF EXISTS `crm_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(120) NOT NULL,
  `endpoint` varchar(500) NOT NULL DEFAULT '',
  `api_token` varchar(500) NOT NULL DEFAULT '',
  `is_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `extra_json` longtext,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_settings`
--

LOCK TABLES `crm_settings` WRITE;
/*!40000 ALTER TABLE `crm_settings` DISABLE KEYS */;
INSERT INTO `crm_settings` VALUES (1,'online_order_received','','',0,NULL,'2026-05-19 08:02:33','2026-05-19 08:02:33'),(2,'manual_order_received','','',0,NULL,'2026-05-19 08:02:33','2026-05-19 08:02:33'),(3,'payment_confirmed','','',0,NULL,'2026-05-19 08:02:33','2026-05-19 08:02:33'),(4,'reject_order','','',0,NULL,'2026-05-19 08:02:33','2026-05-19 08:02:33'),(5,'ready_order','','',0,NULL,'2026-05-19 08:02:33','2026-05-19 08:02:33'),(6,'order_delivered','','',0,NULL,'2026-05-19 08:02:33','2026-05-19 08:02:33'),(7,'follow_up_review','','',0,NULL,'2026-05-19 08:02:33','2026-05-19 08:02:33'),(8,'annual_reorder','','',0,NULL,'2026-05-19 08:02:33','2026-05-19 08:02:33');
/*!40000 ALTER TABLE `crm_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_profiles`
--

DROP TABLE IF EXISTS `customer_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `anniversary_date` date DEFAULT NULL,
  `celebration_date` date DEFAULT NULL,
  `internal_note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_profiles_user` (`user_id`),
  CONSTRAINT `customer_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_profiles`
--

LOCK TABLES `customer_profiles` WRITE;
/*!40000 ALTER TABLE `customer_profiles` DISABLE KEYS */;
INSERT INTO `customer_profiles` VALUES (1,1,NULL,NULL,NULL,NULL,'2026-05-19 14:21:30','2026-05-19 14:21:30');
/*!40000 ALTER TABLE `customer_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_tag_map`
--

DROP TABLE IF EXISTS `customer_tag_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_tag_map` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `tag_id` bigint unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_tag_map` (`user_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `customer_tag_map_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_tag_map_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `customer_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_tag_map`
--

LOCK TABLES `customer_tag_map` WRITE;
/*!40000 ALTER TABLE `customer_tag_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `customer_tag_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_tags`
--

DROP TABLE IF EXISTS `customer_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tag_name` varchar(80) NOT NULL,
  `tag_slug` varchar(90) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag_slug` (`tag_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_tags`
--

LOCK TABLES `customer_tags` WRITE;
/*!40000 ALTER TABLE `customer_tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `customer_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_distance_slabs`
--

DROP TABLE IF EXISTS `delivery_distance_slabs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_distance_slabs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slab_label` varchar(60) NOT NULL,
  `min_km` decimal(5,2) NOT NULL,
  `max_km` decimal(5,2) NOT NULL,
  `delivery_fee` decimal(10,2) NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_distance_slabs`
--

LOCK TABLES `delivery_distance_slabs` WRITE;
/*!40000 ALTER TABLE `delivery_distance_slabs` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_distance_slabs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_pincodes`
--

DROP TABLE IF EXISTS `delivery_pincodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_pincodes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `postal_code` varchar(15) NOT NULL,
  `area_name` varchar(120) NOT NULL,
  `approx_distance_km` decimal(5,2) NOT NULL,
  `is_serviceable` tinyint(1) NOT NULL DEFAULT '1',
  `requires_manual_approval` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `postal_code` (`postal_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_pincodes`
--

LOCK TABLES `delivery_pincodes` WRITE;
/*!40000 ALTER TABLE `delivery_pincodes` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_pincodes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_time_slots`
--

DROP TABLE IF EXISTS `delivery_time_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_time_slots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slot_label` varchar(80) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `fulfilment_mode` enum('delivery','pickup','both') NOT NULL DEFAULT 'both',
  `is_same_day_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_time_slots`
--

LOCK TABLES `delivery_time_slots` WRITE;
/*!40000 ALTER TABLE `delivery_time_slots` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_time_slots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_registrations`
--

DROP TABLE IF EXISTS `event_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_registrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `participant_name` varchar(120) NOT NULL,
  `participant_email` varchar(190) NOT NULL,
  `participant_phone` varchar(25) NOT NULL,
  `attendees_count` int NOT NULL DEFAULT '1',
  `registration_status` enum('new','confirmed','cancelled') NOT NULL DEFAULT 'new',
  `note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_registrations_event` (`event_id`),
  KEY `idx_event_registrations_status` (`registration_status`),
  CONSTRAINT `event_registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_registrations`
--

LOCK TABLES `event_registrations` WRITE;
/*!40000 ALTER TABLE `event_registrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_registrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(190) NOT NULL,
  `slug` varchar(210) NOT NULL,
  `short_description` varchar(280) NOT NULL,
  `full_description` longtext NOT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `instructor_name` varchar(140) NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `event_type` enum('webinar','event') NOT NULL DEFAULT 'event',
  `event_category` varchar(120) DEFAULT NULL,
  `event_status` enum('draft','scheduled','live','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `location_text` varchar(190) DEFAULT NULL,
  `online_link` varchar(255) DEFAULT NULL,
  `capacity` int NOT NULL DEFAULT '30',
  `seats_available` int NOT NULL DEFAULT '30',
  `registration_cta_label` varchar(80) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_events_type` (`event_type`),
  KEY `idx_events_status` (`event_status`),
  KEY `idx_events_start` (`starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inquiries`
--

DROP TABLE IF EXISTS `inquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inquiries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inquiry_type` enum('custom_cake','contact','course','event','b2b_registration','quote_request') NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `message` text,
  `reference_file` varchar(255) DEFAULT NULL,
  `status` enum('new','in_review','closed') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inquiries`
--

LOCK TABLES `inquiries` WRITE;
/*!40000 ALTER TABLE `inquiries` DISABLE KEYS */;
INSERT INTO `inquiries` VALUES (1,'custom_cake','Parin Daulat','parin11@gmail.com','9330033000','{\"phone_country_code\":\"+91\",\"event_information\":\"Birthday\",\"event_date\":\"2026-06-15\",\"number_of_servings_guests\":\"30\",\"budget_range\":\"2000-3500\",\"diet_preference\":\"Veg\",\"design_breif_notes\":\"Chocolate truffle 3-tier cake, floral theme, pastel pink and white. Name: Happy Birthday Parin\",\"privacy_consent\":1}',NULL,'closed','2026-05-19 16:48:46','2026-05-19 18:03:36');
/*!40000 ALTER TABLE `inquiries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `item_label` varchar(190) NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_items_invoice` (`invoice_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(40) NOT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `b2b_order_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `b2b_account_id` bigint unsigned DEFAULT NULL,
  `customer_type` enum('retail','b2b') NOT NULL DEFAULT 'retail',
  `invoice_status` enum('draft','pending_payment','part_paid','paid','overdue','payment_under_verification','unpaid_rejected','cancelled','refunded') NOT NULL DEFAULT 'pending_payment',
  `payment_method` enum('upi','bank_transfer','cash','pos_card','payment_link') NOT NULL DEFAULT 'upi',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `balance_due` decimal(12,2) NOT NULL DEFAULT '0.00',
  `due_on` date DEFAULT NULL,
  `issued_on` date DEFAULT NULL,
  `internal_note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `order_id` (`order_id`),
  KEY `b2b_order_id` (`b2b_order_id`),
  KEY `user_id` (`user_id`),
  KEY `b2b_account_id` (`b2b_account_id`),
  KEY `idx_invoices_number` (`invoice_number`),
  KEY `idx_invoices_status` (`invoice_status`),
  KEY `idx_invoices_due_on` (`due_on`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`b2b_order_id`) REFERENCES `b2b_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_4` FOREIGN KEY (`b2b_account_id`) REFERENCES `b2b_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned DEFAULT NULL,
  `product_name_snapshot` varchar(180) NOT NULL,
  `variant_snapshot` varchar(80) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `quantity` int NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  `customisation_note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `variant_id` (`variant_id`),
  KEY `idx_order_items_order` (`order_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,1,NULL,'Custom Cake Quote',NULL,2800.00,1,2800.00,'3-tier chocolate cake','2026-05-19 17:02:32');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(40) NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(120) NOT NULL,
  `customer_email` varchar(190) NOT NULL,
  `customer_phone` varchar(25) NOT NULL,
  `fulfilment_mode` enum('delivery','pickup','custom_delivery') NOT NULL,
  `order_status` enum('pending','confirmed','in_preparation','out_for_delivery','ready_for_pickup','completed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` enum('pending','paid','failed','refunded','credit') NOT NULL DEFAULT 'pending',
  `payment_confirmed_at` datetime DEFAULT NULL,
  `payment_confirmed_by_admin_id` bigint unsigned DEFAULT NULL,
  `credit_collected_at` datetime DEFAULT NULL,
  `credit_collected_by_admin_id` bigint unsigned DEFAULT NULL,
  `payment_method` enum('upi_manual','cod','gateway','credit') NOT NULL DEFAULT 'upi_manual',
  `order_source` enum('retail','byoc_quote') NOT NULL DEFAULT 'retail',
  `byoc_quote_id` bigint unsigned DEFAULT NULL,
  `scheduled_slot` datetime DEFAULT NULL,
  `scheduled_slot_label` varchar(120) DEFAULT NULL,
  `delivery_postal_code` varchar(15) DEFAULT NULL,
  `delivery_street` varchar(255) DEFAULT NULL,
  `delivery_maps_link` varchar(500) DEFAULT NULL,
  `billing_address_line1` varchar(190) DEFAULT NULL,
  `billing_address_line2` varchar(190) DEFAULT NULL,
  `billing_city` varchar(100) DEFAULT NULL,
  `billing_state` varchar(100) DEFAULT NULL,
  `billing_postal_code` varchar(15) DEFAULT NULL,
  `delivery_distance_km` decimal(5,2) DEFAULT NULL,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(10,2) NOT NULL,
  `discount_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(10,2) NOT NULL,
  `advance_amount` decimal(10,2) DEFAULT NULL,
  `admin_note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  UNIQUE KEY `uq_orders_byoc_quote` (`byoc_quote_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_orders_number` (`order_number`),
  KEY `idx_orders_email` (`customer_email`),
  KEY `idx_orders_status` (`order_status`),
  KEY `idx_orders_source` (`order_source`),
  CONSTRAINT `fk_orders_byoc_quote` FOREIGN KEY (`byoc_quote_id`) REFERENCES `byoc_quotes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'BYOC-20260519-215201',NULL,'Parin Daulat','parin11@gmail.com','9330033000','custom_delivery','confirmed','paid','2026-05-19 18:03:36',1,NULL,NULL,'cod','byoc_quote',3,'2026-06-15 10:00:00','Event Date: 2026-06-15','700001','42 Rose Garden Apartments, Sector 5, Near City Mall',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.00,2800.00,0.00,0.00,2800.00,1400.00,'BYOC quote accepted via secure link. Quote #BYOC-20260519-802327','2026-05-19 17:02:32','2026-05-19 18:03:36');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_verifications`
--

DROP TABLE IF EXISTS `otp_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `otp_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `otp` varchar(12) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_verifications_email` (`email`),
  KEY `idx_otp_verifications_expires_at` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_verifications`
--

LOCK TABLES `otp_verifications` WRITE;
/*!40000 ALTER TABLE `otp_verifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `otp_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `content` longtext NOT NULL,
  `seo_title` varchar(190) DEFAULT NULL,
  `seo_description` varchar(260) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_pages_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `email` varchar(190) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_password_reset_email` (`email`),
  KEY `idx_password_reset_expires` (`expires_at`),
  CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_proofs`
--

DROP TABLE IF EXISTS `payment_proofs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_proofs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned NOT NULL,
  `file_url` varchar(255) NOT NULL,
  `uploaded_by` enum('customer','b2b_user','admin') NOT NULL DEFAULT 'customer',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`),
  CONSTRAINT `payment_proofs_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_proofs`
--

LOCK TABLES `payment_proofs` WRITE;
/*!40000 ALTER TABLE `payment_proofs` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_proofs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_status_history`
--

DROP TABLE IF EXISTS `payment_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_status_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `from_status` varchar(60) DEFAULT NULL,
  `to_status` varchar(60) NOT NULL,
  `changed_by_admin_id` bigint unsigned DEFAULT NULL,
  `note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `changed_by_admin_id` (`changed_by_admin_id`),
  KEY `idx_payment_history_invoice` (`invoice_id`),
  CONSTRAINT `payment_status_history_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_status_history_ibfk_2` FOREIGN KEY (`changed_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_status_history`
--

LOCK TABLES `payment_status_history` WRITE;
/*!40000 ALTER TABLE `payment_status_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `payment_method` enum('upi','bank_transfer','cash','pos_card','payment_link') NOT NULL,
  `payment_status` enum('submitted','verified','rejected') NOT NULL DEFAULT 'submitted',
  `amount` decimal(12,2) NOT NULL,
  `payment_reference` varchar(120) DEFAULT NULL,
  `note` text,
  `verified_by_admin_id` bigint unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `verified_by_admin_id` (`verified_by_admin_id`),
  KEY `idx_payments_invoice` (`invoice_id`),
  KEY `idx_payments_status` (`payment_status`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`verified_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `alt_text` varchar(190) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_images_product` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=403 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
INSERT INTO `product_images` VALUES (1,1,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:11'),(2,1,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:11'),(3,2,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:11'),(4,2,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:11'),(5,3,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:11'),(6,3,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:11'),(7,4,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:11'),(8,4,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:11'),(9,5,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:11'),(10,5,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:11'),(11,6,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:11'),(12,6,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:11'),(13,7,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:11'),(14,7,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:11'),(15,8,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:11'),(16,8,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:11'),(17,9,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:11'),(18,9,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:11'),(19,10,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:11'),(20,10,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:11'),(21,11,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:11'),(22,11,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:11'),(23,12,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:11'),(24,12,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:11'),(25,13,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:11'),(26,13,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:11'),(27,14,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:11'),(28,14,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:11'),(29,15,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:11'),(30,15,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:11'),(31,16,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:11'),(32,16,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:11'),(33,17,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:11'),(34,17,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:11'),(35,18,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:11'),(36,18,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:11'),(37,19,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:11'),(38,19,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:11'),(39,20,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:11'),(40,20,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:11'),(41,21,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:11'),(42,21,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:11'),(43,22,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:11'),(44,22,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:11'),(45,23,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:11'),(46,23,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:11'),(47,24,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:11'),(48,24,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:11'),(49,25,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:12'),(50,25,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:12'),(51,26,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:12'),(52,26,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:12'),(53,27,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:12'),(54,27,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:12'),(55,28,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:12'),(56,28,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:12'),(57,29,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:12'),(58,29,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:12'),(59,30,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:12'),(60,30,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:12'),(61,31,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:12'),(62,31,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:12'),(63,32,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:12'),(64,32,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:12'),(65,33,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:12'),(66,33,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:12'),(67,34,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:12'),(68,34,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:12'),(69,35,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:12'),(70,35,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:12'),(71,36,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:12'),(72,36,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:12'),(73,37,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:12'),(74,37,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:12'),(75,38,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:12'),(76,38,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:12'),(77,39,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:12'),(78,39,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:12'),(79,40,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:12'),(80,40,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:12'),(81,41,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:12'),(82,41,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:12'),(83,42,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:12'),(84,42,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:12'),(85,43,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:12'),(86,43,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:12'),(87,44,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:12'),(88,44,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:12'),(89,45,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:12'),(90,45,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:12'),(91,46,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:12'),(92,46,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:12'),(93,47,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:12'),(94,47,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:12'),(95,48,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:13'),(96,48,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:13'),(97,49,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:13'),(98,49,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:13'),(99,50,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:13'),(100,50,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:13'),(101,51,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:13'),(102,51,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:13'),(103,52,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:13'),(104,52,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:13'),(105,53,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:13'),(106,53,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:13'),(107,54,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:13'),(108,54,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:13'),(109,55,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:13'),(110,55,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:13'),(111,56,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:13'),(112,56,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:13'),(113,57,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:13'),(114,57,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:13'),(115,58,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:13'),(116,58,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:13'),(117,59,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:13'),(118,59,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:13'),(119,60,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:13'),(120,60,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:13'),(121,61,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:13'),(122,61,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:13'),(123,62,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:13'),(124,62,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:13'),(125,63,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:13'),(126,63,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:13'),(127,64,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:13'),(128,64,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:13'),(129,65,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:13'),(130,65,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:13'),(131,66,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:13'),(132,66,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:13'),(133,67,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:13'),(134,67,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:13'),(135,68,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:13'),(136,68,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:13'),(137,69,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:13'),(138,69,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:13'),(139,70,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:13'),(140,70,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:13'),(141,71,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:13'),(142,71,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:13'),(143,72,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:13'),(144,72,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:13'),(145,73,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:13'),(146,73,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:13'),(147,74,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:13'),(148,74,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:13'),(149,75,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:13'),(150,75,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:13'),(151,76,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:13'),(152,76,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:13'),(153,77,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:14'),(154,77,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:14'),(155,78,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:14'),(156,78,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:14'),(157,79,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:14'),(158,79,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:14'),(159,80,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:14'),(160,80,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:14'),(161,81,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:14'),(162,81,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:14'),(163,82,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:14'),(164,82,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:14'),(165,83,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:14'),(166,83,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:14'),(167,84,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:14'),(168,84,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:14'),(169,85,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:14'),(170,85,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:14'),(171,86,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:14'),(172,86,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:14'),(173,87,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:14'),(174,87,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:14'),(175,88,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:14'),(176,88,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:14'),(177,89,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:14'),(178,89,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:14'),(179,90,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:14'),(180,90,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:14'),(181,91,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:14'),(182,91,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:14'),(183,92,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:14'),(184,92,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:14'),(185,93,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:14'),(186,93,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:14'),(187,94,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:14'),(188,94,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:14'),(189,95,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:14'),(190,95,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:14'),(191,96,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:14'),(192,96,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:14'),(193,97,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:14'),(194,97,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:14'),(195,98,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:14'),(196,98,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:14'),(197,99,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:14'),(198,99,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:14'),(199,100,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:14'),(200,100,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:14'),(201,101,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:14'),(202,101,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:14'),(203,102,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:14'),(204,102,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:14'),(205,103,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:14'),(206,103,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:14'),(207,104,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:14'),(208,104,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:14'),(209,105,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:14'),(210,105,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:14'),(211,106,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:14'),(212,106,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:14'),(213,107,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:15'),(214,107,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:15'),(215,108,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:15'),(216,108,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:15'),(217,109,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:15'),(218,109,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:15'),(219,110,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:15'),(220,110,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:15'),(221,111,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:15'),(222,111,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:15'),(223,112,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:15'),(224,112,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:15'),(225,113,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:15'),(226,113,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:15'),(227,114,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:15'),(228,114,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:15'),(229,115,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:15'),(230,115,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:15'),(231,116,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:15'),(232,116,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:15'),(233,117,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:15'),(234,117,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:15'),(235,118,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:15'),(236,118,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:15'),(237,119,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:15'),(238,119,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:15'),(239,120,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:15'),(240,120,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:15'),(241,121,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:15'),(242,121,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:15'),(243,122,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:15'),(244,122,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:15'),(245,123,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:15'),(246,123,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:15'),(247,124,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:15'),(248,124,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:15'),(249,125,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:15'),(250,125,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:15'),(251,126,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:15'),(252,126,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:15'),(253,127,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:15'),(254,127,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:15'),(255,128,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:15'),(256,128,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:15'),(257,129,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:15'),(258,129,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:15'),(259,130,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:15'),(260,130,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:15'),(261,131,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:15'),(262,131,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:15'),(263,132,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:15'),(264,132,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:15'),(265,133,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:15'),(266,133,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:15'),(267,134,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:16'),(268,134,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:16'),(269,135,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:16'),(270,135,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:16'),(271,136,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:16'),(272,136,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:16'),(273,137,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:16'),(274,137,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:16'),(275,138,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:16'),(276,138,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:16'),(277,139,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:16'),(278,139,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:16'),(279,140,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:16'),(280,140,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:16'),(281,141,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:16'),(282,141,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:16'),(283,142,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:16'),(284,142,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:16'),(285,143,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:16'),(286,143,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:16'),(287,144,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:16'),(288,144,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:16'),(289,145,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:16'),(290,145,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:16'),(291,146,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:16'),(292,146,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:16'),(293,147,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:16'),(294,147,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:16'),(295,148,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:16'),(296,148,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:16'),(297,149,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:16'),(298,149,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:16'),(299,150,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:16'),(300,150,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:16'),(301,151,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:16'),(302,151,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:16'),(303,152,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:16'),(304,152,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:16'),(305,153,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:16'),(306,153,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:16'),(307,154,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:16'),(308,154,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:16'),(309,155,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:16'),(310,155,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:16'),(311,156,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:17'),(312,156,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:17'),(313,157,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:17'),(314,157,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:17'),(315,158,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:17'),(316,158,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:17'),(317,159,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:17'),(318,159,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:17'),(319,160,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:17'),(320,160,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:17'),(321,161,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:17'),(322,161,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:17'),(323,162,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:17'),(324,162,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:17'),(325,163,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:17'),(326,163,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:17'),(327,164,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:17'),(328,164,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:17'),(329,165,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:17'),(330,165,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:17'),(331,166,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:17'),(332,166,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:17'),(333,167,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:17'),(334,167,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:17'),(335,168,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:17'),(336,168,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:17'),(337,169,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:17'),(338,169,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:17'),(339,170,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:17'),(340,170,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:17'),(341,171,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:17'),(342,171,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:17'),(343,172,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:18'),(344,172,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:18'),(345,173,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:18'),(346,173,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:18'),(347,174,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:18'),(348,174,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:18'),(349,175,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:18'),(350,175,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:18'),(351,176,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:18'),(352,176,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:18'),(353,177,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:18'),(354,177,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:18'),(355,178,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:18'),(356,178,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:18'),(357,179,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:18'),(358,179,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:18'),(359,180,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:18'),(360,180,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:18'),(361,181,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:18'),(362,181,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:18'),(363,182,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:18'),(364,182,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:18'),(365,183,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:18'),(366,183,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:18'),(367,184,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:18'),(368,184,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:18'),(369,185,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:18'),(370,185,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:18'),(371,186,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:18'),(372,186,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:18'),(373,187,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:18'),(374,187,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:18'),(375,188,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:18'),(376,188,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:18'),(377,189,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:18'),(378,189,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:18'),(379,190,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:18'),(380,190,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:18'),(381,191,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:18'),(382,191,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:18'),(383,192,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:18'),(384,192,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:18'),(385,193,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:18'),(386,193,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:18'),(387,194,'/client/assets/images/placeholder-cake-3.jpg',NULL,0,'2026-05-19 04:31:18'),(388,194,'/client/assets/images/placeholder-cake-5.jpg',NULL,1,'2026-05-19 04:31:18'),(389,195,'/client/assets/images/placeholder-cake-4.jpg',NULL,0,'2026-05-19 04:31:18'),(390,195,'/client/assets/images/placeholder-cake-6.jpg',NULL,1,'2026-05-19 04:31:18'),(391,196,'/client/assets/images/placeholder-cake-5.jpg',NULL,0,'2026-05-19 04:31:18'),(392,196,'/client/assets/images/placeholder-cake-1.jpg',NULL,1,'2026-05-19 04:31:18'),(393,197,'/client/assets/images/placeholder-cake-6.jpg',NULL,0,'2026-05-19 04:31:18'),(394,197,'/client/assets/images/placeholder-cake-2.jpg',NULL,1,'2026-05-19 04:31:18'),(395,198,'/client/assets/images/placeholder-cake-1.jpg',NULL,0,'2026-05-19 04:31:19'),(396,198,'/client/assets/images/placeholder-cake-3.jpg',NULL,1,'2026-05-19 04:31:19'),(397,199,'/client/assets/images/placeholder-cake-2.jpg',NULL,0,'2026-05-19 04:31:19'),(398,199,'/client/assets/images/placeholder-cake-4.jpg',NULL,1,'2026-05-19 04:31:19'),(401,200,'/client/assets/images/product/1779193348_b512944a.png',NULL,0,'2026-05-19 12:22:29'),(402,200,'/client/assets/images/product/1779193349_2_2d7f207b.png',NULL,1,'2026-05-19 12:22:29');
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_import_runs`
--

DROP TABLE IF EXISTS `product_import_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_import_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mode` enum('commit','dry_run','restore') NOT NULL DEFAULT 'commit',
  `status` enum('pending','success','partial','failed') NOT NULL DEFAULT 'pending',
  `source_file` varchar(255) DEFAULT NULL,
  `backup_file` varchar(255) DEFAULT NULL,
  `created_count` int NOT NULL DEFAULT '0',
  `updated_count` int NOT NULL DEFAULT '0',
  `deleted_count` int NOT NULL DEFAULT '0',
  `failed_count` int NOT NULL DEFAULT '0',
  `total_rows` int NOT NULL DEFAULT '0',
  `restored_from_run_id` bigint unsigned DEFAULT NULL,
  `metadata_json` longtext,
  `created_by_admin_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by_admin_id` (`created_by_admin_id`),
  KEY `idx_product_import_runs_created` (`created_at`),
  KEY `idx_product_import_runs_mode` (`mode`),
  KEY `idx_product_import_runs_restored` (`restored_from_run_id`),
  CONSTRAINT `product_import_runs_ibfk_1` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_import_runs`
--

LOCK TABLES `product_import_runs` WRITE;
/*!40000 ALTER TABLE `product_import_runs` DISABLE KEYS */;
INSERT INTO `product_import_runs` VALUES (1,'commit','success','cakeitaway-hierarchy-master-200.csv','20260519-100110-cakeitaway-hierarchy-master-200.csv',200,0,0,0,200,NULL,'{\"strict_variants\":true,\"abort_on_error\":false,\"source_backup\":\"20260519-100110-cakeitaway-hierarchy-master-200.csv\"}',1,'2026-05-19 04:31:10');
/*!40000 ALTER TABLE `product_import_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_import_snapshots`
--

DROP TABLE IF EXISTS `product_import_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_import_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint unsigned NOT NULL,
  `snapshot_json` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_import_snapshot_run` (`run_id`),
  CONSTRAINT `product_import_snapshots_ibfk_1` FOREIGN KEY (`run_id`) REFERENCES `product_import_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_import_snapshots`
--

LOCK TABLES `product_import_snapshots` WRITE;
/*!40000 ALTER TABLE `product_import_snapshots` DISABLE KEYS */;
INSERT INTO `product_import_snapshots` VALUES (1,1,'{\"categories\":[],\"products\":[],\"variants\":[]}','2026-05-19 04:31:11');
/*!40000 ALTER TABLE `product_import_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_snapshots`
--

DROP TABLE IF EXISTS `product_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_data` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'JSON snapshot of complete product record',
  `operation` enum('insert','update','delete','restore') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sequence_number` int unsigned NOT NULL COMMENT 'Order within the import run',
  `has_variants` tinyint(1) DEFAULT '0',
  `variant_count` int unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_run_id` (`run_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_sku` (`sku`),
  KEY `idx_operation` (`operation`),
  KEY `idx_created_at` (`created_at` DESC),
  KEY `idx_run_product` (`run_id`,`product_id`),
  KEY `idx_product_latest` (`product_id`,`run_id` DESC),
  CONSTRAINT `product_snapshots_ibfk_1` FOREIGN KEY (`run_id`) REFERENCES `product_import_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Snapshot of product data at each import operation for version history and restore';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_snapshots`
--

LOCK TABLES `product_snapshots` WRITE;
/*!40000 ALTER TABLE `product_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_variant_snapshots`
--

DROP TABLE IF EXISTS `product_variant_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_variant_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_id` bigint unsigned NOT NULL,
  `run_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned NOT NULL,
  `variant_sku` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_data` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'JSON snapshot of variant record',
  `variant_option_values` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g., "Size: Large, Color: Red"',
  `variant_price` decimal(10,2) DEFAULT NULL,
  `variant_stock` int unsigned DEFAULT '0',
  `sequence_number` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_snapshot_id` (`snapshot_id`),
  KEY `idx_run_id` (`run_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_variant_id` (`variant_id`),
  KEY `idx_created_at` (`created_at` DESC),
  CONSTRAINT `product_variant_snapshots_ibfk_1` FOREIGN KEY (`snapshot_id`) REFERENCES `product_snapshots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_variant_snapshots_ibfk_2` FOREIGN KEY (`run_id`) REFERENCES `product_import_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Snapshot of product variants at import time for complete version history';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_variant_snapshots`
--

LOCK TABLES `product_variant_snapshots` WRITE;
/*!40000 ALTER TABLE `product_variant_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_variant_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_variants`
--

DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_variants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `variant_label` varchar(80) NOT NULL,
  `weight_or_size` varchar(50) NOT NULL,
  `flavor` varchar(80) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int NOT NULL DEFAULT '0',
  `sku_suffix` varchar(40) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_variants_product` (`product_id`),
  CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_variants`
--

LOCK TABLES `product_variants` WRITE;
/*!40000 ALTER TABLE `product_variants` DISABLE KEYS */;
INSERT INTO `product_variants` VALUES (1,1,'0.5 kg','0.5 kg',NULL,635.00,NULL,13,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(2,1,'1 lb','1 lb',NULL,885.00,NULL,13,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(3,1,'1.5 lb','1.5 lb',NULL,1185.00,NULL,13,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(4,1,'2 lb','2 lb',NULL,1485.00,NULL,13,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(5,1,'2.5 lb','2.5 lb',NULL,1785.00,NULL,13,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(6,1,'3 lb','3 lb',NULL,2085.00,NULL,13,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(7,2,'0.5 kg','0.5 kg',NULL,670.00,NULL,14,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(8,2,'1 lb','1 lb',NULL,920.00,NULL,14,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(9,2,'1.5 lb','1.5 lb',NULL,1220.00,NULL,14,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(10,2,'2 lb','2 lb',NULL,1520.00,NULL,14,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(11,2,'2.5 lb','2.5 lb',NULL,1820.00,NULL,14,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(12,2,'3 lb','3 lb',NULL,2120.00,NULL,14,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(13,3,'0.5 kg','0.5 kg',NULL,705.00,NULL,15,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(14,3,'1 lb','1 lb',NULL,955.00,NULL,15,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(15,3,'1.5 lb','1.5 lb',NULL,1255.00,NULL,15,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(16,3,'2 lb','2 lb',NULL,1555.00,NULL,15,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(17,3,'2.5 lb','2.5 lb',NULL,1855.00,NULL,15,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(18,3,'3 lb','3 lb',NULL,2155.00,NULL,15,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(19,4,'0.5 kg','0.5 kg',NULL,740.00,NULL,16,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(20,4,'1 lb','1 lb',NULL,990.00,NULL,16,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(21,4,'1.5 lb','1.5 lb',NULL,1290.00,NULL,16,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(22,4,'2 lb','2 lb',NULL,1590.00,NULL,16,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(23,4,'2.5 lb','2.5 lb',NULL,1890.00,NULL,16,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(24,4,'3 lb','3 lb',NULL,2190.00,NULL,16,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(25,5,'0.5 kg','0.5 kg',NULL,775.00,NULL,17,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(26,5,'1 lb','1 lb',NULL,1025.00,NULL,17,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(27,5,'1.5 lb','1.5 lb',NULL,1325.00,NULL,17,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(28,5,'2 lb','2 lb',NULL,1625.00,NULL,17,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(29,5,'2.5 lb','2.5 lb',NULL,1925.00,NULL,17,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(30,5,'3 lb','3 lb',NULL,2225.00,NULL,17,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(31,6,'0.5 kg','0.5 kg',NULL,810.00,NULL,18,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(32,6,'1 lb','1 lb',NULL,1060.00,NULL,18,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(33,6,'1.5 lb','1.5 lb',NULL,1360.00,NULL,18,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(34,6,'2 lb','2 lb',NULL,1660.00,NULL,18,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(35,6,'2.5 lb','2.5 lb',NULL,1960.00,NULL,18,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(36,6,'3 lb','3 lb',NULL,2260.00,NULL,18,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(37,7,'0.5 kg','0.5 kg',NULL,845.00,NULL,19,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(38,7,'1 lb','1 lb',NULL,1095.00,NULL,19,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(39,7,'1.5 lb','1.5 lb',NULL,1395.00,NULL,19,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(40,7,'2 lb','2 lb',NULL,1695.00,NULL,19,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(41,7,'2.5 lb','2.5 lb',NULL,1995.00,NULL,19,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(42,7,'3 lb','3 lb',NULL,2295.00,NULL,19,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(43,8,'0.5 kg','0.5 kg',NULL,880.00,NULL,20,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(44,8,'1 lb','1 lb',NULL,1130.00,NULL,20,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(45,8,'1.5 lb','1.5 lb',NULL,1430.00,NULL,20,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(46,8,'2 lb','2 lb',NULL,1730.00,NULL,20,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(47,8,'2.5 lb','2.5 lb',NULL,2030.00,NULL,20,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(48,8,'3 lb','3 lb',NULL,2330.00,NULL,20,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(49,9,'0.5 kg','0.5 kg',NULL,915.00,NULL,21,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(50,9,'1 lb','1 lb',NULL,1165.00,NULL,21,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(51,9,'1.5 lb','1.5 lb',NULL,1465.00,NULL,21,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(52,9,'2 lb','2 lb',NULL,1765.00,NULL,21,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(53,9,'2.5 lb','2.5 lb',NULL,2065.00,NULL,21,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(54,9,'3 lb','3 lb',NULL,2365.00,NULL,21,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(55,10,'0.5 kg','0.5 kg',NULL,950.00,NULL,22,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(56,10,'1 lb','1 lb',NULL,1200.00,NULL,22,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(57,10,'1.5 lb','1.5 lb',NULL,1500.00,NULL,22,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(58,10,'2 lb','2 lb',NULL,1800.00,NULL,22,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(59,10,'2.5 lb','2.5 lb',NULL,2100.00,NULL,22,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(60,10,'3 lb','3 lb',NULL,2400.00,NULL,22,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(61,11,'0.5 kg','0.5 kg',NULL,985.00,NULL,23,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(62,11,'1 lb','1 lb',NULL,1235.00,NULL,23,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(63,11,'1.5 lb','1.5 lb',NULL,1535.00,NULL,23,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(64,11,'2 lb','2 lb',NULL,1835.00,NULL,23,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(65,11,'2.5 lb','2.5 lb',NULL,2135.00,NULL,23,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(66,11,'3 lb','3 lb',NULL,2435.00,NULL,23,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(67,12,'0.5 kg','0.5 kg',NULL,1020.00,NULL,24,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(68,12,'1 lb','1 lb',NULL,1270.00,NULL,24,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(69,12,'1.5 lb','1.5 lb',NULL,1570.00,NULL,24,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(70,12,'2 lb','2 lb',NULL,1870.00,NULL,24,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(71,12,'2.5 lb','2.5 lb',NULL,2170.00,NULL,24,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(72,12,'3 lb','3 lb',NULL,2470.00,NULL,24,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(73,13,'0.5 kg','0.5 kg',NULL,1055.00,NULL,25,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(74,13,'1 lb','1 lb',NULL,1305.00,NULL,25,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(75,13,'1.5 lb','1.5 lb',NULL,1605.00,NULL,25,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(76,13,'2 lb','2 lb',NULL,1905.00,NULL,25,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(77,13,'2.5 lb','2.5 lb',NULL,2205.00,NULL,25,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(78,13,'3 lb','3 lb',NULL,2505.00,NULL,25,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(79,14,'0.5 kg','0.5 kg',NULL,1090.00,NULL,26,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(80,14,'1 lb','1 lb',NULL,1340.00,NULL,26,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(81,14,'1.5 lb','1.5 lb',NULL,1640.00,NULL,26,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(82,14,'2 lb','2 lb',NULL,1940.00,NULL,26,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(83,14,'2.5 lb','2.5 lb',NULL,2240.00,NULL,26,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(84,14,'3 lb','3 lb',NULL,2540.00,NULL,26,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(85,15,'0.5 kg','0.5 kg',NULL,600.00,NULL,27,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(86,15,'1 lb','1 lb',NULL,850.00,NULL,27,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(87,15,'1.5 lb','1.5 lb',NULL,1150.00,NULL,27,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(88,15,'2 lb','2 lb',NULL,1450.00,NULL,27,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(89,15,'2.5 lb','2.5 lb',NULL,1750.00,NULL,27,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(90,15,'3 lb','3 lb',NULL,2050.00,NULL,27,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(91,16,'0.5 kg','0.5 kg',NULL,635.00,NULL,28,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(92,16,'1 lb','1 lb',NULL,885.00,NULL,28,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(93,16,'1.5 lb','1.5 lb',NULL,1185.00,NULL,28,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(94,16,'2 lb','2 lb',NULL,1485.00,NULL,28,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(95,16,'2.5 lb','2.5 lb',NULL,1785.00,NULL,28,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(96,16,'3 lb','3 lb',NULL,2085.00,NULL,28,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(97,17,'0.5 kg','0.5 kg',NULL,670.00,NULL,29,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(98,17,'1 lb','1 lb',NULL,920.00,NULL,29,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(99,17,'1.5 lb','1.5 lb',NULL,1220.00,NULL,29,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(100,17,'2 lb','2 lb',NULL,1520.00,NULL,29,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(101,17,'2.5 lb','2.5 lb',NULL,1820.00,NULL,29,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(102,17,'3 lb','3 lb',NULL,2120.00,NULL,29,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(103,18,'0.5 kg','0.5 kg',NULL,705.00,NULL,30,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(104,18,'1 lb','1 lb',NULL,955.00,NULL,30,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(105,18,'1.5 lb','1.5 lb',NULL,1255.00,NULL,30,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(106,18,'2 lb','2 lb',NULL,1555.00,NULL,30,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(107,18,'2.5 lb','2.5 lb',NULL,1855.00,NULL,30,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(108,18,'3 lb','3 lb',NULL,2155.00,NULL,30,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(109,19,'0.5 kg','0.5 kg',NULL,740.00,NULL,31,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(110,19,'1 lb','1 lb',NULL,990.00,NULL,31,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(111,19,'1.5 lb','1.5 lb',NULL,1290.00,NULL,31,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(112,19,'2 lb','2 lb',NULL,1590.00,NULL,31,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(113,19,'2.5 lb','2.5 lb',NULL,1890.00,NULL,31,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(114,19,'3 lb','3 lb',NULL,2190.00,NULL,31,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(115,20,'0.5 kg','0.5 kg',NULL,775.00,NULL,32,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(116,20,'1 lb','1 lb',NULL,1025.00,NULL,32,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(117,20,'1.5 lb','1.5 lb',NULL,1325.00,NULL,32,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(118,20,'2 lb','2 lb',NULL,1625.00,NULL,32,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(119,20,'2.5 lb','2.5 lb',NULL,1925.00,NULL,32,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(120,20,'3 lb','3 lb',NULL,2225.00,NULL,32,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(121,21,'0.5 kg','0.5 kg',NULL,810.00,NULL,33,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(122,21,'1 lb','1 lb',NULL,1060.00,NULL,33,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(123,21,'1.5 lb','1.5 lb',NULL,1360.00,NULL,33,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(124,21,'2 lb','2 lb',NULL,1660.00,NULL,33,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(125,21,'2.5 lb','2.5 lb',NULL,1960.00,NULL,33,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(126,21,'3 lb','3 lb',NULL,2260.00,NULL,33,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(127,22,'0.5 kg','0.5 kg',NULL,845.00,NULL,34,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(128,22,'1 lb','1 lb',NULL,1095.00,NULL,34,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(129,22,'1.5 lb','1.5 lb',NULL,1395.00,NULL,34,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(130,22,'2 lb','2 lb',NULL,1695.00,NULL,34,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(131,22,'2.5 lb','2.5 lb',NULL,1995.00,NULL,34,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(132,22,'3 lb','3 lb',NULL,2295.00,NULL,34,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(133,23,'0.5 kg','0.5 kg',NULL,880.00,NULL,35,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(134,23,'1 lb','1 lb',NULL,1130.00,NULL,35,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(135,23,'1.5 lb','1.5 lb',NULL,1430.00,NULL,35,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(136,23,'2 lb','2 lb',NULL,1730.00,NULL,35,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(137,23,'2.5 lb','2.5 lb',NULL,2030.00,NULL,35,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(138,23,'3 lb','3 lb',NULL,2330.00,NULL,35,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(139,24,'0.5 kg','0.5 kg',NULL,915.00,NULL,36,NULL,1,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(140,24,'1 lb','1 lb',NULL,1165.00,NULL,36,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(141,24,'1.5 lb','1.5 lb',NULL,1465.00,NULL,36,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(142,24,'2 lb','2 lb',NULL,1765.00,NULL,36,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(143,24,'2.5 lb','2.5 lb',NULL,2065.00,NULL,36,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(144,24,'3 lb','3 lb',NULL,2365.00,NULL,36,NULL,0,1,'2026-05-19 04:31:11','2026-05-19 04:31:11'),(145,25,'0.5 kg','0.5 kg',NULL,950.00,NULL,37,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(146,25,'1 lb','1 lb',NULL,1200.00,NULL,37,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(147,25,'1.5 lb','1.5 lb',NULL,1500.00,NULL,37,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(148,25,'2 lb','2 lb',NULL,1800.00,NULL,37,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(149,25,'2.5 lb','2.5 lb',NULL,2100.00,NULL,37,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(150,25,'3 lb','3 lb',NULL,2400.00,NULL,37,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(151,26,'0.5 kg','0.5 kg',NULL,985.00,NULL,38,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(152,26,'1 lb','1 lb',NULL,1235.00,NULL,38,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(153,26,'1.5 lb','1.5 lb',NULL,1535.00,NULL,38,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(154,26,'2 lb','2 lb',NULL,1835.00,NULL,38,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(155,26,'2.5 lb','2.5 lb',NULL,2135.00,NULL,38,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(156,26,'3 lb','3 lb',NULL,2435.00,NULL,38,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(157,27,'0.5 kg','0.5 kg',NULL,1020.00,NULL,39,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(158,27,'1 lb','1 lb',NULL,1270.00,NULL,39,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(159,27,'1.5 lb','1.5 lb',NULL,1570.00,NULL,39,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(160,27,'2 lb','2 lb',NULL,1870.00,NULL,39,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(161,27,'2.5 lb','2.5 lb',NULL,2170.00,NULL,39,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(162,27,'3 lb','3 lb',NULL,2470.00,NULL,39,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(163,28,'0.5 kg','0.5 kg',NULL,1055.00,NULL,40,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(164,28,'1 lb','1 lb',NULL,1305.00,NULL,40,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(165,28,'1.5 lb','1.5 lb',NULL,1605.00,NULL,40,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(166,28,'2 lb','2 lb',NULL,1905.00,NULL,40,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(167,28,'2.5 lb','2.5 lb',NULL,2205.00,NULL,40,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(168,28,'3 lb','3 lb',NULL,2505.00,NULL,40,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(169,29,'0.5 kg','0.5 kg',NULL,1090.00,NULL,41,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(170,29,'1 lb','1 lb',NULL,1340.00,NULL,41,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(171,29,'1.5 lb','1.5 lb',NULL,1640.00,NULL,41,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(172,29,'2 lb','2 lb',NULL,1940.00,NULL,41,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(173,29,'2.5 lb','2.5 lb',NULL,2240.00,NULL,41,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(174,29,'3 lb','3 lb',NULL,2540.00,NULL,41,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(175,30,'0.5 kg','0.5 kg',NULL,600.00,NULL,42,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(176,30,'1 lb','1 lb',NULL,850.00,NULL,42,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(177,30,'1.5 lb','1.5 lb',NULL,1150.00,NULL,42,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(178,30,'2 lb','2 lb',NULL,1450.00,NULL,42,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(179,30,'2.5 lb','2.5 lb',NULL,1750.00,NULL,42,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(180,30,'3 lb','3 lb',NULL,2050.00,NULL,42,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(181,31,'0.5 kg','0.5 kg',NULL,635.00,NULL,43,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(182,31,'1 lb','1 lb',NULL,885.00,NULL,43,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(183,31,'1.5 lb','1.5 lb',NULL,1185.00,NULL,43,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(184,31,'2 lb','2 lb',NULL,1485.00,NULL,43,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(185,31,'2.5 lb','2.5 lb',NULL,1785.00,NULL,43,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(186,31,'3 lb','3 lb',NULL,2085.00,NULL,43,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(187,32,'0.5 kg','0.5 kg',NULL,670.00,NULL,44,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(188,32,'1 lb','1 lb',NULL,920.00,NULL,44,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(189,32,'1.5 lb','1.5 lb',NULL,1220.00,NULL,44,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(190,32,'2 lb','2 lb',NULL,1520.00,NULL,44,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(191,32,'2.5 lb','2.5 lb',NULL,1820.00,NULL,44,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(192,32,'3 lb','3 lb',NULL,2120.00,NULL,44,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(193,33,'0.5 kg','0.5 kg',NULL,705.00,NULL,45,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(194,33,'1 lb','1 lb',NULL,955.00,NULL,45,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(195,33,'1.5 lb','1.5 lb',NULL,1255.00,NULL,45,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(196,33,'2 lb','2 lb',NULL,1555.00,NULL,45,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(197,33,'2.5 lb','2.5 lb',NULL,1855.00,NULL,45,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(198,33,'3 lb','3 lb',NULL,2155.00,NULL,45,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(199,34,'0.5 kg','0.5 kg',NULL,740.00,NULL,46,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(200,34,'1 lb','1 lb',NULL,990.00,NULL,46,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(201,34,'1.5 lb','1.5 lb',NULL,1290.00,NULL,46,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(202,34,'2 lb','2 lb',NULL,1590.00,NULL,46,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(203,34,'2.5 lb','2.5 lb',NULL,1890.00,NULL,46,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(204,34,'3 lb','3 lb',NULL,2190.00,NULL,46,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(205,35,'0.5 kg','0.5 kg',NULL,775.00,NULL,47,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(206,35,'1 lb','1 lb',NULL,1025.00,NULL,47,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(207,35,'1.5 lb','1.5 lb',NULL,1325.00,NULL,47,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(208,35,'2 lb','2 lb',NULL,1625.00,NULL,47,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(209,35,'2.5 lb','2.5 lb',NULL,1925.00,NULL,47,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(210,35,'3 lb','3 lb',NULL,2225.00,NULL,47,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(211,36,'0.5 kg','0.5 kg',NULL,810.00,NULL,48,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(212,36,'1 lb','1 lb',NULL,1060.00,NULL,48,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(213,36,'1.5 lb','1.5 lb',NULL,1360.00,NULL,48,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(214,36,'2 lb','2 lb',NULL,1660.00,NULL,48,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(215,36,'2.5 lb','2.5 lb',NULL,1960.00,NULL,48,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(216,36,'3 lb','3 lb',NULL,2260.00,NULL,48,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(217,37,'0.5 kg','0.5 kg',NULL,845.00,NULL,49,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(218,37,'1 lb','1 lb',NULL,1095.00,NULL,49,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(219,37,'1.5 lb','1.5 lb',NULL,1395.00,NULL,49,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(220,37,'2 lb','2 lb',NULL,1695.00,NULL,49,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(221,37,'2.5 lb','2.5 lb',NULL,1995.00,NULL,49,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(222,37,'3 lb','3 lb',NULL,2295.00,NULL,49,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(223,38,'0.5 kg','0.5 kg',NULL,880.00,NULL,50,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(224,38,'1 lb','1 lb',NULL,1130.00,NULL,50,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(225,38,'1.5 lb','1.5 lb',NULL,1430.00,NULL,50,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(226,38,'2 lb','2 lb',NULL,1730.00,NULL,50,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(227,38,'2.5 lb','2.5 lb',NULL,2030.00,NULL,50,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(228,38,'3 lb','3 lb',NULL,2330.00,NULL,50,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(229,39,'0.5 kg','0.5 kg',NULL,915.00,NULL,51,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(230,39,'1 lb','1 lb',NULL,1165.00,NULL,51,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(231,39,'1.5 lb','1.5 lb',NULL,1465.00,NULL,51,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(232,39,'2 lb','2 lb',NULL,1765.00,NULL,51,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(233,39,'2.5 lb','2.5 lb',NULL,2065.00,NULL,51,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(234,39,'3 lb','3 lb',NULL,2365.00,NULL,51,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(235,40,'0.5 kg','0.5 kg',NULL,950.00,NULL,12,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(236,40,'1 lb','1 lb',NULL,1200.00,NULL,12,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(237,40,'1.5 lb','1.5 lb',NULL,1500.00,NULL,12,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(238,40,'2 lb','2 lb',NULL,1800.00,NULL,12,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(239,40,'2.5 lb','2.5 lb',NULL,2100.00,NULL,12,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(240,40,'3 lb','3 lb',NULL,2400.00,NULL,12,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(241,41,'0.5 kg','0.5 kg',NULL,985.00,NULL,13,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(242,41,'1 lb','1 lb',NULL,1235.00,NULL,13,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(243,41,'1.5 lb','1.5 lb',NULL,1535.00,NULL,13,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(244,41,'2 lb','2 lb',NULL,1835.00,NULL,13,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(245,41,'2.5 lb','2.5 lb',NULL,2135.00,NULL,13,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(246,41,'3 lb','3 lb',NULL,2435.00,NULL,13,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(247,42,'0.5 kg','0.5 kg',NULL,1020.00,NULL,14,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(248,42,'1 lb','1 lb',NULL,1270.00,NULL,14,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(249,42,'1.5 lb','1.5 lb',NULL,1570.00,NULL,14,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(250,42,'2 lb','2 lb',NULL,1870.00,NULL,14,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(251,42,'2.5 lb','2.5 lb',NULL,2170.00,NULL,14,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(252,42,'3 lb','3 lb',NULL,2470.00,NULL,14,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(253,43,'0.5 kg','0.5 kg',NULL,1055.00,NULL,15,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(254,43,'1 lb','1 lb',NULL,1305.00,NULL,15,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(255,43,'1.5 lb','1.5 lb',NULL,1605.00,NULL,15,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(256,43,'2 lb','2 lb',NULL,1905.00,NULL,15,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(257,43,'2.5 lb','2.5 lb',NULL,2205.00,NULL,15,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(258,43,'3 lb','3 lb',NULL,2505.00,NULL,15,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(259,44,'0.5 kg','0.5 kg',NULL,1090.00,NULL,16,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(260,44,'1 lb','1 lb',NULL,1340.00,NULL,16,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(261,44,'1.5 lb','1.5 lb',NULL,1640.00,NULL,16,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(262,44,'2 lb','2 lb',NULL,1940.00,NULL,16,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(263,44,'2.5 lb','2.5 lb',NULL,2240.00,NULL,16,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(264,44,'3 lb','3 lb',NULL,2540.00,NULL,16,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(265,45,'0.5 kg','0.5 kg',NULL,600.00,NULL,17,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(266,45,'1 lb','1 lb',NULL,850.00,NULL,17,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(267,45,'1.5 lb','1.5 lb',NULL,1150.00,NULL,17,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(268,45,'2 lb','2 lb',NULL,1450.00,NULL,17,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(269,45,'2.5 lb','2.5 lb',NULL,1750.00,NULL,17,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(270,45,'3 lb','3 lb',NULL,2050.00,NULL,17,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(271,46,'0.5 kg','0.5 kg',NULL,635.00,NULL,18,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(272,46,'1 lb','1 lb',NULL,885.00,NULL,18,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(273,46,'1.5 lb','1.5 lb',NULL,1185.00,NULL,18,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(274,46,'2 lb','2 lb',NULL,1485.00,NULL,18,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(275,46,'2.5 lb','2.5 lb',NULL,1785.00,NULL,18,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(276,46,'3 lb','3 lb',NULL,2085.00,NULL,18,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(277,47,'0.5 kg','0.5 kg',NULL,670.00,NULL,19,NULL,1,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(278,47,'1 lb','1 lb',NULL,920.00,NULL,19,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(279,47,'1.5 lb','1.5 lb',NULL,1220.00,NULL,19,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(280,47,'2 lb','2 lb',NULL,1520.00,NULL,19,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(281,47,'2.5 lb','2.5 lb',NULL,1820.00,NULL,19,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(282,47,'3 lb','3 lb',NULL,2120.00,NULL,19,NULL,0,1,'2026-05-19 04:31:12','2026-05-19 04:31:12'),(283,48,'0.5 kg','0.5 kg',NULL,705.00,NULL,20,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(284,48,'1 lb','1 lb',NULL,955.00,NULL,20,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(285,48,'1.5 lb','1.5 lb',NULL,1255.00,NULL,20,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(286,48,'2 lb','2 lb',NULL,1555.00,NULL,20,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(287,48,'2.5 lb','2.5 lb',NULL,1855.00,NULL,20,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(288,48,'3 lb','3 lb',NULL,2155.00,NULL,20,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(289,49,'0.5 kg','0.5 kg',NULL,740.00,NULL,21,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(290,49,'1 lb','1 lb',NULL,990.00,NULL,21,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(291,49,'1.5 lb','1.5 lb',NULL,1290.00,NULL,21,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(292,49,'2 lb','2 lb',NULL,1590.00,NULL,21,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(293,49,'2.5 lb','2.5 lb',NULL,1890.00,NULL,21,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(294,49,'3 lb','3 lb',NULL,2190.00,NULL,21,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(295,50,'0.5 kg','0.5 kg',NULL,775.00,NULL,22,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(296,50,'1 lb','1 lb',NULL,1025.00,NULL,22,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(297,50,'1.5 lb','1.5 lb',NULL,1325.00,NULL,22,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(298,50,'2 lb','2 lb',NULL,1625.00,NULL,22,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(299,50,'2.5 lb','2.5 lb',NULL,1925.00,NULL,22,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(300,50,'3 lb','3 lb',NULL,2225.00,NULL,22,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(301,51,'0.5 kg','0.5 kg',NULL,810.00,NULL,23,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(302,51,'1 lb','1 lb',NULL,1060.00,NULL,23,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(303,51,'1.5 lb','1.5 lb',NULL,1360.00,NULL,23,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(304,51,'2 lb','2 lb',NULL,1660.00,NULL,23,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(305,51,'2.5 lb','2.5 lb',NULL,1960.00,NULL,23,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(306,51,'3 lb','3 lb',NULL,2260.00,NULL,23,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(307,52,'0.5 kg','0.5 kg',NULL,845.00,NULL,24,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(308,52,'1 lb','1 lb',NULL,1095.00,NULL,24,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(309,52,'1.5 lb','1.5 lb',NULL,1395.00,NULL,24,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(310,52,'2 lb','2 lb',NULL,1695.00,NULL,24,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(311,52,'2.5 lb','2.5 lb',NULL,1995.00,NULL,24,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(312,52,'3 lb','3 lb',NULL,2295.00,NULL,24,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(313,53,'0.5 kg','0.5 kg',NULL,880.00,NULL,25,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(314,53,'1 lb','1 lb',NULL,1130.00,NULL,25,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(315,53,'1.5 lb','1.5 lb',NULL,1430.00,NULL,25,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(316,53,'2 lb','2 lb',NULL,1730.00,NULL,25,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(317,53,'2.5 lb','2.5 lb',NULL,2030.00,NULL,25,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(318,53,'3 lb','3 lb',NULL,2330.00,NULL,25,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(319,54,'0.5 kg','0.5 kg',NULL,915.00,NULL,26,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(320,54,'1 lb','1 lb',NULL,1165.00,NULL,26,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(321,54,'1.5 lb','1.5 lb',NULL,1465.00,NULL,26,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(322,54,'2 lb','2 lb',NULL,1765.00,NULL,26,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(323,54,'2.5 lb','2.5 lb',NULL,2065.00,NULL,26,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(324,54,'3 lb','3 lb',NULL,2365.00,NULL,26,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(325,55,'0.5 kg','0.5 kg',NULL,950.00,NULL,27,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(326,55,'1 lb','1 lb',NULL,1200.00,NULL,27,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(327,55,'1.5 lb','1.5 lb',NULL,1500.00,NULL,27,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(328,55,'2 lb','2 lb',NULL,1800.00,NULL,27,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(329,55,'2.5 lb','2.5 lb',NULL,2100.00,NULL,27,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(330,55,'3 lb','3 lb',NULL,2400.00,NULL,27,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(331,56,'0.5 kg','0.5 kg',NULL,985.00,NULL,28,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(332,56,'1 lb','1 lb',NULL,1235.00,NULL,28,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(333,56,'1.5 lb','1.5 lb',NULL,1535.00,NULL,28,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(334,56,'2 lb','2 lb',NULL,1835.00,NULL,28,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(335,56,'2.5 lb','2.5 lb',NULL,2135.00,NULL,28,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(336,56,'3 lb','3 lb',NULL,2435.00,NULL,28,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(337,57,'0.5 kg','0.5 kg',NULL,1020.00,NULL,29,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(338,57,'1 lb','1 lb',NULL,1270.00,NULL,29,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(339,57,'1.5 lb','1.5 lb',NULL,1570.00,NULL,29,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(340,57,'2 lb','2 lb',NULL,1870.00,NULL,29,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(341,57,'2.5 lb','2.5 lb',NULL,2170.00,NULL,29,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(342,57,'3 lb','3 lb',NULL,2470.00,NULL,29,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(343,58,'0.5 kg','0.5 kg',NULL,1055.00,NULL,30,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(344,58,'1 lb','1 lb',NULL,1305.00,NULL,30,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(345,58,'1.5 lb','1.5 lb',NULL,1605.00,NULL,30,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(346,58,'2 lb','2 lb',NULL,1905.00,NULL,30,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(347,58,'2.5 lb','2.5 lb',NULL,2205.00,NULL,30,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(348,58,'3 lb','3 lb',NULL,2505.00,NULL,30,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(349,59,'0.5 kg','0.5 kg',NULL,1090.00,NULL,31,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(350,59,'1 lb','1 lb',NULL,1340.00,NULL,31,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(351,59,'1.5 lb','1.5 lb',NULL,1640.00,NULL,31,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(352,59,'2 lb','2 lb',NULL,1940.00,NULL,31,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(353,59,'2.5 lb','2.5 lb',NULL,2240.00,NULL,31,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(354,59,'3 lb','3 lb',NULL,2540.00,NULL,31,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(355,60,'0.5 kg','0.5 kg',NULL,600.00,NULL,32,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(356,60,'1 lb','1 lb',NULL,850.00,NULL,32,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(357,60,'1.5 lb','1.5 lb',NULL,1150.00,NULL,32,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(358,60,'2 lb','2 lb',NULL,1450.00,NULL,32,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(359,60,'2.5 lb','2.5 lb',NULL,1750.00,NULL,32,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(360,60,'3 lb','3 lb',NULL,2050.00,NULL,32,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(361,61,'0.5 kg','0.5 kg',NULL,635.00,NULL,33,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(362,61,'1 lb','1 lb',NULL,885.00,NULL,33,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(363,61,'1.5 lb','1.5 lb',NULL,1185.00,NULL,33,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(364,61,'2 lb','2 lb',NULL,1485.00,NULL,33,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(365,61,'2.5 lb','2.5 lb',NULL,1785.00,NULL,33,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(366,61,'3 lb','3 lb',NULL,2085.00,NULL,33,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(367,62,'0.5 kg','0.5 kg',NULL,670.00,NULL,34,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(368,62,'1 lb','1 lb',NULL,920.00,NULL,34,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(369,62,'1.5 lb','1.5 lb',NULL,1220.00,NULL,34,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(370,62,'2 lb','2 lb',NULL,1520.00,NULL,34,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(371,62,'2.5 lb','2.5 lb',NULL,1820.00,NULL,34,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(372,62,'3 lb','3 lb',NULL,2120.00,NULL,34,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(373,63,'0.5 kg','0.5 kg',NULL,705.00,NULL,35,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(374,63,'1 lb','1 lb',NULL,955.00,NULL,35,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(375,63,'1.5 lb','1.5 lb',NULL,1255.00,NULL,35,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(376,63,'2 lb','2 lb',NULL,1555.00,NULL,35,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(377,63,'2.5 lb','2.5 lb',NULL,1855.00,NULL,35,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(378,63,'3 lb','3 lb',NULL,2155.00,NULL,35,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(379,64,'0.5 kg','0.5 kg',NULL,740.00,NULL,36,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(380,64,'1 lb','1 lb',NULL,990.00,NULL,36,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(381,64,'1.5 lb','1.5 lb',NULL,1290.00,NULL,36,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(382,64,'2 lb','2 lb',NULL,1590.00,NULL,36,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(383,64,'2.5 lb','2.5 lb',NULL,1890.00,NULL,36,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(384,64,'3 lb','3 lb',NULL,2190.00,NULL,36,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(385,65,'0.5 kg','0.5 kg',NULL,775.00,NULL,37,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(386,65,'1 lb','1 lb',NULL,1025.00,NULL,37,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(387,65,'1.5 lb','1.5 lb',NULL,1325.00,NULL,37,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(388,65,'2 lb','2 lb',NULL,1625.00,NULL,37,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(389,65,'2.5 lb','2.5 lb',NULL,1925.00,NULL,37,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(390,65,'3 lb','3 lb',NULL,2225.00,NULL,37,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(391,66,'0.5 kg','0.5 kg',NULL,810.00,NULL,38,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(392,66,'1 lb','1 lb',NULL,1060.00,NULL,38,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(393,66,'1.5 lb','1.5 lb',NULL,1360.00,NULL,38,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(394,66,'2 lb','2 lb',NULL,1660.00,NULL,38,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(395,66,'2.5 lb','2.5 lb',NULL,1960.00,NULL,38,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(396,66,'3 lb','3 lb',NULL,2260.00,NULL,38,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(397,67,'0.5 kg','0.5 kg',NULL,845.00,NULL,39,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(398,67,'1 lb','1 lb',NULL,1095.00,NULL,39,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(399,67,'1.5 lb','1.5 lb',NULL,1395.00,NULL,39,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(400,67,'2 lb','2 lb',NULL,1695.00,NULL,39,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(401,67,'2.5 lb','2.5 lb',NULL,1995.00,NULL,39,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(402,67,'3 lb','3 lb',NULL,2295.00,NULL,39,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(403,68,'0.5 kg','0.5 kg',NULL,880.00,NULL,40,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(404,68,'1 lb','1 lb',NULL,1130.00,NULL,40,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(405,68,'1.5 lb','1.5 lb',NULL,1430.00,NULL,40,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(406,68,'2 lb','2 lb',NULL,1730.00,NULL,40,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(407,68,'2.5 lb','2.5 lb',NULL,2030.00,NULL,40,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(408,68,'3 lb','3 lb',NULL,2330.00,NULL,40,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(409,69,'0.5 kg','0.5 kg',NULL,915.00,NULL,41,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(410,69,'1 lb','1 lb',NULL,1165.00,NULL,41,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(411,69,'1.5 lb','1.5 lb',NULL,1465.00,NULL,41,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(412,69,'2 lb','2 lb',NULL,1765.00,NULL,41,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(413,69,'2.5 lb','2.5 lb',NULL,2065.00,NULL,41,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(414,69,'3 lb','3 lb',NULL,2365.00,NULL,41,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(415,70,'0.5 kg','0.5 kg',NULL,950.00,NULL,42,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(416,70,'1 lb','1 lb',NULL,1200.00,NULL,42,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(417,70,'1.5 lb','1.5 lb',NULL,1500.00,NULL,42,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(418,70,'2 lb','2 lb',NULL,1800.00,NULL,42,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(419,70,'2.5 lb','2.5 lb',NULL,2100.00,NULL,42,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(420,70,'3 lb','3 lb',NULL,2400.00,NULL,42,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(421,71,'0.5 kg','0.5 kg',NULL,985.00,NULL,43,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(422,71,'1 lb','1 lb',NULL,1235.00,NULL,43,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(423,71,'1.5 lb','1.5 lb',NULL,1535.00,NULL,43,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(424,71,'2 lb','2 lb',NULL,1835.00,NULL,43,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(425,71,'2.5 lb','2.5 lb',NULL,2135.00,NULL,43,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(426,71,'3 lb','3 lb',NULL,2435.00,NULL,43,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(427,72,'0.5 kg','0.5 kg',NULL,1020.00,NULL,44,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(428,72,'1 lb','1 lb',NULL,1270.00,NULL,44,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(429,72,'1.5 lb','1.5 lb',NULL,1570.00,NULL,44,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(430,72,'2 lb','2 lb',NULL,1870.00,NULL,44,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(431,72,'2.5 lb','2.5 lb',NULL,2170.00,NULL,44,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(432,72,'3 lb','3 lb',NULL,2470.00,NULL,44,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(433,73,'0.5 kg','0.5 kg',NULL,1055.00,NULL,45,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(434,73,'1 lb','1 lb',NULL,1305.00,NULL,45,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(435,73,'1.5 lb','1.5 lb',NULL,1605.00,NULL,45,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(436,73,'2 lb','2 lb',NULL,1905.00,NULL,45,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(437,73,'2.5 lb','2.5 lb',NULL,2205.00,NULL,45,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(438,73,'3 lb','3 lb',NULL,2505.00,NULL,45,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(439,74,'0.5 kg','0.5 kg',NULL,1090.00,NULL,46,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(440,74,'1 lb','1 lb',NULL,1340.00,NULL,46,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(441,74,'1.5 lb','1.5 lb',NULL,1640.00,NULL,46,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(442,74,'2 lb','2 lb',NULL,1940.00,NULL,46,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(443,74,'2.5 lb','2.5 lb',NULL,2240.00,NULL,46,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(444,74,'3 lb','3 lb',NULL,2540.00,NULL,46,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(445,75,'0.5 kg','0.5 kg',NULL,600.00,NULL,47,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(446,75,'1 lb','1 lb',NULL,850.00,NULL,47,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(447,75,'1.5 lb','1.5 lb',NULL,1150.00,NULL,47,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(448,75,'2 lb','2 lb',NULL,1450.00,NULL,47,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(449,75,'2.5 lb','2.5 lb',NULL,1750.00,NULL,47,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(450,75,'3 lb','3 lb',NULL,2050.00,NULL,47,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(451,76,'0.5 kg','0.5 kg',NULL,635.00,NULL,48,NULL,1,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(452,76,'1 lb','1 lb',NULL,885.00,NULL,48,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(453,76,'1.5 lb','1.5 lb',NULL,1185.00,NULL,48,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(454,76,'2 lb','2 lb',NULL,1485.00,NULL,48,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(455,76,'2.5 lb','2.5 lb',NULL,1785.00,NULL,48,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(456,76,'3 lb','3 lb',NULL,2085.00,NULL,48,NULL,0,1,'2026-05-19 04:31:13','2026-05-19 04:31:13'),(457,77,'0.5 kg','0.5 kg',NULL,670.00,NULL,49,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(458,77,'1 lb','1 lb',NULL,920.00,NULL,49,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(459,77,'1.5 lb','1.5 lb',NULL,1220.00,NULL,49,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(460,77,'2 lb','2 lb',NULL,1520.00,NULL,49,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(461,77,'2.5 lb','2.5 lb',NULL,1820.00,NULL,49,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(462,77,'3 lb','3 lb',NULL,2120.00,NULL,49,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(463,78,'0.5 kg','0.5 kg',NULL,705.00,NULL,50,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(464,78,'1 lb','1 lb',NULL,955.00,NULL,50,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(465,78,'1.5 lb','1.5 lb',NULL,1255.00,NULL,50,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(466,78,'2 lb','2 lb',NULL,1555.00,NULL,50,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(467,78,'2.5 lb','2.5 lb',NULL,1855.00,NULL,50,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(468,78,'3 lb','3 lb',NULL,2155.00,NULL,50,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(469,79,'0.5 kg','0.5 kg',NULL,740.00,NULL,51,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(470,79,'1 lb','1 lb',NULL,990.00,NULL,51,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(471,79,'1.5 lb','1.5 lb',NULL,1290.00,NULL,51,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(472,79,'2 lb','2 lb',NULL,1590.00,NULL,51,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(473,79,'2.5 lb','2.5 lb',NULL,1890.00,NULL,51,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(474,79,'3 lb','3 lb',NULL,2190.00,NULL,51,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(475,80,'0.5 kg','0.5 kg',NULL,775.00,NULL,12,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(476,80,'1 lb','1 lb',NULL,1025.00,NULL,12,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(477,80,'1.5 lb','1.5 lb',NULL,1325.00,NULL,12,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(478,80,'2 lb','2 lb',NULL,1625.00,NULL,12,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(479,80,'2.5 lb','2.5 lb',NULL,1925.00,NULL,12,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(480,80,'3 lb','3 lb',NULL,2225.00,NULL,12,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(481,81,'0.5 kg','0.5 kg',NULL,810.00,NULL,13,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(482,81,'1 lb','1 lb',NULL,1060.00,NULL,13,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(483,81,'1.5 lb','1.5 lb',NULL,1360.00,NULL,13,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(484,81,'2 lb','2 lb',NULL,1660.00,NULL,13,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(485,81,'2.5 lb','2.5 lb',NULL,1960.00,NULL,13,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(486,81,'3 lb','3 lb',NULL,2260.00,NULL,13,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(487,82,'0.5 kg','0.5 kg',NULL,845.00,NULL,14,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(488,82,'1 lb','1 lb',NULL,1095.00,NULL,14,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(489,82,'1.5 lb','1.5 lb',NULL,1395.00,NULL,14,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(490,82,'2 lb','2 lb',NULL,1695.00,NULL,14,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(491,82,'2.5 lb','2.5 lb',NULL,1995.00,NULL,14,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(492,82,'3 lb','3 lb',NULL,2295.00,NULL,14,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(493,83,'0.5 kg','0.5 kg',NULL,880.00,NULL,15,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(494,83,'1 lb','1 lb',NULL,1130.00,NULL,15,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(495,83,'1.5 lb','1.5 lb',NULL,1430.00,NULL,15,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(496,83,'2 lb','2 lb',NULL,1730.00,NULL,15,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(497,83,'2.5 lb','2.5 lb',NULL,2030.00,NULL,15,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(498,83,'3 lb','3 lb',NULL,2330.00,NULL,15,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(499,84,'0.5 kg','0.5 kg',NULL,915.00,NULL,16,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(500,84,'1 lb','1 lb',NULL,1165.00,NULL,16,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(501,84,'1.5 lb','1.5 lb',NULL,1465.00,NULL,16,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(502,84,'2 lb','2 lb',NULL,1765.00,NULL,16,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(503,84,'2.5 lb','2.5 lb',NULL,2065.00,NULL,16,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(504,84,'3 lb','3 lb',NULL,2365.00,NULL,16,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(505,85,'0.5 kg','0.5 kg',NULL,950.00,NULL,17,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(506,85,'1 lb','1 lb',NULL,1200.00,NULL,17,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(507,85,'1.5 lb','1.5 lb',NULL,1500.00,NULL,17,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(508,85,'2 lb','2 lb',NULL,1800.00,NULL,17,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(509,85,'2.5 lb','2.5 lb',NULL,2100.00,NULL,17,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(510,85,'3 lb','3 lb',NULL,2400.00,NULL,17,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(511,86,'0.5 kg','0.5 kg',NULL,985.00,NULL,18,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(512,86,'1 lb','1 lb',NULL,1235.00,NULL,18,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(513,86,'1.5 lb','1.5 lb',NULL,1535.00,NULL,18,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(514,86,'2 lb','2 lb',NULL,1835.00,NULL,18,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(515,86,'2.5 lb','2.5 lb',NULL,2135.00,NULL,18,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(516,86,'3 lb','3 lb',NULL,2435.00,NULL,18,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(517,87,'0.5 kg','0.5 kg',NULL,1020.00,NULL,19,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(518,87,'1 lb','1 lb',NULL,1270.00,NULL,19,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(519,87,'1.5 lb','1.5 lb',NULL,1570.00,NULL,19,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(520,87,'2 lb','2 lb',NULL,1870.00,NULL,19,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(521,87,'2.5 lb','2.5 lb',NULL,2170.00,NULL,19,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(522,87,'3 lb','3 lb',NULL,2470.00,NULL,19,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(523,88,'0.5 kg','0.5 kg',NULL,1055.00,NULL,20,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(524,88,'1 lb','1 lb',NULL,1305.00,NULL,20,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(525,88,'1.5 lb','1.5 lb',NULL,1605.00,NULL,20,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(526,88,'2 lb','2 lb',NULL,1905.00,NULL,20,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(527,88,'2.5 lb','2.5 lb',NULL,2205.00,NULL,20,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(528,88,'3 lb','3 lb',NULL,2505.00,NULL,20,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(529,89,'0.5 kg','0.5 kg',NULL,1090.00,NULL,21,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(530,89,'1 lb','1 lb',NULL,1340.00,NULL,21,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(531,89,'1.5 lb','1.5 lb',NULL,1640.00,NULL,21,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(532,89,'2 lb','2 lb',NULL,1940.00,NULL,21,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(533,89,'2.5 lb','2.5 lb',NULL,2240.00,NULL,21,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(534,89,'3 lb','3 lb',NULL,2540.00,NULL,21,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(535,90,'0.5 kg','0.5 kg',NULL,600.00,NULL,22,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(536,90,'1 lb','1 lb',NULL,850.00,NULL,22,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(537,90,'1.5 lb','1.5 lb',NULL,1150.00,NULL,22,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(538,90,'2 lb','2 lb',NULL,1450.00,NULL,22,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(539,90,'2.5 lb','2.5 lb',NULL,1750.00,NULL,22,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(540,90,'3 lb','3 lb',NULL,2050.00,NULL,22,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(541,91,'0.5 kg','0.5 kg',NULL,635.00,NULL,23,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(542,91,'1 lb','1 lb',NULL,885.00,NULL,23,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(543,91,'1.5 lb','1.5 lb',NULL,1185.00,NULL,23,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(544,91,'2 lb','2 lb',NULL,1485.00,NULL,23,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(545,91,'2.5 lb','2.5 lb',NULL,1785.00,NULL,23,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(546,91,'3 lb','3 lb',NULL,2085.00,NULL,23,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(547,92,'0.5 kg','0.5 kg',NULL,670.00,NULL,24,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(548,92,'1 lb','1 lb',NULL,920.00,NULL,24,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(549,92,'1.5 lb','1.5 lb',NULL,1220.00,NULL,24,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(550,92,'2 lb','2 lb',NULL,1520.00,NULL,24,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(551,92,'2.5 lb','2.5 lb',NULL,1820.00,NULL,24,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(552,92,'3 lb','3 lb',NULL,2120.00,NULL,24,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(553,93,'0.5 kg','0.5 kg',NULL,705.00,NULL,25,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(554,93,'1 lb','1 lb',NULL,955.00,NULL,25,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(555,93,'1.5 lb','1.5 lb',NULL,1255.00,NULL,25,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(556,93,'2 lb','2 lb',NULL,1555.00,NULL,25,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(557,93,'2.5 lb','2.5 lb',NULL,1855.00,NULL,25,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(558,93,'3 lb','3 lb',NULL,2155.00,NULL,25,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(559,94,'0.5 kg','0.5 kg',NULL,740.00,NULL,26,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(560,94,'1 lb','1 lb',NULL,990.00,NULL,26,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(561,94,'1.5 lb','1.5 lb',NULL,1290.00,NULL,26,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(562,94,'2 lb','2 lb',NULL,1590.00,NULL,26,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(563,94,'2.5 lb','2.5 lb',NULL,1890.00,NULL,26,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(564,94,'3 lb','3 lb',NULL,2190.00,NULL,26,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(565,95,'0.5 kg','0.5 kg',NULL,775.00,NULL,27,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(566,95,'1 lb','1 lb',NULL,1025.00,NULL,27,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(567,95,'1.5 lb','1.5 lb',NULL,1325.00,NULL,27,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(568,95,'2 lb','2 lb',NULL,1625.00,NULL,27,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(569,95,'2.5 lb','2.5 lb',NULL,1925.00,NULL,27,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(570,95,'3 lb','3 lb',NULL,2225.00,NULL,27,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(571,96,'0.5 kg','0.5 kg',NULL,810.00,NULL,28,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(572,96,'1 lb','1 lb',NULL,1060.00,NULL,28,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(573,96,'1.5 lb','1.5 lb',NULL,1360.00,NULL,28,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(574,96,'2 lb','2 lb',NULL,1660.00,NULL,28,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(575,96,'2.5 lb','2.5 lb',NULL,1960.00,NULL,28,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(576,96,'3 lb','3 lb',NULL,2260.00,NULL,28,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(577,97,'0.5 kg','0.5 kg',NULL,845.00,NULL,29,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(578,97,'1 lb','1 lb',NULL,1095.00,NULL,29,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(579,97,'1.5 lb','1.5 lb',NULL,1395.00,NULL,29,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(580,97,'2 lb','2 lb',NULL,1695.00,NULL,29,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(581,97,'2.5 lb','2.5 lb',NULL,1995.00,NULL,29,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(582,97,'3 lb','3 lb',NULL,2295.00,NULL,29,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(583,98,'0.5 kg','0.5 kg',NULL,880.00,NULL,30,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(584,98,'1 lb','1 lb',NULL,1130.00,NULL,30,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(585,98,'1.5 lb','1.5 lb',NULL,1430.00,NULL,30,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(586,98,'2 lb','2 lb',NULL,1730.00,NULL,30,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(587,98,'2.5 lb','2.5 lb',NULL,2030.00,NULL,30,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(588,98,'3 lb','3 lb',NULL,2330.00,NULL,30,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(589,99,'0.5 kg','0.5 kg',NULL,915.00,NULL,31,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(590,99,'1 lb','1 lb',NULL,1165.00,NULL,31,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(591,99,'1.5 lb','1.5 lb',NULL,1465.00,NULL,31,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(592,99,'2 lb','2 lb',NULL,1765.00,NULL,31,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(593,99,'2.5 lb','2.5 lb',NULL,2065.00,NULL,31,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(594,99,'3 lb','3 lb',NULL,2365.00,NULL,31,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(595,100,'0.5 kg','0.5 kg',NULL,950.00,NULL,32,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(596,100,'1 lb','1 lb',NULL,1200.00,NULL,32,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(597,100,'1.5 lb','1.5 lb',NULL,1500.00,NULL,32,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(598,100,'2 lb','2 lb',NULL,1800.00,NULL,32,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(599,100,'2.5 lb','2.5 lb',NULL,2100.00,NULL,32,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(600,100,'3 lb','3 lb',NULL,2400.00,NULL,32,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(601,101,'0.5 kg','0.5 kg',NULL,985.00,NULL,33,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(602,101,'1 lb','1 lb',NULL,1235.00,NULL,33,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(603,101,'1.5 lb','1.5 lb',NULL,1535.00,NULL,33,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(604,101,'2 lb','2 lb',NULL,1835.00,NULL,33,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(605,101,'2.5 lb','2.5 lb',NULL,2135.00,NULL,33,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(606,101,'3 lb','3 lb',NULL,2435.00,NULL,33,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(607,102,'0.5 kg','0.5 kg',NULL,1020.00,NULL,34,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(608,102,'1 lb','1 lb',NULL,1270.00,NULL,34,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(609,102,'1.5 lb','1.5 lb',NULL,1570.00,NULL,34,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(610,102,'2 lb','2 lb',NULL,1870.00,NULL,34,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(611,102,'2.5 lb','2.5 lb',NULL,2170.00,NULL,34,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(612,102,'3 lb','3 lb',NULL,2470.00,NULL,34,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(613,103,'0.5 kg','0.5 kg',NULL,1055.00,NULL,35,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(614,103,'1 lb','1 lb',NULL,1305.00,NULL,35,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(615,103,'1.5 lb','1.5 lb',NULL,1605.00,NULL,35,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(616,103,'2 lb','2 lb',NULL,1905.00,NULL,35,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(617,103,'2.5 lb','2.5 lb',NULL,2205.00,NULL,35,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(618,103,'3 lb','3 lb',NULL,2505.00,NULL,35,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(619,104,'0.5 kg','0.5 kg',NULL,1090.00,NULL,36,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(620,104,'1 lb','1 lb',NULL,1340.00,NULL,36,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(621,104,'1.5 lb','1.5 lb',NULL,1640.00,NULL,36,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(622,104,'2 lb','2 lb',NULL,1940.00,NULL,36,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(623,104,'2.5 lb','2.5 lb',NULL,2240.00,NULL,36,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(624,104,'3 lb','3 lb',NULL,2540.00,NULL,36,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(625,105,'0.5 kg','0.5 kg',NULL,600.00,NULL,37,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(626,105,'1 lb','1 lb',NULL,850.00,NULL,37,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(627,105,'1.5 lb','1.5 lb',NULL,1150.00,NULL,37,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(628,105,'2 lb','2 lb',NULL,1450.00,NULL,37,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(629,105,'2.5 lb','2.5 lb',NULL,1750.00,NULL,37,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(630,105,'3 lb','3 lb',NULL,2050.00,NULL,37,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(631,106,'0.5 kg','0.5 kg',NULL,635.00,NULL,38,NULL,1,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(632,106,'1 lb','1 lb',NULL,885.00,NULL,38,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(633,106,'1.5 lb','1.5 lb',NULL,1185.00,NULL,38,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(634,106,'2 lb','2 lb',NULL,1485.00,NULL,38,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(635,106,'2.5 lb','2.5 lb',NULL,1785.00,NULL,38,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(636,106,'3 lb','3 lb',NULL,2085.00,NULL,38,NULL,0,1,'2026-05-19 04:31:14','2026-05-19 04:31:14'),(637,107,'0.5 kg','0.5 kg',NULL,670.00,NULL,39,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(638,107,'1 lb','1 lb',NULL,920.00,NULL,39,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(639,107,'1.5 lb','1.5 lb',NULL,1220.00,NULL,39,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(640,107,'2 lb','2 lb',NULL,1520.00,NULL,39,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(641,107,'2.5 lb','2.5 lb',NULL,1820.00,NULL,39,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(642,107,'3 lb','3 lb',NULL,2120.00,NULL,39,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(643,108,'0.5 kg','0.5 kg',NULL,705.00,NULL,40,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(644,108,'1 lb','1 lb',NULL,955.00,NULL,40,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(645,108,'1.5 lb','1.5 lb',NULL,1255.00,NULL,40,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(646,108,'2 lb','2 lb',NULL,1555.00,NULL,40,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(647,108,'2.5 lb','2.5 lb',NULL,1855.00,NULL,40,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(648,108,'3 lb','3 lb',NULL,2155.00,NULL,40,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(649,109,'0.5 kg','0.5 kg',NULL,740.00,NULL,41,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(650,109,'1 lb','1 lb',NULL,990.00,NULL,41,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(651,109,'1.5 lb','1.5 lb',NULL,1290.00,NULL,41,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(652,109,'2 lb','2 lb',NULL,1590.00,NULL,41,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(653,109,'2.5 lb','2.5 lb',NULL,1890.00,NULL,41,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(654,109,'3 lb','3 lb',NULL,2190.00,NULL,41,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(655,110,'0.5 kg','0.5 kg',NULL,775.00,NULL,42,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(656,110,'1 lb','1 lb',NULL,1025.00,NULL,42,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(657,110,'1.5 lb','1.5 lb',NULL,1325.00,NULL,42,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(658,110,'2 lb','2 lb',NULL,1625.00,NULL,42,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(659,110,'2.5 lb','2.5 lb',NULL,1925.00,NULL,42,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(660,110,'3 lb','3 lb',NULL,2225.00,NULL,42,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(661,111,'0.5 kg','0.5 kg',NULL,810.00,NULL,43,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(662,111,'1 lb','1 lb',NULL,1060.00,NULL,43,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(663,111,'1.5 lb','1.5 lb',NULL,1360.00,NULL,43,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(664,111,'2 lb','2 lb',NULL,1660.00,NULL,43,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(665,111,'2.5 lb','2.5 lb',NULL,1960.00,NULL,43,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(666,111,'3 lb','3 lb',NULL,2260.00,NULL,43,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(667,112,'0.5 kg','0.5 kg',NULL,845.00,NULL,44,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(668,112,'1 lb','1 lb',NULL,1095.00,NULL,44,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(669,112,'1.5 lb','1.5 lb',NULL,1395.00,NULL,44,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(670,112,'2 lb','2 lb',NULL,1695.00,NULL,44,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(671,112,'2.5 lb','2.5 lb',NULL,1995.00,NULL,44,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(672,112,'3 lb','3 lb',NULL,2295.00,NULL,44,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(673,113,'0.5 kg','0.5 kg',NULL,880.00,NULL,45,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(674,113,'1 lb','1 lb',NULL,1130.00,NULL,45,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(675,113,'1.5 lb','1.5 lb',NULL,1430.00,NULL,45,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(676,113,'2 lb','2 lb',NULL,1730.00,NULL,45,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(677,113,'2.5 lb','2.5 lb',NULL,2030.00,NULL,45,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(678,113,'3 lb','3 lb',NULL,2330.00,NULL,45,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(679,114,'0.5 kg','0.5 kg',NULL,915.00,NULL,46,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(680,114,'1 lb','1 lb',NULL,1165.00,NULL,46,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(681,114,'1.5 lb','1.5 lb',NULL,1465.00,NULL,46,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(682,114,'2 lb','2 lb',NULL,1765.00,NULL,46,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(683,114,'2.5 lb','2.5 lb',NULL,2065.00,NULL,46,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(684,114,'3 lb','3 lb',NULL,2365.00,NULL,46,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(685,115,'0.5 kg','0.5 kg',NULL,950.00,NULL,47,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(686,115,'1 lb','1 lb',NULL,1200.00,NULL,47,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(687,115,'1.5 lb','1.5 lb',NULL,1500.00,NULL,47,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(688,115,'2 lb','2 lb',NULL,1800.00,NULL,47,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(689,115,'2.5 lb','2.5 lb',NULL,2100.00,NULL,47,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(690,115,'3 lb','3 lb',NULL,2400.00,NULL,47,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(691,116,'0.5 kg','0.5 kg',NULL,985.00,NULL,48,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(692,116,'1 lb','1 lb',NULL,1235.00,NULL,48,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(693,116,'1.5 lb','1.5 lb',NULL,1535.00,NULL,48,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(694,116,'2 lb','2 lb',NULL,1835.00,NULL,48,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(695,116,'2.5 lb','2.5 lb',NULL,2135.00,NULL,48,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(696,116,'3 lb','3 lb',NULL,2435.00,NULL,48,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(697,117,'0.5 kg','0.5 kg',NULL,1020.00,NULL,49,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(698,117,'1 lb','1 lb',NULL,1270.00,NULL,49,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(699,117,'1.5 lb','1.5 lb',NULL,1570.00,NULL,49,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(700,117,'2 lb','2 lb',NULL,1870.00,NULL,49,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(701,117,'2.5 lb','2.5 lb',NULL,2170.00,NULL,49,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(702,117,'3 lb','3 lb',NULL,2470.00,NULL,49,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(703,118,'0.5 kg','0.5 kg',NULL,1055.00,NULL,50,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(704,118,'1 lb','1 lb',NULL,1305.00,NULL,50,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(705,118,'1.5 lb','1.5 lb',NULL,1605.00,NULL,50,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(706,118,'2 lb','2 lb',NULL,1905.00,NULL,50,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(707,118,'2.5 lb','2.5 lb',NULL,2205.00,NULL,50,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(708,118,'3 lb','3 lb',NULL,2505.00,NULL,50,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(709,119,'0.5 kg','0.5 kg',NULL,1090.00,NULL,51,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(710,119,'1 lb','1 lb',NULL,1340.00,NULL,51,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(711,119,'1.5 lb','1.5 lb',NULL,1640.00,NULL,51,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(712,119,'2 lb','2 lb',NULL,1940.00,NULL,51,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(713,119,'2.5 lb','2.5 lb',NULL,2240.00,NULL,51,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(714,119,'3 lb','3 lb',NULL,2540.00,NULL,51,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(715,120,'0.5 kg','0.5 kg',NULL,600.00,NULL,12,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(716,120,'1 lb','1 lb',NULL,850.00,NULL,12,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(717,120,'1.5 lb','1.5 lb',NULL,1150.00,NULL,12,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(718,120,'2 lb','2 lb',NULL,1450.00,NULL,12,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(719,120,'2.5 lb','2.5 lb',NULL,1750.00,NULL,12,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(720,120,'3 lb','3 lb',NULL,2050.00,NULL,12,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(721,121,'0.5 kg','0.5 kg',NULL,635.00,NULL,13,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(722,121,'1 lb','1 lb',NULL,885.00,NULL,13,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(723,121,'1.5 lb','1.5 lb',NULL,1185.00,NULL,13,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(724,121,'2 lb','2 lb',NULL,1485.00,NULL,13,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(725,121,'2.5 lb','2.5 lb',NULL,1785.00,NULL,13,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(726,121,'3 lb','3 lb',NULL,2085.00,NULL,13,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(727,122,'0.5 kg','0.5 kg',NULL,670.00,NULL,14,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(728,122,'1 lb','1 lb',NULL,920.00,NULL,14,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(729,122,'1.5 lb','1.5 lb',NULL,1220.00,NULL,14,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(730,122,'2 lb','2 lb',NULL,1520.00,NULL,14,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(731,122,'2.5 lb','2.5 lb',NULL,1820.00,NULL,14,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(732,122,'3 lb','3 lb',NULL,2120.00,NULL,14,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(733,123,'0.5 kg','0.5 kg',NULL,705.00,NULL,15,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(734,123,'1 lb','1 lb',NULL,955.00,NULL,15,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(735,123,'1.5 lb','1.5 lb',NULL,1255.00,NULL,15,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(736,123,'2 lb','2 lb',NULL,1555.00,NULL,15,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(737,123,'2.5 lb','2.5 lb',NULL,1855.00,NULL,15,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(738,123,'3 lb','3 lb',NULL,2155.00,NULL,15,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(739,124,'0.5 kg','0.5 kg',NULL,740.00,NULL,16,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(740,124,'1 lb','1 lb',NULL,990.00,NULL,16,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(741,124,'1.5 lb','1.5 lb',NULL,1290.00,NULL,16,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(742,124,'2 lb','2 lb',NULL,1590.00,NULL,16,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(743,124,'2.5 lb','2.5 lb',NULL,1890.00,NULL,16,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(744,124,'3 lb','3 lb',NULL,2190.00,NULL,16,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(745,125,'0.5 kg','0.5 kg',NULL,775.00,NULL,17,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(746,125,'1 lb','1 lb',NULL,1025.00,NULL,17,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(747,125,'1.5 lb','1.5 lb',NULL,1325.00,NULL,17,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(748,125,'2 lb','2 lb',NULL,1625.00,NULL,17,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(749,125,'2.5 lb','2.5 lb',NULL,1925.00,NULL,17,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(750,125,'3 lb','3 lb',NULL,2225.00,NULL,17,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(751,126,'0.5 kg','0.5 kg',NULL,810.00,NULL,18,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(752,126,'1 lb','1 lb',NULL,1060.00,NULL,18,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(753,126,'1.5 lb','1.5 lb',NULL,1360.00,NULL,18,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(754,126,'2 lb','2 lb',NULL,1660.00,NULL,18,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(755,126,'2.5 lb','2.5 lb',NULL,1960.00,NULL,18,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(756,126,'3 lb','3 lb',NULL,2260.00,NULL,18,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(757,127,'0.5 kg','0.5 kg',NULL,845.00,NULL,19,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(758,127,'1 lb','1 lb',NULL,1095.00,NULL,19,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(759,127,'1.5 lb','1.5 lb',NULL,1395.00,NULL,19,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(760,127,'2 lb','2 lb',NULL,1695.00,NULL,19,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(761,127,'2.5 lb','2.5 lb',NULL,1995.00,NULL,19,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(762,127,'3 lb','3 lb',NULL,2295.00,NULL,19,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(763,128,'0.5 kg','0.5 kg',NULL,880.00,NULL,20,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(764,128,'1 lb','1 lb',NULL,1130.00,NULL,20,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(765,128,'1.5 lb','1.5 lb',NULL,1430.00,NULL,20,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(766,128,'2 lb','2 lb',NULL,1730.00,NULL,20,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(767,128,'2.5 lb','2.5 lb',NULL,2030.00,NULL,20,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(768,128,'3 lb','3 lb',NULL,2330.00,NULL,20,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(769,129,'0.5 kg','0.5 kg',NULL,915.00,NULL,21,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(770,129,'1 lb','1 lb',NULL,1165.00,NULL,21,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(771,129,'1.5 lb','1.5 lb',NULL,1465.00,NULL,21,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(772,129,'2 lb','2 lb',NULL,1765.00,NULL,21,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(773,129,'2.5 lb','2.5 lb',NULL,2065.00,NULL,21,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(774,129,'3 lb','3 lb',NULL,2365.00,NULL,21,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(775,130,'0.5 kg','0.5 kg',NULL,950.00,NULL,22,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(776,130,'1 lb','1 lb',NULL,1200.00,NULL,22,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(777,130,'1.5 lb','1.5 lb',NULL,1500.00,NULL,22,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(778,130,'2 lb','2 lb',NULL,1800.00,NULL,22,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(779,130,'2.5 lb','2.5 lb',NULL,2100.00,NULL,22,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(780,130,'3 lb','3 lb',NULL,2400.00,NULL,22,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(781,131,'0.5 kg','0.5 kg',NULL,985.00,NULL,23,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(782,131,'1 lb','1 lb',NULL,1235.00,NULL,23,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(783,131,'1.5 lb','1.5 lb',NULL,1535.00,NULL,23,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(784,131,'2 lb','2 lb',NULL,1835.00,NULL,23,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(785,131,'2.5 lb','2.5 lb',NULL,2135.00,NULL,23,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(786,131,'3 lb','3 lb',NULL,2435.00,NULL,23,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(787,132,'0.5 kg','0.5 kg',NULL,1020.00,NULL,24,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(788,132,'1 lb','1 lb',NULL,1270.00,NULL,24,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(789,132,'1.5 lb','1.5 lb',NULL,1570.00,NULL,24,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(790,132,'2 lb','2 lb',NULL,1870.00,NULL,24,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(791,132,'2.5 lb','2.5 lb',NULL,2170.00,NULL,24,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(792,132,'3 lb','3 lb',NULL,2470.00,NULL,24,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(793,133,'0.5 kg','0.5 kg',NULL,1055.00,NULL,25,NULL,1,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(794,133,'1 lb','1 lb',NULL,1305.00,NULL,25,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(795,133,'1.5 lb','1.5 lb',NULL,1605.00,NULL,25,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(796,133,'2 lb','2 lb',NULL,1905.00,NULL,25,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(797,133,'2.5 lb','2.5 lb',NULL,2205.00,NULL,25,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(798,133,'3 lb','3 lb',NULL,2505.00,NULL,25,NULL,0,1,'2026-05-19 04:31:15','2026-05-19 04:31:15'),(799,134,'0.5 kg','0.5 kg',NULL,1090.00,NULL,26,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(800,134,'1 lb','1 lb',NULL,1340.00,NULL,26,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(801,134,'1.5 lb','1.5 lb',NULL,1640.00,NULL,26,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(802,134,'2 lb','2 lb',NULL,1940.00,NULL,26,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(803,134,'2.5 lb','2.5 lb',NULL,2240.00,NULL,26,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(804,134,'3 lb','3 lb',NULL,2540.00,NULL,26,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(805,135,'0.5 kg','0.5 kg',NULL,600.00,NULL,27,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(806,135,'1 lb','1 lb',NULL,850.00,NULL,27,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(807,135,'1.5 lb','1.5 lb',NULL,1150.00,NULL,27,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(808,135,'2 lb','2 lb',NULL,1450.00,NULL,27,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(809,135,'2.5 lb','2.5 lb',NULL,1750.00,NULL,27,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(810,135,'3 lb','3 lb',NULL,2050.00,NULL,27,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(811,136,'0.5 kg','0.5 kg',NULL,635.00,NULL,28,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(812,136,'1 lb','1 lb',NULL,885.00,NULL,28,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(813,136,'1.5 lb','1.5 lb',NULL,1185.00,NULL,28,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(814,136,'2 lb','2 lb',NULL,1485.00,NULL,28,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(815,136,'2.5 lb','2.5 lb',NULL,1785.00,NULL,28,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(816,136,'3 lb','3 lb',NULL,2085.00,NULL,28,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(817,137,'0.5 kg','0.5 kg',NULL,670.00,NULL,29,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(818,137,'1 lb','1 lb',NULL,920.00,NULL,29,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(819,137,'1.5 lb','1.5 lb',NULL,1220.00,NULL,29,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(820,137,'2 lb','2 lb',NULL,1520.00,NULL,29,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(821,137,'2.5 lb','2.5 lb',NULL,1820.00,NULL,29,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(822,137,'3 lb','3 lb',NULL,2120.00,NULL,29,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(823,138,'0.5 kg','0.5 kg',NULL,705.00,NULL,30,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(824,138,'1 lb','1 lb',NULL,955.00,NULL,30,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(825,138,'1.5 lb','1.5 lb',NULL,1255.00,NULL,30,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(826,138,'2 lb','2 lb',NULL,1555.00,NULL,30,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(827,138,'2.5 lb','2.5 lb',NULL,1855.00,NULL,30,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(828,138,'3 lb','3 lb',NULL,2155.00,NULL,30,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(829,139,'0.5 kg','0.5 kg',NULL,740.00,NULL,31,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(830,139,'1 lb','1 lb',NULL,990.00,NULL,31,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(831,139,'1.5 lb','1.5 lb',NULL,1290.00,NULL,31,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(832,139,'2 lb','2 lb',NULL,1590.00,NULL,31,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(833,139,'2.5 lb','2.5 lb',NULL,1890.00,NULL,31,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(834,139,'3 lb','3 lb',NULL,2190.00,NULL,31,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(835,140,'0.5 kg','0.5 kg',NULL,775.00,NULL,32,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(836,140,'1 lb','1 lb',NULL,1025.00,NULL,32,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(837,140,'1.5 lb','1.5 lb',NULL,1325.00,NULL,32,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(838,140,'2 lb','2 lb',NULL,1625.00,NULL,32,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(839,140,'2.5 lb','2.5 lb',NULL,1925.00,NULL,32,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(840,140,'3 lb','3 lb',NULL,2225.00,NULL,32,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(841,141,'0.5 kg','0.5 kg',NULL,810.00,NULL,33,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(842,141,'1 lb','1 lb',NULL,1060.00,NULL,33,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(843,141,'1.5 lb','1.5 lb',NULL,1360.00,NULL,33,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(844,141,'2 lb','2 lb',NULL,1660.00,NULL,33,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(845,141,'2.5 lb','2.5 lb',NULL,1960.00,NULL,33,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(846,141,'3 lb','3 lb',NULL,2260.00,NULL,33,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(847,142,'0.5 kg','0.5 kg',NULL,845.00,NULL,34,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(848,142,'1 lb','1 lb',NULL,1095.00,NULL,34,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(849,142,'1.5 lb','1.5 lb',NULL,1395.00,NULL,34,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(850,142,'2 lb','2 lb',NULL,1695.00,NULL,34,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(851,142,'2.5 lb','2.5 lb',NULL,1995.00,NULL,34,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(852,142,'3 lb','3 lb',NULL,2295.00,NULL,34,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(853,143,'0.5 kg','0.5 kg',NULL,880.00,NULL,35,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(854,143,'1 lb','1 lb',NULL,1130.00,NULL,35,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(855,143,'1.5 lb','1.5 lb',NULL,1430.00,NULL,35,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(856,143,'2 lb','2 lb',NULL,1730.00,NULL,35,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(857,143,'2.5 lb','2.5 lb',NULL,2030.00,NULL,35,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(858,143,'3 lb','3 lb',NULL,2330.00,NULL,35,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(859,144,'0.5 kg','0.5 kg',NULL,915.00,NULL,36,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(860,144,'1 lb','1 lb',NULL,1165.00,NULL,36,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(861,144,'1.5 lb','1.5 lb',NULL,1465.00,NULL,36,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(862,144,'2 lb','2 lb',NULL,1765.00,NULL,36,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(863,144,'2.5 lb','2.5 lb',NULL,2065.00,NULL,36,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(864,144,'3 lb','3 lb',NULL,2365.00,NULL,36,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(865,145,'0.5 kg','0.5 kg',NULL,950.00,NULL,37,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(866,145,'1 lb','1 lb',NULL,1200.00,NULL,37,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(867,145,'1.5 lb','1.5 lb',NULL,1500.00,NULL,37,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(868,145,'2 lb','2 lb',NULL,1800.00,NULL,37,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(869,145,'2.5 lb','2.5 lb',NULL,2100.00,NULL,37,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(870,145,'3 lb','3 lb',NULL,2400.00,NULL,37,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(871,146,'0.5 kg','0.5 kg',NULL,985.00,NULL,38,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(872,146,'1 lb','1 lb',NULL,1235.00,NULL,38,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(873,146,'1.5 lb','1.5 lb',NULL,1535.00,NULL,38,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(874,146,'2 lb','2 lb',NULL,1835.00,NULL,38,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(875,146,'2.5 lb','2.5 lb',NULL,2135.00,NULL,38,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(876,146,'3 lb','3 lb',NULL,2435.00,NULL,38,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(877,147,'0.5 kg','0.5 kg',NULL,1020.00,NULL,39,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(878,147,'1 lb','1 lb',NULL,1270.00,NULL,39,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(879,147,'1.5 lb','1.5 lb',NULL,1570.00,NULL,39,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(880,147,'2 lb','2 lb',NULL,1870.00,NULL,39,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(881,147,'2.5 lb','2.5 lb',NULL,2170.00,NULL,39,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(882,147,'3 lb','3 lb',NULL,2470.00,NULL,39,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(883,148,'0.5 kg','0.5 kg',NULL,1055.00,NULL,40,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(884,148,'1 lb','1 lb',NULL,1305.00,NULL,40,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(885,148,'1.5 lb','1.5 lb',NULL,1605.00,NULL,40,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(886,148,'2 lb','2 lb',NULL,1905.00,NULL,40,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(887,148,'2.5 lb','2.5 lb',NULL,2205.00,NULL,40,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(888,148,'3 lb','3 lb',NULL,2505.00,NULL,40,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(889,149,'0.5 kg','0.5 kg',NULL,1090.00,NULL,41,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(890,149,'1 lb','1 lb',NULL,1340.00,NULL,41,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(891,149,'1.5 lb','1.5 lb',NULL,1640.00,NULL,41,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(892,149,'2 lb','2 lb',NULL,1940.00,NULL,41,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(893,149,'2.5 lb','2.5 lb',NULL,2240.00,NULL,41,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(894,149,'3 lb','3 lb',NULL,2540.00,NULL,41,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(895,150,'0.5 kg','0.5 kg',NULL,600.00,NULL,42,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(896,150,'1 lb','1 lb',NULL,850.00,NULL,42,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(897,150,'1.5 lb','1.5 lb',NULL,1150.00,NULL,42,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(898,150,'2 lb','2 lb',NULL,1450.00,NULL,42,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(899,150,'2.5 lb','2.5 lb',NULL,1750.00,NULL,42,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(900,150,'3 lb','3 lb',NULL,2050.00,NULL,42,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(901,151,'0.5 kg','0.5 kg',NULL,635.00,NULL,43,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(902,151,'1 lb','1 lb',NULL,885.00,NULL,43,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(903,151,'1.5 lb','1.5 lb',NULL,1185.00,NULL,43,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(904,151,'2 lb','2 lb',NULL,1485.00,NULL,43,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(905,151,'2.5 lb','2.5 lb',NULL,1785.00,NULL,43,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(906,151,'3 lb','3 lb',NULL,2085.00,NULL,43,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(907,152,'0.5 kg','0.5 kg',NULL,670.00,NULL,44,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(908,152,'1 lb','1 lb',NULL,920.00,NULL,44,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(909,152,'1.5 lb','1.5 lb',NULL,1220.00,NULL,44,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(910,152,'2 lb','2 lb',NULL,1520.00,NULL,44,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(911,152,'2.5 lb','2.5 lb',NULL,1820.00,NULL,44,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(912,152,'3 lb','3 lb',NULL,2120.00,NULL,44,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(913,153,'0.5 kg','0.5 kg',NULL,705.00,NULL,45,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(914,153,'1 lb','1 lb',NULL,955.00,NULL,45,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(915,153,'1.5 lb','1.5 lb',NULL,1255.00,NULL,45,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(916,153,'2 lb','2 lb',NULL,1555.00,NULL,45,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(917,153,'2.5 lb','2.5 lb',NULL,1855.00,NULL,45,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(918,153,'3 lb','3 lb',NULL,2155.00,NULL,45,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(919,154,'0.5 kg','0.5 kg',NULL,740.00,NULL,46,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(920,154,'1 lb','1 lb',NULL,990.00,NULL,46,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(921,154,'1.5 lb','1.5 lb',NULL,1290.00,NULL,46,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(922,154,'2 lb','2 lb',NULL,1590.00,NULL,46,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(923,154,'2.5 lb','2.5 lb',NULL,1890.00,NULL,46,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(924,154,'3 lb','3 lb',NULL,2190.00,NULL,46,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(925,155,'0.5 kg','0.5 kg',NULL,775.00,NULL,47,NULL,1,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(926,155,'1 lb','1 lb',NULL,1025.00,NULL,47,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(927,155,'1.5 lb','1.5 lb',NULL,1325.00,NULL,47,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(928,155,'2 lb','2 lb',NULL,1625.00,NULL,47,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(929,155,'2.5 lb','2.5 lb',NULL,1925.00,NULL,47,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(930,155,'3 lb','3 lb',NULL,2225.00,NULL,47,NULL,0,1,'2026-05-19 04:31:16','2026-05-19 04:31:16'),(931,156,'0.5 kg','0.5 kg',NULL,810.00,NULL,48,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(932,156,'1 lb','1 lb',NULL,1060.00,NULL,48,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(933,156,'1.5 lb','1.5 lb',NULL,1360.00,NULL,48,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(934,156,'2 lb','2 lb',NULL,1660.00,NULL,48,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(935,156,'2.5 lb','2.5 lb',NULL,1960.00,NULL,48,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(936,156,'3 lb','3 lb',NULL,2260.00,NULL,48,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(937,157,'0.5 kg','0.5 kg',NULL,845.00,NULL,49,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(938,157,'1 lb','1 lb',NULL,1095.00,NULL,49,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(939,157,'1.5 lb','1.5 lb',NULL,1395.00,NULL,49,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(940,157,'2 lb','2 lb',NULL,1695.00,NULL,49,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(941,157,'2.5 lb','2.5 lb',NULL,1995.00,NULL,49,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(942,157,'3 lb','3 lb',NULL,2295.00,NULL,49,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(943,158,'0.5 kg','0.5 kg',NULL,880.00,NULL,50,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(944,158,'1 lb','1 lb',NULL,1130.00,NULL,50,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(945,158,'1.5 lb','1.5 lb',NULL,1430.00,NULL,50,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(946,158,'2 lb','2 lb',NULL,1730.00,NULL,50,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(947,158,'2.5 lb','2.5 lb',NULL,2030.00,NULL,50,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(948,158,'3 lb','3 lb',NULL,2330.00,NULL,50,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(949,159,'0.5 kg','0.5 kg',NULL,915.00,NULL,51,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(950,159,'1 lb','1 lb',NULL,1165.00,NULL,51,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(951,159,'1.5 lb','1.5 lb',NULL,1465.00,NULL,51,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(952,159,'2 lb','2 lb',NULL,1765.00,NULL,51,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(953,159,'2.5 lb','2.5 lb',NULL,2065.00,NULL,51,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(954,159,'3 lb','3 lb',NULL,2365.00,NULL,51,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(955,160,'0.5 kg','0.5 kg',NULL,950.00,NULL,12,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(956,160,'1 lb','1 lb',NULL,1200.00,NULL,12,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(957,160,'1.5 lb','1.5 lb',NULL,1500.00,NULL,12,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(958,160,'2 lb','2 lb',NULL,1800.00,NULL,12,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(959,160,'2.5 lb','2.5 lb',NULL,2100.00,NULL,12,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(960,160,'3 lb','3 lb',NULL,2400.00,NULL,12,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(961,161,'0.5 kg','0.5 kg',NULL,985.00,NULL,13,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(962,161,'1 lb','1 lb',NULL,1235.00,NULL,13,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(963,161,'1.5 lb','1.5 lb',NULL,1535.00,NULL,13,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(964,161,'2 lb','2 lb',NULL,1835.00,NULL,13,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(965,161,'2.5 lb','2.5 lb',NULL,2135.00,NULL,13,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(966,161,'3 lb','3 lb',NULL,2435.00,NULL,13,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(967,162,'0.5 kg','0.5 kg',NULL,1020.00,NULL,14,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(968,162,'1 lb','1 lb',NULL,1270.00,NULL,14,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(969,162,'1.5 lb','1.5 lb',NULL,1570.00,NULL,14,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(970,162,'2 lb','2 lb',NULL,1870.00,NULL,14,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(971,162,'2.5 lb','2.5 lb',NULL,2170.00,NULL,14,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(972,162,'3 lb','3 lb',NULL,2470.00,NULL,14,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(973,163,'0.5 kg','0.5 kg',NULL,1055.00,NULL,15,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(974,163,'1 lb','1 lb',NULL,1305.00,NULL,15,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(975,163,'1.5 lb','1.5 lb',NULL,1605.00,NULL,15,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(976,163,'2 lb','2 lb',NULL,1905.00,NULL,15,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(977,163,'2.5 lb','2.5 lb',NULL,2205.00,NULL,15,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(978,163,'3 lb','3 lb',NULL,2505.00,NULL,15,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(979,164,'0.5 kg','0.5 kg',NULL,1090.00,NULL,16,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(980,164,'1 lb','1 lb',NULL,1340.00,NULL,16,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(981,164,'1.5 lb','1.5 lb',NULL,1640.00,NULL,16,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(982,164,'2 lb','2 lb',NULL,1940.00,NULL,16,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(983,164,'2.5 lb','2.5 lb',NULL,2240.00,NULL,16,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(984,164,'3 lb','3 lb',NULL,2540.00,NULL,16,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(985,165,'0.5 kg','0.5 kg',NULL,600.00,NULL,17,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(986,165,'1 lb','1 lb',NULL,850.00,NULL,17,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(987,165,'1.5 lb','1.5 lb',NULL,1150.00,NULL,17,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(988,165,'2 lb','2 lb',NULL,1450.00,NULL,17,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(989,165,'2.5 lb','2.5 lb',NULL,1750.00,NULL,17,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(990,165,'3 lb','3 lb',NULL,2050.00,NULL,17,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(991,166,'0.5 kg','0.5 kg',NULL,635.00,NULL,18,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(992,166,'1 lb','1 lb',NULL,885.00,NULL,18,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(993,166,'1.5 lb','1.5 lb',NULL,1185.00,NULL,18,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(994,166,'2 lb','2 lb',NULL,1485.00,NULL,18,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(995,166,'2.5 lb','2.5 lb',NULL,1785.00,NULL,18,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(996,166,'3 lb','3 lb',NULL,2085.00,NULL,18,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(997,167,'0.5 kg','0.5 kg',NULL,670.00,NULL,19,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(998,167,'1 lb','1 lb',NULL,920.00,NULL,19,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(999,167,'1.5 lb','1.5 lb',NULL,1220.00,NULL,19,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1000,167,'2 lb','2 lb',NULL,1520.00,NULL,19,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1001,167,'2.5 lb','2.5 lb',NULL,1820.00,NULL,19,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1002,167,'3 lb','3 lb',NULL,2120.00,NULL,19,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1003,168,'0.5 kg','0.5 kg',NULL,705.00,NULL,20,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1004,168,'1 lb','1 lb',NULL,955.00,NULL,20,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1005,168,'1.5 lb','1.5 lb',NULL,1255.00,NULL,20,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1006,168,'2 lb','2 lb',NULL,1555.00,NULL,20,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1007,168,'2.5 lb','2.5 lb',NULL,1855.00,NULL,20,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1008,168,'3 lb','3 lb',NULL,2155.00,NULL,20,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1009,169,'0.5 kg','0.5 kg',NULL,740.00,NULL,21,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1010,169,'1 lb','1 lb',NULL,990.00,NULL,21,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1011,169,'1.5 lb','1.5 lb',NULL,1290.00,NULL,21,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1012,169,'2 lb','2 lb',NULL,1590.00,NULL,21,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1013,169,'2.5 lb','2.5 lb',NULL,1890.00,NULL,21,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1014,169,'3 lb','3 lb',NULL,2190.00,NULL,21,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1015,170,'0.5 kg','0.5 kg',NULL,775.00,NULL,22,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1016,170,'1 lb','1 lb',NULL,1025.00,NULL,22,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1017,170,'1.5 lb','1.5 lb',NULL,1325.00,NULL,22,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1018,170,'2 lb','2 lb',NULL,1625.00,NULL,22,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1019,170,'2.5 lb','2.5 lb',NULL,1925.00,NULL,22,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1020,170,'3 lb','3 lb',NULL,2225.00,NULL,22,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1021,171,'0.5 kg','0.5 kg',NULL,810.00,NULL,23,NULL,1,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1022,171,'1 lb','1 lb',NULL,1060.00,NULL,23,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1023,171,'1.5 lb','1.5 lb',NULL,1360.00,NULL,23,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1024,171,'2 lb','2 lb',NULL,1660.00,NULL,23,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1025,171,'2.5 lb','2.5 lb',NULL,1960.00,NULL,23,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1026,171,'3 lb','3 lb',NULL,2260.00,NULL,23,NULL,0,1,'2026-05-19 04:31:17','2026-05-19 04:31:17'),(1027,172,'0.5 kg','0.5 kg',NULL,845.00,NULL,24,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1028,172,'1 lb','1 lb',NULL,1095.00,NULL,24,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1029,172,'1.5 lb','1.5 lb',NULL,1395.00,NULL,24,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1030,172,'2 lb','2 lb',NULL,1695.00,NULL,24,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1031,172,'2.5 lb','2.5 lb',NULL,1995.00,NULL,24,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1032,172,'3 lb','3 lb',NULL,2295.00,NULL,24,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1033,173,'0.5 kg','0.5 kg',NULL,880.00,NULL,25,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1034,173,'1 lb','1 lb',NULL,1130.00,NULL,25,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1035,173,'1.5 lb','1.5 lb',NULL,1430.00,NULL,25,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1036,173,'2 lb','2 lb',NULL,1730.00,NULL,25,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1037,173,'2.5 lb','2.5 lb',NULL,2030.00,NULL,25,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1038,173,'3 lb','3 lb',NULL,2330.00,NULL,25,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1039,174,'0.5 kg','0.5 kg',NULL,915.00,NULL,26,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1040,174,'1 lb','1 lb',NULL,1165.00,NULL,26,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1041,174,'1.5 lb','1.5 lb',NULL,1465.00,NULL,26,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1042,174,'2 lb','2 lb',NULL,1765.00,NULL,26,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1043,174,'2.5 lb','2.5 lb',NULL,2065.00,NULL,26,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1044,174,'3 lb','3 lb',NULL,2365.00,NULL,26,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1045,175,'0.5 kg','0.5 kg',NULL,950.00,NULL,27,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1046,175,'1 lb','1 lb',NULL,1200.00,NULL,27,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1047,175,'1.5 lb','1.5 lb',NULL,1500.00,NULL,27,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1048,175,'2 lb','2 lb',NULL,1800.00,NULL,27,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1049,175,'2.5 lb','2.5 lb',NULL,2100.00,NULL,27,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1050,175,'3 lb','3 lb',NULL,2400.00,NULL,27,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1051,176,'0.5 kg','0.5 kg',NULL,985.00,NULL,28,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1052,176,'1 lb','1 lb',NULL,1235.00,NULL,28,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1053,176,'1.5 lb','1.5 lb',NULL,1535.00,NULL,28,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1054,176,'2 lb','2 lb',NULL,1835.00,NULL,28,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1055,176,'2.5 lb','2.5 lb',NULL,2135.00,NULL,28,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1056,176,'3 lb','3 lb',NULL,2435.00,NULL,28,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1057,177,'0.5 kg','0.5 kg',NULL,1020.00,NULL,29,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1058,177,'1 lb','1 lb',NULL,1270.00,NULL,29,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1059,177,'1.5 lb','1.5 lb',NULL,1570.00,NULL,29,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1060,177,'2 lb','2 lb',NULL,1870.00,NULL,29,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1061,177,'2.5 lb','2.5 lb',NULL,2170.00,NULL,29,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1062,177,'3 lb','3 lb',NULL,2470.00,NULL,29,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1063,178,'0.5 kg','0.5 kg',NULL,1055.00,NULL,30,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1064,178,'1 lb','1 lb',NULL,1305.00,NULL,30,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1065,178,'1.5 lb','1.5 lb',NULL,1605.00,NULL,30,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1066,178,'2 lb','2 lb',NULL,1905.00,NULL,30,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1067,178,'2.5 lb','2.5 lb',NULL,2205.00,NULL,30,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1068,178,'3 lb','3 lb',NULL,2505.00,NULL,30,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1069,179,'0.5 kg','0.5 kg',NULL,1090.00,NULL,31,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1070,179,'1 lb','1 lb',NULL,1340.00,NULL,31,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1071,179,'1.5 lb','1.5 lb',NULL,1640.00,NULL,31,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1072,179,'2 lb','2 lb',NULL,1940.00,NULL,31,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1073,179,'2.5 lb','2.5 lb',NULL,2240.00,NULL,31,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1074,179,'3 lb','3 lb',NULL,2540.00,NULL,31,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1075,180,'0.5 kg','0.5 kg',NULL,600.00,NULL,32,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1076,180,'1 lb','1 lb',NULL,850.00,NULL,32,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1077,180,'1.5 lb','1.5 lb',NULL,1150.00,NULL,32,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1078,180,'2 lb','2 lb',NULL,1450.00,NULL,32,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1079,180,'2.5 lb','2.5 lb',NULL,1750.00,NULL,32,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1080,180,'3 lb','3 lb',NULL,2050.00,NULL,32,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1081,181,'0.5 kg','0.5 kg',NULL,635.00,NULL,33,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1082,181,'1 lb','1 lb',NULL,885.00,NULL,33,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1083,181,'1.5 lb','1.5 lb',NULL,1185.00,NULL,33,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1084,181,'2 lb','2 lb',NULL,1485.00,NULL,33,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1085,181,'2.5 lb','2.5 lb',NULL,1785.00,NULL,33,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1086,181,'3 lb','3 lb',NULL,2085.00,NULL,33,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1087,182,'0.5 kg','0.5 kg',NULL,670.00,NULL,34,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1088,182,'1 lb','1 lb',NULL,920.00,NULL,34,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1089,182,'1.5 lb','1.5 lb',NULL,1220.00,NULL,34,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1090,182,'2 lb','2 lb',NULL,1520.00,NULL,34,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1091,182,'2.5 lb','2.5 lb',NULL,1820.00,NULL,34,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1092,182,'3 lb','3 lb',NULL,2120.00,NULL,34,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1093,183,'0.5 kg','0.5 kg',NULL,705.00,NULL,35,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1094,183,'1 lb','1 lb',NULL,955.00,NULL,35,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1095,183,'1.5 lb','1.5 lb',NULL,1255.00,NULL,35,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1096,183,'2 lb','2 lb',NULL,1555.00,NULL,35,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1097,183,'2.5 lb','2.5 lb',NULL,1855.00,NULL,35,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1098,183,'3 lb','3 lb',NULL,2155.00,NULL,35,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1099,184,'0.5 kg','0.5 kg',NULL,740.00,NULL,36,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1100,184,'1 lb','1 lb',NULL,990.00,NULL,36,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1101,184,'1.5 lb','1.5 lb',NULL,1290.00,NULL,36,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1102,184,'2 lb','2 lb',NULL,1590.00,NULL,36,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1103,184,'2.5 lb','2.5 lb',NULL,1890.00,NULL,36,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1104,184,'3 lb','3 lb',NULL,2190.00,NULL,36,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1105,185,'0.5 kg','0.5 kg',NULL,775.00,NULL,37,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1106,185,'1 lb','1 lb',NULL,1025.00,NULL,37,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1107,185,'1.5 lb','1.5 lb',NULL,1325.00,NULL,37,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1108,185,'2 lb','2 lb',NULL,1625.00,NULL,37,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1109,185,'2.5 lb','2.5 lb',NULL,1925.00,NULL,37,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1110,185,'3 lb','3 lb',NULL,2225.00,NULL,37,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1111,186,'0.5 kg','0.5 kg',NULL,810.00,NULL,38,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1112,186,'1 lb','1 lb',NULL,1060.00,NULL,38,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1113,186,'1.5 lb','1.5 lb',NULL,1360.00,NULL,38,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1114,186,'2 lb','2 lb',NULL,1660.00,NULL,38,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1115,186,'2.5 lb','2.5 lb',NULL,1960.00,NULL,38,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1116,186,'3 lb','3 lb',NULL,2260.00,NULL,38,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1117,187,'0.5 kg','0.5 kg',NULL,845.00,NULL,39,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1118,187,'1 lb','1 lb',NULL,1095.00,NULL,39,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1119,187,'1.5 lb','1.5 lb',NULL,1395.00,NULL,39,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1120,187,'2 lb','2 lb',NULL,1695.00,NULL,39,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1121,187,'2.5 lb','2.5 lb',NULL,1995.00,NULL,39,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1122,187,'3 lb','3 lb',NULL,2295.00,NULL,39,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1123,188,'0.5 kg','0.5 kg',NULL,880.00,NULL,40,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1124,188,'1 lb','1 lb',NULL,1130.00,NULL,40,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1125,188,'1.5 lb','1.5 lb',NULL,1430.00,NULL,40,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1126,188,'2 lb','2 lb',NULL,1730.00,NULL,40,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1127,188,'2.5 lb','2.5 lb',NULL,2030.00,NULL,40,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1128,188,'3 lb','3 lb',NULL,2330.00,NULL,40,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1129,189,'0.5 kg','0.5 kg',NULL,915.00,NULL,41,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1130,189,'1 lb','1 lb',NULL,1165.00,NULL,41,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1131,189,'1.5 lb','1.5 lb',NULL,1465.00,NULL,41,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1132,189,'2 lb','2 lb',NULL,1765.00,NULL,41,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1133,189,'2.5 lb','2.5 lb',NULL,2065.00,NULL,41,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1134,189,'3 lb','3 lb',NULL,2365.00,NULL,41,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1135,190,'0.5 kg','0.5 kg',NULL,950.00,NULL,42,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1136,190,'1 lb','1 lb',NULL,1200.00,NULL,42,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1137,190,'1.5 lb','1.5 lb',NULL,1500.00,NULL,42,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1138,190,'2 lb','2 lb',NULL,1800.00,NULL,42,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1139,190,'2.5 lb','2.5 lb',NULL,2100.00,NULL,42,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1140,190,'3 lb','3 lb',NULL,2400.00,NULL,42,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1141,191,'0.5 kg','0.5 kg',NULL,985.00,NULL,43,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1142,191,'1 lb','1 lb',NULL,1235.00,NULL,43,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1143,191,'1.5 lb','1.5 lb',NULL,1535.00,NULL,43,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1144,191,'2 lb','2 lb',NULL,1835.00,NULL,43,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1145,191,'2.5 lb','2.5 lb',NULL,2135.00,NULL,43,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1146,191,'3 lb','3 lb',NULL,2435.00,NULL,43,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1147,192,'0.5 kg','0.5 kg',NULL,1020.00,NULL,44,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1148,192,'1 lb','1 lb',NULL,1270.00,NULL,44,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1149,192,'1.5 lb','1.5 lb',NULL,1570.00,NULL,44,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1150,192,'2 lb','2 lb',NULL,1870.00,NULL,44,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1151,192,'2.5 lb','2.5 lb',NULL,2170.00,NULL,44,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1152,192,'3 lb','3 lb',NULL,2470.00,NULL,44,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1153,193,'0.5 kg','0.5 kg',NULL,1055.00,NULL,45,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1154,193,'1 lb','1 lb',NULL,1305.00,NULL,45,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1155,193,'1.5 lb','1.5 lb',NULL,1605.00,NULL,45,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1156,193,'2 lb','2 lb',NULL,1905.00,NULL,45,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1157,193,'2.5 lb','2.5 lb',NULL,2205.00,NULL,45,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1158,193,'3 lb','3 lb',NULL,2505.00,NULL,45,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1159,194,'0.5 kg','0.5 kg',NULL,1090.00,NULL,46,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1160,194,'1 lb','1 lb',NULL,1340.00,NULL,46,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1161,194,'1.5 lb','1.5 lb',NULL,1640.00,NULL,46,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1162,194,'2 lb','2 lb',NULL,1940.00,NULL,46,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1163,194,'2.5 lb','2.5 lb',NULL,2240.00,NULL,46,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1164,194,'3 lb','3 lb',NULL,2540.00,NULL,46,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1165,195,'0.5 kg','0.5 kg',NULL,600.00,NULL,47,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1166,195,'1 lb','1 lb',NULL,850.00,NULL,47,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1167,195,'1.5 lb','1.5 lb',NULL,1150.00,NULL,47,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1168,195,'2 lb','2 lb',NULL,1450.00,NULL,47,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1169,195,'2.5 lb','2.5 lb',NULL,1750.00,NULL,47,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1170,195,'3 lb','3 lb',NULL,2050.00,NULL,47,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1171,196,'0.5 kg','0.5 kg',NULL,635.00,NULL,48,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1172,196,'1 lb','1 lb',NULL,885.00,NULL,48,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1173,196,'1.5 lb','1.5 lb',NULL,1185.00,NULL,48,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1174,196,'2 lb','2 lb',NULL,1485.00,NULL,48,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1175,196,'2.5 lb','2.5 lb',NULL,1785.00,NULL,48,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1176,196,'3 lb','3 lb',NULL,2085.00,NULL,48,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1177,197,'0.5 kg','0.5 kg',NULL,670.00,NULL,49,NULL,1,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1178,197,'1 lb','1 lb',NULL,920.00,NULL,49,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1179,197,'1.5 lb','1.5 lb',NULL,1220.00,NULL,49,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1180,197,'2 lb','2 lb',NULL,1520.00,NULL,49,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1181,197,'2.5 lb','2.5 lb',NULL,1820.00,NULL,49,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1182,197,'3 lb','3 lb',NULL,2120.00,NULL,49,NULL,0,1,'2026-05-19 04:31:18','2026-05-19 04:31:18'),(1183,198,'0.5 kg','0.5 kg',NULL,705.00,NULL,50,NULL,1,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1184,198,'1 lb','1 lb',NULL,955.00,NULL,50,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1185,198,'1.5 lb','1.5 lb',NULL,1255.00,NULL,50,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1186,198,'2 lb','2 lb',NULL,1555.00,NULL,50,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1187,198,'2.5 lb','2.5 lb',NULL,1855.00,NULL,50,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1188,198,'3 lb','3 lb',NULL,2155.00,NULL,50,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1189,199,'0.5 kg','0.5 kg',NULL,740.00,NULL,51,NULL,1,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1190,199,'1 lb','1 lb',NULL,990.00,NULL,51,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1191,199,'1.5 lb','1.5 lb',NULL,1290.00,NULL,51,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1192,199,'2 lb','2 lb',NULL,1590.00,NULL,51,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1193,199,'2.5 lb','2.5 lb',NULL,1890.00,NULL,51,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1194,199,'3 lb','3 lb',NULL,2190.00,NULL,51,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1195,200,'0.5 kg','0.5 kg',NULL,775.00,NULL,12,NULL,1,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1196,200,'1 lb','1 lb',NULL,1025.00,NULL,12,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1197,200,'1.5 lb','1.5 lb',NULL,1325.00,NULL,12,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1198,200,'2 lb','2 lb',NULL,1625.00,NULL,12,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1199,200,'2.5 lb','2.5 lb',NULL,1925.00,NULL,12,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19'),(1200,200,'3 lb','3 lb',NULL,2225.00,NULL,12,NULL,0,1,'2026-05-19 04:31:19','2026-05-19 04:31:19');
/*!40000 ALTER TABLE `product_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(180) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `short_description` varchar(280) NOT NULL,
  `long_description` text NOT NULL,
  `flavour_notes` text,
  `texture_notes` text,
  `ingredients_summary` text,
  `packaging_note` text,
  `topper_note` text,
  `sku` varchar(80) NOT NULL,
  `collection_category_id` bigint unsigned NOT NULL,
  `subcategory_id` bigint unsigned DEFAULT NULL,
  `child_category_id` bigint unsigned DEFAULT NULL,
  `occasion_tag` varchar(100) DEFAULT NULL,
  `dietary_tag` enum('regular','eggless','vegan','sugar_free','healthy') NOT NULL DEFAULT 'regular',
  `is_veg` tinyint(1) NOT NULL DEFAULT '1',
  `availability_status` enum('in_stock','out_of_stock','preorder','draft') NOT NULL DEFAULT 'in_stock',
  `lead_time_hours` int NOT NULL DEFAULT '24',
  `customisation_note` text,
  `delivery_eligible` tinyint(1) NOT NULL DEFAULT '1',
  `pickup_eligible` tinyint(1) NOT NULL DEFAULT '1',
  `featured_image` varchar(255) DEFAULT NULL,
  `starting_price` decimal(10,2) NOT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int NOT NULL DEFAULT '0',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `is_bestseller` tinyint(1) NOT NULL DEFAULT '0',
  `seo_title` varchar(190) DEFAULT NULL,
  `seo_description` varchar(260) DEFAULT NULL,
  `rating_average` decimal(3,2) NOT NULL DEFAULT '0.00',
  `review_count` int NOT NULL DEFAULT '0',
  `is_b2b_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `b2b_minimum_quantity` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `is_chef_special` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `sku` (`sku`),
  KEY `idx_products_slug` (`slug`),
  KEY `idx_products_sku` (`sku`),
  KEY `idx_products_collection` (`collection_category_id`),
  KEY `idx_products_subcategory` (`subcategory_id`),
  KEY `idx_products_child_category` (`child_category_id`),
  KEY `idx_products_occasion` (`occasion_tag`),
  KEY `idx_products_featured` (`is_featured`),
  KEY `idx_products_bestseller` (`is_bestseller`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`collection_category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`subcategory_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `products_ibfk_3` FOREIGN KEY (`child_category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'Chocolate Opera Signature Cake 1','chocolate-opera-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0001',1,2,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',885.00,885.00,NULL,13,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(2,'Chocolate Opera Signature Cake 2','chocolate-opera-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0002',1,2,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',920.00,920.00,NULL,14,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(3,'Chocolate Opera Signature Cake 3','chocolate-opera-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0003',1,2,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',955.00,955.00,NULL,15,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(4,'Chocolate Opera Signature Cake 4','chocolate-opera-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0004',1,2,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',990.00,990.00,910.00,16,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(5,'Chocolate Opera Signature Cake 5','chocolate-opera-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0005',1,2,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1025.00,1025.00,NULL,17,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(6,'Coffee Opera Signature Cake 1','coffee-opera-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0006',1,3,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1060.00,1060.00,NULL,18,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(7,'Coffee Opera Signature Cake 2','coffee-opera-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0007',1,3,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1095.00,1095.00,NULL,19,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(8,'Coffee Opera Signature Cake 3','coffee-opera-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0008',1,3,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1130.00,1130.00,1050.00,20,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(9,'Coffee Opera Signature Cake 4','coffee-opera-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0009',1,3,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1165.00,1165.00,NULL,21,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(10,'Coffee Opera Signature Cake 5','coffee-opera-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0010',1,3,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1200.00,1200.00,NULL,22,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(11,'Fruit Opera Signature Cake 1','fruit-opera-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0011',1,4,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1235.00,1235.00,NULL,23,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(12,'Fruit Opera Signature Cake 2','fruit-opera-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0012',1,4,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1270.00,1270.00,1190.00,24,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(13,'Fruit Opera Signature Cake 3','fruit-opera-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0013',1,4,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1305.00,1305.00,NULL,25,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(14,'Fruit Opera Signature Cake 4','fruit-opera-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0014',1,4,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1340.00,1340.00,NULL,26,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(15,'Fruit Opera Signature Cake 5','fruit-opera-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0015',1,4,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',850.00,850.00,NULL,27,1,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(16,'Signature Opera Signature Cake 1','signature-opera-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0016',1,5,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',885.00,885.00,805.00,28,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(17,'Signature Opera Signature Cake 2','signature-opera-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0017',1,5,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',920.00,920.00,NULL,29,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(18,'Signature Opera Signature Cake 3','signature-opera-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0018',1,5,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',955.00,955.00,NULL,30,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(19,'Signature Opera Signature Cake 4','signature-opera-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0019',1,5,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',990.00,990.00,NULL,31,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(20,'Signature Opera Signature Cake 5','signature-opera-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0020',1,5,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1025.00,1025.00,945.00,32,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(21,'Birthday Decor Signature Cake 1','birthday-decor-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0021',6,7,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1060.00,1060.00,NULL,33,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(22,'Birthday Decor Signature Cake 2','birthday-decor-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0022',6,7,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1095.00,1095.00,NULL,34,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(23,'Birthday Decor Signature Cake 3','birthday-decor-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0023',6,7,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1130.00,1130.00,NULL,35,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(24,'Birthday Decor Signature Cake 4','birthday-decor-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0024',6,7,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1165.00,1165.00,1085.00,36,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:11','2026-05-19 04:31:11',NULL,0),(25,'Birthday Decor Signature Cake 5','birthday-decor-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0025',6,7,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1200.00,1200.00,NULL,37,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(26,'Anniversary Decor Signature Cake 1','anniversary-decor-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0026',6,8,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1235.00,1235.00,NULL,38,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(27,'Anniversary Decor Signature Cake 2','anniversary-decor-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0027',6,8,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1270.00,1270.00,NULL,39,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(28,'Anniversary Decor Signature Cake 3','anniversary-decor-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0028',6,8,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1305.00,1305.00,1225.00,40,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(29,'Anniversary Decor Signature Cake 4','anniversary-decor-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0029',6,8,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1340.00,1340.00,NULL,41,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(30,'Anniversary Decor Signature Cake 5','anniversary-decor-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0030',6,8,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',850.00,850.00,NULL,42,1,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(31,'Kids Theme Signature Cake 1','kids-theme-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0031',6,9,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',885.00,885.00,NULL,43,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(32,'Kids Theme Signature Cake 2','kids-theme-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0032',6,9,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',920.00,920.00,840.00,44,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(33,'Kids Theme Signature Cake 3','kids-theme-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0033',6,9,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',955.00,955.00,NULL,45,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(34,'Kids Theme Signature Cake 4','kids-theme-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0034',6,9,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',990.00,990.00,NULL,46,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(35,'Kids Theme Signature Cake 5','kids-theme-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0035',6,9,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1025.00,1025.00,NULL,47,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(36,'Wedding Decor Signature Cake 1','wedding-decor-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0036',6,10,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1060.00,1060.00,980.00,48,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(37,'Wedding Decor Signature Cake 2','wedding-decor-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0037',6,10,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1095.00,1095.00,NULL,49,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(38,'Wedding Decor Signature Cake 3','wedding-decor-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0038',6,10,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1130.00,1130.00,NULL,50,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(39,'Wedding Decor Signature Cake 4','wedding-decor-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0039',6,10,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1165.00,1165.00,NULL,51,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(40,'Wedding Decor Signature Cake 5','wedding-decor-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0040',6,10,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1200.00,1200.00,1120.00,12,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(41,'Chocolate Classics Signature Cake 1','chocolate-classics-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0041',11,12,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1235.00,1235.00,NULL,13,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(42,'Chocolate Classics Signature Cake 2','chocolate-classics-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0042',11,12,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1270.00,1270.00,NULL,14,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(43,'Chocolate Classics Signature Cake 3','chocolate-classics-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0043',11,12,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1305.00,1305.00,NULL,15,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(44,'Chocolate Classics Signature Cake 4','chocolate-classics-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0044',11,12,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1340.00,1340.00,1260.00,16,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(45,'Chocolate Classics Signature Cake 5','chocolate-classics-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0045',11,12,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',850.00,850.00,NULL,17,1,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(46,'Vanilla Classics Signature Cake 1','vanilla-classics-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0046',11,13,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',885.00,885.00,NULL,18,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(47,'Vanilla Classics Signature Cake 2','vanilla-classics-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0047',11,13,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',920.00,920.00,NULL,19,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:12','2026-05-19 04:31:12',NULL,0),(48,'Vanilla Classics Signature Cake 3','vanilla-classics-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0048',11,13,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',955.00,955.00,875.00,20,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(49,'Vanilla Classics Signature Cake 4','vanilla-classics-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0049',11,13,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',990.00,990.00,NULL,21,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(50,'Vanilla Classics Signature Cake 5','vanilla-classics-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0050',11,13,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1025.00,1025.00,NULL,22,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(51,'Fruit Classics Signature Cake 1','fruit-classics-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0051',11,14,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1060.00,1060.00,NULL,23,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(52,'Fruit Classics Signature Cake 2','fruit-classics-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0052',11,14,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1095.00,1095.00,1015.00,24,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(53,'Fruit Classics Signature Cake 3','fruit-classics-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0053',11,14,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1130.00,1130.00,NULL,25,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(54,'Fruit Classics Signature Cake 4','fruit-classics-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0054',11,14,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1165.00,1165.00,NULL,26,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(55,'Fruit Classics Signature Cake 5','fruit-classics-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0055',11,14,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1200.00,1200.00,NULL,27,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(56,'Tea Time Classics Signature Cake 1','tea-time-classics-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0056',11,15,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1235.00,1235.00,1155.00,28,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(57,'Tea Time Classics Signature Cake 2','tea-time-classics-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0057',11,15,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1270.00,1270.00,NULL,29,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(58,'Tea Time Classics Signature Cake 3','tea-time-classics-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0058',11,15,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1305.00,1305.00,NULL,30,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(59,'Tea Time Classics Signature Cake 4','tea-time-classics-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0059',11,15,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1340.00,1340.00,NULL,31,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(60,'Tea Time Classics Signature Cake 5','tea-time-classics-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0060',11,15,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',850.00,850.00,770.00,32,1,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(61,'Baked Cheesecakes Signature Cake 1','baked-cheesecakes-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0061',16,17,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',885.00,885.00,NULL,33,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(62,'Baked Cheesecakes Signature Cake 2','baked-cheesecakes-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0062',16,17,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',920.00,920.00,NULL,34,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(63,'Baked Cheesecakes Signature Cake 3','baked-cheesecakes-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0063',16,17,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',955.00,955.00,NULL,35,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(64,'Baked Cheesecakes Signature Cake 4','baked-cheesecakes-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0064',16,17,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',990.00,990.00,910.00,36,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(65,'Baked Cheesecakes Signature Cake 5','baked-cheesecakes-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0065',16,17,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1025.00,1025.00,NULL,37,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(66,'No Bake Cheesecakes Signature Cake 1','no-bake-cheesecakes-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0066',16,18,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1060.00,1060.00,NULL,38,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(67,'No Bake Cheesecakes Signature Cake 2','no-bake-cheesecakes-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0067',16,18,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1095.00,1095.00,NULL,39,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(68,'No Bake Cheesecakes Signature Cake 3','no-bake-cheesecakes-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0068',16,18,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1130.00,1130.00,1050.00,40,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(69,'No Bake Cheesecakes Signature Cake 4','no-bake-cheesecakes-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0069',16,18,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1165.00,1165.00,NULL,41,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(70,'No Bake Cheesecakes Signature Cake 5','no-bake-cheesecakes-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0070',16,18,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1200.00,1200.00,NULL,42,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(71,'Berry Cheesecakes Signature Cake 1','berry-cheesecakes-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0071',16,19,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1235.00,1235.00,NULL,43,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(72,'Berry Cheesecakes Signature Cake 2','berry-cheesecakes-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0072',16,19,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1270.00,1270.00,1190.00,44,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(73,'Berry Cheesecakes Signature Cake 3','berry-cheesecakes-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0073',16,19,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1305.00,1305.00,NULL,45,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(74,'Berry Cheesecakes Signature Cake 4','berry-cheesecakes-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0074',16,19,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1340.00,1340.00,NULL,46,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(75,'Berry Cheesecakes Signature Cake 5','berry-cheesecakes-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0075',16,19,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',850.00,850.00,NULL,47,1,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(76,'Premium Cheesecakes Signature Cake 1','premium-cheesecakes-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0076',16,20,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',885.00,885.00,805.00,48,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:13','2026-05-19 04:31:13',NULL,0),(77,'Premium Cheesecakes Signature Cake 2','premium-cheesecakes-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0077',16,20,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',920.00,920.00,NULL,49,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(78,'Premium Cheesecakes Signature Cake 3','premium-cheesecakes-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0078',16,20,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',955.00,955.00,NULL,50,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(79,'Premium Cheesecakes Signature Cake 4','premium-cheesecakes-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0079',16,20,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',990.00,990.00,NULL,51,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(80,'Premium Cheesecakes Signature Cake 5','premium-cheesecakes-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0080',16,20,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1025.00,1025.00,945.00,12,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(81,'Birthday Celebration Signature Cake 1','birthday-celebration-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0081',21,22,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1060.00,1060.00,NULL,13,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(82,'Birthday Celebration Signature Cake 2','birthday-celebration-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0082',21,22,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1095.00,1095.00,NULL,14,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(83,'Birthday Celebration Signature Cake 3','birthday-celebration-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0083',21,22,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1130.00,1130.00,NULL,15,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(84,'Birthday Celebration Signature Cake 4','birthday-celebration-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0084',21,22,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1165.00,1165.00,1085.00,16,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(85,'Birthday Celebration Signature Cake 5','birthday-celebration-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0085',21,22,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1200.00,1200.00,NULL,17,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(86,'Engagement Celebration Signature Cake 1','engagement-celebration-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0086',21,23,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1235.00,1235.00,NULL,18,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(87,'Engagement Celebration Signature Cake 2','engagement-celebration-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0087',21,23,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1270.00,1270.00,NULL,19,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(88,'Engagement Celebration Signature Cake 3','engagement-celebration-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0088',21,23,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1305.00,1305.00,1225.00,20,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(89,'Engagement Celebration Signature Cake 4','engagement-celebration-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0089',21,23,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1340.00,1340.00,NULL,21,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(90,'Engagement Celebration Signature Cake 5','engagement-celebration-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0090',21,23,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',850.00,850.00,NULL,22,1,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(91,'Baby Shower Signature Cake 1','baby-shower-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0091',21,24,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',885.00,885.00,NULL,23,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(92,'Baby Shower Signature Cake 2','baby-shower-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0092',21,24,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',920.00,920.00,840.00,24,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(93,'Baby Shower Signature Cake 3','baby-shower-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0093',21,24,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',955.00,955.00,NULL,25,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(94,'Baby Shower Signature Cake 4','baby-shower-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0094',21,24,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',990.00,990.00,NULL,26,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(95,'Baby Shower Signature Cake 5','baby-shower-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0095',21,24,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1025.00,1025.00,NULL,27,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(96,'Milestone Celebration Signature Cake 1','milestone-celebration-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0096',21,25,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1060.00,1060.00,980.00,28,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(97,'Milestone Celebration Signature Cake 2','milestone-celebration-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0097',21,25,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1095.00,1095.00,NULL,29,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(98,'Milestone Celebration Signature Cake 3','milestone-celebration-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0098',21,25,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1130.00,1130.00,NULL,30,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(99,'Milestone Celebration Signature Cake 4','milestone-celebration-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0099',21,25,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1165.00,1165.00,NULL,31,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(100,'Milestone Celebration Signature Cake 5','milestone-celebration-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0100',21,25,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1200.00,1200.00,1120.00,32,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(101,'Loaf Cakes Signature Cake 1','loaf-cakes-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0101',26,27,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1235.00,1235.00,NULL,33,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(102,'Loaf Cakes Signature Cake 2','loaf-cakes-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0102',26,27,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1270.00,1270.00,NULL,34,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(103,'Loaf Cakes Signature Cake 3','loaf-cakes-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0103',26,27,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1305.00,1305.00,NULL,35,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(104,'Loaf Cakes Signature Cake 4','loaf-cakes-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0104',26,27,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1340.00,1340.00,1260.00,36,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(105,'Loaf Cakes Signature Cake 5','loaf-cakes-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0105',26,27,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',850.00,850.00,NULL,37,1,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(106,'Bundt Cakes Signature Cake 1','bundt-cakes-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0106',26,28,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',885.00,885.00,NULL,38,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:14','2026-05-19 04:31:14',NULL,0),(107,'Bundt Cakes Signature Cake 2','bundt-cakes-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0107',26,28,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',920.00,920.00,NULL,39,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(108,'Bundt Cakes Signature Cake 3','bundt-cakes-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0108',26,28,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',955.00,955.00,875.00,40,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(109,'Bundt Cakes Signature Cake 4','bundt-cakes-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0109',26,28,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',990.00,990.00,NULL,41,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(110,'Bundt Cakes Signature Cake 5','bundt-cakes-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0110',26,28,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1025.00,1025.00,NULL,42,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(111,'Artisan Breads Signature Cake 1','artisan-breads-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0111',26,29,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1060.00,1060.00,NULL,43,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(112,'Artisan Breads Signature Cake 2','artisan-breads-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0112',26,29,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1095.00,1095.00,1015.00,44,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(113,'Artisan Breads Signature Cake 3','artisan-breads-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0113',26,29,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1130.00,1130.00,NULL,45,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(114,'Artisan Breads Signature Cake 4','artisan-breads-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0114',26,29,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1165.00,1165.00,NULL,46,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(115,'Artisan Breads Signature Cake 5','artisan-breads-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0115',26,29,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1200.00,1200.00,NULL,47,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(116,'Quick Breads Signature Cake 1','quick-breads-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0116',26,30,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1235.00,1235.00,1155.00,48,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(117,'Quick Breads Signature Cake 2','quick-breads-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0117',26,30,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1270.00,1270.00,NULL,49,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(118,'Quick Breads Signature Cake 3','quick-breads-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0118',26,30,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1305.00,1305.00,NULL,50,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(119,'Quick Breads Signature Cake 4','quick-breads-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0119',26,30,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1340.00,1340.00,NULL,51,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(120,'Quick Breads Signature Cake 5','quick-breads-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0120',26,30,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',850.00,850.00,770.00,12,1,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(121,'Chocolate Jars Signature Cake 1','chocolate-jars-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0121',31,32,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',885.00,885.00,NULL,13,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(122,'Chocolate Jars Signature Cake 2','chocolate-jars-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0122',31,32,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',920.00,920.00,NULL,14,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(123,'Chocolate Jars Signature Cake 3','chocolate-jars-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0123',31,32,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',955.00,955.00,NULL,15,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(124,'Chocolate Jars Signature Cake 4','chocolate-jars-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0124',31,32,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',990.00,990.00,910.00,16,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(125,'Chocolate Jars Signature Cake 5','chocolate-jars-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0125',31,32,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1025.00,1025.00,NULL,17,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(126,'Fruit Jars Signature Cake 1','fruit-jars-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0126',31,33,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1060.00,1060.00,NULL,18,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(127,'Fruit Jars Signature Cake 2','fruit-jars-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0127',31,33,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1095.00,1095.00,NULL,19,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(128,'Fruit Jars Signature Cake 3','fruit-jars-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0128',31,33,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1130.00,1130.00,1050.00,20,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(129,'Fruit Jars Signature Cake 4','fruit-jars-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0129',31,33,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1165.00,1165.00,NULL,21,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(130,'Fruit Jars Signature Cake 5','fruit-jars-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0130',31,33,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1200.00,1200.00,NULL,22,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(131,'Mousse Jars Signature Cake 1','mousse-jars-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0131',31,34,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1235.00,1235.00,NULL,23,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(132,'Mousse Jars Signature Cake 2','mousse-jars-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0132',31,34,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1270.00,1270.00,1190.00,24,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(133,'Mousse Jars Signature Cake 3','mousse-jars-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0133',31,34,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1305.00,1305.00,NULL,25,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:15','2026-05-19 04:31:15',NULL,0),(134,'Mousse Jars Signature Cake 4','mousse-jars-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0134',31,34,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1340.00,1340.00,NULL,26,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(135,'Mousse Jars Signature Cake 5','mousse-jars-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0135',31,34,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',850.00,850.00,NULL,27,1,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(136,'Seasonal Jars Signature Cake 1','seasonal-jars-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0136',31,35,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',885.00,885.00,805.00,28,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(137,'Seasonal Jars Signature Cake 2','seasonal-jars-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0137',31,35,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',920.00,920.00,NULL,29,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(138,'Seasonal Jars Signature Cake 3','seasonal-jars-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0138',31,35,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',955.00,955.00,NULL,30,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(139,'Seasonal Jars Signature Cake 4','seasonal-jars-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0139',31,35,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',990.00,990.00,NULL,31,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(140,'Seasonal Jars Signature Cake 5','seasonal-jars-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0140',31,35,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1025.00,1025.00,945.00,32,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(141,'Fudge Brownies Signature Cake 1','fudge-brownies-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0141',36,37,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1060.00,1060.00,NULL,33,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(142,'Fudge Brownies Signature Cake 2','fudge-brownies-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0142',36,37,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1095.00,1095.00,NULL,34,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(143,'Fudge Brownies Signature Cake 3','fudge-brownies-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0143',36,37,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1130.00,1130.00,NULL,35,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(144,'Fudge Brownies Signature Cake 4','fudge-brownies-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0144',36,37,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1165.00,1165.00,1085.00,36,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(145,'Fudge Brownies Signature Cake 5','fudge-brownies-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0145',36,37,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1200.00,1200.00,NULL,37,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(146,'Nutty Brownies Signature Cake 1','nutty-brownies-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0146',36,38,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1235.00,1235.00,NULL,38,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(147,'Nutty Brownies Signature Cake 2','nutty-brownies-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0147',36,38,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1270.00,1270.00,NULL,39,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(148,'Nutty Brownies Signature Cake 3','nutty-brownies-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0148',36,38,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1305.00,1305.00,1225.00,40,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(149,'Nutty Brownies Signature Cake 4','nutty-brownies-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0149',36,38,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1340.00,1340.00,NULL,41,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(150,'Nutty Brownies Signature Cake 5','nutty-brownies-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0150',36,38,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',850.00,850.00,NULL,42,1,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(151,'Blondies Signature Cake 1','blondies-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0151',36,39,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',885.00,885.00,NULL,43,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(152,'Blondies Signature Cake 2','blondies-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0152',36,39,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',920.00,920.00,840.00,44,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(153,'Blondies Signature Cake 3','blondies-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0153',36,39,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',955.00,955.00,NULL,45,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(154,'Blondies Signature Cake 4','blondies-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0154',36,39,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',990.00,990.00,NULL,46,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(155,'Blondies Signature Cake 5','blondies-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0155',36,39,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1025.00,1025.00,NULL,47,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(156,'Dessert Bars Signature Cake 1','dessert-bars-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0156',36,40,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1060.00,1060.00,980.00,48,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:16','2026-05-19 04:31:16',NULL,0),(157,'Dessert Bars Signature Cake 2','dessert-bars-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0157',36,40,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1095.00,1095.00,NULL,49,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(158,'Dessert Bars Signature Cake 3','dessert-bars-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0158',36,40,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1130.00,1130.00,NULL,50,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(159,'Dessert Bars Signature Cake 4','dessert-bars-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0159',36,40,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1165.00,1165.00,NULL,51,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(160,'Dessert Bars Signature Cake 5','dessert-bars-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0160',36,40,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1200.00,1200.00,1120.00,12,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(161,'Classic Cupcakes Signature Cake 1','classic-cupcakes-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0161',41,42,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1235.00,1235.00,NULL,13,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(162,'Classic Cupcakes Signature Cake 2','classic-cupcakes-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0162',41,42,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1270.00,1270.00,NULL,14,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(163,'Classic Cupcakes Signature Cake 3','classic-cupcakes-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0163',41,42,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1305.00,1305.00,NULL,15,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(164,'Classic Cupcakes Signature Cake 4','classic-cupcakes-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0164',41,42,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1340.00,1340.00,1260.00,16,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(165,'Classic Cupcakes Signature Cake 5','classic-cupcakes-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0165',41,42,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',850.00,850.00,NULL,17,1,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(166,'Filled Cupcakes Signature Cake 1','filled-cupcakes-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0166',41,43,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',885.00,885.00,NULL,18,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(167,'Filled Cupcakes Signature Cake 2','filled-cupcakes-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0167',41,43,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',920.00,920.00,NULL,19,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(168,'Filled Cupcakes Signature Cake 3','filled-cupcakes-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0168',41,43,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',955.00,955.00,875.00,20,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(169,'Filled Cupcakes Signature Cake 4','filled-cupcakes-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0169',41,43,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',990.00,990.00,NULL,21,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(170,'Filled Cupcakes Signature Cake 5','filled-cupcakes-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0170',41,43,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1025.00,1025.00,NULL,22,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(171,'Party Cupcakes Signature Cake 1','party-cupcakes-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0171',41,44,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1060.00,1060.00,NULL,23,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:17','2026-05-19 04:31:17',NULL,0),(172,'Party Cupcakes Signature Cake 2','party-cupcakes-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0172',41,44,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1095.00,1095.00,1015.00,24,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(173,'Party Cupcakes Signature Cake 3','party-cupcakes-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0173',41,44,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1130.00,1130.00,NULL,25,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(174,'Party Cupcakes Signature Cake 4','party-cupcakes-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0174',41,44,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1165.00,1165.00,NULL,26,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(175,'Party Cupcakes Signature Cake 5','party-cupcakes-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0175',41,44,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1200.00,1200.00,NULL,27,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(176,'Premium Cupcakes Signature Cake 1','premium-cupcakes-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0176',41,45,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1235.00,1235.00,1155.00,28,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(177,'Premium Cupcakes Signature Cake 2','premium-cupcakes-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0177',41,45,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1270.00,1270.00,NULL,29,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(178,'Premium Cupcakes Signature Cake 3','premium-cupcakes-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0178',41,45,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1305.00,1305.00,NULL,30,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(179,'Premium Cupcakes Signature Cake 4','premium-cupcakes-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0179',41,45,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1340.00,1340.00,NULL,31,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(180,'Premium Cupcakes Signature Cake 5','premium-cupcakes-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0180',41,45,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',850.00,850.00,770.00,32,1,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(181,'Summer Specials Signature Cake 1','summer-specials-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0181',46,47,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',885.00,885.00,NULL,33,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(182,'Summer Specials Signature Cake 2','summer-specials-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0182',46,47,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',920.00,920.00,NULL,34,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(183,'Summer Specials Signature Cake 3','summer-specials-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0183',46,47,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',955.00,955.00,NULL,35,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(184,'Summer Specials Signature Cake 4','summer-specials-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0184',46,47,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',990.00,990.00,910.00,36,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(185,'Summer Specials Signature Cake 5','summer-specials-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0185',46,47,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1025.00,1025.00,NULL,37,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(186,'Monsoon Specials Signature Cake 1','monsoon-specials-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0186',46,48,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1060.00,1060.00,NULL,38,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(187,'Monsoon Specials Signature Cake 2','monsoon-specials-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0187',46,48,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1095.00,1095.00,NULL,39,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(188,'Monsoon Specials Signature Cake 3','monsoon-specials-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0188',46,48,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1130.00,1130.00,1050.00,40,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(189,'Monsoon Specials Signature Cake 4','monsoon-specials-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0189',46,48,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',1165.00,1165.00,NULL,41,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(190,'Monsoon Specials Signature Cake 5','monsoon-specials-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0190',46,48,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',1200.00,1200.00,NULL,42,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(191,'Festive Specials Signature Cake 1','festive-specials-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0191',46,49,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',1235.00,1235.00,NULL,43,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(192,'Festive Specials Signature Cake 2','festive-specials-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0192',46,49,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',1270.00,1270.00,1190.00,44,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(193,'Festive Specials Signature Cake 3','festive-specials-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0193',46,49,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',1305.00,1305.00,NULL,45,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(194,'Festive Specials Signature Cake 4','festive-specials-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0194',46,49,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-3.jpg',1340.00,1340.00,NULL,46,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(195,'Festive Specials Signature Cake 5','festive-specials-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0195',46,49,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-4.jpg',850.00,850.00,NULL,47,1,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(196,'Winter Specials Signature Cake 1','winter-specials-signature-cake-1','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0196',46,50,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-5.jpg',885.00,885.00,805.00,48,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(197,'Winter Specials Signature Cake 2','winter-specials-signature-cake-2','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0197',46,50,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-6.jpg',920.00,920.00,NULL,49,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(198,'Winter Specials Signature Cake 3','winter-specials-signature-cake-3','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0198',46,50,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-1.jpg',955.00,955.00,NULL,50,1,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:18','2026-05-19 04:31:18',NULL,0),(199,'Winter Specials Signature Cake 4','winter-specials-signature-cake-4','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0199',46,50,NULL,NULL,'regular',1,'in_stock',24,NULL,1,1,'/client/assets/images/placeholder-cake-2.jpg',990.00,990.00,NULL,51,0,0,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:19','2026-05-19 04:31:19',NULL,0),(200,'Winter Specials Signature Cake 5','winter-specials-signature-cake-5','Synthetic test catalog item for master-truth import and restore validation.','Synthetic test catalog item for master-truth import and restore validation.',NULL,NULL,NULL,NULL,NULL,'CKF-MT-0200',46,50,NULL,NULL,'eggless',1,'in_stock',24,NULL,1,1,'/client/assets/images/product/1779193348_b512944a.png',1025.00,1025.00,945.00,12,0,1,NULL,NULL,0.00,0,0,NULL,'2026-05-19 04:31:19','2026-05-19 12:22:29',NULL,0);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qa_form_test_runs`
--

DROP TABLE IF EXISTS `qa_form_test_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qa_form_test_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `test_case_id` varchar(60) NOT NULL,
  `layer_label` varchar(30) NOT NULL DEFAULT 'second',
  `form_action` varchar(120) NOT NULL,
  `expected_outcome` varchar(120) NOT NULL,
  `actual_outcome` varchar(120) NOT NULL,
  `verdict` enum('pass','fail') NOT NULL,
  `evidence_ref` varchar(255) DEFAULT NULL,
  `notes` text,
  `tested_by_admin_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qa_form_runs_case` (`test_case_id`),
  KEY `idx_qa_form_runs_verdict` (`verdict`),
  KEY `idx_qa_form_runs_created` (`created_at`),
  CONSTRAINT `chk_qa_form_actual_outcome_policy_v1` CHECK ((`actual_outcome` in (_utf8mb4'accepted_201_created',_utf8mb4'rejected_422_required_fields',_utf8mb4'rejected_422_invalid_email',_utf8mb4'rejected_422_invalid_country_code',_utf8mb4'rejected_422_phone_india_10_digits',_utf8mb4'rejected_422_phone_intl_6_15_digits',_utf8mb4'rejected_422_servings_numeric',_utf8mb4'rejected_422_privacy_consent',_utf8mb4'rejected_422_invalid_event_information',_utf8mb4'rejected_422_invalid_diet_preference')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qa_form_test_runs`
--

LOCK TABLES `qa_form_test_runs` WRITE;
/*!40000 ALTER TABLE `qa_form_test_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `qa_form_test_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `queue_jobs`
--

DROP TABLE IF EXISTS `queue_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_type` varchar(80) NOT NULL,
  `payload_json` json DEFAULT NULL,
  `status` enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  `available_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `attempts` int NOT NULL DEFAULT '0',
  `last_error` varchar(260) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_queue_status` (`status`,`available_at`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `queue_jobs`
--

LOCK TABLES `queue_jobs` WRITE;
/*!40000 ALTER TABLE `queue_jobs` DISABLE KEYS */;
INSERT INTO `queue_jobs` VALUES (1,'send_communication','{\"log_id\": 1, \"channel\": \"email\", \"event_key\": \"build_your_cake_quote_email\", \"recipient\": \"parin11@gmail.com\"}','completed','2026-05-19 16:52:37',1,NULL,'2026-05-19 16:52:37','2026-05-19 17:34:15'),(2,'send_communication','{\"log_id\": 2, \"channel\": \"whatsapp\", \"event_key\": \"build_your_cake_quote_whatsapp\", \"recipient\": \"+919330033000\"}','queued','2026-05-19 18:21:21',2,'No active approved WhatsApp template mapping for event: build_your_cake_quote_whatsapp','2026-05-19 16:52:37','2026-05-19 18:17:21'),(3,'send_communication','{\"log_id\": 3, \"channel\": \"email\", \"event_key\": \"build_your_cake_quote_email\", \"recipient\": \"parin11@gmail.com\"}','completed','2026-05-19 16:52:57',1,NULL,'2026-05-19 16:52:57','2026-05-19 17:34:18'),(4,'send_communication','{\"log_id\": 4, \"channel\": \"whatsapp\", \"event_key\": \"build_your_cake_quote_whatsapp\", \"recipient\": \"+919330033000\"}','queued','2026-05-19 18:21:21',2,'No active approved WhatsApp template mapping for event: build_your_cake_quote_whatsapp','2026-05-19 16:52:57','2026-05-19 18:17:21'),(5,'send_communication','{\"log_id\": 5, \"channel\": \"email\", \"event_key\": \"build_your_cake_quote_email\", \"recipient\": \"parin11@gmail.com\"}','completed','2026-05-19 16:53:14',1,NULL,'2026-05-19 16:53:14','2026-05-19 17:34:22'),(6,'send_communication','{\"log_id\": 6, \"channel\": \"whatsapp\", \"event_key\": \"build_your_cake_quote_whatsapp\", \"recipient\": \"+919330033000\"}','queued','2026-05-19 18:21:21',2,'No active approved WhatsApp template mapping for event: build_your_cake_quote_whatsapp','2026-05-19 16:53:14','2026-05-19 18:17:21'),(7,'send_communication','{\"log_id\": 7, \"channel\": \"email\", \"event_key\": \"invoice_paid\", \"recipient\": \"parin11@gmail.com\"}','completed','2026-05-19 18:03:36',1,NULL,'2026-05-19 18:03:36','2026-05-19 18:17:26'),(8,'send_communication','{\"log_id\": 8, \"channel\": \"email\", \"event_key\": \"payment_confirmed_customer\", \"recipient\": \"parin11@gmail.com\"}','completed','2026-05-19 18:03:37',1,NULL,'2026-05-19 18:03:37','2026-05-19 18:17:29'),(9,'send_communication','{\"log_id\": 9, \"channel\": \"email\", \"event_key\": \"payment_confirmed_admin\", \"recipient\": \"cakeouflage@gmail.com\"}','completed','2026-05-19 18:03:37',1,NULL,'2026-05-19 18:03:37','2026-05-19 18:17:32'),(10,'send_communication','{\"log_id\": 10, \"channel\": \"email\", \"event_key\": \"build_your_cake_quote_email\", \"recipient\": \"parin11@gmail.com\"}','completed','2026-05-19 18:17:15',1,NULL,'2026-05-19 18:17:15','2026-05-19 18:17:35');
/*!40000 ALTER TABLE `queue_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reminders`
--

DROP TABLE IF EXISTS `reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reminders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `b2b_account_id` bigint unsigned DEFAULT NULL,
  `reminder_type` enum('payment_due','birthday','follow_up','production') NOT NULL,
  `title` varchar(180) NOT NULL,
  `reminder_on` datetime NOT NULL,
  `status` enum('pending','done','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text,
  `created_by_admin_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `b2b_account_id` (`b2b_account_id`),
  KEY `created_by_admin_id` (`created_by_admin_id`),
  KEY `idx_reminders_when` (`reminder_on`),
  KEY `idx_reminders_status` (`status`),
  CONSTRAINT `reminders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reminders_ibfk_2` FOREIGN KEY (`b2b_account_id`) REFERENCES `b2b_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reminders_ibfk_3` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reminders`
--

LOCK TABLES `reminders` WRITE;
/*!40000 ALTER TABLE `reminders` DISABLE KEYS */;
/*!40000 ALTER TABLE `reminders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `review_text` text,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_reviews_product` (`product_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(120) NOT NULL,
  `setting_value` longtext,
  `updated_by_admin_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by_admin_id` (`updated_by_admin_id`),
  CONSTRAINT `settings_ibfk_1` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'byoc_whatsapp_enabled','0',1,'2026-05-19 18:44:46','2026-05-19 18:44:46');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `smtp_settings`
--

DROP TABLE IF EXISTS `smtp_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `smtp_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `host` varchar(190) DEFAULT NULL,
  `port` int DEFAULT NULL,
  `username` varchar(190) DEFAULT NULL,
  `password_encrypted` text,
  `encryption` enum('none','ssl','tls') NOT NULL DEFAULT 'tls',
  `from_name` varchar(120) DEFAULT NULL,
  `from_email` varchar(190) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `updated_by_admin_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `updated_by_admin_id` (`updated_by_admin_id`),
  CONSTRAINT `smtp_settings_ibfk_1` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `smtp_settings`
--

LOCK TABLES `smtp_settings` WRITE;
/*!40000 ALTER TABLE `smtp_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `smtp_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_addresses`
--

DROP TABLE IF EXISTS `user_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `label` varchar(60) DEFAULT NULL,
  `recipient_name` varchar(120) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `line1` varchar(190) NOT NULL,
  `line2` varchar(190) DEFAULT NULL,
  `landmark` varchar(190) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `postal_code` varchar(15) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_addresses_user_id` (`user_id`),
  KEY `idx_user_addresses_postal` (`postal_code`),
  CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_addresses`
--

LOCK TABLES `user_addresses` WRITE;
/*!40000 ALTER TABLE `user_addresses` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('customer','b2b_user') NOT NULL DEFAULT 'customer',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `email_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Parin Daulat','parin11@gmail.com','9330033000','$2y$10$TkSb6N1oLzNyX0u9wwAXXuE82EwfwiFlon0RIRx901ptfsu8WOI/e','customer',1,NULL,'2026-05-19 14:21:15','2026-05-19 14:21:15','2026-05-19 14:21:30',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_settings`
--

DROP TABLE IF EXISTS `whatsapp_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider_name` varchar(120) DEFAULT NULL,
  `app_id` varchar(190) DEFAULT NULL,
  `app_secret_encrypted` text,
  `api_base_url` varchar(255) DEFAULT NULL,
  `api_key_encrypted` text,
  `access_token_encrypted` text,
  `phone_number_id` varchar(120) DEFAULT NULL,
  `business_account_id` varchar(120) DEFAULT NULL,
  `webhook_callback_url` varchar(255) DEFAULT NULL,
  `webhook_verify_token` varchar(120) DEFAULT NULL,
  `default_language_code` varchar(12) DEFAULT NULL,
  `default_category` varchar(40) DEFAULT NULL,
  `namespace_reference` varchar(190) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `updated_by_admin_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `updated_by_admin_id` (`updated_by_admin_id`),
  CONSTRAINT `whatsapp_settings_ibfk_1` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_settings`
--

LOCK TABLES `whatsapp_settings` WRITE;
/*!40000 ALTER TABLE `whatsapp_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_template_approval_logs`
--

DROP TABLE IF EXISTS `whatsapp_template_approval_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_template_approval_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint unsigned NOT NULL,
  `previous_status` varchar(40) DEFAULT NULL,
  `new_status` varchar(40) NOT NULL,
  `meta_reason` varchar(260) DEFAULT NULL,
  `response_payload_json` json DEFAULT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `changed_by` (`changed_by`),
  KEY `idx_wa_approval_logs_template` (`template_id`),
  CONSTRAINT `whatsapp_template_approval_logs_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `whatsapp_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `whatsapp_template_approval_logs_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_template_approval_logs`
--

LOCK TABLES `whatsapp_template_approval_logs` WRITE;
/*!40000 ALTER TABLE `whatsapp_template_approval_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_template_approval_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_template_buttons`
--

DROP TABLE IF EXISTS `whatsapp_template_buttons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_template_buttons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint unsigned NOT NULL,
  `button_type` enum('quick_reply','url','phone') NOT NULL,
  `button_text` varchar(60) NOT NULL,
  `button_value` varchar(255) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wa_template_buttons_template` (`template_id`),
  CONSTRAINT `whatsapp_template_buttons_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `whatsapp_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_template_buttons`
--

LOCK TABLES `whatsapp_template_buttons` WRITE;
/*!40000 ALTER TABLE `whatsapp_template_buttons` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_template_buttons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_template_mappings`
--

DROP TABLE IF EXISTS `whatsapp_template_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_template_mappings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_key` varchar(120) NOT NULL,
  `template_id` bigint unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_key` (`event_key`),
  KEY `template_id` (`template_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `whatsapp_template_mappings_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `whatsapp_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `whatsapp_template_mappings_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_template_mappings`
--

LOCK TABLES `whatsapp_template_mappings` WRITE;
/*!40000 ALTER TABLE `whatsapp_template_mappings` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_template_mappings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_template_sync_logs`
--

DROP TABLE IF EXISTS `whatsapp_template_sync_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_template_sync_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint unsigned DEFAULT NULL,
  `sync_direction` enum('push_to_meta','pull_from_meta') NOT NULL,
  `status` enum('success','failed','partial') NOT NULL,
  `request_payload_json` json DEFAULT NULL,
  `response_payload_json` json DEFAULT NULL,
  `message` varchar(260) DEFAULT NULL,
  `synced_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `synced_by` (`synced_by`),
  KEY `idx_wa_sync_logs_template` (`template_id`),
  CONSTRAINT `whatsapp_template_sync_logs_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `whatsapp_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `whatsapp_template_sync_logs_ibfk_2` FOREIGN KEY (`synced_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_template_sync_logs`
--

LOCK TABLES `whatsapp_template_sync_logs` WRITE;
/*!40000 ALTER TABLE `whatsapp_template_sync_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_template_sync_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_template_variables`
--

DROP TABLE IF EXISTS `whatsapp_template_variables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_template_variables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint unsigned NOT NULL,
  `variable_key` varchar(120) NOT NULL,
  `variable_label` varchar(120) NOT NULL,
  `component_scope` enum('header','body','footer','button') NOT NULL DEFAULT 'body',
  `parameter_order` int NOT NULL,
  `fallback_value` varchar(180) DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wa_template_variable_template` (`template_id`),
  CONSTRAINT `whatsapp_template_variables_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `whatsapp_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_template_variables`
--

LOCK TABLES `whatsapp_template_variables` WRITE;
/*!40000 ALTER TABLE `whatsapp_template_variables` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_template_variables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_template_versions`
--

DROP TABLE IF EXISTS `whatsapp_template_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_template_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint unsigned NOT NULL,
  `version_number` int NOT NULL,
  `snapshot_json` json NOT NULL,
  `change_note` varchar(260) DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wa_template_version` (`template_id`,`version_number`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `whatsapp_template_versions_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `whatsapp_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `whatsapp_template_versions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_template_versions`
--

LOCK TABLES `whatsapp_template_versions` WRITE;
/*!40000 ALTER TABLE `whatsapp_template_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_template_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_templates`
--

DROP TABLE IF EXISTS `whatsapp_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `internal_name` varchar(180) NOT NULL,
  `template_key` varchar(120) NOT NULL,
  `meta_template_name` varchar(180) NOT NULL,
  `meta_template_id_or_reference` varchar(190) DEFAULT NULL,
  `waba_id` varchar(120) DEFAULT NULL,
  `phone_number_id` varchar(120) DEFAULT NULL,
  `category` enum('utility','marketing','authentication') NOT NULL DEFAULT 'utility',
  `language_code` varchar(12) NOT NULL DEFAULT 'en_US',
  `header_type` enum('none','text','image','video','document') NOT NULL DEFAULT 'none',
  `header_text` varchar(240) DEFAULT NULL,
  `header_media_example` varchar(255) DEFAULT NULL,
  `body_text` longtext NOT NULL,
  `footer_text` varchar(180) DEFAULT NULL,
  `buttons_json` json DEFAULT NULL,
  `variables_json` json DEFAULT NULL,
  `approval_status` enum('draft','ready_to_submit','submitted','in_review','approved','rejected','paused','disabled','archived') NOT NULL DEFAULT 'draft',
  `approval_reason` varchar(260) DEFAULT NULL,
  `sync_status` enum('local_only','pending_sync','synced','sync_failed') NOT NULL DEFAULT 'local_only',
  `last_synced_at` datetime DEFAULT NULL,
  `mapped_event_key` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_key` (`template_key`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_wa_template_status` (`approval_status`),
  KEY `idx_wa_template_event` (`mapped_event_key`),
  KEY `idx_wa_template_meta_name` (`meta_template_name`),
  CONSTRAINT `whatsapp_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `whatsapp_templates_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_templates`
--

LOCK TABLES `whatsapp_templates` WRITE;
/*!40000 ALTER TABLE `whatsapp_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wishlist_items`
--

DROP TABLE IF EXISTS `wishlist_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wishlist_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wishlist_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wishlist_product` (`wishlist_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `wishlist_items_ibfk_1` FOREIGN KEY (`wishlist_id`) REFERENCES `wishlists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlist_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlist_items`
--

LOCK TABLES `wishlist_items` WRITE;
/*!40000 ALTER TABLE `wishlist_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `wishlist_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wishlists`
--

DROP TABLE IF EXISTS `wishlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wishlists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wishlist_user` (`user_id`),
  CONSTRAINT `wishlists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlists`
--

LOCK TABLES `wishlists` WRITE;
/*!40000 ALTER TABLE `wishlists` DISABLE KEYS */;
INSERT INTO `wishlists` VALUES (1,1,'2026-05-19 14:21:15');
/*!40000 ALTER TABLE `wishlists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'cakeouflage_dev'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-20  7:00:00
