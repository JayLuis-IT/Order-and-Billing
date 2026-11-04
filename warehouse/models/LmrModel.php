<?php
namespace App\Models;

use App\Core\BaseModel;

class LmrModel extends BaseModel {
    public function getAll($status = null) {
        $sql = "SELECT lmr.*
                FROM lmr_orders lmr";
        $params = [];

        if ($status && $status !== 'all') {
            $sql .= " WHERE lmr.lmr_status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY lmr.created_at DESC";

        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $order['items'] = $this->getItemsByLmrId($order['lmr_id']);
            $order['item_code'] = $order['items'][0]['item_code'] ?? '-';
            $order['qty_ordered'] = $order['items'][0]['qty_ordered'] ?? 0;
        }

        return $orders;
    }

    public function getItemsByLmrId($lmrId) {
        $sql = "SELECT * FROM lmr_order_items WHERE lmr_id = :lmr_id ORDER BY lri_id ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['lmr_id' => $lmrId]);
        return $stmt->fetchAll();
    }

    /**
     * Line-item shell built from the LMR header itself (bom_code / item_code /
     * item_description / qty_ordered). Used when the entry form stored only the
     * header and no lmr_order_items rows exist, so print/view/validation still
     * resolve the BOM breakdown.
     */
    public function headerAsItemRow($order) {
        return [[
            'lri_id' => 0,
            'lmr_id' => $order['lmr_id'] ?? null,
            'item_id' => null,
            'item_code' => $order['item_code'] ?? null,
            'item_description' => $order['item_description'] ?? null,
            'uom' => null,
            'item_type' => null,
            'site' => $order['lmr_site'] ?? null,
            'qty_ordered' => $order['qty_ordered'] ?? 0,
            'so_number' => null,
            'bom_code' => $order['bom_code'] ?? null,
        ]];
    }

    /**
     * Line items for breakdown/print purposes, falling back to the header row
     * when the LMR has no lmr_order_items entries (header-only entry form).
     */
    public function getItemsForDisplay($lmrId) {
        $items = $this->getItemsByLmrId($lmrId);
        if (empty($items)) {
            $order = $this->findById($lmrId);
            if ($order) {
                $items = $this->headerAsItemRow($order);
            }
        }
        return $items;
    }

    public function getActiveBoms() {
        $sql = "SELECT b.bom_code, b.fg_item_id, b.customer_id, i.item_code, i.item_description
                FROM fg_boms b
                JOIN items i ON i.item_id = b.fg_item_id
                WHERE b.status = 'active' AND i.`remove` = 0
                ORDER BY b.bom_code ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getItemIdByBomCode($bomCode) {
        $sql = "SELECT fg_item_id FROM fg_boms WHERE bom_code = :bom_code AND status = 'active' ORDER BY id ASC LIMIT 1";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['bom_code' => $bomCode]);
        return $stmt->fetchColumn() ?: null;
    }

