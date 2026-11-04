<?php
namespace App\Models;

use App\Core\BaseModel;

class CatalogModel extends BaseModel {
    protected $table = 'items';

    public function getCustomers() {
        $sql = "SELECT * FROM customers WHERE `remove` = 0 AND status = 1 ORDER BY customer_code ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getCustomerById($customerId) {
        $sql = "SELECT customer_id FROM customers
                WHERE customer_id = :customer_id AND `remove` = 0 AND status = 1";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['customer_id' => $customerId]);
        return $stmt->fetch();
    }

    public function getItems() {
        $sql = "SELECT * FROM items WHERE `remove` = 0 AND status = 1 ORDER BY item_code ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getLmrProductItems() {
        $sql = "SELECT DISTINCT i.*
                FROM items i
                INNER JOIN fg_boms b
                        ON b.fg_item_id = i.item_id
                       AND b.status = 'active'
                WHERE i.`remove` = 0 AND i.status = 1
                  AND i.item_type IN ('FG','SFG')
                ORDER BY i.item_code ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getItemsByCustomer($customer_id) {
        $sql = "SELECT item_id, item_code, item_description, customer_id, item_uom, uom_conversion
                FROM customer_finished_goods
                WHERE `remove` = 0 AND status = 1 AND customer_id = :customer_id
                ORDER BY item_code ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['customer_id' => $customer_id]);
        return $stmt->fetchAll();
    }

    public function getItemByPoiId($poi_id) {
        if (!$poi_id) return null;
        $sql = "SELECT i.item_id, i.item_code, i.item_description, i.item_uom, i.uom_conversion, poi.unit_price
                FROM purchase_order_items poi
                JOIN items i ON poi.item_id = i.item_id
            WHERE poi.poi_id = :poi_id AND i.remove = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['poi_id' => $poi_id]);
        return $stmt->fetch();
    }

    public function getItemById($item_id) {
        if (!$item_id) return null;
        $sql = "SELECT item_id, item_code, item_description, item_uom, uom_conversion 
                FROM items WHERE item_id = :item_id AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['item_id' => $item_id]);
        return $stmt->fetch();
    }

    public function searchItems($query) {
        $query = trim((string) $query);
        // Browse mode (empty query): the Purchasing PO code field shows the
        // first codes on focus so a user who doesn't know the code can pick.
        if ($query === '') {
            $sql = "(SELECT i.item_id, i.item_code, i.item_description, i.item_uom, i.uom_conversion
                    FROM items i
                    WHERE i.`remove` = 0 AND i.status = 1
                    ORDER BY i.item_code ASC
                    LIMIT 50)
                    UNION
                    (SELECT item_id, item_code, item_description, item_uom, uom_conversion
                    FROM customer_finished_goods
                    WHERE `remove` = 0 AND status = 1
                    ORDER BY item_code ASC
                    LIMIT 50)
                    ORDER BY item_code ASC
                    LIMIT 50";
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        }
        $sql = "(SELECT i.item_id, i.item_code, i.item_description, i.item_uom, i.uom_conversion
                FROM items i
                WHERE i.`remove` = 0 AND i.status = 1
                AND (i.item_code LIKE :q1 OR i.item_description LIKE :q2)
                ORDER BY i.item_code ASC
                LIMIT 20)
                UNION
                (SELECT item_id, item_code, item_description, item_uom, uom_conversion
                FROM customer_finished_goods
                WHERE `remove` = 0 AND status = 1
                AND (item_code LIKE :q3 OR item_description LIKE :q4)
                ORDER BY item_code ASC
                LIMIT 20)
                ORDER BY item_code ASC
                LIMIT 20";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['q1' => "%{$query}%", 'q2' => "%{$query}%", 'q3' => "%{$query}%", 'q4' => "%{$query}%"]);
        return $stmt->fetchAll();
    }

    public function searchFgItems($query) {
        $sql = "SELECT MIN(item_id) AS item_id, item_code,
                       MAX(item_description) AS item_description,
                       MAX(item_uom) AS item_uom,
                       MAX(uom_conversion) AS uom_conversion
                FROM items
                WHERE `remove` = 0 AND status = 1 AND item_type = 'FG'
                AND (item_code LIKE :q1 OR item_description LIKE :q2)
                GROUP BY item_code
                ORDER BY item_code ASC
                LIMIT 20";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['q1' => "%{$query}%", 'q2' => "%{$query}%"]);
        return $stmt->fetchAll();
    }
}
