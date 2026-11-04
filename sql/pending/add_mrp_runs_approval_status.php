<?php
/**
 * Migration: mrp_runs.approval_status — run-level approval state for
 * zero-lacking MRP runs (runs saved with no lacking RM, so no requisitions).
 *
 * Requisition-bearing runs keep their status in supplier_orders (legacy
 * two/three-gate flow); zero-lacking runs have no supplier_orders rows, so
 * their gate progress lives here:
 *   pending_whse_approval → pending_sysadmin_approval → pending_qc_approval
 *     → approved    (stock reserved at the QC gate; no purchase requests)
 *   reject at any gate   → cancelled
 * NULL = requisition-driven run (not managed by this column).
 *
 * Run: php sql/pending/add_mrp_runs_approval_status.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== mrp_runs.approval_status Migration ===\n";

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

echo "1. Adding mrp_runs.approval_status...\n";

if (!columnExists($pdo, 'mrp_runs', 'approval_status')) {
    $pdo->exec("ALTER TABLE `mrp_runs` ADD COLUMN `approval_status` VARCHAR(30) NULL DEFAULT NULL AFTER `mrp_ref`");
    echo "   Added: mrp_runs.approval_status\n";
} else {
    echo "   Already exists: mrp_runs.approval_status\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['mrp-runs-approval-status-v1']);

echo "=== Migration complete ===\n";
