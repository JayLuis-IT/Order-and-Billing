<?php
/**
 * Migration: stock_movements — ledger for the Stock In/Out page
 * (warehouse receiving personnel role).
 *
 * Every manual stock IN (goods received against a purchasing PO reference)
 * and stock OUT (issued/removed from SOH) writes one row here, and the
 * matching delta is applied to inventory_balances.qty_on_hand.
 *
 * Run: php sql/pending/add_stock_movements.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== stock_movements Migration ===\n";

if (!function_exists('tableExists')) {
    function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

if (!tableExists($pdo, 'stock_movements')) {
    $pdo->exec("CREATE TABLE `stock_movements` (
        `movement_id` INT(11) NOT NULL AUTO_INCREMENT,
        `movement_type` ENUM('IN','OUT') NOT NULL,
        `item_id` INT(11) NOT NULL,
        `site_code` VARCHAR(20) NOT NULL DEFAULT '001',
        `qty` DECIMAL(15,4) NOT NULL,
        `reference_type` VARCHAR(30) DEFAULT NULL,
        `reference_no` VARCHAR(50) DEFAULT NULL,
        `remarks` TEXT DEFAULT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `date_created` DATETIME DEFAULT current_timestamp(),
        PRIMARY KEY (`movement_id`),
        KEY `idx_item_site` (`item_id`, `site_code`),
        KEY `idx_type_date` (`movement_type`, `date_created`),
        CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
        CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "   Created: stock_movements\n";
} else {
    echo "   Already exists: stock_movements\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['stock-movements-v1']);

echo "=== Migration complete ===\n";
