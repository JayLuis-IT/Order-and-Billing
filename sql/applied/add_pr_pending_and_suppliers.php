<?php
/**
 * Migration: Purchase Request lifecycle redesign
 *
 * - purchase_requests.status gains 'pending' (default): requestor submits ->
 *   PENDING -> procurement adds supplier(s) + price -> SUBMITTED -> requestor
 *   prints/signs/approves -> APPROVED -> procurement creates the PR ->
 *   IN_PROCESS. Existing 'submitted' rows predate supplier entry -> pending.
 * - purchase_request_suppliers: per-request supplier+price quotes entered by
 *   procurement while the request is pending.
 *
 * Run: php sql/pending/add_pr_pending_and_suppliers.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== PR pending status + purchase_request_suppliers Migration ===\n";

// 1. status ENUM gains 'pending', default pending (only when missing).
$stmt = $pdo->prepare(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_requests' AND COLUMN_NAME = 'status'"
);
$stmt->execute();
$colType = (string) $stmt->fetchColumn();
if ($colType !== '' && strpos($colType, 'pending') === false) {
    $pdo->exec("ALTER TABLE `purchase_requests`
        MODIFY `status` ENUM('pending','submitted','approved','in_process') NOT NULL DEFAULT 'pending'");
    echo "   Altered: status ENUM now includes pending (default pending)\n";
} elseif ($colType === '') {
    echo "   SKIP: purchase_requests.status column not found\n";
} else {
    echo "   Already includes pending\n";
}

// 2. Existing requestor-submitted rows predate supplier entry -> pending.
$moved = $pdo->exec("UPDATE purchase_requests SET status = 'pending' WHERE status = 'submitted'");
echo "   Migrated submitted -> pending: " . (int) $moved . " row(s)\n";

// 3. Per-request supplier + price quotes.
if (!function_exists('tableExists')) {
    function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}
if (!tableExists($pdo, 'purchase_request_suppliers')) {
    $pdo->exec("CREATE TABLE `purchase_request_suppliers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `pr_id` INT UNSIGNED NOT NULL,
        `supplier_id` INT(11) NOT NULL,
        `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `created_by` INT(11) DEFAULT NULL,
        `date_created` DATETIME DEFAULT current_timestamp(),
        `remove` TINYINT(1) DEFAULT 0 COMMENT '0=active, 1=soft deleted',
        `last_update` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_prs_pr` (`pr_id`),
        CONSTRAINT `fk_prs_pr` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`pr_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_prs_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "   Created: purchase_request_suppliers\n";
} else {
    echo "   Already exists: purchase_request_suppliers\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['pr-pending-suppliers-v1']);

echo "=== Migration complete ===\n";
