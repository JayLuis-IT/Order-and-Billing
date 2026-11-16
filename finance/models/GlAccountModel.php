<?php
namespace App\Models;

use App\Core\BaseModel;
use PDO;

class GlAccountModel extends BaseModel {
    protected $table = 'gl_accounts';

    public function getAll($activeOnly = true) {
        $sql = "SELECT * FROM {$this->table} WHERE `remove` = 0";
        if ($activeOnly) {
            $sql .= " AND status = 1";
        }
        $sql .= " ORDER BY status DESC, gl_account_code ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE gl_account_id = :id AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getByCode($code) {
        $sql = "SELECT * FROM {$this->table} WHERE gl_account_code = :code AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['code' => $code]);
        return $stmt->fetch();
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table}
                (gl_account_code, gl_account_description, report_included, grouping_financial_report)
                VALUES (:gl_account_code, :gl_account_description, :report_included, :grouping_financial_report)";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'gl_account_code'          => $data['gl_account_code'],
            'gl_account_description'   => $data['gl_account_description'],
            'report_included'          => $data['report_included'] ?? null,
            'grouping_financial_report'=> $data['grouping_financial_report'] ?? null
        ]);
        return (int)self::getConnection()->lastInsertId();
    }

    public function update($id, $data) {
        $sql = "UPDATE {$this->table} SET
                gl_account_code = :gl_account_code,
                gl_account_description = :gl_account_description,
                report_included = :report_included,
                grouping_financial_report = :grouping_financial_report
                WHERE gl_account_id = :id AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute([
            'id'                        => $id,
            'gl_account_code'          => $data['gl_account_code'],
            'gl_account_description'   => $data['gl_account_description'],
            'report_included'          => $data['report_included'] ?? null,
            'grouping_financial_report'=> $data['grouping_financial_report'] ?? null
        ]);
    }

    public function toggleStatus($id) {
        $sql = "UPDATE {$this->table} SET status = NOT status WHERE gl_account_id = :id AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function getAllFiltered($filters = []) {
        $sql = "SELECT * FROM {$this->table} WHERE `remove` = 0";
        $params = [];

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $sql .= " AND (gl_account_code LIKE :search1
                       OR gl_account_description LIKE :search2
                       OR report_included LIKE :search3
                       OR grouping_financial_report LIKE :search4)";
            $params['search1'] = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
            $params['search4'] = $like;
        }

        $sql .= " ORDER BY status DESC, gl_account_code ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
