<?php
namespace App\Models;

use App\Core\BaseModel;

class WarehouseModel extends BaseModel {
    protected $table = 'users';

    private $backloadModel;
    private $catalogModel;

    public function __construct() {
        $this->backloadModel = new BackloadModel();
        $this->backloadModel->setWarehouseModel($this);
        $this->catalogModel = new CatalogModel();
    }

    public function getByUsername($username) {
        $sql = "SELECT * FROM users WHERE username = :username AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['username' => $username]);
        return $stmt->fetch();
    }

    // â”€â”€â”€ Catalog (delegated to CatalogModel) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getCustomers() {
        return $this->catalogModel->getCustomers();
    }

    public function getCustomerById($customer_id) {
        return $this->catalogModel->getCustomerById($customer_id);
    }

    public function getItems() {
        return $this->catalogModel->getItems();
    }

    public function getItemsByCustomer($customer_id) {
        return $this->catalogModel->getItemsByCustomer($customer_id);
    }

    public function createPurchaseOrder($data) {
        $sql = "INSERT INTO purchase_orders (customer_po_number, customer_po_date, customer_id, requested_by, customer_terms, production_type, date_created) 
                VALUES (:customer_po_number, :customer_po_date, :customer_id, :requested_by, :customer_terms, :production_type, NOW())";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'customer_po_number' => $data['customer_po_number'],
            'customer_po_date' => $data['customer_po_date'],
            'customer_id' => $data['customer_id'],
            'requested_by' => $data['requested_by'],
            'customer_terms' => $data['customer_terms'] ?? 0,
            'production_type' => $data['production_type'] ?? 'normal'
        ]);
        return self::getConnection()->lastInsertId();
    }

    public function createPurchaseOrderItem($po_id, $item_id, $quantity, $unit_price, $uom = 'PCS', $item_code = null) {
        $sql = "INSERT INTO purchase_order_items (po_id, item_id, quantity, unit_price, item_uom, item_code) 
                VALUES (:po_id, :item_id, :quantity, :unit_price, :item_uom, :item_code)";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'po_id' => $po_id,
            'item_id' => $item_id,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'item_uom' => $uom,
            'item_code' => $item_code
        ]);
        
        $poi_id = self::getConnection()->lastInsertId();
        
        // Update the total quantity for the PO
        $this->updatePOTotalQuantity($po_id);
        
        return $poi_id;
    }
    
    public function updatePOTotalQuantity($po_id) {
        $sql = "UPDATE purchase_orders po 
                SET total_quantity = (
                    SELECT COALESCE(SUM(quantity), 0) 
                    FROM purchase_order_items 
                    WHERE po_id = :po_id
                )
                WHERE po.po_id = :po_id2";
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute(['po_id' => $po_id, 'po_id2' => $po_id]);
    }

    public function updatePurchaseOrderItem($poi_id, $quantity, $item_id = null, $unit_price = null, $item_uom = null, $item_code = null) {
        $fields = ['quantity' => $quantity, 'poi_id' => $poi_id];
        $set = "quantity = :quantity";
        if ($item_id !== null) {
            $set .= ", item_id = :item_id";
            $fields['item_id'] = $item_id;
        }
        if ($unit_price !== null) {
            $set .= ", unit_price = :unit_price";
            $fields['unit_price'] = $unit_price;
        }
        if ($item_uom !== null) {
            $set .= ", item_uom = :item_uom";
            $fields['item_uom'] = $item_uom;
        }
        if ($item_code !== null) {
            $set .= ", item_code = :item_code";
            $fields['item_code'] = $item_code;
        }
        $sql = "UPDATE purchase_order_items SET $set WHERE poi_id = :poi_id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($fields);
        
        $poSql = "SELECT po_id FROM purchase_order_items WHERE poi_id = :poi_id";
        $poStmt = self::getConnection()->prepare($poSql);
        $poStmt->execute(['poi_id' => $poi_id]);
        $row = $poStmt->fetch();
        if ($row) {
            $this->updatePOTotalQuantity($row['po_id']);
        }
        
        return $stmt->rowCount();
    }

    public function getPurchaseOrders() {
        $sql = "SELECT po.*, c.customer_name, u.full_name as requested_by_name 
                FROM purchase_orders po 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON po.requested_by = u.user_id 
                WHERE po.`remove` = 0
                ORDER BY po.last_update DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getActivePOsForDashboard($limit = 5) {
        $sql = "SELECT po.*, c.customer_name, u.full_name as requested_by_name 
                FROM purchase_orders po 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON po.requested_by = u.user_id 
                WHERE po.`remove` = 0 AND po.delivered_quantity < po.total_quantity
                AND po.production_type != 'advance'
                ORDER BY po.last_update DESC
                LIMIT :limit";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPurchaseOrderById($id) {
        $sql = "SELECT po.*, c.customer_name, c.customer_code, c.customer_tin, c.customer_address
                FROM purchase_orders po 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                WHERE po.po_id = :id AND po.`remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getPurchaseOrderItems($po_id) {
        $sql = "SELECT poi.*, i.item_code, i.item_description, COALESCE(poi.item_uom, i.item_uom) as item_uom, i.uom_conversion 
            FROM purchase_order_items poi 
            LEFT JOIN items i ON poi.item_id = i.item_id 
            WHERE poi.po_id = :po_id AND i.remove = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['po_id' => $po_id]);
        $rows = $stmt->fetchAll();
        return $rows;
    }

    public function getPurchaseOrderItemById($poi_id) {
        $sql = "SELECT poi.*, i.item_code, i.item_description, COALESCE(poi.item_uom, i.item_uom) as item_uom, i.uom_conversion,
            po.po_number, po.customer_po_number, c.customer_name
            FROM purchase_order_items poi 
            LEFT JOIN items i ON poi.item_id = i.item_id 
            LEFT JOIN purchase_orders po ON poi.po_id = po.po_id
            LEFT JOIN customers c ON po.customer_id = c.customer_id
            WHERE poi.poi_id = :poi_id AND i.remove = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['poi_id' => $poi_id]);
        return $stmt->fetch();
    }

    public function getPurchaseOrderItemsByPOIds($poIds) {
        if (empty($poIds)) return [];
        $placeholders = implode(',', array_fill(0, count($poIds), '?'));
        $sql = "SELECT poi.*, i.item_code, i.item_description, COALESCE(poi.item_uom, i.item_uom) as item_uom, i.uom_conversion 
                FROM purchase_order_items poi 
                LEFT JOIN items i ON poi.item_id = i.item_id 
                WHERE poi.po_id IN ($placeholders) AND i.remove = 0
                ORDER BY poi.po_id, poi.produced_quantity DESC, poi.poi_id ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($poIds);
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['po_id']][] = $row;
        }
        return $grouped;
    }

    public function createDelivery($data) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
        $lotItems = json_decode($data['lot_items'] ?? '[]', true);
        $actualDeliveryQty = intval($data['delivery_quantity']);

        if (is_array($lotItems) && count($lotItems) > 0) {
            $actualDeliveryQty = 0;
            $perPoi = [];
            foreach ($lotItems as $li) {
                $qty = intval($li['qty'] ?? 0);
                $actualDeliveryQty += $qty;
                $poiId = $li['poi_id'] ?? null;
                if ($poiId) {
                    $perPoi[$poiId] = ($perPoi[$poiId] ?? 0) + $qty;
                }
            }
        } else {
            $perPoi = [];
        }

        $sql = "INSERT INTO deliveries (po_id, poi_id, lot_id, delivered_by, delivery_date, delivery_quantity, dr_number, plate_number, vehicle_type, logistic_provider, lot_items, remarks, is_over_shipment) 
                VALUES (:po_id, :poi_id, :lot_id, :delivered_by, :delivery_date, :delivery_quantity, :dr_number, :plate_number, :vehicle_type, :logistic_provider, :lot_items, :remarks, :is_over_shipment)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'po_id' => $data['po_id'],
            'poi_id' => $data['poi_id'] ?? null,
            'lot_id' => $data['lot_id'] ?? null,
            'delivered_by' => $data['delivered_by'],
            'delivery_date' => $data['delivery_date'],
            'delivery_quantity' => $actualDeliveryQty,
            'dr_number' => $data['dr_number'] ?? null,
            'plate_number' => $data['plate_number'] ?? null,
            'vehicle_type' => $data['vehicle_type'] ?? null,
            'logistic_provider' => $data['logistic_provider'] ?? null,
            'lot_items' => $data['lot_items'] ?? null,
            'remarks' => $data['remarks'] ?? '',
            'is_over_shipment' => $data['is_over_shipment'] ?? 0
        ]);
        $deliveryId = $conn->lastInsertId();

        if (!empty($perPoi)) {
            foreach ($perPoi as $poiId => $qty) {
                $conn->prepare("UPDATE purchase_order_items poi SET delivered_quantity = GREATEST(0,
                    (SELECT COALESCE(SUM(d.delivery_quantity), 0) FROM deliveries d
                    WHERE d.poi_id = poi.poi_id AND d.`remove` = 0)
                    - COALESCE((SELECT SUM(b.quantity) FROM backloads b WHERE b.poi_id = poi.poi_id AND b.`remove` = 0), 0)
                ) WHERE poi.poi_id = :poi_id")
                    ->execute(['poi_id' => $poiId]);
            }
        } elseif (!empty($data['poi_id'])) {
            $conn->prepare("UPDATE purchase_order_items poi SET delivered_quantity = GREATEST(0,
                (SELECT COALESCE(SUM(d.delivery_quantity), 0) FROM deliveries d
                WHERE d.poi_id = poi.poi_id AND d.`remove` = 0)
                - COALESCE((SELECT SUM(b.quantity) FROM backloads b WHERE b.poi_id = poi.poi_id AND b.`remove` = 0), 0)
            ) WHERE poi.poi_id = :poi_id")
                ->execute(['poi_id' => $data['poi_id']]);
        }

        $conn->prepare("UPDATE purchase_orders po SET delivered_quantity = (
            SELECT COALESCE(SUM(delivered_quantity), 0) FROM purchase_order_items WHERE po_id = :po_id
        ) WHERE po.po_id = :po_id2")
            ->execute(['po_id' => $data['po_id'], 'po_id2' => $data['po_id']]);

        if (!empty($data['po_id'])) {
            $this->recalculatePODeliveryStatus($data['po_id']);
        }

        $conn->commit();
        return $deliveryId;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function recalculatePODeliveryStatus($poId) {
        $poId = intval($poId);
        if ($poId <= 0) return false;

        $conn = self::getConnection();
        $itemStmt = $conn->prepare("SELECT poi_id, quantity FROM purchase_order_items WHERE po_id = :po_id");
        $itemStmt->execute(['po_id' => $poId]);
        $poItems = $itemStmt->fetchAll();
        if (empty($poItems)) return false;

        $deliveredByPoi = [];
        $deliveryStmt = $conn->prepare("SELECT poi_id, delivery_quantity, lot_items FROM deliveries
                WHERE po_id = :po_id AND `remove` = 0");
        $deliveryStmt->execute(['po_id' => $poId]);
        while ($delivery = $deliveryStmt->fetch()) {
            $lotItems = json_decode($delivery['lot_items'] ?? '', true);
            if (is_array($lotItems) && !empty($lotItems)) {
                foreach ($lotItems as $lotItem) {
                    $poiId = intval($lotItem['poi_id'] ?? 0);
                    if ($poiId > 0) {
                        $deliveredByPoi[$poiId] = ($deliveredByPoi[$poiId] ?? 0) + intval($lotItem['qty'] ?? 0);
                    }
                }
            } elseif (!empty($delivery['poi_id'])) {
                $poiId = intval($delivery['poi_id']);
                $deliveredByPoi[$poiId] = ($deliveredByPoi[$poiId] ?? 0) + intval($delivery['delivery_quantity'] ?? 0);
            }
        }

        $backloadStmt = $conn->prepare("SELECT poi_id, SUM(quantity) AS quantity FROM backloads
                WHERE po_id = :po_id AND `remove` = 0 GROUP BY poi_id");
        $backloadStmt->execute(['po_id' => $poId]);
        while ($backload = $backloadStmt->fetch()) {
            $poiId = intval($backload['poi_id'] ?? 0);
            if ($poiId > 0) {
                $deliveredByPoi[$poiId] = max(0, ($deliveredByPoi[$poiId] ?? 0) - intval($backload['quantity'] ?? 0));
            }
        }

        $totalDelivered = 0;
        $allItemsComplete = true;
        foreach ($poItems as $poItem) {
            $poiId = intval($poItem['poi_id']);
            $delivered = max(0, intval($deliveredByPoi[$poiId] ?? 0));
            $ordered = intval($poItem['quantity'] ?? 0);
            $totalDelivered += $delivered;
            if ($delivered < $ordered) $allItemsComplete = false;
            $conn->prepare("UPDATE purchase_order_items SET delivered_quantity = :delivered WHERE poi_id = :poi_id")
                ->execute(['delivered' => $delivered, 'poi_id' => $poiId]);
        }

        $conn->prepare("UPDATE purchase_orders SET delivered_quantity = :delivered WHERE po_id = :po_id")
            ->execute(['delivered' => $totalDelivered, 'po_id' => $poId]);

        if ($allItemsComplete) {
            $conn->prepare("UPDATE purchase_orders SET status = 'delivered', completed_at = COALESCE(completed_at, NOW()) WHERE po_id = :po_id")
                ->execute(['po_id' => $poId]);
        } else {
            $conn->prepare("UPDATE purchase_orders SET status = CASE WHEN :delivered > 0 THEN 'accepted' ELSE 'pending' END,
                    completed_at = NULL WHERE po_id = :po_id")
                ->execute(['delivered' => $totalDelivered, 'po_id' => $poId]);
        }
        return true;
    }

    public function getDeliveryById($delivery_id) {
        $sql = "SELECT d.*, po.customer_po_number, po.total_quantity, po.delivered_quantity, 
                       po.customer_terms, po.customer_id,
                       c.customer_name, c.customer_code, c.customer_address, c.customer_tin,
                       u.full_name as delivered_by_name,
                       i.item_code as delivery_item_code, i.item_description as delivery_item_description,
                       COALESCE(poi.item_uom, i.item_uom) as item_uom, i.uom_conversion,
                       l.lot_number
                FROM deliveries d 
                LEFT JOIN purchase_orders po ON d.po_id = po.po_id 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON d.delivered_by = u.user_id
                LEFT JOIN purchase_order_items poi ON d.poi_id = poi.poi_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                LEFT JOIN production_lots l ON d.lot_id = l.lot_id
                WHERE d.delivery_id = :delivery_id AND d.`remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['delivery_id' => $delivery_id]);
        return $stmt->fetch();
    }

    public function getDeliveries() {
        $sql = "SELECT d.*, po.customer_po_number, po.customer_po_date, po.total_quantity, po.delivered_quantity, po.production_type, c.customer_name,
                   poi.quantity as item_quantity, i.item_code, i.item_description, COALESCE(poi.item_uom, i.item_uom) as item_uom, i.uom_conversion, pl.lot_number,
                   u.full_name as delivered_by_name
            FROM deliveries d 
            LEFT JOIN purchase_orders po ON d.po_id = po.po_id 
            LEFT JOIN customers c ON po.customer_id = c.customer_id 
            LEFT JOIN purchase_order_items poi ON d.poi_id = poi.poi_id
            LEFT JOIN items i ON poi.item_id = i.item_id
            LEFT JOIN production_lots pl ON d.lot_id = pl.lot_id
            LEFT JOIN users u ON d.delivered_by = u.user_id
            WHERE d.`remove` = 0
            ORDER BY d.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function deleteDelivery($deliveryId) {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT delivery_id, po_id, poi_id, lot_items FROM deliveries WHERE delivery_id = :delivery_id AND `remove` = 0");
        $stmt->execute(['delivery_id' => $deliveryId]);
        $delivery = $stmt->fetch();
        if (!$delivery) return false;

        $conn->beginTransaction();
        try {
            $conn->prepare("UPDATE deliveries SET `remove` = 1 WHERE delivery_id = :delivery_id")
                ->execute(['delivery_id' => $deliveryId]);

            $poiIds = [];
            if (!empty($delivery['poi_id'])) {
                $poiIds[] = $delivery['poi_id'];
            }
            $lotItems = json_decode($delivery['lot_items'] ?? '[]', true);
            if (is_array($lotItems)) {
                foreach ($lotItems as $li) {
                    if (!empty($li['poi_id']) && !in_array($li['poi_id'], $poiIds)) {
                        $poiIds[] = $li['poi_id'];
                    }
                }
            }

            foreach ($poiIds as $poiId) {
                $conn->prepare("UPDATE purchase_order_items poi SET delivered_quantity = GREATEST(0,
                    (SELECT COALESCE(SUM(d.delivery_quantity), 0) FROM deliveries d
                    WHERE d.poi_id = poi.poi_id AND d.`remove` = 0)
                    - COALESCE((SELECT SUM(b.quantity) FROM backloads b WHERE b.poi_id = poi.poi_id AND b.`remove` = 0), 0)
                ) WHERE poi.poi_id = :poi_id")
                    ->execute(['poi_id' => $poiId]);
            }

            if (!empty($delivery['po_id'])) {
                $conn->prepare("UPDATE purchase_orders po SET delivered_quantity = (
                    SELECT COALESCE(SUM(delivered_quantity), 0) FROM purchase_order_items WHERE po_id = :po_id
                ) WHERE po.po_id = :po_id2")
                    ->execute(['po_id' => $delivery['po_id'], 'po_id2' => $delivery['po_id']]);
                $this->recalculatePODeliveryStatus($delivery['po_id']);
            }

            $conn->commit();
            return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function deleteProductionHistory($historyId) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("SELECT history_id, po_id, poi_id, item_id, lot_number,
                                           added_quantity, sts_ref, pcs_per_case
                                    FROM production_history
                                    WHERE history_id = :history_id FOR UPDATE");
            $stmt->execute(['history_id' => $historyId]);
            $history = $stmt->fetch();
            if (!$history) {
                $conn->rollBack();
                return false;
            }

            if (!empty($history['item_id']) && $history['lot_number'] !== null && $history['lot_number'] !== '') {
                $lotStmt = $conn->prepare("SELECT lot_id, quantity_produced, pcs_per_case
                                            FROM production_lots
                                            WHERE item_id = :item_id AND lot_number = :lot_number AND `is_removed` = 0
                                            FOR UPDATE");
                $lotStmt->execute([
                    'item_id' => $history['item_id'],
                    'lot_number' => $history['lot_number'],
                ]);
                $lots = $lotStmt->fetchAll();

                $toRemove = max(0, intval($history['added_quantity']));
                $poolTotal = 0;
                foreach ($lots as $l) {
                    $poolTotal += max(0, intval($l['quantity_produced']));
                }
                if ($toRemove > 0 && $poolTotal < $toRemove) {
                    throw new \RuntimeException('Lot "' . $history['lot_number'] . '" pool quantity (' . $poolTotal
                        . ') is smaller than this entry (' . $toRemove . '). Reconcile lot quantities before deleting.');
                }

                // Absorb the deduction across the lot's rows: prefer the row sharing this
                // entry's pcs_per_case, then the largest row. Never lets any row go negative
                // and never deducts more than once per group (item_id + lot_number).
                $pcs = $history['pcs_per_case'] !== null ? intval($history['pcs_per_case']) : null;
                usort($lots, function ($a, $b) use ($pcs) {
                    $aMatch = ($pcs !== null && $a['pcs_per_case'] !== null && intval($a['pcs_per_case']) === $pcs) ? 1 : 0;
                    $bMatch = ($pcs !== null && $b['pcs_per_case'] !== null && intval($b['pcs_per_case']) === $pcs) ? 1 : 0;
                    if ($aMatch !== $bMatch) return $bMatch - $aMatch;
                    return max(0, intval($b['quantity_produced'])) - max(0, intval($a['quantity_produced']));
                });

                $remaining = $toRemove;
                $updStmt = $conn->prepare("UPDATE production_lots SET quantity_produced = quantity_produced - :take
                                            WHERE lot_id = :lot_id");
                foreach ($lots as $l) {
                    if ($remaining <= 0) break;
                    $take = min(max(0, intval($l['quantity_produced'])), $remaining);
                    if ($take > 0) {
                        $updStmt->execute(['take' => $take, 'lot_id' => $l['lot_id']]);
                        $remaining -= $take;
                    }
                }

                // Remove lot rows that are now empty (Lot Tracker / FG inventory stop showing them)
                $emptyStmt = $conn->prepare("SELECT lot_id FROM production_lots
                                             WHERE item_id = :item_id AND lot_number = :lot_number
                                               AND quantity_produced <= 0 AND `is_removed` = 0");
                $emptyStmt->execute([
                    'item_id' => $history['item_id'],
                    'lot_number' => $history['lot_number'],
                ]);
                foreach ($emptyStmt->fetchAll() as $empty) {
                    $this->removeLotRow(intval($empty['lot_id']), $conn);
                }
            }

            if (!empty($history['poi_id'])) {
                $this->recalculateProducedQuantity($history['poi_id'], $conn);
            }

            $conn->prepare("DELETE FROM production_reports WHERE history_id = :history_id")
                ->execute(['history_id' => $historyId]);

            $conn->prepare("DELETE FROM production_history WHERE history_id = :history_id")
                ->execute(['history_id' => $historyId]);

            $conn->commit();
            return $history;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Remove an emptied production_lots row. Hard-deletes the row when nothing
     * references it; otherwise falls back to a soft delete so the RESTRICT foreign
     * keys on deliveries/backloads and the deliveries.lot_items JSON stay intact.
     * In both cases the lot disappears from Lot Tracker / FG inventory views
     * (they only read is_removed = 0 rows).
     */
    private function removeLotRow($lotId, $conn) {
        $refStmt = $conn->prepare("SELECT (
                                        EXISTS(SELECT 1 FROM deliveries WHERE lot_id = :l1)
                                      + EXISTS(SELECT 1 FROM backloads WHERE lot_id = :l2)
                                      + EXISTS(SELECT 1 FROM delivery_reports WHERE lot_id = :l3)
                                      + EXISTS(SELECT 1 FROM deliveries WHERE lot_items LIKE :l4)
                                    ) AS ref_count");
        $pattern = '%' . '"lot_id":' . $lotId . '%';
        $refStmt->execute([
            'l1' => $lotId,
            'l2' => $lotId,
            'l3' => $lotId,
            'l4' => $pattern,
        ]);
        $refCount = intval($refStmt->fetchColumn());

        if ($refCount > 0) {
            $conn->prepare("UPDATE production_lots SET `is_removed` = 1 WHERE lot_id = :lot_id")
                ->execute(['lot_id' => $lotId]);
        } else {
            $conn->prepare("DELETE FROM production_lots WHERE lot_id = :lot_id")
                ->execute(['lot_id' => $lotId]);
        }
    }

    public function getNextPONumber() {
        $sql = "SELECT MAX(CAST(SUBSTRING(customer_po_number, 4) AS UNSIGNED)) as max_num FROM purchase_orders WHERE `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        $next = ($result['max_num'] ?? 0) + 1;
        return 'PO-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Supplier options behind the Purchasing PO modals' supplier dropdown.
     * TEMPORARY static list: when the suppliers table ships, replace the body
     * with e.g. SELECT supplier_id AS id, supplier_name AS name
     *          FROM suppliers WHERE `remove` = 0 ORDER BY supplier_name
     * and nothing else changes â€” both the purchasingPo view (server-rendered
     * options) and WarehouseController::getSuppliers() (JSON hook) read this.
     */
    public function getSuppliers() {
        return [
            ['id' => 1, 'name' => 'Supplier 1'],
            ['id' => 2, 'name' => 'Supplier 2'],
            ['id' => 3, 'name' => 'Supplier 3'],
            ['id' => 4, 'name' => 'Supplier 4'],
            ['id' => 5, 'name' => 'Supplier 5'],
        ];
    }

    public function updateProducedQuantity($po_id, $added_quantity, $user_id) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
        $stmt = $conn->prepare("SELECT produced_quantity FROM purchase_orders WHERE po_id = :po_id FOR UPDATE");
        $stmt->execute(['po_id' => $po_id]);
        $po = $stmt->fetch();
        $previous_quantity = $po['produced_quantity'] ?? 0;
        $new_quantity = $previous_quantity + $added_quantity;
        
        $conn->prepare("UPDATE purchase_orders SET produced_quantity = :produced_quantity WHERE po_id = :po_id")
            ->execute(['produced_quantity' => $new_quantity, 'po_id' => $po_id]);
        
        $conn->prepare("INSERT INTO production_history (po_id, user_id, previous_quantity, added_quantity, new_quantity, date_created) VALUES (:po_id, :user_id, :previous_quantity, :added_quantity, :new_quantity, NOW())")
            ->execute([
                'po_id' => $po_id,
                'user_id' => $user_id,
                'previous_quantity' => $previous_quantity,
                'added_quantity' => $added_quantity,
                'new_quantity' => $new_quantity
            ]);
        
        $conn->commit();
        return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function recalculateProducedQuantity($poiId, $conn = null) {
        return $this->recalculateProducedQuantityFromDelivery($poiId, $conn);
    }

    public function updateItemProducedQuantity($poi_id, $added_quantity, $user_id = null, $lot_number = null, $item_description = null, $sts_ref = null, $extraStsData = []) {
        $conn = self::getConnection();

        $stmt = $conn->prepare("SELECT produced_quantity, po_id FROM purchase_order_items WHERE poi_id = :poi_id");
        $stmt->execute(['poi_id' => $poi_id]);
        $item = $stmt->fetch();
        $previous_quantity = $item['produced_quantity'] ?? 0;
        $new_quantity = $previous_quantity + $added_quantity;

        $this->recalculateProducedQuantity($poi_id, $conn);

        if ($item) {
            $stmt2 = $conn->prepare("SELECT produced_quantity FROM purchase_order_items WHERE poi_id = :poi_id");
            $stmt2->execute(['poi_id' => $poi_id]);
            $new_quantity = intval($stmt2->fetchColumn());

            if ($user_id) {
                $shift = $extraStsData['shift'] ?? null;
                $rejectStatus = $extraStsData['reject_status'] ?? null;
                $stsRemarks = $extraStsData['sts_remarks'] ?? null;
                $pcsPerCase = $extraStsData['pcs_per_case'] ?? null;
                $preparedByName = $extraStsData['prepared_by_name'] ?? null;
                $checkedByName = $extraStsData['checked_by_name'] ?? null;
                $receivedByName = $extraStsData['received_by_name'] ?? null;

                $conn->prepare("INSERT INTO production_history (po_id, poi_id, lot_number, item_description, sts_ref, shift, reject_status, sts_remarks, pcs_per_case, prepared_by_name, checked_by_name, received_by_name, user_id, previous_quantity, added_quantity, new_quantity, date_created) 
                    VALUES (:po_id, :poi_id, :lot_number, :item_description, :sts_ref, :shift, :reject_status, :sts_remarks, :pcs_per_case, :prepared_by_name, :checked_by_name, :received_by_name, :user_id, :previous_quantity, :added_quantity, :new_quantity, NOW())")
                    ->execute([
                        'po_id' => $item['po_id'],
                        'poi_id' => $poi_id,
                        'lot_number' => $lot_number,
                        'item_description' => $item_description,
                        'sts_ref' => $sts_ref,
                        'shift' => $shift,
                        'reject_status' => $rejectStatus,
                        'sts_remarks' => $stsRemarks,
                        'pcs_per_case' => $pcsPerCase,
                        'prepared_by_name' => $preparedByName,
                        'checked_by_name' => $checkedByName,
                        'received_by_name' => $receivedByName,
                        'user_id' => $user_id,
                        'previous_quantity' => $previous_quantity,
                        'added_quantity' => $added_quantity,
                        'new_quantity' => $new_quantity
                    ]);
            }
        }
        return true;
    }

    public function getProductionHistory() {
        $sql = "SELECT ph.*, po.customer_po_number, po.production_type, c.customer_name, u.full_name,
                UNIX_TIMESTAMP(ph.date_created) + 300 as undo_deadline,
                (ph.date_created >= NOW() - INTERVAL 5 MINUTE) as undo_available,
                    eu.full_name as edited_by_name, ph.date_edited,
                    pr.report_id, pr.status as report_status, pr.reason as report_reason,
                    pr.report_type as report_type, pr.new_lot_number as resolved_lot,
                    poi.quantity as ordered_quantity, poi.produced_quantity as poi_produced_quantity,
                    ph.qc_remark, ph.qc_inspected_by, ph.qc_inspected_at,
                    ph.qc_inspector_name,
                    ph.qa_remark, ph.qa_inspected_by, ph.qa_inspected_at,
                    ph.qa_inspector_name,
                    COALESCE(i.item_code, ifa.item_code) as item_code
                FROM production_history ph 
                LEFT JOIN purchase_orders po ON ph.po_id = po.po_id 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON ph.user_id = u.user_id
                LEFT JOIN users eu ON ph.edited_by = eu.user_id
                LEFT JOIN production_reports pr ON ph.history_id = pr.history_id AND pr.status = 'pending'
                LEFT JOIN purchase_order_items poi ON ph.poi_id = poi.poi_id
                LEFT JOIN items i ON ph.item_id = i.item_id
                LEFT JOIN items ifa ON ph.item_description = ifa.item_description AND ph.item_id IS NULL
                WHERE ph.is_removed = 0
                ORDER BY ph.date_created ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $transferredLots = self::getConnection()->query("
            SELECT pl.item_id, pl.po_id as target_po_id, pl.poi_id, pl.lot_number, i.item_description, SUM(pl.quantity_produced) as transfer_qty, po.customer_po_number as source_po_number
            FROM production_lots pl
            LEFT JOIN purchase_order_items poi ON pl.poi_id = poi.poi_id
            LEFT JOIN items i ON poi.item_id = i.item_id
            LEFT JOIN purchase_orders po ON pl.transferred_from_po_id = po.po_id
            WHERE pl.transferred_from_po_id IS NOT NULL AND pl.is_removed = 0
            GROUP BY pl.item_id, pl.po_id, pl.poi_id, pl.lot_number, i.item_description, po.customer_po_number
        ")->fetchAll(\PDO::FETCH_ASSOC);
        $transferredMap = [];
        foreach ($transferredLots as $tl) {
            $key = ($tl['item_id'] ?? 0) . '|' . $tl['lot_number'];
            $transferredMap[$key] = ['qty' => (int)$tl['transfer_qty'], 'source' => $tl['source_po_number'] ?? ''];
        }

        $lotItemTotals = [];
        foreach ($rows as &$row) {
            $lot = $row['lot_number'] ?? '';
            $lotItemKey = ($row['item_id'] ?? 0) . '|' . $lot;

            if (!isset($lotItemTotals[$lotItemKey])) {
                if (isset($transferredMap[$lotItemKey]) && !empty($transferredMap[$lotItemKey]['qty'])) {
                    $lotItemTotals[$lotItemKey] = $transferredMap[$lotItemKey]['qty'];
                    $row['transfer_source_po'] = $transferredMap[$lotItemKey]['source'];
                    $row['transfer_qty'] = $transferredMap[$lotItemKey]['qty'];
                } else {
                    $lotItemTotals[$lotItemKey] = 0;
                }
            }
            $row['computed_prev_lot_qty'] = $lotItemTotals[$lotItemKey];
            $lotItemTotals[$lotItemKey] += $row['added_quantity'];
            $row['computed_new_lot_qty'] = $lotItemTotals[$lotItemKey];
        }
        unset($row);

        usort($rows, function ($first, $second) {
            $dateComparison = strcmp($second['date_created'] ?? '', $first['date_created'] ?? '');
            return $dateComparison !== 0
                ? $dateComparison
                : ((int)($second['history_id'] ?? 0) <=> (int)($first['history_id'] ?? 0));
        });
        return array_values($rows);
    }

    public function getProductionHistoryById($historyId) {
        $stmt = self::getConnection()->prepare("SELECT history_id, po_id, poi_id, lot_number, added_quantity, previous_quantity, new_quantity, old_added_quantity, old_lot_number FROM production_history WHERE history_id = :history_id");
        $stmt->execute(['history_id' => $historyId]);
        return $stmt->fetch();
    }

    public function getNormalProductionPOs() {
        $sql = "SELECT po.*, c.customer_name, u.full_name as requested_by_name 
                FROM purchase_orders po 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON po.requested_by = u.user_id 
                WHERE po.production_type = 'normal' AND po.`remove` = 0
                ORDER BY po.last_update DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPurchaseOrdersFiltered($filters = []) {
        $sql = "SELECT po.*, c.customer_name, u.full_name as requested_by_name 
                FROM purchase_orders po 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON po.requested_by = u.user_id 
                WHERE po.`remove` = 0";
        $params = [];

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $sql .= " AND (po.customer_po_number LIKE :search1 
                       OR po.po_number LIKE :search2 
                       OR c.customer_name LIKE :search3
                       OR u.full_name LIKE :search4
                       OR po.production_type LIKE :search5)";
            $params['search1'] = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
            $params['search4'] = $like;
            $params['search5'] = $like;
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
        if (!empty($filters['production_type'])) {
            $sql .= " AND po.production_type = :filter_prod_type";
            $params['filter_prod_type'] = $filters['production_type'];
        }
        if (!empty($filters['delivery_status'])) {
            if ($filters['delivery_status'] === 'open') {
                $sql .= " AND po.delivered_quantity < po.total_quantity";
            } elseif ($filters['delivery_status'] === 'closed') {
                $sql .= " AND po.delivered_quantity >= po.total_quantity";
            }
        }

        $sql .= " ORDER BY po.last_update DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getDeliveriesFiltered($filters = []) {
        $sql = "SELECT d.*, po.customer_po_number, po.customer_po_date, po.total_quantity, po.delivered_quantity, po.production_type, c.customer_name,
                   poi.quantity as item_quantity, i.item_code, i.item_description, COALESCE(poi.item_uom, i.item_uom) as item_uom, i.uom_conversion, pl.lot_number,
                   u.full_name as delivered_by_name
            FROM deliveries d 
            LEFT JOIN purchase_orders po ON d.po_id = po.po_id 
            LEFT JOIN customers c ON po.customer_id = c.customer_id 
            LEFT JOIN purchase_order_items poi ON d.poi_id = poi.poi_id
            LEFT JOIN items i ON poi.item_id = i.item_id
            LEFT JOIN production_lots pl ON d.lot_id = pl.lot_id
            LEFT JOIN users u ON d.delivered_by = u.user_id
            WHERE d.`remove` = 0";
        $params = [];

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $sql .= " AND (d.dr_number LIKE :search1 
                       OR po.customer_po_number LIKE :search2
                       OR c.customer_name LIKE :search3
                       OR i.item_description LIKE :search4
                       OR pl.lot_number LIKE :search5
                       OR u.full_name LIKE :search6
                       OR d.remarks LIKE :search7)";
            $params['search1'] = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
            $params['search4'] = $like;
            $params['search5'] = $like;
            $params['search6'] = $like;
            $params['search7'] = $like;
        }
        if (!empty($filters['customer_name'])) {
            $sql .= " AND c.customer_name LIKE :filter_customer";
            $params['filter_customer'] = '%' . $filters['customer_name'] . '%';
        }
        if (!empty($filters['item_description'])) {
            $sql .= " AND i.item_description LIKE :filter_item";
            $params['filter_item'] = '%' . $filters['item_description'] . '%';
        }
        if (!empty($filters['dr_number'])) {
            $sql .= " AND d.dr_number LIKE :filter_dr";
            $params['filter_dr'] = '%' . $filters['dr_number'] . '%';
        }
        if (!empty($filters['delivery_date'])) {
            $sql .= " AND DATE(d.delivery_date) = :filter_date";
            $params['filter_date'] = $filters['delivery_date'];
        }
        if (!empty($filters['po_number'])) {
            $sql .= " AND po.customer_po_number LIKE :filter_po";
            $params['filter_po'] = '%' . $filters['po_number'] . '%';
        }
        if (!empty($filters['delivered_by'])) {
            $sql .= " AND u.full_name LIKE :filter_delivered_by";
            $params['filter_delivered_by'] = '%' . $filters['delivered_by'] . '%';
        }
        if (!empty($filters['production_type'])) {
            $sql .= " AND po.production_type = :filter_prod_type";
            $params['filter_prod_type'] = $filters['production_type'];
        }
        if (!empty($filters['has_reports'])) {
            $sql .= " AND d.remarks_type = 'report'";
        }

        $sql .= " ORDER BY d.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getProductionHistoryFiltered($filters = []) {
        $sql = "SELECT ph.*, po.customer_po_number, po.production_type, c.customer_name, u.full_name,
                UNIX_TIMESTAMP(ph.date_created) + 300 as undo_deadline,
                (ph.date_created >= NOW() - INTERVAL 5 MINUTE) as undo_available,
                    eu.full_name as edited_by_name, ph.date_edited,
                    pr.report_id, pr.status as report_status, pr.reason as report_reason,
                    pr.report_type as report_type, pr.new_lot_number as resolved_lot,
                    poi.quantity as ordered_quantity, poi.produced_quantity as poi_produced_quantity,
                    ph.qc_remark, ph.qc_inspected_by, ph.qc_inspected_at,
                    ph.qc_inspector_name,
                    ph.qa_remark, ph.qa_inspected_by, ph.qa_inspected_at,
                    ph.qa_inspector_name,
                    COALESCE(i.item_code, ifa.item_code) as item_code
                FROM production_history ph 
                LEFT JOIN purchase_orders po ON ph.po_id = po.po_id 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON ph.user_id = u.user_id
                LEFT JOIN users eu ON ph.edited_by = eu.user_id
                LEFT JOIN production_reports pr ON ph.history_id = pr.history_id AND pr.status = 'pending'
                LEFT JOIN purchase_order_items poi ON ph.poi_id = poi.poi_id
                LEFT JOIN items i ON ph.item_id = i.item_id
                LEFT JOIN items ifa ON ph.item_description = ifa.item_description AND ph.item_id IS NULL
                WHERE ph.is_removed = 0";
        $params = [];

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $sql .= " AND (ph.lot_number LIKE :search1 
                       OR ph.item_description LIKE :search2
                       OR po.customer_po_number LIKE :search3
                       OR c.customer_name LIKE :search4
                       OR ph.sts_ref LIKE :search5
                       OR u.full_name LIKE :search6
                       OR pr.reason LIKE :search7)";
            $params['search1'] = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
            $params['search4'] = $like;
            $params['search5'] = $like;
            $params['search6'] = $like;
            $params['search7'] = $like;
        }
        if (!empty($filters['customer_name'])) {
            $sql .= " AND c.customer_name LIKE :filter_customer";
            $params['filter_customer'] = '%' . $filters['customer_name'] . '%';
        }
        if (!empty($filters['item_description'])) {
            $sql .= " AND ph.item_description LIKE :filter_item";
            $params['filter_item'] = '%' . $filters['item_description'] . '%';
        }
        if (!empty($filters['lot_number'])) {
            $sql .= " AND ph.lot_number = :filter_lot";
            $params['filter_lot'] = $filters['lot_number'];
        }
        if (!empty($filters['po_number'])) {
            $sql .= " AND po.customer_po_number LIKE :filter_po";
            $params['filter_po'] = '%' . $filters['po_number'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(ph.date_created) >= :filter_date_from";
            $params['filter_date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(ph.date_created) <= :filter_date_to";
            $params['filter_date_to'] = $filters['date_to'];
        }
        if (!empty($filters['has_reports'])) {
            $sql .= " AND pr.status = 'pending'";
        }

        $sql .= " ORDER BY ph.date_created ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $transferredSql = "SELECT pl.item_id, pl.po_id as target_po_id, pl.poi_id, pl.lot_number, i.item_description, SUM(pl.quantity_produced) as transfer_qty, po.customer_po_number as source_po_number
            FROM production_lots pl
            LEFT JOIN purchase_order_items poi ON pl.poi_id = poi.poi_id
            LEFT JOIN items i ON poi.item_id = i.item_id
            LEFT JOIN purchase_orders po ON pl.transferred_from_po_id = po.po_id
            WHERE pl.transferred_from_po_id IS NOT NULL AND pl.is_removed = 0";
        $transferredParams = [];
        if (!empty($filters['lot_number'])) {
            $transferredSql .= " AND pl.lot_number = :filter_lot";
            $transferredParams['filter_lot'] = $filters['lot_number'];
        }
        $transferredSql .= " GROUP BY pl.item_id, pl.po_id, pl.poi_id, pl.lot_number, i.item_description, po.customer_po_number";
        $transferredStmt = self::getConnection()->prepare($transferredSql);
        $transferredStmt->execute($transferredParams);
        $transferredLots = $transferredStmt->fetchAll(\PDO::FETCH_ASSOC);
        $transferredMap = [];
        foreach ($transferredLots as $tl) {
            $key = ($tl['item_id'] ?? 0) . '|' . $tl['lot_number'];
            $transferredMap[$key] = ['qty' => (int)$tl['transfer_qty'], 'source' => $tl['source_po_number'] ?? ''];
        }

        $lotItemTotals = [];
        foreach ($rows as &$row) {
            $lot = $row['lot_number'] ?? '';
            $lotItemKey = ($row['item_id'] ?? 0) . '|' . $lot;

            if (!isset($lotItemTotals[$lotItemKey])) {
                if (isset($transferredMap[$lotItemKey]) && !empty($transferredMap[$lotItemKey]['qty'])) {
                    $lotItemTotals[$lotItemKey] = $transferredMap[$lotItemKey]['qty'];
                    $row['transfer_source_po'] = $transferredMap[$lotItemKey]['source'];
                    $row['transfer_qty'] = $transferredMap[$lotItemKey]['qty'];
                } else {
                    $lotItemTotals[$lotItemKey] = 0;
                }
            }
            $row['computed_prev_lot_qty'] = $lotItemTotals[$lotItemKey];
            $lotItemTotals[$lotItemKey] += $row['added_quantity'];
            $row['computed_new_lot_qty'] = $lotItemTotals[$lotItemKey];
        }
        unset($row);

        usort($rows, function ($first, $second) {
            $dateComparison = strcmp($second['date_created'] ?? '', $first['date_created'] ?? '');
            return $dateComparison !== 0
                ? $dateComparison
                : ((int)($second['history_id'] ?? 0) <=> (int)($first['history_id'] ?? 0));
        });
        return array_values($rows);
    }

    public function getPOsReadyToDeliverFiltered($filters = []) {
        $sql = "SELECT po.*, c.customer_name, c.customer_code, u.full_name as requested_by_name,
                (po.produced_quantity - po.delivered_quantity) as available_for_delivery
                FROM purchase_orders po 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON po.requested_by = u.user_id 
                WHERE po.`remove` = 0 
                AND po.delivered_quantity < po.total_quantity";
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

        $sql .= " ORDER BY po.last_update DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateDRNumber($delivery_id, $dr_number) {
        $sql = "UPDATE deliveries SET dr_number = :dr_number WHERE delivery_id = :delivery_id";
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute([
            'dr_number' => $dr_number,
            'delivery_id' => $delivery_id
        ]);
    }

    public function getDRNumbersByPOIds($poIds) {
        if (empty($poIds)) return [];
        $placeholders = implode(',', array_fill(0, count($poIds), '?'));
        $sql = "SELECT po_id, dr_number FROM deliveries 
                WHERE po_id IN ($placeholders) AND dr_number IS NOT NULL AND dr_number != '' AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($poIds);
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['po_id']][] = $row['dr_number'];
        }
        return $grouped;
    }

    public function getDeliveriesByPOId($po_id) {
        $sql = "SELECT d.*, l.lot_number
                FROM deliveries d
                LEFT JOIN production_lots l ON d.lot_id = l.lot_id
                WHERE d.po_id = :po_id AND d.`remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['po_id' => $po_id]);
        return $stmt->fetchAll();
    }

    public function createLot($data) {
        $sql = "INSERT INTO production_lots (po_id, poi_id, lot_number, quantity_produced, pcs_per_case, lot_date, created_by)
                VALUES (:po_id, :poi_id, :lot_number, :quantity_produced, :pcs_per_case, :lot_date, :created_by)";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'po_id' => $data['po_id'],
            'poi_id' => $data['poi_id'],
            'lot_number' => $data['lot_number'],
            'quantity_produced' => $data['quantity_produced'] ?? 0,
            'pcs_per_case' => $data['pcs_per_case'] ?? null,
            'lot_date' => $data['lot_date'] ?? date('Y-m-d'),
            'created_by' => $data['created_by'] ?? null
        ]);
        return self::getConnection()->lastInsertId();
    }

    public function updateLotQuantity($poi_id, $lot_number, $added_quantity, $user_id, $po_id = null, $pcs_per_case = null) {
        $conn = self::getConnection();
        $sql = "SELECT lot_id, quantity_produced, pcs_per_case FROM production_lots 
                WHERE poi_id = :poi_id AND lot_number = :lot_number AND `is_removed` = 0 FOR UPDATE";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['poi_id' => $poi_id, 'lot_number' => $lot_number]);
        $lot = $stmt->fetch();

        if ($lot) {
            $existingPcs = $lot['pcs_per_case'] !== null ? intval($lot['pcs_per_case']) : null;
            $newPcs = $pcs_per_case !== null ? intval($pcs_per_case) : null;
            $pcsMatches = ($existingPcs === $newPcs);

            if ($pcsMatches) {
                $newQty = $lot['quantity_produced'] + $added_quantity;
                $sql = "UPDATE production_lots SET quantity_produced = :qty WHERE lot_id = :lot_id";
                $stmt = $conn->prepare($sql);
                $stmt->execute(['qty' => $newQty, 'lot_id' => $lot['lot_id']]);
                return $lot['lot_id'];
            } else {
                return $this->createLot([
                    'po_id' => $po_id,
                    'poi_id' => $poi_id,
                    'lot_number' => $lot_number,
                    'quantity_produced' => $added_quantity,
                    'pcs_per_case' => $pcs_per_case,
                    'created_by' => $user_id
                ]);
            }
        } else {
            return $this->createLot([
                'po_id' => $po_id,
                'poi_id' => $poi_id,
                'lot_number' => $lot_number,
                'quantity_produced' => $added_quantity,
                'pcs_per_case' => $pcs_per_case,
                'created_by' => $user_id
            ]);
        }
    }

    public function getLotsByPOItem($poi_id, $po_id = null) {
        $conn = self::getConnection();

        // When po_id is provided, derive lots from deliveries' lot_items JSON
        // This matches how delivered_quantity is computed in purchase_order_items
        if (!is_null($po_id)) {
            $stmt = $conn->prepare("SELECT lot_items FROM deliveries 
                WHERE po_id = :po_id AND lot_items IS NOT NULL AND `remove` = 0");
            $stmt->execute(['po_id' => $po_id]);

            $lotMap = [];
            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $items = json_decode($r['lot_items'], true);
                if (!is_array($items)) continue;
                foreach ($items as $li) {
                    $lPoi = (int)($li['poi_id'] ?? 0);
                    $qty = (int)($li['qty'] ?? 0);
                    $lotNum = $li['lot_number'] ?? '';
                    if ($lPoi == $poi_id && $qty > 0 && $lotNum !== '') {
                        if (!isset($lotMap[$lotNum])) {
                            $lotMap[$lotNum] = ['lot_number' => $lotNum, 'quantity_delivered' => 0];
                        }
                        $lotMap[$lotNum]['quantity_delivered'] += $qty;
                    }
                }
            }

            ksort($lotMap);
            return array_values($lotMap);
        }

        // Fallback: return production lots (used by FG inventory / reports)
        $sql = "SELECT pl.lot_id, pl.poi_id, pl.lot_number, pl.quantity_produced,
                       MIN(pl.lot_date) as lot_date, MIN(pl.created_by) as created_by, MIN(pl.date_created) as date_created
                FROM production_lots pl
                WHERE pl.poi_id = :poi_id
                AND pl.is_removed = 0 
                GROUP BY pl.lot_id, pl.lot_number, pl.quantity_produced
                ORDER BY lot_number ASC, date_created ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute(['poi_id' => $poi_id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAvailableLotsForPO($po_id) {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT delivery_id, delivery_date, lot_items FROM deliveries 
                WHERE po_id = :po_id AND lot_items IS NOT NULL AND `remove` = 0
                ORDER BY delivery_date ASC, delivery_id ASC");
        $stmt->execute(['po_id' => $po_id]);
        $jsonDelivered = [];
        $returnedConsumed = [];
        while ($r = $stmt->fetch()) {
            $items = json_decode($r['lot_items'], true);
            if (!is_array($items)) continue;
            foreach ($items as $li) {
                if (isset($li['lot_id'])) {
                    $lid = intval($li['lot_id']);
                    $qty = intval($li['qty'] ?? 0);
                    $jsonDelivered[$lid] = ($jsonDelivered[$lid] ?? 0) + $qty;
                    $returnedConsumed[$lid] = ($returnedConsumed[$lid] ?? 0) + intval($li['returned_qty'] ?? 0);
                }
            }
        }

        $jsonBackloaded = [];
        $blStmt = $conn->prepare("SELECT b.lot_id, b.quantity
                FROM backloads b WHERE b.po_id = :po_id AND b.`remove` = 0");
        $blStmt->execute(['po_id' => $po_id]);
        while ($bl = $blStmt->fetch()) {
            $lid = intval($bl['lot_id']);
            $jsonBackloaded[$lid] = ($jsonBackloaded[$lid] ?? 0) + intval($bl['quantity']);
        }

        $stmtPoi = $conn->prepare("SELECT poi_id, item_id FROM purchase_order_items WHERE po_id = :po_id");
        $stmtPoi->execute(['po_id' => $po_id]);
        $normalItems = $stmtPoi->fetchAll();
        $allPoiIds = array_column($normalItems, 'poi_id');
        if (empty($allPoiIds)) return [];

        $placeholders = implode(',', array_fill(0, count($allPoiIds), '?'));
        $stmt2 = $conn->prepare("SELECT l.* FROM production_lots l 
                WHERE l.poi_id IN ($placeholders) AND l.`is_removed` = 0");
        $stmt2->execute(array_values($allPoiIds));
        $lots = $stmt2->fetchAll();

        $merged = [];
        foreach ($lots as $lot) {
            $lid = $lot['lot_id'];
            $lot['available_quantity'] = max(0, $lot['quantity_produced'] - ($jsonDelivered[$lid] ?? 0) + ($jsonBackloaded[$lid] ?? 0));
            $lot['backloaded_qty'] = max(0, ($jsonBackloaded[$lid] ?? 0) - ($returnedConsumed[$lid] ?? 0));
            if ($lot['available_quantity'] <= 0) continue;

            $key = $lot['lot_number'] . '_' . $lot['poi_id'];
            if (isset($merged[$key])) {
                $merged[$key]['available_quantity'] += $lot['available_quantity'];
                $merged[$key]['quantity_produced'] += $lot['quantity_produced'];
                $merged[$key]['backloaded_qty'] += $lot['backloaded_qty'];
            } else {
                $merged[$key] = $lot;
            }
        }
        $result = array_values($merged);
        usort($result, function($a, $b) {
            $dateCmp = strtotime($a['date_created']) - strtotime($b['date_created']);
            return $dateCmp !== 0 ? $dateCmp : strcmp($a['lot_number'], $b['lot_number']);
        });
        return $result;
    }

    public function getAvailableLotsForDelivery($poi_id) {
        $conn = self::getConnection();
        $poiStmt = $conn->prepare("SELECT po_id FROM purchase_order_items WHERE poi_id = :poi_id");
        $poiStmt->execute(['poi_id' => $poi_id]);
        $po_id = $poiStmt->fetchColumn();
        if (!$po_id) return [];

        $sql = "SELECT l.* FROM production_lots l 
                WHERE l.poi_id = :poi_id AND l.`is_removed` = 0";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['poi_id' => $poi_id]);
        $lots = $stmt->fetchAll();

        $stmt2 = $conn->prepare("SELECT delivery_id, lot_items FROM deliveries 
                WHERE po_id = :po_id AND lot_items IS NOT NULL AND `remove` = 0");
        $stmt2->execute(['po_id' => $po_id]);
        $jsonDelivered = [];
        $returnedConsumed = [];
        while ($r = $stmt2->fetch()) {
            $items = json_decode($r['lot_items'], true);
            if (!is_array($items)) continue;
            foreach ($items as $li) {
                if (isset($li['lot_id'])) {
                    $lid = intval($li['lot_id']);
                    $jsonDelivered[$lid] = ($jsonDelivered[$lid] ?? 0) + intval($li['qty'] ?? 0);
                    $returnedConsumed[$lid] = ($returnedConsumed[$lid] ?? 0) + intval($li['returned_qty'] ?? 0);
                }
            }
        }

        $jsonBackloaded = [];
        $blStmt = $conn->prepare("SELECT b.lot_id, b.quantity
                FROM backloads b INNER JOIN deliveries d ON b.delivery_id = d.delivery_id
                WHERE d.po_id = :po_id AND b.`remove` = 0");
        $blStmt->execute(['po_id' => $po_id]);
        while ($bl = $blStmt->fetch()) {
            $lid = intval($bl['lot_id']);
            $jsonBackloaded[$lid] = ($jsonBackloaded[$lid] ?? 0) + intval($bl['quantity']);
        }

        $merged = [];
        foreach ($lots as $lot) {
            $lid = $lot['lot_id'];
            $lot['available_quantity'] = max(0, $lot['quantity_produced'] - ($jsonDelivered[$lid] ?? 0) + ($jsonBackloaded[$lid] ?? 0));
            $lot['backloaded_qty'] = max(0, ($jsonBackloaded[$lid] ?? 0) - ($returnedConsumed[$lid] ?? 0));
            if ($lot['available_quantity'] <= 0) continue;
            $key = $lot['lot_number'] . '_' . $lot['poi_id'];
            if (isset($merged[$key])) {
                $merged[$key]['available_quantity'] += $lot['available_quantity'];
                $merged[$key]['quantity_produced'] += $lot['quantity_produced'];
                $merged[$key]['backloaded_qty'] += $lot['backloaded_qty'];
            } else {
                $merged[$key] = $lot;
            }
        }
        $result = array_values($merged);
        usort($result, function($a, $b) {
            $dateCmp = strtotime($a['date_created']) - strtotime($b['date_created']);
            return $dateCmp !== 0 ? $dateCmp : strcmp($a['lot_number'], $b['lot_number']);
        });
        return $result;
    }

    public function getItemByPoiId($poi_id) {
        return $this->catalogModel->getItemByPoiId($poi_id);
    }

    public function getItemById($item_id) {
        return $this->catalogModel->getItemById($item_id);
    }

    public function getLotById($lot_id) {
        $sql = "SELECT * FROM production_lots WHERE lot_id = :lot_id AND `is_removed` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['lot_id' => $lot_id]);
        return $stmt->fetch();
    }

    public function getLotsByLotNumber($lot_number, $poi_id) {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT * FROM production_lots 
                WHERE lot_number = :lot_number AND poi_id = :poi_id AND `is_removed` = 0");
        $stmt->execute(['lot_number' => $lot_number, 'poi_id' => $poi_id]);
        return $stmt->fetchAll();
    }

    public function getLotRemaining($lot_id) {
        $lot = $this->getLotById($lot_id);
        if (!$lot) return 0;
        $conn = self::getConnection();
        $poi_id = $lot['poi_id'];

        if ($poi_id) {
            $poiStmt = $conn->prepare("SELECT po_id FROM purchase_order_items WHERE poi_id = :poi_id");
            $poiStmt->execute(['poi_id' => $poi_id]);
            $po_id = $poiStmt->fetchColumn();
            if (!$po_id) return 0;
            $stmt2 = $conn->prepare("SELECT lot_items FROM deliveries 
                    WHERE po_id = :po_id AND lot_items IS NOT NULL AND `remove` = 0");
            $stmt2->execute(['po_id' => $po_id]);
        } else {
            // For independent lots, search deliveries containing this lot_id in JSON
            $lotIdJson = json_encode(intval($lot_id));
            $stmt2 = $conn->prepare("SELECT lot_items FROM deliveries 
                    WHERE lot_items IS NOT NULL AND `remove` = 0
                    AND lot_items LIKE :pattern");
            $stmt2->execute(['pattern' => '%"lot_id":' . $lot_id . '%']);
        }
        $deliveredJson = 0;
        while ($r = $stmt2->fetch()) {
            $items = json_decode($r['lot_items'], true);
            if (!is_array($items)) continue;
            foreach ($items as $li) {
                if (isset($li['lot_id']) && intval($li['lot_id']) === intval($lot_id)) {
                    $deliveredJson += intval($li['qty'] ?? 0);
                }
            }
        }

        if ($poi_id) {
            $backloadedStmt = $conn->prepare("SELECT COALESCE(SUM(b.quantity), 0) FROM backloads b
                    INNER JOIN deliveries d ON b.delivery_id = d.delivery_id
                    WHERE d.po_id = :po_id AND b.lot_id = :lot_id AND b.`remove` = 0");
            $backloadedStmt->execute(['po_id' => $po_id, 'lot_id' => $lot_id]);
        } else {
            $backloadedStmt = $conn->prepare("SELECT COALESCE(SUM(b.quantity), 0) FROM backloads b
                    WHERE b.lot_id = :lot_id AND b.`remove` = 0");
            $backloadedStmt->execute(['lot_id' => $lot_id]);
        }
        $backloaded = intval($backloadedStmt->fetchColumn());

        return max(0, $lot['quantity_produced'] - $deliveredJson + $backloaded);
    }

    public function getLotsByPOForPrint($po_id) {
        $sql = "SELECT l.*, 
                    poi.quantity AS poi_quantity, poi.unit_price, poi.item_id,
                    i.item_code, i.item_description, i.item_uom, i.uom_conversion,
                    COALESCE(
                        (SELECT SUM(d.delivery_quantity) FROM deliveries d 
                         WHERE d.lot_id = l.lot_id AND d.`remove` = 0), 0
                    ) AS delivered_legacy
                FROM production_lots l
                LEFT JOIN purchase_order_items poi ON l.poi_id = poi.poi_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                WHERE l.po_id = :po_id AND l.`is_removed` = 0
                ORDER BY i.item_description ASC, l.lot_number ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['po_id' => $po_id]);
        $rows = $stmt->fetchAll();
        $conn = self::getConnection();
        $stmt2 = $conn->prepare("SELECT lot_items FROM deliveries 
                WHERE lot_items IS NOT NULL AND `remove` = 0");
        $stmt2->execute();
        $jsonDelivered = [];
        $lotActualConv = [];
        while ($r = $stmt2->fetch()) {
            $items = json_decode($r['lot_items'], true);
            if (!is_array($items)) continue;
            foreach ($items as $li) {
                if (isset($li['lot_id'])) {
                    $lid = intval($li['lot_id']);
                    $jsonDelivered[$lid] = ($jsonDelivered[$lid] ?? 0) + intval($li['qty'] ?? 0);
                    if (!isset($lotActualConv[$lid]) && !empty($li['actual_uom_conversion'])) {
                        $lotActualConv[$lid] = intval($li['actual_uom_conversion']);
                    }
                }
            }
        }
        foreach ($rows as &$row) {
            $row['available_quantity'] = max(0, $row['quantity_produced'] - ($row['delivered_legacy'] ?? 0) - ($jsonDelivered[$row['lot_id']] ?? 0));
            if (isset($lotActualConv[$row['lot_id']])) {
                $row['actual_uom_conversion'] = $lotActualConv[$row['lot_id']];
            }
        }
        unset($row);

        $grouped = [];
        foreach ($rows as $row) {
            $itemId = $row['item_id'];
            if (!isset($grouped[$itemId])) {
                $grouped[$itemId] = [
                    'item_id' => $itemId,
                    'item_code' => $row['item_code'],
                    'item_description' => $row['item_description'],
                    'item_uom' => $row['item_uom'],
                    'uom_conversion' => $row['uom_conversion'],
                    'unit_price' => $row['unit_price'],
                    'lots' => []
                ];
            }
            $grouped[$itemId]['lots'][] = $row;
        }
        return $grouped;
    }

    public function getLotsByIds($lotIds) {
        if (empty($lotIds)) return [];
        $placeholders = implode(',', array_fill(0, count($lotIds), '?'));
        $sql = "SELECT l.*, 
                    poi.quantity AS poi_quantity, poi.unit_price, poi.item_id,
                    i.item_code, i.item_description, i.item_uom, i.uom_conversion,
                    COALESCE((SELECT SUM(d.delivery_quantity) FROM deliveries d WHERE d.lot_id = l.lot_id AND d.`remove` = 0), 0) AS total_delivered
                FROM production_lots l
                LEFT JOIN purchase_order_items poi ON l.poi_id = poi.poi_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                WHERE l.lot_id IN ($placeholders) AND l.`is_removed` = 0
                ORDER BY i.item_description ASC, l.lot_number ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($lotIds);
        return $stmt->fetchAll();
    }

    public function getDeliveriesByDRNumber($dr_number) {
        $sql = "SELECT d.*, po.customer_po_number, po.customer_terms, c.customer_name, c.customer_code, c.customer_address, c.customer_tin
                FROM deliveries d
                LEFT JOIN purchase_orders po ON d.po_id = po.po_id
                LEFT JOIN customers c ON po.customer_id = c.customer_id
                WHERE d.dr_number = :dr_number AND d.`remove` = 0
                ORDER BY d.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['dr_number' => $dr_number]);
        $deliveries = $stmt->fetchAll();
        $temp = [];
        foreach ($deliveries as $d) {
            $lotItems = json_decode($d['lot_items'] ?? '[]', true);
            if (is_array($lotItems) && count($lotItems) > 0) {
                foreach ($lotItems as $li) {
                    $key = ($li['lot_number'] ?? '') . '||' . ($li['item_code'] ?? '');
                    if (isset($temp[$key])) {
                        $temp[$key]['delivery_quantity'] += $li['qty'] ?? 0;
                    } else {
                        $temp[$key] = [
                            'delivery_id' => $d['delivery_id'],
                            'po_id' => $d['po_id'],
                            'lot_number' => $li['lot_number'] ?? '',
                            'item_code' => $li['item_code'] ?? '',
                            'item_description' => $li['item_description'] ?? '',
                            'delivery_quantity' => $li['qty'] ?? 0,
                            'delivery_date' => $d['delivery_date'],
                            'dr_number' => $d['dr_number'],
                            'customer_po_number' => $d['customer_po_number'] ?? '',
                            'customer_name' => $d['customer_name'] ?? '',
                            'customer_code' => $d['customer_code'] ?? '',
                            'customer_address' => $d['customer_address'] ?? '',
                            'customer_tin' => $d['customer_tin'] ?? '',
                            'customer_terms' => $d['customer_terms'] ?? 0,
                            'unit_price' => $li['unit_price'] ?? 0,
                            'item_uom' => $li['item_uom'] ?? '',
                            'uom_conversion' => $li['uom_conversion'] ?? null,
                            'actual_uom_conversion' => $li['actual_uom_conversion'] ?? null,
                            'item_id' => $li['item_id'] ?? null,
                            'remarks' => $d['remarks'] ?? '',
                            'plate_number' => $d['plate_number'] ?? '',
                            'vehicle_type' => $d['vehicle_type'] ?? '',
                            'logistic_provider' => $d['logistic_provider'] ?? '',
                        ];
                    }
                }
            } else {
                $temp[] = $d;
            }
        }
        return array_values($temp);
    }

    public function getLotsByDRNumber($dr_number) {
        $sql = "SELECT l.lot_id
                FROM deliveries d
                LEFT JOIN production_lots l ON d.lot_id = l.lot_id
                WHERE d.dr_number = :dr_number AND d.`remove` = 0 AND d.lot_id IS NOT NULL";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['dr_number' => $dr_number]);
        return array_column($stmt->fetchAll(), 'lot_id');
    }

    public function saveDRNumberForLots($lotIds, $dr_number) {
        if (empty($lotIds)) return;
        $placeholders = implode(',', array_fill(0, count($lotIds), '?'));
        $sql = "UPDATE deliveries SET dr_number = ? WHERE lot_id IN ($placeholders) AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $params = array_merge([$dr_number], $lotIds);
        $stmt->execute($params);
    }

    public function checkDRNumber($dr_number) {
        $sql = "SELECT DISTINCT po_id FROM deliveries WHERE dr_number = :dr_number AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['dr_number' => $dr_number]);
        $rows = $stmt->fetchAll();
        return [
            'exists' => count($rows) > 0,
            'po_ids' => array_column($rows, 'po_id')
        ];
    }

    public function reportDelivery($deliveryId, $remarks) {
        $sql = "UPDATE deliveries SET report_remarks = :remarks, remarks_type = 'report'
                WHERE delivery_id = :delivery_id AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['remarks' => $remarks, 'delivery_id' => $deliveryId]);
    }

    public function createDeliveryReport($deliveryId, $poiId, $poId, $lotId, $oldQuantity, $userId, $reason, $reportType = 'dr_number') {
        $sql = "INSERT INTO delivery_reports (delivery_id, poi_id, po_id, lot_id, old_quantity, reported_by, reason, report_type)
                VALUES (:delivery_id, :poi_id, :po_id, :lot_id, :old_quantity, :reported_by, :reason, :report_type)";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'delivery_id' => $deliveryId,
            'poi_id' => $poiId,
            'po_id' => $poId,
            'lot_id' => $lotId,
            'old_quantity' => $oldQuantity,
            'reported_by' => $userId,
            'reason' => $reason,
            'report_type' => $reportType
        ]);
        return self::getConnection()->lastInsertId();
    }

    public function getDeliveryReportsByDeliveryId($deliveryId) {
        $sql = "SELECT dr.*, u.full_name as reporter_name, ru.full_name as resolver_name
                FROM delivery_reports dr
                LEFT JOIN users u ON dr.reported_by = u.user_id
                LEFT JOIN users ru ON dr.resolved_by = ru.user_id
                WHERE dr.delivery_id = :delivery_id
                ORDER BY dr.date_reported DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['delivery_id' => $deliveryId]);
        return $stmt->fetchAll();
    }

    public function getDeliveryReportsCount() {
        $sql = "SELECT COUNT(DISTINCT dr.delivery_id) FROM delivery_reports dr
                INNER JOIN deliveries d ON dr.delivery_id = d.delivery_id
                WHERE dr.status = 'pending' AND d.remove = 0";
        return self::getConnection()->query($sql)->fetchColumn();
    }

    public function getDeliveryReportById($reportId) {
        $sql = "SELECT dr.*, u.full_name as reporter_name, d.lot_items, d.dr_number
                FROM delivery_reports dr
                LEFT JOIN users u ON dr.reported_by = u.user_id
                LEFT JOIN deliveries d ON dr.delivery_id = d.delivery_id
                WHERE dr.report_id = :report_id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['report_id' => $reportId]);
        return $stmt->fetch();
    }

    public function resolveDeliveryReport($reportId, $newQuantity, $resolvedBy, $newDrNumber = null) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
        $report = $this->getDeliveryReportById($reportId);
        if (!$report) { $conn->rollBack(); return false; }

        $deliveryId = $report['delivery_id'];

        // 1. Mark report as resolved
        $conn->prepare("UPDATE delivery_reports SET status = 'resolved', resolved_by = :resolved_by,
            new_quantity = :new_quantity, date_resolved = NOW() WHERE report_id = :report_id")
            ->execute(['resolved_by' => $resolvedBy, 'new_quantity' => $newQuantity, 'report_id' => $reportId]);

        // 2. Update delivery remarks_type from 'report' to 'edited'
        $conn->prepare("UPDATE deliveries SET remarks_type = 'edited'
            WHERE delivery_id = :delivery_id AND remarks_type = 'report'")
            ->execute(['delivery_id' => $deliveryId]);

        if ($report['report_type'] === 'quantity' && $report['lot_id']) {
            // 3a. Quantity report â€” update lot_items JSON
            $lotId = $report['lot_id'];
            $lotItems = json_decode($report['lot_items'] ?? '[]', true);
            if (!is_array($lotItems)) $lotItems = [];
            foreach ($lotItems as &$li) {
                if (isset($li['lot_id']) && intval($li['lot_id']) === intval($lotId)) {
                    $li['qty'] = $newQuantity;
                    break;
                }
            }
            unset($li);
            $newLotItemsJson = json_encode($lotItems);

            // 4a. Recalculate delivery_quantity = SUM of all lot_items qty
            $newDeliveryQty = 0;
            foreach ($lotItems as $li) {
                $newDeliveryQty += intval($li['qty'] ?? 0);
            }

            $conn->prepare("UPDATE deliveries SET lot_items = :lot_items, delivery_quantity = :delivery_quantity
                WHERE delivery_id = :delivery_id")
                ->execute(['lot_items' => $newLotItemsJson, 'delivery_quantity' => $newDeliveryQty, 'delivery_id' => $deliveryId]);

            // 5a. Recalculate purchase_order_items.delivered_quantity
            $conn->prepare("UPDATE purchase_order_items poi SET delivered_quantity = GREATEST(0,
                (SELECT COALESCE(SUM(d.delivery_quantity), 0) FROM deliveries d
                WHERE d.poi_id = poi.poi_id AND d.`remove` = 0)
                - COALESCE((SELECT SUM(b.quantity) FROM backloads b WHERE b.poi_id = poi.poi_id AND b.`remove` = 0), 0)
            ) WHERE poi.poi_id = :poi_id")
                ->execute(['poi_id' => $report['poi_id']]);

            // 6a. Recalculate purchase_orders.delivered_quantity
            $conn->prepare("UPDATE purchase_orders po SET delivered_quantity = (
                SELECT COALESCE(SUM(delivered_quantity), 0) FROM purchase_order_items WHERE po_id = :po_id
            ) WHERE po.po_id = :po_id2")
                ->execute(['po_id' => $report['po_id'], 'po_id2' => $report['po_id']]);
        } elseif ($report['report_type'] === 'dr_number' && $newDrNumber) {
            // 3b. DR Number report â€” update dr_number
            $conn->prepare("UPDATE deliveries SET dr_number = :dr_number
                WHERE delivery_id = :delivery_id")
                ->execute(['dr_number' => $newDrNumber, 'delivery_id' => $deliveryId]);
        }

        $conn->commit();
        return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function updateDelivery($deliveryId, $data) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
        $fields = [];
        $params = ['delivery_id' => $deliveryId];

        if (isset($data['dr_number'])) {
            // Get current dr_number to store as old
            $stmt = $conn->prepare("SELECT dr_number FROM deliveries WHERE delivery_id = :delivery_id");
            $stmt->execute(['delivery_id' => $deliveryId]);
            $current = $stmt->fetch();
            if ($current && $current['dr_number'] !== trim($data['dr_number']) && !empty($current['dr_number'])) {
                $fields[] = 'old_dr_number = :old_dr_number';
                $params['old_dr_number'] = $current['dr_number'];
            }
            $fields[] = 'dr_number = :dr_number';
            $params['dr_number'] = trim($data['dr_number']);
        }
        if (isset($data['delivery_date'])) {
            $fields[] = 'delivery_date = :delivery_date';
            $params['delivery_date'] = $data['delivery_date'];
        }
        if (array_key_exists('plate_number', $data)) {
            $fields[] = 'plate_number = :plate_number';
            $params['plate_number'] = trim($data['plate_number']);
        }
        if (array_key_exists('vehicle_type', $data)) {
            $fields[] = 'vehicle_type = :vehicle_type';
            $params['vehicle_type'] = trim($data['vehicle_type']);
        }
        if (array_key_exists('logistic_provider', $data)) {
            $fields[] = 'logistic_provider = :logistic_provider';
            $params['logistic_provider'] = trim($data['logistic_provider']);
        }

        // Recalculation targets, populated by PO reassignment and lot changes
        $recalcPoiIds = [];
        $recalcPoIds = [];

        // Working copy of the delivery JSON shared by all change handlers
        $stmt = $conn->prepare("SELECT po_id, poi_id, lot_items, delivery_quantity, old_quantity FROM deliveries WHERE delivery_id = :delivery_id");
        $stmt->execute(['delivery_id' => $deliveryId]);
        $deliveryBase = $stmt->fetch();
        $workingLotItems = [];
        $jsonDirty = false;
        if ($deliveryBase) {
            $workingLotItems = json_decode($deliveryBase['lot_items'] ?? '[]', true);
            if (!is_array($workingLotItems)) $workingLotItems = [];
        }

        // Handle PO reassignment (clean overwrite: delivery re-linked to the new PO)
        $newPoId = !empty($data['po_id']) ? intval($data['po_id']) : 0;
        $codeToItemStmt = $conn->prepare("SELECT item_id, item_code FROM items WHERE item_code = :code");
        $codeItemCache = [];
        $getItemIdByCode = function($code) use ($conn, $codeToItemStmt, &$codeItemCache) {
            $code = trim($code ?? '');
            if ($code === '') return null;
            if (array_key_exists($code, $codeItemCache)) return $codeItemCache[$code];
            $codeToItemStmt->execute(['code' => $code]);
            $iid = $codeToItemStmt->fetchColumn() ?: null;
            $codeItemCache[$code] = $iid ? intval($iid) : null;
            return $codeItemCache[$code];
        };
        if ($newPoId > 0 && $deliveryBase && intval($deliveryBase['po_id']) !== $newPoId) {
            $poCheck = $conn->prepare("SELECT po_id FROM purchase_orders WHERE po_id = :po_id AND `remove` = 0");
            $poCheck->execute(['po_id' => $newPoId]);
            if ($poCheck->fetchColumn()) {
                // remap each lot item to the POI of the new PO matching its item
                $newPoisStmt = $conn->prepare("SELECT poi_id, item_id FROM purchase_order_items WHERE po_id = :po_id ORDER BY poi_id ASC");
                $newPoisStmt->execute(['po_id' => $newPoId]);
                $itemPoiMap = [];
                $firstPoi = null;
                foreach ($newPoisStmt->fetchAll() as $np) {
                    if ($firstPoi === null) $firstPoi = intval($np['poi_id']);
                    if (!isset($itemPoiMap[$np['item_id']])) $itemPoiMap[$np['item_id']] = intval($np['poi_id']);
                }
                if (!empty($workingLotItems)) {
                    $mappedPoi = null;
                    foreach ($workingLotItems as &$li) {
                        $itemRef = $getItemIdByCode($li['item_code'] ?? '');
                        if ($itemRef && isset($itemPoiMap[$itemRef])) {
                            $li['poi_id'] = $itemPoiMap[$itemRef];
                            if ($mappedPoi === null) $mappedPoi = $li['poi_id'];
                            if (!in_array($li['poi_id'], $recalcPoiIds)) $recalcPoiIds[] = $li['poi_id'];
                        }
                    }
                    unset($li);
                    $jsonDirty = true;
                    $fields[] = 'poi_id = :poi_id_remap';
                    $params['poi_id_remap'] = $mappedPoi ?? ($firstPoi ?? null);
                }
                $fields[] = 'po_id = :po_id';
                $params['po_id'] = $newPoId;
                // old PO keeps its delivered figures correct after re-link
                if (!empty($deliveryBase['poi_id']) && !in_array(intval($deliveryBase['poi_id']), $recalcPoiIds)) {
                    $recalcPoiIds[] = intval($deliveryBase['poi_id']);
                }
                if (!empty($deliveryBase['po_id'])) $recalcPoIds[] = intval($deliveryBase['po_id']);
                $recalcPoIds[] = $newPoId;
                // re-link uploaded DR receipts to the new PO
                $conn->prepare("UPDATE delivery_receipts SET po_id = :po_id WHERE delivery_id = :delivery_id AND `remove` = 0")
                    ->execute(['po_id' => $newPoId, 'delivery_id' => $deliveryId]);
            }
        }

        $targetPoId = $newPoId > 0 ? $newPoId : intval($deliveryBase['po_id'] ?? 0);

        // Helper: map of item_id => poi_id for a PO
        $getPoiMapForPo = function($poId) use ($conn) {
            $stmtP = $conn->prepare("SELECT poi_id, item_id FROM purchase_order_items WHERE po_id = :po_id");
            $stmtP->execute(['po_id' => $poId]);
            $map = [];
            foreach ($stmtP->fetchAll() as $p) {
                if (!isset($map[$p['item_id']])) $map[$p['item_id']] = intval($p['poi_id']);
            }
            return $map;
        };
        // Helper: consumed qty for (item_id, lot_number) from OTHER deliveries' JSON,
        // keyed by (item_code, lot_number) so orphaned lot_id references can't skew it
        $consumedByOthers = function($itemId, $lotNumber) use ($conn, $deliveryId, $getItemIdByCode) {
            $codeStmt = $conn->prepare("SELECT item_code, item_description FROM items WHERE item_id = :item_id");
            $codeStmt->execute(['item_id' => $itemId]);
            $itemRow = $codeStmt->fetch();
            $itemCode = $itemRow['item_code'] ?? '';
            $key = $itemCode . '|' . $lotNumber;
            $consumedStmt = $conn->prepare("SELECT lot_items FROM deliveries WHERE `remove` = 0 AND delivery_id != :delivery_id AND lot_items IS NOT NULL");
            $consumedStmt->execute(['delivery_id' => $deliveryId]);
            $consumed = 0;
            while ($row = $consumedStmt->fetch()) {
                $items = json_decode($row['lot_items'], true);
                if (!is_array($items)) continue;
                foreach ($items as $li) {
                    if (($li['item_code'] ?? '') . '|' . ($li['lot_number'] ?? '') === $key) {
                        $consumed += intval($li['qty'] ?? 0);
                    }
                }
            }
            $rowsStmt = $conn->prepare("SELECT COALESCE(SUM(quantity_produced), 0) FROM production_lots
                WHERE lot_number = :lot_number AND `is_removed` = 0
                AND (item_id = :item_id OR poi_id IN (SELECT poi_id FROM purchase_order_items WHERE item_id = :item_id2))");
            $rowsStmt->execute(['lot_number' => $lotNumber, 'item_id' => $itemId, 'item_id2' => $itemId]);
            return ['available_lots' => [], 'produced' => (int)$rowsStmt->fetchColumn(), 'consumed' => $consumed];
        };

        // Handle item / lot reassignment (clean overwrite of mis-clicked item or lot)
        $itemChanges = $data['item_changes'] ?? [];
        foreach ($itemChanges as $change) {
            if (empty($workingLotItems)) break;
            $groupLot = trim($change['old_lot_number'] ?? '');
            $groupItemCode = trim($change['old_item_code'] ?? '');
            $newLotId = intval($change['new_lot_id'] ?? 0);
            $newQty = intval($change['new_qty'] ?? 0) ?: intval($change['old_qty'] ?? 0);

            $groupIndices = [];
            $oldQtySum = 0;
            $oldPois = [];
            foreach ($workingLotItems as $idx => $li) {
                if (($li['lot_number'] ?? '') === $groupLot && ($li['item_code'] ?? '') === $groupItemCode) {
                    $groupIndices[] = $idx;
                    $oldQtySum += intval($li['qty'] ?? 0);
                    if (!empty($li['poi_id'])) $oldPois[] = intval($li['poi_id']);
                }
            }
            if (empty($groupIndices) || !$newLotId) continue;

            $stmtLot = $conn->prepare("SELECT pl.lot_id, pl.poi_id, pl.item_id, pl.lot_number, pl.pcs_per_case,
                    i.item_code, i.item_description, i.item_uom, i.uom_conversion
                FROM production_lots pl
                LEFT JOIN items i ON pl.item_id = i.item_id
                WHERE pl.lot_id = :lot_id AND pl.`is_removed` = 0");
            $stmtLot->execute(['lot_id' => $newLotId]);
            $newLot = $stmtLot->fetch();
            if (!$newLot) {
                return ['success' => false, 'error' => 'The selected lot is no longer available.'];
            }
            $newItemId = intval($newLot['item_id'] ?? 0);
            if (!$newItemId && !empty($newLot['poi_id'])) {
                $stmtItem = $conn->prepare("SELECT item_id FROM purchase_order_items WHERE poi_id = :poi_id");
                $stmtItem->execute(['poi_id' => $newLot['poi_id']]);
                $newItemId = intval($stmtItem->fetchColumn() ?: 0);
            }
            if (!$newItemId) {
                return ['success' => false, 'error' => 'The selected lot has no resolvable item.'];
            }

            // Availability across all rows sharing (item, lot_number), minus other deliveries' consumption
            $scope = $consumedByOthers($newItemId, $newLot['lot_number']);
            $available = $scope['produced'] - $scope['consumed'];
            if ($newQty > $available) {
                return ['success' => false, 'error' => 'Cannot deliver ' . $newQty . ' of lot ' . $newLot['lot_number'] . '. Only ' . max(0, $available) . ' available (produced: ' . $scope['produced'] . ', delivered by others: ' . $scope['consumed'] . ').'];
            }

            // Apply overwrite: first group entry becomes the reference, other duplicates removed
            $firstIdx = $groupIndices[0];
            $keepEntry = $workingLotItems[$firstIdx];
            $keepEntry['lot_id'] = intval($newLot['lot_id']);
            $keepEntry['lot_number'] = $newLot['lot_number'];
            if (!empty($newLot['item_code'])) $keepEntry['item_code'] = $newLot['item_code'];
            if (!empty($newLot['item_description'])) $keepEntry['item_description'] = $newLot['item_description'];
            if (!empty($newLot['item_uom'])) $keepEntry['item_uom'] = $newLot['item_uom'];
            if (!empty($newLot['uom_conversion'])) $keepEntry['uom_conversion'] = intval($newLot['uom_conversion']);
            $keepEntry['qty'] = $newQty;
            $poiMap = $getPoiMapForPo($targetPoId);
            if (isset($poiMap[$newItemId])) {
                $keepEntry['poi_id'] = $poiMap[$newItemId];
            }

            $replace = [];
            foreach ($workingLotItems as $idx => $li) {
                if ($idx === $firstIdx) $replace[] = $keepEntry;
                elseif (!in_array($idx, $groupIndices)) $replace[] = $li;
            }
            $workingLotItems = $replace;
            $jsonDirty = true;

            // Old linkage gets its delivered figures re-checked
            foreach ($oldPois as $op) {
                if (!in_array($op, $recalcPoiIds)) $recalcPoiIds[] = $op;
            }
            if (!empty($deliveryBase['po_id'])) $recalcPoIds[] = intval($deliveryBase['po_id']);
            if ($targetPoId) $recalcPoIds[] = $targetPoId;
            if (!empty($keepEntry['poi_id']) && !in_array(intval($keepEntry['poi_id']), $recalcPoiIds)) {
                $recalcPoiIds[] = intval($keepEntry['poi_id']);
            }
        }

        // Handle lot quantity changes
        $lotChanges = $data['lot_changes'] ?? [];
        if ($deliveryBase && !empty($lotChanges)) {
            // Validate: check available quantity for each lot change (group-level math:
            // produced across all rows of (item, lot_number) minus other deliveries' consumption)
            foreach ($lotChanges as $change) {
                $changeLotId = intval($change['lot_id'] ?? 0);
                $newQty = intval($change['new_qty'] ?? 0);

                // Find the current entry to learn its item/lot identity
                $entryCode = null;
                $entryLot = null;
                foreach ($workingLotItems as $li) {
                    if (isset($li['lot_id']) && intval($li['lot_id']) === $changeLotId) {
                        $entryCode = $li['item_code'] ?? '';
                        $entryLot = $li['lot_number'] ?? '';
                        break;
                    }
                }
                if ($entryLot === null || !$entryCode) continue;

                $entryItemId = $getItemIdByCode($entryCode);
                if (!$entryItemId) continue;

                // Group produced across all rows sharing (item, lot_number)
                $stmtLot = $conn->prepare("SELECT COALESCE(SUM(quantity_produced), 0) FROM production_lots
                    WHERE lot_number = :lot_number AND `is_removed` = 0
                    AND (item_id = :item_id OR poi_id IN (SELECT poi_id FROM purchase_order_items WHERE item_id = :item_id2))");
                $stmtLot->execute(['lot_number' => $entryLot, 'item_id' => $entryItemId, 'item_id2' => $entryItemId]);
                $lotProduced = (int)$stmtLot->fetchColumn();

                // Count consumption by (item_code, lot_number) from OTHER deliveries' JSON
                $stmtJson = $conn->prepare("SELECT lot_items FROM deliveries WHERE `remove` = 0 AND delivery_id != :delivery_id AND lot_items IS NOT NULL");
                $stmtJson->execute(['delivery_id' => $deliveryId]);
                $otherJsonDelivered = 0;
                while ($row = $stmtJson->fetch()) {
                    $items = json_decode($row['lot_items'], true);
                    if (!is_array($items)) continue;
                    foreach ($items as $li) {
                        if (($li['item_code'] ?? '') === $entryCode && ($li['lot_number'] ?? '') === $entryLot) {
                            $otherJsonDelivered += intval($li['qty'] ?? 0);
                        }
                    }
                }

                // Available = produced - (other deliveries' qty for this lot)
                // The current delivery's old qty is being replaced, so it doesn't count
                $available = $lotProduced - $otherJsonDelivered;

                if ($newQty > $available) {
                    return ['success' => false, 'error' => 'Cannot set quantity to ' . $newQty . '. Only ' . max(0, $available) . ' available for lot ' . $entryLot . ' (produced: ' . $lotProduced . ', delivered by others: ' . $otherJsonDelivered . ')'];
                }
            }

            foreach ($lotChanges as $change) {
                $changeLotId = intval($change['lot_id'] ?? 0);
                $newQty = intval($change['new_qty'] ?? 0);
                $actualConv = !empty($change['actual_uom_conversion']) ? intval($change['actual_uom_conversion']) : null;
                foreach ($workingLotItems as &$li) {
                    if (isset($li['lot_id']) && intval($li['lot_id']) === $changeLotId) {
                        $oldQty = intval($li['qty'] ?? 0);
                        $li['qty'] = $newQty;
                        if ($actualConv !== null) {
                            $li['actual_uom_conversion'] = $actualConv;
                        }
                        if ($oldQty !== $newQty) {
                            $existingOld = json_decode($deliveryBase['old_quantity'] ?? '{}', true);
                            if (!is_array($existingOld)) $existingOld = [];
                            $existingOld[strval($changeLotId)] = $oldQty;
                            $fields[] = 'old_quantity = :old_quantity';
                            $params['old_quantity'] = json_encode($existingOld);
                        }
                        break;
                    }
                }
                unset($li);
            }
            $jsonDirty = true;
        }

        // Persist the (possibly rewritten) lot items JSON and recompute delivery quantity
        if ($deliveryBase && ($jsonDirty || !empty($lotChanges))) {
            // Group lot_items by lot_number to merge same lots
            $groupedLotItems = [];
            foreach ($workingLotItems as $li) {
                $key = $li['lot_number'] ?? uniqid();
                if (!isset($groupedLotItems[$key])) {
                    $groupedLotItems[$key] = $li;
                    $groupedLotItems[$key]['qty'] = 0;
                }
                $groupedLotItems[$key]['qty'] += intval($li['qty'] ?? 0);
                if (isset($li['returned_qty'])) {
                    $groupedLotItems[$key]['returned_qty'] = ($groupedLotItems[$key]['returned_qty'] ?? 0) + intval($li['returned_qty']);
                }
            }
            $lotItems = array_values($groupedLotItems);

            // Recalculate delivery_quantity
            $newDeliveryQty = 0;
            foreach ($lotItems as $li) {
                $newDeliveryQty += intval($li['qty'] ?? 0);
            }

            $fields[] = 'lot_items = :lot_items';
            $params['lot_items'] = json_encode($lotItems);
            $fields[] = 'delivery_quantity = :delivery_quantity';
            $params['delivery_quantity'] = $newDeliveryQty;

            // Re-point delivery poi to the first (possibly remapped) item entry
            if (!empty($lotItems[0]['poi_id'])) {
                $fields[] = 'poi_id = :poi_id_main';
                $params['poi_id_main'] = $lotItems[0]['poi_id'];
            }

            // Collect POI ids for delivered recalculation (performed after row update)
            $poiIds = [];
            if (!empty($deliveryBase['poi_id'])) {
                $poiIds[] = $deliveryBase['poi_id'];
            }
            foreach ($lotItems as $li) {
                if (!empty($li['poi_id']) && !in_array($li['poi_id'], $poiIds)) {
                    $poiIds[] = $li['poi_id'];
                }
            }
            foreach ($poiIds as $poiId) {
                if (!in_array(intval($poiId), $recalcPoiIds)) $recalcPoiIds[] = intval($poiId);
            }
            $recalcPoIds[] = intval($deliveryBase['po_id']);
        }

        // Auto-resolve pending delivery reports when editing
        $conn->prepare("UPDATE delivery_reports SET status = 'resolved', resolved_by = 1,
            date_resolved = NOW() WHERE delivery_id = :delivery_id AND status = 'pending'")
            ->execute(['delivery_id' => $deliveryId]);

        if (!empty($fields)) {
            $fields[] = "remarks_type = 'edited'";
        }

        if (!empty($fields)) {
            $sql = "UPDATE deliveries SET " . implode(', ', $fields) .
                   " WHERE delivery_id = :delivery_id AND `remove` = 0";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }

        // Recalculate delivered quantities AFTER the delivery row reflects the edits
        foreach (array_unique($recalcPoiIds) as $poiId) {
            $conn->prepare("UPDATE purchase_order_items poi SET delivered_quantity = GREATEST(0,
                (SELECT COALESCE(SUM(d.delivery_quantity), 0) FROM deliveries d
                WHERE d.poi_id = poi.poi_id AND d.`remove` = 0)
                - COALESCE((SELECT SUM(b.quantity) FROM backloads b WHERE b.poi_id = poi.poi_id AND b.`remove` = 0), 0)
            ) WHERE poi.poi_id = :poi_id")->execute(['poi_id' => $poiId]);
        }
        foreach (array_unique(array_filter($recalcPoIds)) as $poId) {
            $conn->prepare("UPDATE purchase_orders po SET delivered_quantity = (
                SELECT COALESCE(SUM(delivered_quantity), 0) FROM purchase_order_items WHERE po_id = :po_id
            ) WHERE po.po_id = :po_id2")->execute(['po_id' => $poId, 'po_id2' => $poId]);
            $this->recalculatePODeliveryStatus($poId);
        }

        $conn->commit();
        return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function attachDRPhoto($data) {
        $sql = "INSERT INTO delivery_receipts (delivery_id, po_id, file_name, file_path, file_type, file_size, uploaded_by)
                VALUES (:delivery_id, :po_id, :file_name, :file_path, :file_type, :file_size, :uploaded_by)";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'delivery_id' => $data['delivery_id'],
            'po_id' => $data['po_id'],
            'file_name' => $data['file_name'],
            'file_path' => $data['file_path'],
            'file_type' => $data['file_type'],
            'file_size' => $data['file_size'],
            'uploaded_by' => $data['uploaded_by']
        ]);
        return self::getConnection()->lastInsertId();
    }

    public function getDRPhotoByDeliveryId($delivery_id) {
        $sql = "SELECT * FROM delivery_receipts
                WHERE delivery_id = :delivery_id AND `remove` = 0
                ORDER BY date_created DESC LIMIT 1";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['delivery_id' => $delivery_id]);
        return $stmt->fetch();
    }

    public function getReceiptsByPOId($po_id) {
        $sql = "SELECT dr.* FROM delivery_receipts dr
                WHERE dr.po_id = :po_id AND dr.`remove` = 0
                ORDER BY dr.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['po_id' => $po_id]);
        return $stmt->fetchAll();
    }

    public function deleteDRPhoto($receiptId) {
        $receipt = $this->getReceiptById($receiptId);
        if ($receipt && file_exists(__DIR__ . '/../../' . $receipt['file_path'])) {
            unlink(__DIR__ . '/../../' . $receipt['file_path']);
        }
        $sql = "UPDATE delivery_receipts SET `remove` = 1 WHERE receipt_id = :receipt_id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['receipt_id' => $receiptId]);
    }

    public function getReceiptById($receiptId) {
        $sql = "SELECT * FROM delivery_receipts WHERE receipt_id = :receipt_id AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['receipt_id' => $receiptId]);
        return $stmt->fetch();
    }

    public function toggleDeliveryStatus($deliveryId) {
        $conn = self::getConnection();
        $sql = "UPDATE deliveries SET active_status = IF(active_status = 1, 0, 1)
                WHERE delivery_id = :delivery_id AND `remove` = 0";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['delivery_id' => $deliveryId]);

        $sql2 = "SELECT active_status FROM deliveries WHERE delivery_id = :delivery_id";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->execute(['delivery_id' => $deliveryId]);
        return $stmt2->fetchColumn();
    }

    public function getReportedRemarksCount() {
        $sql = "SELECT COUNT(*) FROM deliveries WHERE remarks_type = 'report' AND `remove` = 0";
        return self::getConnection()->query($sql)->fetchColumn();
    }

    public function getPOsReadyToDeliver() {
        $sql = "SELECT po.*, c.customer_name, c.customer_code, u.full_name as requested_by_name,
                (po.produced_quantity - po.delivered_quantity) as available_for_delivery
                FROM purchase_orders po 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON po.requested_by = u.user_id 
                WHERE po.`remove` = 0 
                AND po.produced_quantity > po.delivered_quantity
                ORDER BY po.last_update DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createProductionReport($history_id, $poi_id, $po_id, $old_lot_number, $user_id, $reason, $report_type = 'lot_number') {
        $sql = "INSERT INTO production_reports (history_id, poi_id, po_id, old_lot_number, reported_by, reason, report_type)
                VALUES (:history_id, :poi_id, :po_id, :old_lot_number, :reported_by, :reason, :report_type)";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'history_id' => $history_id,
            'poi_id' => $poi_id,
            'po_id' => $po_id,
            'old_lot_number' => $old_lot_number,
            'reported_by' => $user_id,
            'reason' => $reason,
            'report_type' => $report_type
        ]);
        return self::getConnection()->lastInsertId();
    }

    public function getProductionReportsCount() {
        $sql = "SELECT COUNT(*) FROM production_reports WHERE status = 'pending'";
        return self::getConnection()->query($sql)->fetchColumn();
    }

    public function getProductionReportById($report_id) {
        $sql = "SELECT pr.*, ph.lot_number as history_lot, ph.item_description, ph.added_quantity,
                    u.full_name as reporter_name
                FROM production_reports pr
                LEFT JOIN production_history ph ON pr.history_id = ph.history_id
                LEFT JOIN users u ON pr.reported_by = u.user_id
                WHERE pr.report_id = :report_id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['report_id' => $report_id]);
        return $stmt->fetch();
    }

    public function resolveProductionReport($report_id, $new_lot_number, $resolved_by) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
        $report = $this->getProductionReportById($report_id);
        if (!$report) { $conn->rollBack(); return false; }

        $conn->prepare("UPDATE production_reports SET status = 'resolved', resolved_by = :resolved_by, 
            new_lot_number = :new_lot_number, date_resolved = NOW() WHERE report_id = :report_id")
            ->execute(['resolved_by' => $resolved_by, 'new_lot_number' => $new_lot_number, 'report_id' => $report_id]);

        $conn->prepare("UPDATE production_history SET lot_number = :lot_number WHERE history_id = :history_id")
            ->execute(['lot_number' => $new_lot_number, 'history_id' => $report['history_id']]);

        if ($report['poi_id'] && $report['old_lot_number']) {
            $conn->prepare("UPDATE production_lots SET lot_number = :new_lot 
                WHERE poi_id = :poi_id AND lot_number = :old_lot AND `is_removed` = 0")
                ->execute(['new_lot' => $new_lot_number, 'poi_id' => $report['poi_id'], 'old_lot' => $report['old_lot_number']]);
        }

        $conn->commit();
        return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function updateHistoryLotNumber($history_id, $new_lot_number) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
        $stmt = $conn->prepare("SELECT poi_id, lot_number FROM production_history WHERE history_id = :history_id");
        $stmt->execute(['history_id' => $history_id]);
        $history = $stmt->fetch();
        if (!$history) { $conn->rollBack(); return false; }

        $conn->prepare("UPDATE production_history SET lot_number = :lot_number WHERE history_id = :history_id")
            ->execute(['lot_number' => $new_lot_number, 'history_id' => $history_id]);

        if ($history['poi_id'] && $history['lot_number']) {
            $conn->prepare("UPDATE production_lots SET lot_number = :new_lot 
                WHERE poi_id = :poi_id AND lot_number = :old_lot AND `is_removed` = 0")
                ->execute(['new_lot' => $new_lot_number, 'poi_id' => $history['poi_id'], 'old_lot' => $history['lot_number']]);
        }

        $conn->prepare("UPDATE production_reports SET status = 'resolved', new_lot_number = :new_lot, date_resolved = NOW()
            WHERE history_id = :history_id AND status = 'pending'")
            ->execute(['new_lot' => $new_lot_number, 'history_id' => $history_id]);

        $conn->commit();
        return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /** FG PO-item options (poi_id + labels) for the production-history item change dropdown. */
    public function getItemOptionsForHistoryEdit() {
        $conn = self::getConnection();
        $sql = "SELECT poi.poi_id, poi.po_id, i.item_id, i.item_code, i.item_description, i.item_uom,
                       po.po_number, c.customer_name
                FROM purchase_order_items poi
                JOIN purchase_orders po ON po.po_id = poi.po_id AND po.`remove` = 0
                JOIN customers c ON c.customer_id = po.customer_id
                JOIN items i ON i.item_id = poi.item_id AND i.item_type = 'FG' AND i.`remove` = 0
                ORDER BY i.item_code ASC, po.po_number ASC";
        return $conn->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function editHistoryRecord($history_id, $new_added_quantity, $new_lot_number, $edited_by, $new_item_id = null) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("SELECT poi_id, po_id, added_quantity, previous_quantity, lot_number, item_id, item_description, date_created FROM production_history WHERE history_id = :history_id");
            $stmt->execute(['history_id' => $history_id]);
            $history = $stmt->fetch();
            if (!$history) { $conn->rollBack(); return false; }

            $old_added = (int)$history['added_quantity'];
            $old_lot = $history['lot_number'];
            $old_item_id = $history['item_id'] ?: null;
            $new_item_id = $new_item_id ? (int)$new_item_id : ($old_item_id ?: ($history['poi_id'] ? $this->getItemIdByPoi($history['poi_id']) : null));
            $itemDescription = $history['item_description'];
            if ($new_item_id) {
                $itemStmt = $conn->prepare("SELECT item_code, item_description FROM items WHERE item_id = :item_id");
                $itemStmt->execute(['item_id' => $new_item_id]);
                $itemRow = $itemStmt->fetch();
                if ($itemRow) {
                    $itemDescription = $itemRow['item_description'];
                }
            }

            $new_new_quantity = $history['previous_quantity'] + $new_added_quantity;
            $lot_changed = $new_lot_number !== $old_lot;
            $item_changed = $new_item_id && $old_item_id && (int)$new_item_id !== (int)$old_item_id;

            // Reverse old stock contribution from production_lots (delivery availability source)
            if ($old_lot && ($history['poi_id'] || $old_item_id)) {
                if ($history['poi_id']) {
                    if ($lot_changed || $item_changed) {
                        $conn->prepare("UPDATE production_lots SET quantity_produced = GREATEST(0, quantity_produced - :removed_qty)
                            WHERE poi_id = :poi_id AND lot_number = :lot_number AND item_id = :item_id AND `is_removed` = 0")
                            ->execute([
                                'removed_qty' => $old_added,
                                'poi_id' => $history['poi_id'],
                                'lot_number' => $old_lot,
                                'item_id' => $old_item_id
                            ]);
                    } else {
                        $conn->prepare("UPDATE production_lots SET quantity_produced = GREATEST(0, quantity_produced - :removed_qty)
                            WHERE poi_id = :poi_id AND lot_number = :lot_number AND `is_removed` = 0")
                            ->execute([
                                'removed_qty' => $old_added,
                                'poi_id' => $history['poi_id'],
                                'lot_number' => $old_lot
                            ]);
                    }

                    // Cleanly empty old lot rows when nothing remains after reversal
                    if ($lot_changed || $item_changed) {
                        $conn->prepare("UPDATE production_lots SET `is_removed` = 1
                            WHERE poi_id = :poi_id AND lot_number = :lot_number AND quantity_produced <= 0 AND `is_removed` = 0")
                            ->execute([
                                'poi_id' => $history['poi_id'],
                                'lot_number' => $old_lot
                            ]);
                    }
                } elseif ($old_item_id) {
                    // Independent FG lot: no POI link, reverse by item + lot
                    $conn->prepare("UPDATE production_lots SET quantity_produced = GREATEST(0, quantity_produced - :removed_qty)
                        WHERE item_id = :item_id AND lot_number = :lot_number AND `is_removed` = 0")
                        ->execute([
                            'removed_qty' => $old_added,
                            'item_id' => $old_item_id,
                            'lot_number' => $old_lot
                        ]);

                    $conn->prepare("UPDATE production_lots SET `is_removed` = 1
                        WHERE item_id = :item_id AND lot_number = :lot_number AND quantity_produced <= 0 AND `is_removed` = 0")
                        ->execute([
                            'item_id' => $old_item_id,
                            'lot_number' => $old_lot
                        ]);
                }
            }

            // Overwrite the history record as the original entry (no edited tags, no old values)
            $conn->prepare("UPDATE production_history
                SET added_quantity = :added, new_quantity = :new_qty, lot_number = :lot,
                    item_id = :item_id, item_description = :item_desc,
                    old_added_quantity = NULL, old_lot_number = NULL,
                    edited_by = NULL, date_edited = NULL
                WHERE history_id = :history_id")
                ->execute([
                    'added' => $new_added_quantity,
                    'new_qty' => $new_new_quantity,
                    'lot' => $new_lot_number,
                    'item_id' => $new_item_id,
                    'item_desc' => $itemDescription,
                    'history_id' => $history_id
                ]);

            // Apply the new stock contribution to production_lots under the new lot/item
            if ($history['poi_id'] || $new_item_id) {
                if ($history['poi_id']) {
                    $applyCondition = "poi_id = :poi_id";
                    $applyParams = ['poi_id' => $history['poi_id']];
                } else {
                    $applyCondition = "poi_id IS NULL AND item_id = :item_id";
                    $applyParams = ['item_id' => $new_item_id];
                }
                if ($new_item_id) {
                    $applyStmt = $conn->prepare("SELECT lot_id FROM production_lots
                        WHERE $applyCondition AND lot_number = :lot_number AND `is_removed` = 0
                        ORDER BY (item_id = :item_id_ord) DESC, (item_id IS NULL) DESC, lot_id ASC LIMIT 1");
                    $applyParams['lot_number'] = $new_lot_number;
                    $applyParams['item_id'] = $new_item_id;
                    $applyParams['item_id_ord'] = $new_item_id;
                    $applyStmt->execute($applyParams);
                } else {
                    $applyStmt = $conn->prepare("SELECT lot_id FROM production_lots
                        WHERE $applyCondition AND lot_number = :lot_number AND `is_removed` = 0
                        ORDER BY lot_id ASC LIMIT 1");
                    $applyParams['lot_number'] = $new_lot_number;
                    $applyStmt->execute($applyParams);
                }
                $targetLot = $applyStmt->fetch();

                if ($targetLot) {
                    $conn->prepare("UPDATE production_lots SET quantity_produced = quantity_produced + :added, item_id = :item_id
                        WHERE lot_id = :lot_id")
                        ->execute(['added' => $new_added_quantity, 'item_id' => $new_item_id, 'lot_id' => $targetLot['lot_id']]);
                } else {
                    $conn->prepare("INSERT INTO production_lots (po_id, poi_id, item_id, lot_number, quantity_produced, lot_date, created_by, `is_removed`)
                        VALUES (:po_id, :poi_id, :item_id, :lot_number, :qty, :lot_date, :created_by, 0)")
                        ->execute([
                            'po_id' => $history['po_id'],
                            'poi_id' => $history['poi_id'],
                            'item_id' => $new_item_id,
                            'lot_number' => $new_lot_number,
                            'qty' => $new_added_quantity,
                            'lot_date' => date('Y-m-d', strtotime($history['date_created'] ?? 'now')),
                            'created_by' => $edited_by
                        ]);
                }

                if ($history['poi_id']) {
                    $this->recalculateProducedQuantity($history['poi_id'], $conn);
                }
            }

            // Re-sync both lot groups from production history so quantities can never drift
            if ($new_item_id) {
                $this->reconcileLotGroup($new_item_id, $new_lot_number, $conn);
            }
            if (($lot_changed || $item_changed) && $old_item_id) {
                $this->reconcileLotGroup($old_item_id, $old_lot, $conn);
            }

            // Resolve any pending reports for this record so no old flags linger
            $conn->prepare("DELETE FROM production_reports WHERE history_id = :history_id")
                ->execute(['history_id' => $history_id]);

            // SOH cascade — when the item changed, revert the produced qty on the
            // OLD item's stock row and credit it to the NEW item's row (upsert)
            if ($item_changed) {
                $conn->prepare("UPDATE inventory_balances SET qty_on_hand = qty_on_hand - :qty
                    WHERE item_id = :item_id")
                    ->execute(['qty' => $old_added, 'item_id' => $old_item_id]);
                $conn->prepare("INSERT INTO inventory_balances (item_id, site_code, qty_on_hand)
                    VALUES (:item_id, :site, :qty)
                    ON DUPLICATE KEY UPDATE qty_on_hand = qty_on_hand + :qty2")
                    ->execute([
                        'item_id' => $new_item_id,
                        'site' => self::DEFAULT_SITE_CODE,
                        'qty' => $new_added_quantity,
                        'qty2' => $new_added_quantity,
                    ]);
            }

            $conn->commit();
            return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    private function getItemIdByPoi($poiId) {
        $stmt = self::getConnection()->prepare("SELECT item_id FROM purchase_order_items WHERE poi_id = :poi_id");
        $stmt->execute(['poi_id' => $poiId]);
        return $stmt->fetchColumn() ?: null;
    }

    public function reconcileLotGroup($item_id, $lot_number, $conn = null) {
        $owns = false;
        if (!$conn) {
            $conn = self::getConnection();
            $owns = true;
            $conn->beginTransaction();
        }
        try {
            $item_id = (int)$item_id;
            if (!$item_id || $lot_number === null || trim($lot_number) === '') {
                if ($owns) $conn->commit();
                return;
            }

            // Source of truth
            $histStmt = $conn->prepare("SELECT COALESCE(SUM(added_quantity), 0) FROM production_history
                WHERE `is_removed` = 0 AND item_id = :item_id AND lot_number = :lot_number");
            $histStmt->execute(['item_id' => $item_id, 'lot_number' => $lot_number]);
            $total = (int)$histStmt->fetchColumn();

            // Lock the group rows
            $rowsStmt = $conn->prepare("SELECT lot_id, quantity_produced FROM production_lots
                WHERE lot_number = :lot_number AND `is_removed` = 0
                AND (item_id = :item_id OR poi_id IN (SELECT poi_id FROM purchase_order_items WHERE item_id = :item_id2))
                ORDER BY date_created DESC, lot_id DESC
                FOR UPDATE");
            $rowsStmt->execute(['lot_number' => $lot_number, 'item_id' => $item_id, 'item_id2' => $item_id]);
            $rows = $rowsStmt->fetchAll();

            if (empty($rows)) {
                if ($owns) $conn->commit();
                return;
            }

            // Already consistent
            $currentTotal = array_sum(array_map(fn($r) => (int)$r['quantity_produced'], $rows));
            if (count($rows) === 1 && $currentTotal === $total) {
                if ($owns) $conn->commit();
                return;
            }

            // Holder: prefer a row referenced by deliveries/backloads, else the newest
            $holder = null;
            foreach ($rows as $row) {
                $lid = (int)$row['lot_id'];
                $refStmt = $conn->prepare("SELECT
                    (SELECT COUNT(*) FROM deliveries WHERE lot_id = :l1 AND `remove` = 0) +
                    (SELECT COUNT(*) FROM backloads WHERE lot_id = :l2 AND `remove` = 0) +
                    (SELECT COUNT(*) FROM deliveries WHERE lot_items LIKE CONCAT('%\"lot_id\":', :l3, '\"') AND `remove` = 0) AS refs");
                $refStmt->execute(['l1' => $lid, 'l2' => $lid, 'l3' => $lid]);
                if ((int)$refStmt->fetchColumn() > 0) { $holder = $row; break; }
            }
            if ($holder === null) $holder = $rows[0];

            $conn->prepare("UPDATE production_lots SET quantity_produced = :qty WHERE lot_id = :lot_id")
                ->execute(['qty' => $total, 'lot_id' => $holder['lot_id']]);

            foreach ($rows as $row) {
                if ((int)$row['lot_id'] === (int)$holder['lot_id']) continue;
                $lid = (int)$row['lot_id'];
                $refStmt = $conn->prepare("SELECT
                    (SELECT COUNT(*) FROM deliveries WHERE lot_id = :l1 AND `remove` = 0) +
                    (SELECT COUNT(*) FROM backloads WHERE lot_id = :l2 AND `remove` = 0) +
                    (SELECT COUNT(*) FROM deliveries WHERE lot_items LIKE CONCAT('%\"lot_id\":', :l3, '\"') AND `remove` = 0) AS refs");
                $refStmt->execute(['l1' => $lid, 'l2' => $lid, 'l3' => $lid]);
                if ((int)$refStmt->fetchColumn() === 0) {
                    $conn->prepare("DELETE FROM production_lots WHERE lot_id = :lot_id")
                        ->execute(['lot_id' => $lid]);
                } else {
                    $conn->prepare("UPDATE production_lots SET quantity_produced = 0 WHERE lot_id = :lot_id")
                        ->execute(['lot_id' => $lid]);
                }
            }

            if ($owns) $conn->commit();
        } catch (\Exception $e) {
            if ($owns && $conn->inTransaction()) $conn->rollBack();
            throw $e;
        }
    }

    // Consolidated available lots for a single item (used by the Admin DR edit modal).
    // Availability excludes the delivery being edited so the current lot can be re-picked.
    public function getLotChoicesForDeliveryEdit($item_id, $delivery_id = null) {
        $conn = self::getConnection();
        $sql = "SELECT pl.lot_id, pl.lot_number, pl.pcs_per_case, pl.quantity_produced, pl.lot_date,
                       COALESCE(i2.item_id, i.item_id) as item_id,
                       COALESCE(i2.item_code, i.item_code) as item_code,
                       COALESCE(i2.item_description, i.item_description) as item_description,
                       i.uom_conversion
                FROM production_lots pl
                LEFT JOIN purchase_order_items poi ON pl.poi_id = poi.poi_id
                LEFT JOIN items i ON pl.item_id = i.item_id
                LEFT JOIN items i2 ON poi.item_id = i2.item_id
                WHERE pl.`is_removed` = 0 AND COALESCE(i2.item_id, i.item_id) = :item_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['item_id' => $item_id]);
        $rows = $stmt->fetchAll();
        if (empty($rows)) return [];

        $groups = [];
        foreach ($rows as $row) {
            $key = $row['lot_number'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'lot_id' => intval($row['lot_id']),
                    'lot_ids' => [intval($row['lot_id'])],
                    'item_id' => intval($row['item_id']),
                    'item_code' => $row['item_code'],
                    'item_description' => $row['item_description'],
                    'lot_number' => $row['lot_number'],
                    'pcs_per_case' => $row['pcs_per_case'] !== null ? intval($row['pcs_per_case']) : null,
                    'uom_conversion' => $row['uom_conversion'] !== null ? intval($row['uom_conversion']) : null,
                    'quantity_produced' => 0,
                    'lot_date' => $row['lot_date'],
                ];
            }
            $groups[$key]['quantity_produced'] += intval($row['quantity_produced']);
            $groups[$key]['lot_ids'][] = intval($row['lot_id']);
            if ($row['lot_date'] && ($groups[$key]['lot_date'] === null || strtotime($row['lot_date']) < strtotime($groups[$key]['lot_date']))) {
                $groups[$key]['lot_date'] = $row['lot_date'];
            }
        }

        // Consumption from OTHER deliveries keyed by (item_code, lot_number) — the
        // immutable lot identity — so delivered counting cannot drift from lot_id churn
        $deliveredStmt = $conn->prepare("SELECT lot_items FROM deliveries WHERE `remove` = 0 AND delivery_id != :delivery_id AND lot_items IS NOT NULL");
        $deliveredStmt->execute(['delivery_id' => $delivery_id ?: 0]);
        $consumed = [];
        while ($r = $deliveredStmt->fetch()) {
            $items = json_decode($r['lot_items'], true);
            if (!is_array($items)) continue;
            foreach ($items as $li) {
                $k = ($li['item_code'] ?? '') . '|' . ($li['lot_number'] ?? '');
                $consumed[$k] = ($consumed[$k] ?? 0) + intval($li['qty'] ?? 0);
            }
        }

        $blStmt = $conn->prepare("SELECT lot_id, SUM(quantity) q FROM backloads WHERE `remove` = 0 GROUP BY lot_id");
        $blStmt->execute();
        $backloaded = [];
        while ($b = $blStmt->fetch()) $backloaded[intval($b['lot_id'])] = intval($b['q']);

        $result = [];
        foreach ($groups as $key => &$group) {
            $consumedKey = ($group['item_code'] ?? '') . '|' . $group['lot_number'];
            $consumedQty = $consumed[$consumedKey] ?? 0;
            $backQty = 0;
            foreach ($group['lot_ids'] as $lid) $backQty += $backloaded[$lid] ?? 0;
            $group['quantity_delivered'] = $consumedQty;
            $group['available_quantity'] = max(0, $group['quantity_produced'] - $consumedQty + $backQty);
            $result[] = $group;
        }
        unset($group);

        usort($result, function($a, $b) {
            $d = strtotime($b['lot_date'] ?? '') <=> strtotime($a['lot_date'] ?? '');
            return $d !== 0 ? $d : strcmp($a['lot_number'], $b['lot_number']);
        });
        return $result;
    }

    public function getWeeklyDeliveryStats($customerId = null, $weekOffset = 0) {
        $startOffset = $weekOffset * 12;
        $sql = "SELECT 
                    YEARWEEK(d.delivery_date, 1) as year_week,
                    COUNT(*) as delivery_count,
                    COALESCE(SUM(CASE 
                        WHEN i.uom_conversion IS NOT NULL AND i.uom_conversion > 0 
                        THEN FLOOR(d.delivery_quantity / i.uom_conversion) 
                        ELSE d.delivery_quantity 
                    END), 0) as total_cases
                FROM deliveries d
                LEFT JOIN purchase_order_items poi ON d.poi_id = poi.poi_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                WHERE d.`remove` = 0
                  AND d.delivery_date >= DATE_SUB(CURDATE(), INTERVAL " . ($startOffset + 24) . " WEEK)";
        $params = [];

        if ($customerId) {
            $sql .= " AND d.po_id IN (SELECT po_id FROM purchase_orders WHERE customer_id = :customer_id)";
            $params['customer_id'] = $customerId;
        }

        $sql .= " GROUP BY year_week ORDER BY year_week ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getCustomersWithDeliveries() {
        $sql = "SELECT DISTINCT c.customer_id, c.customer_name
                FROM customers c
                INNER JOIN purchase_orders po ON c.customer_id = po.customer_id
                INNER JOIN deliveries d ON po.po_id = d.po_id
                WHERE d.`remove` = 0
                ORDER BY c.customer_name";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getDeliveryDetailsForWeek($yearWeek, $customerId = null) {
        $sql = "SELECT 
                    d.delivery_id,
                    d.delivery_date,
                    d.dr_number,
                    d.delivery_quantity,
                    d.lot_items,
                    po.customer_po_number,
                    c.customer_name,
                    i.item_description,
                    i.item_uom,
                    i.uom_conversion,
                    u.full_name as delivered_by_name
                FROM deliveries d
                LEFT JOIN purchase_orders po ON d.po_id = po.po_id
                LEFT JOIN customers c ON po.customer_id = c.customer_id
                LEFT JOIN purchase_order_items poi ON d.poi_id = poi.poi_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                LEFT JOIN users u ON d.delivered_by = u.user_id
                WHERE d.`remove` = 0
                  AND YEARWEEK(d.delivery_date, 1) = :year_week";
        $params = ['year_week' => $yearWeek];

        if ($customerId) {
            $sql .= " AND d.po_id IN (SELECT po_id FROM purchase_orders WHERE customer_id = :customer_id)";
            $params['customer_id'] = $customerId;
        }

        $sql .= " ORDER BY d.delivery_date DESC, d.delivery_id DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $lotItems = json_decode($row['lot_items'], true);
            if (is_array($lotItems) && count($lotItems) > 0) {
                $totalCases = 0;
                foreach ($lotItems as $li) {
                    $qty = intval($li['qty'] ?? 0);
                    $conv = !empty($li['actual_uom_conversion']) ? intval($li['actual_uom_conversion']) : 0;
                    if ($conv > 0) {
                        $totalCases += floor($qty / $conv);
                    } else {
                        $itemUom = $row['uom_conversion'] ?? 0;
                        $totalCases += $itemUom > 0 ? floor($qty / $itemUom) : 0;
                    }
                }
                $row['cases_delivered'] = $totalCases;
            } else {
                $conv = $row['uom_conversion'] ?? 0;
                $row['cases_delivered'] = ($conv > 0) ? floor($row['delivery_quantity'] / $conv) : $row['delivery_quantity'];
            }
        }
        return $rows;
    }

    public function getPoItemSummary($customerId = null) {
        $sql = "SELECT 
                    poi.poi_id,
                    poi.po_id,
                    poi.quantity as po_qty,
                    poi.produced_quantity,
                    poi.delivered_quantity,
                    poi.unit_price,
                    po.customer_po_number,
                    po.date_created as po_date,
                    c.customer_name,
                    i.item_code,
                    i.item_description,
                    i.item_uom,
                    i.uom_conversion
                FROM purchase_order_items poi
                LEFT JOIN purchase_orders po ON poi.po_id = po.po_id
                LEFT JOIN customers c ON po.customer_id = c.customer_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                WHERE poi.quantity > 0";
        $params = [];

        if ($customerId) {
            $sql .= " AND po.customer_id = :customer_id";
            $params['customer_id'] = $customerId;
        }

        $sql .= " ORDER BY c.customer_name ASC, po.customer_po_number ASC, i.item_description ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return $rows;
    }

    public function getUniqueItemsForLots() {
        $sql = "SELECT DISTINCT i.item_id, i.item_code, i.item_description
                FROM production_lots pl
                LEFT JOIN purchase_order_items poi ON pl.poi_id = poi.poi_id
            LEFT JOIN items i ON COALESCE(poi.item_id, pl.item_id) = i.item_id
                WHERE pl.is_removed = 0
                  AND i.item_id IS NOT NULL
                ORDER BY i.item_description ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getLotsByItem($itemId) {
        // Fetch PO-linked lots for this item
        $poLotsSql = "SELECT 
                    pl.lot_number,
                    pl.po_id,
                    MIN(pl.poi_id) as poi_id,
                    MIN(pl.lot_date) as lot_date,
                    MIN(pl.created_by) as created_by,
                    SUM(pl.quantity_produced) as quantity_produced,
                    po.customer_po_number,
                    c.customer_name,
                    MIN(u.full_name) as created_by_name,
                    i.uom_conversion,
                    i.item_description,
                    i.item_code
                FROM production_lots pl
                LEFT JOIN purchase_order_items poi ON pl.poi_id = poi.poi_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                LEFT JOIN purchase_orders po ON pl.po_id = po.po_id
                LEFT JOIN customers c ON po.customer_id = c.customer_id
                LEFT JOIN users u ON pl.created_by = u.user_id
                WHERE pl.is_removed = 0
                  AND pl.poi_id IS NOT NULL
                  AND pl.item_id = :item_id
                GROUP BY pl.lot_number, pl.po_id, po.customer_po_number, c.customer_name, i.uom_conversion, i.item_description, i.item_code";
        $stmt = self::getConnection()->prepare($poLotsSql);
        $stmt->execute(['item_id' => $itemId]);
        $poLots = $stmt->fetchAll();

        // Fetch independent lots (poi_id IS NULL) for this item
        $indLotsSql = "SELECT 
                    pl.lot_number,
                    pl.po_id,
                    MIN(pl.poi_id) as poi_id,
                    MIN(pl.lot_date) as lot_date,
                    MIN(pl.created_by) as created_by,
                    SUM(pl.quantity_produced) as quantity_produced,
                    NULL as customer_po_number,
                    NULL as customer_name,
                    MIN(u.full_name) as created_by_name,
                    i.uom_conversion,
                    i.item_description,
                    i.item_code
                FROM production_lots pl
                LEFT JOIN items i ON pl.item_id = i.item_id
                LEFT JOIN users u ON pl.created_by = u.user_id
                WHERE pl.is_removed = 0
                  AND pl.poi_id IS NULL
                  AND pl.item_id = :item_id
                GROUP BY pl.lot_number, i.uom_conversion, i.item_description, i.item_code";
        $stmt2 = self::getConnection()->prepare($indLotsSql);
        $stmt2->execute(['item_id' => $itemId]);
        $indLots = $stmt2->fetchAll();

        $lots = array_merge($poLots, $indLots);

        $allDelivered = $this->getLotDeliveries();
        $allLotIds = $this->getLotIdsByItem($itemId);

        $backloadStmt = self::getConnection()->prepare(
            "SELECT lot_id, SUM(quantity) as total_backloaded FROM backloads WHERE `remove` = 0 GROUP BY lot_id"
        );
        $backloadStmt->execute();
        $allBackloaded = [];
        while ($bl = $backloadStmt->fetch()) {
            $allBackloaded[intval($bl['lot_id'])] = intval($bl['total_backloaded']);
        }

        $deliveredByKey = [];
        foreach ($allLotIds as $lid) {
            $key = $lid['lot_number'] . '_' . $lid['po_id'] . '_' . $lid['item_id'];
            $deliveredByKey[$key] = ($deliveredByKey[$key] ?? 0) + ($allDelivered[$lid['lot_id']] ?? 0);
        }
        foreach ($lots as &$lot) {
            $key = $lot['lot_number'] . '_' . $lot['po_id'] . '_' . $itemId;
            $lot['quantity_delivered'] = $deliveredByKey[$key] ?? 0;
            $lotBackloaded = 0;
            foreach ($allLotIds as $lid) {
                if ($lid['lot_number'] === $lot['lot_number'] && $lid['po_id'] == $lot['po_id'] && $lid['item_id'] == $itemId) {
                    $lotBackloaded += $allBackloaded[$lid['lot_id']] ?? 0;
                }
            }
            $lot['quantity_backloaded'] = $lotBackloaded;
        }
        return $lots;
    }

    public function getAllLotsStockOnHand() {
        // Fetch PO-linked lots (with poi_id set)
        $poLotsSql = "SELECT 
                    pl.lot_number,
                    pl.po_id,
                    poi.item_id,
                    MIN(pl.lot_id) as lot_id,
                    MIN(pl.poi_id) as poi_id,
                    MIN(pl.lot_date) as lot_date,
                    MIN(pl.created_by) as created_by,
                    SUM(pl.quantity_produced) as quantity_produced,
                    po.customer_po_number,
                    c.customer_name,
                    MIN(u.full_name) as created_by_name,
                    i.uom_conversion,
                    i.item_code,
                    i.item_description
                FROM production_lots pl
                LEFT JOIN purchase_order_items poi ON pl.poi_id = poi.poi_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                LEFT JOIN purchase_orders po ON pl.po_id = po.po_id
                LEFT JOIN customers c ON po.customer_id = c.customer_id
                LEFT JOIN users u ON pl.created_by = u.user_id
                WHERE pl.is_removed = 0 AND pl.poi_id IS NOT NULL
                GROUP BY pl.lot_number, pl.po_id, poi.item_id, po.customer_po_number, c.customer_name, i.uom_conversion, i.item_code, i.item_description
                ORDER BY MIN(pl.lot_id) DESC, i.item_description ASC";
        $stmt = self::getConnection()->prepare($poLotsSql);
        $stmt->execute();
        $poLots = $stmt->fetchAll();

        // Fetch independent lots (poi_id IS NULL, but item_id is set)
        $independentLotsSql = "SELECT 
                    pl.lot_number,
                    pl.po_id,
                    pl.item_id as item_id,
                    MIN(pl.lot_id) as lot_id,
                    MIN(pl.poi_id) as poi_id,
                    MIN(pl.lot_date) as lot_date,
                    MIN(pl.created_by) as created_by,
                    SUM(pl.quantity_produced) as quantity_produced,
                    NULL as customer_po_number,
                    NULL as customer_name,
                    MIN(u.full_name) as created_by_name,
                    i.uom_conversion,
                    i.item_code,
                    i.item_description
                FROM production_lots pl
                LEFT JOIN items i ON pl.item_id = i.item_id
                LEFT JOIN users u ON pl.created_by = u.user_id
                WHERE pl.is_removed = 0 AND pl.poi_id IS NULL
                GROUP BY pl.lot_number, pl.item_id, i.uom_conversion, i.item_code, i.item_description
                ORDER BY MIN(pl.lot_id) DESC, i.item_description ASC";
        $stmt2 = self::getConnection()->prepare($independentLotsSql);
        $stmt2->execute();
        $independentLots = $stmt2->fetchAll();

        $allLots = array_merge($poLots, $independentLots);

        $allDelivered = $this->getLotDeliveries();

        $backloadStmt = self::getConnection()->prepare(
            "SELECT lot_id, SUM(quantity) as total_backloaded FROM backloads WHERE `remove` = 0 GROUP BY lot_id"
        );
        $backloadStmt->execute();
        $allBackloaded = [];
        while ($bl = $backloadStmt->fetch()) {
            $allBackloaded[intval($bl['lot_id'])] = intval($bl['total_backloaded']);
        }

        $lotIdMap = self::getConnection()->prepare(
            "SELECT pl.lot_id, pl.lot_number, pl.po_id, pl.item_id
                FROM production_lots pl
                WHERE pl.is_removed = 0"
        );
        $lotIdMap->execute();
        $allLotIds = $lotIdMap->fetchAll();

        $deliveredByKey = [];
        foreach ($allLotIds as $lid) {
            $key = $lid['lot_number'] . '_' . $lid['po_id'] . '_' . $lid['item_id'];
            $deliveredByKey[$key] = ($deliveredByKey[$key] ?? 0) + ($allDelivered[intval($lid['lot_id'])] ?? 0);
        }

        foreach ($allLots as &$lot) {
            $key = $lot['lot_number'] . '_' . $lot['po_id'] . '_' . $lot['item_id'];
            $lot['quantity_delivered'] = $deliveredByKey[$key] ?? 0;
            $lotBackloaded = 0;
            foreach ($allLotIds as $lid) {
                if ($lid['lot_number'] === $lot['lot_number'] && $lid['po_id'] == $lot['po_id'] && $lid['item_id'] == $lot['item_id']) {
                    $lotBackloaded += $allBackloaded[intval($lid['lot_id'])] ?? 0;
                }
            }
            $lot['quantity_backloaded'] = $lotBackloaded;
        }
        return $allLots;
    }

    private function getLotIdsByItem($itemId) {
        $sql = "SELECT pl.lot_id, pl.lot_number, pl.po_id, pl.item_id
                FROM production_lots pl
                WHERE pl.is_removed = 0 AND pl.item_id = :item_id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['item_id' => $itemId]);
        return $stmt->fetchAll();
    }

    private function getLotDeliveries() {
        $result = [];

        $sql = "SELECT delivery_id, lot_items FROM deliveries WHERE remove = 0 AND lot_items IS NOT NULL";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $items = json_decode($row['lot_items'], true);
            if (!is_array($items)) continue;
            foreach ($items as $li) {
                $lotId = intval($li['lot_id']);
                $qty = intval($li['qty'] ?? 0);
                if ($lotId > 0) {
                    $result[$lotId] = ($result[$lotId] ?? 0) + $qty;
                }
            }
        }

        $sql2 = "SELECT lot_id, delivery_quantity FROM deliveries WHERE remove = 0 AND lot_items IS NULL AND lot_id IS NOT NULL AND delivery_quantity > 0";
        $stmt2 = self::getConnection()->prepare($sql2);
        $stmt2->execute();
        $rows2 = $stmt2->fetchAll();
        foreach ($rows2 as $row) {
            $lotId = intval($row['lot_id']);
            $qty = intval($row['delivery_quantity']);
            if ($lotId > 0) {
                $result[$lotId] = ($result[$lotId] ?? 0) + $qty;
            }
        }

        return $result;
    }

    public function getDeliveryReportData($filters = []) {
        $sql = "SELECT d.delivery_id, d.dr_number, d.si_number, d.plate_number, d.vehicle_type, d.logistic_provider,
                       d.delivery_date, d.delivery_quantity,
                       d.lot_items, d.remarks, d.report_remarks, d.date_created,
                       po.customer_po_number, po.customer_po_date, po.total_quantity, po.production_type,
                       c.customer_name,
                       poi.quantity as item_quantity, poi.poi_id,
                       i.item_code, i.item_description, COALESCE(poi.item_uom, i.item_uom) as item_uom,
                       pl.lot_number,
                       u.full_name as delivered_by_name
                FROM deliveries d
                LEFT JOIN purchase_orders po ON d.po_id = po.po_id
                LEFT JOIN customers c ON po.customer_id = c.customer_id
                LEFT JOIN purchase_order_items poi ON d.poi_id = poi.poi_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                LEFT JOIN production_lots pl ON d.lot_id = pl.lot_id
                LEFT JOIN users u ON d.delivered_by = u.user_id
                WHERE d.`remove` = 0";
        $params = [];

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $sql .= " AND (d.dr_number LIKE :s1 OR d.si_number LIKE :s2
                       OR po.customer_po_number LIKE :s3 OR c.customer_name LIKE :s4
                       OR i.item_description LIKE :s5 OR pl.lot_number LIKE :s6
                       OR u.full_name LIKE :s7 OR d.remarks LIKE :s8)";
            $params['s1'] = $like;
            $params['s2'] = $like;
            $params['s3'] = $like;
            $params['s4'] = $like;
            $params['s5'] = $like;
            $params['s6'] = $like;
            $params['s7'] = $like;
            $params['s8'] = $like;
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND d.delivery_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND d.delivery_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['customer_id'])) {
            $sql .= " AND po.customer_id = :customer_id";
            $params['customer_id'] = $filters['customer_id'];
        }

        $sql .= " ORDER BY d.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getDeliveryReportCustomers() {
        $sql = "SELECT DISTINCT c.customer_id, c.customer_name
                FROM deliveries d
                JOIN purchase_orders po ON d.po_id = po.po_id
                JOIN customers c ON po.customer_id = c.customer_id
                WHERE d.`remove` = 0
                ORDER BY c.customer_name ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getActivePOsByCustomer($customer_id) {
        $sql = "SELECT po.po_id, po.customer_po_number, po.date_created, po.production_type
                FROM purchase_orders po
                WHERE po.customer_id = :customer_id AND po.`remove` = 0
                ORDER BY po.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['customer_id' => $customer_id]);
        return $stmt->fetchAll();
    }

    public function getAllActivePOs() {
        $sql = "SELECT po.po_id, po.po_number, po.customer_po_number, c.customer_name
                FROM purchase_orders po
                JOIN customers c ON c.customer_id = po.customer_id
                WHERE po.`remove` = 0
                ORDER BY po.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // â”€â”€â”€ Backloads (delegated to BackloadModel) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function createBackload($data) {
        return $this->backloadModel->createBackload($data);
    }

    public function getBackloads($filters = []) {
        return $this->backloadModel->getBackloads($filters);
    }

    public function getBackloadsByDeliveryId($delivery_id) {
        return $this->backloadModel->getBackloadsByDeliveryId($delivery_id);
    }

    public function getDeliveryLotsForBackload($delivery_id) {
        return $this->backloadModel->getDeliveryLotsForBackload($delivery_id);
    }

    // ==================== INDEPENDENT FG PRODUCTION ====================

    public function createIndependentLot($data) {
        $sql = "INSERT INTO production_lots (po_id, poi_id, item_id, lot_number, quantity_produced, pcs_per_case, lot_date, created_by)
                VALUES (NULL, NULL, :item_id, :lot_number, :quantity_produced, :pcs_per_case, :lot_date, :created_by)";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'item_id' => $data['item_id'] ?? null,
            'lot_number' => $data['lot_number'],
            'quantity_produced' => $data['quantity_produced'] ?? 0,
            'pcs_per_case' => $data['pcs_per_case'] ?? null,
            'lot_date' => $data['lot_date'] ?? date('Y-m-d'),
            'created_by' => $data['created_by'] ?? null
        ]);
        return self::getConnection()->lastInsertId();
    }

    public function getLotByItemAndLotNumber($item_id, $lot_number) {
        $sql = "SELECT lot_id, quantity_produced, pcs_per_case FROM production_lots
                WHERE item_id = :item_id AND lot_number = :lot_number AND `is_removed` = 0
                LIMIT 1";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['item_id' => $item_id, 'lot_number' => $lot_number]);
        return $stmt->fetch() ?: null;
    }

    public function undoProductionHistory($historyIds, $userId = null) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $ids = array_map('intval', $historyIds);
            if (empty($ids)) {
                $conn->rollBack();
                return false;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

                $userCondition = $userId !== null ? ' AND user_id = ?' : '';
                $queryParams = $ids;
                if ($userId !== null) {
                $queryParams[] = $userId;
                }
                $stmt = $conn->prepare("SELECT history_id, item_id, lot_number, added_quantity, date_created
                    FROM production_history
                    WHERE history_id IN ($placeholders) AND is_removed = 0
                    AND date_created >= NOW() - INTERVAL 5 MINUTE$userCondition");
                $stmt->execute($queryParams);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                $conn->rollBack();
                return false;
            }

            $validIds = array_column($rows, 'history_id');
            $validPlaceholders = implode(',', array_fill(0, count($validIds), '?'));
            $conn->prepare("UPDATE production_history SET is_removed = 1 WHERE history_id IN ($validPlaceholders)")
                ->execute($validIds);

            $lotDeductions = [];
            foreach ($rows as $row) {
                $key = $row['item_id'] . '_' . $row['lot_number'];
                if (!isset($lotDeductions[$key])) {
                    $lotDeductions[$key] = [
                        'item_id' => $row['item_id'],
                        'lot_number' => $row['lot_number'],
                        'total_deducted' => 0,
                    ];
                }
                $lotDeductions[$key]['total_deducted'] += intval($row['added_quantity']);
            }

            foreach ($lotDeductions as $deduction) {
                $conn->prepare("UPDATE production_lots SET quantity_produced = GREATEST(0, quantity_produced - :deducted)
                        WHERE item_id = :item_id AND lot_number = :lot_number AND is_removed = 0")
                    ->execute([
                        'deducted' => $deduction['total_deducted'],
                        'item_id' => $deduction['item_id'],
                        'lot_number' => $deduction['lot_number'],
                    ]);
            }

            // Purge lot rows emptied by this undo so they don't linger at 0 stock.
            // removeLotRow hard-deletes unreferenced rows and soft-deletes rows still
            // referenced by deliveries/backloads FKs or the deliveries.lot_items JSON.
            $emptyStmt = $conn->prepare("SELECT lot_id FROM production_lots
                    WHERE item_id = :item_id AND lot_number = :lot_number
                      AND quantity_produced <= 0 AND `is_removed` = 0");
            foreach ($lotDeductions as $deduction) {
                $emptyStmt->execute([
                    'item_id' => $deduction['item_id'],
                    'lot_number' => $deduction['lot_number'],
                ]);
                foreach ($emptyStmt->fetchAll() as $empty) {
                    $this->removeLotRow(intval($empty['lot_id']), $conn);
                }
            }

            $conn->commit();
            return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function upsertItemLot($data) {
        $conn = self::getConnection();
        $item_id = $data['item_id'];
        $lot_number = $data['lot_number'];
        $added_quantity = intval($data['quantity_produced'] ?? 0);
        $pcs_per_case = $data['pcs_per_case'] ?? null;

        $sql = "SELECT lot_id, quantity_produced, pcs_per_case FROM production_lots
                WHERE item_id = :item_id AND lot_number = :lot_number AND `is_removed` = 0
                FOR UPDATE";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['item_id' => $item_id, 'lot_number' => $lot_number]);
        $existing = $stmt->fetch();

        if ($existing) {
            $existingPcs = $existing['pcs_per_case'] !== null ? intval($existing['pcs_per_case']) : null;
            $newPcs = $pcs_per_case !== null ? intval($pcs_per_case) : null;
            if ($existingPcs === $newPcs) {
                $newQty = intval($existing['quantity_produced']) + $added_quantity;
                $upd = $conn->prepare("UPDATE production_lots SET quantity_produced = :qty WHERE lot_id = :lot_id");
                $upd->execute(['qty' => $newQty, 'lot_id' => $existing['lot_id']]);
                return $existing['lot_id'];
            } else {
                return $this->createIndependentLot([
                    'item_id' => $item_id,
                    'lot_number' => $lot_number,
                    'quantity_produced' => $added_quantity,
                    'pcs_per_case' => $pcs_per_case,
                    'created_by' => $data['created_by'] ?? null
                ]);
            }
        } else {
            return $this->createIndependentLot([
                'item_id' => $item_id,
                'lot_number' => $lot_number,
                'quantity_produced' => $added_quantity,
                'pcs_per_case' => $pcs_per_case,
                'created_by' => $data['created_by'] ?? null
            ]);
        }
    }

    public function getAllAvailableItemsForDelivery($po_id = null) {
        $conn = self::getConnection();
        $selectedPoId = $po_id !== null && $po_id !== '' ? intval($po_id) : null;

        $selectedPoMatch = $selectedPoId === null
            ? '1'
            : '(EXISTS (
                    SELECT 1 FROM purchase_order_items selected_poi
                    JOIN items sip ON sip.item_id = selected_poi.item_id
                    WHERE selected_poi.po_id = ?
                    AND (selected_poi.item_id = COALESCE(i2.item_id, i.item_id)
                         OR sip.item_code = COALESCE(i2.item_code, i.item_code))
                ))';
        $sql = "SELECT COALESCE(i2.item_id, i.item_id) as item_id,
                COALESCE(i2.item_code, i.item_code) as item_code,
                COALESCE(i2.item_description, i.item_description) as item_description,
                COALESCE(i2.item_uom, i.item_uom) as item_uom,
                COALESCE(i2.uom_conversion, i.uom_conversion) as uom_conversion,
                l.lot_id, l.lot_number, l.quantity_produced, l.pcs_per_case, l.lot_date, l.po_id, l.date_created,
                po.po_number, po.customer_po_number,
                CASE WHEN $selectedPoMatch THEN 1 ELSE 0 END AS in_selected_po
                FROM production_lots l
                LEFT JOIN items i ON l.item_id = i.item_id
                LEFT JOIN purchase_order_items poi ON l.poi_id = poi.poi_id
                LEFT JOIN items i2 ON poi.item_id = i2.item_id
                LEFT JOIN purchase_orders po ON l.po_id = po.po_id
                WHERE l.`is_removed` = 0
                AND (i.`remove` = 0 OR i2.`remove` = 0)
                AND COALESCE(i2.item_id, i.item_id) IS NOT NULL";

        $params = $selectedPoId === null ? [] : [$selectedPoId];

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $lots = $stmt->fetchAll();
        if (empty($lots)) return [];

        $deliveredStmt = $conn->prepare("SELECT delivery_id, lot_items FROM deliveries 
                WHERE lot_items IS NOT NULL AND `remove` = 0
                ORDER BY delivery_date ASC, delivery_id ASC");
        $deliveredStmt->execute();
        $jsonDelivered = [];
        while ($r = $deliveredStmt->fetch()) {
            $items = json_decode($r['lot_items'], true);
            if (!is_array($items)) continue;
            foreach ($items as $li) {
                if (isset($li['lot_id'])) {
                    $lid = intval($li['lot_id']);
                    $jsonDelivered[$lid] = ($jsonDelivered[$lid] ?? 0) + intval($li['qty'] ?? 0);
                }
            }
        }

        $jsonBackloaded = [];
        $blStmt = $conn->prepare("SELECT lot_id, quantity FROM backloads WHERE `remove` = 0");
        $blStmt->execute();
        while ($bl = $blStmt->fetch()) {
            $lid = intval($bl['lot_id']);
            $jsonBackloaded[$lid] = ($jsonBackloaded[$lid] ?? 0) + intval($bl['quantity']);
        }

        $grouped = [];
        foreach ($lots as $lot) {
            $lid = $lot['lot_id'];
            $available = max(0, $lot['quantity_produced'] - ($jsonDelivered[$lid] ?? 0) + ($jsonBackloaded[$lid] ?? 0));
            if ($available <= 0) continue;

            $iid = intval($lot['item_id']);
            $ln = $lot['lot_number'];
            $gkey = ($lot['item_code'] ?? '') !== '' ? strval($lot['item_code']) : strval($iid);
            if (!isset($grouped[$gkey])) {
                $grouped[$gkey] = [
                    'item_id' => $iid,
                    'item_code' => $lot['item_code'],
                    'item_description' => $lot['item_description'],
                    'item_uom' => $lot['item_uom'] ?? 'PCS',
                    'uom_conversion' => $lot['uom_conversion'] ?? null,
                    'lots' => [],
                ];
            } elseif ($iid < intval($grouped[$gkey]['item_id'])) {
                $grouped[$gkey]['item_id'] = $iid;
            }

            $key = $gkey . '_' . $ln;
            if (!isset($grouped[$gkey]['lots'][$key])) {
                $grouped[$gkey]['lots'][$key] = [
                    'lot_id' => $lid,
                    'lot_ids' => [$lid],
                    'lot_number' => $ln,
                    'available_quantity' => $available,
                    'po_id' => $lot['po_id'] ? intval($lot['po_id']) : null,
                    'po_number' => $lot['customer_po_number'] ?: $lot['po_number'] ?? null,
                    'pcs_per_case' => $lot['pcs_per_case'],
                    'lot_date' => $lot['lot_date'],
                    'date_created' => $lot['date_created'],
                    'uom_conversion' => $lot['uom_conversion'] ?? null,
                    'in_selected_po' => (int)($lot['in_selected_po'] ?? 1),
                    'sub_lots' => [['lot_id' => $lid, 'available' => $available]],
                ];
            } else {
                $gl = &$grouped[$gkey]['lots'][$key];
                $gl['lot_ids'][] = $lid;
                $gl['available_quantity'] += $available;
                $gl['in_selected_po'] = (int)((int)($gl['in_selected_po'] ?? 1) && (int)($lot['in_selected_po'] ?? 1));
                $gl['sub_lots'][] = ['lot_id' => $lid, 'available' => $available];
            }
        }

        $result = array_values($grouped);
        foreach ($result as &$item) {
            $item['lots'] = array_values($item['lots']);
            usort($item['lots'], function($a, $b) {
                return strtotime($a['date_created']) - strtotime($b['date_created']);
            });
        }
        unset($item);

        if ($selectedPoId !== null) {
            $poiStmt = $conn->prepare("SELECT poi.item_id, poi.quantity, poi.delivered_quantity, i.uom_conversion, i.item_code
                    FROM purchase_order_items poi
                    JOIN items i ON poi.item_id = i.item_id
                    WHERE poi.po_id = ?");
            $poiStmt->execute([$selectedPoId]);
            $poiFulfillment = [];
            $poiFulfillmentByCode = [];
            while ($row = $poiStmt->fetch()) {
                $info = [
                    'quantity' => intval($row['quantity']),
                    'delivered_quantity' => intval($row['delivered_quantity']),
                    'uom_conversion' => $row['uom_conversion'] ? intval($row['uom_conversion']) : null,
                ];
                $poiFulfillment[intval($row['item_id'])] = $info;
                $code = strval($row['item_code'] ?? '');
                if ($code !== '') {
                    if (!isset($poiFulfillmentByCode[$code])) {
                        $poiFulfillmentByCode[$code] = $info;
                    } else {
                        $poiFulfillmentByCode[$code]['quantity'] += $info['quantity'];
                        $poiFulfillmentByCode[$code]['delivered_quantity'] += $info['delivered_quantity'];
                    }
                }
            }
            foreach ($result as &$item) {
                $iid = intval($item['item_id']);
                $match = $poiFulfillmentByCode[strval($item['item_code'] ?? '')] ?? $poiFulfillment[$iid] ?? null;
                if ($match !== null) {
                    $item['po_quantity'] = $match['quantity'];
                    $item['po_delivered'] = $match['delivered_quantity'];
                    $item['po_uom_conversion'] = $match['uom_conversion'];
                }
            }
            unset($item);
        }

        usort($result, function($a, $b) { return strcmp($a['item_code'], $b['item_code']); });
        return $result;
    }

    public function getPOsContainingItem($item_id) {
        $sql = "SELECT po.po_id, po.customer_po_number, po.customer_po_date, po.production_type,
                       c.customer_name, poi.poi_id, poi.quantity, poi.produced_quantity, poi.delivered_quantity
                FROM purchase_order_items poi
                JOIN purchase_orders po ON poi.po_id = po.po_id
                JOIN customers c ON po.customer_id = c.customer_id
                WHERE poi.item_id = :item_id AND po.`remove` = 0 AND poi.`remove` = 0
                AND po.status != 'rejected'
                ORDER BY po.customer_po_number ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['item_id' => $item_id]);
        return $stmt->fetchAll();
    }

    public function recalculateProducedQuantityFromDelivery($poiId, $conn = null) {
        $ownsConn = false;
        if (!$conn) {
            $conn = self::getConnection();
            $ownsConn = true;
        }

        $prodStmt = $conn->prepare("SELECT COALESCE(SUM(quantity_produced), 0) FROM production_lots WHERE poi_id = :poi_id AND is_removed = 0");
        $prodStmt->execute(['poi_id' => $poiId]);
        $produced = intval($prodStmt->fetchColumn());

        $conn->prepare("UPDATE purchase_order_items SET produced_quantity = :produced WHERE poi_id = :poi_id")
            ->execute(['produced' => $produced, 'poi_id' => $poiId]);

        $poStmt = $conn->prepare("SELECT po_id FROM purchase_order_items WHERE poi_id = :poi_id");
        $poStmt->execute(['poi_id' => $poiId]);
        $poId = $poStmt->fetchColumn();

        if ($poId) {
            $conn->prepare("UPDATE purchase_orders SET produced_quantity = (
                SELECT COALESCE(SUM(produced_quantity), 0) FROM purchase_order_items WHERE po_id = :po_id
            ) WHERE po_id = :po_id2")
                ->execute(['po_id' => $poId, 'po_id2' => $poId]);
        }

        return $produced;
    }

    // ==================== FG INVENTORY ====================

    public function getFGInventory($filters = []) {
        $conn = self::getConnection();

        $sql = "SELECT 
                    i.item_id, i.item_code, i.item_description, i.item_uom, i.uom_conversion,
                    COALESCE(SUM(pl.quantity_produced), 0) as total_produced,
                    COUNT(pl.lot_id) as total_lots,
                    MAX(pl.last_update) as last_updated
                FROM items i
                LEFT JOIN production_lots pl ON pl.item_id = i.item_id AND pl.is_removed = 0
                WHERE i.`remove` = 0 AND i.status = 1
                GROUP BY i.item_id, i.item_code, i.item_description, i.item_uom, i.uom_conversion
                HAVING total_produced > 0";

        $params = [];

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $sql .= " AND (i.item_code LIKE :search1 OR i.item_description LIKE :search2)";
            $params['search1'] = $like;
            $params['search2'] = $like;
        }

        $sql .= " ORDER BY MAX(pl.last_update) DESC, i.item_code ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if (empty($rows)) return [];

        $itemIds = array_column($rows, 'item_id');
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

        $lotStmt = $conn->prepare("SELECT lot_id, item_id, quantity_produced FROM production_lots WHERE item_id IN ($placeholders) AND is_removed = 0");
        $lotStmt->execute($itemIds);
        $allLotIds = [];
        while ($r = $lotStmt->fetch()) {
            $allLotIds[intval($r['lot_id'])] = ['item_id' => intval($r['item_id']), 'qty' => intval($r['quantity_produced'])];
        }

        $allDelivered = $this->getLotDeliveries();

        $backloadStmt = $conn->prepare("SELECT lot_id, SUM(quantity) as total_backloaded FROM backloads WHERE `remove` = 0 GROUP BY lot_id");
        $backloadStmt->execute();
        $allBackloaded = [];
        while ($bl = $backloadStmt->fetch()) {
            $allBackloaded[intval($bl['lot_id'])] = intval($bl['total_backloaded']);
        }

        $itemDelivered = [];
        $itemBackloaded = [];
        foreach ($allLotIds as $lid => $lotInfo) {
            $iid = $lotInfo['item_id'];
            $itemDelivered[$iid] = ($itemDelivered[$iid] ?? 0) + ($allDelivered[$lid] ?? 0);
            $itemBackloaded[$iid] = ($itemBackloaded[$iid] ?? 0) + ($allBackloaded[$lid] ?? 0);
        }

        foreach ($rows as &$row) {
            $iid = $row['item_id'];
            $delivered = $itemDelivered[$iid] ?? 0;
            $backloaded = $itemBackloaded[$iid] ?? 0;
            $row['total_delivered'] = $delivered;
            $row['available_stock'] = max(0, intval($row['total_produced']) - $delivered + $backloaded);
        }
        unset($row);

        return $rows;
    }

    public function getLotsByItemForInventory($item_id) {
        $conn = self::getConnection();

        $sql = "SELECT pl.lot_id, pl.lot_number, pl.pcs_per_case, pl.quantity_produced,
                       pl.lot_date, pl.created_by, pl.item_id,
                       u.full_name as created_by_name
                FROM production_lots pl
                LEFT JOIN users u ON pl.created_by = u.user_id
                WHERE pl.item_id = :item_id AND pl.is_removed = 0
                ORDER BY pl.last_update DESC, pl.date_created DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['item_id' => $item_id]);
        $lots = $stmt->fetchAll();
        if (empty($lots)) return [];

        $allDelivered = $this->getLotDeliveries();

        $blStmt = $conn->prepare("SELECT lot_id, SUM(quantity) as total_backloaded FROM backloads WHERE `remove` = 0 GROUP BY lot_id");
        $blStmt->execute();
        $allBackloaded = [];
        while ($bl = $blStmt->fetch()) {
            $allBackloaded[intval($bl['lot_id'])] = intval($bl['total_backloaded']);
        }

        foreach ($lots as &$lot) {
            $lid = intval($lot['lot_id']);
            $delivered = $allDelivered[$lid] ?? 0;
            $backloaded = $allBackloaded[$lid] ?? 0;
            $lot['quantity_delivered'] = $delivered;
            $lot['available_balance'] = max(0, intval($lot['quantity_produced']) - $delivered + $backloaded);
        }
        unset($lot);

        return $lots;
    }

public function searchItems($query) {
        return $this->catalogModel->searchItems($query);
    }

    // â”€â”€â”€ MRP Methods â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getCustomersWithOpenPOs() {
        $sql = "SELECT DISTINCT c.customer_id, c.customer_code, c.customer_name
                FROM customers c
                JOIN purchase_orders po ON c.customer_id = po.customer_id
                WHERE c.`remove` = 0 AND c.status = 1
                  AND po.delivered_quantity < po.total_quantity
                  AND po.`remove` = 0
                ORDER BY c.customer_code ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getActiveCustomersForMrp() {
        $sql = "SELECT DISTINCT c.customer_id, c.customer_code, c.customer_name
                FROM customers c
                INNER JOIN fg_boms b ON b.customer_id = c.customer_id
                WHERE c.`remove` = 0 AND c.status = 1
                  AND b.status = 'active'
                  AND EXISTS (
                        SELECT 1 FROM items i
                        WHERE i.item_id = b.fg_item_id
                          AND i.`remove` = 0 AND i.status = 1
                          AND i.item_type IN ('FG', 'SFG')
                  )
                ORDER BY c.customer_name ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * FGs and SFGs with an active BOM, optionally filtered to the customer
     * assigned on the BOM header (fg_boms.customer_id).
     *
     * An FG may carry several active BOMs (multi-formulation), so the join is
     * pinned to one row per item â€” the default BOM when flagged, otherwise the
     * oldest active one â€” otherwise the dropdown would list the FG twice.
     */
    public function getFgsWithBomByCustomer($customerId = null) {
        // Pick one BOM per FG inside the same filter as the outer query, so a
        // customer-filtered view still resolves to a BOM of that customer.
        $pickWhere = "status = 'active'";
        $params = [];
        if (!empty($customerId)) {
            $pickWhere .= " AND customer_id = :cid";
            $params['cid'] = $customerId;
        }
        $sql = "SELECT i.item_id, i.item_code, i.item_description, i.item_uom, i.customer_id,
                       b.id AS bom_id, b.bom_code,
                       b.batch_qty AS fill_volume, b.batch_uom AS uom,
                       b.batch_unit_divisor, b.is_legacy_formula
                FROM items i
                INNER JOIN fg_boms b ON b.fg_item_id = i.item_id
                INNER JOIN (
                    SELECT fg_item_id,
                           SUBSTRING_INDEX(GROUP_CONCAT(id ORDER BY is_default DESC, id ASC), ',', 1) AS pick_id
                    FROM fg_boms
                    WHERE {$pickWhere}
                    GROUP BY fg_item_id
                ) pick ON pick.pick_id = b.id AND pick.fg_item_id = i.item_id
                WHERE i.`remove` = 0 AND i.status = 1
                  AND i.item_type IN ('FG', 'SFG')
                  AND b.status = 'active'";
        if (!empty($customerId)) {
            $sql .= " AND b.customer_id = :cid2";
            $params['cid2'] = $customerId;
        }
        $sql .= " ORDER BY i.item_code ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Active BOM header + FG identity for one finished good.
     *
     * $bomId pins the run to a specific formulation (Switch BOM); it is only
     * honoured when it belongs to this FG and is active. Without it the BOM
     * of the selected customer (when given) wins first, then the default BOM
     * (fg_boms.is_default), falling back to the oldest active BOM.
     */
    public function getBomForFg($fgItemId, $bomId = null, $customerId = null) {
        $select = "SELECT b.id AS bom_id, b.bom_code,
                       b.batch_qty AS fill_volume, b.batch_uom AS uom,
                       b.batch_unit_divisor, b.is_legacy_formula, b.is_default,
                       b.customer_id AS bom_customer_id,
                       i.item_id AS fg_item_id, i.item_code AS fg_code,
                       i.item_description AS fg_name, i.item_uom,
                       i.customer_id
                FROM fg_boms b
                INNER JOIN items i ON i.item_id = b.fg_item_id
                WHERE b.fg_item_id = :fg_item_id
                  AND b.status = 'active'
                  AND i.`remove` = 0 AND i.status = 1";
        $params = ['fg_item_id' => $fgItemId];

        // Explicit choice (Switch BOM): honoured only while it still belongs to
        // this FG. A stale id (another tab, back button, deleted BOM) must not
        // blank the calculation â€” it falls through to the default resolution.
        if (!empty($bomId)) {
            $stmt = self::getConnection()->prepare($select . " AND b.id = :bom_id");
            $params['bom_id'] = intval($bomId);
            $stmt->execute($params);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
            unset($params['bom_id']);
        }

        if (!empty($customerId)) {
            $sql = $select . " ORDER BY (b.customer_id = :cid) DESC, b.is_default DESC, b.id ASC
                    LIMIT 1";
            $params['cid'] = intval($customerId);
        } else {
            $sql = $select . " ORDER BY b.is_default DESC, b.id ASC
                    LIMIT 1";
        }
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: false;
    }

    /**
     * All active BOMs for one FG/SFG â€” powers the MRP Sheet "Switch BOM" list.
     * Ordered exactly like getBomForFg() resolves them (customer match first,
     * then the default formulation), so boms[0] is what Calculate would use
     * when no BOM is explicitly chosen.
     */
    public function getItemBoms($fgItemId, $customerId = null) {
        $sql = "SELECT b.id AS bom_id, b.bom_code,
                       b.batch_qty AS fill_volume, b.batch_uom AS uom,
                       b.batch_unit_divisor, b.is_default, b.status, b.bom_type,
                       b.created_at, b.customer_id,
                       i.item_code AS fg_code, i.item_description AS fg_name,
                       i.item_uom,
                       (SELECT COUNT(*) FROM fg_bom_items bi WHERE bi.bom_id = b.id) AS component_count
                FROM fg_boms b
                INNER JOIN items i ON i.item_id = b.fg_item_id
                WHERE b.fg_item_id = :fg_item_id
                  AND b.status = 'active'
                  AND i.`remove` = 0 AND i.status = 1";
        $params = ['fg_item_id' => intval($fgItemId)];
        if (!empty($customerId)) {
            $sql .= " ORDER BY (b.customer_id = :cid) DESC, b.is_default DESC, b.id ASC";
            $params['cid'] = intval($customerId);
        } else {
            $sql .= " ORDER BY b.is_default DESC, b.id ASC";
        }
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getOpenPOsByCustomer($customer_id) {
        $sql = "SELECT po.*, c.customer_name
                FROM purchase_orders po
                JOIN customers c ON po.customer_id = c.customer_id
                WHERE po.customer_id = :customer_id
                  AND po.delivered_quantity < po.total_quantity
                  AND po.`remove` = 0
                  AND po.status NOT IN ('delivered', 'rejected')
                  AND po.completed_at IS NULL
                ORDER BY po.customer_po_date DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['customer_id' => $customer_id]);
        return $stmt->fetchAll();
    }

    public function getPOItemsWithBOM($po_id) {
        $sql = "SELECT poi.poi_id, poi.quantity, poi.item_uom, poi.item_id,
                       i.item_code, i.item_description,
                       b.id AS bom_id, b.bom_code, b.batch_qty, b.batch_uom,
                       b.batch_qty AS fill_volume, b.batch_uom AS uom,
                       b.batch_unit_divisor, b.is_legacy_formula, b.is_default
                FROM purchase_order_items poi
                JOIN items i ON poi.item_id = i.item_id AND i.`remove` = 0
                LEFT JOIN fg_boms b ON b.fg_item_id = poi.item_id
                WHERE poi.po_id = :po_id
                ORDER BY i.item_code ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['po_id' => $po_id]);
        return $stmt->fetchAll();
    }

    /**
     * Physical SOH/allocated per BOM component, summed across ALL sites.
     *
     * Single source of truth shared by the LMR Entry modal, MRP sheet, LMR
     * release validation and LMR printing, so every screen reports the exact
     * same figure regardless of which site row QC/receiving wrote stock to.
     * view_inventory_status is COALESCE(SUM(qty_on_hand),0) per item over
     * inventory_balances â€” equivalent to the raw COALESCE(SUM(...)) query.
     */
    public function getBOMComponentsWithStock($bom_ids) {
        if (empty($bom_ids)) return [];
        $placeholders = implode(',', array_fill(0, count($bom_ids), '?'));

        $sql = "SELECT bi.bom_id, bi.item_id AS component_item_id, bi.dosage_rate, bi.wastage_allowance_pct,
                       bi.phase_code, i.item_type,
                       i.item_code, i.item_description, i.item_uom,
                       COALESCE(NULLIF(bi.uom, ''), i.item_uom) AS display_uom,
                       COALESCE(v.total_soh, 0) AS soh,
                       COALESCE(v.total_allocated, 0) AS allocated,
                       COALESCE(v.available_stock, 0) AS available_stock
                FROM fg_bom_items bi
                JOIN items i ON bi.item_id = i.item_id AND i.`remove` = 0
                LEFT JOIN view_inventory_status v ON v.item_id = bi.item_id
                WHERE bi.bom_id IN ({$placeholders})
                ORDER BY bi.bom_id, bi.phase_code ASC, bi.id ASC";

        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($bom_ids);
        return $stmt->fetchAll();
    }

    public function getPendingAllocationsAcrossPOs($exclude_poi_ids, $ingredient_item_ids) {
        if (empty($ingredient_item_ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ingredient_item_ids), '?'));
        // Legacy BOMs: (qty / batch_qty) * dosage * wastage
        // New BOMs:    RM â†’ (qty * fill_volume / divisor) * dosage% ; others â†’ qty * dosage ; both * wastage
        $sql = "SELECT bi.item_id AS component_item_id,
                       SUM(
                           CASE
                               WHEN b.is_legacy_formula = 1 THEN
                                   (poi.quantity / b.batch_qty) * bi.dosage_rate
                               WHEN i.item_type = 'RM' THEN
                                   (poi.quantity * b.batch_qty /
                                       CASE WHEN b.batch_unit_divisor > 0 THEN b.batch_unit_divisor ELSE 1000 END
                                   ) * (bi.dosage_rate / 100)
                               ELSE
                                   poi.quantity * bi.dosage_rate
                           END
                           * (1 + bi.wastage_allowance_pct / 100)
                       ) AS total_pending
                FROM purchase_order_items poi
                JOIN fg_boms b ON b.fg_item_id = poi.item_id
                JOIN fg_bom_items bi ON bi.bom_id = b.id
                JOIN items i ON bi.item_id = i.item_id
                JOIN purchase_orders po ON poi.po_id = po.po_id
                WHERE po.delivered_quantity < po.total_quantity
                  AND po.`remove` = 0
                  AND bi.item_id IN ({$placeholders})";
        $params = $ingredient_item_ids;
        if (!empty($exclude_poi_ids)) {
            $exPlaceholders = implode(',', array_fill(0, count($exclude_poi_ids), '?'));
            $sql .= " AND poi.poi_id NOT IN ({$exPlaceholders})";
            $params = array_merge($params, $exclude_poi_ids);
        }
        $sql .= " GROUP BY bi.item_id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['component_item_id']] = $row['total_pending'];
        }
        return $result;
    }

    // â”€â”€â”€ BOM Lookup â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * BOM header used by LMR / production flows. Multi-BOM: resolves to the
     * active default formulation (oldest active BOM as fallback), matching
     * what the MRP Sheet picks when no BOM is chosen explicitly.
     */
    public function hasBOM($item_id) {
        $sql = "SELECT id, bom_code, batch_qty, batch_uom,
                       batch_qty AS fill_volume, batch_uom AS uom,
                       batch_unit_divisor, is_legacy_formula, is_default
                FROM fg_boms
                WHERE fg_item_id = :item_id
                ORDER BY (status = 'active') DESC, is_default DESC, id ASC
                LIMIT 1";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['item_id' => $item_id]);
        return $stmt->fetch() ?: false;
    }

    public function getBomByCode($bomCode) {
        $sql = "SELECT b.id, b.bom_code, b.batch_qty, b.batch_uom,
                       b.batch_qty AS fill_volume, b.batch_uom AS uom,
                       b.batch_unit_divisor, b.is_legacy_formula,
                       b.fg_item_id,
                       i.item_code AS fg_code, i.item_description AS fg_name
                FROM fg_boms b
                JOIN items i ON b.fg_item_id = i.item_id AND i.`remove` = 0
                WHERE b.bom_code = :bom_code
                LIMIT 1";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['bom_code' => $bomCode]);
        return $stmt->fetch() ?: false;
    }

    // â”€â”€â”€ Procurement PO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getSupplierOrdersFiltered($filters = []) {
        $where = ["so.`remove` = 0"];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "so.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['supplier_name'])) {
            $where[] = "so.supplier_name LIKE :supplier_name";
            $params['supplier_name'] = '%' . $filters['supplier_name'] . '%';
        }
        if (!empty($filters['search'])) {
            $where[] = "(so.supplier_name LIKE :search1 OR i.item_code LIKE :search2 OR i.item_description LIKE :search3 OR so.po_ref LIKE :search4 OR so.remarks LIKE :search5)";
            $params['search1'] = '%' . $filters['search'] . '%';
            $params['search2'] = $params['search1'];
            $params['search3'] = $params['search1'];
            $params['search4'] = $params['search1'];
            $params['search5'] = $params['search1'];
        }

        $whereSql = implode(' AND ', $where);
        // PO Ref is the explicit po_ref entered by procurement (New/Process
        // modal); '-' until one is provided. Never customer_po_number, never
        // auto-generated internal ids.
        $sql = "SELECT so.*, i.item_code, i.item_description, i.item_type,
                       COALESCE(so.uom,
                                (SELECT bi.uom FROM fg_bom_items bi
                                 JOIN fg_boms b ON b.id = bi.bom_id AND b.status = 'active'
                                 WHERE bi.item_id = so.item_id
                                   AND bi.uom IS NOT NULL AND bi.uom <> ''
                                 ORDER BY bi.id DESC LIMIT 1),
                                i.item_uom) AS item_uom,
                       u.full_name AS created_by_name,
                       CASE WHEN so.po_ref IS NOT NULL AND TRIM(so.po_ref) <> ''
                            THEN TRIM(so.po_ref)
                            ELSE '-'
                       END AS po_ref_display
                FROM supplier_orders so
                JOIN items i ON so.item_id = i.item_id
                JOIN users u ON so.created_by = u.user_id
                WHERE {$whereSql}
                ORDER BY so.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getSupplierOrderById($id) {
        $sql = "SELECT so.*, i.item_code, i.item_description, i.item_type,
                       COALESCE(so.uom,
                                (SELECT bi.uom FROM fg_bom_items bi
                                 JOIN fg_boms b ON b.id = bi.bom_id AND b.status = 'active'
                                 WHERE bi.item_id = so.item_id
                                   AND bi.uom IS NOT NULL AND bi.uom <> ''
                                 ORDER BY bi.id DESC LIMIT 1),
                                i.item_uom) AS item_uom
                FROM supplier_orders so
                JOIN items i ON so.item_id = i.item_id
                WHERE so.supplier_order_id = :id AND so.`remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: false;
    }

    public function createSupplierOrder($data) {
        $status = $data['status'] ?? 'pending';
        $poId = $data['po_id'] ?? null;
        $uom = $data['uom'] ?? null;
        $mrpRunId = isset($data['mrp_run_id']) ? intval($data['mrp_run_id']) : null;
        $mrpRef = $data['mrp_ref'] ?? null;
        if ($uom === null || $uom === '') {
            $uomStmt = self::getConnection()->prepare("SELECT item_uom FROM items WHERE item_id = ?");
            $uomStmt->execute([$data['item_id']]);
            $uom = $uomStmt->fetchColumn() ?: null;
        }
        $sql = "INSERT INTO supplier_orders (supplier_name, item_id, quantity, uom, unit_cost, order_date, expected_date, remarks, created_by, status, po_id, mrp_run_id, mrp_ref)
                VALUES (:supplier_name, :item_id, :quantity, :uom, :unit_cost, :order_date, :expected_date, :remarks, :created_by, :status, :po_id, :mrp_run_id, :mrp_ref)";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'supplier_name' => $data['supplier_name'],
            'item_id' => $data['item_id'],
            'quantity' => $data['quantity'],
            'uom' => $uom,
            'unit_cost' => $data['unit_cost'],
            'order_date' => $data['order_date'],
            'expected_date' => $data['expected_date'],
            'remarks' => $data['remarks'],
            'created_by' => $data['created_by'],
            'status' => $status,
            'po_id' => $poId,
            'mrp_run_id' => $mrpRunId,
            'mrp_ref' => $mrpRef,
        ]);
        return self::getConnection()->lastInsertId();
    }

    public function cancelSupplierOrder($id) {
        $sql = "UPDATE supplier_orders SET status = 'cancelled' WHERE supplier_order_id = :id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteSupplierOrder($id) {
        $sql = "UPDATE supplier_orders SET `remove` = 1 WHERE supplier_order_id = :id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Pending PO / RR per item â€” the informational "PENDING PO / RR" column on
     * the MRP sheet and snapshot history. Never part of the LACKING netting
     * (Lacking = max(0, Required - max(0, SOH - Allocated))).
     *
     * Item matching is by the items FK (item_id), never by item_code strings:
     * supplier_orders has no item_code column, so a requisition can only ever
     * count against the exact item row it references â€” a line for PM00097
     * sums only PM00097's requisitions; dummy/orphan codes cannot leak in.
     *
     * Statuses mirror the Purchasing PO tabs (purchasingStatusTabSet) and only
     * cover open, non-fulfilled quantities; terminal states (received,
     * cancelled, rejected, completed) and remove = 1 rows never count:
     *   1. Open Requested requisitions (MRP- or procurement-generated) at
     *      their remaining quantity (quantity - received_qty) â€” each MRP run
     *      keeps its own distinct row, so re-runs see what is already queued.
     *   2. Open supplier order remainders (ordered, not yet received):
     *      status in pending / processed / partially_received / For Inspection.
     *   3. Received-but-not-yet-passed-QC stock sitting in the inspection hold
     *      bucket (inventory_balances.qty_for_inspect) â€” not counted in SOH.
     */
    public function getPendingPoRrForItems($item_ids) {
        if (empty($item_ids)) return [];
        $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
        $conn = self::getConnection();

        $result = [];
        foreach ($item_ids as $id) {
            $result[intval($id)] = 0.0;
        }

        $sql = "SELECT item_id, SUM(GREATEST(quantity - COALESCE(received_qty, 0), 0)) AS open_qty
                FROM supplier_orders
                WHERE `remove` = 0
                  AND status IN ('pending', 'processed', 'partially_received', 'For Inspection')
                  AND item_id IN ({$placeholders})
                GROUP BY item_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_map('intval', $item_ids));
        foreach ($stmt->fetchAll() as $row) {
            $result[intval($row['item_id'])] += floatval($row['open_qty']);
        }

        $sql = "SELECT item_id, SUM(GREATEST(qty_for_inspect, 0)) AS inspect_qty
                FROM inventory_balances
                WHERE qty_for_inspect > 0
                  AND item_id IN ({$placeholders})
                GROUP BY item_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_map('intval', $item_ids));
        foreach ($stmt->fetchAll() as $row) {
            $result[intval($row['item_id'])] += floatval($row['inspect_qty']);
        }

        // Open requisitions count at their unfulfilled remainder only â€” same
        // formula as the open-PO branch, so a Requested row that already
        // received part of its quantity never overstates PENDING PO / RR.
        $sql = "SELECT item_id, SUM(GREATEST(quantity - COALESCE(received_qty, 0), 0)) AS requested_qty
                FROM supplier_orders
                WHERE `remove` = 0
                  AND status = 'requested'
                  AND item_id IN ({$placeholders})
                GROUP BY item_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_map('intval', $item_ids));
        foreach ($stmt->fetchAll() as $row) {
            $result[intval($row['item_id'])] += floatval($row['requested_qty']);
        }

        return $result;
    }

    public function getPendingSupplierOrdersForItems($item_ids, $exclude_po_id = null) {
        if (empty($item_ids)) return [];
        $namedParams = [];
        $itemPlaceholders = [];
        foreach ($item_ids as $idx => $id) {
            $key = ':item_' . $idx;
            $itemPlaceholders[] = $key;
            $namedParams[$key] = $id;
        }
        $itemList = implode(',', $itemPlaceholders);
        $sql = "SELECT item_id, SUM(quantity - received_qty) AS pending_supplier_qty
                FROM supplier_orders
                WHERE status = 'pending' AND `remove` = 0
                  AND item_id IN ({$itemList})";
        if ($exclude_po_id !== null) {
            $sql .= " AND (po_id IS NULL OR po_id != :exclude_po_id)";
            $namedParams[':exclude_po_id'] = $exclude_po_id;
        }
        $sql .= " GROUP BY item_id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($namedParams);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['item_id']] = $row['pending_supplier_qty'];
        }
        return $result;
    }

    /**
     * Oldest open Requested line per item (legacy callers only need existence).
     * MIN(supplier_order_id) keeps the pick deterministic when several rows are open.
     */
    public function getExistingRequestedOrders($item_ids) {
        if (empty($item_ids)) return [];
        $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
        $sql = "SELECT item_id, MIN(supplier_order_id) AS supplier_order_id
                FROM supplier_orders
                WHERE status = 'requested' AND `remove` = 0
                  AND item_id IN ({$placeholders})
                GROUP BY item_id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($item_ids);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['item_id']] = $row['supplier_order_id'];
        }
        return $result;
    }

    public function processSupplierOrder($id, $data) {
        $sql = "UPDATE supplier_orders
                SET supplier_name = :supplier_name,
                    quantity = :quantity,
                    unit_cost = :unit_cost,
                    order_date = :order_date,
                    expected_date = :expected_date,
                    remarks = :remarks,
                    po_id = :po_id,
                    status = 'pending'
                WHERE supplier_order_id = :id AND status = 'requested'";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'supplier_name' => $data['supplier_name'],
            'quantity' => $data['quantity'],
            'unit_cost' => $data['unit_cost'],
            'order_date' => $data['order_date'],
            'expected_date' => $data['expected_date'],
            'remarks' => $data['remarks'],
            'po_id' => $data['po_id'] ?? null,
            'id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function batchProcessSupplierOrders($ids, $data) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $checkSql = "SELECT supplier_order_id, status FROM supplier_orders
                         WHERE supplier_order_id IN ({$placeholders}) AND `remove` = 0";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute($ids);
            $rows = $checkStmt->fetchAll();

            if (count($rows) !== count($ids)) {
                $conn->rollBack();
                throw new \RuntimeException('One or more selected orders were not found or have been deleted.');
            }

            foreach ($rows as $row) {
                if ($row['status'] !== 'requested') {
                    $conn->rollBack();
                    throw new \RuntimeException("Order #{$row['supplier_order_id']} is no longer in Requested status. Please refresh and try again.");
                }
            }

            $updateSql = "UPDATE supplier_orders
                          SET supplier_name = :supplier_name,
                              unit_cost = :unit_cost,
                              order_date = :order_date,
                              expected_date = :expected_date,
                              status = 'pending'
                          WHERE supplier_order_id IN ({$placeholders})";
            $params = [
                'supplier_name' => $data['supplier_name'],
                'unit_cost' => $data['unit_cost'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'],
            ];
            foreach ($ids as $idx => $id) {
                $params[":id_{$idx}"] = $id;
            }
            $stmt = $conn->prepare($updateSql);
            $stmt->execute($params);

            $conn->commit();
            return $stmt->rowCount();
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    // â”€â”€â”€ Purchasing PO (renamed from Procurement PO) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Status sets behind the Purchasing PO filter tabs.
     * Returns null = no status restriction (All Orders).
     * A concrete $filters['status'] always wins over the tab.
     */
    private function purchasingStatusTabSet($tab) {
        switch ((string) $tab) {
            case 'requested':
                return ['requested'];
            case 'approval':
                return ['pending_whse_approval', 'pending_sysadmin_approval', 'pending_qc_approval', 'pending_admin_approval'];
            case 'active':
                return ['pending', 'processed', 'partially_received', 'For Inspection'];
            case 'history':
                return ['received', 'cancelled', 'rejected', 'completed'];
            default:
                return null;
        }
    }

    /**
     * Per-tab row counts for the Purchasing PO status tabs.
     */
    public function getPurchasingTabCounts() {
        // Same visibility predicate as getPurchasingPoFiltered() — tab badges
        // must match the rows the Purchasing PO tables actually render
        // (born MRP run-gate requisitions are hidden from both).
        $sql = "SELECT status, COUNT(*) AS c FROM supplier_orders
                WHERE `remove` = 0
                  AND (status NOT IN ('pending_sysadmin_approval', 'pending_qc_approval')
                       AND (status <> 'pending_whse_approval' OR po_group_id IS NOT NULL))
                GROUP BY status";
        $byStatus = [];
        $all = 0;
        foreach (self::getConnection()->query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $byStatus[$row['status']] = (int) $row['c'];
            $all += (int) $row['c'];
        }
        $sumOf = function (array $set) use ($byStatus) {
            $total = 0;
            foreach ($set as $status) {
                $total += $byStatus[$status] ?? 0;
            }
            return $total;
        };
        return [
            'all' => $all,
            'requested' => $sumOf($this->purchasingStatusTabSet('requested')),
            'approval' => $sumOf($this->purchasingStatusTabSet('approval')),
            'active' => $sumOf($this->purchasingStatusTabSet('active')),
            'history' => $sumOf($this->purchasingStatusTabSet('history')),
        ];
    }

    public function getPurchasingPoFiltered($filters = []) {
        $where = ["so.`remove` = 0"];
        $params = [];

        // MRP / Purchasing PO separation: the Purchasing PO tables show only
        // procurement's PO work (requested → processed → receiving/history).
        // Born MRP requisitions sitting at the RUN gates — pending_whse without
        // a po_group_id (not yet processed), pending_sysadmin, pending_qc —
        // belong to the MRP Approval pages and are hidden here.
        $where[] = "(so.status NOT IN ('pending_sysadmin_approval', 'pending_qc_approval')
                     AND (so.status <> 'pending_whse_approval' OR so.po_group_id IS NOT NULL))";

        // Exact status (dropdown) overrides the tab group.
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $statuses = [$filters['status']];
        } else {
            $statuses = $this->purchasingStatusTabSet($filters['tab'] ?? '');
        }
        if ($statuses !== null) {
            $placeholders = [];
            foreach ($statuses as $i => $status) {
                $placeholders[] = ":st{$i}";
                $params["st{$i}"] = $status;
            }
            $where[] = 'so.status IN (' . implode(', ', $placeholders) . ')';
        }
        if (!empty($filters['supplier_name'])) {
            $where[] = "so.supplier_name LIKE :supplier_name";
            $params['supplier_name'] = '%' . $filters['supplier_name'] . '%';
        }
        if (!empty($filters['search'])) {
            $where[] = "(so.supplier_name LIKE :search1 OR i.item_code LIKE :search2 OR i.item_description LIKE :search3 OR so.po_ref LIKE :search4 OR so.remarks LIKE :search5)";
            $params['search1'] = '%' . $filters['search'] . '%';
            $params['search2'] = $params['search1'];
            $params['search3'] = $params['search1'];
            $params['search4'] = $params['search1'];
            $params['search5'] = $params['search1'];
        }

        $whereSql = implode(' AND ', $where);
        // PO Ref is the explicit po_ref entered by procurement; '-' when empty.
        // Grouped cart lines sort together via po_group_id.
        $sql = "SELECT so.*, i.item_code, i.item_description, i.item_type,
                       COALESCE(so.uom,
                                (SELECT bi.uom FROM fg_bom_items bi
                                 JOIN fg_boms b ON b.id = bi.bom_id AND b.status = 'active'
                                 WHERE bi.item_id = so.item_id
                                   AND bi.uom IS NOT NULL AND bi.uom <> ''
                                 ORDER BY bi.id DESC LIMIT 1),
                                i.item_uom) AS item_uom,
                       u.full_name AS created_by_name,
                       CASE WHEN so.po_ref IS NOT NULL AND TRIM(so.po_ref) <> ''
                            THEN TRIM(so.po_ref)
                            ELSE '-'
                       END AS po_ref_display
                FROM supplier_orders so
                JOIN items i ON so.item_id = i.item_id
                JOIN users u ON so.created_by = u.user_id
                WHERE {$whereSql}
                ORDER BY so.date_created DESC, COALESCE(so.po_group_id, so.supplier_order_id) DESC, so.supplier_order_id ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getPurchasingPoById($id) {
        $sql = "SELECT so.*, i.item_code, i.item_description, i.item_type,
                       COALESCE(so.uom,
                                (SELECT bi.uom FROM fg_bom_items bi
                                 JOIN fg_boms b ON b.id = bi.bom_id AND b.status = 'active'
                                 WHERE bi.item_id = so.item_id
                                   AND bi.uom IS NOT NULL AND bi.uom <> ''
                                 ORDER BY bi.id DESC LIMIT 1),
                                i.item_uom) AS item_uom,
                       u.full_name AS created_by_name,
                       ru.full_name AS received_by_name
                FROM supplier_orders so
                JOIN items i ON so.item_id = i.item_id
                LEFT JOIN users u ON u.user_id = so.created_by
                LEFT JOIN users ru ON ru.user_id = so.received_by
                WHERE so.supplier_order_id = :id AND so.`remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: false;
    }

    /**
     * All live supplier_order lines for a set of MRP runs — used by the
     * Purchasing PO list so a Source group's View/Process modals can show
     * every line of the run, even when a status tab filters some out.
     * Returns [mrp_run_id => [line, ...]] ordered by line id.
     */
    public function getSupplierOrdersByRuns($runIds) {
        $runIds = array_values(array_filter(array_map('intval', (array) $runIds)));
        if (!count($runIds)) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($runIds), '?'));
        $stmt = self::getConnection()->prepare(
            "SELECT so.*, i.item_code, i.item_description,
                    COALESCE(so.uom,
                        (SELECT bi.uom FROM fg_bom_items bi
                         JOIN fg_boms b ON b.id = bi.bom_id AND b.status = 'active'
                         WHERE bi.item_id = so.item_id
                           AND bi.uom IS NOT NULL AND bi.uom <> ''
                         ORDER BY bi.id DESC LIMIT 1),
                        i.item_uom) AS item_uom
             FROM supplier_orders so
             JOIN items i ON so.item_id = i.item_id
             WHERE so.mrp_run_id IN ({$placeholders}) AND so.`remove` = 0
             ORDER BY so.supplier_order_id ASC"
        );
        $stmt->execute($runIds);
        $byRun = [];
        foreach ($stmt->fetchAll() as $row) {
            $byRun[(int) $row['mrp_run_id']][] = $row;
        }
        return $byRun;
    }

    public function getPoFullDetails($id) {
        $conn = self::getConnection();
        $po = $this->getPurchasingPoById($id);
        if (!$po) {
            return false;
        }

        $batchStmt = $conn->prepare(
            "SELECT ri.id, ri.po_ref, ri.ordered_qty, ri.received_qty, ri.passed_qty, ri.rejected_qty,
                    ri.lot_number, ri.expiry_date, ri.dr_invoice_no, ri.received_date, ri.received_by,
                    ru.full_name AS received_by_name,
                    ri.remarks, ri.qc_status, ri.inspected_by, ri.inspected_at
             FROM receiving_items ri
             LEFT JOIN users ru ON ru.user_id = ri.received_by
             WHERE ri.supplier_order_id = :id
             ORDER BY ri.received_date DESC, ri.id DESC"
        );
        $batchStmt->execute(['id' => $id]);
        $batches = $batchStmt->fetchAll(\PDO::FETCH_ASSOC);

        $inspections = [];
        if ($batches) {
            $riIds = array_map('intval', array_column($batches, 'id'));
            $insStmt = $conn->prepare(
                "SELECT qi.receiving_item_id, qi.decision, qi.passed_qty, qi.rejected_qty,
                        qi.inspector_name, qi.remarks, qi.inspected_at
                 FROM qc_inspections qi
                 WHERE qi.receiving_item_id IN (" . implode(', ', $riIds) . ")
                 ORDER BY qi.inspected_at DESC, qi.inspection_id DESC"
            );
            $insStmt->execute();
            foreach ($insStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $key = (int) $row['receiving_item_id'];
                if (!isset($inspections[$key])) {
                    $inspections[$key] = $row;
                }
            }
        }

        foreach ($batches as &$batch) {
            $riId = (int) $batch['id'];
            $qc = $inspections[$riId] ?? null;
            $batch['qc_decision'] = $qc['decision'] ?? (strtoupper((string) ($batch['qc_status'] ?? '')) ?: null);
            $batch['inspector_name'] = $qc['inspector_name'] ?? null;
            $batch['qc_remarks'] = $qc['remarks'] ?? null;
            $batch['qc_date'] = $qc['inspected_at'] ?? ($batch['inspected_at'] ?? null);
            $batch['received_date'] = $batch['received_date'] ? date('m/d/Y', strtotime($batch['received_date'])) : null;
            $batch['qc_date'] = $batch['qc_date'] ? date('m/d/Y H:i', strtotime($batch['qc_date'])) : null;
            $batch['dr_invoice_no'] = trim((string) ($batch['dr_invoice_no'] ?? ''));
            $batch['received_qty'] = rtrim(rtrim(number_format(floatval($batch['received_qty'] ?? 0), 4), '0'), '.');
            $batch['ordered_qty'] = rtrim(rtrim(number_format(floatval($batch['ordered_qty'] ?? 0), 4), '0'), '.');
            $batch['passed_qty'] = rtrim(rtrim(number_format(floatval($batch['passed_qty'] ?? 0), 4), '0'), '.');
            $batch['rejected_qty'] = rtrim(rtrim(number_format(floatval($batch['rejected_qty'] ?? 0), 4), '0'), '.');
        }
        unset($batch);

        $latest = $batches[0] ?? null;
        $uom = (string) ($po['item_uom'] ?? '');
        $qty = floatval($po['quantity'] ?? 0);
        $recvTotal = floatval($po['received_qty'] ?? 0);

        $receivingNotes = null;
        if ($latest) {
            $noteParts = [trim((string) ($latest['remarks'] ?? ''))];
            // QC inspection overwrites receiving_items.remarks, so rebuild the
            // lot/expiry staging note when the original receipt note is gone.
            if ($noteParts[0] === '' && !empty($latest['lot_number'])) {
                $rebuilt = 'Lot: ' . $latest['lot_number'];
                if (!empty($latest['expiry_date'])) {
                    $rebuilt .= ' | Expiry: ' . $latest['expiry_date'];
                }
                $noteParts[0] = $rebuilt;
            }
            $receivingNotes = $noteParts[0] !== '' ? $noteParts[0] : null;
        }

        return [
            'po_ref' => trim((string) ($po['po_ref'] ?? '')) ?: null,
            'supplier_name' => $po['supplier_name'] ?? null,
            'item_code' => $po['item_code'] ?? null,
            'description' => $po['item_description'] ?? null,
            'quantity' => formatQty($qty),
            'uom' => $uom,
            'unit_cost' => number_format(floatval($po['unit_cost'] ?? 0), 2),
            'order_date' => $po['order_date'] ? date('m/d/Y', strtotime($po['order_date'])) : null,
            'expected_date' => $po['expected_date'] ? date('m/d/Y', strtotime($po['expected_date'])) : null,
            'status' => $po['status'] ?? null,
            'order_remarks' => trim((string) ($po['remarks'] ?? '')) ?: null,
            'created_by' => $po['created_by_name'] ?? null,
            'received_qty' => formatQty($recvTotal),
            'received_date' => ($latest && $latest['received_date']) ? date('m/d/Y', strtotime($latest['received_date'])) : null,
            'dr_number' => $latest ? trim((string) ($latest['dr_invoice_no'] ?? '')) : '',
            'received_by' => $po['received_by_name'] ?? null,
            'receiving_notes' => $receivingNotes,
            'lot_number' => $latest['lot_number'] ?? null,
            'expiry_date' => $latest['expiry_date'] ?? null,
            'qc_decision' => $latest['qc_decision'] ?? null,
            'inspector_name' => $latest['inspector_name'] ?? null,
            'qc_date' => ($latest && $latest['qc_date']) ? date('m/d/Y H:i', strtotime($latest['qc_date'])) : null,
            'qc_remarks' => $latest['qc_remarks'] ?? null,
            'passed_qty' => $latest ? rtrim(rtrim(number_format(floatval($latest['passed_qty'] ?? 0), 4), '0'), '.') : null,
            'rejected_qty' => $latest ? rtrim(rtrim(number_format(floatval($latest['rejected_qty'] ?? 0), 4), '0'), '.') : null,
            'batches' => $batches,
        ];
    }

    /**
     * Raw (unformatted) purchasing PO row for the Edit modal, with an
     * editable flag — cancelled/rejected orders are frozen; fully received
     * & approved lines too (only QC may correct them post-inspection).
     */
    public function getPurchasingPoForEdit($id) {
        $po = $this->getPurchasingPoById($id);
        if (!$po) {
            return false;
        }
        $frozen = ['cancelled', 'rejected'];
        $receivedQty = floatval($po['received_qty'] ?? 0);
        $dept = $_SESSION['department'] ?? '';
        // Receipted lines are locked for everyone except QC (post-inspection
        // corrections); quantity changes go through the staged-receipt Edit.
        $editable = !in_array(strtolower((string) ($po['status'] ?? '')), $frozen, true)
            && !($receivedQty > 0.0001 && $dept !== 'qc');
        return [
            'supplier_order_id' => (int) $po['supplier_order_id'],
            'supplier_name' => $po['supplier_name'] ?? '',
            'item_code' => $po['item_code'] ?? '',
            'item_description' => $po['item_description'] ?? null,
            'uom' => $po['item_uom'] ?? '',
            'quantity' => formatQty(floatval($po['quantity'] ?? 0)),
            'received_qty' => formatQty($receivedQty),
            'unit_cost' => rtrim(rtrim(number_format(floatval($po['unit_cost'] ?? 0), 2), '0'), '.'),
            'order_date' => $po['order_date'] ?? null,
            'expected_date' => $po['expected_date'] ?? null,
            'remarks' => trim((string) ($po['remarks'] ?? '')),
            'status' => $po['status'] ?? null,
            'editable' => $editable,
        ];
    }

    /**
     * Correct typo-level fields on a purchasing PO. Cancelled/rejected rows
     * are frozen; rows with received quantities may still be edited but the
     * quantity can never drop below what was already received. Item identity
     * and any inventory/receiving links are preserved.
     */
    public function updatePurchasingPo($id, array $data) {
        $conn = self::getConnection();
        $stmt = $conn->prepare(
            "SELECT supplier_order_id, status, received_qty FROM supplier_orders
              WHERE supplier_order_id = :id AND `remove` = 0
              LIMIT 1 FOR UPDATE"
        );
        $conn->beginTransaction();
        try {
            $stmt->execute(['id' => $id]);
            $po = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$po) {
                throw new \RuntimeException('Purchasing PO not found.');
            }
            if (in_array(strtolower((string) $po['status']), ['cancelled', 'rejected'], true)) {
                throw new \RuntimeException('Cancelled or rejected orders cannot be edited.');
            }
            $receivedQty = floatval($po['received_qty']);
            $dept = $_SESSION['department'] ?? '';
            // Any receipted line is "received" — QC retains post-inspection
            // correction rights; everyone else is locked, with quantity
            // corrections done via the staged-receipt Edit instead.
            if ($receivedQty > 0.0001 && $dept !== 'qc') {
                throw new \RuntimeException('This PO already has received goods; editing the PO record is locked. '
                    . 'Use the staged-receipt Edit in Receiving while the batch is still awaiting inspection.');
            }
            $newQty = floatval($data['quantity'] ?? 0);
            if ($newQty <= 0) {
                throw new \RuntimeException('Quantity must be greater than zero.');
            }
            if ($receivedQty > 0 && $newQty < $receivedQty) {
                throw new \RuntimeException('Quantity cannot be less than the already-received quantity ('
                    . formatQty($receivedQty) . ').');
            }
            $upd = $conn->prepare(
                "UPDATE supplier_orders SET
                    supplier_name = :supplier_name,
                    quantity = :quantity,
                    unit_cost = :unit_cost,
                    order_date = :order_date,
                    expected_date = :expected_date,
                    remarks = :remarks
                  WHERE supplier_order_id = :id"
            );
            $upd->execute([
                'supplier_name' => trim($data['supplier_name'] ?? ''),
                'quantity' => $newQty,
                'unit_cost' => floatval($data['unit_cost'] ?? 0),
                'order_date' => ($data['order_date'] ?? '') !== '' ? $data['order_date'] : null,
                'expected_date' => ($data['expected_date'] ?? '') !== '' ? $data['expected_date'] : null,
                'remarks' => trim($data['remarks'] ?? '') !== '' ? trim($data['remarks']) : null,
                'id' => $id,
            ]);

            $conn->commit();
            return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function createPurchasingPo($data) {
        $status = $data['status'] ?? 'requested';
        $poId = $data['po_id'] ?? null;
        $uom = $data['uom'] ?? null;
        if ($uom === null || $uom === '') {
            $uomStmt = self::getConnection()->prepare("SELECT item_uom FROM items WHERE item_id = ?");
            $uomStmt->execute([$data['item_id']]);
            $uom = $uomStmt->fetchColumn() ?: null;
        }
        $sql = "INSERT INTO supplier_orders (supplier_name, item_id, quantity, uom, unit_cost, order_date, expected_date, remarks, po_ref, created_by, status, po_id, received_qty, received_date)
                VALUES (:supplier_name, :item_id, :quantity, :uom, :unit_cost, :order_date, :expected_date, :remarks, :po_ref, :created_by, :status, :po_id, 0, NULL)";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'supplier_name' => $data['supplier_name'],
            'item_id' => $data['item_id'],
            'quantity' => $data['quantity'],
            'uom' => $uom,
            'unit_cost' => $data['unit_cost'],
            'order_date' => $data['order_date'],
            'expected_date' => $data['expected_date'],
            'remarks' => $data['remarks'],
            'po_ref' => $data['po_ref'] ?? null,
            'created_by' => $data['created_by'],
            'status' => $status,
            'po_id' => $poId
        ]);
        return self::getConnection()->lastInsertId();
    }

    /**
     * Manually created multi-line Purchasing PO (cart save). Every line of the
     * PO shares one po_group_id (the id of the first inserted line) so WHSE
     * Admin / System Admin decisions can act on the whole group. All lines are
     * born at pending_whse_approval — the first gate of the manual PO chain.
     * $header: po_ref, order_date, expected_date, remarks, created_by
     * $lines:  [ [supplier_name, item_id, quantity, unit_cost], ... ]
     */
    public function createManualPoGroup($header, $lines) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $groupId = null;
            $insert = $conn->prepare(
                "INSERT INTO supplier_orders
                    (supplier_name, item_id, quantity, uom, unit_cost, order_date, expected_date,
                     remarks, po_ref, created_by, status, po_id, received_qty, received_date, po_group_id)
                 VALUES
                    (:supplier_name, :item_id, :quantity, :uom, :unit_cost, :order_date, :expected_date,
                     :remarks, :po_ref, :created_by, 'pending_whse_approval', NULL, 0, NULL, :po_group_id)"
            );
            foreach ($lines as $line) {
                $uomStmt = $conn->prepare("SELECT item_uom FROM items WHERE item_id = ?");
                $uomStmt->execute([$line['item_id']]);
                $uom = $uomStmt->fetchColumn() ?: null;
                $insert->execute([
                    'supplier_name' => $line['supplier_name'],
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'uom' => $uom,
                    'unit_cost' => $line['unit_cost'],
                    'order_date' => $header['order_date'] ?? null,
                    'expected_date' => $header['expected_date'] ?? null,
                    'remarks' => $header['remarks'] ?? null,
                    'po_ref' => $header['po_ref'] ?? null,
                    'created_by' => $header['created_by'],
                    'po_group_id' => $groupId,
                ]);
                if ($groupId === null) {
                    $groupId = (int) $conn->lastInsertId();
                    $conn->prepare("UPDATE supplier_orders SET po_group_id = ? WHERE supplier_order_id = ?")
                        ->execute([$groupId, $groupId]);
                }
            }
            $conn->commit();
            return ['po_group_id' => $groupId, 'line_count' => count($lines)];
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * WHSE Admin gate for a manual PO group: every line still at
     * pending_whse_approval moves to pending_admin_approval (System Admin).
     * Throws when the line is gone, not a manual PO, or already decided.
     */
    public function approveManualPoGroup($id, $approverLabel) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare(
                "SELECT supplier_order_id, status, po_group_id, mrp_run_id FROM supplier_orders
                 WHERE supplier_order_id = :id AND `remove` = 0 FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \RuntimeException('Purchasing PO not found.');
            }
            if (!empty($row['mrp_run_id']) && empty($row['po_group_id'])) {
                throw new \RuntimeException('This is a born MRP requisition — it uses the MRP run approval gate, not the PO draft gate.');
            }
            if ($row['status'] !== 'pending_whse_approval') {
                throw new \RuntimeException('This PO is no longer awaiting WHSE Admin approval — refresh and try again.');
            }
            $groupId = $row['po_group_id'] ?: $row['supplier_order_id'];
            $upd = $conn->prepare(
                "UPDATE supplier_orders
                 SET status = 'pending_admin_approval', last_update = NOW(),
                     remarks = CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks, '') = '' THEN '' ELSE ' | ' END, :note)
                 WHERE po_group_id = :gid AND status = 'pending_whse_approval' AND `remove` = 0"
            );
            $upd->execute([
                'note' => '[Approval] WHSE Admin approved by ' . $approverLabel . ' on ' . date('Y-m-d H:i') . ' — awaiting System Admin.',
                'gid' => $groupId,
            ]);
            $moved = $upd->rowCount();
            if (!$moved) {
                throw new \RuntimeException('No PO lines in this group are still awaiting WHSE Admin approval.');
            }
            $conn->commit();
            return ['po_group_id' => $groupId, 'moved' => $moved];
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * WHSE Admin reject for a manual PO group: every line still at
     * pending_whse_approval is cancelled with the rejection reason.
     */
    public function rejectManualPoGroup($id, $reason, $approverLabel) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare(
                "SELECT supplier_order_id, status, po_group_id, mrp_run_id FROM supplier_orders
                 WHERE supplier_order_id = :id AND `remove` = 0 FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \RuntimeException('Purchasing PO not found.');
            }
            if (!empty($row['mrp_run_id']) && empty($row['po_group_id'])) {
                throw new \RuntimeException('This is a born MRP requisition — it uses the MRP run approval gate, not the PO draft gate.');
            }
            if ($row['status'] !== 'pending_whse_approval') {
                throw new \RuntimeException('This PO is no longer awaiting WHSE Admin approval — refresh and try again.');
            }
            $groupId = $row['po_group_id'] ?: $row['supplier_order_id'];
            $upd = $conn->prepare(
                "UPDATE supplier_orders
                 SET status = 'cancelled', last_update = NOW(),
                     remarks = CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks, '') = '' THEN '' ELSE ' | ' END, :note)
                 WHERE po_group_id = :gid AND status = 'pending_whse_approval' AND `remove` = 0"
            );
            $upd->execute([
                'note' => '[Rejection] WHSE Admin rejected by ' . $approverLabel . ' on ' . date('Y-m-d H:i')
                    . ' - Reason: ' . ($reason !== '' ? $reason : 'not provided'),
                'gid' => $groupId,
            ]);
            $moved = $upd->rowCount();
            if (!$moved) {
                throw new \RuntimeException('No PO lines in this group are still awaiting WHSE Admin approval.');
            }
            $conn->commit();
            return ['po_group_id' => $groupId, 'cancelled' => $moved];
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Lines for the printable Purchasing PO page: all lines sharing the
     * group of the given line (or just that line when it has no group).
     */
    public function getPoGroupForPrint($id) {
        // Resolve the group first: cart POs print their po_group_id lines,
        // MRP-sourced lines print the WHOLE run (so a processed source shows
        // every RM item), ungrouped single rows print just themselves.
        $keyStmt = self::getConnection()->prepare(
            "SELECT po_group_id, mrp_run_id, supplier_order_id FROM supplier_orders
             WHERE supplier_order_id = :id AND `remove` = 0"
        );
        $keyStmt->execute(['id' => $id]);
        $key = $keyStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$key) {
            return [];
        }
        if (!empty($key['po_group_id'])) {
            $col = 'so.po_group_id';
            $gid = (int) $key['po_group_id'];
        } elseif (!empty($key['mrp_run_id'])) {
            $col = 'so.mrp_run_id';
            $gid = (int) $key['mrp_run_id'];
        } else {
            $col = 'so.supplier_order_id';
            $gid = (int) $key['supplier_order_id'];
        }
        $stmt = self::getConnection()->prepare(
            "SELECT so.*, i.item_code, i.item_description,
                    COALESCE(so.uom,
                        (SELECT bi.uom FROM fg_bom_items bi
                         JOIN fg_boms b ON b.id = bi.bom_id AND b.status = 'active'
                         WHERE bi.item_id = so.item_id
                           AND bi.uom IS NOT NULL AND bi.uom <> ''
                         ORDER BY bi.id DESC LIMIT 1),
                        i.item_uom) AS item_uom,
                    u.full_name AS created_by_name
             FROM supplier_orders so
             JOIN items i ON so.item_id = i.item_id
             JOIN users u ON so.created_by = u.user_id
             WHERE so.`remove` = 0
               AND {$col} = :gid
             ORDER BY so.supplier_order_id ASC"
        );
        $stmt->execute(['gid' => $gid]);
        return $stmt->fetchAll();
    }

    public function processPurchasingPo($id, $data) {
        // po_ref is the explicit vendor PO reference entered by procurement;
        // COALESCE keeps an existing value when the field is submitted blank.
        // remarks keeps its COALESCE so blank submissions preserve receipt logs.
        // PO drafts enter the WHSE Admin gate first (self-grouped via
        // po_group_id — the discriminator vs. born MRP requisition rows).
        $sql = "UPDATE supplier_orders
                SET supplier_name = :supplier_name,
                    quantity = :quantity,
                    unit_cost = :unit_cost,
                    order_date = :order_date,
                    expected_date = :expected_date,
                    remarks = COALESCE(:remarks, remarks),
                    po_ref = COALESCE(:po_ref, po_ref),
                    po_id = :po_id,
                    po_group_id = COALESCE(po_group_id, :group_id),
                    status = 'pending_whse_approval'
                WHERE supplier_order_id = :id AND status IN ('requested', 'pending_whse_approval', 'pending_admin_approval')";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'supplier_name' => $data['supplier_name'],
            'quantity' => $data['quantity'],
            'unit_cost' => $data['unit_cost'],
            'order_date' => $data['order_date'],
            'expected_date' => $data['expected_date'],
            'remarks' => $data['remarks'],
            'po_ref' => $data['po_ref'] ?? null,
            'po_id' => $data['po_id'] ?? null,
            'group_id' => $id,
            'id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Group Process for an MRP source: every listed line of the run still at
     * 'requested' gets its supplier/qty/cost plus the shared header
     * (dates + one PO REF) and enters the PO approval chain at the WHSE gate:
     *   pending_whse_approval (WHSE Admin) → pending_admin_approval (System Admin)
     * The processed lines become one manual-style group (po_group_id = first
     * line) — the discriminator that tells them apart from born requisition
     * rows still awaiting the run's own WHSE gate (those have po_group_id NULL).
     * $header: order_date, expected_date, po_ref
     * $lines:  [ [supplier_order_id, supplier_name, quantity, unit_cost], ... ]
     * Returns the number of lines moved; throws when nothing was processed.
     */
    public function processMrpPoGroup($runId, $header, $lines) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $moved = 0;
            $groupId = null;
            $upd = $conn->prepare(
                "UPDATE supplier_orders
                 SET supplier_name = :supplier_name,
                     quantity = :quantity,
                     unit_cost = :unit_cost,
                     order_date = :order_date,
                     expected_date = :expected_date,
                     po_ref = :po_ref,
                     status = 'pending_whse_approval',
                     po_group_id = COALESCE(po_group_id, :group_id),
                     last_update = NOW()
                 WHERE supplier_order_id = :id
                   AND mrp_run_id = :run_id
                   AND status = 'requested'
                   AND `remove` = 0"
            );
            foreach ($lines as $line) {
                if ($groupId === null) {
                    $groupId = (int) $line['supplier_order_id'];
                }
                $upd->execute([
                    'supplier_name' => $line['supplier_name'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'order_date' => $header['order_date'] ?? null,
                    'expected_date' => $header['expected_date'] ?? null,
                    'po_ref' => $header['po_ref'] ?? null,
                    'group_id' => $groupId,
                    'id' => (int) $line['supplier_order_id'],
                    'run_id' => (int) $runId,
                ]);
                $moved += $upd->rowCount();
            }
            if (!$moved) {
                throw new \RuntimeException('No lines from this MRP source are still awaiting processing — refresh and try again.');
            }
            $conn->commit();
            return $moved;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    // ─── MRP dual-approval gate (Draft → WHSE Admin → System Admin → Procurement) ───

    /** Is this user id a Warehouse Admin approver (MRP gate, step 1)? */
    public function isWarehouseApprover($userId) {
        if (!$userId) return false;
        $stmt = self::getConnection()->prepare(
            "SELECT is_approver, role FROM users WHERE user_id = :id AND `remove` = 0 AND status = 1"
        );
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return false;
        // whse_admin role auto-passes the gate; legacy is_approver flag still honored.
        return ($row['role'] ?? '') === 'whse_admin' || (bool) $row['is_approver'];
    }

    /**
     * Read-only way for controllers to inspect a requisition before acting.
     */
    public function getSupplierOrderStatus($id) {
        $sql = "SELECT so.*, i.item_code, i.item_description
                FROM supplier_orders so
                JOIN items i ON so.item_id = i.item_id
                WHERE so.supplier_order_id = :id AND so.`remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * One approval-gate transition, row-locked so two approvers cannot race:
     *   step 1 (warehouse admin):  pending_whse_approval      → pending_sysadmin_approval
     *   step 2 (system admin):     pending_sysadmin_approval  → requested (released)
     * Throws when the row is gone, not at the expected status, or not an
     * MRP-generated requisition (mrp_run_id must be set — the gate only
     * governs drafts born from an MRP run).
     */
    public function approveMrpRequisition($id, $currentStatus, $nextStatus, $stageLabel, $approverLabel) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare(
                "SELECT status, mrp_run_id, po_group_id FROM supplier_orders
                 WHERE supplier_order_id = :id AND `remove` = 0 FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \RuntimeException('Requisition not found.');
            }
            if ($row['status'] !== $currentStatus) {
                throw new \RuntimeException(
                    'Requisition is no longer at the "' . $stageLabel . '" stage — refresh and try again.'
                );
            }
            if (empty($row['mrp_run_id'])) {
                throw new \RuntimeException('Only MRP-generated requisitions pass through the approval gate.');
            }
            // MRP / Purchasing PO separation: processed PO drafts pass the
            // Purchasing PO gates, never the MRP run gates.
            if (!empty($row['po_group_id'])) {
                throw new \RuntimeException(
                    'This line is a processed Purchasing PO draft — it passes the PO approval gates, not the MRP run gate.'
                );
            }

            $upd = $conn->prepare(
                "UPDATE supplier_orders
                 SET status = :status, last_update = NOW(),
                     remarks = CONCAT(remarks, :note)
                 WHERE supplier_order_id = :id"
            );
            $upd->execute([
                'status' => $nextStatus,
                'note' => ' [Approval] ' . $stageLabel . ' approved by ' . $approverLabel . ' on ' . date('Y-m-d H:i'),
                'id' => $id,
            ]);
            $conn->commit();
            return $nextStatus;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Rejection at either gate: the draft is cancelled (terminal) with the
     * reason appended to the remarks trail. Committed MRP stock (qty_allocated)
     * is NOT released here — releasing stays with MRP History delete,
     * consistent with cancelPurchasingPo()'s existing behavior.
     */
    public function rejectMrpRequisition($id, $currentStatus, $stageLabel, $reason, $approverLabel) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare(
                "SELECT status, po_group_id FROM supplier_orders
                 WHERE supplier_order_id = :id AND `remove` = 0 FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \RuntimeException('Requisition not found.');
            }
            if ($row['status'] !== $currentStatus) {
                throw new \RuntimeException(
                    'Requisition is no longer at the "' . $stageLabel . '" stage — refresh and try again.'
                );
            }
            // MRP / Purchasing PO separation: processed PO drafts pass the
            // Purchasing PO gates, never the MRP run gates.
            if (!empty($row['po_group_id'])) {
                throw new \RuntimeException(
                    'This line is a processed Purchasing PO draft — it passes the PO approval gates, not the MRP run gate.'
                );
            }

            $upd = $conn->prepare(
                "UPDATE supplier_orders
                 SET status = 'cancelled', last_update = NOW(),
                     remarks = CONCAT(remarks, :note)
                 WHERE supplier_order_id = :id"
            );
            $upd->execute([
                'note' => ' [Approval] ' . $stageLabel . ' rejected by ' . $approverLabel . ' on ' . date('Y-m-d H:i')
                        . ' — Reason: ' . ($reason !== '' ? $reason : 'not provided'),
                'id' => $id,
            ]);
            $conn->commit();
            return 'cancelled';
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /** Requisitions at an MRP approval stage ('whse' or 'sysadmin').
     *  The warehouse viewport lists only its own stage; the admin viewport
     *  lists BOTH draft stages with a computed stage badge, so nothing saved
     *  for approval ever becomes invisible to the System Admin — rows at the
     *  earlier stage render read-only ("Awaiting Warehouse Admin"). */
    public function getMrpApprovalRows($stage) {
        if ($stage === 'sysadmin') {
            $sql = "SELECT so.supplier_order_id, so.quantity, so.uom, so.remarks, so.mrp_ref, so.mrp_run_id,
                           so.date_created, i.item_code, i.item_description, i.item_type,
                           COALESCE(so.uom,
                                    (SELECT bi.uom FROM fg_bom_items bi
                                     JOIN fg_boms b ON b.id = bi.bom_id AND b.status = 'active'
                                     WHERE bi.item_id = so.item_id
                                       AND bi.uom IS NOT NULL AND bi.uom <> ''
                                     ORDER BY bi.id DESC LIMIT 1),
                                    i.item_uom) AS item_uom,
                           u.full_name AS created_by_name,
                           CASE so.status
                               WHEN 'pending_sysadmin_approval' THEN 'sysadmin'
                               ELSE 'whse'
                           END AS gate_stage
                    FROM supplier_orders so
                    JOIN items i ON so.item_id = i.item_id
                    JOIN users u ON so.created_by = u.user_id
                    WHERE so.`remove` = 0
                      AND so.status IN ('pending_whse_approval', 'pending_sysadmin_approval')
                    ORDER BY so.mrp_run_id DESC, so.supplier_order_id ASC";
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $sql = "SELECT so.supplier_order_id, so.quantity, so.uom, so.remarks, so.mrp_ref, so.mrp_run_id,
                       so.date_created, i.item_code, i.item_description, i.item_type,
                       COALESCE(so.uom,
                                (SELECT bi.uom FROM fg_bom_items bi
                                 JOIN fg_boms b ON b.id = bi.bom_id AND b.status = 'active'
                                 WHERE bi.item_id = so.item_id
                                   AND bi.uom IS NOT NULL AND bi.uom <> ''
                                 ORDER BY bi.id DESC LIMIT 1),
                                i.item_uom) AS item_uom,
                       u.full_name AS created_by_name,
                       'whse' AS gate_stage
                FROM supplier_orders so
                JOIN items i ON so.item_id = i.item_id
                JOIN users u ON so.created_by = u.user_id
                WHERE so.`remove` = 0 AND so.status = 'pending_whse_approval'
                ORDER BY so.mrp_run_id DESC, so.supplier_order_id ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Sidebar badge count: MRP runs (not rows) needing approval at the given stage. */
    public function getMrpApprovalCount($stage) {
        $status = $stage === 'sysadmin' ? 'pending_sysadmin_approval'
                : ($stage === 'qc' ? 'pending_qc_approval' : 'pending_whse_approval');
        $conn = self::getConnection();
        $sql = "SELECT COUNT(DISTINCT mrp_run_id) FROM supplier_orders
                WHERE `remove` = 0 AND status = :status AND mrp_run_id IS NOT NULL";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['status' => $status]);
        $count = (int) $stmt->fetchColumn();
        // Zero-lacking runs awaiting the same gate (run-level column).
        $zero = $conn->prepare("SELECT COUNT(*) FROM mrp_runs WHERE approval_status = :status");
        $zero->execute(['status' => $status]);
        return $count + (int) $zero->fetchColumn();
    }

    /**
     * Sidebar badge count for the admin Approvals hub: every queue a System
     * Admin must act on. MRP entries count one per RUN (all requisitions of a
     * run are decided together); other queues count per row.
     * Extend with new approval-bearing modules as they ship.
     */
    public function getAdminApprovalsCount() {
        $conn = self::getConnection();
        $count = 0;
        foreach ([
            "SELECT COUNT(DISTINCT mrp_run_id) FROM supplier_orders WHERE `remove` = 0 AND status = 'pending_sysadmin_approval' AND mrp_run_id IS NOT NULL",
            "SELECT COUNT(*) FROM mrp_runs WHERE approval_status = 'pending_sysadmin_approval'",
            "SELECT COUNT(*) FROM supplier_orders WHERE `remove` = 0 AND status = 'pending_admin_approval'",
            "SELECT COUNT(*) FROM supplier_orders WHERE `remove` = 0 AND overage_request_status = 'pending'",
        ] as $sql) {
            $count += (int) $conn->query($sql)->fetchColumn();
        }
        return $count;
    }

    /**
     * MRP requisition drafts grouped per saved run for the approval screens:
     * each entry carries the run header (MRP ref, FG, target qty, timestamp,
     * preparer) plus its open draft items, so the Warehouse Admin can review
     * and approve/reject one executive decision per run instead of row soup.
     */
    public function getMrpApprovalRuns($stage) {
        $conn = self::getConnection();
        $statusByStage = [
            'sysadmin' => 'pending_sysadmin_approval',
            'qc'       => 'pending_qc_approval',
        ];
        $status = $statusByStage[$stage] ?? 'pending_whse_approval';
        $statusSet = $conn->quote($status);
        $sql = "SELECT so.supplier_order_id, so.quantity, so.uom, so.remarks,
                       so.status, i.item_code, i.item_description,
                       COALESCE(so.uom,
                                (SELECT bi.uom FROM fg_bom_items bi
                                 JOIN fg_boms b ON b.id = bi.bom_id AND b.status = 'active'
                                 WHERE bi.item_id = so.item_id
                                   AND bi.uom IS NOT NULL AND bi.uom <> ''
                                 ORDER BY bi.id DESC LIMIT 1),
                                i.item_uom) AS item_uom,
                       mr.run_id, mr.mrp_ref, mr.fg_code, mr.target_qty, mr.customer_id, mr.bom_id,
                       COALESCE(b.bom_code, mr.fg_code) AS bom_code,
                       c.customer_name, mr.date_created AS run_date,
                       ru.full_name AS run_user_name, u.full_name AS created_by_name
                FROM supplier_orders so
                JOIN items i ON so.item_id = i.item_id
                JOIN users u ON so.created_by = u.user_id
                JOIN mrp_runs mr ON mr.run_id = so.mrp_run_id
                LEFT JOIN fg_boms b ON b.id = mr.bom_id
                LEFT JOIN customers c ON mr.customer_id = c.customer_id
                JOIN users ru ON mr.user_id = ru.user_id
                WHERE so.`remove` = 0 AND so.status IN ($statusSet)
                  -- MRP / Purchasing PO separation: run gates list BORN
                  -- requisitions only. Processed PO drafts (po_group_id set)
                  -- pass the Purchasing PO gates, never the MRP run gates.
                  AND so.po_group_id IS NULL
                ORDER BY mr.date_created DESC, mr.run_id DESC, so.supplier_order_id ASC";
        $runs = [];
        foreach ($conn->query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $runId = (int) $row['run_id'];
            if (!isset($runs[$runId])) {
                $runs[$runId] = [
                    'run_id' => $runId,
                    'mrp_ref' => $row['mrp_ref'] ?: ('MRP-#' . $runId),
                    'fg_code' => $row['fg_code'],
                    'target_qty' => $row['target_qty'],
                    'bom_id' => $row['bom_id'],
                    'bom_code' => $row['bom_code'],
                    'customer_name' => $row['customer_name'],
                    'run_date' => $row['run_date'],
                    'run_user_name' => $row['run_user_name'],
                    'items' => [],
                    'zero_lacking' => false,
                ];
            }
            $runs[$runId]['items'][] = [
                'supplier_order_id' => $row['supplier_order_id'],
                'item_code' => $row['item_code'],
                'item_description' => $row['item_description'],
                'quantity' => $row['quantity'],
                'item_uom' => $row['item_uom'] ?? $row['uom'],
            ];
        }

        // Zero-lacking runs: gate progress lives on mrp_runs.approval_status
        // (they have no requisitions) — surface them at the same stage.
        $zeroSql = "SELECT mr.run_id, mr.mrp_ref, mr.fg_code, mr.target_qty, mr.bom_id,
                           COALESCE(b.bom_code, mr.fg_code) AS bom_code,
                           c.customer_name, mr.date_created AS run_date,
                           ru.full_name AS run_user_name
                    FROM mrp_runs mr
                    LEFT JOIN fg_boms b ON b.id = mr.bom_id
                    LEFT JOIN customers c ON mr.customer_id = c.customer_id
                    JOIN users ru ON mr.user_id = ru.user_id
                    WHERE mr.approval_status = $statusSet
                    ORDER BY mr.date_created DESC, mr.run_id DESC";
        foreach ($conn->query($zeroSql)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $runId = (int) $row['run_id'];
            if (isset($runs[$runId])) continue;   // requisition-bearing run already listed
            $runs[$runId] = [
                'run_id' => $runId,
                'mrp_ref' => $row['mrp_ref'] ?: ('MRP-#' . $runId),
                'fg_code' => $row['fg_code'],
                'target_qty' => $row['target_qty'],
                'bom_id' => $row['bom_id'],
                'bom_code' => $row['bom_code'],
                'customer_name' => $row['customer_name'],
                'run_date' => $row['run_date'],
                'run_user_name' => $row['run_user_name'],
                'items' => [],
                'zero_lacking' => true,
            ];
        }
        return array_values($runs);
    }

    /**
     * Peek a run's gate state: non-empty approval_status = zero-lacking run
     * (header-managed); NULL = requisition-bearing run (row-managed).
     */
    public function getMrpRunApprovalStatus($runId) {
        $stmt = self::getConnection()->prepare(
            "SELECT approval_status FROM mrp_runs WHERE run_id = :run_id"
        );
        $stmt->execute(['run_id' => (int) $runId]);
        $val = $stmt->fetchColumn();
        return $val === false ? null : $val;
    }

    /**
     * Run-level approval decision. Two gates for EVERY run:
     *   step 1 (warehouse admin):  pending_whse_approval      → pending_sysadmin_approval
     *   step 2 (system admin):     pending_sysadmin_approval  → requested (requisitions
     *                                 released to Procurement + stock reserved) or
     *                                 approved (zero-lacking runs, stock reserved)
     * QC Admin no longer gates the run — raw material inspection happens at
     * Receiving (goods received → QC → stock added). The legacy
     * pending_qc_approval current-status is still accepted so the data
     * migration can release runs stranded at the old QC gate.
     * Requisition-bearing runs flip their open supplier_orders rows (row-locked
     * on the run header so a concurrent decision on another stage cannot
     * interleave). Zero-lacking runs (approval_status set on the header) flip
     * the run-level column instead — no requisitions exist for them.
     * Returns moved count + run metadata.
     */
    public function decideMrpRun($runId, $currentStatus, $newStatus, $approverLabel, $reason = '') {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $lock = $conn->prepare(
                "SELECT run_id, mrp_ref, approval_status FROM mrp_runs WHERE run_id = :run_id FOR UPDATE"
            );
            $lock->execute(['run_id' => $runId]);
            $run = $lock->fetch(\PDO::FETCH_ASSOC);
            if (!$run) throw new \RuntimeException('MRP run not found.');

            // Zero-lacking run: gates live on the run header, not supplier_orders.
            if (!empty($run['approval_status'])) {
                if ($run['approval_status'] !== $currentStatus) {
                    throw new \RuntimeException('This run is no longer at the awaited approval stage — refresh and try again.');
                }
                $conn->prepare(
                    "UPDATE mrp_runs SET approval_status = :new_status WHERE run_id = :run_id"
                )->execute(['new_status' => $newStatus, 'run_id' => $runId]);

                // Release gate: the approved plan reserves its materials
                // (System Admin release, or the legacy QC-gate migration path).
                if ($newStatus === 'approved'
                    && in_array($currentStatus, ['pending_sysadmin_approval', 'pending_qc_approval'], true)) {
                    $this->bookMrpRunAllocation($conn, $runId);
                }

                $conn->commit();
                return ['moved' => 0, 'mrp_ref' => $run['mrp_ref'], 'zero_lacking' => true];
            }

            $upd = $conn->prepare(
                "UPDATE supplier_orders
                 SET status = :new_status, last_update = NOW(),
                     remarks = CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks, '') = '' THEN '' ELSE ' | ' END,
                                      :note)
                 WHERE mrp_run_id = :run_id AND `remove` = 0 AND status = :current_status
                   -- MRP / Purchasing PO separation: run gates only ever move
                   -- BORN requisitions; processed PO drafts (po_group_id set)
                   -- pass the Purchasing PO gates, never the run gates.
                   AND po_group_id IS NULL"
            );
            $prefix = $newStatus === 'cancelled'
                ? ' [Approval] Run requisitions rejected by ' . $approverLabel . ' on ' . date('Y-m-d H:i')
                  . ' — Reason: ' . ($reason !== '' ? $reason : 'not provided')
                : ' [Approval] Run requisitions approved by ' . $approverLabel . ' on ' . date('Y-m-d H:i');
            $upd->execute([
                'new_status' => $newStatus,
                'note' => $prefix,
                'run_id' => $runId,
                'current_status' => $currentStatus,
            ]);
            $moved = $upd->rowCount();
            if ($moved <= 0) {
                throw new \RuntimeException('No requisitions for this run remain at the awaited stage — refresh and try again.');
            }

            // Release gate: the run passes both approvals and is released to
            // Procurement — NOW the frozen requirement turns into an official
            // reservation: qty_allocated is booked and qty_committed recorded.
            // (Legacy pending_qc_approval accepted for the data migration.)
            if ($newStatus === 'requested'
                && in_array($currentStatus, ['pending_sysadmin_approval', 'pending_qc_approval'], true)) {
                $this->bookMrpRunAllocation($conn, $runId);
            }
            $conn->commit();
            return ['moved' => $moved, 'mrp_ref' => $run['mrp_ref'], 'zero_lacking' => false];
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Book the frozen requirement as an official reservation: qty_allocated
     * += total_reqt per component and qty_committed recorded. Called inside
     * decideMrpRun's transaction at the RELEASE (System Admin) gate only.
     * $allowedItemIds restricts reservation to an explicit subset (QC check:
     * only Approved/Waived components); null reserves every component.
     */
    private function bookMrpRunAllocation($conn, $runId, ?array $allowedItemIds = null) {
        $items = $conn->prepare(
            "SELECT component_item_id, total_reqt, qty_committed
             FROM mrp_run_items
             WHERE run_id = :run_id AND total_reqt > 0
             ORDER BY id ASC FOR UPDATE"
        );
        $items->execute(['run_id' => $runId]);
        $alloc = $conn->prepare(
            "INSERT INTO inventory_balances (item_id, site_code, qty_allocated)
             VALUES (:item_id, :site_code, :qty)
             ON DUPLICATE KEY UPDATE qty_allocated = qty_allocated + :qty2"
        );
        $commit = $conn->prepare(
            "UPDATE mrp_run_items
             SET qty_committed = total_reqt
             WHERE run_id = :run_id AND component_item_id = :item_id AND total_reqt > 0 AND qty_committed = 0"
        );
        foreach ($items->fetchAll(\PDO::FETCH_ASSOC) as $item) {
            if (floatval($item['qty_committed']) > 0) continue;   // already committed earlier
            $itemId = (int) $item['component_item_id'];
            if ($allowedItemIds !== null && !in_array($itemId, $allowedItemIds, true)) {
                continue;   // excluded (e.g. QC-rejected component)
            }
            $alloc->execute([
                'item_id' => $itemId,
                'site_code' => self::DEFAULT_SITE_CODE,
                'qty' => floatval($item['total_reqt']),
                'qty2' => floatval($item['total_reqt']),
            ]);
            $commit->execute(['run_id' => $runId, 'item_id' => $itemId]);
        }
    }

    /**
     * Runs the Warehouse Admin already signed, so the decision history is
     * reviewable: each run shows whether its requisitions still await the
     * System Admin or were released to Procurement. Terminally received/
     * cancelled runs are left to MRP History.
     */
    public function getMrpWhseDecisionRuns($limit = 20) {
        $conn = self::getConnection();
        $sql = "SELECT mr.run_id, mr.mrp_ref, mr.fg_code, mr.target_qty, mr.bom_id, mr.bom_id IS NULL AS no_bom,
                       b.bom_code, mr.date_created AS run_date, ru.full_name AS run_user_name,
                       COUNT(so.supplier_order_id) AS item_count,
                       SUM(CASE WHEN so.status = 'pending_sysadmin_approval' THEN 1 ELSE 0 END) AS st_awaiting_sysadmin,
                       SUM(CASE WHEN so.status = 'pending_qc_approval' THEN 1 ELSE 0 END) AS st_awaiting_qc,
                       SUM(CASE WHEN so.status = 'requested' THEN 1 ELSE 0 END) AS st_released
                FROM supplier_orders so
                JOIN mrp_runs mr ON mr.run_id = so.mrp_run_id
                LEFT JOIN fg_boms b ON b.id = mr.bom_id
                JOIN users ru ON mr.user_id = ru.user_id
                WHERE so.`remove` = 0
                  AND so.status IN ('pending_sysadmin_approval', 'pending_qc_approval', 'requested')
                GROUP BY mr.run_id, mr.mrp_ref, mr.fg_code, mr.target_qty, mr.bom_id, mr.date_created, ru.full_name, b.bom_code
                HAVING st_awaiting_sysadmin > 0 OR st_awaiting_qc > 0 OR st_released > 0
                ORDER BY mr.date_created DESC
                LIMIT :limit";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':limit', (int) $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if ((int) $row['st_awaiting_sysadmin'] > 0) {
                $row['tracking_label'] = 'For Approval (System Admin)';
                $row['tracking_badge'] = 'bg-info text-dark';
            } elseif ((int) $row['st_awaiting_qc'] > 0) {
                $row['tracking_label'] = 'For Approval (QC Admin)';
                $row['tracking_badge'] = 'bg-primary';
            } else {
                $row['tracking_label'] = 'Approved — In Procurement';
                $row['tracking_badge'] = 'bg-success';
            }
        }
        unset($row);

        // Zero-lacking runs the WHSE Admin signed (gates on the run header).
        $zeroSql = "SELECT mr.run_id, mr.mrp_ref, mr.fg_code, mr.target_qty, mr.bom_id,
                           mr.bom_id IS NULL AS no_bom, b.bom_code,
                           mr.date_created AS run_date, ru.full_name AS run_user_name,
                           0 AS item_count, 0 AS st_awaiting_sysadmin, 0 AS st_awaiting_qc, 0 AS st_released,
                           mr.approval_status
                    FROM mrp_runs mr
                    LEFT JOIN fg_boms b ON b.id = mr.bom_id
                    JOIN users ru ON mr.user_id = ru.user_id
                    WHERE mr.approval_status IN ('pending_sysadmin_approval', 'pending_qc_approval', 'approved', 'cancelled')
                    ORDER BY mr.date_created DESC
                    LIMIT :limit2";
        $zstmt = $conn->prepare($zeroSql);
        $zstmt->bindValue(':limit2', (int) $limit, \PDO::PARAM_INT);
        $zstmt->execute();
        foreach ($zstmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $labels = [
                'pending_sysadmin_approval' => ['For Approval (System Admin)', 'bg-info text-dark'],
                'pending_qc_approval'       => ['For Approval (QC Admin)', 'bg-primary'],
                'approved'                  => ['Approved — No Purchase Requests', 'bg-success'],
                'cancelled'                 => ['Rejected / Cancelled', 'bg-dark'],
            ];
            [$row['tracking_label'], $row['tracking_badge']] = $labels[$row['approval_status']] ?? ['Unknown', 'bg-secondary'];
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * MRP runs the System Admin actually decided (APPROVE/REJECT recorded in
     * audit_logs with target_type = 'mrp_run'), joined back to the run so the
     * admin Approvals hub shows the same run-level tracking view as the
     * warehouse "Runs I Signed" table, with the full review modal available.
     */
    public function getMrpSysadminDecisionRuns($limit = 20) {
        $conn = self::getConnection();
        $sql = "SELECT al.action AS decision, al.created_at AS decided_at,
                       mr.run_id, mr.mrp_ref, mr.fg_code, mr.target_qty, mr.bom_id,
                       mr.bom_id IS NULL AS no_bom, b.bom_code,
                       mr.date_created AS run_date, ru.full_name AS run_user_name,
                       COUNT(so.supplier_order_id) AS item_count,
                       SUM(CASE WHEN so.status = 'requested' THEN 1 ELSE 0 END) AS st_released,
                       SUM(CASE WHEN so.status = 'cancelled' THEN 1 ELSE 0 END) AS st_cancelled
                FROM audit_logs al
                JOIN mrp_runs mr ON mr.run_id = al.target_id
                LEFT JOIN fg_boms b ON b.id = mr.bom_id
                JOIN users ru ON ru.user_id = mr.user_id
                LEFT JOIN supplier_orders so ON so.mrp_run_id = mr.run_id AND so.`remove` = 0
                WHERE al.department = 'admin'
                  AND al.action IN ('APPROVE', 'REJECT')
                  AND al.target_type = 'mrp_run'
                GROUP BY al.log_id, mr.run_id, mr.mrp_ref, mr.fg_code, mr.target_qty, mr.bom_id,
                         b.bom_code, mr.date_created, ru.full_name
                ORDER BY al.created_at DESC
                LIMIT :limit";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':limit', (int) $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if ($row['decision'] === 'REJECT') {
                $row['tracking_label'] = 'Rejected — Cancelled';
                $row['tracking_badge'] = 'bg-danger';
            } elseif ((int) $row['st_released'] > 0) {
                $row['tracking_label'] = 'Approved — In Procurement';
                $row['tracking_badge'] = 'bg-success';
            } else {
                // SysAdmin signed it: zero-lacking runs complete at this gate
                // (no purchase requests; QC inspection happens at Receiving only).
                $row['tracking_label'] = 'Approved — No Purchase Requests';
                $row['tracking_badge'] = 'bg-success';
            }
        }
        unset($row);
        return $rows;
    }

    /**
     * Runs the QC Admin actually decided (APPROVE/REJECT with department='qc'
     * on target_type = 'mrp_run') — the "Runs I Checked" table on the QC
     * MRP Check page.
     */
    public function getMrpQcDecisionRuns($limit = 20) {
        $conn = self::getConnection();
        $sql = "SELECT al.action AS decision, al.created_at AS decided_at,
                       mr.run_id, mr.mrp_ref, mr.fg_code, mr.target_qty, mr.bom_id,
                       mr.bom_id IS NULL AS no_bom, b.bom_code,
                       mr.date_created AS run_date, ru.full_name AS run_user_name,
                       mr.approval_status,
                       (SELECT COUNT(*) FROM supplier_orders so
                        WHERE so.mrp_run_id = mr.run_id AND so.`remove` = 0) AS item_count
                FROM audit_logs al
                JOIN mrp_runs mr ON mr.run_id = al.target_id
                LEFT JOIN fg_boms b ON b.id = mr.bom_id
                JOIN users ru ON ru.user_id = mr.user_id
                WHERE al.department = 'qc'
                  AND al.action IN ('APPROVE', 'REJECT')
                  AND al.target_type = 'mrp_run'
                ORDER BY al.created_at DESC
                LIMIT :limit";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':limit', (int) $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if ($row['decision'] === 'REJECT') {
                $row['tracking_label'] = 'Rejected — Cancelled';
                $row['tracking_badge'] = 'bg-danger';
            } elseif ((int) $row['item_count'] > 0) {
                $row['tracking_label'] = 'Approved — In Procurement';
                $row['tracking_badge'] = 'bg-success';
            } else {
                $row['tracking_label'] = 'Approved — No Purchase Requests';
                $row['tracking_badge'] = 'bg-success';
            }
        }
        unset($row);
        return $rows;
    }

    /**
     * Stock commitment guard for the legacy per-row sysadmin approve path:
     * commits qty_allocated for a run's components only once NO row of the
     * run remains at either approval stage (i.e., the run is fully released).
     * Call after each row-level System Admin approval; no-ops until then.
     */
    public function commitMrpRunStockIfReleased($runId) {
        if (!$runId) return 0;
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $remaining = $conn->prepare(
                "SELECT COUNT(*) FROM supplier_orders
                 WHERE mrp_run_id = :run_id AND `remove` = 0
                   AND status IN ('pending_whse_approval', 'pending_sysadmin_approval', 'pending_qc_approval')"
            );
            $remaining->execute(['run_id' => $runId]);
            if ((int) $remaining->fetchColumn() > 0) {
                // Some requisitions have not passed the System Admin gate yet.
                $conn->commit();
                return 0;
            }
            $items = $conn->prepare(
                "SELECT id, component_item_id, total_reqt, qty_committed
                 FROM mrp_run_items
                 WHERE run_id = :run_id AND total_reqt > 0
                 ORDER BY id ASC FOR UPDATE"
            );
            $items->execute(['run_id' => $runId]);
            $alloc = $conn->prepare(
                "INSERT INTO inventory_balances (item_id, site_code, qty_allocated)
                 VALUES (:item_id, :site_code, :qty)
                 ON DUPLICATE KEY UPDATE qty_allocated = qty_allocated + :qty2"
            );
            $committedCount = 0;
            foreach ($items->fetchAll(\PDO::FETCH_ASSOC) as $item) {
                if (floatval($item['qty_committed']) > 0) continue;
                $alloc->execute([
                    'item_id' => (int) $item['component_item_id'],
                    'site_code' => self::DEFAULT_SITE_CODE,
                    'qty' => floatval($item['total_reqt']),
                    'qty2' => floatval($item['total_reqt']),
                ]);
                $committedCount++;
            }
            $conn->prepare(
                "UPDATE mrp_run_items
                 SET qty_committed = total_reqt
                 WHERE run_id = :run_id AND total_reqt > 0 AND qty_committed = 0"
            )->execute(['run_id' => $runId]);
            $conn->commit();
            return $committedCount;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    // ─── Receiving overage guardrails (System Admin approval) ────────────────
    // Under / partial receipts pass straight through to QC. Over-receipts
    // (received qty beyond the ordered total) are blocked at receiving until
    // the System Admin approves the overage amount (step: warehouse submits
    // the shipment → request stored pending → admin approves → warehouse
    // retried within the approved cap → straight to QC).

    /**
     * Record/refresh a pending overage request for a supplier order: the
     * cumulative received total (IQ received + this shipment - ordered).
     * Kept even while another request is pending (the newest shipment size
     * wins) so warehouse never needs two flows for one line.
     */
    public function requestOverageApproval($id, $requestedOverQty) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare(
                "SELECT status FROM supplier_orders
                 WHERE supplier_order_id = :id AND `remove` = 0 FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) throw new \RuntimeException('Requisition not found.');
            if (in_array($row['status'], ['cancelled', 'rejected', 'received'], true)) {
                throw new \RuntimeException('Cannot request overage approval for a closed/rejected order.');
            }
            $conn->prepare(
                "UPDATE supplier_orders
                 SET overage_request_qty = :qty,
                     overage_request_status = 'pending',
                     last_update = NOW()
                 WHERE supplier_order_id = :id"
            )->execute(['qty' => $requestedOverQty, 'id' => $id]);
            $conn->commit();
            return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /** Pending overage approvals for the System Admin gate list. */
    public function getPendingOverageApprovals() {
        $sql = "SELECT so.supplier_order_id, so.quantity, COALESCE(so.received_qty, 0) AS received_qty,
                       so.overage_request_qty, so.item_id, i.item_code, i.item_description,
                       i.item_uom, so.supplier_name, so.po_ref, so.mrp_ref
                FROM supplier_orders so
                JOIN items i ON so.item_id = i.item_id
                WHERE so.`remove` = 0 AND so.overage_request_status = 'pending'
                ORDER BY so.supplier_order_id ASC";
        return self::getConnection()->query($sql)->fetchAll();
    }

    /** System Admin approval of a pending overage request. */
    public function approveOverageRequest($id, $approverLabel) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare(
                "SELECT overage_request_qty, overage_request_status, item_id, quantity FROM supplier_orders
                 WHERE supplier_order_id = :id AND `remove` = 0 FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) throw new \RuntimeException('Requisition not found.');
            if ($row['overage_request_status'] !== 'pending') {
                throw new \RuntimeException(
                    'This overage request is no longer pending (current state: ' .
                    ($row['overage_request_status'] ?: 'none') . '). Refresh and try again.'
                );
            }
            if (floatval($row['overage_request_qty'] ?? 0) <= 0) {
                throw new \RuntimeException('No overage quantity is attached to this request.');
            }
            $conn->prepare(
                "UPDATE supplier_orders
                 SET overage_request_status = 'approved',
                     overage_approved_qty = overage_request_qty,
                     remarks = CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks, '') = '' THEN '' ELSE ' | ' END,
                                      ' [Overage] Approved +', overage_request_qty, ' beyond ordered by ', :approver, ' on ', NOW()),
                     last_update = NOW()
                 WHERE supplier_order_id = :id"
            )->execute(['id' => $id, 'approver' => $approverLabel]);
            $conn->commit();
            return floatval($row['overage_request_qty']);
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /** System Admin rejection of a pending overage request. */
    public function rejectOverageRequest($id, $reason, $approverLabel) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare(
                "SELECT overage_request_qty, overage_request_status FROM supplier_orders
                 WHERE supplier_order_id = :id AND `remove` = 0 FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) throw new \RuntimeException('Requisition not found.');
            if ($row['overage_request_status'] !== 'pending') {
                throw new \RuntimeException(
                    'This overage request is no longer pending (current state: ' .
                    ($row['overage_request_status'] ?: 'none') . '). Refresh and try again.'
                );
            }
            $conn->prepare(
                "UPDATE supplier_orders
                 SET overage_request_status = 'rejected',
                     remarks = CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks, '') = '' THEN '' ELSE ' | ' END,
                                      ' [Overage] Rejected (', overage_request_qty, ' beyond ordered) by ', :approver, ' on ', NOW(),
                                      ' — Reason: ', :reason),
                     last_update = NOW()
                 WHERE supplier_order_id = :id"
            )->execute(['id' => $id, 'approver' => $approverLabel, 'reason' => $reason !== '' ? $reason : 'not provided']);
            $conn->commit();
            return floatval($row['overage_request_qty']);
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    // ─── Purchasing PO Admin Approval Gate (Processed drafts) ─────────────────
    // When Procurement processes requested items into POs the resulting row is
    // a draft (pending_admin_approval). System Admin approval — and only that
    // (no Warehouse Admin step here) — flips it to the Active 'pending' status
    // that Receiving works from.

    /** System Admin approval of a processed PO draft. Returns the order info. */
    public function approvePurchaseOrderDraft($id, $approverLabel) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare(
                "SELECT status FROM supplier_orders
                 WHERE supplier_order_id = :id AND `remove` = 0 FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) throw new \RuntimeException('Purchasing PO not found.');
            if ($row['status'] !== 'pending_admin_approval') {
                throw new \RuntimeException('This PO is not awaiting System Admin approval (current status: '
                    . ($row['status'] ?: 'unknown') . '). Refresh and try again.');
            }
            $conn->prepare(
                "UPDATE supplier_orders
                 SET status = 'pending', last_update = NOW(),
                     remarks = CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks, '') = '' THEN '' ELSE ' | ' END,
                                      ' [Approval] PO draft approved by ', :approver, ' on ', NOW())
                 WHERE supplier_order_id = :id"
            )->execute(['id' => $id, 'approver' => $approverLabel]);
            $conn->commit();
            return 'pending';
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /** System Admin rejection of a processed PO draft (cancelled with reason). */
    public function rejectPurchaseOrderDraft($id, $reason, $approverLabel) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare(
                "SELECT status FROM supplier_orders
                 WHERE supplier_order_id = :id AND `remove` = 0 FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) throw new \RuntimeException('Purchasing PO not found.');
            if ($row['status'] !== 'pending_admin_approval') {
                throw new \RuntimeException('This PO is not awaiting System Admin approval (current status: '
                    . ($row['status'] ?: 'unknown') . '). Refresh and try again.');
            }
            $conn->prepare(
                "UPDATE supplier_orders
                 SET status = 'cancelled', last_update = NOW(),
                     remarks = CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks, '') = '' THEN '' ELSE ' | ' END,
                                      ' [Approval] PO draft rejected by ', :approver, ' on ', NOW(),
                                      ' — Reason: ', :reason),
                     last_update = NOW()
                 WHERE supplier_order_id = :id"
            )->execute(['id' => $id, 'approver' => $approverLabel, 'reason' => $reason !== '' ? $reason : 'not provided']);
            $conn->commit();
            return 'cancelled';
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Bulk-process selected requisitions into ONE consolidated PO draft.
     *
     * All selected rows must share the same item â€” validated here as well as
     * in the browser, with the offending item codes in the error. The oldest
     * selected row survives with quantity = SUM(selected) and the merged MRP
     * reference tags (MRP1, MRP2, ...); the remaining rows are marked
     * status 'PO_Created' â€” kept visible in Purchasing "All Orders" for audit
     * but excluded from Receiving's tabs and from the MRP pending-coverage
     * math (which only counts requested + open PO statuses), so the summed
     * quantity is never counted twice.
     *
     * Returns ['consolidated_id' => int, 'absorbed' => int, 'total_qty' => float, 'mrp_refs' => array].
     */
    public function batchProcessPurchasingPo($ids, $data) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $ids = array_values(array_unique(array_map('intval', (array) $ids)));
            if (!$ids) {
                throw new \RuntimeException('No orders selected.');
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $checkStmt = $conn->prepare(
                "SELECT so.supplier_order_id, so.item_id, so.quantity, so.status, so.mrp_ref, i.item_code
                 FROM supplier_orders so
                 JOIN items i ON so.item_id = i.item_id
                 WHERE so.supplier_order_id IN ({$placeholders}) AND so.`remove` = 0
                 ORDER BY so.supplier_order_id ASC"
            );
            $checkStmt->execute($ids);
            $rows = $checkStmt->fetchAll();

            if (count($rows) !== count($ids)) {
                throw new \RuntimeException('One or more selected orders were not found or have been deleted.');
            }
          foreach ($rows as $row) {
            if (!in_array($row['status'], ['requested', 'pending_whse_approval', 'pending_admin_approval'])) {
                throw new \RuntimeException("Order #{$row['supplier_order_id']} is not in Requested / Pending WHSE Approval / Pending Admin Approval status. Please refresh and try again.");
            }
        }

            // Validation: every selected row must be the SAME item code.
            $codes = [];
            foreach ($rows as $row) {
                $codes[$row['item_code']] = true;
            }
            if (count($codes) > 1) {
                throw new \RuntimeException(
                    'You can only bulk process requisitions for the SAME item code. Selected items contain: '
                    . implode(', ', array_keys($codes)) . '.'
                );
            }

            // Oldest selected row becomes the single consolidated PO draft.
            $survivorId = (int) $rows[0]['supplier_order_id'];
            $absorbedIds = [];
            $totalQty = 0.0;
            $mrpRefs = [];
            foreach ($rows as $row) {
                $totalQty += floatval($row['quantity']);
                $rowId = (int) $row['supplier_order_id'];
                if ($rowId !== $survivorId) {
                    $absorbedIds[] = $rowId;
                }
                $ref = trim((string) ($row['mrp_ref'] ?? ''));
                if ($ref !== '' && !in_array($ref, $mrpRefs, true)) {
                    $mrpRefs[] = $ref;
                }
            }
            if ($totalQty <= 0) {
                throw new \RuntimeException('Consolidated quantity must be greater than zero.');
            }

            // mrp_ref is varchar(30): keep the merged tag list inside the column.
            $mergedRef = $mrpRefs ? implode(', ', $mrpRefs) : null;
            if ($mergedRef !== null && strlen($mergedRef) > 30) {
                $mergedRef = substr($mergedRef, 0, 30);
            }

            $note = 'Bulk-consolidated ' . count($rows) . ' requisition(s) #'
                  . implode(', ', $ids)
                  . ($mrpRefs ? ' | MRP refs: ' . implode(', ', $mrpRefs) : '');

            // Survivor: the summed PO draft (order_date kept when not posted).
            // Enters the WHSE Admin gate first; po_group_id = self marks it as
            // a processed PO draft (not a born run requisition).
            $conn->prepare(
                "UPDATE supplier_orders
                 SET supplier_name = :supplier_name,
                     quantity = :quantity,
                     unit_cost = :unit_cost,
                     order_date = COALESCE(:order_date, order_date),
                     expected_date = :expected_date,
                     status = 'pending_whse_approval',
                     po_group_id = COALESCE(po_group_id, :group_id),
                     mrp_ref = COALESCE(:mrp_ref, mrp_ref),
                     remarks = CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks, '') = '' THEN '' ELSE ' | ' END, :note),
                     last_update = NOW()
                 WHERE supplier_order_id = :id AND `remove` = 0"
            )->execute([
                'supplier_name' => $data['supplier_name'],
                'quantity' => $totalQty,
                'unit_cost' => $data['unit_cost'],
                'order_date' => $data['order_date'] ?? null,
                'expected_date' => $data['expected_date'] ?? null,
                'group_id' => $survivorId,
                'mrp_ref' => $mergedRef,
                'note' => $note,
                'id' => $survivorId,
            ]);

            // Absorbed rows: marked as converted, never deleted.
            if ($absorbedIds) {
                $absParams = ['note' => 'Consolidated into PO #' . $survivorId];
                $absPlaceholders = [];
                foreach ($absorbedIds as $idx => $absId) {
                    $key = ':a' . $idx;
                    $absPlaceholders[] = $key;
                    $absParams[$key] = $absId;
                }
                $conn->prepare(
                    "UPDATE supplier_orders
                     SET status = 'PO_Created',
                         remarks = CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks, '') = '' THEN '' ELSE ' | ' END, :note),
                         last_update = NOW()
                     WHERE supplier_order_id IN (" . implode(', ', $absPlaceholders) . ") AND `remove` = 0"
                )->execute($absParams);
            }

            $conn->commit();
            return [
                'consolidated_id' => $survivorId,
                'absorbed' => count($absorbedIds),
                'total_qty' => $totalQty,
                'mrp_refs' => $mrpRefs,
            ];
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function cancelPurchasingPo($id) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $order = $this->getPurchasingPoById($id);
            if (!$order) {
                throw new \RuntimeException('Purchasing PO not found.');
            }
            // Cannot cancel once any receipt has been staged: the goods already
            // sit in the QC inspection queue / received counts and must go
            // through inspection instead of being silently voided.
            if (floatval($order['received_qty'] ?? 0) > 0.0001) {
                throw new \RuntimeException('Cannot cancel: this PO already has received goods awaiting inspection.');
            }
            if (in_array($order['status'], ['cancelled', 'received', 'completed', 'partially_received', 'For Inspection'])) {
                throw new \RuntimeException('Cannot cancel a purchasing PO with status "' . ucfirst($order['status']) . '".');
            }

            // Pull any pending QC queue entries for this order so ghost items
            // do not remain in the inspection queue, and release their
            // inspection hold quantity. Staging rows are retained (CANCELLED).
            $pendings = $conn->prepare(
                "SELECT id, item_code, received_qty FROM receiving_items
                 WHERE supplier_order_id = :sid AND qc_status = 'PENDING_QC'"
            );
            $pendings->execute(['sid' => $id]);
            foreach ($pendings->fetchAll() as $pi) {
                $holdQty = floatval($pi['received_qty']);
                if ($holdQty > 0) {
                    $conn->prepare(
                        "UPDATE inventory_balances
                         SET qty_for_inspect = GREATEST(qty_for_inspect - :qty, 0)
                         WHERE item_id = :item_id AND site_code = :site_code"
                    )->execute(['qty' => $holdQty, 'item_id' => $order['item_id'], 'site_code' => self::DEFAULT_SITE_CODE]);
                }
                $conn->prepare(
                    "UPDATE receiving_items
                     SET qc_status = 'CANCELLED',
                         remarks = CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks,'') = '' THEN '' ELSE ' | ' END, 'Cancelled with purchasing PO #', :sid)
                     WHERE id = :rid"
                )->execute(['sid' => $id, 'rid' => $pi['id']]);
            }

            $conn->prepare(
                "UPDATE supplier_orders SET status = 'cancelled', last_update = NOW() WHERE supplier_order_id = :id"
            )->execute(['id' => $id]);

            $conn->commit();
            return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function deletePurchasingPo($id) {
        $sql = "UPDATE supplier_orders SET `remove` = 1 WHERE supplier_order_id = :id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // â”€â”€â”€ Receiving Purchasing PO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Status sets behind the Receiving PO filter tabs.
     * Returns null = no status restriction (All Shipments).
     * A concrete $filters['status'] (dropdown) always wins over the tab.
     */
    private function receivingStatusTabSet($tab) {
        switch ((string) $tab) {
            case 'pending':      // In-Transit: ordered/underway, awaiting receipt
            case '':             // default â€” Receiving has no "All Shipments" view
                return ['pending', 'processed', 'partially_received'];
            case 'inspection':   // received into quarantine, awaiting QC decision
                return ['For Inspection'];
            case 'completed':    // terminal outcomes: approved + rejected + cancelled
                return ['received', 'rejected', 'cancelled'];
            case 'all':          // legacy bookmarked URL only
            default:
                return null;
        }
    }

    /**
     * Per-tab row counts for the receiving status tabs.
     * "requested" lines are excluded â€” they never appear on Receiving.
     */
    public function getReceivingTabCounts() {
        $conn = self::getConnection();
        $sql = "SELECT status, COUNT(*) AS c FROM supplier_orders
                WHERE `remove` = 0
                  AND status NOT IN ('requested', 'pending_whse_approval', 'pending_sysadmin_approval', 'pending_qc_approval', 'pending_admin_approval')
                GROUP BY status";
        $byStatus = [];
        $all = 0;
        foreach ($conn->query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $byStatus[$row['status']] = (int) $row['c'];
            $all += (int) $row['c'];
        }
        $sumOf = function (array $set) use ($byStatus) {
            $total = 0;
            foreach ($set as $status) {
                $total += $byStatus[$status] ?? 0;
            }
            return $total;
        };
        // In-Transit: only lines with a receivable balance (strict rule).
        $pendingCount = (int) $conn->query(
            "SELECT COUNT(*) FROM supplier_orders
             WHERE `remove` = 0 AND status IN ('pending', 'processed', 'partially_received')
               AND (quantity - COALESCE(received_qty, 0)) > 0.0001"
        )->fetchColumn();
        // For QC Inspection: fully-staged lines plus any line with an
        // uninspected (PENDING_QC) batch.
        $inspectionCount = (int) $conn->query(
            "SELECT COUNT(*) FROM supplier_orders so
             WHERE so.`remove` = 0 AND so.status NOT IN ('requested', 'pending_whse_approval', 'pending_sysadmin_approval', 'pending_qc_approval', 'pending_admin_approval', 'received', 'rejected', 'cancelled')
               AND (so.status = 'For Inspection' OR EXISTS (
                   SELECT 1 FROM receiving_items rix
                   WHERE rix.supplier_order_id = so.supplier_order_id AND rix.qc_status = 'PENDING_QC'))"
        )->fetchColumn();
        return [
            'all' => $all,
            'pending' => $pendingCount,
            'inspection' => $inspectionCount,
            'completed' => $sumOf($this->receivingStatusTabSet('completed')),
        ];
    }

    public function getReceivingPoFiltered($filters = []) {
        // Receiving never lists requisitions: procurement owns those until they
        // are processed into supplier orders. Unreleased approval drafts are
        // likewise invisible here (warehouse/admin gate them in Purchasing PO).
        // Excluded for every tab and for the status dropdown alike.
        $where = ["so.`remove` = 0",
                  "so.status NOT IN ('requested', 'pending_whse_approval', 'pending_sysadmin_approval', 'pending_qc_approval', 'pending_admin_approval')"];
        $params = [];

        // Exact status (dropdown) overrides the tab group.
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $statuses = [$filters['status']];
        } else {
            $statuses = $this->receivingStatusTabSet($filters['tab'] ?? '');
        }
        // For QC Inspection tab the status set alone is too narrow — fully-staged
        // lines AND any line with an uninspected batch qualify (see below), so the
        // plain status IN() is replaced by the compound predicate.
        $isInspectionTab = ($filters['tab'] ?? '') === 'inspection' && empty($filters['status']);
        if ($statuses !== null && !$isInspectionTab) {
            $placeholders = [];
            foreach ($statuses as $i => $status) {
                $placeholders[] = ":st{$i}";
                $params["st{$i}"] = $status;
            }
            $where[] = 'so.status IN (' . implode(', ', $placeholders) . ')';
        }
        // Strict In-Transit rule: a line is receivable only while a balance
        // remains — fully-staged ('For Inspection') or fully-received lines
        // never linger in In-Transit even if status data drifts.
        if (($filters['tab'] ?? '') === 'pending' && empty($filters['status'])) {
            $where[] = '(so.quantity - COALESCE(so.received_qty, 0)) > 0.0001';
        }
        // For QC Inspection tab: fully-staged lines AND any line that still has
        // an uninspected batch (e.g. partially_received with a staged 20 while
        // the 10 balance sits in In-Transit). Terminal outcomes stay in Completed.
        if (($filters['tab'] ?? '') === 'inspection' && empty($filters['status'])) {
            $where[] = "so.status NOT IN ('received', 'rejected', 'cancelled')
                        AND (so.status = 'For Inspection' OR EXISTS (
                            SELECT 1 FROM receiving_items rix
                            WHERE rix.supplier_order_id = so.supplier_order_id AND rix.qc_status = 'PENDING_QC'))";
        }
        if (!empty($filters['supplier'])) {
            $where[] = "so.supplier_name = :supplier";
            $params['supplier'] = $filters['supplier'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(so.supplier_name LIKE :search1 OR i.item_code LIKE :search2 OR i.item_description LIKE :search3 OR so.po_ref LIKE :search4 OR so.remarks LIKE :search5)";
            $params['search1'] = '%' . $filters['search'] . '%';
            $params['search2'] = $params['search1'];
            $params['search3'] = $params['search1'];
            $params['search4'] = $params['search1'];
            $params['search5'] = $params['search1'];
        }

        $whereSql = implode(' AND ', $where);
        // PO Ref is the explicit po_ref entered by procurement; '-' when empty.
        $sql = "SELECT so.*, i.item_code, i.item_description, i.item_type,
                       EXISTS (SELECT 1 FROM receiving_items rix
                               WHERE rix.supplier_order_id = so.supplier_order_id
                                 AND rix.qc_status = 'PENDING_QC') AS has_pending_qc,
                       COALESCE(so.uom,
                                (SELECT bi.uom FROM fg_bom_items bi
                                 JOIN fg_boms b ON b.id = bi.bom_id AND b.status = 'active'
                                 WHERE bi.item_id = so.item_id
                                   AND bi.uom IS NOT NULL AND bi.uom <> ''
                                 ORDER BY bi.id DESC LIMIT 1),
                                i.item_uom) AS item_uom,
                       u.full_name AS created_by_name,
                       ru.full_name AS received_by_name,
                       CASE WHEN so.po_ref IS NOT NULL AND TRIM(so.po_ref) <> ''
                            THEN TRIM(so.po_ref)
                            ELSE '-'
                       END AS po_ref_display
                FROM supplier_orders so
                JOIN items i ON so.item_id = i.item_id
                JOIN users u ON so.created_by = u.user_id
                LEFT JOIN users ru ON ru.user_id = so.received_by
                WHERE {$whereSql}
                ORDER BY so.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getPurchasingPoSuppliers() {
        $sql = "SELECT DISTINCT supplier_name FROM supplier_orders WHERE `remove` = 0 AND status IN ('pending', 'processed', 'partially_received', 'For Inspection', 'received', 'rejected', 'cancelled') ORDER BY supplier_name";
        $stmt = self::getConnection()->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function receivePurchasingPo($id, $data) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $order = $this->getPurchasingPoById($id);
            if (!$order) {
                throw new \RuntimeException('Purchasing PO not found.');
            }

            $receivedQty = floatval($data['received_qty']);
            $currentReceived = floatval($order['received_qty'] ?? 0);
            $orderedQty = floatval($order['quantity']);
            $newReceivedQty = $currentReceived + $receivedQty;

            // Server-side guard: exact / partial receipts (<= ordered) pass
            // straight through. Receiving beyond the ordered total is allowed
            // only within an approved overage (overage_request_status =
            // 'approved' caps the excess at overage_approved_qty).
            if ($receivedQty <= 0) {
                throw new \RuntimeException('Received quantity must be greater than zero.');
            }
            $remaining = $orderedQty - $currentReceived;
            $hasApprovedOver = (string) ($order['overage_request_status'] ?? '') === 'approved';
            $approvedOver = $hasApprovedOver ? floatval($order['overage_approved_qty'] ?? 0) : 0.0;
            $receivingCap = $orderedQty + $approvedOver;
            if ($newReceivedQty > $receivingCap + 0.0001) {
                throw new \RuntimeException(
                    'Cannot receive ' . formatQty($receivedQty) . ' — only ' .
                    formatQty(max($remaining + $approvedOver, 0)) . ' remaining (ordered ' .
                    formatQty($orderedQty) . ($approvedOver > 0 ? ' + approved overage ' . formatQty($approvedOver) : '') .
                    '). Receipts beyond the ordered quantity require System Admin overage approval.'
                );
            }
            // Resolve PO ref up front (used for staging + duplicate check).
            // receiving_items.po_ref (NOT NULL) takes the explicit po_ref entered
            // by procurement; only when left blank fall back to the internal id
            // so the QC queue still has an identifier. Never the customer PO number.
            $ref = trim((string) ($order['po_ref'] ?? ''));
            $poRef = $ref !== '' ? $ref : 'SO #' . $id;
            $remarksParts = [];
            if (!empty($data['lot_number'])) $remarksParts[] = 'Lot: ' . $data['lot_number'];
            if (!empty($data['expiry_date'])) $remarksParts[] = 'Expiry: ' . $data['expiry_date'];
            if (!empty($data['remarks'])) $remarksParts[] = $data['remarks'];
            $stagingRemarks = $remarksParts ? implode(' | ', $remarksParts) : null;

            // Every confirmed submission is its own batch: insert a fresh QC
            // queue row and add to supplier_orders.received_qty below. Duplicate
            // protection (double-click / back-resubmit) is handled upstream by
            // the one-shot receipt_token in WarehouseController::receivePurchasingPo,
            // so a genuine second batch while the first is still PENDING_QC is
            // not mistaken for a resubmit and is no longer silently swallowed.

            // Partial receiving: a balance still due keeps the order open as
            // 'partially_received' so warehouse can receive the next batch.
            // Only a fully-ordered receipt gates the line to QC inspection
            // (guarded above). The pending QC batch stays queued regardless,
            // because the queue is driven by receiving_items.qc_status.
            $remainingAfter = max(0, $orderedQty - $newReceivedQty);
            $qcGateStatus = $remainingAfter > 0.0001 ? 'partially_received' : 'For Inspection';
            // Account that transacted this receipt (WarehouseController passes
            // $_SESSION['user_id']); kept on the order as the latest receiver and
            // on each receiving_items batch so multi-batch receipts stay attributable.
            $receivedBy = !empty($data['received_by']) ? (int) $data['received_by'] : null;
            $sql = "UPDATE supplier_orders 
                    SET received_qty = :received_qty,
                        received_date = :received_date,
                        received_by = :received_by,
                        status = :status,
                        last_update = NOW()
                    WHERE supplier_order_id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'received_qty' => $newReceivedQty,
                'received_date' => $data['received_date'],
                'received_by' => $receivedBy,
                'status' => $qcGateStatus,
                'id' => $id
            ]);

            // Stage the receipt for the QC inspection queue (same transaction).
            $conn->prepare("
                INSERT INTO receiving_items
                    (po_ref, supplier, item_code, item_name, uom,
                     ordered_qty, received_qty, passed_qty, rejected_qty,
                     lot_number, expiry_date, received_date, received_by,
                     remarks, qc_status, supplier_order_id)
                VALUES
                    (:po_ref, :supplier, :item_code, :item_name, :uom,
                     :ordered_qty, :received_qty, 0, 0,
                     :lot_number, :expiry_date, :received_date, :received_by,
                     :remarks, 'PENDING_QC', :supplier_order_id)
            ")->execute([
                'po_ref' => $poRef,
                'supplier' => $order['supplier_name'],
                'item_code' => $order['item_code'],
                'item_name' => $order['item_description'],
                'uom' => $order['item_uom'],
                'ordered_qty' => $orderedQty,
                'received_qty' => $receivedQty,
                'lot_number' => $data['lot_number'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'received_date' => $data['received_date'],
                'received_by' => $receivedBy,
                'remarks' => $stagingRemarks,
                'supplier_order_id' => $id,
            ]);
            $receivingItemId = (int) $conn->lastInsertId();

            // Gatekeeping: received goods sit in inspection hold until QC approves them.
            // Available SOH is computed from qty_on_hand only; qty_for_inspect is excluded.
            $conn->prepare("
                INSERT INTO inventory_balances (item_id, site_code, qty_on_hand, qty_for_inspect)
                VALUES (:item_id, :site_code, 0, :qty)
                ON DUPLICATE KEY UPDATE qty_for_inspect = qty_for_inspect + :qty2
            ")->execute([
                'item_id' => $order['item_id'],
                'site_code' => self::DEFAULT_SITE_CODE,
                'qty' => $receivedQty,
                'qty2' => $receivedQty
            ]);

            // Log the receipt details
            if (!empty($data['remarks']) || !empty($data['lot_number'])) {
                $receiptInfo = [];
                if (!empty($data['lot_number'])) $receiptInfo[] = "Lot: {$data['lot_number']}";
                if (!empty($data['expiry_date'])) $receiptInfo[] = "Expiry: {$data['expiry_date']}";
                if (!empty($data['remarks'])) $receiptInfo[] = $data['remarks'];
                
                $receiptRemarks = implode(' | ', $receiptInfo);
                $existingRemarks = $order['remarks'] ?? '';
                $updatedRemarks = $existingRemarks ? $existingRemarks . "\n[Receipt] " . $receiptRemarks : "[Receipt] " . $receiptRemarks;
                
                $conn->prepare("UPDATE supplier_orders SET remarks = :remarks WHERE supplier_order_id = :id")
                    ->execute(['remarks' => $updatedRemarks, 'id' => $id]);
            }

            $conn->commit();
            return ['success' => true, 'receiving_item_id' => $receivingItemId, 'po_ref' => $poRef, 'status' => $qcGateStatus];
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Load the pending QC staging batch (receiving_items) of a supplier order
     * for the "Edit Receiving (staged)" modal â€” lot, expiry, qty, remarks.
     */
    public function getReceivingStagingForEdit($supplierOrderId) {
        $sql = "SELECT ri.id, ri.supplier_order_id, ri.po_ref, ri.item_code, ri.item_name,
                       ri.uom, ri.ordered_qty, ri.received_qty, ri.lot_number, ri.expiry_date,
                       ri.remarks, ri.qc_status, ri.received_date,
                       so.quantity AS ordered_total, so.received_qty AS cumulative_received,
                       so.status AS order_status
                FROM receiving_items ri
                JOIN supplier_orders so ON so.supplier_order_id = ri.supplier_order_id
                WHERE ri.supplier_order_id = :sid AND ri.qc_status = 'PENDING_QC'
                ORDER BY ri.id DESC
                LIMIT 1";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['sid' => intval($supplierOrderId)]);
        return $stmt->fetch();
    }

    /**
     * Save corrections to a staged (PENDING_QC) receiving batch: received qty,
     * lot number, expiry date and remarks. Adjusts the inspection-hold balance
     * and the order's cumulative received qty / status accordingly.
     */
    public function updateReceivingStaging($id, $data) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare(
                "SELECT ri.*, so.quantity AS ordered_total, so.received_qty AS cumulative_received, so.item_id,
                        so.overage_request_status, so.overage_approved_qty
                 FROM receiving_items ri
                 JOIN supplier_orders so ON so.supplier_order_id = ri.supplier_order_id
                 WHERE ri.id = :id FOR UPDATE"
            );
            $stmt->execute(['id' => intval($id)]);
            $row = $stmt->fetch();
            if (!$row) {
                throw new \RuntimeException('Staged receipt not found.');
            }
            if ($row['qc_status'] !== 'PENDING_QC') {
                throw new \RuntimeException('Only batches still awaiting QC inspection can be edited.');
            }

            $newQty = floatval($data['received_qty']);
            $oldQty = floatval($row['received_qty']);
            if ($newQty <= 0) {
                throw new \RuntimeException('Received quantity must be greater than zero.');
            }
            // Cumulative received excluding this batch must fit the ordered
            // total, plus any System Admin approved overage.
            $otherReceived = floatval($row['cumulative_received']) - $oldQty;
            $stagingCap = floatval($row['ordered_total'])
                + ((string) ($row['overage_request_status'] ?? '') === 'approved'
                    ? floatval($row['overage_approved_qty'] ?? 0) : 0.0);
            if ($otherReceived + $newQty > $stagingCap + 0.0001) {
                throw new \RuntimeException(
                    'Received quantity cannot exceed the receiving limit of ' .
                    formatQty($stagingCap) . ' (ordered ' . formatQty(floatval($row['ordered_total'])) .
                    ' incl. any approved overage).'
                );
            }

            $remarks = trim((string) ($data['remarks'] ?? ''));
            $lot = trim((string) ($data['lot_number'] ?? ''));
            $expiry = trim((string) ($data['expiry_date'] ?? ''));

            $conn->prepare(
                "UPDATE receiving_items
                 SET received_qty = :qty, lot_number = :lot, expiry_date = :expiry, remarks = :remarks
                 WHERE id = :id"
            )->execute([
                'qty' => $newQty,
                'lot' => $lot !== '' ? $lot : null,
                'expiry' => $expiry !== '' ? $expiry : null,
                'remarks' => $remarks !== '' ? $remarks : null,
                'id' => $id,
            ]);

            // Mirror the delta onto the inspection hold and the order totals.
            $delta = $newQty - $oldQty;
            if (abs($delta) > 0.0001) {
                $conn->prepare(
                    "UPDATE inventory_balances
                     SET qty_for_inspect = GREATEST(qty_for_inspect + :delta, 0)
                     WHERE item_id = :item_id AND site_code = :site_code"
                )->execute([
                    'delta' => $delta,
                    'item_id' => $row['item_id'],
                    'site_code' => self::DEFAULT_SITE_CODE,
                ]);

                $newCumulative = max(0, floatval($row['cumulative_received']) + $delta);
                $remaining = floatval($row['ordered_total']) - $newCumulative;
                // A balance still due keeps the order open as 'partially_received'
                // so the remaining qty stays receivable under In-Transit; only a
                // fully-staged order gates to 'For Inspection' (0 balance left).
                $status = $remaining > 0.0001 ? 'partially_received' : 'For Inspection';
                $conn->prepare(
                    "UPDATE supplier_orders
                     SET received_qty = :qty, status = :status, last_update = NOW()
                     WHERE supplier_order_id = :sid"
                )->execute([
                    'qty' => $newCumulative,
                    'status' => $status,
                    'sid' => $row['supplier_order_id'],
                ]);
            }

            $conn->commit();
            return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    // â”€â”€â”€ MRP Snapshots â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function saveMrpRun($po_id, $customer_id, $user_id, $sections, $consolidated) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("INSERT INTO mrp_runs (po_id, customer_id, user_id) VALUES (:po_id, :customer_id, :user_id)");
            $stmt->execute(['po_id' => $po_id, 'customer_id' => $customer_id, 'user_id' => $user_id]);
            $runId = $conn->lastInsertId();

            $itemStmt = $conn->prepare("INSERT INTO mrp_run_items (run_id, fg_item_id, component_item_id, total_reqt, soh, allocated, pending, excess, remarks)
                VALUES (:run_id, :fg_item_id, :component_item_id, :total_reqt, :soh, :allocated, :pending, :excess, :remarks)");

            foreach ($sections as $sec) {
                foreach ($sec['components'] as $comp) {
                    $itemStmt->execute([
                        'run_id' => $runId,
                        'fg_item_id' => $sec['fg_item_id'],
                        'component_item_id' => $comp['component_item_id'],
                        'total_reqt' => $comp['total_reqt'],
                        'soh' => $comp['soh'],
                        'allocated' => $comp['allocated'],
                        'pending' => $comp['pending'],
                        'excess' => $comp['excess'],
                        'remarks' => $comp['remarks']
                    ]);
                }
            }

            $conn->commit();
            return $runId;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Save an FG/customer/target MRP calculation: history run + component lines,
     * committed-stock allocation, and the purchase requests for lacking items â€”
     * all in one transaction so a failure leaves no half-saved run.
     *
     * $rows    â€” every BOM component: component_item_id, required, soh, allocated
     * $lacking â€” [item_id => ['quantity' => float, 'uom' => string]] recomputed by caller
     */
    /**
     * Next MRP reference in the MRP#YYYY-XXXX sequence (e.g. MRP#2026-0001).
     * The sequence restarts every calendar year and only reads refs that
     * already match the current-year format, so legacy refs (MRP-#<id>, MRP1)
     * never feed the counter.
     *
     * $lockForUpdate serializes concurrent generators by locking the newest
     * mrp_runs row â€” call it inside a transaction (saveMrpCalculationRun does)
     * so two simultaneous saves cannot mint the same number.
     */
    public function generateNextMrpRef($conn = null, $lockForUpdate = false) {
        $conn = $conn ?: self::getConnection();
        $prefix = 'MRP#' . date('Y') . '-';

        if ($lockForUpdate) {
            // 1) Serialize generators on the newest row (and the table's
            //    supremum gap when empty). 2) Locking read of the sequence:
            //    a plain SELECT here would reuse this transaction's earlier
            //    snapshot (saveMrpCalculationRun reads items first) and miss
            //    refs committed after it â€” FOR UPDATE always sees the latest.
            $conn->query('SELECT run_id FROM mrp_runs ORDER BY run_id DESC LIMIT 1 FOR UPDATE');
        }

        $stmt = $conn->prepare(
            "SELECT mrp_ref FROM mrp_runs WHERE mrp_ref LIKE :prefix" . ($lockForUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->execute([':prefix' => $prefix . '%']);

        $maxNum = 0;
        $existing = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $ref) {
            $existing[(string) $ref] = true;
            if (preg_match('/-(\d+)$/', (string) $ref, $m)) {
                $maxNum = max($maxNum, intval($m[1]));
            }
        }

        // Defensive: skip any candidate already taken in this year's sequence.
        do {
            $maxNum++;
            $candidate = $prefix . str_pad($maxNum, 4, '0', STR_PAD_LEFT);
        } while (isset($existing[$candidate]));

        return $candidate;
    }

    public function saveMrpCalculationRun($customerId, $fgItemId, $targetQty, $userId, array $rows, array $lacking, $customRef = null, $bomId = null) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $fgStmt = $conn->prepare("SELECT item_code FROM items WHERE item_id = :id");
            $fgStmt->execute(['id' => $fgItemId]);
            $fgCode = $fgStmt->fetchColumn();
            if ($fgCode === false) {
                throw new \RuntimeException('Finished good not found.');
            }

            // Server-assigned sequence (MRP#YYYY-XXXX), generated fresh inside
            // this transaction under the row lock so the saved ref is unique.
            // The prompt modal only previews this number â€” $customRef is ignored.
            $mrpRef = $this->generateNextMrpRef($conn, true);

            // Zero-lacking runs have no requisitions, so their gate progress
            // lives on the run header (approval_status) instead of
            // supplier_orders — they still pass all three approval gates.
            $runApprovalStatus = empty($lacking) ? 'pending_whse_approval' : null;

            $conn->prepare(
                "INSERT INTO mrp_runs (po_id, customer_id, user_id, fg_item_id, bom_id, fg_code, target_qty, mrp_ref, approval_status)
                 VALUES (NULL, :customer_id, :user_id, :fg_item_id, :bom_id, :fg_code, :target_qty, :mrp_ref, :approval_status)"
            )->execute([
                'customer_id' => $customerId > 0 ? $customerId : null,
                'user_id' => $userId,
                'fg_item_id' => $fgItemId,
                'bom_id' => !empty($bomId) ? intval($bomId) : null,
                'fg_code' => $fgCode,
                'target_qty' => $targetQty,
                'mrp_ref' => $mrpRef,
                'approval_status' => $runApprovalStatus,
            ]);
            $runId = (int) $conn->lastInsertId();

            $itemStmt = $conn->prepare(
                "INSERT INTO mrp_run_items
                    (run_id, fg_item_id, component_item_id, total_reqt, soh, allocated,
                     pending, excess, remarks, qty_committed)
                 VALUES
                    (:run_id, :fg_item_id, :component_item_id, :total_reqt, :soh, :allocated,
                     :pending, :excess, :remarks, 0)"
            );

            // FROZEN: a run still in For Approval is only a drafted calculation —
            // qty_committed = 0 and inventory_balances.qty_allocated is untouched, so
            // ALLOCATED totals and Pending PO / RR never see unapproved runs. Stock is
            // committed (qty_committed = total_reqt) by decideMrpRun() when the System
            // Admin finally releases the run to Procurement.
            foreach ($rows as $row) {
                $itemId = (int) $row['component_item_id'];
                $required = floatval($row['required']);
                $soh = floatval($row['soh']);
                $allocated = floatval($row['allocated']);
                $pendingPoRr = floatval($row['pending_po_rr'] ?? 0);
                // Mirrors the Calculate screen: excess is measured against
                // unallocated physical SOH only (never negative). Pending
                // PO/RR is stored informational and never nets the shortage.
                $available = max(0, $soh - $allocated);
                $excess = $available - $required;

                if ($required <= 0) {
                    $remarks = 'NO NEED';
                } elseif ($excess < 0) {
                    $remarks = 'LACKING';
                } elseif ($excess < $available * 0.1) {
                    $remarks = 'LOW STOCK';
                } else {
                    $remarks = 'OK';
                }

                $itemStmt->execute([
                    'run_id' => $runId,
                    'fg_item_id' => $fgItemId,
                    'component_item_id' => $itemId,
                    'total_reqt' => $required,
                    'soh' => $soh,
                    'allocated' => $allocated,
                    'pending' => $pendingPoRr,
                    'excess' => $excess,
                    'remarks' => $remarks,
                ]);
            }

            // Purchase requests for lacking items: every MRP run INSERTs its
            // own distinct draft row (pending_whse_approval) — quantities push
            // to Procurement only after BOTH approval gates (warehouse admin,
            // then system admin) flip the status to 'requested'. Earlier
            // requisitions (and procurement's own) are never UPDATEd or
            // deleted here, so each snapshot keeps its own requisition trail.
            // Rows are tagged with this run id (mrp_run_id + mrp_ref + the
            // legacy remarks format) so deleteMrpRun() finds them and the
            // Purchasing table shows the MRP origin.
            $queued = 0;
            foreach ($lacking as $itemId => $target) {
                $this->createSupplierOrder([
                    'supplier_name' => 'Pending Selection',
                    'item_id' => $itemId,
                    'quantity' => $target['quantity'],
                    'uom' => $target['uom'] ?? null,
                    'unit_cost' => 0,
                    'order_date' => date('Y-m-d'),
                    'expected_date' => null,
                    'remarks' => 'Auto-generated from MRP Run #' . $runId,
                    'created_by' => $userId,
                    'status' => 'pending_whse_approval',
                    'po_id' => null,
                    'mrp_run_id' => $runId,
                    'mrp_ref' => $mrpRef,
                ]);
                $queued++;
            }

            $conn->commit();
            return [
                'run_id' => $runId,
                'queued' => $queued,
                'committed' => 0,
                'mrp_ref' => $mrpRef,
                'zero_lacking' => empty($lacking),
            ];
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function getMrpRunsByPO($po_id) {
        $sql = "SELECT mr.*, u.full_name AS user_name
                FROM mrp_runs mr
                JOIN users u ON mr.user_id = u.user_id
                WHERE mr.po_id = :po_id
                ORDER BY mr.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['po_id' => $po_id]);
        return $this->deriveMrpRunStatuses($stmt->fetchAll());
    }

    /**
     * Snapshot detail rows for the history modal (and PDF / delete release).
     *
     * FROZEN columns: total_reqt, soh, allocated, pending, excess and remarks are
     * selected explicitly from mrp_run_items â€” captured at save time â€” so callers
     * always get the historical figures. The items joins supply static descriptors
     * only (codes, descriptions, uom) and never touch view_inventory_status.
     *
     * Values are returned RAW: the PDF runs number_format()/abs() on them and
     * deleteMrpRun() subtracts qty_committed. Display formatting is applied by
     * WarehouseController::mrpRunDetail() for the modal JSON.
     */
    public function getMrpRunItems($run_id) {
        $sql = "SELECT mri.id, mri.run_id, mri.fg_item_id, mri.component_item_id,
                       mri.total_reqt, mri.soh, mri.allocated, mri.pending,
                       mri.excess, mri.remarks, mri.qty_committed,
                       i.item_code AS fg_code, i.item_description AS fg_name,
                       ic.item_code AS component_code, ic.item_description AS component_name,
                       ic.item_uom AS component_uom, ic.item_type AS component_type
                FROM mrp_run_items mri
                JOIN items i ON mri.fg_item_id = i.item_id
                JOIN items ic ON mri.component_item_id = ic.item_id
                WHERE mri.run_id = :run_id
                ORDER BY mri.fg_item_id, mri.component_item_id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['run_id' => $run_id]);
        return $stmt->fetchAll();
    }

    public function getExistingMrpRunForPO($po_id) {
        $sql = "SELECT run_id FROM mrp_runs WHERE po_id = :po_id LIMIT 1";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['po_id' => $po_id]);
        return $stmt->fetch();
    }

    /**
     * Every supplier order a run generated: matched on mrp_run_id first, then
     * the legacy remarks convention â€” procurement may have rewritten remarks
     * after processing, and rows written before the column existed only carry
     * the remarks form.
     */
    public function getLinkedSupplierOrdersForRun($run_id) {
        $sql = "SELECT supplier_order_id, item_id, quantity, status
                FROM supplier_orders
                WHERE (mrp_run_id = :run_id
                       OR remarks = CONCAT('Auto-generated from MRP Run #', :run_id2))
                  AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['run_id' => $run_id, 'run_id2' => $run_id]);
        return $stmt->fetchAll();
    }

    /**
     * MRP Check items modal data: every frozen component of the run joined to
     * its latest Raw Material Analysis Report (if any) and its requisition
     * (if any) so the QC Admin can inspect and track per-item progress.
     */
    public function getMrpRunQcItems($runId) {
        $conn = self::getConnection();
        $items = $this->getMrpRunItems($runId);
        $latestReports = [];
        $stmt = $conn->prepare(
            "SELECT car.* FROM qc_analysis_reports car
             WHERE car.run_id = :run_id
               AND car.report_id = (
                   SELECT car2.report_id FROM qc_analysis_reports car2
                   WHERE car2.run_id = car.run_id AND car2.component_item_id = car.component_item_id
                   ORDER BY car2.date_inspected DESC, car2.report_id DESC LIMIT 1
               )"
        );
        $stmt->execute(['run_id' => (int) $runId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $latestReports[(int) $row['component_item_id']] = $row;
        }
        $requisitions = [];
        foreach ($this->getLinkedSupplierOrdersForRun($runId) as $order) {
            $requisitions[(int) $order['item_id']] = $order;
        }

        // Items whose stock already passed Receiving Inspection — their RM was
        // quality-approved there, so the MRP Check needs no new report for them.
        $approvedFromReceiving = [];
        $componentIds = array_map('intval', array_column($items, 'component_item_id'));
        if ($componentIds) {
            $placeholders = implode(',', array_fill(0, count($componentIds), '?'));
            $apStmt = $conn->prepare(
                "SELECT i.item_id, MAX(ri.received_date) AS last_passed_date
                 FROM receiving_items ri
                 JOIN items i ON ri.item_code = i.item_code
                 WHERE ri.qc_status = 'PASSED' AND i.item_id IN ($placeholders)
                 GROUP BY i.item_id"
            );
            $apStmt->execute($componentIds);
            foreach ($apStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $approvedFromReceiving[(int) $row['item_id']] = $row['last_passed_date'];
            }
        }

        foreach ($items as &$item) {
            $itemId = (int) $item['component_item_id'];
            $report = $latestReports[$itemId] ?? null;
            $item['disposition'] = $report['disposition'] ?? null;
            $item['latest_report_id'] = $report['report_id'] ?? null;
            $item['inspected_at'] = $report['date_inspected'] ?? null;
            // No run-level report yet, but the RM already passed Receiving
            // Inspection → the MRP Check skips the analysis report for it.
            $item['already_qc_approved'] = $report === null && isset($approvedFromReceiving[$itemId]);
            $item['last_qc_passed_date'] = $approvedFromReceiving[$itemId] ?? null;
            // Not lacking (frozen excess >= 0) → no purchase request exists for
            // this component → nothing for QC to decide, no report needed.
            $item['no_po_needed'] = $report === null && floatval($item['excess'] ?? 0) >= 0;
            $item['requisition_status'] = $requisitions[$itemId]['status'] ?? null;
            $item['supplier_order_id'] = $requisitions[$itemId]['supplier_order_id'] ?? null;
        }
        unset($item);
        return $items;
    }

    /**
     * Auto-complete the QC gate once every component of the run carries a
     * final disposition (APPROVED / WAIVED / REJECTED — HOLD and uninspected
     * keep the run open):
     *   - Approved/Waived requisitions  → released ('requested')
     *   - Rejected requisitions         → cancelled with a [QC] note
     *   - Zero-lacking runs             → approval_status 'approved'
     *   - Stock reserved only for Approved/Waived components
     * Components with no run-level report are auto-approved when EITHER:
     *   (a) their stock already PASSED Receiving Inspection, or
     *   (b) they are not lacking (frozen excess >= 0) → no purchase request
     *       exists, so there is nothing for QC to decide (No PO Needed).
     * Returns ['complete' => bool, released, cancelled, reserved items, ...].
     */
    public function completeMrpRunCheck($runId) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            $lock = $conn->prepare(
                "SELECT run_id, mrp_ref, approval_status FROM mrp_runs WHERE run_id = :run_id FOR UPDATE"
            );
            $lock->execute(['run_id' => (int) $runId]);
            $run = $lock->fetch(\PDO::FETCH_ASSOC);
            if (!$run) throw new \RuntimeException('MRP run not found.');

            // Latest disposition per component.
            $stmt = $conn->prepare(
                "SELECT component_item_id, disposition, report_id FROM qc_analysis_reports
                 WHERE run_id = :run_id
                 ORDER BY date_inspected ASC, report_id ASC"
            );
            $stmt->execute(['run_id' => (int) $runId]);
            $latest = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $latest[(int) $row['component_item_id']] = $row['disposition'];
            }

            $allItems = $conn->prepare(
                "SELECT component_item_id, excess FROM mrp_run_items WHERE run_id = :run_id"
            );
            $allItems->execute(['run_id' => (int) $runId]);
            $frozenExcess = [];
            foreach ($allItems->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $frozenExcess[(int) $row['component_item_id']] = floatval($row['excess']);
            }
            $componentIds = array_keys($frozenExcess);

            // Components without a run report whose stock already passed
            // Receiving Inspection — auto-approved, no report needed.
            $approvedFromReceiving = [];
            $unreported = [];
            foreach ($componentIds as $cid) {
                if (!isset($latest[$cid])) $unreported[] = $cid;
            }
            if ($unreported) {
                $placeholders = implode(',', array_fill(0, count($unreported), '?'));
                $apStmt = $conn->prepare(
                    "SELECT DISTINCT i.item_id
                     FROM receiving_items ri
                     JOIN items i ON ri.item_code = i.item_code
                     WHERE ri.qc_status = 'PASSED' AND i.item_id IN ($placeholders)"
                );
                $apStmt->execute($unreported);
                foreach ($apStmt->fetchAll(\PDO::FETCH_COLUMN) as $aid) {
                    $approvedFromReceiving[(int) $aid] = true;
                }
            }

            $final = ['APPROVED', 'WAIVED', 'REJECTED'];
            $approvedIds = [];
            $rejectedIds = [];
            foreach ($componentIds as $cid) {
                $disp = $latest[$cid] ?? null;
                if ($disp === null && isset($approvedFromReceiving[$cid])) {
                    $disp = 'APPROVED';   // quality-approved at Receiving Inspection
                }
                if ($disp === null && $frozenExcess[$cid] >= 0) {
                    // Not lacking → no purchase request exists → nothing to
                    // decide (No PO Needed) → counts as approved.
                    $disp = 'APPROVED';
                }
                if ($disp === null || !in_array($disp, $final, true)) {
                    $conn->rollBack();
                    return ['complete' => false];
                }
                if ($disp === 'REJECTED') {
                    $rejectedIds[] = $cid;
                } else {
                    $approvedIds[] = $cid;
                }
            }

            $approverLabel = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'QC') . ' (qc)';

            // Requisition-bearing runs: per-item release / cancel.
            $released = 0;
            $cancelled = 0;
            if (empty($run['approval_status'])) {
                foreach ($approvedIds as $cid) {
                    $upd = $conn->prepare(
                        "UPDATE supplier_orders SET status = 'requested', last_update = NOW(),
                             remarks = CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks,'') = '' THEN '' ELSE ' | ' END, :note)
                         WHERE mrp_run_id = :run_id AND item_id = :item_id AND `remove` = 0
                           AND status = 'pending_qc_approval'"
                    );
                    $upd->execute([
                        'run_id' => (int) $runId,
                        'item_id' => $cid,
                        'note' => ' [QC] Released after Raw Material Analysis — Approved/Waived by ' . $approverLabel . ' on ' . date('Y-m-d H:i'),
                    ]);
                    $released += $upd->rowCount();
                }
                foreach ($rejectedIds as $cid) {
                    $upd = $conn->prepare(
                        "UPDATE supplier_orders SET status = 'cancelled', last_update = NOW(),
                             remarks = CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks,'') = '' THEN '' ELSE ' | ' END, :note)
                         WHERE mrp_run_id = :run_id AND item_id = :item_id AND `remove` = 0
                           AND status = 'pending_qc_approval'"
                    );
                    $upd->execute([
                        'run_id' => (int) $runId,
                        'item_id' => $cid,
                        'note' => ' [QC] Rejected at Raw Material Analysis by ' . $approverLabel . ' on ' . date('Y-m-d H:i'),
                    ]);
                    $cancelled += $upd->rowCount();
                }
            }

            // Reserve stock for Approved/Waived components only.
            if (!empty($approvedIds)) {
                $this->bookMrpRunAllocation($conn, $runId, $approvedIds);
            }
            $reserved = count($approvedIds);

            // Zero-lacking runs complete on the run header.
            if (!empty($run['approval_status'])) {
                $conn->prepare(
                    "UPDATE mrp_runs SET approval_status = 'approved' WHERE run_id = :run_id"
                )->execute(['run_id' => (int) $runId]);
            }

            $conn->commit();
            return [
                'complete' => true,
                'mrp_ref' => $run['mrp_ref'],
                'released' => $released,
                'cancelled' => $cancelled,
                'reserved' => $reserved,
                'zero_lacking' => !empty($run['approval_status']),
            ];
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Cancel the purchase requests this run generated while they are still
     * unconverted (Requested / Draft). POs already converted to an active order
     * (pending/processed), received, approved or rejected are deliberately NOT
     * touched â€” they are real commitments that outlive the snapshot.
     * Returns the number of orders cancelled.
     */
    public function cancelLinkedSupplierOrdersForRun($run_id) {
        $sql = "UPDATE supplier_orders SET status = 'cancelled', last_update = NOW()
                WHERE (mrp_run_id = :run_id
                       OR remarks = CONCAT('Auto-generated from MRP Run #', :run_id2))
                  AND LOWER(status) IN ('requested', 'draft', 'pending_whse_approval', 'pending_sysadmin_approval', 'pending_qc_approval') AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['run_id' => $run_id, 'run_id2' => $run_id]);
        return $stmt->rowCount();
    }

    /**
     * Purge (soft delete) the requisitions this run generated that were never
     * converted: Requested, Draft and already-Cancelled lines get remove = 1 so
     * they disappear from the Purchasing PO screens entirely instead of piling
     * up as dead Cancelled rows in History. Converted / received / rejected
     * lines keep remove = 0 â€” they are real commitments that outlive the run.
     * Returns the number of orders purged.
     */
    public function purgeUnfulfilledSupplierOrdersForRun($run_id) {
        $sql = "UPDATE supplier_orders SET `remove` = 1, last_update = NOW()
                WHERE (mrp_run_id = :run_id
                       OR remarks = CONCAT('Auto-generated from MRP Run #', :run_id2))
                  AND LOWER(status) IN ('requested', 'draft', 'cancelled', 'pending_whse_approval', 'pending_sysadmin_approval', 'pending_qc_approval')
                  AND `remove` = 0";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['run_id' => $run_id, 'run_id2' => $run_id]);
        return $stmt->rowCount();
    }

    public function deleteMrpRun($run_id) {
        $conn = self::getConnection();
        $conn->beginTransaction();
        try {
            // 1. Identify every PO this run generated. Unconverted ones
            //    (Requested/Draft/Cancelled) are purged below; anything already
            //    converted / received / approved is preserved as-is.
            $linkedOrders = $this->getLinkedSupplierOrdersForRun($run_id);
            $preserved = 0;
            foreach ($linkedOrders as $order) {
                $status = strtolower(trim((string) ($order['status'] ?? '')));
                if (!in_array($status, ['requested', 'draft', 'cancelled', 'pending_whse_approval', 'pending_sysadmin_approval', 'pending_qc_approval'], true)) {
                    $preserved++;
                }
            }
            $purged = $this->purgeUnfulfilledSupplierOrdersForRun($run_id);

            // 2. Release the stock this run committed (qty_committed), so deleting a
            //    snapshot does not leave permanently reserved ALLOCATED behind.
            $releaseStmt = $conn->prepare(
                "UPDATE inventory_balances
                 SET qty_allocated = GREATEST(0, qty_allocated - :qty)
                 WHERE item_id = :item_id AND site_code = :site_code"
            );
            foreach ($this->getMrpRunItems($run_id) as $runItem) {
                $qty = floatval($runItem['qty_committed'] ?? 0);
                if ($qty > 0) {
                    $releaseStmt->execute(['qty' => $qty, 'item_id' => $runItem['component_item_id'], 'site_code' => self::DEFAULT_SITE_CODE]);
                }
            }

            // 3. Remove the run (mrp_run_items goes with it via ON DELETE CASCADE).
            $runStmt = $conn->prepare("DELETE FROM mrp_runs WHERE run_id = :run_id");
            $runStmt->execute(['run_id' => $run_id]);

            $conn->commit();

            $message = "MRP Run #{$run_id} deleted.";
            if ($purged > 0) {
                $message .= " {$purged} unfulfilled purchase request(s) removed.";
            }
            if ($preserved > 0) {
                $message .= " {$preserved} active PO(s) left untouched.";
            }
            return ['success' => true, 'purged' => $purged, 'preserved' => $preserved, 'message' => $message];
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function getAllMrpRuns() {
        // LEFT JOINs: FG/customer-target runs have no purchase order, and the
        // customer may be unset ("All Customers") â€” they must still be listed.
        $sql = "SELECT mr.*, u.full_name AS user_name,
                       po.customer_po_number, po.total_quantity, po.produced_quantity, po.delivered_quantity,
                       c.customer_name, c.customer_code
                FROM mrp_runs mr
                JOIN users u ON mr.user_id = u.user_id
                LEFT JOIN purchase_orders po ON mr.po_id = po.po_id
                LEFT JOIN customers c ON mr.customer_id = c.customer_id
                ORDER BY mr.date_created DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $this->deriveMrpRunStatuses($stmt->fetchAll());
    }

    /**
     * Approval-state badge data for MRP history rows, derived from the run's
     * linked requisition statuses (drafts born pending_whse_approval). Worst?
     * No — most relevant: the furthest stage any linked requisition has
     * reached decides the run badge, except pending stages which always dominate
     * so partially approved runs stay visible as awaiting approval.
     * Returns each run with ['approval_label', 'approval_badge', 'approval_statuses'].
     */
    public function deriveMrpRunStatuses(array $runs) {
        $priority = [
            'pending_whse_approval'     => ['label' => 'For Approval (WHSE Admin)',        'badge' => 'bg-warning text-dark',        'p' => 0],
            'pending_sysadmin_approval' => ['label' => 'For Approval (System Admin)',      'badge' => 'bg-info text-dark',           'p' => 0],
            'pending_qc_approval'       => ['label' => 'For Approval (QC Admin)',          'badge' => 'bg-primary',                  'p' => 0],
            'pending_admin_approval'    => ['label' => 'For Approval (Admin · PO Draft)',  'badge' => 'bg-info text-dark',           'p' => 1],
            'requested'                 => ['label' => 'Approved — In Procurement (Req.)', 'badge' => 'bg-success',                  'p' => 2],
            'pending'                   => ['label' => 'Approved — Active PO',             'badge' => 'bg-primary',                  'p' => 3],
            'processed'                 => ['label' => 'Approved — Active PO',             'badge' => 'bg-primary',                  'p' => 3],
            'partially_received'        => ['label' => 'Approved — Partially Received',    'badge' => 'bg-secondary',                'p' => 4],
            'For Inspection'            => ['label' => 'Approved — For QC Inspection',     'badge' => 'bg-primary',                  'p' => 4],
            'PO_Created'                => ['label' => 'Approved — PO Consolidated',       'badge' => 'bg-light text-dark border',   'p' => 5],
            'received'                  => ['label' => 'Approved — Fully Received',        'badge' => 'bg-success',                  'p' => 6],
            'rejected'                  => ['label' => 'Rejected',                         'badge' => 'bg-danger',                   'p' => 7],
            'cancelled'                 => ['label' => 'Cancelled / Rejected',             'badge' => 'bg-dark',                     'p' => 7],
        ];
        // Zero-lacking runs: gate progress lives on mrp_runs.approval_status.
        $runLevel = [
            'pending_whse_approval'     => ['label' => 'For Approval (WHSE Admin)',        'badge' => 'bg-warning text-dark'],
            'pending_sysadmin_approval' => ['label' => 'For Approval (System Admin)',      'badge' => 'bg-info text-dark'],
            'pending_qc_approval'       => ['label' => 'For Approval (QC Admin)',          'badge' => 'bg-primary'],
            'approved'                  => ['label' => 'Approved — No Purchase Requests',  'badge' => 'bg-success'],
            'cancelled'                 => ['label' => 'Cancelled / Rejected',             'badge' => 'bg-dark'],
        ];
        foreach ($runs as $idx => $run) {
            if (!empty($run['approval_status'])) {
                $meta = $runLevel[$run['approval_status']] ?? ['label' => ucfirst($run['approval_status']), 'badge' => 'bg-secondary'];
                $runs[$idx]['approval_label'] = $meta['label'];
                $runs[$idx]['approval_badge'] = $meta['badge'];
                $runs[$idx]['approval_statuses'] = [$run['approval_status']];
                continue;
            }
            $statuses = [];
            foreach ($this->getLinkedSupplierOrdersForRun($run['run_id']) as $order) {
                $statuses[strtolower(trim((string) $order['status']))] = true;
            }
            if (empty($statuses)) {
                $runs[$idx]['approval_label'] = 'No Requisitions';
                $runs[$idx]['approval_badge'] = 'bg-secondary-subtle text-secondary';
                $runs[$idx]['approval_statuses'] = [];
                continue;
            }
            $best = null;
            foreach ($statuses as $status => $_) {
                $meta = $priority[$status] ?? ['label' => ucfirst($status), 'badge' => 'bg-secondary', 'p' => 9];
                if ($best === null || $meta['p'] < $best['p'] || ($meta['p'] === $best['p'] && $meta['p'] === 0)) {
                    $best = $meta;
                }
            }
            $runs[$idx]['approval_label'] = $best['label'];
            $runs[$idx]['approval_badge'] = $best['badge'];
            $runs[$idx]['approval_statuses'] = array_keys($statuses);
        }
        return $runs;
    }

    public function getMrpRunSnapshotData($run_id) {
        $conn = self::getConnection();

        $run = $conn->prepare("SELECT mr.*, u.full_name AS user_name
                FROM mrp_runs mr
                JOIN users u ON mr.user_id = u.user_id
                WHERE mr.run_id = :run_id");
        $run->execute(['run_id' => $run_id]);
        $runRow = $run->fetch();
        if (!$runRow) return false;

        // FG-based runs (po_id NULL) still need a printable snapshot — build a
        // lightweight header from the run + customer + BOM instead of a PO.
        $poHeader = null;
        if (!empty($runRow['po_id'])) {
            $poStmt = $conn->prepare("SELECT po.*, c.customer_name, c.customer_code
                    FROM purchase_orders po
                    JOIN customers c ON po.customer_id = c.customer_id
                    WHERE po.po_id = :po_id");
            $poStmt->execute(['po_id' => $runRow['po_id']]);
            $poHeader = $poStmt->fetch();
            if (!$poHeader) return false;
            $allPoItems = $this->getPOItemsWithBOM($runRow['po_id']);
        } else {
            $customerName = '';
            $customerCode = '';
            if (!empty($runRow['customer_id'])) {
                $cStmt = $conn->prepare("SELECT customer_name, customer_code FROM customers WHERE customer_id = :id");
                $cStmt->execute(['id' => $runRow['customer_id']]);
                $cust = $cStmt->fetch(\PDO::FETCH_ASSOC);
                if ($cust) {
                    $customerName = $cust['customer_name'];
                    $customerCode = $cust['customer_code'];
                }
            }
            $poHeader = [
                'po_id' => null,
                'customer_po_number' => $runRow['mrp_ref'] ?? ('MRP-#' . $run_id),
                'customer_name' => $customerName,
                'customer_code' => $customerCode,
                'production_type' => '',
            ];
            $allPoItems = [];
        }

        $snapshotItems = $this->getMrpRunItems($run_id);

        $batchMeta = [];
        foreach ($allPoItems as $poi) {
            if (empty($poi['bom_id'])) continue;
            $fillVolume = floatval($poi['fill_volume'] ?? $poi['batch_qty'] ?? 1) ?: 1;
            $uom = $poi['uom'] ?? $poi['batch_uom'] ?? 'PCS';
            $divisor = floatval($poi['batch_unit_divisor'] ?? 1000) ?: 1000;
            $isLegacy = !empty($poi['is_legacy_formula']);
            $targetQty = floatval($poi['quantity']);
            $batchMeta[$poi['item_id']] = [
                'target_qty' => $poi['quantity'],
                'item_uom' => $poi['item_uom'],
                'fill_volume' => $fillVolume,
                'uom' => $uom,
                'batch_unit_divisor' => $divisor,
                'is_legacy_formula' => $isLegacy,
                'bom_code' => $poi['bom_code'] ?? '',
                'is_default' => !empty($poi['is_default'] ?? 0),
                // Legacy display keys (lot-based)
                'batch_qty' => $fillVolume,
                'batch_uom' => $uom,
                'batches_needed' => $isLegacy ? ($targetQty / $fillVolume) : 0,
                'bulk_batch' => $isLegacy ? 0 : (($targetQty * $fillVolume) / $divisor),
                'fg_code' => $poi['item_code'],
                'fg_name' => $poi['item_description'],
            ];
        }
        // FG-based run: pull the BOM meta + FG identity from the run header.
        if (empty($batchMeta) && empty($runRow['po_id']) && !empty($runRow['fg_item_id'])) {
            $fgStmt = $conn->prepare("SELECT item_code, item_description, item_uom FROM items WHERE item_id = :id");
            $fgStmt->execute(['id' => $runRow['fg_item_id']]);
            $fg = $fgStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            $fillVolume = 1;
            $uom = $fg['item_uom'] ?? 'PCS';
            $divisor = 1000;
            $isLegacy = 0;
            if (!empty($runRow['bom_id'])) {
                $bomStmt = $conn->prepare("SELECT b.batch_qty AS fill_volume, b.batch_uom AS uom, b.batch_unit_divisor, b.is_legacy_formula,
                        b.bom_code, b.is_default
                    FROM fg_boms b WHERE b.id = :id");
                $bomStmt->execute(['id' => $runRow['bom_id']]);
                if ($bom = $bomStmt->fetch(\PDO::FETCH_ASSOC)) {
                    $fillVolume = floatval($bom['fill_volume'] ?? 1) ?: 1;
                    $uom = $bom['uom'] ?: $uom;
                    $divisor = floatval($bom['batch_unit_divisor'] ?? 1000) ?: 1000;
                    $isLegacy = !empty($bom['is_legacy_formula']);
                }
            }
            $targetQty = floatval($runRow['target_qty'] ?? 0);
            $batchMeta[$runRow['fg_item_id']] = [
                'target_qty' => $runRow['target_qty'],
                'item_uom' => $fg['item_uom'] ?? '',
                'fill_volume' => $fillVolume,
                'uom' => $uom,
                'batch_unit_divisor' => $divisor,
                'is_legacy_formula' => $isLegacy,
                'bom_code' => $bom['bom_code'] ?? '',
                'is_default' => !empty($bom['is_default'] ?? 0),
                'batch_qty' => $fillVolume,
                'batch_uom' => $uom,
                'batches_needed' => $isLegacy ? ($targetQty / $fillVolume) : 0,
                'bulk_batch' => $isLegacy ? 0 : (($targetQty * $fillVolume) / $divisor),
                'fg_code' => $fg['item_code'] ?? ($runRow['fg_code'] ?? ''),
                'fg_name' => $fg['item_description'] ?? '',
            ];
        }

        $sectionsGrouped = [];
        foreach ($snapshotItems as $item) {
            $fgId = $item['fg_item_id'];
            if (!isset($sectionsGrouped[$fgId])) {
                $meta = $batchMeta[$fgId] ?? [
                    'target_qty' => 0, 'item_uom' => '', 'fill_volume' => 1, 'uom' => 'PCS',
                    'batch_unit_divisor' => 1000, 'is_legacy_formula' => 0,
                    'bom_code' => '', 'is_default' => 0,
                    'batch_qty' => 1, 'batch_uom' => 'PCS', 'batches_needed' => 0, 'bulk_batch' => 0,
                    'fg_code' => $item['fg_code'], 'fg_name' => $item['fg_name'],
                ];
                $sectionsGrouped[$fgId] = [
                    'fg_item_id' => $fgId,
                    'fg_code' => $meta['fg_code'],
                    'fg_name' => $meta['fg_name'],
                    'target_qty' => $meta['target_qty'],
                    'item_uom' => $meta['item_uom'],
                    'fill_volume' => $meta['fill_volume'],
                    'uom' => $meta['uom'],
                    'batch_unit_divisor' => $meta['batch_unit_divisor'],
                    'is_legacy_formula' => $meta['is_legacy_formula'],
                    'bom_code' => $meta['bom_code'] ?? '',
                    'is_default' => !empty($meta['is_default'] ?? 0),
                    'batch_qty' => $meta['batch_qty'],
                    'batch_uom' => $meta['batch_uom'],
                    'batches_needed' => $meta['batches_needed'],
                    'bulk_batch' => $meta['bulk_batch'],
                    'components' => [],
                ];
            }
            $sectionsGrouped[$fgId]['components'][] = [
                'component_item_id' => $item['component_item_id'],
                'item_code' => $item['component_code'],
                'item_description' => $item['component_name'],
                'item_uom' => $item['component_uom'],
                'category' => $item['component_type'] ?? '-',
                'total_reqt' => floatval($item['total_reqt']),
                'soh' => floatval($item['soh']),
                'allocated' => floatval($item['allocated']),
                'pending' => floatval($item['pending']),
                // Frozen rows carry no per-supplier split; pending itself is
                // the Pending PO/RR figure. (Was pending - allocated, which
                // only ever evaluated to 0 while pending duplicated allocated.)
                'supplier_pending' => 0,
                'excess' => floatval($item['excess']),
                'remarks' => $item['remarks'],
            ];
        }
        $mrpSections = array_values($sectionsGrouped);

        $consolidatedMap = [];
        foreach ($mrpSections as $section) {
            foreach ($section['components'] as $row) {
                $key = $row['item_code'];
                if (!isset($consolidatedMap[$key])) {
                    $consolidatedMap[$key] = $row;
                } else {
                    $consolidatedMap[$key]['total_reqt'] += $row['total_reqt'];
                    $consolidatedMap[$key]['soh'] = $row['soh'];
                    $consolidatedMap[$key]['allocated'] += $row['allocated'];
                    $consolidatedMap[$key]['pending'] += $row['pending'];
                    $consolidatedMap[$key]['supplier_pending'] = ($consolidatedMap[$key]['supplier_pending'] ?? 0) + ($row['supplier_pending'] ?? 0);
                    $consolidatedMap[$key]['excess'] = ($consolidatedMap[$key]['soh'] - $consolidatedMap[$key]['allocated']) - $consolidatedMap[$key]['total_reqt'] + ($consolidatedMap[$key]['supplier_pending'] ?? 0);
                    if ($consolidatedMap[$key]['excess'] < 0) {
                        $consolidatedMap[$key]['remarks'] = 'LACKING';
                    }
                }
            }
        }
        $consolidated = array_values($consolidatedMap);

        return [
            'po' => $poHeader,
            'mrpSections' => $mrpSections,
            'consolidated' => $consolidated,
            'userName' => $runRow['user_name'],
            'userDept' => $_SESSION['department'] ?? '',
            'snapshotDate' => $runRow['date_created'],
            'runId' => $run_id,
            'mrpRef' => $runRow['mrp_ref'] ?? ('MRP-#' . $run_id),
        ];
    }

    // ─── Stock In/Out (stock_movements ledger) ─────────────────────────────────

    /** Purchasing POs that can be referenced for a stock IN entry. */
    public function getStockInPoOptions() {
        $sql = "SELECT so.supplier_order_id, so.po_ref, so.supplier_name,
                       i.item_code, i.item_description, i.item_id, i.item_uom
                FROM supplier_orders so
                JOIN items i ON so.item_id = i.item_id
                WHERE so.`remove` = 0
                  AND so.status NOT IN ('cancelled', 'rejected')
                GROUP BY so.supplier_order_id
                ORDER BY so.supplier_order_id DESC
                LIMIT 200";
        return self::getConnection()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Recent stock movements (newest first). */
    public function getStockMovements($limit = 50) {
        $sql = "SELECT sm.*, i.item_code, i.item_description, i.item_uom, u.full_name AS created_by_name
                FROM stock_movements sm
                JOIN items i ON sm.item_id = i.item_id
                LEFT JOIN users u ON sm.created_by = u.user_id
                ORDER BY sm.date_created DESC, sm.movement_id DESC
                LIMIT " . (int) $limit;
        return self::getConnection()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Insert one stock movement row. The SOH delta is applied separately by
     * ItemImportModel::adjustSohDelta inside the controller's flow.
     */
    public function recordStockMovement(array $data) {
        $sql = "INSERT INTO stock_movements (movement_type, item_id, site_code, qty, reference_type, reference_no, remarks, created_by)
                VALUES (:movement_type, :item_id, :site_code, :qty, :reference_type, :reference_no, :remarks, :created_by)";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'movement_type' => $data['movement_type'],
            'item_id' => (int) $data['item_id'],
            'site_code' => $data['site_code'],
            'qty' => (float) $data['qty'],
            'reference_type' => $data['reference_type'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
        return self::getConnection()->lastInsertId();
    }

    /** Purchasing-PO status counts for the procurement dashboard. */
    public function getProcurementStatusCounts() {
        $byStatus = [];
        foreach (self::getConnection()->query(
            "SELECT status, COUNT(*) AS c FROM supplier_orders WHERE `remove` = 0 GROUP BY status"
        )->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $byStatus[$row['status']] = (int) $row['c'];
        }
        return [
            'requested' => $byStatus['requested'] ?? 0,
            'processed' => $byStatus['processed'] ?? 0,
            'partially_received' => $byStatus['partially_received'] ?? 0,
            'received' => $byStatus['received'] ?? 0,
            'all' => array_sum($byStatus),
        ];
    }

}