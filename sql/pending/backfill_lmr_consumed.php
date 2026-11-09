<?php
/**
 * Backfill: released LMRs that never consumed their materials.
 *
 * consume-on-release shipped after these LMRs were already Released, so
 * their stock was never deducted and their lot allocations still reserve
 * inventory. This script runs LmrModel::consumeMaterials() on each such
 * LMR (full sweep: operator lot allocations issued from their lots,
 * everything else FIFO from the BOM requirement) exactly as a fresh
 * release would today.
 *
 * Idempotent: consumeMaterials() self-checks materials_consumed_at.
 * Run standalone: php sql/pending/backfill_lmr_consumed.php
 * (also require()'d by deploy_current.php — shares $pdo when set)
 */

if (PHP_SAPI !== 'cli' && !empty($_SERVER['SERVER_SOFTWARE'])) {
    exit("CLI only\n");
}

$_lmrBackfillRoot = dirname(__DIR__, 2);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    chdir($_lmrBackfillRoot);
    require_once $_lmrBackfillRoot . '/core/BaseModel.php';
    $pdo = App\Core\BaseModel::getConnection();
}

spl_autoload_register(function ($class) use ($_lmrBackfillRoot) {
    $map = [
        $_lmrBackfillRoot . '\\warehouse\\controllers' => 'App\\Controllers',
        $_lmrBackfillRoot . '\\warehouse\\models' => 'App\\Models',
    ];
    foreach ($map as $dir => $prefix) {
        if (strpos($class, $prefix . '\\') === 0) {
            $rel = str_replace($prefix . '\\', '', $class);
            $path = $dir . '\\' . str_replace('\\', '\\', $rel) . '.php';
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}, true, true);

$rows = $pdo->query("
    SELECT lmr_id, lmr_number
    FROM lmr_orders
    WHERE lmr_status = 'Released' AND materials_consumed_at IS NULL
    ORDER BY lmr_id ASC
")->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "No released LMRs awaiting consumption backfill.\n";
    exit(0);
}

foreach ($rows as $row) {
    echo "Consuming LMR #{$row['lmr_id']} ({$row['lmr_number']})... ";
    try {
        (new App\Models\LmrModel())->consumeMaterials((int) $row['lmr_id']);
        echo "done\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}

echo "Backfill complete.\n";
