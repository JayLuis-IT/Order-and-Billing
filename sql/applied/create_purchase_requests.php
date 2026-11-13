<?php
/**
 * Migration: Purchase Request (PR) module tables.
 *
 * - purchase_requests        — PR header (department, purpose, classification,
 *                              priority, status, signed-PRF proof attachment)
 * - purchase_request_items   — PR line items (optional item_code link to the
 *                              Item Master List, free-text allowed)
 *
 * Run: php sql/pending/create_purchase_requests.php
 * (also picked up automatically by php sql/run_pending.php)
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Purchase Request Tables Migration ===\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS `purchase_requests` (
    `pr_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pr_number` VARCHAR(30) NOT NULL,
    `department` VARCHAR(50) NOT NULL,
    `purpose` VARCHAR(255) NOT NULL,
    `date_created` DATE NOT NULL,
    `classification` ENUM('new','repeat_order') NOT NULL DEFAULT 'new',
    `priority` ENUM('immediate','standard') NOT NULL DEFAULT 'standard',
    `status` ENUM('submitted','approved') NOT NULL DEFAULT 'submitted',
    `prepared_by` VARCHAR(120) NULL DEFAULT NULL,
    `checked_by` VARCHAR(120) NULL DEFAULT NULL,
    `approved_by_name` VARCHAR(120) NULL DEFAULT NULL,
    `received_by` VARCHAR(120) NULL DEFAULT NULL,
    `notes` TEXT NULL,
    `signed_file_name` VARCHAR(255) NULL DEFAULT NULL,
    `signed_file_path` VARCHAR(255) NULL DEFAULT NULL,
    `signed_file_type` VARCHAR(100) NULL DEFAULT NULL,
    `signed_uploaded_by` INT NULL DEFAULT NULL,
    `signed_uploaded_at` DATETIME NULL DEFAULT NULL,
    `approved_by` INT NULL DEFAULT NULL,
    `approved_at` DATETIME NULL DEFAULT NULL,
    `created_by` INT NOT NULL,
    `remove` TINYINT(1) NOT NULL DEFAULT 0,
    `date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_update` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`pr_id`),
    UNIQUE KEY `uq_pr_number` (`pr_number`),
    KEY `idx_pr_department` (`department`),
    KEY `idx_pr_status` (`status`),
    KEY `idx_pr_created_by` (`created_by`),
    CONSTRAINT `fk_pr_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
echo "   Table ready: purchase_requests\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS `purchase_request_items` (
    `pri_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pr_id` INT UNSIGNED NOT NULL,
    `item_id` INT NULL DEFAULT NULL,
    `item_code` VARCHAR(50) NULL DEFAULT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `specs` VARCHAR(255) NULL DEFAULT NULL,
    `dimension` VARCHAR(100) NULL DEFAULT NULL,
    `qty` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `uom` VARCHAR(50) NULL DEFAULT NULL,
    `preferred_supplier` VARCHAR(150) NULL DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`pri_id`),
    KEY `idx_pri_pr_id` (`pr_id`),
    KEY `idx_pri_item_id` (`item_id`),
    CONSTRAINT `fk_pri_pr_id` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`pr_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pri_item_id` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
echo "   Table ready: purchase_request_items\n";

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['create-purchase-requests-v1']);

echo "=== Migration complete ===\n";
