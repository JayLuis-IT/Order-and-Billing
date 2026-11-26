<?php
namespace App\Models;

use App\Core\BaseModel;

class FinanceModel extends BaseModel {

    public function getPOsReadyToDeliver() {
        $sql = "SELECT po.*, c.customer_name, c.customer_code, u.full_name as requested_by_name,
                (po.produced_quantity - po.delivered_quantity) as available_for_delivery
                FROM purchase_orders po 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON po.requested_by = u.user_id 
                WHERE po.`remove` = 0 
                AND po.manually_closed = 0
                AND EXISTS (SELECT 1 FROM purchase_order_items oi WHERE oi.po_id = po.po_id AND oi.manually_closed = 0)
                AND po.production_type = 'normal'
                AND po.produced_quantity > po.delivered_quantity
                ORDER BY po.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAllPurchaseOrders() {
        $sql = "SELECT po.*, c.customer_name, c.customer_code, u.full_name as requested_by_name 
                FROM purchase_orders po 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON po.requested_by = u.user_id 
                WHERE po.`remove` = 0
                AND po.manually_closed = 0
                AND EXISTS (SELECT 1 FROM purchase_order_items oi2 WHERE oi2.po_id = po.po_id AND oi2.manually_closed = 0)
                ORDER BY CASE
                        WHEN po.manually_closed = 1
                            OR (SELECT COALESCE(SUM(poiS.delivered_quantity), 0) FROM purchase_order_items poiS WHERE poiS.po_id = po.po_id) >= (SELECT COALESCE(SUM(poiQ.quantity), 0) FROM purchase_order_items poiQ WHERE poiQ.po_id = po.po_id) THEN 2
                        WHEN (SELECT COALESCE(SUM(poiD.delivered_quantity), 0) FROM purchase_order_items poiD WHERE poiD.po_id = po.po_id) > 0 THEN 0
                        ELSE 1
                    END ASC,
                    po.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPurchaseOrderById($id) {
        $sql = "SELECT po.*, c.customer_name, c.customer_code, c.customer_address, c.customer_tin
                FROM purchase_orders po 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                WHERE po.po_id = :id AND po.`remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getPurchaseOrderItems($po_id) {
        $sql = "SELECT poi.*, i.item_code, i.item_description, i.item_uom, i.item_size 
                FROM purchase_order_items poi 
                LEFT JOIN items i ON poi.item_id = i.item_id 
                WHERE poi.po_id = :po_id AND i.`remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['po_id' => $po_id]);
        $rows = $stmt->fetchAll();
        return $rows;
    }

    public function getDeliveriesByPO($po_id) {
        $sql = "SELECT d.*, u.full_name as delivered_by_name
                FROM deliveries d 
                LEFT JOIN users u ON d.delivered_by = u.user_id 
                WHERE d.po_id = :po_id AND d.`remove` = 0
                ORDER BY d.delivery_date DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['po_id' => $po_id]);
        return $stmt->fetchAll();
    }

    public function isSINumberTaken($si_number, $exclude_delivery_id = null) {
        $sql = "SELECT delivery_id FROM deliveries WHERE si_number = :si_number AND `remove` = 0";
        $params = ['si_number' => $si_number];
        if ($exclude_delivery_id) {
            $sql .= " AND delivery_id != :delivery_id";
            $params['delivery_id'] = $exclude_delivery_id;
        }
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ? true : false;
    }

    public function saveSINumber($delivery_id, $si_number) {
        $conn = self::getConnection();
        $stmt = $conn->prepare("UPDATE deliveries SET si_number = :si_number WHERE delivery_id = :delivery_id");
        $stmt->execute(['si_number' => $si_number, 'delivery_id' => $delivery_id]);
        return $stmt->rowCount() > 0;
    }

