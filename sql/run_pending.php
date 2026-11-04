<?php
/**
 * Run All Pending Migrations
 *
 * Scans sql/pending/ for .sql and .php files, executes them
 * in alphabetical order, then moves them to sql/applied/.
 *
 * Usage:
 *   CLI:    php sql/run_pending.php
 *   Browser: http://localhost/order-billing-system/sql/run_pending.php
 */

$isCli = (php_sapi_name() === 'cli');

// --- Bootstrap ---
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../core/Config.php';

if (!$isCli) {
    App\Core\Config::init();
    echo '<!DOCTYPE html><html><head><title>Pending Migrations</title>';
    echo '<link rel="stylesheet" href="../public/css/bootstrap.min.css">';
    echo '<style>body{background:#1a1a2e;color:#e0e0e0;padding:2rem;font-family:monospace}';
    echo '.step{padding:4px 0;border-bottom:1px solid #333}';
    echo '.ok{color:#4ade80}.skip{color:#facc15}.err{color:#f87171}.info{color:#94a3b8}</style>';
    echo '</head><body><div class="container">';
    echo '<h4 class="text-light mb-3"><i class="bi bi-database"></i> Pending Migrations</h4>';
} else {
    echo "=== Pending Migrations ===\n\n";
}

$pdo = App\Core\BaseModel::getConnection();
$pendingDir = __DIR__ . '/pending';
$appliedDir = __DIR__ . '/applied';

if (!is_dir($appliedDir)) {
    mkdir($appliedDir, 0755, true);
}

// --- Scan pending files ---
$files = glob($pendingDir . '/*.{sql,php}', GLOB_BRACE);
if ($files === false || count($files) === 0) {
    $msg = "No pending migrations found.";
    if ($isCli) {
        echo "  {$msg}\n";
    } else {
        echo '<div class="step info">' . $msg . '</div>';
        echo '</div></body></html>';
    }
    exit(0);
}

sort($files, SORT_NATURAL);
$total = count($files);
$applied = 0;
$failed = 0;

if ($isCli) {
    echo "Found {$total} pending migration(s).\n\n";
} else {
    echo '<div class="step info">Found ' . $total . ' pending migration(s).</div>';
}

// --- Execute each file ---
foreach ($files as $file) {
    $baseName = basename($file);
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if ($isCli) {
        printf("  %-50s ", $baseName);
    } else {
        echo '<div class="step"><strong>' . htmlspecialchars($baseName) . '</strong> ... ';
    }

    try {
        if ($ext === 'sql') {
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException("Could not read file");
            }

            // Strip comment-only lines, then split by semicolons
            $statements = array_filter(array_map('trim', explode(';', $sql)));

            foreach ($statements as $stmt) {
                // Skip empty or pure-comment statements
                $clean = preg_replace('/--.*$/m', '', $stmt);
                $clean = preg_replace('#/\*.*?\*/#s', '', $clean);
                $clean = trim($clean);
                if ($clean === '') continue;

                $pdo->exec($stmt);
            }
        } elseif ($ext === 'php') {
            // PHP files handle their own DB connection
            require $file;
        }

        // Move to applied/
        rename($file, $appliedDir . '/' . $baseName);
        $applied++;

        if ($isCli) {
            echo "[DONE]\n";
        } else {
            echo '<span class="ok">[DONE]</span></div>';
        }
    } catch (Throwable $e) {
        $failed++;
        $errMsg = $e->getMessage();
        if ($isCli) {
            echo "[ERROR] {$errMsg}\n";
            echo "  Stopping. Fix the error and re-run.\n";
        } else {
            echo '<span class="err">[ERROR] ' . htmlspecialchars($errMsg) . '</span></div>';
            echo '<div class="step err">Stopping. Fix the error and re-run.</div>';
        }
        break;
    }
}

// --- Summary ---
if ($isCli) {
    echo "\nDone. {$applied} applied, {$failed} failed, " . ($total - $applied) . " remaining.\n";
    if ($failed === 0 && $applied > 0) {
        echo "\n[SUCCESS] All migrations applied.\n";
    }
} else {
    $class = $failed > 0 ? 'err' : 'ok';
    echo '<div class="step ' . $class . '" style="margin-top:1rem;font-weight:bold">';
    echo "Done. {$applied} applied, {$failed} failed, " . ($total - $applied) . " remaining.";
    if ($failed === 0 && $applied > 0) {
        echo '<br>[SUCCESS] All migrations applied.';
    }
    echo '</div></div></body></html>';
}

exit($failed > 0 ? 1 : 0);
