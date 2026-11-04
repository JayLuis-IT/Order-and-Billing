<?php
/**
 * Migration: multi-BOM creation in the R&D "Create New BOM" modal.
 *
 * - fg_boms.bom_type     → VARCHAR(50) DEFAULT 'Alternative' after is_default
 *     'Primary'   = first active formulation of the item (is_default = 1)
 *     'Alternative' = every additional formulation (is_default = 0)
 * - fg_boms.variant_name → VARCHAR(100) NULL after bom_type
 *     Free-text designation shown while selecting a formulation for MRP
 *     (e.g. "Supplier B RM Version").
 *
 * Notes:
 * - No unique index on fg_boms needs dropping: multi-BOM uniqueness lives in
 *   PHP only (BomModel::bomCodeExistsForItem — one bom_code per finished good)
 *   and fg_boms carries just PRIMARY KEY (id) + non-unique FK indexes.
 * - bom_type is kept in step with is_default: backfilled from it here, and
 *   set by BomModel::create()/delete().
 *
 * Run: php sql/pending/add_fg_boms_bom_type_variant.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== fg_boms.bom_type / variant_name Migration ===\n";

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

echo "0. Dropping any legacy unique index that blocked multiple BOMs...\n";

// Spec asks to drop a unique item_code index if present. This schema has none
// (fg_boms carries only PRIMARY KEY (id) + non-unique indexes), so this is an
// idempotent no-op guard kept for databases migrated from the old layout.
$legacyUnique = $pdo->query(
    "SELECT INDEX_NAME FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fg_boms'
       AND NON_UNIQUE = 0 AND INDEX_NAME <> 'PRIMARY'
       AND COLUMN_NAME IN ('fg_item_id', 'item_code')
     GROUP BY INDEX_NAME"
)->fetchAll(\PDO::FETCH_COLUMN);
foreach ($legacyUnique as $idx) {
    try {
        $pdo->exec("ALTER TABLE `fg_boms` DROP INDEX `{$idx}`");
        echo "   Dropped legacy unique index: {$idx}\n";
    } catch (\PDOException $e) {
        echo "   Skipped {$idx} (likely still referenced by a foreign key): {$e->getMessage()}\n";
    }
}
if (!$legacyUnique) {
    echo "   No unique index on fg_boms — nothing to drop\n";
}

echo "1. Adding fg_boms.bom_type...\n";

if (!columnExists($pdo, 'fg_boms', 'bom_type')) {
    $pdo->exec("ALTER TABLE `fg_boms` ADD COLUMN `bom_type` VARCHAR(50) DEFAULT 'Alternative' AFTER `is_default`");
    echo "   Added: fg_boms.bom_type (default 'Alternative')\n";
} else {
    echo "   Already exists: fg_boms.bom_type\n";
}

echo "2. Adding fg_boms.variant_name...\n";

if (!columnExists($pdo, 'fg_boms', 'variant_name')) {
    $pdo->exec("ALTER TABLE `fg_boms` ADD COLUMN `variant_name` VARCHAR(100) NULL AFTER `bom_type`");
    echo "   Added: fg_boms.variant_name\n";
} else {
    echo "   Already exists: fg_boms.variant_name\n";
}

echo "3. Backfilling bom_type from is_default...\n";

$primaries = $pdo->exec(
    "UPDATE fg_boms SET bom_type = 'Primary'
     WHERE is_default = 1 AND (bom_type IS NULL OR bom_type <> 'Primary')"
);
$alternatives = $pdo->exec(
    "UPDATE fg_boms SET bom_type = 'Alternative'
     WHERE is_default = 0 AND (bom_type IS NULL OR bom_type <> 'Alternative')"
);
echo "   Set {$primaries} row(s) to Primary, {$alternatives} to Alternative\n";

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['fg-boms-bom-type-variant-v1']);

echo "=== Migration complete ===\n";
