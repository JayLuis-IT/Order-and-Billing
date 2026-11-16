<?php
/**
 * Migration: purchase_requests.status gains 'in_process' —
 * submitted -> approved (signed PRF) -> in_process (procurement clicks
 * Process on the queue).
 *
 * Run: php sql/pending/add_purchase_requests_in_process.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== purchase_requests.in_process Migration ===\n";

$stmt = $pdo->prepare(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_requests' AND COLUMN_NAME = 'status'"
);
$stmt->execute();
$colType = (string) $stmt->fetchColumn();

if ($colType !== '' && strpos($colType, 'in_process') === false) {
    $pdo->exec("ALTER TABLE `purchase_requests`
        MODIFY `status` ENUM('submitted','approved','in_process') NOT NULL DEFAULT 'submitted'");
    echo "   Altered: status ENUM now includes in_process\n";
} elseif ($colType === '') {
    echo "   SKIP: purchase_requests.status column not found\n";
} else {
    echo "   Already includes in_process\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['purchase-requests-in-process-v1']);

echo "=== Migration complete ===\n";
