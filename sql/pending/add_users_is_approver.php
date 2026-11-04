<?php
/**
 * Migration: users.is_approver — Warehouse Admin approver flag.
 *
 * - users.is_approver → TINYINT(1) NOT NULL DEFAULT 0
 *   Marks a warehouse user as a "Warehouse Admin" who may approve/reject
 *   MRP draft requisitions (step 1 of the dual approval gate).
 *   System Admin (step 2) approval remains department='admin'.
 *
 * No automatic backfill: grant the flag deliberately via
 *   php grant-approver.php <username>
 * (never through signup — new users are always non-approvers).
 *
 * Run: php sql/pending/add_users_is_approver.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== users.is_approver Migration ===\n";

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

echo "1. Adding users.is_approver...\n";

if (!columnExists($pdo, 'users', 'is_approver')) {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_approver` TINYINT(1) NOT NULL DEFAULT 0");
    echo "   Added: users.is_approver\n";
} else {
    echo "   Already exists: users.is_approver\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['users-is-approver-v1']);

echo "=== Migration complete ===\n";
