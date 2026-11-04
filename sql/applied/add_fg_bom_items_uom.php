<?php
/**
 * Migration: per-component UOM on BOM components.
 *
 * - fg_bom_items.uom → VARCHAR(50) NULL (new)
 *
 * NULL means "use the ingredient's items.item_uom"; views fall back to the
 * joined item UOM so legacy rows keep rendering unchanged.
 *
 * Run: php sql/pending/add_fg_bom_items_uom.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== fg_bom_items.uom Migration ===\n";

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

echo "1. Adding fg_bom_items.uom...\n";

$tableExists = (bool) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fg_bom_items'"
)->fetchColumn();

if (!$tableExists) {
    echo "   Skipped: fg_bom_items does not exist yet (fresh installs get the column from deploy_current.php)\n";
} elseif (!columnExists($pdo, 'fg_bom_items', 'uom')) {
    $after = columnExists($pdo, 'fg_bom_items', 'phase_code') ? ' AFTER `phase_code`' : ' AFTER `wastage_allowance_pct`';
    $pdo->exec("ALTER TABLE `fg_bom_items` ADD COLUMN `uom` VARCHAR(50) NULL{$after}");
    echo "   Added: fg_bom_items.uom\n";
} else {
    echo "   Already exists: fg_bom_items.uom\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['fg-bom-items-uom-v1']);

echo "=== Migration complete ===\n";
