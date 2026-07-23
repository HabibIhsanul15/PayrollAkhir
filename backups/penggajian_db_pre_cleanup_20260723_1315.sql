-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: penggajian_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `penggajian_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `penggajian_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `penggajian_db`;

--
-- Table structure for table `allowance_types`
--

DROP TABLE IF EXISTS `allowance_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `allowance_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `calculation_type` enum('per_mandays','per_trip','flat','formula','per_hour','per_toddler') NOT NULL,
  `input_source` varchar(50) DEFAULT NULL,
  `condition_field` varchar(50) DEFAULT NULL,
  `condition_operator` varchar(10) DEFAULT NULL,
  `condition_value` decimal(14,2) DEFAULT NULL,
  `applies_to` enum('all','project_only','fix_rate_only') NOT NULL DEFAULT 'all',
  `display_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `allowance_types_code_unique` (`code`),
  KEY `allowance_types_applies_to_is_active_index` (`applies_to`,`is_active`),
  KEY `allowance_types_display_order_index` (`display_order`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `allowance_types`
--

LOCK TABLES `allowance_types` WRITE;
/*!40000 ALTER TABLE `allowance_types` DISABLE KEYS */;
INSERT INTO `allowance_types` VALUES (1,'transport_trip','Transport Trip','per_trip','business_trips',NULL,NULL,NULL,'all',1,'Transport Trip (perpindahan project)',1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(2,'meal','Tunjangan Makan','per_mandays','total_mandays',NULL,NULL,NULL,'all',2,'Tunjangan Makan di project',1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(3,'position','Tunjangan Jabatan','flat',NULL,NULL,NULL,NULL,'all',3,'Tunjangan Jabatan (promosi/probation)',1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(4,'childcare','Tunjangan Pengasuh (Childcare)','per_toddler',NULL,NULL,NULL,NULL,'all',4,'Tunjangan Pengasuh (Childcare) untuk Project Partner',1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(5,'training','Tunjangan Training','per_mandays','training_days',NULL,NULL,NULL,'all',5,'Tunjangan Training (Trainer)',1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(6,'business_trip','Tunjangan Perjalanan Dinas (Luar Kota)','per_mandays','total_mandays',NULL,NULL,NULL,'all',6,'Tunjangan Perjalanan Dinas (Luar Kota) untuk Fix Rate Partner',1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(7,'ho_transport_meal','Tunjangan Transport & Makan (Harian HO)','per_mandays','total_mandays',NULL,NULL,NULL,'all',7,'Tunjangan Transport & Makan (Harian HO) untuk Fix Rate Partner',1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(8,'transport_insurance','Tunjangan Transport & Asuransi (Luar Kota)','per_mandays','total_mandays',NULL,NULL,NULL,'all',8,'Tunjangan Transport & Asuransi (Luar Kota / Project)',1,'2026-07-21 22:40:22','2026-07-21 22:40:22');
/*!40000 ALTER TABLE `allowance_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `payroll_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  KEY `audit_logs_payroll_id_foreign` (`payroll_id`),
  KEY `audit_logs_action_created_at_index` (`action`,`created_at`),
  CONSTRAINT `audit_logs_payroll_id_foreign` FOREIGN KEY (`payroll_id`) REFERENCES `payrolls` (`id`) ON DELETE SET NULL,
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,3,1,'PAYROLL_AUTO_CALCULATE','127.0.0.1',NULL,'{\"employee_id\":16,\"period_month\":\"2026-07\",\"warnings\":[]}','2026-07-22 01:06:02','2026-07-22 01:06:02'),(2,3,1,'PAYROLL_SUBMIT','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','[]','2026-07-22 01:06:03','2026-07-22 01:06:03'),(3,3,2,'PAYROLL_AUTO_CALCULATE','127.0.0.1',NULL,'{\"employee_id\":7,\"period_month\":\"2026-07\",\"warnings\":[]}','2026-07-23 00:29:15','2026-07-23 00:29:15'),(4,3,2,'PAYROLL_SUBMIT','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','[]','2026-07-23 00:29:16','2026-07-23 00:29:16'),(5,1,2,'PAYROLL_APPROVE','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','{\"note\":null}','2026-07-23 00:30:12','2026-07-23 00:30:12'),(6,3,2,'PAYROLL_MARK_PAID','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','{\"paid_proof_path\":\"payroll_proofs\\/AY0CjB9OMzxQv4pmNHJSKdfjfYwfbI0oW3vW16vc.pdf\",\"paid_ref\":\"TRF-202606-00002\"}','2026-07-23 00:30:33','2026-07-23 00:30:33'),(7,7,2,'PAYROLL_VIEW_DETAIL','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','[]','2026-07-23 00:32:21','2026-07-23 00:32:21'),(8,7,2,'PAYROLL_VIEW_PDF','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','[]','2026-07-23 00:32:31','2026-07-23 00:32:31'),(9,3,NULL,'PAYROLL_AUTO_CALCULATE','127.0.0.1',NULL,'{\"employee_id\":20,\"period_month\":\"2026-07\",\"warnings\":[]}','2026-07-23 00:34:25','2026-07-23 00:34:25'),(10,3,NULL,'PAYROLL_SUBMIT','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','[]','2026-07-23 00:34:27','2026-07-23 00:34:27'),(11,7,2,'PAYROLL_VIEW_DETAIL','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','[]','2026-07-23 03:45:28','2026-07-23 03:45:28'),(12,1,NULL,'PAYROLL_REJECT','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','{\"note\":\"cek kembali keuangannya\"}','2026-07-23 03:57:40','2026-07-23 03:57:40'),(13,3,NULL,'PAYROLL_SUBMIT','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','[]','2026-07-23 03:58:25','2026-07-23 03:58:25'),(14,1,NULL,'PAYROLL_REJECT','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','{\"note\":\"cek kembali keuangannya\"}','2026-07-23 03:59:37','2026-07-23 03:59:37'),(16,3,4,'PAYROLL_AUTO_CALCULATE','127.0.0.1',NULL,'{\"employee_id\":20,\"period_month\":\"2026-07\",\"warnings\":[]}','2026-07-23 04:27:53','2026-07-23 04:27:53'),(17,3,4,'PAYROLL_SUBMIT','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','[]','2026-07-23 04:27:53','2026-07-23 04:27:53'),(18,1,4,'PAYROLL_REJECT','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','{\"note\":\"cek\"}','2026-07-23 05:05:59','2026-07-23 05:05:59');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crypto_keys`
--

DROP TABLE IF EXISTS `crypto_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crypto_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `alg` varchar(255) NOT NULL,
  `public_key_pem` text NOT NULL,
  `private_key_pem_enc` text NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crypto_keys`
--

