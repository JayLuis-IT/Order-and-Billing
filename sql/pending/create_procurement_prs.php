<?php
/**
 * Procurement Purchase Requisition (PR) tables.
 *
 * Separates Procurement's formal PR document (PR###### ERP format) from the
 * requestor's Purchase Request Form (PRF / MPF-001-01) stored in
 * purchase_requests. Each approved PRF can be converted into exactly one PR.
 *
 * Run: php sql/pending/create_procurement_prs.php
 */

require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Procurement PR Tables Migration ===\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS `procurement_prs` (
    `pr_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pr_number` VARCHAR(20) NOT NULL,
    `prf_id` INT UNSIGNED DEFAULT NULL COMMENT 'Source purchase_requests.pr_id (PRF)',
    `po_site_code` VARCHAR(10) NOT NULL DEFAULT '001',
    `ship_to_code` VARCHAR(10) NOT NULL DEFAULT '001',
    `priority` ENUM('immediate','standard') NOT NULL DEFAULT 'standard',
    `pr_status` VARCHAR(20) NOT NULL DEFAULT 'Open',
    `printed` TINYINT(1) NOT NULL DEFAULT 0,
    `pr_date` DATE NOT NULL,
    `department` VARCHAR(50) DEFAULT NULL,
    `requisitioner` VARCHAR(100) DEFAULT NULL,
    `purpose` VARCHAR(255) DEFAULT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `remove` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`pr_id`),
    UNIQUE KEY `uq_procurement_pr_number` (`pr_number`),
    KEY `idx_ppr_prf` (`prf_id`),
    CONSTRAINT `fk_ppr_prf` FOREIGN KEY (`prf_id`) REFERENCES `purchase_requests` (`pr_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "   Table ready: procurement_prs\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS `procurement_pr_items` (
    `pri_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pr_id` INT UNSIGNED NOT NULL,
    `line_no` INT UNSIGNED NOT NULL DEFAULT 1,
    `item_number` VARCHAR(32) DEFAULT NULL COMMENT 'PR######-0000000000NN',
    `description` VARCHAR(255) NOT NULL,
    `account_charged` VARCHAR(30) DEFAULT NULL,
    `unit` VARCHAR(20) DEFAULT NULL,
    `qty` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    `date_required` DATE DEFAULT NULL,
    PRIMARY KEY (`pri_id`),
    KEY `idx_ppi_pr` (`pr_id`),
    CONSTRAINT `fk_ppi_pr_id` FOREIGN KEY (`pr_id`) REFERENCES `procurement_prs` (`pr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "   Table ready: procurement_pr_items\n";
