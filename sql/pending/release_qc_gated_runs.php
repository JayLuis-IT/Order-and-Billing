<?php
/**
 * Migration: release MRP runs stranded at the retired QC Admin gate.
 *
 * The MRP run chain is now two gates:
 *   WHSE Admin → System Admin → released to Procurement
 * QC Admin no longer gates runs — raw material inspection happens at
 * Receiving (goods received → QC → stock added).
 *
 * This migration moves anything stuck at the old third gate:
 *   1. Runs with approval_status = 'pending_qc_approval' (zero-lacking)
 *        → 'approved' (+ stock reserved)
 *   2. Requisition-bearing rows at status 'pending_qc_approval'
 *        → 'requested' (+ stock reserved) via WarehouseModel::decideMrpRun
 *   3. Orphan rows at 'pending_qc_approval' with no run → 'requested'
 *
 * Idempotent: each step no-ops when nothing matches.
 *
 * Run: php sql/pending/release_qc_gated_runs.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';
require_once __DIR__ . '/../../warehouse/models/BackloadModel.php';
require_once __DIR__ . '/../../warehouse/models/CatalogModel.php';
require_once __DIR__ . '/../../warehouse/models/WarehouseModel.php';

use App\Core\BaseModel;
use App\Models\WarehouseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$model = new WarehouseModel();
$label = 'System (QC gate retired migration)';

echo "=== Release QC-gated MRP runs Migration ===\n";

// 1. Zero-lacking runs stranded at the old QC gate → approved (+ reserved).
$zStmt = $pdo->query(
    "SELECT run_id, mrp_ref FROM mrp_runs WHERE approval_status = 'pending_qc_approval'"
);
$zeroRuns = $zStmt->fetchAll(PDO::FETCH_ASSOC);
echo '1. Zero-lacking runs at pending_qc_approval: ' . count($zeroRuns) . "\n";
foreach ($zeroRuns as $run) {
    try {
        $model->decideMrpRun((int) $run['run_id'], 'pending_qc_approval', 'approved', $label);
        echo "   Released run #{$run['run_id']} ({$run['mrp_ref']}) → approved, stock reserved\n";
    } catch (\Exception $e) {
        echo "   Skipped run #{$run['run_id']}: {$e->getMessage()}\n";
    }
}

// 2. Requisition-bearing runs whose rows sit at the old QC gate → requested.
//    (Runs with an approval_status header are zero-lacking — handled above.)
$rStmt = $pdo->query(
    "SELECT DISTINCT so.mrp_run_id AS run_id, mr.mrp_ref
     FROM supplier_orders so
     LEFT JOIN mrp_runs mr ON mr.run_id = so.mrp_run_id
     WHERE so.status = 'pending_qc_approval' AND so.`remove` = 0
       AND so.mrp_run_id IS NOT NULL
       AND (mr.approval_status IS NULL OR mr.approval_status = '')"
);
$runRows = $rStmt->fetchAll(PDO::FETCH_ASSOC);
echo '2. Requisition runs with rows at pending_qc_approval: ' . count($runRows) . "\n";
foreach ($runRows as $run) {
    try {
        $result = $model->decideMrpRun((int) $run['run_id'], 'pending_qc_approval', 'requested', $label);
        echo "   Released run #{$run['run_id']} ({$run['mrp_ref']}) → "
            . $result['moved'] . " requisition(s) released to Procurement, stock reserved\n";
    } catch (\Exception $e) {
        echo "   Skipped run #{$run['run_id']}: {$e->getMessage()}\n";
    }
}

// 3. Orphan rows (no run attached) → requested with an audit note.
$orphan = $pdo->exec(
    "UPDATE supplier_orders
     SET status = 'requested', last_update = NOW(),
         remarks = CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks, '') = '' THEN '' ELSE ' | ' END,
                          ' [Migration] Released from the retired QC gate — two-gate flow (WHSE → System Admin).')
     WHERE status = 'pending_qc_approval' AND `remove` = 0 AND mrp_run_id IS NULL"
);
echo "3. Orphan rows released: {$orphan}\n";

// Leftover sanity report (should be 0 after steps 1-2).
$left = (int) $pdo->query(
    "SELECT COUNT(*) FROM supplier_orders WHERE status = 'pending_qc_approval' AND `remove` = 0"
)->fetchColumn();
$leftRuns = (int) $pdo->query(
    "SELECT COUNT(*) FROM mrp_runs WHERE approval_status = 'pending_qc_approval'"
)->fetchColumn();
echo "Remaining at the retired gate: {$left} row(s), {$leftRuns} run header(s)\n";

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['release-qc-gated-runs-v1']);

echo "=== Migration complete ===\n";
