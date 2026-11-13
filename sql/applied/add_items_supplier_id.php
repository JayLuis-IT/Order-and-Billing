<?php
/**
 * Migration: items.supplier_id — preferred supplier link used by the
 * Item Master List edit modal (System Admin + R&D). Combined with the
 * existing items.item_amount as the item price.
 *
 * Run: php sql/pending/add_items_supplier_id.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== items.supplier_id Migration ===\n";

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
$stmt->execute(['items', 'supplier_id']);
if ((int) $stmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `items`
        ADD COLUMN `supplier_id` INT(11) DEFAULT NULL AFTER `customer_id`,
        ADD KEY `idx_supplier` (`supplier_id`)");
    echo "   Added: items.supplier_id + idx_supplier\n";
} else {
    echo "   Already exists: items.supplier_id\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['items-supplier-id-v1']);

echo "=== Migration complete ===\n";