    public function poBelongsToCustomer($poNumber, $customerId) {
        $sql = "SELECT po_id FROM purchase_orders
                WHERE customer_po_number = :po_number
                  AND customer_id = :customer_id
                  AND `remove` = 0
                LIMIT 1";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['po_number' => $poNumber, 'customer_id' => (int) $customerId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Latest receiving lot number per item code (QC positions preferred:
     * PASSED, then any). Used to print the actual lot the RM arrived with.
     */
    public function getReceivingLotNumbers(array $itemCodes) {
        $itemCodes = array_values(array_unique(array_filter($itemCodes, function ($c) {
            return $c !== null && trim((string) $c) !== '';
        })));
        if (empty($itemCodes)) {
            return [];
        }
        $in = implode(',', array_fill(0, count($itemCodes), '?'));
        $sql = "SELECT item_code, lot_number
                FROM receiving_items
                WHERE item_code IN ($in)
                  AND lot_number IS NOT NULL AND lot_number <> ''
                  AND qc_status <> 'CANCELLED'
                ORDER BY item_code,
                         CASE qc_status WHEN 'PASSED' THEN 0 WHEN 'PENDING_QC' THEN 1 ELSE 2 END ASC,
                         received_date DESC,
                         id DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($itemCodes);
        $lots = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $code = $row['item_code'];
            if (!isset($lots[$code])) {
                $lots[$code] = $row['lot_number'];
            }
        }
        return $lots;
    }

    /**
     * FIFO lot list for one component item, sourced from inventory_stock
     * (per-lot rows written by QC receiving) and joined to receiving_items
     * for the received / expiry dates. Available per lot = on hand - blocked
     * - already reserved by other active LMR lot allocations.
     * Sorted oldest receipt first so FIFO rotation is front of the list.
     */
    public function getAvailableLotsForItem($itemId, $excludeLmrId = null) {
        $itemId = intval($itemId);
        if ($itemId <= 0) {
            return [];
        }
        $excludeLmrId = intval($excludeLmrId);

        $sql = "
            SELECT s.lot_number,
                   SUM(GREATEST(s.qty_on_hand - s.qty_blocked, 0)) AS raw_qty,
                   MAX(r.received_date) AS received_date,
                   MIN(r.expiry_date) AS expiry_date,
                   COALESCE(SUM(GREATEST(s.qty_on_hand - s.qty_blocked, 0)), 0) -
                   COALESCE(ua.allocated_qty, 0) AS available_qty
            FROM inventory_stock s
            LEFT JOIN receiving_items r ON r.id = s.source_receiving_item_id
            LEFT JOIN (
                SELECT item_id, lot_number, SUM(allocated_qty) AS allocated_qty
                FROM lmr_lot_allocations
                WHERE status = 'active'"
                . ($excludeLmrId > 0 ? " AND lmr_id <> :exclude_lmr" : "") . "
                GROUP BY item_id, lot_number
            ) ua ON ua.item_id = s.item_id AND ua.lot_number = s.lot_number
            WHERE s.item_id = :item_id
              AND s.status = 'PASSED'
            GROUP BY s.lot_number, ua.allocated_qty
            HAVING available_qty > 0
            ORDER BY (MAX(r.received_date) IS NULL) ASC,
                     MAX(r.received_date) ASC,
                     MIN(r.expiry_date) ASC";

        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(array_merge(
            $excludeLmrId > 0 ? ['exclude_lmr' => $excludeLmrId] : [],
            ['item_id' => $itemId]
        ));

        return array_map(function ($row) {
            return [
                'lot_number' => $row['lot_number'],
                'available_qty' => round(floatval($row['available_qty']), 4),
                'qty_on_hand' => round(floatval($row['raw_qty']), 4),
                'received_date' => $row['received_date'] ? date('Y-m-d', strtotime($row['received_date'])) : null,
                'expiry_date' => $row['expiry_date'] ? date('Y-m-d', strtotime($row['expiry_date'])) : null,
            ];
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Active lot allocations for one LMR ordered by item then receipt order.
     */
    public function getLotAllocations($lmrId) {
        $stmt = self::getConnection()->prepare("
            SELECT id, lmr_id, lri_id, item_id, item_code, lot_number, allocated_qty
            FROM lmr_lot_allocations
            WHERE lmr_id = :lmr_id AND status = 'active'
            ORDER BY item_id, id ASC
        ");
        $stmt->execute(['lmr_id' => intval($lmrId)]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Replace the active lot allocations of an LMR with the given set.
     * Each allocation: [item_id, item_code, lot_number, allocated_qty].
     * lri_id is re-linked separately (see linkLotAllocationsToItemRows).
     */
    public function saveLotAllocations($lmrId, array $allocations) {
        $pdo = self::getConnection();
        $cancel = $pdo->prepare("
            UPDATE lmr_lot_allocations SET status = 'cancelled', updated_at = NOW()
            WHERE lmr_id = :lmr_id AND status = 'active'
        ");
        $cancel->execute(['lmr_id' => intval($lmrId)]);

        if (empty($allocations)) {
            return;
        }

        $insert = $pdo->prepare("
            INSERT INTO lmr_lot_allocations
                (lmr_id, lri_id, item_id, item_code, lot_number, allocated_qty, status, created_by)
            VALUES
                (:lmr_id, :lri_id, :item_id, :item_code, :lot_number, :qty, 'active', :created_by)
        ");
        foreach ($allocations as $alloc) {
            $qty = floatval($alloc['allocated_qty'] ?? 0);
            if ($qty <= 0 || $alloc['lot_number'] === '' || $alloc['lot_number'] === null) {
                continue;
            }
            $insert->execute([
                'lmr_id' => intval($lmrId),
                'lri_id' => $alloc['lri_id'] ?? null,
                'item_id' => intval($alloc['item_id']),
                'item_code' => $alloc['item_code'] ?? null,
                'lot_number' => $alloc['lot_number'],
                'qty' => round($qty, 4),
                'created_by' => $_SESSION['user_id'] ?? null,
            ]);
        }
    }

    /**
     * Legacy-aware required quantity for one BOM component.
     * Legacy: (orderQty / batch_qty) * dosage * wastage
     * New:    RM → ((orderQty * fill_volume) / divisor) * dosage% * wastage
     *         others → orderQty * dosage * wastage
     */
    private function computeRequiredQty($orderQty, $bom, $comp) {
        $dosage = floatval($comp['dosage_rate'] ?? 0);
        $wastage = floatval($comp['wastage_allowance_pct'] ?? 0);

        if (!empty($bom['is_legacy_formula'])) {
            $batchQty = floatval($bom['fill_volume'] ?? $bom['batch_qty'] ?? 1) ?: 1;
            $base = ($orderQty / $batchQty) * $dosage;
        } elseif (($comp['item_type'] ?? '') === 'RM') {
            $fillVolume = floatval($bom['fill_volume'] ?? $bom['batch_qty'] ?? 0);
            $divisor = floatval($bom['batch_unit_divisor'] ?? 1000) ?: 1000;
            $base = (($orderQty * $fillVolume) / $divisor) * ($dosage / 100);
        } else {
            $base = $orderQty * $dosage;
        }

        return $base * (1 + $wastage / 100);
    }

    /**
     * Display/meta keys for a BOM against an order quantity.
     * Exposes both legacy keys (batch_qty/batches_needed) and new keys
     * (fill_volume/uom/batch_unit_divisor/bulk_batch).
     */
    private function bomMeta($bom, $orderQty) {
        $fillVolume = floatval($bom['fill_volume'] ?? $bom['batch_qty'] ?? 1) ?: 1;
        $uom = $bom['uom'] ?? $bom['batch_uom'] ?? 'PCS';
        $divisor = floatval($bom['batch_unit_divisor'] ?? 1000) ?: 1000;
        $isLegacy = !empty($bom['is_legacy_formula']);

        return [
            'is_legacy_formula' => $isLegacy,
            'fill_volume' => $fillVolume,
            'uom' => $uom,
            'batch_unit_divisor' => $divisor,
            'batch_qty' => $fillVolume,
            'batch_uom' => $uom,
            'batches_needed' => $isLegacy ? ($orderQty / $fillVolume) : 0,
            'bulk_batch' => $isLegacy ? 0 : (($orderQty * $fillVolume) / $divisor),
        ];
    }

    public function create($data, $items) {
        $pdo = self::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("INSERT INTO lmr_orders (
                lmr_number, lmr_type, lmr_site, order_date, due_date, planned_start_date, priority,
                reference_no, lmr_status, customer_id, customer_code, customer_name,
                batch_lot_no, lot_series, lot_number, date_started, date_finished, expiry_date,
                po_number, bom_code, item_code, item_description, qty_ordered, created_by
            ) VALUES (
                :lmr_number, :lmr_type, :lmr_site, :order_date, :due_date, :planned_start_date, :priority,
                :reference_no, :lmr_status, :customer_id, :customer_code, :customer_name,
                :batch_lot_no, :lot_series, :lot_number, :date_started, :date_finished, :expiry_date,
                :po_number, :bom_code, :item_code, :item_description, :qty_ordered, :created_by
            )");

            $stmt->execute([
                'lmr_number' => trim($data['lmr_number']),
                'lmr_type' => $data['lmr_type'] ?? 'Standard',
                'lmr_site' => $data['lmr_site'] ?? '001 - Sterling Technopark',
                'order_date' => $data['order_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'planned_start_date' => $data['planned_start_date'] ?? null,
                'priority' => (int) ($data['priority'] ?? 3),
                'reference_no' => $data['reference_no'] ?? null,
                'lmr_status' => $data['lmr_status'] ?? 'Planned',
                'customer_id' => $data['customer_id'] ?? null,
                'customer_code' => $data['customer_code'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'batch_lot_no' => $data['batch_lot_no'] ?? null,
                'lot_series' => $data['lot_series'] ?? null,
                'lot_number' => $data['lot_number'] ?? null,
                'date_started' => $data['date_started'] ?? null,
                'date_finished' => $data['date_finished'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'po_number' => $data['po_number'] ?? null,
                'bom_code' => $data['bom_code'] ?? null,
                'item_code' => $data['item_code'] ?? null,
                'item_description' => $data['item_description'] ?? null,
                'qty_ordered' => isset($data['qty_ordered']) && $data['qty_ordered'] !== '' && $data['qty_ordered'] !== null ? (float) $data['qty_ordered'] : null,
                'created_by' => $_SESSION['user_id'] ?? null
            ]);

            $lmrId = $pdo->lastInsertId();

            if (!empty($items)) {
                $itemStmt = $pdo->prepare("INSERT INTO lmr_order_items (
                    lmr_id, item_id, item_code, item_description, uom, item_type, site, qty_ordered, so_number, bom_code
                ) VALUES (
                    :lmr_id, :item_id, :item_code, :item_description, :uom, :item_type, :site, :qty_ordered, :so_number, :bom_code
                )");

                foreach ($items as $item) {
                    $itemStmt->execute([
                        'lmr_id' => $lmrId,
                        'item_id' => $item['item_id'] ?? null,
                        'item_code' => $item['item_code'] ?? null,
                        'item_description' => $item['item_description'] ?? null,
                        'uom' => $item['uom'] ?? null,
                        'item_type' => $item['item_type'] ?? null,
                        'site' => $item['site'] ?? '001 - Sterling Technopark',
                        'qty_ordered' => (float) ($item['qty_ordered'] ?? 0),
                        'so_number' => $item['so_number'] ?? null,
                        'bom_code' => $item['bom_code'] ?? null,
                    ]);
                }
            }

            $pdo->commit();
            return $lmrId;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function update($lmrId, $data, $items) {
        $pdo = self::getConnection();
        $pdo->beginTransaction();

        try {
            $cur = $pdo->prepare("SELECT lmr_status FROM lmr_orders WHERE lmr_id = :lmr_id LIMIT 1");
            $cur->execute(['lmr_id' => $lmrId]);
            $oldStatus = $cur->fetchColumn();
            $newStatus = $data['lmr_status'] ?? 'Planned';

            $cur = $pdo->prepare("SELECT COUNT(*) FROM mrp_allocations WHERE lmr_id = :lmr_id AND status = 'active'");
            $cur->execute(['lmr_id' => $lmrId]);
            $hadActive = (int) $cur->fetchColumn() > 0;
            $oldTerminal = in_array($oldStatus, ['Completed', 'Cancelled'], true);
            $newTerminal = in_array($newStatus, ['Completed', 'Cancelled'], true);

            // Release BEFORE the item wipe: mrp_allocations.lri_id cascades away
            // with lmr_order_items, and that cascade does not return
            // the reserved stock — releasing first keeps qty_allocated honest.
            if ($hadActive || ($newTerminal && !$oldTerminal)) {
                $this->deallocateMaterials($lmrId);
            }

            $stmt = $pdo->prepare("UPDATE lmr_orders SET
                lmr_number = :lmr_number, lmr_type = :lmr_type, lmr_site = :lmr_site,
                order_date = :order_date, due_date = :due_date, planned_start_date = :planned_start_date,
                priority = :priority, reference_no = :reference_no, lmr_status = :lmr_status,
                customer_id = :customer_id, customer_code = :customer_code, customer_name = :customer_name,
                batch_lot_no = :batch_lot_no, lot_series = :lot_series, lot_number = :lot_number,
                date_started = :date_started, date_finished = :date_finished, expiry_date = :expiry_date,
                po_number = :po_number, bom_code = :bom_code, item_code = :item_code,
                item_description = :item_description, qty_ordered = :qty_ordered, updated_at = NOW()
                WHERE lmr_id = :lmr_id");

            $stmt->execute([
                'lmr_id' => $lmrId,
                'lmr_number' => trim($data['lmr_number']),
                'lmr_type' => $data['lmr_type'] ?? 'Standard',
                'lmr_site' => $data['lmr_site'] ?? '001 - Sterling Technopark',
                'order_date' => $data['order_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'planned_start_date' => $data['planned_start_date'] ?? null,
                'priority' => (int) ($data['priority'] ?? 3),
                'reference_no' => $data['reference_no'] ?? null,
                'lmr_status' => $data['lmr_status'] ?? 'Planned',
                'customer_id' => $data['customer_id'] ?? null,
                'customer_code' => $data['customer_code'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'batch_lot_no' => $data['batch_lot_no'] ?? null,
                'lot_series' => $data['lot_series'] ?? null,
                'lot_number' => $data['lot_number'] ?? null,
                'date_started' => $data['date_started'] ?? null,
                'date_finished' => $data['date_finished'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'po_number' => $data['po_number'] ?? null,
                'bom_code' => $data['bom_code'] ?? null,
                'item_code' => $data['item_code'] ?? null,
                'item_description' => $data['item_description'] ?? null,
                'qty_ordered' => isset($data['qty_ordered']) && $data['qty_ordered'] !== '' && $data['qty_ordered'] !== null ? (float) $data['qty_ordered'] : null,
            ]);

            $delStmt = $pdo->prepare("DELETE FROM lmr_order_items WHERE lmr_id = :lmr_id");
            $delStmt->execute(['lmr_id' => $lmrId]);

            if (!empty($items)) {
                $itemStmt = $pdo->prepare("INSERT INTO lmr_order_items (
                    lmr_id, item_id, item_code, item_description, uom, item_type, site, qty_ordered, so_number, bom_code
                ) VALUES (
                    :lmr_id, :item_id, :item_code, :item_description, :uom, :item_type, :site, :qty_ordered, :so_number, :bom_code
                )");

                foreach ($items as $item) {
                    $itemStmt->execute([
                        'lmr_id' => $lmrId,
                        'item_id' => $item['item_id'] ?? null,
                        'item_code' => $item['item_code'] ?? null,
                        'item_description' => $item['item_description'] ?? null,
                        'uom' => $item['uom'] ?? null,
                        'item_type' => $item['item_type'] ?? null,
                        'site' => $item['site'] ?? '001 - Sterling Technopark',
                        'qty_ordered' => (float) ($item['qty_ordered'] ?? 0),
                        'so_number' => $item['so_number'] ?? null,
                        'bom_code' => $item['bom_code'] ?? null,
                    ]);
                }
            }

            if ($hadActive && $newStatus === 'Released') {
                $this->allocateMaterials($lmrId);
            }

            $pdo->commit();
            return $lmrId;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function findById($lmrId) {
        $sql = "SELECT * FROM lmr_orders WHERE lmr_id = :lmr_id LIMIT 1";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['lmr_id' => $lmrId]);
        return $stmt->fetch();
    }

    public function updateStatus($lmrId, $status) {
        $pdo = self::getConnection();
        $ownsTx = !$pdo->inTransaction();
        if ($ownsTx) {
            $pdo->beginTransaction();
        }
        try {
            $cur = $pdo->prepare("SELECT lmr_status FROM lmr_orders WHERE lmr_id = :lmr_id LIMIT 1");
            $cur->execute(['lmr_id' => $lmrId]);
            $oldStatus = $cur->fetchColumn();

            $terminal = ['Completed', 'Cancelled'];
            if ($oldStatus !== false
                && in_array($status, $terminal, true)
                && !in_array($oldStatus, $terminal, true)) {
                $this->deallocateMaterials($lmrId);
            }

            $sql = "UPDATE lmr_orders SET lmr_status = :status, updated_at = NOW() WHERE lmr_id = :lmr_id";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute(['status' => $status, 'lmr_id' => $lmrId]);

            if ($ownsTx) {
                $pdo->commit();
            }
            return $result;
        } catch (\Exception $e) {
            if ($ownsTx) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function allocateMaterials($lmrId) {
        $pdo = self::getConnection();
        $warehouseModel = new \App\Models\WarehouseModel();

        $items = $this->getItemsByLmrId($lmrId);

        foreach ($items as $item) {
            $bomCode = $item['bom_code'] ?? null;
            $qtyOrdered = floatval($item['qty_ordered'] ?? 0);

            if (empty($bomCode) || $qtyOrdered <= 0) continue;

            $bom = $warehouseModel->getBomByCode($bomCode);
            if (!$bom) continue;

            $components = $warehouseModel->getBOMComponentsWithStock([$bom['id']]);

            foreach ($components as $comp) {
                $requiredQty = round($this->computeRequiredQty($qtyOrdered, $bom, $comp), 4);

                if ($requiredQty <= 0) continue;

                $allocStmt = $pdo->prepare("
                    INSERT INTO mrp_allocations (lmr_id, lri_id, item_id, allocated_qty, status)
                    VALUES (:lmr_id, :lri_id, :item_id, :qty, 'active')
                    ON DUPLICATE KEY UPDATE
                        allocated_qty = allocated_qty + VALUES(allocated_qty),
                        lri_id = VALUES(lri_id),
                        status = 'active',
                        updated_at = NOW()
                ");
                $allocStmt->execute([
                    'lmr_id' => $lmrId,
                    'lri_id' => $item['lri_id'],
                    'item_id' => $comp['component_item_id'],
                    'qty' => $requiredQty,
                ]);

                $invStmt = $pdo->prepare("
                    INSERT INTO inventory_balances (item_id, site_code, qty_on_hand, qty_allocated)
                    VALUES (:item_id, :site_code, 0, :qty)
                    ON DUPLICATE KEY UPDATE
                        qty_allocated = qty_allocated + VALUES(qty_allocated),
                        updated_at = NOW()
                ");
                $invStmt->execute([
                    'item_id' => $comp['component_item_id'],
                    'site_code' => self::DEFAULT_SITE_CODE,
                    'qty' => $requiredQty,
                ]);
            }
        }
    }

    public function deallocateMaterials($lmrId) {
        $pdo = self::getConnection();

        $stmt = $pdo->prepare("SELECT item_id, allocated_qty FROM mrp_allocations WHERE lmr_id = :lmr_id AND status = 'active'");
        $stmt->execute(['lmr_id' => $lmrId]);
        $allocations = $stmt->fetchAll();

        foreach ($allocations as $alloc) {
            // Mirror allocateMaterials(): release only from the canonical site
            // row, otherwise every site row for the item gets decremented.
            $invStmt = $pdo->prepare("
                UPDATE inventory_balances
                SET qty_allocated = GREATEST(0, qty_allocated - :qty),
                    updated_at = NOW()
                WHERE item_id = :item_id AND site_code = :site_code
            ");
            $invStmt->execute([
                'item_id' => $alloc['item_id'],
                'site_code' => self::DEFAULT_SITE_CODE,
                'qty' => $alloc['allocated_qty'],
            ]);
        }

        $cancelStmt = $pdo->prepare("
            UPDATE mrp_allocations SET status = 'cancelled', updated_at = NOW()
            WHERE lmr_id = :lmr_id AND status = 'active'
        ");
        $cancelStmt->execute(['lmr_id' => $lmrId]);
    }

    public function getLmrBomBreakdown($lmrId) {
        $warehouseModel = new \App\Models\WarehouseModel();
        $items = $this->getItemsForDisplay($lmrId);
        $breakdown = [];

        foreach ($items as $item) {
            $bomCode = $item['bom_code'] ?? null;
            $qtyOrdered = floatval($item['qty_ordered'] ?? 0);

            if (empty($bomCode)) {
                $breakdown[] = [
                    'lri_id' => $item['lri_id'],
                    'item_code' => $item['item_code'] ?? '-',
                    'item_description' => $item['item_description'] ?? '-',
                    'qty_ordered' => $qtyOrdered,
                    'bom_code' => null,
                    'bom_status' => 'no_bom',
                    'components' => [],
                ];
                continue;
            }

            $bom = $warehouseModel->getBomByCode($bomCode);
            if (!$bom) {
                $breakdown[] = [
                    'lri_id' => $item['lri_id'],
                    'item_code' => $item['item_code'] ?? '-',
                    'item_description' => $item['item_description'] ?? '-',
                    'qty_ordered' => $qtyOrdered,
                    'bom_code' => $bomCode,
                    'bom_status' => 'not_found',
                    'components' => [],
                ];
                continue;
            }

            $components = $warehouseModel->getBOMComponentsWithStock([$bom['id']]);

            $meta = $this->bomMeta($bom, $qtyOrdered);

            $componentDetails = [];
            $hasShortage = false;
            foreach ($components as $comp) {
                $dosage = floatval($comp['dosage_rate']);
                $wastage = floatval($comp['wastage_allowance_pct']);
                $requiredQty = $this->computeRequiredQty($qtyOrdered, $bom, $comp);
                $soh = floatval($comp['soh']);
                $allocated = floatval($comp['allocated']);
                $availableStock = max(0, $soh - $allocated);
                $shortage = round(max(0, $requiredQty - max(0, $soh)), 4);

                if ($shortage > 0) $hasShortage = true;

                $componentDetails[] = [
                    'component_item_id' => $comp['component_item_id'],
                    'item_code' => $comp['item_code'],
                    'item_description' => $comp['item_description'],
                    'item_uom' => $comp['item_uom'],
                    'item_type' => $comp['item_type'] ?? '',
                    'phase_code' => $comp['phase_code'] ?? '101',
                    'dosage_rate' => $dosage,
                    'wastage_pct' => $wastage,
                    'required_qty' => round($requiredQty, 4),
                    'soh' => $soh,
                    'allocated' => round($allocated, 4),
                    'net_available' => round($availableStock, 4),
                    'shortage' => $shortage,
                    'status' => $shortage > 0 ? 'lacking' : 'available',
                ];
            }

            $breakdown[] = [
                'lri_id' => $item['lri_id'],
                'item_code' => $item['item_code'] ?? '-',
                'item_description' => $item['item_description'] ?? '-',
                'qty_ordered' => $qtyOrdered,
                'bom_code' => $bomCode,
                'bom_status' => 'ok',
                'batch_qty' => $meta['batch_qty'],
                'batches_needed' => round($meta['batches_needed'], 4),
                'fill_volume' => $meta['fill_volume'],
                'uom' => $meta['uom'],
                'batch_unit_divisor' => $meta['batch_unit_divisor'],
                'is_legacy_formula' => $meta['is_legacy_formula'],
                'bulk_batch' => round($meta['bulk_batch'], 4),
                'components' => $componentDetails,
                'has_shortage' => $hasShortage,
            ];
        }

        return $breakdown;
    }

    public function validateMoRelease($lmrId) {
        $breakdown = $this->getLmrBomBreakdown($lmrId);
        $shortages = [];

        foreach ($breakdown as $item) {
            if ($item['bom_status'] === 'not_found') {
                $shortages[] = [
                    'item_code' => $item['item_code'],
                    'item_description' => $item['item_description'] ?? '',
                    'reason' => 'BOM "' . htmlspecialchars($item['bom_code']) . '" not found in system',
                    'required' => 0,
                    'available' => 0,
                    'short_by' => 0,
                    'uom' => '',
                ];
                continue;
            }
            if ($item['bom_status'] === 'no_bom') {
                continue;
            }
            foreach ($item['components'] as $comp) {
                if ($comp['status'] === 'lacking') {
                    $shortages[] = [
                        'item_code' => $comp['item_code'],
                        'item_description' => $comp['item_description'],
                    'required' => $comp['required_qty'],
                    'available' => $comp['soh'],
                    'short_by' => $comp['shortage'],
                        'uom' => $comp['item_uom'],
                    ];
                }
            }
        }

        return [
            'valid' => empty($shortages),
            'shortages' => $shortages,
        ];
    }

    public function getBomBreakdownForItem($itemId, $qty) {
        $warehouseModel = new \App\Models\WarehouseModel();
        $qty = floatval($qty);

        $bom = $warehouseModel->hasBOM($itemId);
        if (!$bom) {
            return [
                'bom_code' => null,
                'batch_qty' => 0,
                'batches_needed' => 0,
                'fill_volume' => 0,
                'uom' => '',
                'batch_unit_divisor' => 1000,
                'is_legacy_formula' => 0,
                'bulk_batch' => 0,
                'components' => [],
                'has_shortage' => false,
                'no_bom' => true,
                'not_found' => false,
            ];
        }

        $bomFull = $warehouseModel->getBomByCode($bom['bom_code']);
        if (!$bomFull) {
            return [
                'bom_code' => $bom['bom_code'],
                'batch_qty' => 0,
                'batches_needed' => 0,
                'fill_volume' => 0,
                'uom' => '',
                'batch_unit_divisor' => 1000,
                'is_legacy_formula' => 0,
                'bulk_batch' => 0,
                'components' => [],
                'has_shortage' => false,
                'no_bom' => false,
                'not_found' => true,
            ];
        }

        $components = $warehouseModel->getBOMComponentsWithStock([$bomFull['id']]);
        $meta = $this->bomMeta($bomFull, $qty);

        $componentDetails = [];
        $hasShortage = false;
        foreach ($components as $comp) {
            $dosage = floatval($comp['dosage_rate']);
            $wastage = floatval($comp['wastage_allowance_pct']);
            $requiredQty = $this->computeRequiredQty($qty, $bomFull, $comp);
            $soh = floatval($comp['soh']);
            $allocated = floatval($comp['allocated']);
            $availableStock = max(0, $soh - $allocated);
            $shortage = round(max(0, $requiredQty - max(0, $soh)), 4);

            if ($shortage > 0) $hasShortage = true;

            $componentDetails[] = [
                'component_item_id' => $comp['component_item_id'] ?? null,
                'item_code' => $comp['item_code'],
                'item_description' => $comp['item_description'],
                'item_uom' => $comp['item_uom'],
                'item_type' => $comp['item_type'] ?? '',
                'phase_code' => $comp['phase_code'] ?? '101',
                'dosage_rate' => $dosage,
                'wastage_pct' => $wastage,
                'required_qty' => round($requiredQty, 4),
                'soh' => $soh,
                'allocated' => round($allocated, 4),
                'net_available' => round($availableStock, 4),
                'status' => $shortage > 0 ? 'lacking' : 'available',
                'shortage' => $shortage,
            ];
        }

        return [
            'bom_code' => $bomFull['bom_code'],
            'batch_qty' => $meta['batch_qty'],
            'batches_needed' => round($meta['batches_needed'], 4),
            'fill_volume' => $meta['fill_volume'],
            'uom' => $meta['uom'],
            'batch_unit_divisor' => $meta['batch_unit_divisor'],
            'is_legacy_formula' => $meta['is_legacy_formula'],
            'bulk_batch' => round($meta['bulk_batch'], 4),
            'components' => $componentDetails,
            'has_shortage' => $hasShortage,
            'no_bom' => false,
            'not_found' => false,
        ];
    }
}
