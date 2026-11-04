<?php
/**
 * Migration: qc_analysis_reports â€” shared Raw Material Analysis Report.
 *
 * Used by BOTH QC flows:
 *   - MRP Check: per-run-item inspection (run_id + component_item_id)
 *   - Receiving Inspection: per-receipt inspection (receiving_item_id)
 * One report row per inspection; the latest report per target drives the
 * gate (MRP) or the receiving decision (PASSED/REJECTED/HOLD/WAIVED).
 *
 * Also extends receiving_items.qc_status with HOLD so a held receipt stays
 * in the queue for re-inspection instead of being force-passed/rejected.
 *
 * Run: php sql/pending/add_qc_analysis_reports.php
 */
require_once __DIR__ . '/../../core/BaseModel.php';

use App\Core\BaseModel;

$pdo = BaseModel::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== qc_analysis_reports Migration ===\n";

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

echo "1. Creating qc_analysis_reports...\n";

if (!tableExists($pdo, 'qc_analysis_reports')) {
    $pdo->exec("CREATE TABLE `qc_analysis_reports` (
        `report_id` INT(11) NOT NULL AUTO_INCREMENT,
        `run_id` INT(11) DEFAULT NULL,
        `component_item_id` INT(11) DEFAULT NULL,
        `receiving_item_id` INT(11) DEFAULT NULL,
        `rm_name` VARCHAR(255) NOT NULL,
        `supplier_name` VARCHAR(150) DEFAULT NULL,
        `rr_number` VARCHAR(50) DEFAULT NULL,
        `lot_number` VARCHAR(100) DEFAULT NULL,
        `old_expiry_date` DATE DEFAULT NULL,
        `date_delivered` DATE DEFAULT NULL,
        `date_tested` DATE DEFAULT NULL,
        `expiry_date` DATE DEFAULT NULL,
        `delivery_type` ENUM('new_delivery','retest_1','retest_2','retest_3') NOT NULL DEFAULT 'new_delivery',
        `appearance_spec` VARCHAR(255) DEFAULT NULL,
        `appearance_result` VARCHAR(255) DEFAULT NULL,
        `color_spec` VARCHAR(255) DEFAULT NULL,
        `color_result` VARCHAR(255) DEFAULT NULL,
        `odor_spec` VARCHAR(255) DEFAULT NULL,
        `odor_result` VARCHAR(255) DEFAULT NULL,
        `texture_spec` VARCHAR(255) DEFAULT NULL,
        `texture_result` VARCHAR(255) DEFAULT NULL,
        `ph_spec` VARCHAR(255) DEFAULT NULL,
        `ph_result` VARCHAR(255) DEFAULT NULL,
        `viscosity_spec` VARCHAR(255) DEFAULT NULL,
        `viscosity_result` VARCHAR(255) DEFAULT NULL,
        `sg_spec` VARCHAR(255) DEFAULT NULL,
        `sg_result` VARCHAR(255) DEFAULT NULL,
        `solubility_spec` VARCHAR(255) DEFAULT NULL,
        `solubility_result` VARCHAR(255) DEFAULT NULL,
        `alcohol_spec` VARCHAR(255) DEFAULT NULL,
        `alcohol_result` VARCHAR(255) DEFAULT NULL,
        `microbial_spec` VARCHAR(255) DEFAULT NULL,
        `microbial_result` VARCHAR(255) DEFAULT NULL,
        `disposition` ENUM('APPROVED','REJECTED','HOLD','WAIVED') NOT NULL,
        `remarks` TEXT DEFAULT NULL,
        `inspected_by` INT(11) DEFAULT NULL,
        `date_inspected` DATETIME NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`report_id`),
        KEY `idx_mrp_target` (`run_id`, `component_item_id`),
        KEY `idx_receiving_target` (`receiving_item_id`),
        CONSTRAINT `qc_analysis_reports_ibfk_1` FOREIGN KEY (`run_id`) REFERENCES `mrp_runs` (`run_id`) ON DELETE CASCADE,
        CONSTRAINT `qc_analysis_reports_ibfk_2` FOREIGN KEY (`component_item_id`) REFERENCES `items` (`item_id`),
        CONSTRAINT `qc_analysis_reports_ibfk_3` FOREIGN KEY (`receiving_item_id`) REFERENCES `receiving_items` (`id`) ON DELETE CASCADE,
        CONSTRAINT `qc_analysis_reports_ibfk_4` FOREIGN KEY (`inspected_by`) REFERENCES `users` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "   Created: qc_analysis_reports\n";
} else {
    echo "   Already exists: qc_analysis_reports\n";
}

echo "2. Extending receiving_items.qc_status with HOLD...\n";

$qcStatus = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'receiving_items' AND COLUMN_NAME = 'qc_status'")->fetchColumn();
if ($qcStatus !== false && strpos($qcStatus, "'HOLD'") === false) {
    $pdo->exec("ALTER TABLE receiving_items MODIFY qc_status ENUM('PENDING_QC','HOLD','PASSED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING_QC'");
    echo "   Added HOLD to receiving_items.qc_status\n";
} else {
    echo "   receiving_items.qc_status already has HOLD (or column missing)\n";
}

$record = $pdo->prepare('INSERT INTO schema_migrations (migration_key) VALUES (?)
    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP');
$record->execute(['qc-analysis-reports-v1']);

echo "=== Migration complete ===\n";
