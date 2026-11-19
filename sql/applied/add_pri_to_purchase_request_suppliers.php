<?php
/**
 * Migration: purchase_request_suppliers.pri_id — supplier+price quotes are
 * recorded PER line item (merged into the requested-items rows on the Process
 * modal and the printed PRF), not just per request.
 *
 * Backfills any existing quote rows to their PR's first line item.
 *
 * Run: php sql/pending/add_pri_to_purchase_request_suppliers.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== purchase_request_suppliers.pri_id Migration ===\n";

// 1. Column.
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_request_suppliers' AND COLUMN_NAME = 'pri_id'"
);
$stmt->execute();
if ((int) $stmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `purchase_request_suppliers`
        ADD COLUMN `pri_id` INT(10) UNSIGNED NULL AFTER `pr_id`");
    echo "   Added: purchase_request_suppliers.pri_id\n";
} else {
    echo "   Already exists: pri_id\n";
}

// 2. Index (skip when it already exists).
$idx = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_request_suppliers' AND INDEX_NAME = 'idx_prs_pri'"
);
$idx->execute();
if ((int) $idx->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE `purchase_request_suppliers` ADD KEY `idx_prs_pri` (`pri_id`)');
    echo "   Added: idx_prs_pri\n";
} else {
    echo "   Already exists: idx_prs_pri\n";
}

// 3. FK (skip when it already exists).
$fk = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_request_suppliers'
       AND CONSTRAINT_NAME = 'fk_prs_pri'"
);
$fk->execute();
if ((int) $fk->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `purchase_request_suppliers`
        ADD CONSTRAINT `fk_prs_pri` FOREIGN KEY (`pri_id`)
        REFERENCES `purchase_request_items` (`pri_id`) ON DELETE CASCADE");
    echo "   Added: fk_prs_pri\n";
} else {
    echo "   Already exists: fk_prs_pri\n";
}

// 4. Backfill legacy rows (pre per-item quotes) to the PR's first line item.
$backfilled = $pdo->exec(
    "UPDATE purchase_request_suppliers prs
     JOIN (SELECT pr_id, MIN(pri_id) AS first_pri FROM purchase_request_items GROUP BY pr_id) m
       ON m.pr_id = prs.pr_id
     SET prs.pri_id = m.first_pri
     WHERE prs.pri_id IS NULL"
);
echo "   Backfilled: " . (int) $backfilled . " existing row(s)\n";

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['pr-suppliers-per-item-v1']);

echo "=== Migration complete ===\n";
