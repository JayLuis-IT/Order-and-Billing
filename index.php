<?php
require_once __DIR__ . '/core/Config.php';
\App\Core\Config::init();
require_once __DIR__ . '/app/helpers/format_helper.php';

spl_autoload_register(function ($class) {
    $paths = [
        'C:\xampp\htdocs\order-billing-system\core' => 'App\Core',
        'C:\xampp\htdocs\order-billing-system\app\helpers' => 'App\Helpers',
        'C:\xampp\htdocs\order-billing-system\admin\controllers' => 'App\Controllers',
        'C:\xampp\htdocs\order-billing-system\admin\models' => 'App\Models',
        'C:\xampp\htdocs\order-billing-system\auth\controllers' => 'App\Controllers',
        'C:\xampp\htdocs\order-billing-system\auth\models' => 'App\Models',
        'C:\xampp\htdocs\order-billing-system\warehouse\controllers' => 'App\Controllers',
        'C:\xampp\htdocs\order-billing-system\warehouse\models' => 'App\Models',
        'C:\xampp\htdocs\order-billing-system\production\controllers' => 'App\Controllers',
        'C:\xampp\htdocs\order-billing-system\production\models' => 'App\Models',
        'C:\xampp\htdocs\order-billing-system\finance\controllers' => 'App\Controllers',
        'C:\xampp\htdocs\order-billing-system\finance\models' => 'App\Models',
        'C:\xampp\htdocs\order-billing-system\qc\controllers' => 'App\Controllers',
        'C:\xampp\htdocs\order-billing-system\qc\models' => 'App\Models',
        'C:\xampp\htdocs\order-billing-system\qa\controllers' => 'App\Controllers',
        'C:\xampp\htdocs\order-billing-system\qa\models' => 'App\Models',
        'C:\xampp\htdocs\order-billing-system\rnd\controllers' => 'App\Controllers',
        'C:\xampp\htdocs\order-billing-system\rnd\models' => 'App\Models',
        'C:\xampp\htdocs\order-billing-system\pr\controllers' => 'App\Controllers',
        'C:\xampp\htdocs\order-billing-system\pr\models' => 'App\Models'
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

$controller = $_GET['controller'] ?? 'auth';
$action = $_GET['action'] ?? 'login';

// Legacy alias: 'mo' routes were renamed to 'lmr' — redirect old links/bookmarks.
if ($controller === 'mo') {
    $qs = $_GET;
    $qs['controller'] = 'lmr';
    header('Location: ?' . http_build_query($qs));
    exit;
}

$authControllers = ['auth'];
$warehouseControllers = ['warehouse', 'lmr'];
$productionControllers = ['production'];
$financeControllers = ['finance'];
$qcControllers = ['qc'];
$qaControllers = ['qa'];
$rndControllers = ['rnd'];
$prControllers = ['pr'];

if (in_array($controller, $authControllers)) {
    $controllerFile = __DIR__ . "/auth/controllers/{$controller}Controller.php";
} elseif (in_array($controller, $warehouseControllers)) {
    $controllerFile = __DIR__ . "/warehouse/controllers/{$controller}Controller.php";
} elseif (in_array($controller, $productionControllers)) {
    $controllerFile = __DIR__ . "/production/controllers/{$controller}Controller.php";
} elseif (in_array($controller, $financeControllers)) {
    $controllerFile = __DIR__ . "/finance/controllers/{$controller}Controller.php";
} elseif (in_array($controller, $qcControllers)) {
    $controllerFile = __DIR__ . "/qc/controllers/{$controller}Controller.php";
} elseif (in_array($controller, $qaControllers)) {
    $controllerFile = __DIR__ . "/qa/controllers/{$controller}Controller.php";
} elseif (in_array($controller, $rndControllers)) {
    $controllerFile = __DIR__ . "/rnd/controllers/{$controller}Controller.php";
} elseif (in_array($controller, $prControllers)) {
    $controllerFile = __DIR__ . "/pr/controllers/{$controller}Controller.php";
} else {
    $controllerFile = __DIR__ . "/admin/controllers/{$controller}Controller.php";
}

if (file_exists($controllerFile)) {
    require_once $controllerFile;
    $className = "App\\Controllers\\" . ucfirst($controller) . "Controller";
    $instance = new $className();
    if (method_exists($instance, $action)) {
        $instance->$action();
    } else {
        $instance->index();
    }
} else {
    echo "<h1>404 - Controller Not Found</h1>";
}