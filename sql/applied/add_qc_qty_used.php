<?php
/**
 * Migration: qty_used columns — quantity consumed during QC inspection.
 *
 * QC Admin enters how much material was used for testing (e.g. 0.03kg out
 * of 2.5kg received). This quantity is released from inventory_balances
 * .qty_for_inspect but never enters qty_on_hand — it is consumed. A
 * stock_movements OUT row is logged for audit.
 *
 * Invariant becomes: passed + rejected + qty_used == received
 *
 * Columns added to:
 *   - qc_analysis_reports (QCF-06 RM)
 *   - qc_pm_reports       (QCF-007 PM)
 *   - receiving_items     (queue accounting)
 *   - qc_inspections      (decision audit log)
 *
 * Run: php sql/pending/add_qc_qty_used.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== qty_used Migration ===\n";

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

$tables = ['qc_analysis_reports', 'qc_pm_reports', 'receiving_items', 'qc_inspections'];
$i = 1;
foreach ($tables as $table) {
    echo "{$i}. Adding qty_used to {$table}...\n";
    if (columnExists($pdo, $table, 'qty_used')) {
        echo "   Already exists\n";
    } else {
        // Anchor after rejected_qty where it exists, otherwise append at end.
        $hasRejected = columnExists($pdo, $table, 'rejected_qty');
        $after = $hasRejected ? " AFTER `rejected_qty`" : "";
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `qty_used` DECIMAL(15,4) NOT NULL DEFAULT 0{$after}");
        echo "   Added qty_used\n";
    }
    $i++;
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['qc-qty-used-v1']);

echo "=== Migration complete ===\n";