LOCK TABLES `crypto_keys` WRITE;
/*!40000 ALTER TABLE `crypto_keys` DISABLE KEYS */;
INSERT INTO `crypto_keys` VALUES (1,'payroll-rsa-2026-01','RSA-2048','-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAoqQfKdcFWk12FaZAc4P7\n1SA/kifXNtT59SQjpMuV1TKPipASgPWNazzIuuHVED3LJlWqWRYo7smyoQTAFQ3F\nxD5poxYtKdhKsV94rSCTqI8P5CaViCMOk4P+3a0e8E6k863VsRRCC9JvRLlK5xCM\nekb2r8pwdtTXX9JqipH0kxVDAX1jdlTG37+2orqkZi/VNruW/9tsITrQMYehXHYn\nZYafav1J08ZYZqMbpvaevg0S0/pSpyedVInQMcd+tbPTBKTBlicMbn7/+EugCpru\nzzW21qlSfKkOqKMkWnQSSZpyMzHAtYTiglXhTsJxm4tKwxsMO4vLu0YHqECQAdwJ\n0wIDAQAB\n-----END PUBLIC KEY-----\n','eyJpdiI6ImVsM0NKbFRPd2psNE5kV2x4Zks4N0E9PSIsInZhbHVlIjoicXc4Vi9mQnZPS0tiTDlXOWFnQnZmWlQvMExJbjMvaEZtT1B1TGY4YXBJR2RnRmtUbDFqTkZpbTRzL2ZBaWlVNmN4ZFIwOEJtdUJaYUcrSzNtTGZWUGlVcFFYVWpUMG5ZWWlOV1oyTE5hTlBlMVMvelFLVDg2QUZSUHlGWnk1THZLeUlTQnpvbDlyUjZJalJBNEltWlc4YjY5a2VKMVlWdzVkZCtSZzF5bEFtYkJlY3JrbUdtakVKMk5DWFd3UWl1alk4L2x2WG1LcVpvaDBubFZvbnUrSklnQUFqNDY2WnBubk9wTjN2WnFLeXBTaUNXMGF1WEkydnhlYWlSN1hzTFJKSUFiUU5EUHdpZzR1QnJLcm5Jd0wvZmpiOTFNQVF6eU9qaXR3RjFaNlZNTWlDWWlTdFVVNE80UWtOK3RzOUhEb2J1S2ZkSkdsK0o2am1qOUtyaUd0Rk5mVFdUVnZCbjNEMWNDcTZIazVlbXIrNVVSZ0tlQTg5bGgxMFc2Ylkwa3JqczROVFhkNTVzMWU4RTZ2bHk2V1dIY3R4R1FwYzJnQysrRThHNzh4dnJwRHVSRVp3c2VvTzFMMEk2YUJPQ0VlTy9URERBcTRRTysxS0NUWDExdkRYMEl2SytCSEpiVUxSQ2xWcWZlVXVpazFobUJyZU1OSnBrdGh1b0trNFE5R0thcGZhK1NZaHl4YjhTdTJmem4xMmtINWY1aTBJQXZpVDAyb3JtQjlHcW5IbHovdndWb3M3bVVlUlZORUVQeHVPeE9ZbEV6UUVOcmM2YjVlNFA5RC9rZjBQM1RrbzVQUjFSTnV1WmU5dG9TeDlGKzhCdzVSVjNZVjRpaGY0dEx2dVdtL2czeUlwR2xnMEZKRGllVmNSYXRBUm84RlN5QmRzZlNFZTY0UklzcUc1K3JGM09WZVhDelAxUmRsVG9EUHdONC96TU1Nc0VtdTVUTVJveGFkTi9keFY1bU1ZdHhXM2RUWG13Y2tmUGgzdjJMNnllUGlwUkpCK3NIdnB4T1pxamt5TW5aeXArWkJQVGdNZGtkMmVKWkduaGpFQnVJeW9tSzJIUVprcFluTHRzWm1lakZ6N2lKUmM0QmYvbXloZEVCbUw3RjlUM2FZOTR5ekl4UmlMclZvYmFIVHR1Y0FIUGtsTkw1a2R2Zk8wbktWdER5bjJPNEJJbUJDbnNST1RUZ25WVzZPVitkYS9TZkxhN0s1NUZkaER5VkZBc2tlbkI2ZkRNbmh1Zm9nT1dMSGdScWxpYTVPZXpVOExkVEh5bEYvVVU4UzFIK2Y2RjVqWnU2Sld2WTRuVkxHbjg3aUg4TXhwcDJuVjc1THlEeVU4dnBrS29EYzdtSFg0djVKUlEyNTJ6SVlQTEZHa2NxbUJMZEIzYjE4ZFAxRE1JMGV3YTlBZFBGcjlVYyt4aG5Jc0VjZjdRcXFhUzRoaWxFSzlRK3pScXlzWlBROXdIRG9yNTJPVE5ZRkNDOTQvOFdKVCthR3c0MGNkMzVOM3JHWjQyWVY5TlFrVlVJVXh2RjlyL3Zpb1F5azdjVDl2blAwNnNCdTc5S1plaGxRcEJmTTNpRjI2TUF1Nzc3Q2NJaWhVWU9iNUpYSW1lbWpHWVNHWVBhVFFEdHBpSTczbHhKMVZMTkhnbkw5THREL0M4OGhOS21RTmxIUldYelgvSUdxdE5FVkdUUmhWWnJmYUhRQkVKaEhCYlFWckQ4S0NxN28weWlXVVA4UkFlRXdSby90d0Faa21iWGphamFzQjRINTRMeW94L1V2OXRiMEhHcmRqdG1oUUtkZnJHUHBkZ3JIV1JzL3BEQ0ZxOHZCTStaejRmTTFDanBMU3ZZQ04wczlqVi9ydkJ2VlVkWXdtdXpTWTJYV3dMczJ2VEZvZDR3OXRTQUtwZFRqSjk1bklIYmRQRiszRUxKdTJ3enhGT0hMblBGVUZqVWtucE56bVJ6Skw5Q2Q2NnVJSTlld2Jjc1dnckc5WWMwNDdNaUh2blQ2VXhQT2l0MlJ2RnFrbGRKK3g3a2pmWXhYc3hNRWxJRTkyTzdYNElrRzY4clZyQjliZVZlM3o0YTgvWTJNSU5SZ0p3VUttSzJML0ExcEJkU1RUNVdEZkFOcU0zOEwvdjVuczlPLzdPbzFEeklwVnc5dDFJUXRRc2pLeGkrZ3pGdnVpOWdQNm9mNTZaak9ocVR0T0tZNXROMHkzd2lVZkxDaHdoMm5oUEJvcWI2V2xtbzNHN050a0RPQjgvWEZOZ3NWRWZYN3JqamI2bVpVUzNNQjhsTmpSdFZmSVNKNVdUNnlCV0JHR3BxcHR3YWhGajJDNksvL1BXZWZBaGlreERkSDl0UW5FVnJyeFVxZUZsWFlwYy9lZ3hrYkUzNVZ2dkRMNDRNTjVLb25aT09NeW1JVnBSYVpwdkR3MDRZVXdoT0lBeU5SdGZXWm9kSmNZenNYWUo5d2JKQnJwS3RHenBCeTllWE05YVk1SVpWaWxpT2RveUhxeGwyM1lPZnUvWDBrdzQ5ajNucHN5d0kvOVlWa3YxSlJvbFZOUUhyNmRsTFhtRWkvaytEV3Y2SDdPQ3ZGekFJQmg4Qk5VM1V4b3p0c1BtUEp0SjErS0VCelQ1TzkzakJZODlqbk82KzE0ZGZxbmV6UCs2K0JKd2RSMUg0MEo3SkM2SmcrWTlpeWQ1NjBQcnh3SHc0WVRXMzhnTWRuUnpVZ3docFd1UVRpa2J1ZzJpMnpUZFFubmlFMWdPNmU2T2N6MXMwNXBXai9NakJpZ2dqb3ZRT2NtRkQyZ2dpb2dIaUtVRHNhckZoTDhraVh0R3N6UEpSK1Ftdk80S20wWHRaZFRzTThRRDliMktzMmlVSXpEdjNCaFgvQjkvemF3d0ZQdmFRcXJyaVB3a1RHcEh0Uk1xV1pkZ3JPN0ZKRjBGYmFBRlc1ckwzQ3J2eUF5TVlSWE5RUjU2d1VKbW5kUkZwNW5UdHVESElmM3VydUpXYWFVQjNweWhGOXRZUDl4dGtQYUhyMjNMVXpzUGJBRU1LRmwrak1VPSIsIm1hYyI6ImMwM2NlYTViZWUxMzFhNjQ1ODVjZTEwMGI2OTY5N2ZhODQ5MGI2MGFlM2RiOWExMjdkOTJhN2FkN2RkMWZiOGIiLCJ0YWciOiIifQ==','active','2026-07-22 01:01:57','2026-07-22 01:01:57');
/*!40000 ALTER TABLE `crypto_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deduction_types`
--

DROP TABLE IF EXISTS `deduction_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deduction_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `deduction_types_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deduction_types`
--

LOCK TABLES `deduction_types` WRITE;
/*!40000 ALTER TABLE `deduction_types` DISABLE KEYS */;
INSERT INTO `deduction_types` VALUES (1,'bpjs_kesehatan','BPJS Kesehatan',1,'Nominal bagian karyawan yang dipotong dari payroll.',1,'2026-07-21 22:40:21','2026-07-21 22:40:21'),(2,'bpjs_tk_jht','BPJS TK - JHT',2,'Nominal bagian karyawan yang dipotong dari payroll.',1,'2026-07-21 22:40:21','2026-07-21 22:40:21'),(3,'bpjs_tk_jp','BPJS TK - JP',3,'Nominal bagian karyawan yang dipotong dari payroll.',1,'2026-07-21 22:40:21','2026-07-21 22:40:21'),(4,'employee_loan','Pinjaman Karyawan',4,'Cicilan atau pengembalian pinjaman karyawan.',1,'2026-07-21 22:40:21','2026-07-21 22:40:21'),(5,'other','Potongan Lainnya',5,'Potongan manual lain sesuai kebijakan perusahaan.',1,'2026-07-21 22:40:21','2026-07-21 22:40:21');
/*!40000 ALTER TABLE `deduction_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `employee_code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `join_date` date DEFAULT NULL,
  `nik_enc` longtext DEFAULT NULL,
  `npwp_enc` longtext DEFAULT NULL,
  `phone_enc` longtext DEFAULT NULL,
  `address_enc` longtext DEFAULT NULL,
  `pii_alg` varchar(20) NOT NULL DEFAULT 'AES',
  `pii_key_id` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_name` varchar(150) DEFAULT NULL,
  `bank_account_number_enc` longtext DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `position_id` bigint(20) unsigned DEFAULT NULL,
  `num_toddlers` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Jumlah balita, digunakan untuk syarat Tunjangan Pengasuh',
  `is_trainer` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Kategori trainer, digunakan untuk Tunjangan Training (1.5x rate)',
  `is_on_probation` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Masa percobaan promosi, Tunjangan Jabatan 50%',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_employee_code_unique` (`employee_code`),
  KEY `employees_user_id_foreign` (`user_id`),
  KEY `employees_position_id_foreign` (`position_id`),
  CONSTRAINT `employees_position_id_foreign` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,1,'DIR-001','Test Director','2024-10-22','yaCakR/a7o0wZd11IdCHRAJzEP/lFakrFr+Wpc2T/xMrWQgSnGBZFoGSyFw=','COOwwDP9k3MUAE9ore02dyImap3QHYj+3+zVEI/fO+Cnv8aAgrlBO6y/WctzgX3G','+wMF6W14GxsLwR3juBkXEitmuWbSeYyzcN65G0KhvAEKyov34dYRWQ==','+LKmIYjiY/H1MuUC8H4+DNpcI1c/2u2CUF1gFECtiIlZg7UEwAIyeBxB43S6uo+xHCC4L2Ga/pP9s+H6CB1CWQ==','AES','aes128:7a51d064a1a2','BCA','Test Director','leuO7q6wvqueSSCwBhfRwjBzxavgyc7ETXErX6NMbVqijjIMHP4=','Board of Directors','Manager',5,0,0,0,'active','2026-07-21 22:40:22','2026-07-21 22:40:22'),(2,2,'HR-001','Test HCGA','2025-06-22','xIjl4nYEdjRwE1vq+/pamQujfvbl4JhVIlyROayUmDCruC4oW82LQ4JRUms=','gdTRwEaoUK0p3WuKd+0wREAcKeE0lucRl63I9fPp0fgUoZiWIiYnO+RE6bML9Atf','bMMr7ej62se2tQFVVrcnIVi9oPrtaTw0awsa1kY9+5Z9chPhCJYsVg==','UGRAHLQXRIImbRB4N84piPKcSvPr/obov8t94l4CYDDYgpVE8F+XuOyeY19T13l21M4N1YQDkESUX2D3PlJBVw==','AES','aes128:7a51d064a1a2','Mandiri','Test HCGA','CLnH0p1fkL0+rddbszZeILpeAdEm0lJJcxD9pp6DpPYp7ZqvDbG4PU8=','Human Capital','Manager',5,2,0,0,'active','2026-07-21 22:40:22','2026-07-21 22:40:22'),(3,3,'FIN-001','Test FAT','2024-01-22','2yCabNE47ZmOgzmwZe4PQ2fukcE+7AaEejFjjGDhQcvBA45b5zwEUn+MyNk=','NwmV6ER2EqzUtsBpLw0OOdAZkf839yccb5KXE5I9wFFvg0pcXgexb1PgqGvGL0W3','dSmBxIIzHmircPqOMO61SN72jPs85hNxvuQRgrfoTCNlNYeiO9+gWg==','v+ExeRBO8FCuDEL9c0Ox/I8rd9COL5MVZKqjq8nI+7thqSxM7oUuc86DD1SKYTxLJ7m1C0MpiiY0LJegRHdIGA==','AES','aes128:7a51d064a1a2','BNI','Test FAT','CaLYT5PAmq0mS2yAO+hglaQ8AfYZ0NMmcKOtxXupiZ3aPzyDJQQ=','Finance','Manager',5,1,0,0,'active','2026-07-21 22:40:23','2026-07-21 22:40:23'),(4,4,'OPS-001','Test Staff','2025-04-22','H3ryghpLg1IY0/xl77UGZvjH9eucxkTWWzstN734Cybjd/yWVTsJOInUbWI=','rQKVvDfGoNPYnnR6v85HpFLmdl+qw9WLeiijxukLX0w43+P9X/AQ5yRtRap9SWnt','bLBij2fO31GV7RUItwJ1LikQHNF0eQLvSn2yStdpwA9/YV4eIdm6vg==','XV+vfXnVDydPPLFolDC76XZAoHgO+ISc5TKth2vdPBxNkF3d3zMmocASah9ICa2XVbHehr1T7ltnMe+kK2W34A==','AES','aes128:7a51d064a1a2','BRI','Test Staff','aTo9HBjjhFVZxnWQooix2oxTHuxV8usr7xNO5yhB0vbgXIqta1w=','Operations','Staff',8,1,0,0,'active','2026-07-21 22:40:23','2026-07-21 22:40:23'),(5,5,'OPS-002','Andi Saputra','2024-07-22','udhdYylTxGTAhoXhOk4JRynqLupoDYqTBmyzAyuAShOvXcTXXW32h0HPwsw=','iAglIZWJS9zUctemy6euTgm3WafnT1nd37P0qly3FKJ0IpxjzuG4bjEd/OsZ2Vvo','+gaKvauV9v6cr10qj9COVayJZxPh+vyalwfhf3CYUqPx3FiGVIcWUQ==','icUL8XBkZtH7l9y02lRrjM8jN0d7NXAvlMGXe2LPqcQM4u+zoh47nQ7VUA3OuNwmAkYEO5pLy2UG48SuWpbR6A==','AES','aes128:7a51d064a1a2','BCA','Andi Saputra','7SkSdazgSBnQOLkETLeq6oHeUDrH0dCmDrgfIQ52gMf7LFlwWvo=','Operations','Staff',8,0,0,0,'active','2026-07-21 22:40:23','2026-07-21 22:40:23'),(6,6,'MKT-001','Joko Anwar','2024-08-22','kN3P7uRlDYCXegNr/0o2yuyHDTYoba2IpW471+oTXnsnWxeIjbbBQRqGJsI=','egtfx2tnDp6O223AU3r+cfyJ8oV+a60hks7PeMLbrzEaWi8sRpmp3c7iyOKbbxZu','Auv6OuUK6tp+wKT5KkxBOdcKayxV6j9vnIopGOJv/k8yeAEd4849hQ==','Ntbh7vX/TUREL4BOZhlVBvRNlUaBiIfv+TUd/4GHoUhZkqBmJE+x+tsTo0MIl5BIgK+4WGYRFDU4BHJqMDPFrw==','AES','aes128:7a51d064a1a2','Mandiri','Joko Anwar','cnBQDnzg9HWIVYDR1JzDfzdS3P9lI93RL1q48t3PhkkRFP6Jpwep9K8=','Marketing','Supervisor',7,1,0,0,'active','2026-07-21 22:40:24','2026-07-21 22:40:24'),(7,7,'IT-001','Rina Melati','2023-10-22','oXDAdyV1z+3c28GYJ63zoFKkSV9PtFjyv7B/ETZmxZkL1MluCxdXHDzUEz0=','o/1lYFo/XurEKorAxwOC1pPALNcfQv00W4Ka4JKOTCwpOyfyHzuIsQArl95iPWEi','bbSeW5l4DsKIE5v6ZughzQpjFOmGKBbrBocvuqtuPuxjUosNKHG06Q==','Jy3OP359IYj9Vro829rOR6ei+ZLU+hMrixXLTen6ktjePywaUzV/e86GJsRoq32Ds6j7jozxx0UMFbhtCSn05Q==','AES','aes128:7a51d064a1a2','BCA','Rina Melati','3KOiRJbLNh3ZObqRhfGgTw+BHzxBhQ7RMXGm7r2vRkutSfxOcb8=','IT','Supervisor',7,2,0,0,'active','2026-07-21 22:40:24','2026-07-21 22:40:24'),(8,8,'KND001','Kandidat Pegawai 1','2026-06-30','4tL7loTQOSsGOazy6FhAN4szzgaAZ+cNE07yt10IG2HEMxG9iOhV0imWOMc=','xZwMN87Iirv4VzpyMxHgdNrH1yzkpCghseSZJQEPwfzTabMYqk88M/DUKWFy3ZnP','uzyQFwIcrJMvVq8sqKoNZ1Vs1TtQ/pLQeKsLCBIpSK5gMZ3bCQrElQ==','WPeV2FA0jmX5EXB0bSd1SUbDx0pLyTR6tCk8ucxUVrLMu4h16tYJk6i28A0OqEtC/LaEGak+J4tH2B4Z4Q==','AES','aes128:7a51d064a1a2','BNI','Kandidat Pegawai 1','S/6iRWFqygCf9fnIHRaZcjYLqBwhk4MlRHgC8sRcQJLtotX/+0M=','Operations','Staff',8,0,0,1,'active','2026-07-21 22:40:24','2026-07-21 22:40:24'),(9,9,'KND002','Kandidat Pegawai 2','2026-06-23','l/gqbLV125hCSGh+x8GYktfcELktbb68eP2OhqL9UusA95/2SF0mTy6q6Qc=','VCFNLqQqnePWyMS5Gg+o5goqvKoRB0uh0u2d1OHdqCXAlbORhGkUpx/BXTuIvRBW','gb9G1XYwjll1m4h0ggjPQiu7QPW6fNLGnBt7SmkcaCO3K470rqgfCA==','9dW68Zbl8Rcb/pc5IPVsGmofel0SvbNvrmcMFQQRHTJhEtjgWuZuH9RVfmRWMlKQ/AoquMyR+y6sEAXMIg==','AES','aes128:7a51d064a1a2','BNI','Kandidat Pegawai 2','DVCLXeA837vxJY+/+3AK7fI4fwZQig0PmarkFN6jDq2xo3RPxhA=','Operations','Staff',8,0,0,1,'active','2026-07-21 22:40:25','2026-07-21 22:40:25'),(10,10,'KND003','Kandidat Pegawai 3','2026-07-08','uuPPy6Z6o2QSNZ2ISYSLZZNzxeI3FK+sa5BHQ/fA8DTxndPDX9wz3D1nxmQ=','/1cp40lTGkG5XA+TiyWwtDOQHC6u7yYQ0ErYO6t/2aXAgktPpaNSv8lsaOtre6sP','XZ18S1EomYujG6YYxUvWktlWsNtbLQONCkCkHkUvoX9erYPtKHzJCw==','Uicbd2lY2fDdZjxMwvzpN+8aAWXAO6XtLrnTNlehn/dSKGhjxmFE106B87FYaDgpgsjYHIHtIumiOKp+lw==','AES','aes128:7a51d064a1a2','BNI','Kandidat Pegawai 3','gmRapluXPXwNxWL/UNH9WbsjY422j7San+rnw1eca9s9WpEs/Sk=','Operations','Staff',8,0,0,1,'active','2026-07-21 22:40:25','2026-07-21 22:40:25'),(11,11,'KND004','Kandidat Pegawai 4','2026-07-05','cVWnuLo9PIqWTMagDpkL8EQRbDWNhRo+lKxIEAodk+lYOaTA4xHGJ9wdFZw=','pF7NgmHRt96f2PrApkAryZEd2MpwByKlPI59sOb6X14KtT2uw5VvblShTas1jlru','YQ96R4PJdQuIaqTRD0H8/c0ldBxUtcULEAsFCbwgoZ7ZURj/pOM8RA==','3VNIvgoZOTL18qzdbj1e9i3bHLZ8uB7LLpSSCRe2Qd6BsvgVccnDDJkgZLxLd34N37M7bdgrbBUmAThfiQ==','AES','aes128:7a51d064a1a2','BNI','Kandidat Pegawai 4','MRf6LTt+BLsEP/9NrNtKcVyOngB3x2LP2GnFGb+Gvptv7yfvEcY=','Operations','Staff',8,0,0,1,'active','2026-07-21 22:40:25','2026-07-21 22:40:25'),(12,12,'KND005','Kandidat Pegawai 5','2026-07-18','KMXiecBt/l9P8TuI6vIg5p3Y/4YlkpVoC4MywTbcpb3sKJEADXGL0xG77F4=','ZNVgqGfGU/8pFuDLvtpcCWWJyLu0shaEDFloFutqX/ddpdon+hKErU6b8p7rNQ7Y','gBdqxve9v1woH8fECso3QsO/+GtIXEkX89v858cOmMHvxBpfDxbJYA==','6D0Y8uh/Wrq2CyXR0KaxESuQaEMsrjoWqq3T1nvdZ+riVHKOp6sV7jmop4ShcXqlbdbo4rjKVNyPWB+KSw==','AES','aes128:7a51d064a1a2','BNI','Kandidat Pegawai 5','u6MoxE4/gQGNMxGCuLsK8UdgueQ0spud8J9oxYRfeF6l/KE7UdY=','Operations','Staff',8,0,0,1,'active','2026-07-21 22:40:26','2026-07-21 22:40:26'),(13,13,'EMP-101','Ahmad Subagyo','2023-02-22','Xqnmb9+GPcaNihyZQpxsYxtrR509isvaSW3V0J8TPo7M92wiB4iKIlEHZ5w=','ZTojkrWSlpceJ95u8O8z80NDkNVh4LMgla3j0zCXsqMoFgA2/gaSUVBqiRQ/BbMb','epx45HD54VYOFDGefdNNfjFBa7USoLbBENQEyMeWYnqPk3X5LQrB1Q==','Qy9gfkCPDDr1iK8qq40Edu1sFtXOOPQO9BmY0GfIFJz3WhyeEMdCa3/85YP6yYD5KzTbBecqXu+YEg==','AES','aes128:7a51d064a1a2','BRI','Ahmad Subagyo','jC2I/yVTquczXiEjRXIXnxdCl5iO5iDELFVwDBqaSMqdOn1v5jg=','Operations','Staff',8,1,0,0,'active','2026-07-21 22:40:26','2026-07-21 22:40:26'),(14,14,'EMP-102','Nina Susanti','2025-07-22','K61bKp676gJusd3Z+X45u7hAtz5PTCfE9ROCmGF5WpZ8ocZup8CQ7ZlQjmw=','A4KZx++PWRgdQcMFGEjlz9bJRh5mP41XJZtj1WILvngzQiiASMW2Y9mzWBoM7B/6','8sCRihOUzNaVNxEqKj1W46FQbOJUo6wKDITx1xWokeFngmxXHmGeQQ==','wHe+L2eQn+m8qZ8jTFmhDlrUYmiNpYhvhJ44if0q9kJpRD/GmdWsKjPBTEXVZ0NAEyBDtDhpIMDmIw==','AES','aes128:7a51d064a1a2','BNI','Nina Susanti','tr9slHiG9Xpy6/RrREbX41oO6PB1o4+EDhuJLuQSHsxU530iygA=','Operations','Staff',8,1,0,0,'active','2026-07-21 22:40:26','2026-07-21 22:40:26'),(15,15,'EMP-103','Wahyu Setiawan','2023-12-22','ELWp6yoEHhDlu8d4rVAS5OXPIrEngw2gZTQXBzy5ZdrPZ/RYOIY85CSpDBk=','e26S89q858IOjhNK/wD2CUyuSUI4yHwtBFclhZ1X5HPY4hfgbVIElEL8G6bqFLrE','wMOUwzRItg0bl1c9YZlPXfiEFDIkQYNIvXrI5wYKkgQranLhoef+yg==','QuyEP/oQy3lHPdGNSNddLs5llXIyuiDZ2csbSaLt+ilkGSLyZdQoemgvWmdq4HOa9vhspy3YG47d1Q==','AES','aes128:7a51d064a1a2','Mandiri','Wahyu Setiawan','gkcJO6uL8eUZf9GQm/0Bt0eII99vLTIJONB6nG/VFIYjZAzmpp8=','Operations','Staff',8,1,0,0,'active','2026-07-21 22:40:26','2026-07-21 22:40:26'),(16,16,'EMP-104','Lia Mulyani','2025-02-22','3jMxb8vu3Lw/toJLBwA+o5zOmI6yc7zCUiZ3rnJjvQq45o6okHbiKOMYEnI=','HcvDF7OVpW7JUlG6WbxSZJAm0nXMKuVAcToo8I0KhmbP9N6gZAxKEgZO8Hb7SnnI','c1h4dr2vD/TqMuCbIgiTUPoLxYlW1UxcIlVLjR2GvgF4EH9TnBBGjw==','LeLUV5e/hMg5/YcG/3fwZb3S4EKD5IoU/4eEnfT+x2iOjEzMqHZZUUTzNNbG+B8x5pWkrgkZOAMnsA==','AES','aes128:7a51d064a1a2','BRI','Lia Mulyani','7vWBqlHEGLwY/7Ded46p8Zi5GMq9Y+MxwWXYjWE3KsLH3+be7WE=','Operations','Staff',8,2,0,0,'active','2026-07-21 22:40:26','2026-07-21 22:40:26'),(17,17,'EMP-105','Eko Prasetyo','2025-01-22','4kP3JIaeRJ1/Ofw0vKliCtENgEFBhJ9+ek7xOK2e3sNpW1FPr8c1qYI4l0k=','Jh77Y/63g1kUx6YgxaVTysMJIr+66I7Bz5ThjQgtf9Yeq7Ikn51dy4C2AjXo4XFj','SY+/am4IjgatfmrqBzLwNhTqoQWvv5INhv1yxtk4S3DFWVbS+Kit6A==','ix2Pc+kjI2ZRDRlCyxSaqrCDEjdJQ9oKaRZxUfLnC3U+GNRwx1a04e/UFF9qhjxY7+HrIkQprJG0Mw==','AES','aes128:7a51d064a1a2','BNI','Eko Prasetyo','kMkcsUFa6RrCge40Xs+aFSiev+DPLuUS4iYjpCh6YGZpJWcCSpY=','Operations','Staff',8,1,0,0,'active','2026-07-21 22:40:26','2026-07-21 22:40:26'),(18,18,'EMP-106','Dina Puspita','2024-05-22','uiC+1hznfClONwMVBFnJ8j9x0GQqmpuAcCNhZ/ksB0ZC4F5xo3qzRpImQ0M=','JLiSENTfrRHPrz8PSOLzc8U39XPVcNKXcxOtDDKyAjOvQpc1JtBeIvxGLXkycNiu','8sDP7y9PZ9nHPiQGqmKkUmCoN45V5mLc0qEQwqEQDXPNo2mwBUbB6A==','eVfF3IWwUJ7RFeCv2BvPWO0TmuFCeIvLOdfz2kKSGoFH9yJ77AaqPyjuyBDVtTiZ76kDvCAHatP8xQ==','AES','aes128:7a51d064a1a2','BNI','Dina Puspita','bIGGN0Wmbfr4XYQEVebtTUnT4PZWpE7kIy3ZOwHnNABt8H/49t0=','Operations','Staff',8,1,0,0,'active','2026-07-21 22:40:26','2026-07-21 22:40:26'),(19,19,'EMP-107','Taufik Hidayat','2024-09-22','Ul2nAdKxGKA6ShxO8Y5mt9zgyqkWzin+6bsBAe9/A8JvNRYPIBB1l9YUHl8=','3wGCrzhnR8meW3DPq/k1WVG5dIUVqjhm818gocg9ZZuYOawPHHSbQknc5cpLkDcg','bsLsREe5nja3dn/QXYSFxrVu0YLgHDZAojuFMiJgsrvB9o39qOcLvg==','2Wi6k79qjdJTePFgoA8ROQ9OKGd3FcTkTiKtjpZth9zlR5UYfAUGR3C8i5oupLlXSCh8TuDDuFOLSw==','AES','aes128:7a51d064a1a2','BNI','Taufik Hidayat','OeDQxS4iXEFRqUFi6OIL5pkxhVOMkSwoPqBB7+BFdvlMlzs1sAo=','Operations','Staff',8,2,0,0,'active','2026-07-21 22:40:26','2026-07-21 22:40:26'),(20,20,'EMP-108','Rika Amalia','2024-03-22','/UTBYroJp7+C4dLCif8iuNC+aiQvTKGy/lT2lsytTYEGhOsYplmuUIcw11E=','csJAQGcstr6XKt6JBPaZIFoeoITNZ1J1GvW1ezYgd9lqox5Xg3hySqoQwJHlXkUv','iowSvpTRvGn+EfzJvuJgHfkDb/qf2YQ6D7oMCwiKAPbMzxuyaRs3PQ==','A6pIID8Z4Q0/sBO/MUFgunv3sewElKDjbTSoByw8d6zziM1jdHOzBjYKhzWXL4kuVzVrokAxZ2HegA==','AES','aes128:7a51d064a1a2','BCA','Rika Amalia','AmW4ShiKjhUffElLWL1/Db8rY+iaRJjhru8pupy2sTT+lC0nK0I=','Operations','Staff',8,3,0,0,'active','2026-07-21 22:40:26','2026-07-21 22:40:26'),(21,21,'EMP-109','Surya Saputra','2024-01-22','x47pK6YQoX6eU/it8PJUHbwCqbmAc6idGoR7tSUhnCSC4/Bj1Zd7hU1WT7E=','mqfSJ0UHiPbcS6C5Dn/f+vYoVvZy1ux2vzGljfwGWEFE2juMAdwVUYpKfG9IlEhS','AHmjDapg9ndFMj2zmIINXwHvdtHcfR1Exbo7bVuI3F1pTtPampdagA==','BOGzo2FxNE0Xr+MCNznaNjVTBH1bBvqV1w5Pt/QFCe4YlrqzRNVsdA0QalHDrml7mHk69vU1WCs+AQ==','AES','aes128:7a51d064a1a2','BNI','Surya Saputra','3ICJwx2rdFW4CfFOcSwZKe//2iswqz8mxGUW6OVk8Tg/b34bkwg=','Operations','Staff',8,2,0,0,'active','2026-07-21 22:40:26','2026-07-21 22:40:26'),(22,22,'EMP-110','Maya Indah','2022-07-22','D17AA7S6kwwaM/3ehcMvB+Kph2svzVBFLYjIl+PdoxFKqBALa7maAfk96wc=','VBA5Zor2/eCIPs4ymCYl9Dy/M9dE6CL8uaKCGqOe2eyNFA8r9/kiVdW4uir2zkPn','yM1XFwBXfyvjSNemLFgd/iyHMhnqLk8EaV+yAvLJ4f2oCWjr4CqGuw==','ObQAGhb5rTzLB6K3OcU9S71WHzL4wKYsM44u4DJAH3wfj/6WZOpPea0JJxvQrpVq6EiZ336XJo8kHMo=','AES','aes128:7a51d064a1a2','Mandiri','Maya Indah','QMoI0fXbwG3sx7lPHURA5Msl2XshTBSe7R/w6zdQ9KFKgwHbwd0=','Operations','Staff',8,2,0,0,'active','2026-07-21 22:40:26','2026-07-21 22:40:26');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
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
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_histories`
--

DROP TABLE IF EXISTS `job_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `position_id` bigint(20) unsigned DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_histories_position_id_foreign` (`position_id`),
  KEY `job_history_employee_start_index` (`employee_id`,`start_date`),
  CONSTRAINT `job_histories_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_histories_position_id_foreign` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_histories`
--

LOCK TABLES `job_histories` WRITE;
/*!40000 ALTER TABLE `job_histories` DISABLE KEYS */;
INSERT INTO `job_histories` VALUES (1,1,5,'Manager','2025-07-01',NULL,'active',NULL,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(2,2,5,'Manager','2025-07-01',NULL,'active',NULL,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(3,3,5,'Manager','2025-07-01',NULL,'active',NULL,'2026-07-21 22:40:23','2026-07-21 22:40:23'),(4,4,8,'Staff','2025-07-01',NULL,'active',NULL,'2026-07-21 22:40:23','2026-07-21 22:40:23'),(5,5,8,'Staff','2025-07-01',NULL,'active',NULL,'2026-07-21 22:40:23','2026-07-21 22:40:23'),(6,6,7,'Supervisor','2025-07-01',NULL,'active',NULL,'2026-07-21 22:40:24','2026-07-21 22:40:24'),(7,7,7,'Supervisor','2025-07-01',NULL,'active',NULL,'2026-07-21 22:40:24','2026-07-21 22:40:24'),(8,8,8,'Staff','2026-07-01',NULL,'active',NULL,'2026-07-21 22:40:24','2026-07-21 22:40:24'),(9,9,8,'Staff','2026-07-01',NULL,'active',NULL,'2026-07-21 22:40:25','2026-07-21 22:40:25'),(10,10,8,'Staff','2026-07-01',NULL,'active',NULL,'2026-07-21 22:40:25','2026-07-21 22:40:25'),(11,11,8,'Staff','2026-07-01',NULL,'active',NULL,'2026-07-21 22:40:25','2026-07-21 22:40:25'),(12,12,8,'Staff','2026-07-01',NULL,'active',NULL,'2026-07-21 22:40:26','2026-07-21 22:40:26'),(13,13,8,'Staff','2025-07-01',NULL,'active','Karyawan Dummy','2026-07-21 22:40:26','2026-07-21 22:40:26'),(14,14,8,'Staff','2025-07-01',NULL,'active','Karyawan Dummy','2026-07-21 22:40:26','2026-07-21 22:40:26'),(15,15,8,'Staff','2025-07-01',NULL,'active','Karyawan Dummy','2026-07-21 22:40:26','2026-07-21 22:40:26'),(16,16,8,'Staff','2025-07-01',NULL,'active','Karyawan Dummy','2026-07-21 22:40:26','2026-07-21 22:40:26'),(17,17,8,'Staff','2025-07-01',NULL,'active','Karyawan Dummy','2026-07-21 22:40:26','2026-07-21 22:40:26'),(18,18,8,'Staff','2025-07-01',NULL,'active','Karyawan Dummy','2026-07-21 22:40:26','2026-07-21 22:40:26'),(19,19,8,'Staff','2025-07-01',NULL,'active','Karyawan Dummy','2026-07-21 22:40:26','2026-07-21 22:40:26'),(20,20,8,'Staff','2025-07-01',NULL,'active','Karyawan Dummy','2026-07-21 22:40:26','2026-07-21 22:40:26'),(21,21,8,'Staff','2025-07-01',NULL,'active','Karyawan Dummy','2026-07-21 22:40:26','2026-07-21 22:40:26'),(22,22,8,'Staff','2025-07-01',NULL,'active','Karyawan Dummy','2026-07-21 22:40:26','2026-07-21 22:40:26');
/*!40000 ALTER TABLE `job_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2025_12_25_111816_create_personal_access_tokens_table',1),(5,'2026_01_06_141217_create_crypto_keys_table',1),(6,'2026_06_06_000003_create_positions_table',1),(7,'2026_06_06_000004_create_allowance_types_table',1),(8,'2026_06_06_000005_create_position_allowance_rates_table',1),(9,'2026_06_06_010001_create_employees_table',1),(10,'2026_06_06_010002_create_salary_profiles_table',1),(11,'2026_06_06_010003_create_payrolls_table',1),(12,'2026_06_06_104832_create_payroll_allowances_table',1),(13,'2026_06_06_104833_create_payroll_deductions_table',1),(14,'2026_06_06_113558_create_phase3_tables',1),(15,'2026_07_09_142747_create_job_histories_table',1),(16,'2026_07_12_000002_create_audit_and_performance_logs',1),(17,'2026_07_14_044008_create_payroll_periods_table',1),(18,'2026_07_14_144100_create_mutation_requests_table',1),(19,'2026_07_15_100000_create_deduction_types_table',1),(20,'2026_07_15_100003_create_special_deductions_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `monthly_recaps`
--

DROP TABLE IF EXISTS `monthly_recaps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `monthly_recaps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `period_month` char(7) NOT NULL,
  `wfo_days` decimal(8,2) NOT NULL DEFAULT 0.00,
  `wfh_days` decimal(8,2) NOT NULL DEFAULT 0.00,
  `out_of_town_days` decimal(8,2) NOT NULL DEFAULT 0.00,
  `business_trips` int(11) NOT NULL DEFAULT 0,
  `training_days` decimal(8,2) NOT NULL DEFAULT 0.00,
  `overtime_hours` decimal(8,2) NOT NULL DEFAULT 0.00,
  `total_mandays` decimal(8,2) NOT NULL DEFAULT 0.00,
  `late_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_finalized` tinyint(1) NOT NULL DEFAULT 0,
  `finalized_by` bigint(20) unsigned DEFAULT NULL,
  `finalized_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `salary_profile_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recap_emp_period_profile_unique` (`employee_id`,`period_month`,`salary_profile_id`),
  KEY `monthly_recaps_finalized_by_foreign` (`finalized_by`),
  KEY `monthly_recaps_salary_profile_id_foreign` (`salary_profile_id`),
  CONSTRAINT `monthly_recaps_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monthly_recaps_finalized_by_foreign` FOREIGN KEY (`finalized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monthly_recaps_salary_profile_id_foreign` FOREIGN KEY (`salary_profile_id`) REFERENCES `salary_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `monthly_recaps`
--

LOCK TABLES `monthly_recaps` WRITE;
/*!40000 ALTER TABLE `monthly_recaps` DISABLE KEYS */;
INSERT INTO `monthly_recaps` VALUES (1,16,'2026-07',20.00,0.00,0.00,0,0.00,0.00,20.00,0,1,2,'2026-07-22 08:03:28','2026-07-22 01:00:36','2026-07-22 01:03:28',16),(2,7,'2026-07',20.00,0.00,0.00,0,0.00,0.00,20.00,0,1,2,'2026-07-23 06:48:09','2026-07-22 23:47:26','2026-07-22 23:48:09',7),(3,20,'2026-07',20.00,0.00,0.00,0,0.00,0.00,20.00,0,1,2,'2026-07-23 07:28:25','2026-07-23 00:28:05','2026-07-23 00:28:25',20);
/*!40000 ALTER TABLE `monthly_recaps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mutation_requests`
--

DROP TABLE IF EXISTS `mutation_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mutation_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `target_position_id` bigint(20) unsigned NOT NULL,
  `mutation_type` varchar(50) NOT NULL DEFAULT 'promotion',
  `effective_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mutation_requests_employee_id_foreign` (`employee_id`),
  KEY `mutation_requests_target_position_id_foreign` (`target_position_id`),
  KEY `mutation_requests_requested_by_foreign` (`requested_by`),
  KEY `mutation_requests_approved_by_foreign` (`approved_by`),
  CONSTRAINT `mutation_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mutation_requests_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mutation_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mutation_requests_target_position_id_foreign` FOREIGN KEY (`target_position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mutation_requests`
--

LOCK TABLES `mutation_requests` WRITE;
/*!40000 ALTER TABLE `mutation_requests` DISABLE KEYS */;
INSERT INTO `mutation_requests` VALUES (1,9,3,'demotion','2026-09-01','Contoh pengajuan dari seeder ke-1',NULL,'rejected',NULL,1,NULL,'2026-07-21 22:40:26','2026-07-21 22:40:26'),(2,14,5,'promotion','2026-08-01','Contoh pengajuan dari seeder ke-2',NULL,'pending',NULL,1,NULL,'2026-07-21 22:40:26','2026-07-21 22:40:26'),(3,15,3,'promotion','2026-08-01','Contoh pengajuan dari seeder ke-3',NULL,'approved',NULL,1,NULL,'2026-07-21 22:40:26','2026-07-21 22:40:26'),(4,7,4,'promotion','2026-09-01','Contoh pengajuan dari seeder ke-4',NULL,'rejected',NULL,1,NULL,'2026-07-21 22:40:26','2026-07-21 22:40:26'),(5,10,4,'promotion','2026-08-01','Contoh pengajuan dari seeder ke-5',NULL,'rejected',NULL,1,NULL,'2026-07-21 22:40:26','2026-07-21 22:40:26'),(6,17,1,'promotion','2026-09-01','Contoh pengajuan dari seeder ke-6',NULL,'pending',NULL,1,NULL,'2026-07-21 22:40:26','2026-07-21 22:40:26'),(7,11,3,'demotion','2026-09-01','Contoh pengajuan dari seeder ke-7',NULL,'approved',NULL,1,NULL,'2026-07-21 22:40:26','2026-07-21 22:40:26'),(8,20,5,'promotion','2026-07-01','Contoh pengajuan dari seeder ke-8',NULL,'pending',NULL,1,NULL,'2026-07-21 22:40:26','2026-07-21 22:40:26'),(9,7,2,'promotion','2026-09-01','Contoh pengajuan dari seeder ke-9',NULL,'rejected',NULL,1,NULL,'2026-07-21 22:40:26','2026-07-21 22:40:26'),(10,2,4,'promotion','2026-08-01','Contoh pengajuan dari seeder ke-10',NULL,'approved',NULL,1,NULL,'2026-07-21 22:40:26','2026-07-21 22:40:26');
/*!40000 ALTER TABLE `mutation_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_allowances`
--

DROP TABLE IF EXISTS `payroll_allowances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_allowances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint(20) unsigned NOT NULL,
  `allowance_type_id` bigint(20) unsigned NOT NULL,
  `mandays` decimal(8,2) DEFAULT NULL,
  `calculation_detail` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`calculation_detail`)),
  `condition_met` tinyint(1) NOT NULL DEFAULT 1,
  `is_manual_override` tinyint(1) NOT NULL DEFAULT 0,
  `amount_enc` text DEFAULT NULL,
  `salary_alg` varchar(20) DEFAULT NULL,
  `salary_key_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_allowances_payroll_id_foreign` (`payroll_id`),
  KEY `payroll_allowances_allowance_type_id_foreign` (`allowance_type_id`),
  CONSTRAINT `payroll_allowances_allowance_type_id_foreign` FOREIGN KEY (`allowance_type_id`) REFERENCES `allowance_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_allowances_payroll_id_foreign` FOREIGN KEY (`payroll_id`) REFERENCES `payrolls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_allowances`
--

LOCK TABLES `payroll_allowances` WRITE;
/*!40000 ALTER TABLE `payroll_allowances` DISABLE KEYS */;
INSERT INTO `payroll_allowances` VALUES (1,1,2,20.00,'{\"calculation_type\":\"per_mandays\",\"units\":20}',1,0,'jYwa5sjfjd+a8rNO2ADjH5LjAWwP/lm3i6yYXnBLTAP2vQ==','AES','aes128:7a51d064a1a2','2026-07-22 01:06:02','2026-07-22 01:06:02'),(2,1,6,20.00,'{\"calculation_type\":\"per_mandays\",\"units\":20}',1,0,'zWLlTmGeo7tGdL2TMF6t3+nzxMMWWMSPGwPYafYXXzUFLdA=','AES','aes128:7a51d064a1a2','2026-07-22 01:06:02','2026-07-22 01:06:02'),(3,1,7,20.00,'{\"calculation_type\":\"per_mandays\",\"units\":20}',1,0,'CiatiQ/Q+ED3m7OdH2MMvkNS3M3v7a8wifX5cH1MjjywZQ==','AES','aes128:7a51d064a1a2','2026-07-22 01:06:02','2026-07-22 01:06:02'),(4,1,8,20.00,'{\"calculation_type\":\"per_mandays\",\"units\":20}',1,0,'x8+WoBBbyTEp/1ambzMiukUtWQ9MGWWIE6zAXpClZuvGTg==','AES','aes128:7a51d064a1a2','2026-07-22 01:06:02','2026-07-22 01:06:02'),(5,2,2,20.00,'{\"calculation_type\":\"per_mandays\",\"units\":20}',1,0,'TYbYmjiuKXIQbRmdEt3joEwGTGLTnGNRcCBn1KScuPivfA==','AES','aes128:7a51d064a1a2','2026-07-23 00:29:15','2026-07-23 00:29:15'),(6,2,6,20.00,'{\"calculation_type\":\"per_mandays\",\"units\":20}',1,0,'zkqMA+J7mJSPyUWzeoFPqN0SIMflfhMbgg8nAccfayLhwBo=','AES','aes128:7a51d064a1a2','2026-07-23 00:29:15','2026-07-23 00:29:15'),(7,2,7,20.00,'{\"calculation_type\":\"per_mandays\",\"units\":20}',1,0,'4P3GzD6iZ2VxRO+q1aNS4ujZkFoLuOu8/O5fk2fdWhTuqw==','AES','aes128:7a51d064a1a2','2026-07-23 00:29:15','2026-07-23 00:29:15'),(8,2,8,20.00,'{\"calculation_type\":\"per_mandays\",\"units\":20}',1,0,'mIrO82PDWvtpT1zga46lEuqvnUBq7BhVSjYsFHIGw415YQ==','AES','aes128:7a51d064a1a2','2026-07-23 00:29:15','2026-07-23 00:29:15'),(13,4,2,20.00,'{\"calculation_type\":\"per_mandays\",\"units\":20}',1,0,'gn61ADZg3TNR84KelgCU2NSgu9GkPnDibgNDHC1TAd8Trw==','AES','aes128:7a51d064a1a2','2026-07-23 04:27:53','2026-07-23 04:27:53'),(14,4,6,20.00,'{\"calculation_type\":\"per_mandays\",\"units\":20}',1,0,'B17utZOc57pK9VQ/yiLq3V63vqUMf8eYvTT4qqsUtXqH+6s=','AES','aes128:7a51d064a1a2','2026-07-23 04:27:53','2026-07-23 04:27:53'),(15,4,7,20.00,'{\"calculation_type\":\"per_mandays\",\"units\":20}',1,0,'/NJ6wGuRaqCS0DVV0uNnY+UfUkrL9DRrOOgEMULPUEmzlw==','AES','aes128:7a51d064a1a2','2026-07-23 04:27:53','2026-07-23 04:27:53'),(16,4,8,20.00,'{\"calculation_type\":\"per_mandays\",\"units\":20}',1,0,'WGORhz2JIvuus9C3v5wDDLvzI6/0Wh/GTg30XAdbrH5guA==','AES','aes128:7a51d064a1a2','2026-07-23 04:27:53','2026-07-23 04:27:53');
/*!40000 ALTER TABLE `payroll_allowances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_deductions`
--

DROP TABLE IF EXISTS `payroll_deductions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_deductions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint(20) unsigned NOT NULL,
  `deduction_type` varchar(50) NOT NULL,
  `deduction_label` varchar(100) DEFAULT NULL,
  `calculation_detail` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`calculation_detail`)),
  `is_manual_override` tinyint(1) NOT NULL DEFAULT 0,
  `amount_enc` text DEFAULT NULL,
  `salary_alg` varchar(20) DEFAULT NULL,
  `salary_key_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_deductions_payroll_id_foreign` (`payroll_id`),
  CONSTRAINT `payroll_deductions_payroll_id_foreign` FOREIGN KEY (`payroll_id`) REFERENCES `payrolls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_deductions`
--

LOCK TABLES `payroll_deductions` WRITE;
/*!40000 ALTER TABLE `payroll_deductions` DISABLE KEYS */;
INSERT INTO `payroll_deductions` VALUES (1,2,'bpjs_kesehatan','BPJS Kesehatan','{\"special_deduction_id\":1}',0,'QuX+xDPI0+gZsPD4nPFg4ZNMHgu6cdvnwgXOyHmkt6kR','AES','aes128:7a51d064a1a2','2026-07-23 00:29:15','2026-07-23 00:29:15'),(2,2,'bpjs_tk_jht','BPJS TK - JHT','{\"special_deduction_id\":2}',0,'w+Z4ty2rP4uCf9RmiehsDX1PnUUMRKAMXOpxrWKmbOgh','AES','aes128:7a51d064a1a2','2026-07-23 00:29:15','2026-07-23 00:29:15');
/*!40000 ALTER TABLE `payroll_deductions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_periods`
--

DROP TABLE IF EXISTS `payroll_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `period_month` char(7) NOT NULL,
  `name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_periods_period_month_unique` (`period_month`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_periods`
--

LOCK TABLES `payroll_periods` WRITE;
/*!40000 ALTER TABLE `payroll_periods` DISABLE KEYS */;
INSERT INTO `payroll_periods` VALUES (1,'2026-07','Periode Gaji July 2026','2026-06-28','2026-07-27','open','2026-07-21 23:28:13','2026-07-21 23:28:13'),(2,'2026-08','Periode Gaji August 2026','2026-07-28','2026-08-27','open','2026-07-23 04:29:21','2026-07-23 04:29:21');
/*!40000 ALTER TABLE `payroll_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payrolls`
--

DROP TABLE IF EXISTS `payrolls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payrolls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `periode` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_by` bigint(20) unsigned DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `paid_proof_path` varchar(255) DEFAULT NULL,
  `paid_proof_uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `paid_proof_uploaded_at` timestamp NULL DEFAULT NULL,
  `paid_ref` varchar(120) DEFAULT NULL,
  `paid_note` text DEFAULT NULL,
  `approval_note` text DEFAULT NULL,
  `gaji_pokok_enc` longtext DEFAULT NULL,
  `tunjangan_enc` longtext DEFAULT NULL,
  `potongan_enc` longtext DEFAULT NULL,
  `total_allowances_enc` text DEFAULT NULL,
  `total_deductions_enc` text DEFAULT NULL,
  `calculation_mode` varchar(20) NOT NULL DEFAULT 'manual',
  `engine_version` varchar(20) DEFAULT NULL,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `calculated_at` timestamp NULL DEFAULT NULL,
  `total_enc` longtext DEFAULT NULL,
  `catatan_enc` longtext DEFAULT NULL,
  `salary_alg` varchar(20) NOT NULL DEFAULT 'AES',
  `salary_key_id` varchar(50) DEFAULT NULL,
  `dek_enc` longtext DEFAULT NULL,
  `enc_meta` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payrolls_employee_id_periode_unique` (`employee_id`,`periode`),
  KEY `payrolls_user_id_foreign` (`user_id`),
  KEY `payrolls_requested_by_foreign` (`requested_by`),
  KEY `payrolls_approved_by_foreign` (`approved_by`),
  KEY `payrolls_paid_by_foreign` (`paid_by`),
  KEY `payrolls_paid_proof_uploaded_by_foreign` (`paid_proof_uploaded_by`),
  CONSTRAINT `payrolls_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payrolls_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payrolls_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payrolls_paid_proof_uploaded_by_foreign` FOREIGN KEY (`paid_proof_uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payrolls_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payrolls_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payrolls`
--

LOCK TABLES `payrolls` WRITE;
/*!40000 ALTER TABLE `payrolls` DISABLE KEYS */;
INSERT INTO `payrolls` VALUES (1,3,16,'2026-06-28','submitted',3,'2026-07-22 01:06:03',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'LlwyRJPrNk7uDQ7tfilu6DOMfNG+tVTV5gb4aINMRGhcc4c=','5wdLm7yQZM9vDfjJguQB7WF9nhpPF4SEM34nFjzKYBOII68=','G04131laf6/wK43f3gkGetnMOJ2m+Uaf3cLuVk4=','UOrTXy2qCjZMO+JxVSPocfJKK0gFZMrG+op29fQL3FhBcyU=','MH03MJDfyOVEMBpLYZduLjLD+Ia7MBwwjm28ytA=','auto','v2.0','2026-06-28','2026-07-27','2026-07-22 01:06:02','cJ1+UsZdqQ94j0+Zzt/pVU5EEnGFPP/83yorQ7iGUM4+4h0=','XQacPUeTH+O/ZzGXvq4X6r7ew7TiozJrtHr7zA==','HYBRID','hybrid:rsa2048:1','BZoBNoXmqrp5q8X+NVe8MuXMe+CVx71y60V0CcM6AsY+Olh6dAWmhhq1s7RGBaAu7H1edKxUyPje6cLvXaGVHzO5MalPy8DtL4Q2agNXTDgyrMivW7e6You97YUyZmwM5gUJLZHkVw03jKE1oaTtuIAZChmtBKA8biFn7vcvn+8MCPnMG6VBHbhboqjR5ZsuLru9szPs6YdDtRLC7oeh7yUyUPZP7mZgMco5UAlynG2qc5pcCFFU15T6gpXOq0D3F6vN+eubv69lq4H+ayx7V3xvI/tTctBPYxutmRN4VnpwNsOL+B4m8tqip7aDWT25wdGpLn0NGBbo6U5ggBlqQw==','{\"v\":2,\"alg\":\"HYBRID\",\"rsa_key_id\":1,\"dek_wrap\":\"RSA-2048-OAEP\",\"data_cipher\":\"AES-128-GCM\"}','2026-07-22 01:06:02','2026-07-22 01:06:03'),(2,3,7,'2026-06-28','paid',3,'2026-07-23 00:29:16',1,'2026-07-23 00:30:12',3,'2026-07-23 00:30:33','payroll_proofs/AY0CjB9OMzxQv4pmNHJSKdfjfYwfbI0oW3vW16vc.pdf',3,'2026-07-23 00:30:33','TRF-202606-00002',NULL,NULL,'83G5R5g74qj+vpb2A43tExbKqJDBPdLxSzueo9wnaZhWEsU=','IK1/VKcnrH84/CXnEWml6xDW+ZojDDFJqjMtM9uatL1icMw=','H7mQq1HwnIug2p4FnLzlVTSWFHZJgS9eSxxn8UpUoDrk','QF4UOYGt3OVutMcD61v1K6GEZ9ksYhqteGb+zFTmM9bjI0M=','dJtuOXqk9RMmqTupEf2hwcJN51jrR06KW9sfFKeMpdWV','auto','v2.0','2026-06-28','2026-07-27','2026-07-23 00:29:15','q8F0E1pAGHqUVaY5IgSQpXutlumHRCioDN4Wt1j3n0FIEFY=','gk7SwnkoOyNmnsCN8ObFta0cpsFuKbxuuZjuRw==','HYBRID','hybrid:rsa2048:1','EVE5z2LdMvmulwJ59xrqs7XZEcgx59JgxGGRsa4H6Qu6GHCGxqe/R5UHbGTO6lNyJqpXkXus3Qt21cRotBSclljlwJqExy61rJT9nxSoChx61lHvuwuotRkrn6BSD3Z+KwyieWLbju3TO/puqJ8FK6SL+5VNU40qHO7DsoEaEYhVsxchRkwnnbh2gVcPjtthTvS8d6x4ydBRhwO3auXcpdXm7ylHN32N7E36LlZA5Y4h0Nug5k0upaqRJsyEgJv4TKpFRCPNVXMHnYIZKs1oDg0KtVWkCCDnfNrTV0opUg/iG4sndkxprDv9LksnNbXWGs8WSnhI6W0qvBrGTHG84A==','{\"v\":2,\"alg\":\"HYBRID\",\"rsa_key_id\":1,\"dek_wrap\":\"RSA-2048-OAEP\",\"data_cipher\":\"AES-128-GCM\"}','2026-07-23 00:29:15','2026-07-23 00:30:33'),(4,3,20,'2026-06-28','rejected',3,'2026-07-23 04:27:53',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'cek','GUVBG/VJB7+ZWxaj0bnaB2P4U3BDnkufaDhGjfS1jk0wVsw=','n6Oik2pU76kvlxn4qrU24fc9ixnk2+qQ6tBJrF74Dy9kIfQ=','jbSVg+cFzpPhfT9u5HmM3vMu9Pyj0z+uFwyCxxE=','ZzGTW9S4mbDc415Gn1NWQWQQVQPx16PeNlgAQGJR1HqCVqk=','5LAmcVO4XlDMV26G0ZMGr1YGY2J+E9RwrvsS8Do=','auto','v2.0','2026-06-28','2026-07-27','2026-07-23 04:27:53','AmOD7/6cw+W/NGmg3ML1nzqFLVHGSP9/oH7wwUWbKOX08PU=','7Re3ysIBDp15QLSHfhN0BWAUCE5YM5KR7BOR6g==','HYBRID','hybrid:rsa2048:1','DIKC+uNvicTYDkHybDFJXXBOtKSS4bQ2BSSOSn4T/IRerKnrLrbOxw9DwIYWiI4Fp8wE33CtoLDag+/s04YzpXKpDpjuN34/B1ivxy/+rcz7FDrg1dtbGJs/GQ/R20aqAOmgXn4zOeRtAp0Z8Ckd24il2deq0Mx+fjlJXaUzLigyuD5GIjupyv5m2DESS1yybEWTV+94LOfcMvwQEcId+857I0Y0LCwJxznNeMEoeKH4Mn59sy2i0K+VHgJk/aHlg3qiE9e0+V3B4wJuQ56fTNkGcB2V1t5dqm3WXcei2O05f3dt/083miou9iZpneMeYraxol6fUuAB5c7mwU1ZZg==','{\"v\":2,\"alg\":\"HYBRID\",\"rsa_key_id\":1,\"dek_wrap\":\"RSA-2048-OAEP\",\"data_cipher\":\"AES-128-GCM\"}','2026-07-23 04:27:53','2026-07-23 05:05:59');
/*!40000 ALTER TABLE `payrolls` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `perf_logs`
--

DROP TABLE IF EXISTS `perf_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `perf_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint(20) unsigned DEFAULT NULL,
  `scenario` varchar(50) NOT NULL,
  `alg` varchar(20) NOT NULL,
  `encrypt_ms` decimal(12,3) DEFAULT NULL,
  `decrypt_ms` decimal(12,3) DEFAULT NULL,
  `db_ms` decimal(12,3) DEFAULT NULL,
  `total_ms` decimal(12,3) DEFAULT NULL,
  `cipher_bytes` bigint(20) unsigned DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `perf_logs_payroll_id_foreign` (`payroll_id`),
  KEY `perf_scenario_alg_created_index` (`scenario`,`alg`,`created_at`),
  CONSTRAINT `perf_logs_payroll_id_foreign` FOREIGN KEY (`payroll_id`) REFERENCES `payrolls` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perf_logs`
--

LOCK TABLES `perf_logs` WRITE;
/*!40000 ALTER TABLE `perf_logs` DISABLE KEYS */;
INSERT INTO `perf_logs` VALUES (1,NULL,'REPORT','HYBRID',0.000,24.011,21.374,57.335,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"all\\\",\\\"employee_id\\\":null,\\\"row_count\\\":2,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 00:30:41','2026-07-23 00:30:41'),(2,NULL,'REPORT','HYBRID',0.000,23.369,20.880,56.214,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"all\\\",\\\"employee_id\\\":null,\\\"row_count\\\":2,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 00:31:07','2026-07-23 00:31:07'),(3,NULL,'REPORT','HYBRID',0.000,26.063,22.656,66.062,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"all\\\",\\\"employee_id\\\":null,\\\"row_count\\\":2,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 00:31:52','2026-07-23 00:31:52'),(4,2,'READ_DETAIL','HYBRID',NULL,10.106,15.525,33.273,NULL,'{\"masked\":false}','2026-07-23 00:32:21','2026-07-23 00:32:21'),(5,2,'READ_DETAIL','HYBRID',NULL,17.591,38.394,66.604,NULL,'{\"masked\":false}','2026-07-23 03:45:28','2026-07-23 03:45:28'),(6,NULL,'REPORT','HYBRID',0.000,23.514,20.620,53.907,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"all\\\",\\\"employee_id\\\":null,\\\"row_count\\\":3,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 04:02:54','2026-07-23 04:02:54'),(7,NULL,'REPORT','HYBRID',0.000,27.861,19.934,60.856,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"all\\\",\\\"employee_id\\\":null,\\\"row_count\\\":3,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 04:27:59','2026-07-23 04:27:59'),(8,NULL,'REPORT','HYBRID',0.000,24.574,16.780,56.192,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"all\\\",\\\"employee_id\\\":null,\\\"row_count\\\":3,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 04:28:02','2026-07-23 04:28:02'),(9,NULL,'REPORT','HYBRID',0.000,17.029,12.881,37.882,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"all\\\",\\\"employee_id\\\":null,\\\"row_count\\\":3,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 04:28:10','2026-07-23 04:28:10'),(10,NULL,'REPORT','HYBRID',0.000,29.252,18.787,60.254,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"all\\\",\\\"employee_id\\\":null,\\\"row_count\\\":3,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 04:28:22','2026-07-23 04:28:22'),(11,NULL,'REPORT','HYBRID',0.000,25.194,20.026,57.137,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"all\\\",\\\"employee_id\\\":null,\\\"row_count\\\":3,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 04:28:56','2026-07-23 04:28:56'),(12,NULL,'REPORT','HYBRID',0.000,25.425,11.914,46.472,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"all\\\",\\\"employee_id\\\":null,\\\"row_count\\\":3,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 04:29:16','2026-07-23 04:29:16'),(13,NULL,'REPORT','HYBRID',0.000,11.304,22.199,42.393,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"paid\\\",\\\"employee_id\\\":null,\\\"row_count\\\":1,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 04:35:00','2026-07-23 04:35:00'),(14,NULL,'REPORT','HYBRID',0.000,14.416,25.064,50.560,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"paid\\\",\\\"employee_id\\\":null,\\\"row_count\\\":1,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 04:35:23','2026-07-23 04:35:23'),(15,NULL,'REPORT','HYBRID',0.000,10.545,16.200,35.655,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"paid\\\",\\\"employee_id\\\":null,\\\"row_count\\\":1,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 04:35:49','2026-07-23 04:35:49'),(16,NULL,'REPORT','HYBRID',0.000,11.516,13.337,32.027,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"paid\\\",\\\"employee_id\\\":null,\\\"row_count\\\":1,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 05:05:39','2026-07-23 05:05:39'),(17,NULL,'REPORT','HYBRID',0.000,12.159,16.066,36.888,NULL,'\"{\\\"month\\\":\\\"2026-07\\\",\\\"start\\\":\\\"2026-06-28\\\",\\\"end\\\":\\\"2026-07-27\\\",\\\"status\\\":\\\"paid\\\",\\\"employee_id\\\":null,\\\"row_count\\\":1,\\\"read_mode\\\":\\\"CIPHER_ONLY\\\",\\\"storage_mode\\\":\\\"CIPHER_ONLY\\\"}\"','2026-07-23 05:05:46','2026-07-23 05:05:46');
/*!40000 ALTER TABLE `perf_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (5,'App\\Models\\User',7,'payroll','e5d7c3498f2f548ca593f4e08ab66d8804c55ea3cb8a5239c5998ae1925a1dbb','[\"*\"]','2026-07-23 03:45:28',NULL,'2026-07-23 00:32:00','2026-07-23 03:45:28'),(8,'App\\Models\\User',1,'payroll','51f51689845a9a4248a92e7662de87dccbb6165cb13d1139f7b3676b21e0ece1','[\"*\"]','2026-07-23 05:31:08',NULL,'2026-07-23 05:31:07','2026-07-23 05:31:08'),(9,'App\\Models\\User',2,'payroll','6201df668614c7b7f37cc6fa8f4a733a7b8c603305224ba5f7cb013d8b7d2996','[\"*\"]','2026-07-23 06:10:21',NULL,'2026-07-23 05:31:23','2026-07-23 06:10:21'),(10,'App\\Models\\User',3,'payroll','2fb92c5305f6479d54d79dcd21246765df3e0ff2840e207a51cb43629e4a1712','[\"*\"]','2026-07-23 06:10:11',NULL,'2026-07-23 05:58:35','2026-07-23 06:10:11');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `position_allowance_rates`
--

DROP TABLE IF EXISTS `position_allowance_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `position_allowance_rates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint(20) unsigned NOT NULL,
  `allowance_type_id` bigint(20) unsigned NOT NULL,
  `rate_amount` decimal(14,2) DEFAULT NULL COMMENT 'Nominal per unit (per manday/trip/bulan). Null = tidak berlaku untuk jabatan ini',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pos_allowance_unique` (`position_id`,`allowance_type_id`),
  KEY `position_allowance_rates_position_id_is_active_index` (`position_id`,`is_active`),
  KEY `position_allowance_rates_allowance_type_id_is_active_index` (`allowance_type_id`,`is_active`),
  CONSTRAINT `position_allowance_rates_allowance_type_id_foreign` FOREIGN KEY (`allowance_type_id`) REFERENCES `allowance_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `position_allowance_rates_position_id_foreign` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `position_allowance_rates`
--

LOCK TABLES `position_allowance_rates` WRITE;
/*!40000 ALTER TABLE `position_allowance_rates` DISABLE KEYS */;
INSERT INTO `position_allowance_rates` VALUES (1,1,1,5000000.00,1,'2026-07-21 22:40:22','2026-07-21 23:39:30'),(2,2,1,4000000.00,1,'2026-07-21 22:40:22','2026-07-21 23:39:40'),(3,3,1,3000000.00,1,'2026-07-21 22:40:22','2026-07-21 23:39:50'),(4,4,1,2000000.00,1,'2026-07-21 22:40:22','2026-07-21 23:39:57'),(5,5,1,1000000.00,1,'2026-07-21 22:40:22','2026-07-21 23:40:04'),(6,6,1,500000.00,1,'2026-07-21 22:40:22','2026-07-21 23:40:17'),(7,7,1,250000.00,1,'2026-07-21 22:40:22','2026-07-21 23:40:24'),(8,8,1,150000.00,1,'2026-07-21 22:40:22','2026-07-21 23:40:31'),(9,1,2,50000.00,1,'2026-07-21 22:40:22','2026-07-21 23:41:23'),(10,2,2,50000.00,1,'2026-07-21 22:40:22','2026-07-21 23:41:32'),(11,3,2,50000.00,1,'2026-07-21 22:40:22','2026-07-21 23:41:36'),(12,4,2,50000.00,1,'2026-07-21 22:40:22','2026-07-21 23:41:40'),(13,5,2,25000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(14,6,2,25000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(15,7,2,25000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(16,8,2,25000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(17,2,3,2500000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(18,4,3,2500000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(19,3,3,1200000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(20,5,3,1200000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(21,2,4,2500000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(22,3,4,1600000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(23,6,4,1000000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(24,1,5,250000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(25,2,5,220000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(26,3,5,200000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(27,4,5,200000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(28,5,5,180000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(29,6,5,150000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(30,7,5,150000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(31,8,5,150000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(32,4,6,200000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(33,5,6,130000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(34,7,6,100000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(35,8,6,75000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(36,4,7,30000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(37,5,7,30000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(38,7,7,20000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(39,8,7,20000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(40,1,8,100000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(41,2,8,60000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(42,3,8,35000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(43,4,8,60000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(44,5,8,35000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(45,6,8,25000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(46,7,8,25000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22'),(47,8,8,25000.00,1,'2026-07-21 22:40:22','2026-07-21 22:40:22');
/*!40000 ALTER TABLE `position_allowance_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `positions`
--

DROP TABLE IF EXISTS `positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `positions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `level` tinyint(3) unsigned NOT NULL COMMENT 'Hierarki jabatan: 1 = tertinggi',
  `description` text DEFAULT NULL,
  `base_salary_basis` varchar(20) NOT NULL DEFAULT 'daily',
  `default_base_salary_amount` decimal(14,2) DEFAULT NULL,
  `default_late_penalty_amount` decimal(14,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `positions_code_unique` (`code`),
  KEY `positions_level_index` (`level`),
  KEY `positions_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `positions`
--

LOCK TABLES `positions` WRITE;
/*!40000 ALTER TABLE `positions` DISABLE KEYS */;
INSERT INTO `positions` VALUES (1,'bod','Board of Directors',1,'Board of Directors','daily',300000.00,200000.00,0,'2026-07-21 22:40:21','2026-07-23 06:04:04'),(2,'pd','Project Director',2,'Project Director','daily',NULL,NULL,1,'2026-07-21 22:40:21','2026-07-21 22:40:21'),(3,'pm','Project Manager',3,'Project Manager','daily',NULL,NULL,1,'2026-07-21 22:40:21','2026-07-21 22:40:21'),(4,'gm','General Manager',4,'General Manager','daily',NULL,NULL,1,'2026-07-21 22:40:21','2026-07-21 22:40:21'),(5,'manager','Manager',5,'Manager','daily',NULL,NULL,1,'2026-07-21 22:40:21','2026-07-21 22:40:21'),(6,'consultant','Consultant',6,'Consultant','daily',NULL,NULL,1,'2026-07-21 22:40:21','2026-07-21 22:40:21'),(7,'supervisor','Supervisor',7,'Supervisor','daily',NULL,NULL,1,'2026-07-21 22:40:21','2026-07-21 22:40:21'),(8,'staff','Staff',8,'Staff','daily',NULL,NULL,1,'2026-07-21 22:40:22','2026-07-21 22:40:22');
/*!40000 ALTER TABLE `positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_profiles`
--

DROP TABLE IF EXISTS `salary_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salary_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `position_id` bigint(20) unsigned DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `base_salary_amount_enc` longtext DEFAULT NULL,
  `position_allowance_enc` longtext DEFAULT NULL,
  `allowance_fixed_enc` longtext DEFAULT NULL,
  `deduction_fixed_enc` longtext DEFAULT NULL,
  `salary_alg` varchar(20) NOT NULL DEFAULT 'AES',
  `salary_key_id` varchar(50) DEFAULT NULL,
  `effective_from` date NOT NULL DEFAULT '2026-07-22',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salary_profile_employee_date_unique` (`employee_id`,`effective_from`),
  KEY `salary_profiles_position_id_foreign` (`position_id`),
  CONSTRAINT `salary_profiles_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_profiles_position_id_foreign` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_profiles`
--

LOCK TABLES `salary_profiles` WRITE;
/*!40000 ALTER TABLE `salary_profiles` DISABLE KEYS */;
INSERT INTO `salary_profiles` VALUES (1,1,5,'Manager','VTrG0CHBXdECv9vOWZbG+dXXYkxz0RNv8j4iF82jG6RlJzA=','Fx5S0d+l/PZaYGegZGwsOjpMqMRKZlKysCpEHMA=','LOXShsebZRD1FJGxAg72ylPoTbWt8inJiWicv2A=','fkkQu7Y4dcZ+laa9MNpZhlLDLiofW0fUISEnPgE=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:22','2026-07-21 22:40:22'),(2,2,5,'Manager','bZ7EPSqU5LiBlLfg51++y+s5ZdICj/sN7jWugxoN2m9vCg==','l9yWpBKLK678K/m8DMcRMKrDjsVMQSokXc8hqhE=','ejNRaQY3oqqypw70B/+U2XIaMXSAcD+/yUrmaRI=','1rxX4r4ifIh8EpYoRE0Ca2GFRKxgwHWnbaye5g4=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:22','2026-07-21 22:40:22'),(3,3,5,'Manager','PZuM5w6YrOVpdsZAJXp79GACpnXeUVCK7pOySOD5l+95xw==','8iVty/hGMLODubJnFJJUjR8G8mRGVmyGc658SVs=','MwoXLhhnvi75dKX8d/NTQptuE19QqnU8D4DnszY=','ikqfJqIKyBsFfTxdhMPnfzEZaS0CWXFlIxmHkOw=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:23','2026-07-21 22:40:23'),(4,4,8,'Staff','cHNHyI8nLrJSBv+9SjRwzxlpzZ0UrFCmd1XCwFgNK6yNag==','8lKeI6ebyCeqs8sVS4b0kwNl7ZUohy0UH9XRmFM=','Ka3iYRpOAd6MfmeZ4D03w2q6qajsjXhy+RBXP4U=','CLrbSU5R0hPnth/PZLYLPcN0YWvHvmFSwgfo06s=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:23','2026-07-21 22:40:23'),(5,5,8,'Staff','xhNwdcf8XfYiuOOIziGPpYDqjKCXLEu9376xLZCImxfsjg==','+xRhjWN+onmFe6rvaR7CtIviKd4k0uz7RKwOoRg=','BkwIop2XvnvHsFgAR+woJ/4cUu0ZiGbEVXxm/ao=','GL2nenkfgBPCR4qaHkmonJmDtWmFqp58PWmZv8w=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:23','2026-07-21 22:40:23'),(6,6,7,'Supervisor','JtgNdQqUfCQr1BiYXU/9X+M4UxXlADy0or9S6UoQIArx2g==','VFMgv0goWYPFTvSN6Z1l0UCEHTWUkh0cr5u61uk=','/+EhXnGWfiJa3AUyU1FGtAIKiVamWOg5WDG8LvU=','vKKFwJjR/IZAGdd2PR5LnBY4HZeglXlE4tXZfCk=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:24','2026-07-21 22:40:24'),(7,7,7,'Supervisor','IP41xkunBcIUHitkvcFMrlMHoSIySsKxvUdniG7m3c110Q==','wFIXT1e6qTszN0E5XDZDdQIDv1pLIFg67Fg8t5s=','Djk8L2WBMAbO35Ul4ztDws0jL+FSGSTN7+gH434=','3Z4zzxSLODk1hKKQU97pU3SypbCROL6QXAS5blQ=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:24','2026-07-21 22:40:24'),(8,8,8,'Staff','H1dXuo7r3hiP/ndBirCguoM+HWL2m2ULMYfsM1/pSpX1Iw==','bpopuJnUmM1rWqwJLLDmTfRVmVyCTfORCpscMMU=','AfyisQqnak05+JN80CmFnKgsM1NXsjEqUQPuX4U=','HON0/ATPUa19fq/ZRrAHQl4T7fE+zOo8KfOxQNo=','AES','aes128:7a51d064a1a2','2026-07-01','2026-07-21 22:40:24','2026-07-21 22:40:24'),(9,9,8,'Staff','KijavGh++zss5xk6h+uTq40AJjQdNylFvhjwNNSMgrRvLg==','3L53WJWkZFkIHtZJUy0HVX6DWRfG3SDeG6pS90w=','Ao+5jlcNNdgU0qy+jS2UzV9tPiZJTjGnvuvmvfs=','p5eO+QP1FW/1xaydMAhJViFw8RoDCzjvhqB3CUA=','AES','aes128:7a51d064a1a2','2026-07-01','2026-07-21 22:40:25','2026-07-21 22:40:25'),(10,10,8,'Staff','bx9lA9IDkhRKC1kd/qHxiW4lHBtyTaJMtTnzpsZxZ0Yq/w==','q0fG6gFybKVeDKhttqAHxACCFNfknw8BDncDH3Y=','Jq43ZjWqes3KgzG9ffnZu+164HS/szyQxpTVqM0=','vSw6xh6w1tyyabvBoT1VWpoIByBXom4NhC2tV1Q=','AES','aes128:7a51d064a1a2','2026-07-01','2026-07-21 22:40:25','2026-07-21 22:40:25'),(11,11,8,'Staff','zGAfUQWYQCFYWTgvhirLmCEtEQzv1xYS57yFQv/GfCJDHQ==','RUvvBS3hjk0NImC8e/C8Yu6TyJ59HWuYyo0Jmqw=','DFcE+9GUCcf5fRH3bffiZ2ECqFoQuxyaDl1fPxA=','H3yWy4eKYERe/ltPVupbzUf8MZzGMblnBqyFGCw=','AES','aes128:7a51d064a1a2','2026-07-01','2026-07-21 22:40:25','2026-07-21 22:40:25'),(12,12,8,'Staff','WEafDofTMw5XBZwGr12nXJ1Pve3foPi5qpDLEJRrivYmWA==','qj+nsDJuJBlDd9kt0hHPbjfxJCPIzwsZf8yk2J8=','DmygoNtKmKO3RMdX2N/w2oPGDhEMaIMxlgPtxs4=','WgRuctNUGVpQVFIcBnOdNoupAc+Ex4EUuCzMXA4=','AES','aes128:7a51d064a1a2','2026-07-01','2026-07-21 22:40:26','2026-07-21 22:40:26'),(13,13,8,'Staff','8da1LGwid2Hedp/pvFGyKE3Sq1atvfP72H3opNNk9A4VRQ==','7RbiBvaRdAwRyQ7vlqTvW6kDlJYIUYS0Ssu0U40=','xQZDnJ7kktZ5hfJRnJgEt4SvD6dK2x7T6+ATo8o=','KPSVPGSexEODbhEgOJ22pE/HRo+Ig6O2/6x9490=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:26','2026-07-21 22:40:26'),(14,14,8,'Staff','Yr7U+oV3hgHXd6U6tPvKqL4DEU/iAGCJuQOnPR8N51uREQ==','EuOk3k/u53Hbjwr8yxsHqbLVJLyULk7YHHjyhgo=','47h8S9eSoWWOpGPxa00j9HI1OGweguRXqaaP2lE=','GNTPCd5/BxF0ADFnQitgo1lnujkKXbFJEnuBosY=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:26','2026-07-21 22:40:26'),(15,15,8,'Staff','ZsW6ChwT+Toq1kQ++qW8T3ER8zaJGE7Q1mW4QdRVvOz1XQ==','CIbwcYUojgLYxjGwOePMRCHtralsPMfP9vUOTJ4=','Izy4wz16K2sbKut7seLWh2AhGaQI09jLeg6b82Y=','BxIgwUNVZ4q2nClihZi9CbnLYKf98CypkFZ2zy4=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:26','2026-07-21 22:40:26'),(16,16,8,'Staff','cZ8+swSicWjCP840MdCl3aZangENhuKNqerVA+EiNxEn5w==','S7OHc0fljQIDTfkEF1ZlD6KHSw4jRCBEy3P9drA=','anX8LU4SBecyLMZJBxcJ71CliTAc1VUSIMS5mXE=','Pghw0BbjUq4ji7Bc+ZwcQ6qtxvmLakgkwTVUYF4=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:26','2026-07-21 22:40:26'),(17,17,8,'Staff','T4uDGEpZ2UZu2G+sR3QeLyul+eV2WWZO5mAKnElCJ02dVw==','tnr1wvb3gdUOPa979Yfhfv4kvxJJKXrHXwYcQs0=','GIL56NzXKhjzQW19HGYd3sok1bHlsMFzqJEUQ5Y=','zYriUHFuRWUWKNX8NVwoleJA8oO4K7hnXxlGqPo=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:26','2026-07-21 22:40:26'),(18,18,8,'Staff','T3X4fig5g8IYua/eE2ST1IYGWtCCXxJkaGuhgkdBwJon+A==','OMJsGb7VkCsoPm2KhgnvIaUj0ONhWxGKWQShZkQ=','o+mTQEwKC1EyaF43ihSvp2xEPra3EkfqGgtSrJ8=','1fvh8fmdbKPvG7mH6Z7xUiVm58oFQp/h+UUFy78=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:26','2026-07-21 22:40:26'),(19,19,8,'Staff','vgZTZQWv9uKw1VoDKa08pfF5NsnZbLYKW/8JXTyMacd5Yw==','qQ/jwj7QXV7qEJ6cGCsi8oKIi02uZtClV4nzOTw=','DVa2zxKrQc36qUQTxaSzttSlUeEcugSVPMtyxmQ=','s8ng+A1O4K+1WPXcNy4HzNQTeF7w52yNEcWeKYY=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:26','2026-07-21 22:40:26'),(20,20,8,'Staff','yh7PzmS9LK2QMyCEUH0EjJip812fb2qffAlvejQXflT3xw==','DkH7MFFHi03dphvRXjuGWPBHxVXTPZQUgURBbsY=','WJAxS8WI2jI+AEkC7PcZAaA5Ev98nIeEGu6Eh+w=','R5TYKQLfVBrSKBzowCWyDlNl8T3tC3sctFdXYl0=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:26','2026-07-21 22:40:26'),(21,21,8,'Staff','B7xuQduKzOaNIeHq35mqO8KItcLCu4tx0AX8WzD4Oke5ZQ==','iT6yDj2n2bguJT7o6hYy/Dx9GP0X4pojopt3PMw=','ibj7sKpbynnFqQHjvH3Bovjk1rLHVpXajaUtJZg=','b8BdvFQHMNs2ALW7lfqd8wMz5q0Dmd0rburZMvM=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:26','2026-07-21 22:40:26'),(22,22,8,'Staff','DNGN1nt3jak3dktq4n2bOlkKwwZ+jor++40rTi82cuMW/w==','+8WOEfPkfYWCFt+Q+0OaiysAMShAaMgRfYqk0t4=','tT5UItG/Lnh62hUfYj4RxRmZhELInB6a4O/KpYA=','0w8vAEV8Wh/r2MTPmX7uiuGyD+aSvfhguLDGnoM=','AES','aes128:7a51d064a1a2','2025-07-01','2026-07-21 22:40:26','2026-07-21 22:40:26');
/*!40000 ALTER TABLE `salary_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
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
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `special_deductions`
--

DROP TABLE IF EXISTS `special_deductions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `special_deductions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `deduction_type_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'kasbon',
  `period_month` varchar(7) NOT NULL,
  `amount_enc` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `salary_alg` varchar(20) DEFAULT NULL,
  `salary_key_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `special_deduction_employee_period_type_unique` (`employee_id`,`period_month`,`deduction_type_id`),
  KEY `special_deductions_deduction_type_id_foreign` (`deduction_type_id`),
  KEY `special_deductions_created_by_foreign` (`created_by`),
  CONSTRAINT `special_deductions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `special_deductions_deduction_type_id_foreign` FOREIGN KEY (`deduction_type_id`) REFERENCES `deduction_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `special_deductions_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `special_deductions`
--

LOCK TABLES `special_deductions` WRITE;
/*!40000 ALTER TABLE `special_deductions` DISABLE KEYS */;
INSERT INTO `special_deductions` VALUES (1,7,1,'bpjs_kesehatan','2026-07','dOSwriQ9sonuiNBpxR/P94xcGjnKBlVLMY+gjNYl1B9c',NULL,3,'AES','aes128:7a51d064a1a2','2026-07-23 00:28:46','2026-07-23 00:28:46'),(2,7,2,'bpjs_tk_jht','2026-07','ZC2Uz6E2zLBxVmn4DdY/61ZjpSMnuoUhaL5x8EI6Dv+z',NULL,3,'AES','aes128:7a51d064a1a2','2026-07-23 00:28:57','2026-07-23 00:28:57');
/*!40000 ALTER TABLE `special_deductions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'staff',
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Test Director','test.director@payroll.test','director','$2y$12$xktLa1S3PMiSiBlayPgWQeG8ynIGytfZAqMiRoBNkAbiX/rnUd9ia','2026-07-21 22:40:22','2026-07-21 22:40:22'),(2,'Test HCGA','test.hcga@payroll.test','hcga','$2y$12$1088JvP8f6ayYZiYvRFrA.2VsE5Sh1/3nnQdU.WsrKLRA2H3GGfvi','2026-07-21 22:40:22','2026-07-21 22:40:22'),(3,'Test FAT','test.fat@payroll.test','fat','$2y$12$jzW999.RA8nbHynoZ6nIiOIyV5C..wtbrf4Q9ejrKA0EnyU.KRL3e','2026-07-21 22:40:23','2026-07-21 22:40:23'),(4,'Test Staff','test.staff@payroll.test','staff','$2y$12$GS3z2KBqxhVgD1zJSZbgxuqVWDl/LVkemIaPGJUCE7k6Qc4thGowW','2026-07-21 22:40:23','2026-07-21 22:40:23'),(5,'Andi Saputra','andi@payroll.test','staff','$2y$12$6itI9MP7vJpV9b5LUJkUoeMnEb2KxBzSSGghwYkcfNOiHKe/XQ98C','2026-07-21 22:40:23','2026-07-21 22:40:23'),(6,'Joko Anwar','joko@payroll.test','staff','$2y$12$C1zCsFGuxDcXDJlAVWbpM.2WkdZnJHCh58pH9ldRMwKaM7.8EcwNS','2026-07-21 22:40:24','2026-07-21 22:40:24'),(7,'Rina Melati','rinam@payroll.test','staff','$2y$12$KKoHX/WKgTaCH0E65znkOOcUeZ1EPQBgrfgyqPiDtbzTsnAQQVXb2','2026-07-21 22:40:24','2026-07-21 22:40:24'),(8,'Kandidat Pegawai 1','kandidat1@payroll.test','staff','$2y$12$oJvgaWlImcC6lipCr2BUmOCUCFxGKj/sumb2eXCbavtIKD2cmtbTa','2026-07-21 22:40:24','2026-07-21 22:40:24'),(9,'Kandidat Pegawai 2','kandidat2@payroll.test','staff','$2y$12$gSBQCwktz5R4.8ZTm8b3auVNMRxAJAT9NG95YsN83XJfIqgsdCUEO','2026-07-21 22:40:25','2026-07-21 22:40:25'),(10,'Kandidat Pegawai 3','kandidat3@payroll.test','staff','$2y$12$BZBkpxFsls00pPGO0jlStePqPD7r6QNiTibnDhRRtLBq0WipiVnVG','2026-07-21 22:40:25','2026-07-21 22:40:25'),(11,'Kandidat Pegawai 4','kandidat4@payroll.test','staff','$2y$12$fAbIxrQd0hmf28ecKD6DV.BAAd0RMNRj647k3MbMtxKGsKcYpzuuG','2026-07-21 22:40:25','2026-07-21 22:40:25'),(12,'Kandidat Pegawai 5','kandidat5@payroll.test','staff','$2y$12$HaFh5r7GH3KAsgbOASd/KuUqDo2XY6b9XWq.YVGBf3eDHrsfYz/fK','2026-07-21 22:40:26','2026-07-21 22:40:26'),(13,'Ahmad Subagyo','ahmadsubagyo@payroll.test','staff','$2y$12$eW61AsRnNtouDzygC7eoeuh7FntxPtCUvT4ZCXwlvVJd9VLXUqHu.','2026-07-21 22:40:26','2026-07-21 22:40:26'),(14,'Nina Susanti','ninasusanti@payroll.test','staff','$2y$12$eW61AsRnNtouDzygC7eoeuh7FntxPtCUvT4ZCXwlvVJd9VLXUqHu.','2026-07-21 22:40:26','2026-07-21 22:40:26'),(15,'Wahyu Setiawan','wahyusetiawan@payroll.test','staff','$2y$12$eW61AsRnNtouDzygC7eoeuh7FntxPtCUvT4ZCXwlvVJd9VLXUqHu.','2026-07-21 22:40:26','2026-07-21 22:40:26'),(16,'Lia Mulyani','liamulyani@payroll.test','staff','$2y$12$eW61AsRnNtouDzygC7eoeuh7FntxPtCUvT4ZCXwlvVJd9VLXUqHu.','2026-07-21 22:40:26','2026-07-21 22:40:26'),(17,'Eko Prasetyo','ekoprasetyo@payroll.test','staff','$2y$12$eW61AsRnNtouDzygC7eoeuh7FntxPtCUvT4ZCXwlvVJd9VLXUqHu.','2026-07-21 22:40:26','2026-07-21 22:40:26'),(18,'Dina Puspita','dinapuspita@payroll.test','staff','$2y$12$eW61AsRnNtouDzygC7eoeuh7FntxPtCUvT4ZCXwlvVJd9VLXUqHu.','2026-07-21 22:40:26','2026-07-21 22:40:26'),(19,'Taufik Hidayat','taufikhidayat@payroll.test','staff','$2y$12$eW61AsRnNtouDzygC7eoeuh7FntxPtCUvT4ZCXwlvVJd9VLXUqHu.','2026-07-21 22:40:26','2026-07-21 22:40:26'),(20,'Rika Amalia','rikaamalia@payroll.test','staff','$2y$12$eW61AsRnNtouDzygC7eoeuh7FntxPtCUvT4ZCXwlvVJd9VLXUqHu.','2026-07-21 22:40:26','2026-07-21 22:40:26'),(21,'Surya Saputra','suryasaputra@payroll.test','staff','$2y$12$eW61AsRnNtouDzygC7eoeuh7FntxPtCUvT4ZCXwlvVJd9VLXUqHu.','2026-07-21 22:40:26','2026-07-21 22:40:26'),(22,'Maya Indah','mayaindah@payroll.test','staff','$2y$12$eW61AsRnNtouDzygC7eoeuh7FntxPtCUvT4ZCXwlvVJd9VLXUqHu.','2026-07-21 22:40:26','2026-07-21 22:40:26');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'penggajian_db'
--

--
-- Dumping routines for database 'penggajian_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-23 20:10:35
