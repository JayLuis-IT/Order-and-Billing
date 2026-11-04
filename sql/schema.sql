-- Manufacturing Management System Database Schema
-- Generated from the live `manufacturing_mgmt` database on 2026-10-13 (MariaDB 10.4.32).
-- Regenerate with:
--   mysqldump -u root --no-data --skip-add-drop-table --routines=FALSE --triggers=FALSE manufacturing_mgmt
-- Normalized for repo use: CREATE TABLE IF NOT EXISTS, AUTO_INCREMENT counters stripped,
-- DEFINER removed, temporary view placeholders removed (file can be re-run safely).

CREATE DATABASE IF NOT EXISTS manufacturing_mgmt;
USE manufacturing_mgmt;
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: manufacturing_mgmt
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
-- NOTE: not used by application code (kept for history)
-- Table structure for table `advance_production_consumption`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `advance_production_consumption` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `advance_poi_id` int(11) NOT NULL,
  `advance_po_id` int(11) NOT NULL,
  `normal_poi_id` int(11) NOT NULL,
  `normal_po_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `date_allocated` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `advance_poi_id` (`advance_poi_id`),
  KEY `advance_po_id` (`advance_po_id`),
  KEY `normal_poi_id` (`normal_poi_id`),
  KEY `normal_po_id` (`normal_po_id`),
  CONSTRAINT `advance_production_consumption_ibfk_1` FOREIGN KEY (`advance_poi_id`) REFERENCES `purchase_order_items` (`poi_id`),
  CONSTRAINT `advance_production_consumption_ibfk_2` FOREIGN KEY (`advance_po_id`) REFERENCES `purchase_orders` (`po_id`),
  CONSTRAINT `advance_production_consumption_ibfk_3` FOREIGN KEY (`normal_poi_id`) REFERENCES `purchase_order_items` (`poi_id`),
  CONSTRAINT `advance_production_consumption_ibfk_4` FOREIGN KEY (`normal_po_id`) REFERENCES `purchase_orders` (`po_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `department` varchar(50) NOT NULL,
  `action` varchar(20) NOT NULL COMMENT 'LOGIN, LOGOUT, CREATE, UPDATE, DELETE',
  `module` varchar(50) NOT NULL COMMENT 'auth, admin, warehouse, production, finance',
  `target_type` varchar(50) NOT NULL COMMENT 'user, customer, item, po, delivery, production, excess, receipt, price_list',
  `target_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_department` (`department`),
  KEY `idx_module` (`module`),
  KEY `idx_action` (`action`),
  KEY `idx_target` (`target_type`,`target_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backloads`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `backloads` (
  `backload_id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `poi_id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `lot_number` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `cases` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `backloaded_by` int(11) NOT NULL,
  `backload_date` date NOT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  `remove` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`backload_id`),
  KEY `delivery_id` (`delivery_id`),
  KEY `po_id` (`po_id`),
  KEY `poi_id` (`poi_id`),
  KEY `lot_id` (`lot_id`),
  KEY `backloaded_by` (`backloaded_by`),
  CONSTRAINT `backloads_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`delivery_id`),
  CONSTRAINT `backloads_ibfk_2` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`),
  CONSTRAINT `backloads_ibfk_3` FOREIGN KEY (`poi_id`) REFERENCES `purchase_order_items` (`poi_id`),
  CONSTRAINT `backloads_ibfk_4` FOREIGN KEY (`lot_id`) REFERENCES `production_lots` (`lot_id`),
  CONSTRAINT `backloads_ibfk_5` FOREIGN KEY (`backloaded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `customers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `customers` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_address` text DEFAULT NULL,
  `customer_type` enum('vat','non_vat') DEFAULT 'vat' COMMENT 'vat=VAT registered, non_vat=Non-VAT',
  `customer_tin` varchar(50) DEFAULT NULL,
  `customer_terms` varchar(50) DEFAULT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  `status` tinyint(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
  `remove` tinyint(1) DEFAULT 0 COMMENT '0=active, 1=soft deleted',
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `customer_code` (`customer_code`),
  KEY `idx_customer_code` (`customer_code`),
  KEY `idx_status` (`status`),
  KEY `idx_remove` (`remove`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deliveries`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `deliveries` (
  `delivery_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `poi_id` int(11) DEFAULT NULL,
  `lot_id` int(11) DEFAULT NULL,
  `delivered_by` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `report_remarks` text DEFAULT NULL,
  `remarks_type` varchar(20) DEFAULT NULL,
  `active_status` tinyint(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
  `date_created` datetime DEFAULT current_timestamp(),
  `delivery_quantity` int(11) DEFAULT 0,
  `old_quantity` text DEFAULT NULL,
  `dr_number` varchar(50) DEFAULT NULL COMMENT 'Delivery Receipt number',
  `si_number` varchar(50) DEFAULT NULL,
  `plate_number` varchar(50) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `logistic_provider` varchar(100) DEFAULT NULL,
  `is_over_shipment` tinyint(1) DEFAULT 0,
  `old_dr_number` varchar(50) DEFAULT NULL,
  `lot_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of lot details [{lot_id, poi_id, qty}]' CHECK (json_valid(`lot_items`)),
  `remove` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`delivery_id`),
  UNIQUE KEY `uk_si_number` (`si_number`),
  KEY `po_id` (`po_id`),
  KEY `delivered_by` (`delivered_by`),
  KEY `lot_id` (`lot_id`),
  CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`),
  CONSTRAINT `deliveries_ibfk_2` FOREIGN KEY (`delivered_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `deliveries_ibfk_3` FOREIGN KEY (`lot_id`) REFERENCES `production_lots` (`lot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `delivery_receipts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `delivery_receipts` (
  `receipt_id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `type` enum('dr','si') DEFAULT 'dr',
  `uploaded_by` int(11) NOT NULL,
  `remove` tinyint(1) NOT NULL DEFAULT 0,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`receipt_id`),
  KEY `delivery_id` (`delivery_id`),
  KEY `po_id` (`po_id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `delivery_reports`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `delivery_reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `poi_id` int(11) DEFAULT NULL,
  `po_id` int(11) NOT NULL,
  `lot_id` int(11) DEFAULT NULL,
  `old_quantity` int(11) DEFAULT NULL,
  `reported_by` int(11) NOT NULL,
  `reason` text NOT NULL,
  `report_type` enum('dr_number','quantity') DEFAULT 'dr_number',
  `status` enum('pending','resolved') DEFAULT 'pending',
  `resolved_by` int(11) DEFAULT NULL,
  `new_quantity` int(11) DEFAULT NULL,
  `date_reported` datetime DEFAULT current_timestamp(),
  `date_resolved` datetime DEFAULT NULL,
  PRIMARY KEY (`report_id`),
  KEY `idx_delivery_id` (`delivery_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fg_bom_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `fg_bom_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bom_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `dosage_rate` decimal(15,6) DEFAULT 0.000000,
  `wastage_allowance_pct` decimal(5,2) DEFAULT 0.00,
  `phase_code` varchar(20) NOT NULL DEFAULT '101',
  `uom` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bom_id` (`bom_id`),
  KEY `fk_bom_items_item` (`item_id`),
  CONSTRAINT `fk_bom_items_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fg_boms`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `fg_boms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fg_item_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `bom_code` varchar(50) NOT NULL,
  `batch_qty` decimal(15,4) NOT NULL DEFAULT 1.0000,
  `batch_uom` varchar(20) NOT NULL DEFAULT 'PCS',
  `batch_unit_divisor` decimal(15,4) NOT NULL DEFAULT 1000.0000,
  `is_legacy_formula` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `bom_type` varchar(50) DEFAULT 'Alternative',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fg_item_id` (`fg_item_id`),
  KEY `fk_fg_boms_customer` (`customer_id`),
  CONSTRAINT `fg_boms_ibfk_1` FOREIGN KEY (`fg_item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fg_boms_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventory_balances`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `inventory_balances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `site_code` varchar(20) DEFAULT '001',
  `qty_on_hand` decimal(15,4) DEFAULT 0.0000,
  `qty_allocated` decimal(15,4) DEFAULT 0.0000,
  `qty_for_inspect` decimal(15,4) DEFAULT 0.0000,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item_site` (`item_id`,`site_code`),
  CONSTRAINT `inventory_balances_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- NOTE: not used by application code (kept for history)
-- Table structure for table `inventory_stock`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `inventory_stock` (
  `stock_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `site_code` varchar(50) NOT NULL DEFAULT '001',
  `lot_number` varchar(100) DEFAULT NULL,
  `qty_on_hand` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `qty_blocked` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `qty_rejected` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `status` enum('PASSED','REJECTED') NOT NULL DEFAULT 'PASSED',
  `source_receiving_item_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`stock_id`),
  UNIQUE KEY `uq_stock_item_lot_site` (`item_id`,`site_code`,`lot_number`,`status`),
  KEY `idx_status` (`status`),
  KEY `idx_source_receiving_item` (`source_receiving_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_code` varchar(50) NOT NULL,
  `item_type` enum('RM','PM','FG','SFG','SUPPLIES') DEFAULT NULL,
  `raw_c_type` varchar(50) DEFAULT NULL,
  `item_description` varchar(255) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `item_uom` varchar(50) NOT NULL COMMENT 'Unit of Measurement',
  `uom_conversion` int(11) DEFAULT NULL COMMENT 'Units per case, e.g. 10 means 10 PCS = 1 CS. NULL when UOM is CS',
  `item_size` varchar(50) DEFAULT NULL,
  `item_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reorder_level` decimal(15,4) DEFAULT 0.0000,
  `date_created` datetime DEFAULT current_timestamp(),
  `status` tinyint(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
  `remove` tinyint(1) DEFAULT 0 COMMENT '0=active, 1=soft deleted',
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`item_id`),
  KEY `idx_item_code` (`item_code`),
  KEY `idx_status` (`status`),
  KEY `idx_remove` (`remove`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lmr_order_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `lmr_order_items` (
  `lri_id` int(11) NOT NULL AUTO_INCREMENT,
  `lmr_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `item_description` varchar(255) DEFAULT NULL,
  `uom` varchar(50) DEFAULT NULL,
  `item_type` varchar(50) DEFAULT NULL,
  `site` varchar(100) DEFAULT NULL,
  `qty_ordered` decimal(15,2) NOT NULL DEFAULT 0.00,
  `so_number` varchar(100) DEFAULT NULL,
  `bom_code` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`lri_id`),
  KEY `idx_lmr_items_lmr_id` (`lmr_id`),
  CONSTRAINT `fk_lmr_items_lmr` FOREIGN KEY (`lmr_id`) REFERENCES `lmr_orders` (`lmr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lmr_orders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `lmr_orders` (
  `lmr_id` int(11) NOT NULL AUTO_INCREMENT,
  `lmr_number` varchar(100) NOT NULL,
  `lmr_type` varchar(50) NOT NULL DEFAULT 'Standard',
  `lmr_site` varchar(100) NOT NULL DEFAULT '001 - Sterling Technopark',
  `order_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `planned_start_date` date DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT 3,
  `reference_no` varchar(100) DEFAULT NULL,
  `lmr_status` varchar(50) NOT NULL DEFAULT 'Planned',
  `customer_id` int(11) DEFAULT NULL,
  `customer_code` varchar(50) DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `batch_lot_no` varchar(100) DEFAULT NULL,
  `po_number` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`lmr_id`),
  UNIQUE KEY `uk_lmr_orders_number` (`lmr_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mrp_allocations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `mrp_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lmr_id` int(11) NOT NULL,
  `lri_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `allocated_qty` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `status` enum('active','released','cancelled') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lmr_item` (`lmr_id`,`item_id`),
  KEY `idx_lmr_id` (`lmr_id`),
  KEY `idx_item_id` (`item_id`),
  KEY `idx_status` (`status`),
  KEY `fk_alloc_lri` (`lri_id`),
  CONSTRAINT `fk_alloc_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alloc_lmr` FOREIGN KEY (`lmr_id`) REFERENCES `lmr_orders` (`lmr_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alloc_lri` FOREIGN KEY (`lri_id`) REFERENCES `lmr_order_items` (`lri_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mrp_run_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `mrp_run_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `fg_item_id` int(11) NOT NULL,
  `component_item_id` int(11) NOT NULL,
  `total_reqt` decimal(15,4) DEFAULT 0.0000,
  `soh` decimal(15,4) DEFAULT 0.0000,
  `allocated` decimal(15,4) DEFAULT 0.0000,
  `pending` decimal(15,4) DEFAULT 0.0000,
  `excess` decimal(15,4) DEFAULT 0.0000,
  `remarks` varchar(50) DEFAULT NULL,
  `qty_committed` decimal(15,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`id`),
  KEY `run_id` (`run_id`),
  KEY `fg_item_id` (`fg_item_id`),
  KEY `component_item_id` (`component_item_id`),
  CONSTRAINT `mrp_run_items_ibfk_1` FOREIGN KEY (`run_id`) REFERENCES `mrp_runs` (`run_id`) ON DELETE CASCADE ON DELETE CASCADE,
  CONSTRAINT `mrp_run_items_ibfk_2` FOREIGN KEY (`fg_item_id`) REFERENCES `items` (`item_id`),
  CONSTRAINT `mrp_run_items_ibfk_3` FOREIGN KEY (`component_item_id`) REFERENCES `items` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mrp_runs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `mrp_runs` (
  `run_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  `fg_item_id` int(11) DEFAULT NULL,
  `bom_id` int(11) DEFAULT NULL,
  `fg_code` varchar(50) DEFAULT NULL,
  `target_qty` decimal(15,4) DEFAULT NULL,
  `mrp_ref` varchar(30) DEFAULT NULL,
  `approval_status` varchar(30) DEFAULT NULL COMMENT 'Zero-lacking runs: pending_whse_approval|pending_sysadmin_approval|pending_qc_approval|approved|cancelled',
  PRIMARY KEY (`run_id`),
  KEY `po_id` (`po_id`),
  KEY `customer_id` (`customer_id`),
  KEY `user_id` (`user_id`),
  KEY `bom_id` (`bom_id`),
  CONSTRAINT `mrp_runs_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`),
  CONSTRAINT `mrp_runs_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `mrp_runs_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notification_reads`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `notification_reads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date_read` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_read` (`notification_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notification_reads_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`notification_id`),
  CONSTRAINT `notification_reads_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('delivery','production','po','finance','qc') NOT NULL,
  `target_department` enum('admin','warehouse','production','finance','qc') NOT NULL,
  `target_url` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `date_created` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `price_list`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `price_list` (
  `price_list_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `net_size` varchar(100) DEFAULT NULL,
  `price_per_pack` decimal(15,2) NOT NULL DEFAULT 0.00,
  `price_per_case` decimal(15,2) NOT NULL DEFAULT 0.00,
  `price_per_piece` decimal(15,2) NOT NULL DEFAULT 0.00,
  `vat_type` enum('vat','non_vat') DEFAULT 'vat',
  `status` tinyint(1) DEFAULT 1,
  `remove` tinyint(1) DEFAULT 0,
  `date_created` datetime DEFAULT current_timestamp(),
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`price_list_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `price_list_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_history`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `production_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) DEFAULT NULL,
  `poi_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `lot_number` varchar(100) DEFAULT NULL,
  `item_description` varchar(255) DEFAULT NULL,
  `sts_ref` varchar(255) DEFAULT NULL,
  `shift` varchar(50) DEFAULT NULL,
  `mo_no` varchar(100) DEFAULT NULL,
  `material_type` varchar(100) DEFAULT NULL,
  `reject_status` varchar(100) DEFAULT NULL,
  `sts_remarks` text DEFAULT NULL,
  `qc_remark` text DEFAULT NULL,
  `qc_inspected_by` int(11) DEFAULT NULL,
  `qc_inspected_at` datetime DEFAULT NULL,
  `qc_inspector_name` varchar(255) DEFAULT NULL,
  `qa_remark` text DEFAULT NULL,
  `qa_inspected_by` int(11) DEFAULT NULL,
  `qa_inspected_at` datetime DEFAULT NULL,
  `qa_inspector_name` varchar(255) DEFAULT NULL,
  `is_removed` tinyint(1) DEFAULT 0,
  `pcs_per_case` int(11) DEFAULT NULL,
  `prepared_by_name` varchar(255) DEFAULT NULL,
  `checked_by_name` varchar(255) DEFAULT NULL,
  `received_by_name` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `edited_by` int(11) DEFAULT NULL,
  `previous_quantity` int(11) DEFAULT 0,
  `added_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  `date_edited` datetime DEFAULT NULL,
  `old_lot_number` varchar(100) DEFAULT NULL,
  `old_added_quantity` int(11) DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  UNIQUE KEY `idx_sts_ref_unique` (`sts_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_lots`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `production_lots` (
  `lot_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) DEFAULT NULL,
  `poi_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `lot_number` varchar(100) NOT NULL,
  `quantity_produced` int(11) NOT NULL DEFAULT 0,
  `pcs_per_case` int(11) DEFAULT NULL,
  `lot_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  `is_removed` tinyint(1) DEFAULT 0 COMMENT '0=active, 1=soft deleted',
  `transferred_from_po_id` int(11) DEFAULT NULL,
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`lot_id`),
  KEY `po_id` (`po_id`),
  KEY `poi_id` (`poi_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_transferred_from` (`transferred_from_po_id`),
  KEY `idx_item_lot_active` (`item_id`,`lot_number`,`is_removed`),
  CONSTRAINT `production_lots_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`),
  CONSTRAINT `production_lots_ibfk_2` FOREIGN KEY (`poi_id`) REFERENCES `purchase_order_items` (`poi_id`),
  CONSTRAINT `production_lots_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- NOTE: not used by application code (kept for history)
-- Table structure for table `production_orders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `production_orders` (
  `production_order_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` enum('pending','in_production','completed','mrp_saved','cancelled') DEFAULT 'pending',
  `run_id` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `remove` tinyint(1) DEFAULT 0,
  `date_created` datetime DEFAULT current_timestamp(),
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`production_order_id`),
  KEY `po_id` (`po_id`),
  KEY `customer_id` (`customer_id`),
  KEY `item_id` (`item_id`),
  KEY `run_id` (`run_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `production_orders_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`),
  CONSTRAINT `production_orders_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `production_orders_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  CONSTRAINT `production_orders_ibfk_4` FOREIGN KEY (`run_id`) REFERENCES `mrp_runs` (`run_id`) ON DELETE CASCADE,
  CONSTRAINT `production_orders_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_reports`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `production_reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `history_id` int(11) NOT NULL,
  `poi_id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `old_lot_number` varchar(100) DEFAULT NULL,
  `reported_by` int(11) NOT NULL,
  `reason` text NOT NULL,
  `report_type` enum('lot_number','quantity') DEFAULT 'lot_number',
  `status` enum('pending','resolved') DEFAULT 'pending',
  `resolved_by` int(11) DEFAULT NULL,
  `new_lot_number` varchar(100) DEFAULT NULL,
  `date_reported` datetime DEFAULT current_timestamp(),
  `date_resolved` datetime DEFAULT NULL,
  PRIMARY KEY (`report_id`),
  KEY `idx_history_id` (`history_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `purchase_order_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `poi_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `item_uom` varchar(50) NOT NULL DEFAULT 'PCS',
  `quantity` int(11) NOT NULL,
  `produced_quantity` int(11) DEFAULT 0 COMMENT 'Produced quantity per item',
  `delivered_quantity` int(11) DEFAULT 0 COMMENT 'Delivered quantity per item',
  `unit_price` decimal(15,2) NOT NULL,
  PRIMARY KEY (`poi_id`),
  KEY `po_id` (`po_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `purchase_orders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `po_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_po_number` varchar(100) DEFAULT NULL,
  `customer_po_date` date DEFAULT NULL,
  `po_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected','delivered') DEFAULT 'pending',
  `completed_at` datetime DEFAULT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_quantity` int(11) DEFAULT 0,
  `customer_terms` int(11) DEFAULT 0 COMMENT 'Payment terms in days',
  `production_type` enum('normal','advance') DEFAULT 'normal',
  `produced_quantity` int(11) DEFAULT 0,
  `delivered_quantity` int(11) DEFAULT 0,
  `remove` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`po_id`),
  KEY `customer_id` (`customer_id`),
  KEY `requested_by` (`requested_by`),
  CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `qc_analysis_reports`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `qc_analysis_reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) DEFAULT NULL,
  `component_item_id` int(11) DEFAULT NULL,
  `receiving_item_id` int(11) DEFAULT NULL,
  `rm_name` varchar(255) NOT NULL,
  `supplier_name` varchar(150) DEFAULT NULL,
  `rr_number` varchar(50) DEFAULT NULL,
  `lot_number` varchar(100) DEFAULT NULL,
  `old_expiry_date` date DEFAULT NULL,
  `date_delivered` date DEFAULT NULL,
  `date_tested` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `delivery_type` enum('new_delivery','retest_1','retest_2','retest_3') NOT NULL DEFAULT 'new_delivery',
  `appearance_spec` varchar(255) DEFAULT NULL,
  `appearance_result` varchar(255) DEFAULT NULL,
  `color_spec` varchar(255) DEFAULT NULL,
  `color_result` varchar(255) DEFAULT NULL,
  `odor_spec` varchar(255) DEFAULT NULL,
  `odor_result` varchar(255) DEFAULT NULL,
  `texture_spec` varchar(255) DEFAULT NULL,
  `texture_result` varchar(255) DEFAULT NULL,
  `ph_spec` varchar(255) DEFAULT NULL,
  `ph_result` varchar(255) DEFAULT NULL,
  `viscosity_spec` varchar(255) DEFAULT NULL,
  `viscosity_result` varchar(255) DEFAULT NULL,
  `sg_spec` varchar(255) DEFAULT NULL,
  `sg_result` varchar(255) DEFAULT NULL,
  `solubility_spec` varchar(255) DEFAULT NULL,
  `solubility_result` varchar(255) DEFAULT NULL,
  `alcohol_spec` varchar(255) DEFAULT NULL,
  `alcohol_result` varchar(255) DEFAULT NULL,
  `microbial_spec` varchar(255) DEFAULT NULL,
  `microbial_result` varchar(255) DEFAULT NULL,
  `disposition` enum('APPROVED','REJECTED','HOLD','WAIVED') NOT NULL,
  `remarks` text DEFAULT NULL,
  `inspected_by` int(11) DEFAULT NULL,
  `date_inspected` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`report_id`),
  KEY `idx_mrp_target` (`run_id`,`component_item_id`),
  KEY `idx_receiving_target` (`receiving_item_id`),
  CONSTRAINT `qc_analysis_reports_ibfk_1` FOREIGN KEY (`run_id`) REFERENCES `mrp_runs` (`run_id`) ON DELETE CASCADE,
  CONSTRAINT `qc_analysis_reports_ibfk_2` FOREIGN KEY (`component_item_id`) REFERENCES `items` (`item_id`),
  CONSTRAINT `qc_analysis_reports_ibfk_3` FOREIGN KEY (`receiving_item_id`) REFERENCES `receiving_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `qc_analysis_reports_ibfk_4` FOREIGN KEY (`inspected_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `qc_inspections`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `qc_inspections` (
  `inspection_id` int(11) NOT NULL AUTO_INCREMENT,
  `receiving_item_id` int(11) NOT NULL,
  `decision` enum('PASSED','REJECTED') NOT NULL,
  `passed_qty` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `rejected_qty` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `inspector_name` varchar(255) NOT NULL,
  `remarks` text DEFAULT NULL,
  `inspected_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`inspection_id`),
  KEY `idx_receiving_item_id` (`receiving_item_id`),
  CONSTRAINT `fk_qc_receiving_item` FOREIGN KEY (`receiving_item_id`) REFERENCES `receiving_items` (`id`) ON DELETE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `receiving_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `receiving_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_ref` varchar(50) NOT NULL,
  `supplier` varchar(150) DEFAULT NULL,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `uom` varchar(20) DEFAULT NULL,
  `ordered_qty` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `received_qty` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `passed_qty` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `rejected_qty` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `lot_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `dr_invoice_no` varchar(100) DEFAULT NULL,
  `supplier_order_id` int(11) DEFAULT NULL,
  `received_date` date NOT NULL,
  `received_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `qc_status` enum('PENDING_QC','HOLD','PASSED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING_QC',
  `inspected_by` int(11) DEFAULT NULL,
  `inspected_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_supplier_order_id` (`supplier_order_id`),
  KEY `idx_receiving_items_received_by` (`received_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- NOTE: not used by application code (kept for history)
-- Table structure for table `sales_orders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `sales_orders` (
  `so_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `date_created` datetime DEFAULT current_timestamp(),
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`so_id`),
  KEY `po_id` (`po_id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `sales_orders_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`),
  CONSTRAINT `sales_orders_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- NOTE: not used by application code (kept for history)
-- Table structure for table `schema_migrations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `migration_key` varchar(150) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`migration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock_movements`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `movement_id` int(11) NOT NULL AUTO_INCREMENT,
  `movement_type` enum('IN','OUT') NOT NULL,
  `item_id` int(11) NOT NULL,
  `site_code` varchar(20) NOT NULL DEFAULT '001',
  `qty` decimal(15,4) NOT NULL,
  `reference_type` varchar(30) DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`movement_id`),
  KEY `idx_item_site` (`item_id`,`site_code`),
  KEY `idx_type_date` (`movement_type`,`date_created`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `supplier_orders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `supplier_orders` (
  `supplier_order_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(150) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `uom` varchar(50) DEFAULT NULL,
  `unit_cost` decimal(15,2) DEFAULT 0.00,
  `order_date` date DEFAULT NULL,
  `expected_date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `received_qty` decimal(15,4) DEFAULT 0.0000,
  `received_date` date DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `po_ref` varchar(50) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `po_id` int(11) DEFAULT NULL,
  `remove` tinyint(1) DEFAULT 0,
  `date_created` datetime DEFAULT current_timestamp(),
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mrp_run_id` int(11) DEFAULT NULL,
  `mrp_ref` varchar(30) DEFAULT NULL,
  `po_group_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`supplier_order_id`),
  KEY `item_id` (`item_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_supplier_orders_mrp_run` (`mrp_run_id`),
  KEY `idx_supplier_orders_po_group` (`po_group_id`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `supplier_orders_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  CONSTRAINT `supplier_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `supplier_orders_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `department` enum('admin','warehouse','production','finance','qc','qa','rnd') NOT NULL,
  `role` varchar(30) DEFAULT NULL COMMENT 'whse_admin|whse_officer|whse_ppic|whse_procurement|whse_receiving|qc_admin|qc_inspector',
  `status` tinyint(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
  `remove` tinyint(1) DEFAULT 0 COMMENT '0=active, 1=soft deleted',
  `date_created` datetime DEFAULT current_timestamp(),
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_department` (`department`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Final view structure for view `customer_finished_goods`
--

/*!50001 DROP VIEW IF EXISTS `customer_finished_goods`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `customer_finished_goods` AS select `items`.`item_id` AS `item_id`,`items`.`item_code` AS `item_code`,`items`.`item_description` AS `item_description`,`items`.`item_type` AS `item_type`,`items`.`customer_id` AS `customer_id`,`items`.`item_uom` AS `item_uom`,`items`.`uom_conversion` AS `uom_conversion`,`items`.`item_size` AS `item_size`,`items`.`item_amount` AS `item_amount`,`items`.`date_created` AS `date_created`,`items`.`status` AS `status`,`items`.`remove` AS `remove`,`items`.`last_update` AS `last_update` from `items` where `items`.`item_type` = 'FG' and `items`.`customer_id` is not null and `items`.`customer_id` <> 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `view_inventory_status`
--

/*!50001 DROP VIEW IF EXISTS `view_inventory_status`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `view_inventory_status` AS select `i`.`item_id` AS `item_id`,`i`.`item_code` AS `item_code`,`i`.`item_description` AS `item_description`,`i`.`item_type` AS `item_type`,`i`.`item_uom` AS `item_uom`,`i`.`reorder_level` AS `reorder_level`,coalesce(sum(`ib`.`qty_on_hand`),0) AS `total_soh`,coalesce(sum(`ib`.`qty_allocated`),0) AS `total_allocated`,coalesce(sum(`ib`.`qty_on_hand`),0) - coalesce(sum(`ib`.`qty_allocated`),0) AS `available_stock`,case when coalesce(sum(`ib`.`qty_on_hand`),0) - coalesce(sum(`ib`.`qty_allocated`),0) <= 0 then 'OUT OF STOCK' when coalesce(sum(`ib`.`qty_on_hand`),0) - coalesce(sum(`ib`.`qty_allocated`),0) <= `i`.`reorder_level` then 'LOW STOCK' else 'IN STOCK' end AS `inventory_status` from (`items` `i` left join `inventory_balances` `ib` on(`i`.`item_id` = `ib`.`item_id`)) group by `i`.`item_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- NOTE: not used by application code (kept for history)
-- Final view structure for view `vw_lmr_available_stock`
--

/*!50001 DROP VIEW IF EXISTS `vw_lmr_available_stock`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `vw_lmr_available_stock` AS select `inventory_stock`.`item_id` AS `item_id`,`inventory_stock`.`site_code` AS `site_code`,`inventory_stock`.`lot_number` AS `lot_number`,sum(`inventory_stock`.`qty_on_hand`) AS `available_qty`,max(`inventory_stock`.`updated_at`) AS `last_updated` from `inventory_stock` where `inventory_stock`.`status` = 'PASSED' and `inventory_stock`.`qty_on_hand` > 0 group by `inventory_stock`.`item_id`,`inventory_stock`.`site_code`,`inventory_stock`.`lot_number` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-13  8:53:16
