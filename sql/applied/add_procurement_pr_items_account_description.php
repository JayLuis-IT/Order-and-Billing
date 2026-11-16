<?php
/**
 * Migration: procurement_pr_items.account_description — free-text account
 * description next to account_charged on the Process form and printed PR.
 *
 * Run: php sql/pending/add_procurement_pr_items_account_description.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== procurement_pr_items.account_description Migration ===\n";

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'procurement_pr_items' AND COLUMN_NAME = 'account_description'"
);
$stmt->execute();
if ((int) $stmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `procurement_pr_items`
        ADD COLUMN `account_description` VARCHAR(255) NULL AFTER `account_charged`");
    echo "   Added: procurement_pr_items.account_description\n";
} else {
    echo "   Already exists: account_description\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['procurement-pr-items-account-description-v1']);

echo "=== Migration complete ===\n";
