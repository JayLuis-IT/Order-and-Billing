<?php
/**
 * Migration: supplier_orders overage approval columns (receiving guardrails).
 *
 * Over-receipts exceeding the ordered PO quantity are blocked at receiving
 * until the System Admin approves the overage. Columns added:
 *   - overage_request_qty    DECIMAL(15,4) NULL — excess beyond ordered that was requested
 *   - overage_request_status VARCHAR(30) NULL  — 'pending' | 'approved' | 'rejected'
 *   - overage_approved_qty   DECIMAL(15,4) NULL — approved excess above ordered
 *
 * Under- and partial receipts are unaffected (they never touch these columns;
 * NULL request state with NULL approved qty = straight-through to QC).
 *
 * Run: php sql/pending/add_supplier_orders_overage_approval.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== supplier_orders overage approval Migration ===\n";

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

$columns = [
    'overage_request_qty' => "ALTER TABLE `supplier_orders` ADD COLUMN `overage_request_qty` DECIMAL(15,4) NULL",
    'overage_request_status' => "ALTER TABLE `supplier_orders` ADD COLUMN `overage_request_status` VARCHAR(30) NULL",
    'overage_approved_qty' => "ALTER TABLE `supplier_orders` ADD COLUMN `overage_approved_qty` DECIMAL(15,4) NULL",
];

foreach ($columns as $column => $ddl) {
    if (!columnExists($pdo, 'supplier_orders', $column)) {
        $pdo->exec($ddl);
        echo "   Added: supplier_orders.{$column}\n";
    } else {
        echo "   Already exists: supplier_orders.{$column}\n";
    }
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['supplier-orders-overage-approval-v1']);

echo "=== Migration complete ===\n";
