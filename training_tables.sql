-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: payr
-- ------------------------------------------------------
-- Server version	8.4.3

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
-- Table structure for table `training_phases`
--

DROP TABLE IF EXISTS `training_phases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_phases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `order` int NOT NULL DEFAULT '0',
  `tenant_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_phases`
--

LOCK TABLES `training_phases` WRITE;
/*!40000 ALTER TABLE `training_phases` DISABLE KEYS */;
INSERT INTO `training_phases` VALUES (1,'Phase 1: Culture & Integrity','Learn about our core values, mission, and the rules that keep us great.',1,NULL,'2026-05-21 18:06:26','2026-05-21 18:06:26',NULL),(2,'Phase 2: Product Knowledge','Deep dive into our product catalogs and the value we provide to customers.',2,NULL,'2026-05-21 18:06:27','2026-05-21 18:06:27',NULL),(3,'Phase 3: Operational Excellence','Master the tools and workflows you will use daily.',3,NULL,'2026-05-21 18:06:27','2026-05-21 18:06:27',NULL);
/*!40000 ALTER TABLE `training_phases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_modules`
--

DROP TABLE IF EXISTS `training_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `phase_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `content_type` enum('policy','catalog','video','document') COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_body` longtext COLLATE utf8mb4_unicode_ci,
  `content_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estimated_time_minutes` int NOT NULL DEFAULT '0',
  `order` int NOT NULL DEFAULT '0',
  `is_assessment_required` tinyint(1) NOT NULL DEFAULT '1',
  `passing_percentage` int NOT NULL DEFAULT '80',
  `questions_per_test` int NOT NULL DEFAULT '5',
  `show_all_at_once` tinyint(1) NOT NULL DEFAULT '0',
  `video_chapters` json DEFAULT NULL,
  `video_milestones` json DEFAULT NULL,
  `tenant_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_modules_phase_id_foreign` (`phase_id`),
  CONSTRAINT `training_modules_phase_id_foreign` FOREIGN KEY (`phase_id`) REFERENCES `training_phases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_modules`
--

