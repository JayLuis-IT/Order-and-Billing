<?php
/**
 * Grant or revoke the Warehouse Admin approver flag (users.is_approver).
 *
 * Usage (CLI, from the project root):
 *   php grant-approver.php <username>          → grant approver flag
 *   php grant-approver.php <username> revoke   → revoke approver flag
 *
 * The flag only grants MRP draft approval rights (step 1 of the dual
 * approval gate). It is never set through signup; run this script from a
 * server terminal instead. Only warehouse users can be approvers — the
 * script rewrites the department to 'warehouse' when granting.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/core/BaseModel.php';

if ($argc < 2 || !preg_match('/^[\w.\- ]+$/', $argv[1])) {
    fwrite(STDERR, "Usage: php grant-approver.php <username> [revoke]\n");
    exit(1);
}

$username = $argv[1];
$revoke = isset($argv[2]) && strtolower($argv[2]) === 'revoke';

try {
    $pdo = \App\Core\BaseModel::getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('SELECT user_id, username, department FROM users WHERE username = ? AND remove = 0 LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        fwrite(STDERR, "Error: user '{$username}' not found.\n");
        exit(1);
    }

    if ($revoke) {
        $pdo->prepare('UPDATE users SET is_approver = 0 WHERE user_id = ?')->execute([$user['user_id']]);
        echo "Approver flag revoked for '{$username}' ({$user['department']}).\n";
    } else {
        $pdo->prepare("UPDATE users SET is_approver = 1, department = 'warehouse' WHERE user_id = ?")
            ->execute([$user['user_id']]);
        echo "Warehouse Admin approver flag granted to '{$username}' (department=warehouse).\n";
        echo "They can now approve MRP draft requisitions (step 1) in the Purchasing PO screen.\n";
    }
} catch (Exception $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