    public function getAllDeliveries() {
        $sql = "SELECT d.*, po.customer_po_number, po.total_quantity, po.delivered_quantity, po.production_type,
                c.customer_name, u.full_name as delivered_by_name,
                i.item_code, i.item_description
                FROM deliveries d 
                LEFT JOIN purchase_orders po ON d.po_id = po.po_id 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON d.delivered_by = u.user_id 
                LEFT JOIN purchase_order_items poi ON d.poi_id = poi.poi_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                WHERE d.`remove` = 0
                ORDER BY d.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getDeliveryById($id) {
        $sql = "SELECT d.*, po.customer_po_number, po.total_quantity, po.delivered_quantity, po.customer_terms,
                c.customer_name, c.customer_code, c.customer_address, c.customer_tin,
                u.full_name as delivered_by_name,
                poi.quantity as poi_quantity, poi.unit_price, poi.item_id as poi_item_id,
                i.item_code, i.item_description, i.item_uom, i.item_size
                FROM deliveries d 
                LEFT JOIN purchase_orders po ON d.po_id = po.po_id 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON d.delivered_by = u.user_id 
                LEFT JOIN purchase_order_items poi ON d.poi_id = poi.poi_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                WHERE d.delivery_id = :id AND d.remove = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getDeliveryPoiItem($poi_id) {
        $sql = "SELECT poi.*, i.item_code, i.item_description, i.item_uom, i.item_size 
                FROM purchase_order_items poi 
                LEFT JOIN items i ON poi.item_id = i.item_id 
                WHERE poi.poi_id = :poi_id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['poi_id' => $poi_id]);
        return $stmt->fetch();
    }

    public function attachReceipt($data) {
        $type = $data['type'] ?? 'si';
        $sql = "INSERT INTO delivery_receipts (delivery_id, po_id, file_name, file_path, file_type, file_size, type, uploaded_by) 
                VALUES (:delivery_id, :po_id, :file_name, :file_path, :file_type, :file_size, :type, :uploaded_by)";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'delivery_id' => $data['delivery_id'],
            'po_id' => $data['po_id'],
            'file_name' => $data['file_name'],
            'file_path' => $data['file_path'],
            'file_type' => $data['file_type'],
            'file_size' => $data['file_size'],
            'type' => $type,
            'uploaded_by' => $data['uploaded_by']
        ]);
        return self::getConnection()->lastInsertId();
    }

    public function getReceiptsByDelivery($delivery_id) {
        $sql = "SELECT dr.*, u.full_name as uploaded_by_name
                FROM delivery_receipts dr 
                LEFT JOIN users u ON dr.uploaded_by = u.user_id 
                WHERE dr.delivery_id = :delivery_id AND dr.`remove` = 0
                ORDER BY dr.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['delivery_id' => $delivery_id]);
        return $stmt->fetchAll();
    }

    public function getReceiptsByPO($po_id) {
        $sql = "SELECT dr.*, d.delivery_date, u.full_name as uploaded_by_name
                FROM delivery_receipts dr 
                LEFT JOIN deliveries d ON dr.delivery_id = d.delivery_id
                LEFT JOIN users u ON dr.uploaded_by = u.user_id 
                WHERE dr.po_id = :po_id AND dr.`remove` = 0
                ORDER BY dr.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['po_id' => $po_id]);
        return $stmt->fetchAll();
    }

    public function deleteReceipt($receipt_id) {
        $sql = "UPDATE delivery_receipts SET `remove` = 1 WHERE receipt_id = :id";
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute(['id' => $receipt_id]);
    }

    public function getReceiptById($id) {
        $sql = "SELECT * FROM delivery_receipts WHERE receipt_id = :id AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getFinanceStats() {
        $data = [];

        $stmt = self::getConnection()->query("SELECT COUNT(*) as cnt FROM purchase_orders WHERE `remove` = 0");
        $data['total_pos'] = $stmt->fetch()['cnt'];

        $stmt = self::getConnection()->query("SELECT COUNT(*) as cnt FROM purchase_orders po WHERE po.`remove` = 0 AND po.manually_closed = 0 AND EXISTS (SELECT 1 FROM purchase_order_items oi4 WHERE oi4.po_id = po.po_id AND oi4.manually_closed = 0) AND po.production_type = 'normal' AND po.produced_quantity > po.delivered_quantity");
        $data['ready_to_deliver'] = $stmt->fetch()['cnt'];

        $stmt = self::getConnection()->query("SELECT COUNT(*) as cnt FROM deliveries WHERE `remove` = 0");
        $data['total_deliveries'] = $stmt->fetch()['cnt'];

        $stmt = self::getConnection()->query("SELECT COUNT(*) as cnt FROM delivery_receipts WHERE `remove` = 0");
        $data['total_receipts'] = $stmt->fetch()['cnt'];

        $stmt = self::getConnection()->query("SELECT COUNT(*) as cnt FROM deliveries WHERE `remove` = 0 AND si_number IS NOT NULL AND si_number != ''");
        $data['total_invoiced'] = $stmt->fetch()['cnt'];

        $stmt = self::getConnection()->query("SELECT COUNT(*) as cnt FROM deliveries WHERE `remove` = 0 AND (si_number IS NULL OR si_number = '')");
        $data['pending_invoicing'] = $stmt->fetch()['cnt'];

        return $data;
    }

    public function getAllPurchaseOrderItems() {
        $sql = "SELECT poi.*, i.item_code, i.item_description, i.item_uom, i.item_size 
                FROM purchase_order_items poi 
                LEFT JOIN items i ON poi.item_id = i.item_id
                WHERE i.`remove` = 0
                AND poi.manually_closed = 0
                ORDER BY poi.produced_quantity DESC, poi.poi_id ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        $all = $stmt->fetchAll();
        $map = [];
        foreach ($all as $item) {
            $map[$item['po_id']][] = $item;
        }
        return $map;
    }

    public function getAllActiveItems() {
        $sql = "SELECT * FROM items WHERE `remove` = 0 AND status = 1 ORDER BY item_code ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getDeliveriesByPOWithItems($po_id) {
        $sql = "SELECT d.*, u.full_name as delivered_by_name, i.item_description
                FROM deliveries d 
                LEFT JOIN users u ON d.delivered_by = u.user_id 
                LEFT JOIN purchase_order_items poi ON d.poi_id = poi.poi_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                WHERE d.po_id = :po_id AND d.`remove` = 0
                ORDER BY d.delivery_date DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['po_id' => $po_id]);
        return $stmt->fetchAll();
    }

    public function getAllPurchaseOrdersFiltered($filters = []) {
        $sql = "SELECT po.*, c.customer_name, c.customer_code, u.full_name as requested_by_name 
                FROM purchase_orders po 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON po.requested_by = u.user_id 
                WHERE po.`remove` = 0
                AND po.manually_closed = 0
                AND EXISTS (SELECT 1 FROM purchase_order_items oi5 WHERE oi5.po_id = po.po_id AND oi5.manually_closed = 0)";
        $params = [];

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $sql .= " AND (po.customer_po_number LIKE :search1 
                       OR po.po_number LIKE :search2 
                       OR c.customer_name LIKE :search3
                       OR u.full_name LIKE :search4)";
            $params['search1'] = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
            $params['search4'] = $like;
        }
        if (!empty($filters['customer_name'])) {
            $sql .= " AND c.customer_name LIKE :filter_customer";
            $params['filter_customer'] = '%' . $filters['customer_name'] . '%';
        }
        if (!empty($filters['date'])) {
            $sql .= " AND DATE(po.date_created) = :filter_date";
            $params['filter_date'] = $filters['date'];
        }
        if (!empty($filters['item_description'])) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM purchase_order_items poi2
                LEFT JOIN items i2 ON poi2.item_id = i2.item_id
                WHERE poi2.po_id = po.po_id AND i2.item_description LIKE :filter_item
            )";
            $params['filter_item'] = '%' . $filters['item_description'] . '%';
        }

        $sql .= " ORDER BY CASE
                        WHEN po.manually_closed = 1
                            OR (SELECT COALESCE(SUM(poiS2.delivered_quantity), 0) FROM purchase_order_items poiS2 WHERE poiS2.po_id = po.po_id) >= (SELECT COALESCE(SUM(poiQ2.quantity), 0) FROM purchase_order_items poiQ2 WHERE poiQ2.po_id = po.po_id) THEN 2
                        WHEN (SELECT COALESCE(SUM(poiD2.delivered_quantity), 0) FROM purchase_order_items poiD2 WHERE poiD2.po_id = po.po_id) > 0 THEN 0
                        ELSE 1
                    END ASC,
                    po.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getPOsReadyToDeliverFiltered($filters = []) {
        $sql = "SELECT po.*, c.customer_name, c.customer_code, u.full_name as requested_by_name,
                (po.produced_quantity - po.delivered_quantity) as available_for_delivery
                FROM purchase_orders po 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON po.requested_by = u.user_id 
                WHERE po.`remove` = 0 
                AND po.manually_closed = 0
                AND EXISTS (SELECT 1 FROM purchase_order_items oi3 WHERE oi3.po_id = po.po_id AND oi3.manually_closed = 0)
                AND po.production_type = 'normal'
                AND po.produced_quantity > po.delivered_quantity";
        $params = [];

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $sql .= " AND (po.customer_po_number LIKE :search1 
                       OR po.po_number LIKE :search2
                       OR c.customer_name LIKE :search3
                       OR u.full_name LIKE :search4)";
            $params['search1'] = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
            $params['search4'] = $like;
        }

        $sql .= " ORDER BY po.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAllDeliveriesFiltered($filters = []) {
        $sql = "SELECT d.*, po.customer_po_number, po.total_quantity, po.delivered_quantity, po.production_type,
                c.customer_name, u.full_name as delivered_by_name,
                i.item_code, i.item_description
                FROM deliveries d 
                LEFT JOIN purchase_orders po ON d.po_id = po.po_id 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON d.delivered_by = u.user_id 
                LEFT JOIN purchase_order_items poi ON d.poi_id = poi.poi_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                WHERE d.`remove` = 0";
        $params = [];

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $sql .= " AND (d.dr_number LIKE :search1 
                       OR po.customer_po_number LIKE :search2
                       OR c.customer_name LIKE :search3
                       OR i.item_description LIKE :search4
                       OR u.full_name LIKE :search5
                       OR d.si_number LIKE :search6)";
            $params['search1'] = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
            $params['search4'] = $like;
            $params['search5'] = $like;
            $params['search6'] = $like;
        }
        if (!empty($filters['customer_name'])) {
            $sql .= " AND c.customer_name LIKE :filter_customer";
            $params['filter_customer'] = '%' . $filters['customer_name'] . '%';
        }
        if (!empty($filters['item_description'])) {
            $sql .= " AND i.item_description LIKE :filter_item";
            $params['filter_item'] = '%' . $filters['item_description'] . '%';
        }
        if (!empty($filters['si_number'])) {
            $sql .= " AND d.si_number LIKE :filter_si";
            $params['filter_si'] = '%' . $filters['si_number'] . '%';
        }

        $sql .= " ORDER BY d.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
