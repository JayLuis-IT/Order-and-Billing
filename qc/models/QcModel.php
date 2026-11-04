<?php
namespace App\Models;

use App\Core\BaseModel;

class QcModel extends BaseModel {

    public function getProductionHistoryForQC($filters = []) {
        $sql = "SELECT ph.*, po.customer_po_number, po.production_type, c.customer_name, u.full_name,
                    eu.full_name as edited_by_name, ph.date_edited,
                    ph.qc_remark, ph.qc_inspected_by, ph.qc_inspected_at,
                    ph.qc_inspector_name,
                    ph.qa_remark, ph.qa_inspected_at,
                    ph.qa_inspector_name,
                    poi.quantity as ordered_quantity,
                    COALESCE(i.item_code, ifa.item_code) as item_code
                FROM production_history ph 
                LEFT JOIN purchase_orders po ON ph.po_id = po.po_id 
                LEFT JOIN customers c ON po.customer_id = c.customer_id 
                LEFT JOIN users u ON ph.user_id = u.user_id
                LEFT JOIN users eu ON ph.edited_by = eu.user_id
                LEFT JOIN purchase_order_items poi ON ph.poi_id = poi.poi_id
                LEFT JOIN items i ON ph.item_id = i.item_id
                LEFT JOIN items ifa ON ph.item_description = ifa.item_description AND ph.item_id IS NULL
                WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $sql .= " AND (ph.lot_number LIKE :search1 
                       OR ph.item_description LIKE :search2
                       OR po.customer_po_number LIKE :search3
                       OR c.customer_name LIKE :search4
                       OR ph.sts_ref LIKE :search5
                       OR u.full_name LIKE :search6)";
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
            $sql .= " AND ph.item_description LIKE :filter_item";
            $params['filter_item'] = '%' . $filters['item_description'] . '%';
        }
        if (!empty($filters['lot_number'])) {
            $sql .= " AND ph.lot_number = :filter_lot";
            $params['filter_lot'] = $filters['lot_number'];
        }

        $sql .= " ORDER BY ph.date_created ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $lotItemTotals = [];
        foreach ($rows as &$row) {
            $lot = $row['lot_number'] ?? '';
            $item = $row['item_description'] ?? '';
            $lotItemKey = $lot . '|' . $item;

            if (!isset($lotItemTotals[$lotItemKey])) $lotItemTotals[$lotItemKey] = 0;
            $row['computed_prev_lot_qty'] = $lotItemTotals[$lotItemKey];
            $lotItemTotals[$lotItemKey] += $row['added_quantity'];
            $row['computed_new_lot_qty'] = $lotItemTotals[$lotItemKey];
        }
        unset($row);

        krsort($rows);
        return array_values($rows);
    }

    public function getInspectionCounts() {
        $stmt = self::getConnection()->prepare("SELECT COUNT(*) as total FROM production_history");
        $stmt->execute();
        $total = $stmt->fetch()['total'];

        $stmt = self::getConnection()->prepare("SELECT COUNT(*) as inspected FROM production_history WHERE qc_remark IS NOT NULL");
        $stmt->execute();
        $inspected = $stmt->fetch()['inspected'];

        return [
            'total' => $total,
            'inspected' => $inspected,
            'remaining' => $total - $inspected
        ];
    }

    public function updateQcRemark($historyId, $inspectorName, $remark) {
        $stmt = self::getConnection()->prepare(
            "UPDATE production_history SET qc_remark = :remark, qc_inspector_name = :inspector_name, qc_inspected_at = NOW() WHERE history_id = :history_id"
        );
        return $stmt->execute([
            'remark' => $remark,
            'inspector_name' => $inspectorName,
            'history_id' => $historyId
        ]);
    }

    public function getPendingQcItems() {
        $sql = "SELECT
                    ri.id AS receiving_item_id,
                    ri.po_ref,
                    ri.supplier AS supplier_name,
                    ri.item_code,
                    ri.item_name AS item_description,
                    ri.uom AS item_uom,
                    ri.ordered_qty,
                    ri.received_qty,
                    ri.passed_qty,
                    ri.rejected_qty,
                    ri.lot_number,
                    ri.received_date,
                    ri.remarks,
                    ri.qc_status,
                    ri.supplier_order_id,
                    so.mrp_ref,
                    i.item_id,
                    car.report_id AS latest_report_id,
                    car.disposition AS latest_disposition
                FROM receiving_items ri
                LEFT JOIN items i ON ri.item_code = i.item_code
                LEFT JOIN supplier_orders so ON so.supplier_order_id = ri.supplier_order_id
                LEFT JOIN qc_analysis_reports car ON car.report_id = (
                    SELECT car2.report_id FROM qc_analysis_reports car2
                    WHERE car2.receiving_item_id = ri.id
                    ORDER BY car2.date_inspected DESC, car2.report_id DESC LIMIT 1
                )
                WHERE ri.qc_status IN ('PENDING_QC', 'HOLD')
                ORDER BY ri.received_date ASC, ri.id ASC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getCompletedQcItems() {
        $sql = "SELECT
                    ri.id AS receiving_item_id,
                    ri.po_ref,
                    ri.supplier AS supplier_name,
                    ri.item_code,
                    ri.item_name AS item_description,
                    ri.uom AS item_uom,
                    ri.ordered_qty,
                    ri.received_qty,
                    ri.passed_qty,
                    ri.rejected_qty,
                    ri.lot_number,
                    ri.received_date,
                    ri.inspected_at,
                    ri.inspected_by,
                    ri.remarks,
                    ri.qc_status,
                    ri.supplier_order_id,
                    so.mrp_ref,
                    i.item_id,
                    car.report_id AS latest_report_id,
                    car.disposition AS latest_disposition
                FROM receiving_items ri
                LEFT JOIN items i ON ri.item_code = i.item_code
                LEFT JOIN supplier_orders so ON so.supplier_order_id = ri.supplier_order_id
                LEFT JOIN qc_analysis_reports car ON car.report_id = (
                    SELECT car2.report_id FROM qc_analysis_reports car2
                    WHERE car2.receiving_item_id = ri.id
                    ORDER BY car2.date_inspected DESC, car2.report_id DESC LIMIT 1
                )
                WHERE ri.qc_status IN ('PASSED', 'REJECTED')
                ORDER BY ri.inspected_at DESC, ri.id DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getReceivingItemById($id) {
        $sql = "SELECT
                    ri.*,
                    ri.id AS receiving_item_id,
                    ri.po_ref,
                    ri.supplier AS supplier_name,
                    ri.item_name AS item_description,
                    ri.uom AS item_uom,
                    i.item_id
                FROM receiving_items ri
                LEFT JOIN items i ON ri.item_code = i.item_code
                WHERE ri.id = :id";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getInspectionDetail($id) {
        $conn = self::getConnection();
        $sql = "SELECT
                    ri.*,
                    ri.id AS receiving_item_id,
                    ri.po_ref,
                    ri.supplier AS supplier_name,
                    ri.item_name AS item_description,
                    ri.uom AS item_uom,
                    so.quantity AS order_quantity,
                    so.unit_cost,
                    so.order_date,
                    so.expected_date,
                    so.status AS supplier_order_status,
                    so.remarks AS order_remarks,
                    so.created_by,
                    u.full_name AS created_by_name,
                    ru.full_name AS received_by_name
                FROM receiving_items ri
                LEFT JOIN items i ON ri.item_code = i.item_code
                LEFT JOIN supplier_orders so ON so.supplier_order_id = ri.supplier_order_id AND so.`remove` = 0
                LEFT JOIN users u ON u.user_id = so.created_by
                LEFT JOIN users ru ON ru.user_id = ri.received_by
                WHERE ri.id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        $insStmt = $conn->prepare(
            "SELECT decision, passed_qty, rejected_qty, inspector_name, remarks, inspected_at
             FROM qc_inspections
             WHERE receiving_item_id = :id
             ORDER BY inspected_at DESC, inspection_id DESC
             LIMIT 1"
        );
        $insStmt->execute(['id' => (int) $row['receiving_item_id']]);
        $qc = $insStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        $uom = (string) ($row['item_uom'] ?? '');
        $orderQty = $row['order_quantity'] !== null ? floatval($row['order_quantity']) : floatval($row['ordered_qty'] ?? 0);
        $recvQty = floatval($row['received_qty'] ?? 0);

        $batch = [
            'id' => (int) $row['receiving_item_id'],
            'po_ref' => $row['po_ref'] ?? null,
            'ordered_qty' => $orderQty,
            'received_qty' => $recvQty,
            'passed_qty' => floatval($row['passed_qty'] ?? 0),
            'rejected_qty' => floatval($row['rejected_qty'] ?? 0),
            'lot_number' => $row['lot_number'] ?? null,
            'expiry_date' => $row['expiry_date'] ?? null,
            'dr_invoice_no' => $row['dr_invoice_no'] ?? null,
            'received_date' => $row['received_date'] ?? null,
            'received_by' => $row['received_by'] ?? null,
            'received_by_name' => $row['received_by_name'] ?? null,
            'remarks' => $row['remarks'] ?? null,
            'qc_status' => $row['qc_status'] ?? null,
            'inspected_by' => $row['inspected_by'] ?? null,
            'inspected_at' => $row['inspected_at'] ?? null,
            'qc_decision' => $qc['decision'] ?? (strtoupper((string) ($row['qc_status'] ?? '')) ?: null),
            'inspector_name' => $qc['inspector_name'] ?? null,
            'qc_remarks' => $qc['remarks'] ?? null,
            'qc_date' => $qc['inspected_at'] ?? ($row['inspected_at'] ?? null),
        ];
        $batch['received_date'] = $batch['received_date'] ? date('m/d/Y', strtotime($batch['received_date'])) : null;
        $batch['qc_date'] = $batch['qc_date'] ? date('m/d/Y H:i', strtotime($batch['qc_date'])) : null;
        $batch['dr_invoice_no'] = trim((string) ($batch['dr_invoice_no'] ?? ''));
        foreach (['ordered_qty', 'received_qty', 'passed_qty', 'rejected_qty'] as $qk) {
            $batch[$qk] = formatQty(floatval($batch[$qk] ?? 0));
        }

        $receivingNotes = trim((string) ($row['remarks'] ?? ''));
        if ($receivingNotes === '' && !empty($row['lot_number'])) {
            $receivingNotes = 'Lot: ' . $row['lot_number'];
            if (!empty($row['expiry_date'])) {
                $receivingNotes .= ' | Expiry: ' . $row['expiry_date'];
            }
        }

        return [
            'po_ref' => trim((string) ($row['po_ref'] ?? '')) ?: null,
            'supplier_name' => $row['supplier_name'] ?? null,
            'item_code' => $row['item_code'] ?? null,
            'description' => $row['item_description'] ?? null,
            'quantity' => formatQty($orderQty),
            'uom' => $uom,
            'unit_cost' => $row['unit_cost'] !== null ? number_format(floatval($row['unit_cost']), 2) : null,
            'order_date' => $row['order_date'] ? date('m/d/Y', strtotime($row['order_date'])) : null,
            'expected_date' => $row['expected_date'] ? date('m/d/Y', strtotime($row['expected_date'])) : null,
            'status' => $row['supplier_order_status'] ?? null,
            'order_remarks' => trim((string) ($row['order_remarks'] ?? '')) ?: null,
            'created_by' => $row['created_by_name'] ?? null,
            'received_qty' => formatQty($recvQty),
            'received_date' => $row['received_date'] ? date('m/d/Y', strtotime($row['received_date'])) : null,
            'dr_number' => trim((string) ($row['dr_invoice_no'] ?? '')),
            'received_by' => $row['received_by_name'] ?? null,
            'receiving_notes' => $receivingNotes ?: null,
            'lot_number' => $row['lot_number'] ?? null,
            'expiry_date' => $row['expiry_date'] ?? null,
            'qc_decision' => $batch['qc_decision'],
            'inspector_name' => $batch['inspector_name'],
            'qc_date' => $batch['qc_date'] ? date('m/d/Y H:i', strtotime($batch['qc_date'])) : null,
            'qc_remarks' => $batch['qc_remarks'],
            'passed_qty' => formatQty(floatval($row['passed_qty'] ?? 0)),
            'rejected_qty' => formatQty(floatval($row['rejected_qty'] ?? 0)),
            'batches' => [$batch],
        ];
    }

    public function createReceivingItem($data) {
        // Duplicate prevention: one pending inspection row per po_ref + item_code.
        $poRef = $data['po_ref'] ?? ($data['po_id'] ?? null);
        $itemCode = $data['item_code'] ?? null;
        $conn = self::getConnection();
        if ($poRef !== null && $itemCode !== null) {
            $dup = $conn->prepare(
                "SELECT id FROM receiving_items
                 WHERE po_ref = :po_ref AND item_code = :item_code AND qc_status = 'PENDING_QC'
                 LIMIT 1"
            );
            $dup->execute(['po_ref' => $poRef, 'item_code' => $itemCode]);
            $dupRow = $dup->fetch();
            if ($dupRow) {
                $conn->prepare(
                    "UPDATE receiving_items
                     SET received_qty = :received_qty,
                         received_date = :received_date,
                         lot_number = :lot_number,
                         expiry_date = :expiry_date,
                         dr_invoice_no = :dr_invoice_no,
                         received_by = COALESCE(received_by, :received_by),
                         remarks = :remarks
                     WHERE id = :rid"
                )->execute([
                    'received_qty' => $data['received_qty'] ?? 0,
                    'received_date' => $data['received_date'] ?? date('Y-m-d'),
                    'lot_number' => $data['lot_number'] ?? null,
                    'expiry_date' => $data['expiry_date'] ?? null,
                    'dr_invoice_no' => $data['dr_invoice_no'] ?? null,
                    'received_by' => $data['received_by'] ?? ($data['created_by'] ?? null),
                    'remarks' => $data['remarks'] ?? null,
                    'rid' => $dupRow['id'],
                ]);
                return (int) $dupRow['id'];
            }
        }

        $sql = "INSERT INTO receiving_items (
                    po_ref, supplier, item_code, item_name, uom,
                    ordered_qty, received_qty, passed_qty, rejected_qty,
                    lot_number, expiry_date, dr_invoice_no, received_date,
                    received_by, remarks, qc_status
                ) VALUES (
                    :po_ref, :supplier, :item_code, :item_name, :uom,
                    :ordered_qty, :received_qty, :passed_qty, :rejected_qty,
                    :lot_number, :expiry_date, :dr_invoice_no, :received_date,
                    :received_by, :remarks, :qc_status
                )";

        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([
            'po_ref' => $data['po_ref'] ?? ($data['po_id'] ?? null),
            'supplier' => $data['supplier_name'] ?? null,
            'item_code' => $data['item_code'] ?? null,
            'item_name' => $data['item_description'] ?? null,
            'uom' => $data['uom'] ?? null,
            'ordered_qty' => $data['ordered_qty'] ?? ($data['received_qty'] ?? 0),
            'received_qty' => $data['received_qty'] ?? 0,
            'passed_qty' => $data['passed_qty'] ?? 0,
            'rejected_qty' => $data['rejected_qty'] ?? 0,
            'lot_number' => $data['lot_number'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'dr_invoice_no' => $data['dr_invoice_no'] ?? null,
            'received_date' => $data['received_date'] ?? date('Y-m-d'),
            'received_by' => $data['received_by'] ?? ($data['created_by'] ?? null),
            'remarks' => $data['remarks'] ?? null,
            'qc_status' => 'PENDING_QC',
        ]);

        return (int) self::getConnection()->lastInsertId();
    }

    /**
     * STRUCTURAL PLACEHOLDER — custom checklist-based QC evaluation.
     *
     * Upcoming iterations will attach item-checklist driven scoring to each
     * decision. The contract is fixed today so callers/UI can plug in later:
     *   $answers   — one entry per checklist item: [key => ['value' => text, 'pass' => bool]]
     *   $template  — the checklist definition for the item (null until steps ship).
     *
     * Until the checklist engine lands, recordQcInspection() runs WITHOUT
     * checklist validation: this hook returns null (no failure, no data saved)
     * and the PASSED/REJECTED decision flows exactly as it does today. The QC
     * inspection view already reserves a hidden #qcChecklistPanel container,
     * so the future checklist UI mounts without touching the decision flow.
     *
     * @param int   $receivingItemId receiving_items.id the evaluation attaches to
     * @param array $answers         per-checklist-item answers (future input)
     * @param array|null $template    checklist definition (future input)
     * @return array|null ['checklist_passed' => bool, 'violations' => array] | null when no checklist applies
     */
    public function evaluateChecklist($receivingItemId, array $answers = [], ?array $template = null) {
        if ($template === null || $template === []) {
            return null;
        }
        return [
            'checklist_passed' => false,
            'violations' => [],
        ];
    }

    public function recordQcInspection($data) {
        $receivingItemId = (int) ($data['receiving_item_id'] ?? 0);
        $decision = strtoupper((string) ($data['decision'] ?? ''));
        $receivedQty = (float) ($data['received_qty'] ?? 0);
        $passedQty = (float) ($data['passed_qty'] ?? 0);
        $rejectedQty = (float) ($data['rejected_qty'] ?? 0);
        $inspectorName = trim((string) ($data['inspector_name'] ?? ''));
        $remarks = trim((string) ($data['remarks'] ?? ''));

        if ($receivingItemId <= 0) {
            throw new \RuntimeException('Receiving item is required.');
        }
        if (!in_array($decision, ['PASSED', 'REJECTED'], true)) {
            throw new \RuntimeException('QC decision must be PASSED or REJECTED.');
        }
        if ($receivedQty <= 0) {
            throw new \RuntimeException('Received quantity must be greater than zero.');
        }
        if (abs(($passedQty + $rejectedQty) - $receivedQty) > 0.0001) {
            throw new \RuntimeException('Passed + rejected quantities must equal the received quantity.');
        }
        if ($decision === 'PASSED' && $passedQty <= 0) {
            throw new \RuntimeException('Passed quantity must be greater than zero for accepted goods.');
        }
        if ($decision === 'REJECTED' && $rejectedQty <= 0) {
            throw new \RuntimeException('Rejected quantity must be greater than zero for rejected goods.');
        }
        if ($inspectorName === '') {
            throw new \RuntimeException('Inspector name is required.');
        }

        // Checklist evaluation hook (structural placeholder): a no-op until the
        // custom checklist engine lands — result ignored, decisions unchanged.
        $checklistResult = $this->evaluateChecklist(
            $receivingItemId,
            (array) ($data['checklist_answers'] ?? [])
        );

        $receivingItem = $this->getReceivingItemById($receivingItemId);
        if (!$receivingItem) {
            throw new \RuntimeException('Receiving item was not found.');
        }
        // Server-side duplicate guard: a queue entry can only be inspected once
        // (blocks double-clicked Approve/Reject submits). HOLD items may be
        // re-inspected — that is the point of putting them on hold.
        $currentQcStatus = strtoupper((string) ($receivingItem['qc_status'] ?? ''));
        if (!in_array($currentQcStatus, ['PENDING_QC', 'HOLD'], true)) {
            throw new \RuntimeException('This receiving item has already been inspected (status: ' . $currentQcStatus . ').');
        }
        $itemId = (int) ($receivingItem['item_id'] ?? 0);
        if ($itemId <= 0) {
            throw new \RuntimeException('Could not resolve item for receiving item #' . $receivingItemId . '.');
        }

        $finalStatus = $decision;
        $conn = self::getConnection();
        $conn->beginTransaction();

        try {
            $inspectorUserId = (int) ($_SESSION['user_id'] ?? 0);

            $insStmt = $conn->prepare("INSERT INTO qc_inspections (
                    receiving_item_id, decision, passed_qty, rejected_qty, inspector_name, remarks, inspected_at
                ) VALUES (
                    :receiving_item_id, :decision, :passed_qty, :rejected_qty, :inspector_name, :remarks, NOW()
                )");
            $insStmt->execute([
                'receiving_item_id' => $receivingItemId,
                'decision' => $decision,
                'passed_qty' => $passedQty,
                'rejected_qty' => $rejectedQty,
                'inspector_name' => $inspectorName,
                'remarks' => $remarks,
            ]);

            $updateStmt = $conn->prepare("UPDATE receiving_items
                SET qc_status = :status,
                    inspected_by = :inspector_id,
                    inspected_at = NOW(),
                    remarks = :remarks,
                    passed_qty = :passed_qty,
                    rejected_qty = :rejected_qty
                WHERE id = :receiving_item_id");
            $updateStmt->execute([
                'status' => $finalStatus,
                'inspector_id' => $inspectorUserId ?: null,
                'remarks' => $remarks,
                'passed_qty' => $passedQty,
                'rejected_qty' => $rejectedQty,
                'receiving_item_id' => $receivingItemId,
            ]);

            // Reflect QC decision on the source supplier order (never delete/hide it).
            $supplierOrderId = (int) ($receivingItem['supplier_order_id'] ?? 0);
            if ($supplierOrderId > 0) {
                if ($decision === 'PASSED') {
                    $conn->prepare("UPDATE supplier_orders
                        SET status = IF(received_qty >= quantity, 'received', 'partially_received'),
                            last_update = NOW()
                        WHERE supplier_order_id = :sid AND `remove` = 0")->execute(['sid' => $supplierOrderId]);
                } else {
                    $conn->prepare("UPDATE supplier_orders
                        SET status = 'rejected', last_update = NOW()
                        WHERE supplier_order_id = :sid AND `remove` = 0")->execute(['sid' => $supplierOrderId]);
                }
            }

            $qtyToRelease = $decision === 'PASSED' ? $passedQty : $rejectedQty;
            if ($qtyToRelease > 0) {
                $conn->prepare("UPDATE inventory_balances
                    SET qty_for_inspect = GREATEST(qty_for_inspect - :qty_to_release, 0)
                    WHERE item_id = :item_id AND site_code = :site_code")->execute([
                    'qty_to_release' => $qtyToRelease,
                    'item_id' => $itemId,
                    'site_code' => self::DEFAULT_SITE_CODE,
                ]);
            }

            if ($decision === 'PASSED' && $passedQty > 0) {
                $stockSql = "INSERT INTO inventory_stock (
                                item_id, site_code, lot_number, qty_on_hand, qty_blocked, qty_rejected, status, source_receiving_item_id, reference_type, reference_id, updated_at
                            ) VALUES (
                                :item_id, :site_code, :lot_number, :qty_on_hand, 0, 0, 'PASSED', :source_receiving_item_id, 'receiving_item', :source_id, NOW()
                            ) ON DUPLICATE KEY UPDATE
                                qty_on_hand = qty_on_hand + VALUES(qty_on_hand),
                                status = 'PASSED',
                                updated_at = NOW()";
                $conn->prepare($stockSql)->execute([
                    'item_id' => $itemId,
                    'site_code' => self::DEFAULT_SITE_CODE,
                    'lot_number' => $receivingItem['lot_number'],
                    'qty_on_hand' => $passedQty,
                    'source_receiving_item_id' => $receivingItemId,
                    'source_id' => $receivingItemId,
                ]);

                $conn->prepare("INSERT INTO inventory_balances (item_id, site_code, qty_on_hand, qty_for_inspect)
                    VALUES (:item_id, :site_code, :qty_on_hand, 0)
                    ON DUPLICATE KEY UPDATE qty_on_hand = qty_on_hand + VALUES(qty_on_hand)")->execute([
                    'item_id' => $itemId,
                    'site_code' => self::DEFAULT_SITE_CODE,
                    'qty_on_hand' => $passedQty,
                ]);
            }

            if ($decision === 'REJECTED' && $rejectedQty > 0) {
                $stockSql = "INSERT INTO inventory_stock (
                                item_id, site_code, lot_number, qty_on_hand, qty_blocked, qty_rejected, status, source_receiving_item_id, reference_type, reference_id, updated_at
                            ) VALUES (
                                :item_id, :site_code, :lot_number, 0, :qty_blocked, :qty_rejected, 'REJECTED', :source_receiving_item_id, 'receiving_item', :source_id, NOW()
                            ) ON DUPLICATE KEY UPDATE
                                qty_blocked = qty_blocked + VALUES(qty_blocked),
                                qty_rejected = qty_rejected + VALUES(qty_rejected),
                                status = 'REJECTED',
                                updated_at = NOW()";
                $conn->prepare($stockSql)->execute([
                    'item_id' => $itemId,
                    'site_code' => self::DEFAULT_SITE_CODE,
                    'lot_number' => $receivingItem['lot_number'],
                    'qty_blocked' => $rejectedQty,
                    'qty_rejected' => $rejectedQty,
                    'source_receiving_item_id' => $receivingItemId,
                    'source_id' => $receivingItemId,
                ]);
            }

            $conn->commit();
            return [
                'success' => true,
                'decision' => $decision,
                'receiving_item_id' => $receivingItemId,
            ];
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function approveReceivingInspection($id, $data = []) {
        $receivingItemId = (int) ($id ?: ($data['receiving_item_id'] ?? 0));
        $decision = strtoupper((string) ($data['decision'] ?? 'PASSED'));
        $payload = [
            'receiving_item_id' => $receivingItemId,
            'decision' => $decision,
            'received_qty' => isset($data['received_qty']) ? (float) $data['received_qty'] : 0,
            'passed_qty' => isset($data['passed_qty']) ? (float) $data['passed_qty'] : 0,
            'rejected_qty' => isset($data['rejected_qty']) ? (float) $data['rejected_qty'] : 0,
            'inspector_name' => trim((string) ($data['inspector_name'] ?? '')) ?: ($_SESSION['full_name'] ?? 'QC'),
            'remarks' => trim((string) ($data['remarks'] ?? '')),
        ];

        return $this->recordQcInspection($payload);
    }

    // ─── Raw Material Analysis Report (shared: MRP Check + Receiving) ─────────

    /**
     * Save one analysis report row. Exactly one target must be set:
     *   MRP context     — run_id + component_item_id
     *   Receiving context — receiving_item_id
     * Multiple reports per target are allowed (retests); latest wins for gates.
     */
    public function saveAnalysisReport(array $data) {
        $isMrp = !empty($data['run_id']) && !empty($data['component_item_id']);
        $isReceiving = !empty($data['receiving_item_id']);
        if ($isMrp === $isReceiving) {
            throw new \RuntimeException('Inspection target is invalid.');
        }
        $rmName = trim((string) ($data['rm_name'] ?? ''));
        if ($rmName === '') {
            throw new \RuntimeException('RM Name is required.');
        }
        $disposition = strtoupper(trim((string) ($data['disposition'] ?? '')));
        if (!in_array($disposition, ['APPROVED', 'REJECTED', 'HOLD', 'WAIVED'], true)) {
            throw new \RuntimeException('Disposition must be Approved, Rejected, Hold, or Waived.');
        }
        $deliveryType = trim((string) ($data['delivery_type'] ?? 'new_delivery'));
        if (!in_array($deliveryType, ['new_delivery', 'retest_1', 'retest_2', 'retest_3'], true)) {
            $deliveryType = 'new_delivery';
        }

        $params = [
            'run_id' => $isMrp ? (int) $data['run_id'] : null,
            'component_item_id' => $isMrp ? (int) $data['component_item_id'] : null,
            'receiving_item_id' => $isReceiving ? (int) $data['receiving_item_id'] : null,
            'rm_name' => $rmName,
            'supplier_name' => trim((string) ($data['supplier_name'] ?? '')) ?: null,
            'rr_number' => trim((string) ($data['rr_number'] ?? '')) ?: null,
            'lot_number' => trim((string) ($data['lot_number'] ?? '')) ?: null,
            'old_expiry_date' => $this->nullableDate($data['old_expiry_date'] ?? null),
            'date_delivered' => $this->nullableDate($data['date_delivered'] ?? null),
            'date_tested' => $this->nullableDate($data['date_tested'] ?? null) ?: date('Y-m-d'),
            'expiry_date' => $this->nullableDate($data['expiry_date'] ?? null),
            'delivery_type' => $deliveryType,
            'disposition' => $disposition,
            'remarks' => trim((string) ($data['remarks'] ?? '')) ?: null,
            'inspected_by' => (int) ($_SESSION['user_id'] ?? 0) ?: null,
        ];
        foreach (['appearance', 'color', 'odor', 'texture', 'ph', 'viscosity', 'sg', 'solubility', 'alcohol', 'microbial'] as $param) {
            $params[$param . '_spec'] = trim((string) ($data[$param . '_spec'] ?? '')) ?: null;
            $params[$param . '_result'] = trim((string) ($data[$param . '_result'] ?? '')) ?: null;
        }

        $columns = array_keys($params);
        $sql = 'INSERT INTO qc_analysis_reports (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', array_map(function ($c) {
                    return ':' . $c;
                }, $columns)) . ')';
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return (int) self::getConnection()->lastInsertId();
    }

    private function nullableDate($value) {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /** Full report row by id (for the printable page). */
    public function getAnalysisReport($reportId) {
        $stmt = self::getConnection()->prepare(
            "SELECT car.*, u.full_name AS inspector_name
             FROM qc_analysis_reports car
             LEFT JOIN users u ON u.user_id = car.inspected_by
             WHERE car.report_id = :id"
        );
        $stmt->execute(['id' => (int) $reportId]);
        return $stmt->fetch() ?: false;
    }

    /** Latest report per component item of an MRP run (for the items modal). */
    public function getLatestMrpReports($runId) {
        $stmt = self::getConnection()->prepare(
            "SELECT car.* FROM qc_analysis_reports car
             WHERE car.run_id = :run_id
               AND car.report_id = (
                   SELECT car2.report_id FROM qc_analysis_reports car2
                   WHERE car2.run_id = car.run_id AND car2.component_item_id = car.component_item_id
                   ORDER BY car2.date_inspected DESC, car2.report_id DESC LIMIT 1
               )"
        );
        $stmt->execute(['run_id' => (int) $runId]);
        $byItem = [];
        foreach ($stmt->fetchAll() as $row) {
            $byItem[(int) $row['component_item_id']] = $row;
        }
        return $byItem;
    }

    /** Latest report for one receiving item (prefill + record tables). */
    public function getLatestReceivingReport($receivingItemId) {
        $stmt = self::getConnection()->prepare(
            "SELECT car.* FROM qc_analysis_reports car
             WHERE car.receiving_item_id = :id
             ORDER BY car.date_inspected DESC, car.report_id DESC LIMIT 1"
        );
        $stmt->execute(['id' => (int) $receivingItemId]);
        return $stmt->fetch() ?: false;
    }

    /**
     * Apply a receiving-item disposition alongside its analysis report:
     *   APPROVED / WAIVED → PASSED (release to SOH, waive = accept without full test)
     *   REJECTED          → REJECTED (exclude from SOH)
     *   HOLD              → HOLD (stays in the queue for re-inspection)
     */
    public function applyReceivingDisposition($receivingItemId, $disposition, $remarks = '') {
        $item = $this->getReceivingItemById($receivingItemId);
        if (!$item) {
            throw new \RuntimeException('Receiving item not found.');
        }
        $currentStatus = strtoupper((string) ($item['qc_status'] ?? ''));
        if (!in_array($currentStatus, ['PENDING_QC', 'HOLD'], true)) {
            throw new \RuntimeException('This receiving item has already been inspected (status: ' . $currentStatus . ').');
        }

        if ($disposition === 'HOLD') {
            self::getConnection()->prepare(
                "UPDATE receiving_items SET qc_status = 'HOLD', remarks = :remarks WHERE id = :id"
            )->execute([
                'remarks' => $remarks !== '' ? $remarks : ($item['remarks'] ?? null),
                'id' => (int) $receivingItemId,
            ]);
            return ['success' => true, 'decision' => 'HOLD'];
        }

        $receivedQty = (float) ($item['received_qty'] ?? 0);
        $decision = ($disposition === 'REJECTED') ? 'REJECTED' : 'PASSED';
        return $this->recordQcInspection([
            'receiving_item_id' => (int) $receivingItemId,
            'decision' => $decision,
            'received_qty' => $receivedQty,
            'passed_qty' => $decision === 'PASSED' ? $receivedQty : 0,
            'rejected_qty' => $decision === 'REJECTED' ? $receivedQty : 0,
            'inspector_name' => $_SESSION['full_name'] ?? 'QC',
            'remarks' => $remarks,
        ]);
    }
}
