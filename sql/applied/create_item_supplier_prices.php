<?php
/**
 * Migration: item_supplier_prices — dated supplier price history per item
 * (Item Prices page: multi-supplier, price trend, high/low tracking).
 *
 * Run: php sql/pending/create_item_supplier_prices.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== item_supplier_prices Migration ===\n";

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

if (!tableExists($pdo, 'item_supplier_prices')) {
    $pdo->exec("CREATE TABLE `item_supplier_prices` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `item_id` INT(11) NOT NULL,
        `supplier_id` INT(11) NOT NULL,
        `price` DECIMAL(15,2) NOT NULL,
        `effective_date` DATE NOT NULL,
        `remarks` VARCHAR(255) DEFAULT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `date_created` DATETIME DEFAULT current_timestamp(),
        `remove` TINYINT(1) DEFAULT 0 COMMENT '0=active, 1=soft deleted',
        `last_update` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_item_supplier` (`item_id`, `supplier_id`),
        KEY `idx_supplier_date` (`supplier_id`, `effective_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "   Created: item_supplier_prices\n";
} else {
    echo "   Already exists: item_supplier_prices\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['item-supplier-prices-v1']);

echo "=== Migration complete ===\n";
