<?php
/**
 * Migration: gl_accounts — GL Account master for the Finance sidebar
 * (GL Account Code, GL Account Description, Report Included,
 * Grouping in Financial Report).
 *
 * Run: php sql/pending/create_gl_accounts.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== gl_accounts Migration ===\n";

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

if (!tableExists($pdo, 'gl_accounts')) {
    $pdo->exec("CREATE TABLE `gl_accounts` (
        `gl_account_id` INT(11) NOT NULL AUTO_INCREMENT,
        `gl_account_code` VARCHAR(30) NOT NULL,
        `gl_account_description` VARCHAR(255) NOT NULL,
        `report_included` VARCHAR(100) DEFAULT NULL,
        `grouping_financial_report` VARCHAR(100) DEFAULT NULL,
        `status` TINYINT(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
        `remove` TINYINT(1) DEFAULT 0 COMMENT '0=active, 1=soft deleted',
        `date_created` DATETIME DEFAULT current_timestamp(),
        `last_update` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`gl_account_id`),
        UNIQUE KEY `gl_account_code` (`gl_account_code`),
        KEY `idx_status` (`status`),
        KEY `idx_remove` (`remove`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "   Created: gl_accounts\n";
} else {
    echo "   Already exists: gl_accounts\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['create-gl-accounts-v1']);

echo "=== Migration complete ===\n";
