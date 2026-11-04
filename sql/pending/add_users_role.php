<?php
/**
 * Migration: users.role — per-department role assignments.
 *
 * - users.role → VARCHAR(30) NULL
 *   Warehouse roles: whse_admin, whse_officer, whse_ppic, whse_procurement, whse_receiving
 *   QC roles:        qc_admin, qc_inspector
 *   NULL = no role assigned yet (users of other departments always NULL).
 *
 * Backfill: existing warehouse users → whse_admin, existing qc users → qc_admin
 * (the previous department-only model gave every warehouse/QC user full
 * department access, so the admin-equivalent role is the safe default).
 * Roles are managed afterwards via Admin → Users & Approvers.
 *
 * Run: php sql/pending/add_users_role.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== users.role Migration ===\n";

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

echo "1. Adding users.role...\n";

if (!columnExists($pdo, 'users', 'role')) {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `role` VARCHAR(30) NULL DEFAULT NULL AFTER `department`");
    echo "   Added: users.role\n";
} else {
    echo "   Already exists: users.role\n";
}

echo "2. Backfilling existing warehouse/qc users...\n";

$whse = $pdo->exec("UPDATE users SET role = 'whse_admin'
    WHERE department = 'warehouse' AND (role IS NULL OR role = '')");
echo "   {$whse} warehouse user(s) → whse_admin\n";

$qc = $pdo->exec("UPDATE users SET role = 'qc_admin'
    WHERE department = 'qc' AND (role IS NULL OR role = '')");
echo "   {$qc} qc user(s) → qc_admin\n";

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['users-role-v1']);

echo "=== Migration complete ===\n";
