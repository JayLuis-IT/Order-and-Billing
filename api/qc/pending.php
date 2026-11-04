<?php
require_once __DIR__ . '/../../core/Config.php';
\App\Core\Config::init();

spl_autoload_register(function ($class) {
    $paths = [
        'C:\\xampp\\htdocs\\order-billing-system\\core' => 'App\\Core',
        'C:\\xampp\\htdocs\\order-billing-system\\app\\helpers' => 'App\\Helpers',
        'C:\\xampp\\htdocs\\order-billing-system\\admin\\models' => 'App\\Models',
        'C:\\xampp\\htdocs\\order-billing-system\\warehouse\\models' => 'App\\Models',
        'C:\\xampp\\htdocs\\order-billing-system\\qc\\models' => 'App\\Models'
    ];

    foreach ($paths as $baseDir => $prefix) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});

header('Content-Type: application/json');

// Endpoint is legacy/unused — require a logged-in QC Admin session.
if (empty($_SESSION['user_id']) || ($_SESSION['department'] ?? '') !== 'qc'
    || \App\Helpers\RoleHelper::currentRole() !== 'qc_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

try {
    $model = new \App\Models\QcModel();
    echo json_encode([
        'success' => true,
        'items' => $model->getPendingQcItems()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
