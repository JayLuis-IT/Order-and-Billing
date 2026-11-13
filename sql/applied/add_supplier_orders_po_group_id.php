<?php
/**
 * Migration: supplier_orders.po_group_id — groups the lines of one manually
 * created Purchasing PO (multi-item / multi-supplier cart save).
 *
 * - supplier_orders.po_group_id → INT(11) NULL after mrp_ref
 *
 * Semantics: every line of one manual PO shares a po_group_id (the id of the
 * first inserted line). WHSE Admin / System Admin approval acts on the whole
 * group. NULL = single-line order not created via the cart (MRP requisitions,
 * legacy rows, processed supplier orders).
 *
 * Run: php sql/pending/add_supplier_orders_po_group_id.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== supplier_orders.po_group_id Migration ===\n";

if (!function_exists('columnExists')) {
    function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('indexExists')) {
    function indexExists(PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $stmt->execute([$table, $index]);
        return (bool) $stmt->fetchColumn();
    }
}

echo "1. Adding supplier_orders.po_group_id...\n";

if (!columnExists($pdo, 'supplier_orders', 'po_group_id')) {
    $pdo->exec("ALTER TABLE `supplier_orders` ADD COLUMN `po_group_id` INT(11) NULL DEFAULT NULL AFTER `mrp_ref`");
    echo "   Added: supplier_orders.po_group_id\n";
} else {
    echo "   Already exists: supplier_orders.po_group_id\n";
}

echo "2. Adding index idx_supplier_orders_po_group...\n";

if (!indexExists($pdo, 'supplier_orders', 'idx_supplier_orders_po_group')) {
    $pdo->exec("ALTER TABLE `supplier_orders` ADD KEY `idx_supplier_orders_po_group` (`po_group_id`)");
    echo "   Added: idx_supplier_orders_po_group\n";
} else {
    echo "   Already exists: idx_supplier_orders_po_group\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['supplier-orders-po-group-id-v1']);

echo "=== Migration complete ===\n";