LOCK TABLES `training_modules` WRITE;
/*!40000 ALTER TABLE `training_modules` DISABLE KEYS */;
INSERT INTO `training_modules` VALUES (1,1,'Company Code of Conduct','Understanding our professional standards and ethical guidelines.','policy','Welcome to the Team! We believe in transparency, respect, and hard work.\n\nOur first rule is simple: Always act with integrity. Whether you are dealing with a client or a colleague, honesty is our cornerstone.\n\nPunctuality and Professionalism: We value your time. Being on time means you respect your work and your teammates.\n\nDiversity and Inclusion: We are a global family. We celebrate differences and provide equal opportunities for everyone to grow.',NULL,15,1,1,80,5,0,NULL,NULL,NULL,'2026-05-21 18:06:27','2026-05-21 18:06:27',NULL),(2,2,'Product Catalog: HRX Core','Learn about our primary HR management suite features.','catalog','HRX Core is our flagship product. It handles everything from attendance to payroll.\n\nFeature 1: Automated Payroll. No more manual calculations. Our system syncs with attendance logs to generate accurate payslips.\n\nFeature 2: Employee Lifecycle. Track an employee from recruitment to retirement seamlessly.\n\nFeature 3: AI-Powered Analytics. Get insights into team performance and turnover rates with a single click.',NULL,30,1,1,80,5,0,NULL,NULL,NULL,'2026-05-21 18:06:27','2026-05-21 18:06:27',NULL),(3,3,'Welcome Message from CEO','A personal welcome and vision statement from our leadership.','video',NULL,'https://www.youtube.com/watch?v=dQw4w9WgXcQ',5,1,1,80,5,0,NULL,NULL,NULL,'2026-05-21 18:06:27','2026-05-21 18:06:27',NULL),(4,2,'Zorbit Product Catalogue','Detailed technical specs and applications of Zorbit natural desiccants and rain controllers.','catalog',NULL,'http://localhost:8001/storage/training/zorbit-catalogue-2022.pdf',30,2,1,80,3,0,NULL,NULL,NULL,'2026-05-21 18:07:03','2026-05-21 18:07:03',NULL),(5,2,'Fillezy Product Catalogue','Industrial packaging and protective filing products.','catalog',NULL,'http://localhost:8001/storage/training/Fillezy-Catalogue-Final.pdf',30,3,1,80,3,0,NULL,NULL,NULL,'2026-05-21 18:07:35','2026-05-21 18:07:35',NULL),(6,2,'KIF Product Catalogue','VCI paper, films, and advanced anti-corrosion packaging systems.','catalog',NULL,'http://localhost:8001/storage/training/KIF-2022-Catalogue.pdf',30,4,1,80,3,0,NULL,NULL,NULL,'2026-05-21 18:07:48','2026-05-21 18:07:48',NULL),(7,2,'Dr. Bio Product Catalogue','Environmentally friendly bio-degradable rust preventives and packaging.','catalog',NULL,'http://localhost:8001/storage/training/Dr-Bio-Catalogue-New.pdf',30,5,1,80,3,0,NULL,NULL,NULL,'2026-05-21 18:08:07','2026-05-21 18:08:07',NULL),(8,2,'Rust-X Product Catalogue','The complete handbook of Rust-X VCI liquids, oils, emitters, and sprays.','catalog',NULL,'http://localhost:8001/storage/training/Rust-X-New-Catalogue-Print.pdf',30,6,1,80,3,0,NULL,NULL,NULL,'2026-05-21 18:08:14','2026-05-21 18:08:14',NULL),(9,2,'TUFF Product Catalogue','TUFF heavy-duty tarpaulins, covers, and high-strength industrial sheets.','catalog',NULL,'http://localhost:8001/storage/training/TUFF-CATALOUGE.pdf',30,7,1,80,3,0,NULL,NULL,NULL,'2026-05-21 18:08:29','2026-05-21 18:08:29',NULL);
/*!40000 ALTER TABLE `training_modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_questions`
--

DROP TABLE IF EXISTS `training_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_questions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint unsigned NOT NULL,
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `correct_option_index` int NOT NULL,
  `marks` int NOT NULL DEFAULT '1',
  `tenant_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_questions_module_id_foreign` (`module_id`),
  CONSTRAINT `training_questions_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_questions_chk_1` CHECK (json_valid(`options`))
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_questions`
--

LOCK TABLES `training_questions` WRITE;
/*!40000 ALTER TABLE `training_questions` DISABLE KEYS */;
INSERT INTO `training_questions` VALUES (1,1,'What is the cornerstone of our professional standards?','[\"Speed\",\"Honesty & Integrity\",\"Low Cost\",\"Isolation\"]',1,1,NULL,'2026-05-21 18:06:27','2026-05-21 18:06:27'),(2,1,'What does being on time signify in our company?','[\"Nothing\",\"Strict management\",\"Respect for work and teammates\",\"Lack of freedom\"]',2,1,NULL,'2026-05-21 18:06:27','2026-05-21 18:06:27'),(3,2,'Which feature handles recruitment to retirement tracking?','[\"Payroll\",\"Lifecycle Management\",\"Analytics\",\"Attendance\"]',1,1,NULL,'2026-05-21 18:06:27','2026-05-21 18:06:27'),(4,5,'Which material is NOT listed as a basis for Fillezy\'s AER cushioning products?','[\"Paper-based\",\"Corn starch based\",\"Recyclable plastic films\",\"Polyethylene foam\"]',3,10,NULL,'2026-05-21 18:07:48','2026-05-21 18:07:48'),(5,5,'What damage prevention rate does Fillezy claim for its void fill and cushioning solutions?','[\"Up to 75%\",\"Up to 90%\",\"Up to 100%\",\"Up to 50%\"]',2,10,NULL,'2026-05-21 18:07:48','2026-05-21 18:07:48'),(6,5,'According to the catalogue, using Fillezy\'s single‑wrap AER cushion is how much cheaper than normal bubble wrap?','[\"Approximately the same cost\",\"25% cheaper\",\"50% cheaper\",\"75% cheaper\"]',2,10,NULL,'2026-05-21 18:07:48','2026-05-21 18:07:48'),(7,6,'Which two active ingredients are combined in KIF sachets to absorb ethylene and moisture?','[\"Zeolite powder and potassium permanganate\",\"Activated carbon and silica gel\",\"Calcium carbonate and magnesium oxide\",\"Sodium bicarbonate and citric acid\"]',0,10,NULL,'2026-05-21 18:08:07','2026-05-21 18:08:07'),(8,6,'In which of the following countries does KIF NOT have a manufacturing facility?','[\"USA\",\"India\",\"Brazil\",\"Italy\"]',2,10,NULL,'2026-05-21 18:08:07','2026-05-21 18:08:07'),(9,6,'What type of membrane is used in KIF sachets to allow breathability under high humidity conditions?','[\"Tyvek & Dupont membrane\",\"Polyethylene film\",\"PVC coating\",\"Aluminum foil\"]',0,10,NULL,'2026-05-21 18:08:07','2026-05-21 18:08:07'),(10,8,'Which Rust‑X product is described as a water based acrylic transparent non‑peelable coating with a drying time of 30 minutes to 2 hours?','[\"VCI 657\",\"VCI 1372\",\"VCI 1368\",\"VCI 1369\"]',0,10,NULL,'2026-05-21 18:08:29','2026-05-21 18:08:29'),(11,8,'Which product is a powder cleaner/degreaser with a concentration of 2‑3 % and a shelf‑life of 2‑7 days?','[\"VCI 1415\",\"VCI 1417\",\"VCI 1419\",\"VCI 4201\"]',1,10,NULL,'2026-05-21 18:08:29','2026-05-21 18:08:29'),(12,8,'In which of the following countries does Rust‑X have a manufacturing location?','[\"USA\",\"Germany\",\"Brazil\",\"Japan\"]',0,10,NULL,'2026-05-21 18:08:29','2026-05-21 18:08:29'),(13,9,'What is the claimed strength improvement of a regular Tuﬀpaulin film compared to a regular Polyethylene film?','[\"150-250% stronger\",\"50-100% stronger\",\"10-20% stronger\",\"No strength difference\"]',0,10,NULL,'2026-05-21 18:08:48','2026-05-21 18:08:48'),(14,9,'Which of the following is NOT listed as an agricultural application of Tuﬀpaulin film?','[\"Drying of spices\",\"Construction site scaffolding\",\"Fumigation of tobacco leaves\",\"Fish farming\"]',1,10,NULL,'2026-05-21 18:08:48','2026-05-21 18:08:48'),(15,9,'Which certification or standard is mentioned for the Tuﬀpaulin product?','[\"ISO 9001:2008\",\"ISO 14001:2015\",\"ASTM D638\",\"CE Mark\"]',0,10,NULL,'2026-05-21 18:08:48','2026-05-21 18:08:48');
/*!40000 ALTER TABLE `training_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `library_taxonomies`
--

DROP TABLE IF EXISTS `library_taxonomies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `library_taxonomies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('brand','category') COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `library_taxonomies_parent_id_foreign` (`parent_id`),
  CONSTRAINT `library_taxonomies_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `library_taxonomies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `library_taxonomies`
--

LOCK TABLES `library_taxonomies` WRITE;
/*!40000 ALTER TABLE `library_taxonomies` DISABLE KEYS */;
INSERT INTO `library_taxonomies` VALUES (1,'brand','Rust-X',NULL,'#ec0909',NULL,0,'2026-05-04 13:53:46','2026-05-04 14:00:10'),(2,'brand','Dr.Bio',NULL,'#33a701',NULL,0,'2026-05-04 13:53:59','2026-05-04 14:00:10'),(3,'brand','Fillezy',NULL,'#ff9800','Void fill systems',0,'2026-05-04 13:54:15','2026-05-04 14:00:10'),(4,'brand','KIF',NULL,'#2196f3','Fresh produce packaging',0,'2026-05-04 13:54:27','2026-05-04 14:00:10'),(5,'brand','Zorbit',NULL,'#ff5722','Desiccants & absorbents',0,'2026-05-04 13:54:36','2026-05-04 14:00:10'),(6,'brand','Tuffpaulin',NULL,'#673ab7','Heavy-duty covers',0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(7,'brand','HITECH',NULL,'#607d8b','Corporate & General',0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(8,'category','VCI Packaging',1,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(9,'category','VCI Emitters',1,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(10,'category','VCI Sprays',1,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(11,'category','Rust Preventive Oils',1,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(12,'category','Cleaners',1,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(13,'category','Coatings',1,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(14,'category','Bio-Degradable Cleaners',2,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(15,'category','Eco-Friendly Oils',2,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(16,'category','Sustainable Packaging',2,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(17,'category','Void Fill Systems',3,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(18,'category','Air Cushions',3,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(19,'category','Paper Packaging',3,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(20,'category','Freshness Preservation',4,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(21,'category','Ethylene Control',4,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(22,'category','Cold Chain',4,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(23,'category','Desiccants',5,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(24,'category','Silica Gel',5,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(25,'category','Container Desiccants',5,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(26,'category','Heavy Duty Tarps',6,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(27,'category','Industrial Covers',6,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(28,'category','Pond Liners',6,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(29,'category','Industrial Assets',7,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(30,'category','Corporate Docs',7,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(31,'category','Training Material',7,NULL,NULL,0,'2026-05-04 14:00:10','2026-05-04 14:00:10'),(32,'brand','RUST-X',NULL,'#f44336','VCI & corrosion protection',0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(33,'category','VCI Packaging',32,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(34,'category','VCI Emitters',32,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(35,'category','VCI Sprays',32,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(36,'category','Rust Preventive Oils',32,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(37,'category','Cleaners',32,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(38,'category','Coatings',32,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(39,'brand','Dr.Bio',NULL,'#4caf50','Biodegradable solutions',0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(40,'category','Bio-Degradable Cleaners',39,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(41,'category','Eco-Friendly Oils',39,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(42,'category','Sustainable Packaging',39,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(43,'brand','Fillezy',NULL,'#ff9800','Void fill systems',0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(44,'category','Void Fill Systems',43,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(45,'category','Air Cushions',43,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(46,'category','Paper Packaging',43,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(47,'brand','KIF',NULL,'#2196f3','Fresh produce packaging',0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(48,'category','Freshness Preservation',47,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(49,'category','Ethylene Control',47,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(50,'category','Cold Chain',47,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(51,'brand','ZOrbit',NULL,'#ff5722','Desiccants & absorbents',0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(52,'category','Desiccants',51,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(53,'category','Silica Gel',51,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(54,'category','Container Desiccants',51,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(55,'brand','Tuffpaulin',NULL,'#673ab7','Heavy-duty covers',0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(56,'category','Heavy Duty Tarps',55,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(57,'category','Industrial Covers',55,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(58,'category','Pond Liners',55,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(59,'brand','HITECH',NULL,'#607d8b','Corporate & General',0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(60,'category','Industrial Assets',59,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(61,'category','Corporate Docs',59,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38'),(62,'category','Training Material',59,NULL,NULL,0,'2026-05-21 18:06:38','2026-05-21 18:06:38');
/*!40000 ALTER TABLE `library_taxonomies` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-22 11:59:56
