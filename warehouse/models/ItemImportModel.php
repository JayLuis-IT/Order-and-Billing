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
        $sql = "SELECT item_id, item_code, item_description, item_type, item_uom, supplier_id, item_amount
                FROM {$this->table}
                WHERE item_id = :item_id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['item_id' => (int)$itemId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Metadata-only edit (Description / Type / UOM / Supplier / Price).
     *  item_code is immutable — it is denormalized into PO/receiving/LMR/MRP
     *  tables. item_amount is the item price (PO pricing / price list). */
    public function updateItem($itemId, $data) {
        $sql = "UPDATE {$this->table} SET
                    item_description = :item_description,
                    item_type = :item_type,
                    item_uom = :item_uom,
                    supplier_id = :supplier_id,
                    item_amount = :item_amount
                WHERE item_id = :item_id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'item_id' => (int)$itemId,
            'item_description' => $data['item_description'],
            'item_type' => $data['item_type'],
            'item_uom' => $data['item_uom'],
            'supplier_id' => $data['supplier_id'] ?? null,
            'item_amount' => $data['item_amount'] ?? 0
        ]);
        return $stmt->rowCount() >= 0;
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

    // ─── Item supplier price history ──────────────────────────────────────

    /** All price rows for an item with supplier context, newest first per
     *  supplier. Stats/chart are computed from this in the view. */
    public function getPricesForItem($itemId) {
        $sql = "SELECT p.id, p.item_id, p.supplier_id, p.price, p.effective_date,
                       p.remarks, p.date_created, s.supplier_name, s.status AS supplier_status
                FROM item_supplier_prices p
                JOIN suppliers s ON p.supplier_id = s.supplier_id
                WHERE p.item_id = :item_id AND p.remove = 0 AND s.remove = 0
                ORDER BY s.supplier_name ASC, p.effective_date DESC, p.id DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['item_id' => (int)$itemId]);
        return $stmt->fetchAll();
    }

    public function addPrice($data) {
        $sql = "INSERT INTO item_supplier_prices
                (item_id, supplier_id, price, effective_date, remarks, created_by)
                VALUES (:item_id, :supplier_id, :price, :effective_date, :remarks, :created_by)";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'item_id' => (int)$data['item_id'],
            'supplier_id' => (int)$data['supplier_id'],
            'price' => round((float)$data['price'], 2),
            'effective_date' => $data['effective_date'],
            'remarks' => $data['remarks'] ?? null,
            'created_by' => $data['created_by'] ?? null
        ]);
        return (int)self::getConnection()->lastInsertId();
    }

    /** Soft-delete one price row; returns the affected item_id (or null). */
    public function deletePrice($id) {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT item_id FROM item_supplier_prices WHERE id = :id AND remove = 0");
        $stmt->execute(['id' => (int)$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $del = $conn->prepare("UPDATE item_supplier_prices SET remove = 1 WHERE id = :id");
        $del->execute(['id' => (int)$id]);
        return (int)$row['item_id'];
    }

    /** Remove a supplier from an item: soft-delete all of its price rows.
     *  Returns the affected item_id, or null when nothing matched. */
    public function deletePricesForSupplier($itemId, $supplierId) {
        $conn = self::getConnection();
        $stmt = $conn->prepare(
            "UPDATE item_supplier_prices SET remove = 1
             WHERE item_id = :item_id AND supplier_id = :supplier_id AND remove = 0"
        );
        $stmt->execute([
            'item_id' => (int)$itemId,
            'supplier_id' => (int)$supplierId
        ]);
        return $stmt->rowCount() > 0 ? (int)$itemId : null;
    }

    /** items.item_amount = latest active price row (feeds PO pricing /
     *  price list). When no rows remain the current value is left alone
     *  so deleting history never zeroes out PO pricing. */
    public function syncItemAmount($itemId) {
        $sql = "UPDATE items i
                SET i.item_amount = COALESCE(
                    (SELECT price FROM item_supplier_prices p
                     WHERE p.item_id = i.item_id AND p.remove = 0
                     ORDER BY p.effective_date DESC, p.id DESC
                     LIMIT 1),
                    i.item_amount
                )
                WHERE i.item_id = :item_id";
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute(['item_id' => (int)$itemId]);
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
        // LEFT JOIN (not inner) so items without a supplier still list;
        // suppliers PK join cannot multiply rows, so COUNT(*) stays correct.
        $sql = "FROM view_inventory_status v
                JOIN items i ON v.item_id = i.item_id
                LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
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
        $sql = "SELECT v.*, i.customer_id, i.supplier_id, s.supplier_name, i.item_amount,
                       i.date_created, i.status, i.remove
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
