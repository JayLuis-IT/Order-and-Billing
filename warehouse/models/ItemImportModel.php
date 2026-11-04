<?php
namespace App\Models;

use App\Core\BaseModel;

class ItemImportModel extends BaseModel {
    protected $table = 'items';

    public function getItemByCode($code) {
        $sql = "SELECT * FROM {$this->table} WHERE item_code = :code";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['code' => $code]);
        return $stmt->fetch();
    }

    public function createItem($data) {
        if ($this->getItemByCode($data['item_code'])) {
            throw new \Exception("Item code already exists.");
        }

        $conn = self::getConnection();
        $sql = "INSERT INTO {$this->table}
                (item_code, item_description, item_type, item_uom, status, `remove`)
                VALUES (:item_code, :item_description, :item_type, :item_uom, 1, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'item_code' => $data['item_code'],
            'item_description' => $data['item_description'],
            'item_type' => $data['item_type'],
            'item_uom' => $data['item_uom']
        ]);
        $itemId = $conn->lastInsertId();

        $soh = (float)($data['soh'] ?? 0);
        if ($soh > 0) {
            $this->upsertInventoryBalance($itemId, $data['site_code'] ?? self::DEFAULT_SITE_CODE, $soh, 0);
        }

        return $itemId;
    }

    public function upsertItem($data) {
        $existing = $this->getItemByCode($data['item_code']);
        $conn = self::getConnection();

        if ($existing) {
            $sql = "UPDATE {$this->table} SET
                    item_description = :item_description,
                    item_type = :item_type,
                    raw_c_type = :raw_c_type,
                    item_uom = :item_uom
                    WHERE item_code = :item_code";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'item_code' => $data['item_code'],
                'item_description' => $data['item_description'],
                'item_type' => $data['item_type'],
                'raw_c_type' => $data['raw_c_type'] ?? null,
                'item_uom' => $data['item_uom']
            ]);
            return $existing['item_id'];
        } else {
            $sql = "INSERT INTO {$this->table}
                    (item_code, item_description, item_type, raw_c_type, item_uom, status)
                    VALUES (:item_code, :item_description, :item_type, :raw_c_type, :item_uom, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'item_code' => $data['item_code'],
                'item_description' => $data['item_description'],
                'item_type' => $data['item_type'],
                'raw_c_type' => $data['raw_c_type'] ?? null,
                'item_uom' => $data['item_uom']
            ]);
            return $conn->lastInsertId();
        }
    }

    public function upsertInventoryBalance($itemId, $siteCode, $qtyOnHand, $qtyForInspect) {
        $sql = "INSERT INTO inventory_balances (item_id, site_code, qty_on_hand, qty_allocated, qty_for_inspect)
                VALUES (:item_id, :site_code, :qty_on_hand, 0, :qty_for_inspect)
                ON DUPLICATE KEY UPDATE
                    qty_on_hand = VALUES(qty_on_hand),
                    qty_for_inspect = VALUES(qty_for_inspect)";
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute([
            'item_id' => $itemId,
            'site_code' => $siteCode,
            'qty_on_hand' => $qtyOnHand,
            'qty_for_inspect' => $qtyForInspect
        ]);
    }

    public function getItemById($itemId) {
        $sql = "SELECT item_id, item_code, item_description, item_type, item_uom
                FROM {$this->table}
                WHERE item_id = :item_id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['item_id' => (int)$itemId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getBalances($itemId) {
        $sql = "SELECT site_code, qty_on_hand, qty_allocated, qty_for_inspect
                FROM inventory_balances
                WHERE item_id = :item_id
                ORDER BY site_code ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['item_id' => (int)$itemId]);
        return $stmt->fetchAll();
    }

    public function setSoh($itemId, $siteCode, $qtyOnHand) {
        $sql = "INSERT INTO inventory_balances (item_id, site_code, qty_on_hand)
                VALUES (:item_id, :site_code, :qty_on_hand)
                ON DUPLICATE KEY UPDATE qty_on_hand = VALUES(qty_on_hand)";
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute([
            'item_id' => (int)$itemId,
            'site_code' => $siteCode,
            'qty_on_hand' => (float)$qtyOnHand
        ]);
    }

    /** Apply a signed delta to a site's SOH (Stock In/Out page). */
    public function adjustSohDelta($itemId, $siteCode, $delta) {
        $sql = "INSERT INTO inventory_balances (item_id, site_code, qty_on_hand)
                VALUES (:item_id, :site_code, :delta)
                ON DUPLICATE KEY UPDATE qty_on_hand = qty_on_hand + VALUES(qty_on_hand)";
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute([
            'item_id' => (int)$itemId,
            'site_code' => $siteCode,
            'delta' => (float)$delta
        ]);
    }

    public function getSoh($itemId, $siteCode) {
        $sql = "SELECT qty_on_hand FROM inventory_balances WHERE item_id = :item_id AND site_code = :site_code";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['item_id' => (int)$itemId, 'site_code' => $siteCode]);
        $qty = $stmt->fetchColumn();
        return $qty === false ? 0.0 : (float)$qty;
    }

    public static function mapItemType($cType) {
        $cType = strtoupper(trim($cType));
        if (strpos($cType, 'RM') === 0) return 'RM';
        if (strpos($cType, 'PM') === 0) return 'PM';
        if (strpos($cType, 'FG') === 0) return 'FG';
        if (strpos($cType, 'SFG') === 0 || strpos($cType, 'FB') === 0) return 'SFG';
        return 'SUPPLIES';
    }

    private function stockQuery($filters = []) {
        $sql = "FROM view_inventory_status v
                JOIN items i ON v.item_id = i.item_id
                WHERE i.remove = 0";
        $params = [];

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $sql .= " AND (v.item_code LIKE :search1 OR v.item_description LIKE :search2)";
            $params['search1'] = $like;
            $params['search2'] = $like;
        }
        if (!empty($filters['item_type'])) {
            $sql .= " AND v.item_type = :item_type";
            $params['item_type'] = $filters['item_type'];
        }

        return [$sql, $params];
    }

    public function countWithStock($filters = []) {
        [$from, $params] = $this->stockQuery($filters);
        $stmt = self::getConnection()->prepare("SELECT COUNT(*) AS total {$from}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getAllWithStock($filters = [], $limit = null, $offset = null) {
        [$from, $params] = $this->stockQuery($filters);
        $sql = "SELECT v.*, i.customer_id, i.date_created, i.status, i.remove
                {$from}
                ORDER BY v.item_code ASC";

        if ($limit !== null) {
            $limit = max(1, (int)$limit);
            $offset = max(0, (int)$offset);
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
