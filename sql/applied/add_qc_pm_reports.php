<?php
/**
 * Migration: qc_pm_reports — Packaging Material Inspection Report (QCF-007).
 *
 * Used by Receiving Inspection when items.item_type = 'PM'. Separate from
 * qc_analysis_reports (QCF-06 Raw Material) because the PM form has a
 * different layout: dimensional measurements (10 samples per dimension),
 * functional tests, and appearance/visual defect tracking.
 *
 * Dimensional / functional / appearance sections are stored as JSON TEXT
 * columns to keep the schema flexible (variable number of dimensions and
 * defect rows).
 *
 * Run: php sql/pending/add_qc_pm_reports.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== qc_pm_reports Migration ===\n";

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

echo "1. Creating qc_pm_reports...\n";

if (!tableExists($pdo, 'qc_pm_reports')) {
    $pdo->exec("CREATE TABLE `qc_pm_reports` (
        `report_id` INT(11) NOT NULL AUTO_INCREMENT,
        `receiving_item_id` INT(11) NOT NULL,
        `pm_code` VARCHAR(50) DEFAULT NULL,
        `pm_name` VARCHAR(255) DEFAULT NULL,
        `supplier_name` VARCHAR(150) DEFAULT NULL,
        `rr_number` VARCHAR(50) DEFAULT NULL,
        `date_delivered` DATE DEFAULT NULL,
        `date_tested` DATE DEFAULT NULL,
        `quantity_delivered` DECIMAL(15,4) DEFAULT NULL,
        `sample_qty` VARCHAR(50) DEFAULT NULL,
        `dimensional_data` TEXT DEFAULT NULL,
        `result_dimensional` VARCHAR(255) DEFAULT NULL,
        `functional_data` TEXT DEFAULT NULL,
        `appearance_data` TEXT DEFAULT NULL,
        `total_reject_qty` DECIMAL(15,4) DEFAULT NULL,
        `total_reject_rate` VARCHAR(50) DEFAULT NULL,
        `disposition` ENUM('APPROVED','REJECTED','HOLD','WAIVED') NOT NULL,
        `remarks` TEXT DEFAULT NULL,
        `inspected_by` INT(11) DEFAULT NULL,
        `date_inspected` DATETIME NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`report_id`),
        KEY `idx_pm_receiving_target` (`receiving_item_id`),
        CONSTRAINT `qc_pm_reports_ibfk_1` FOREIGN KEY (`receiving_item_id`) REFERENCES `receiving_items` (`id`) ON DELETE CASCADE,
        CONSTRAINT `qc_pm_reports_ibfk_2` FOREIGN KEY (`inspected_by`) REFERENCES `users` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "   Created: qc_pm_reports\n";
} else {
    echo "   Already exists: qc_pm_reports\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['qc-pm-reports-v1']);

echo "=== Migration complete ===\n";
