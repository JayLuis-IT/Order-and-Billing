<?php
/**
 * Migration: multi-BOM support for the MRP Sheet.
 *
 * - fg_boms.is_default → TINYINT(1) NOT NULL DEFAULT 0 (after status)
 *     marks the default formulation used when the MRP Sheet runs without an
 *     explicitly chosen BOM.
 * - mrp_runs.bom_id → INT NULL (after fg_item_id) + index
 *     records which BOM an MRP snapshot was calculated from. No FK: the BOM
 *     may be deleted later and the snapshot must survive.
 *
 * Backfill: the oldest active BOM of each FG/SFG becomes its default.
 *
 * Run: php sql/pending/add_fg_boms_is_default.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== fg_boms.is_default / mrp_runs.bom_id Migration ===\n";

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (bool) $stmt->fetchColumn();
}

echo "1. Adding fg_boms.is_default...\n";

if (!columnExists($pdo, 'fg_boms', 'is_default')) {
    $pdo->exec("ALTER TABLE `fg_boms` ADD COLUMN `is_default` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
    echo "   Added: fg_boms.is_default\n";
} else {
    echo "   Already exists: fg_boms.is_default\n";
}

echo "2. Backfilling one default BOM per FG/SFG (oldest active BOM)...\n";

$backfilled = $pdo->exec(
    "UPDATE fg_boms b
     JOIN (
         SELECT MIN(id) AS min_id
         FROM fg_boms
         WHERE status = 'active'
         GROUP BY fg_item_id
     ) pick ON pick.min_id = b.id
     SET b.is_default = 1
     WHERE b.is_default = 0"
);
$noDefault = (int) $pdo->query(
    "SELECT COUNT(DISTINCT fg_item_id) FROM fg_boms
     WHERE status = 'active' AND fg_item_id NOT IN (
         SELECT fg_item_id FROM fg_boms WHERE status = 'active' AND is_default = 1
     )"
)->fetchColumn();
echo "   Flagged {$backfilled} default BOM(s); {$noDefault} active FG/SFG still without a default\n";

// Safety net: never leave two defaults on one FG (lowest active id wins).
$duplicated = $pdo->exec(
    "UPDATE fg_boms b
     JOIN fg_boms k ON k.fg_item_id = b.fg_item_id
                   AND k.status = 'active' AND k.is_default = 1
     SET b.is_default = 0
     WHERE b.is_default = 1
       AND b.status = 'active'
       AND b.id > k.id"
);
if ($duplicated) {
    echo "   Cleared {$duplicated} duplicate default flag(s)\n";
}

echo "3. Adding mrp_runs.bom_id...\n";

if (!columnExists($pdo, 'mrp_runs', 'bom_id')) {
    $pdo->exec("ALTER TABLE `mrp_runs` ADD COLUMN `bom_id` INT NULL AFTER `fg_item_id`");
    echo "   Added: mrp_runs.bom_id\n";
} else {
    echo "   Already exists: mrp_runs.bom_id\n";
}

if (!indexExists($pdo, 'mrp_runs', 'bom_id')) {
    $pdo->exec("ALTER TABLE `mrp_runs` ADD KEY `bom_id` (`bom_id`)");
    echo "   Added index: mrp_runs.bom_id\n";
} else {
    echo "   Already indexed: mrp_runs.bom_id\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['fg-boms-is-default-v1']);

echo "=== Migration complete ===\n";
