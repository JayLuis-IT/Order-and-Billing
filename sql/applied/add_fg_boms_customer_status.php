<?php
/**
 * Migration: BOM-level customer assignment + BOM active status.
 *
 * - fg_boms.customer_id → INT NULL (FK → customers.customer_id, ON DELETE SET NULL)
 * - fg_boms.status      → ENUM('active','inactive') NOT NULL DEFAULT 'active' (new)
 *
 * Backfill: customer_id is copied from the FG/SFG item's items.customer_id where
 * available; BOMs whose FG has no customer stay NULL and are required on next edit.
 *
 * Run: php sql/pending/add_fg_boms_customer_status.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== fg_boms customer_id / status Migration ===\n";

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

echo "1. Adding fg_boms.customer_id...\n";

if (!columnExists($pdo, 'fg_boms', 'customer_id')) {
    $pdo->exec("ALTER TABLE `fg_boms` ADD COLUMN `customer_id` INT NULL AFTER `fg_item_id`");
    echo "   Added: fg_boms.customer_id\n";
} else {
    echo "   Already exists: fg_boms.customer_id\n";
}

$hasFk = (bool) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fg_boms'
       AND CONSTRAINT_NAME = 'fk_fg_boms_customer'"
)->fetchColumn();

if (!$hasFk) {
    try {
        $pdo->exec("ALTER TABLE `fg_boms`
                    ADD CONSTRAINT `fk_fg_boms_customer`
                    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`customer_id`)
                    ON DELETE SET NULL");
        echo "   Added: FK fg_boms.customer_id → customers.customer_id\n";
    } catch (\PDOException $e) {
        echo "   Skipped FK (customers table not present yet): {$e->getMessage()}\n";
    }
} else {
    echo "   Already exists: fk_fg_boms_customer\n";
}

echo "2. Adding fg_boms.status...\n";

if (!columnExists($pdo, 'fg_boms', 'status')) {
    $pdo->exec("ALTER TABLE `fg_boms` ADD COLUMN `status` ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER `is_legacy_formula`");
    echo "   Added: fg_boms.status (default 'active')\n";
} else {
    echo "   Already exists: fg_boms.status\n";
}

echo "3. Backfilling customer_id from the FG/SFG item...\n";

$backfilled = $pdo->exec(
    "UPDATE fg_boms b
     JOIN items i ON i.item_id = b.fg_item_id
     SET b.customer_id = i.customer_id
     WHERE b.customer_id IS NULL
       AND i.customer_id IS NOT NULL
       AND i.customer_id <> 0"
);
$nullLeft = (int) $pdo->query(
    "SELECT COUNT(*) FROM fg_boms WHERE customer_id IS NULL"
)->fetchColumn();
echo "   Backfilled {$backfilled} BOM(s); {$nullLeft} still without a customer\n";

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['fg-boms-customer-status-v1']);

echo "=== Migration complete ===\n";
