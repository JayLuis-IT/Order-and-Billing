<?php
namespace App\Models;

use App\Core\BaseModel;
use PDO;

class SupplierModel extends BaseModel {
    protected $table = 'suppliers';

    public function getAll($activeOnly = true) {
        $sql = "SELECT * FROM {$this->table} WHERE `remove` = 0";
        if ($activeOnly) {
            $sql .= " AND status = 1";
        }
        $sql .= " ORDER BY status DESC, supplier_id DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE supplier_id = :id AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getByCode($code) {
        $sql = "SELECT * FROM {$this->table} WHERE supplier_code = :code AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['code' => $code]);
        return $stmt->fetch();
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table} (supplier_code, supplier_name, contact_person, phone, email, address)
                VALUES (:supplier_code, :supplier_name, :contact_person, :phone, :email, :address)";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'supplier_code' => $data['supplier_code'],
            'supplier_name' => $data['supplier_name'],
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null
        ]);
        return self::getConnection()->lastInsertId();
    }

    public function update($id, $data) {
        $sql = "UPDATE {$this->table} SET
                supplier_code = :supplier_code,
                supplier_name = :supplier_name,
                contact_person = :contact_person,
                phone = :phone,
                email = :email,
                address = :address
                WHERE supplier_id = :id AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'supplier_code' => $data['supplier_code'],
            'supplier_name' => $data['supplier_name'],
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null
        ]);
    }

    public function toggleStatus($id) {
        $sql = "UPDATE {$this->table} SET status = NOT status WHERE supplier_id = :id AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function getAllFiltered($filters = [], $activeOnly = false) {
        $sql = "SELECT * FROM {$this->table} WHERE `remove` = 0";
        $params = [];

        if ($activeOnly) {
            $sql .= " AND status = 1";
        }

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $sql .= " AND (supplier_code LIKE :search1
                       OR supplier_name LIKE :search2
                       OR contact_person LIKE :search3
                       OR phone LIKE :search4)";
            $params['search1'] = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
            $params['search4'] = $like;
        }

        $sql .= " ORDER BY status DESC, supplier_id DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Active supplier options for the Purchasing PO supplier dropdown —
     *  shape {id, name} matches the old hardcoded getSuppliers() list. */
    public function getActiveList() {
        $sql = "SELECT supplier_id AS id, supplier_name AS name
                FROM {$this->table}
                WHERE `remove` = 0 AND status = 1
                ORDER BY supplier_name ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
