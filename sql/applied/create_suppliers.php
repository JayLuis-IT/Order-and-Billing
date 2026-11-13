<?php
/**
 * Migration: suppliers — supplier master behind the Supplier Management
 * page (System Admin + R&D sidebars) and the Purchasing PO supplier
 * dropdown (replaces the hardcoded placeholder list in
 * WarehouseModel::getSuppliers()).
 *
 * Run: php sql/pending/create_suppliers.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== suppliers Migration ===\n";

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

if (!tableExists($pdo, 'suppliers')) {
    $pdo->exec("CREATE TABLE `suppliers` (
        `supplier_id` INT(11) NOT NULL AUTO_INCREMENT,
        `supplier_code` VARCHAR(50) NOT NULL,
        `supplier_name` VARCHAR(255) NOT NULL,
        `contact_person` VARCHAR(100) DEFAULT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `date_created` DATETIME DEFAULT current_timestamp(),
        `status` TINYINT(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
        `remove` TINYINT(1) DEFAULT 0 COMMENT '0=active, 1=soft deleted',
        `last_update` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`supplier_id`),
        UNIQUE KEY `supplier_code` (`supplier_code`),
        KEY `idx_status` (`status`),
        KEY `idx_remove` (`remove`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "   Created: suppliers\n";
} else {
    echo "   Already exists: suppliers\n";
}

// Seed the five placeholder suppliers the hardcoded dropdown used to
// return, so Purchasing PO keeps working right after the switch.
$seedCount = (int) $pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();
if ($seedCount === 0) {
    $ins = $pdo->prepare(
        'INSERT INTO suppliers (supplier_code, supplier_name, status, `remove`) VALUES (?, ?, 1, 0)'
    );
    for ($i = 1; $i <= 5; $i++) {
        $ins->execute(['SUPPLIER-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'Supplier ' . $i]);
    }
    echo "   Seeded 5 placeholder suppliers\n";
} else {
    echo "   Seed skipped ({$seedCount} row(s) present)\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['create-suppliers-v1']);

echo "=== Migration complete ===\n";
