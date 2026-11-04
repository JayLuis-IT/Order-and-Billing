<?php
namespace App\Models;

use App\Core\BaseModel;
use PDO;

class AuditModel extends BaseModel {
    protected $table = 'audit_logs';

    public static function log($userId, $action, $module, $description, $oldValues = null, $newValues = null, $targetType = '', $targetId = null) {
        $conn = self::getConnection();
        $username = 'system';
        $department = 'system';
        if ($userId) {
            $userStmt = $conn->prepare("SELECT username, department FROM users WHERE user_id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $username = $user['username'];
                $department = $user['department'];
            }
        }
        $sql = "INSERT INTO audit_logs (user_id, username, department, action, module, target_type, target_id, description, old_values, new_values)
                VALUES (:user_id, :username, :department, :action, :module, :target_type, :target_id, :description, :old_values, :new_values)";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([
            'user_id'      => $userId,
            'username'     => $username,
            'department'   => $department,
            'action'       => $action,
            'module'       => $module,
            'target_type'  => $targetType,
            'target_id'    => $targetId,
            'description'  => $description,
            'old_values'   => !empty($oldValues) ? json_encode($oldValues) : null,
            'new_values'   => !empty($newValues) ? json_encode($newValues) : null,
        ]);
    }

    public function getLogs($filters = [], $page = 1, $perPage = 50) {
        $where = '1=1';
        $params = [];

        if (!empty($filters['user_id'])) {
            $where .= ' AND user_id = :user_id';
            $params['user_id'] = $filters['user_id'];
        }
        if (!empty($filters['department'])) {
            $where .= ' AND department = :department';
            $params['department'] = $filters['department'];
        }
        if (!empty($filters['module'])) {
            $where .= ' AND module = :module';
            $params['module'] = $filters['module'];
        }
        if (!empty($filters['log_action'])) {
            $where .= ' AND action = :log_action';
            $params['log_action'] = $filters['log_action'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND DATE(created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND DATE(created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $where .= ' AND (description LIKE :search OR username LIKE :search2)';
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search2'] = '%' . $filters['search'] . '%';
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table} WHERE $where";
        $countStmt = self::getConnection()->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$this->table} WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);

        return [
            'items'     => $stmt->fetchAll(),
            'total'     => (int)$total,
            'page'      => $page,
            'perPage'   => $perPage,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }

    /**
     * Recent APPROVE / REJECT decisions recorded in the audit log — the
     * persistent decision history for the admin Approvals hub (MRP runs,
     * PO drafts, over-receipts … every approve-type entry is retained).
     */
    public function getRecentApprovalHistory($department = 'admin', $limit = 25, $excludeTargetType = null) {
        $sql = "SELECT al.action, al.module, al.description, al.username, al.department,
                       al.created_at, al.target_type, al.target_id, al.old_values, al.new_values
                FROM audit_logs al
                WHERE al.department = :department AND al.action IN ('APPROVE', 'REJECT')"
                . ($excludeTargetType !== null ? " AND al.target_type <> '" . str_replace("'", '', $excludeTargetType) . "'" : '') .
                "
                ORDER BY al.created_at DESC
                LIMIT :limit";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->bindValue(':department', $department, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getLogStats($department = null) {
        $conn = self::getConnection();

        if ($department) {
            $todayStmt = $conn->prepare("SELECT COUNT(*) FROM audit_logs WHERE department = :dept AND DATE(created_at) = CURDATE()");
            $todayStmt->execute(['dept' => $department]);
        } else {
            $todayStmt = $conn->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()");
            $todayStmt->execute();
        }
        $todayCount = $todayStmt->fetchColumn();

        return [
            'today_count' => (int)$todayCount,
            'by_department' => [],
        ];
    }

    public function getAllUsers() {
        $sql = "SELECT user_id, username, full_name, department FROM users WHERE status = 1 AND `remove` = 0 ORDER BY username ASC";
        $stmt = self::getConnection()->query($sql);
        return $stmt->fetchAll();
    }

    /** Full user list incl. role + approver flag + login status (admin "Users" screen). */
    public function getAllUsersWithApprover() {
        $sql = "SELECT user_id, username, full_name, department, role, status, is_approver
                FROM users WHERE `remove` = 0 ORDER BY username ASC";
        $stmt = self::getConnection()->query($sql);
        return $stmt->fetchAll();
    }

    /** Set a user's role (warehouse/qc sub-roles). Returns fresh row or null. */
    public function setUserRole($userId, $role) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("SELECT username, department, role FROM users WHERE user_id = :id AND `remove` = 0 FOR UPDATE");
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $conn->rollBack();
                return null;
            }
            $conn->prepare("UPDATE users SET role = :role WHERE user_id = :id")
                ->execute(['role' => $role !== '' ? $role : null, 'id' => $userId]);
            $conn->commit();
            return ['username' => $user['username'], 'department' => $user['department'],
                    'old_role' => $user['role'], 'role' => $role !== '' ? $role : null];
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Toggle users.is_approver (MRP Warehouse Admin approval, step 1).
     * Granting force-rewrites the department to 'warehouse' (the gate wraps
     * both the flag and the department); revoking only clears the flag.
     * Returns the fresh [is_approver, department] pair, or null when unknown.
     */
    public function setApproverFlag($userId, $grant) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("SELECT username, department FROM users WHERE user_id = :id AND `remove` = 0 FOR UPDATE");
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $conn->rollBack();
                return null;
            }
            if ($grant) {
                $conn->prepare("UPDATE users SET is_approver = 1, department = 'warehouse' WHERE user_id = :id")
                    ->execute(['id' => $userId]);
            } else {
                $conn->prepare("UPDATE users SET is_approver = 0 WHERE user_id = :id")
                    ->execute(['id' => $userId]);
            }
            $conn->commit();
            return ['username' => $user['username'], 'department' => $user['department'], 'is_approver' => $grant ? 1 : 0];
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}
