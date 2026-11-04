<?php
namespace App\Controllers;

use App\Models\WarehouseModel;
use App\Models\CatalogModel;
use App\Models\AuditModel;
use App\Helpers\Pagination;
use App\Helpers\NotificationHelper;

class ProductionController {
    private $warehouseModel;
    private $catalogModel;

    public function __construct() {
        $action = $_GET['action'] ?? '';
        if (!isset($_SESSION['user_id'])) {
            if ($action === 'getPODetails' || $action === 'getCustomerPOs' || $action === 'getItemBomInfo') {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['error' => 'Session expired. Please log in again.']);
                exit;
            }
            header('Location: ?controller=auth&action=login');
            exit;
        }
        $publicActions = ['getPODetails', 'searchItems', 'fgInput', 'saveFgInput', 'getLotsForInventory', 'lmrEntry', 'getCustomerPOs', 'getItemBomInfo'];
        if (!in_array($action, $publicActions, true) && ($_SESSION['department'] ?? '') !== 'production') {
            header('Location: ' . \App\Helpers\RoleHelper::homeUrl());
            exit;
        }
        $this->warehouseModel = new WarehouseModel();
        $this->catalogModel = new CatalogModel();
    }

    public function index() {
        $data['page_title'] = 'Production Dashboard';
        $data['purchase_orders'] = $this->warehouseModel->getActivePOsForDashboard(5);
        $poIds = array_column($data['purchase_orders'], 'po_id');
        $data['po_items_map'] = $this->warehouseModel->getPurchaseOrderItemsByPOIds($poIds);

        $this->render('dashboard', $data);
    }

    public function fgInventory() {
        $search = $_GET['search'] ?? '';
        $allItems = $this->warehouseModel->getFGInventory(['search' => $search]);
        $pagination = Pagination::paginate($allItems, 20);

        $data['inventory'] = $pagination['items'];
        $data['page'] = $pagination['page'];
        $data['totalPages'] = $pagination['totalPages'];
        $data['total'] = $pagination['total'];
        $data['search'] = $search;
        $data['page_title'] = 'FG Inventory';
        $this->render('fg_inventory/index', $data);
    }

    public function getLotsForInventory() {
        header('Content-Type: application/json');
        try {
            $item_id = $_GET['item_id'] ?? null;
            if (!$item_id) {
                echo json_encode([]);
                exit;
            }
            $lots = $this->warehouseModel->getLotsByItemForInventory($item_id);
            echo json_encode($lots);
        } catch (\Exception $e) {
            error_log('getLotsForInventory error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load lots']);
        }
        exit;
    }

    public function purchaseOrders() {
        header('Location: ?controller=production&action=fgInventory');
        exit;
    }

    public function updateQuantity() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $po_id = $_POST['po_id'] ?? '';
            $maxRetries = 3;

            for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
                $conn = \App\Core\BaseModel::getConnection();
                $conn->beginTransaction();
                try {
                    $stmtLast = $conn->prepare("SELECT sts_ref FROM production_history WHERE sts_ref LIKE 'STS-%' ORDER BY history_id DESC LIMIT 1 FOR UPDATE");
                    $stmtLast->execute();
                    $lastSts = $stmtLast->fetchColumn();
                    if ($lastSts && preg_match('/STS-(\d+)/', $lastSts, $m)) {
                        $nextNum = intval($m[1]) + 1;
                    } else {
                        $nextNum = 1;
                    }

                $shifts = $_POST['shift'] ?? [];
                $rejectStatuses = $_POST['reject_status'] ?? [];
                $stsRemarks = $_POST['sts_remarks'] ?? [];
                $pcsPerCases = $_POST['pcs_per_case'] ?? [];
                $preparedByName = trim($_POST['prepared_by_name'] ?? '') ?: null;
                $checkedByName = trim($_POST['checked_by_name'] ?? '') ?: null;
                $receivedByName = trim($_POST['received_by_name'] ?? '') ?: null;
                if (!is_array($shifts)) $shifts = [$shifts];
                if (!is_array($rejectStatuses)) $rejectStatuses = [$rejectStatuses];
                if (!is_array($stsRemarks)) $stsRemarks = [$stsRemarks];
                if (!is_array($pcsPerCases)) $pcsPerCases = [$pcsPerCases];
                $savedLotGroups = [];

                if (is_array($_POST['poi_id'] ?? null)) {
                    $poi_ids = $_POST['poi_id'];
                    $quantities = $_POST['added_quantity'] ?? [];
                    $lot_numbers = $_POST['lot_number'] ?? [];
                    foreach ($poi_ids as $i => $poi_id) {
                        if ($poi_id && isset($quantities[$i]) && $quantities[$i] > 0) {
                            $autoStsRef = 'STS-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
                            $nextNum++;
                            $lot = $lot_numbers[$i] ?? null;
                            $poi = $this->warehouseModel->getPurchaseOrderItemById($poi_id);
                            $itemDesc = $poi['item_description'] ?? null;
                            $previousProduced = intval($poi['produced_quantity'] ?? 0);
                            $addedQty = intval($quantities[$i] ?? 0);
                            $newProduced = $previousProduced + $addedQty;
                            $previousLotQty = $previousProduced;
                            $newLotQty = $newProduced;
                            if ($lot && $lot !== '') {
                                $lotQtyStmt = \App\Core\BaseModel::getConnection()->prepare("SELECT quantity_produced FROM production_lots WHERE poi_id = :poi_id AND lot_number = :lot_number AND `is_removed` = 0 LIMIT 1");
                                $lotQtyStmt->execute(['poi_id' => $poi_id, 'lot_number' => $lot]);
                                $currentLot = $lotQtyStmt->fetch();
                                $previousLotQty = isset($currentLot['quantity_produced']) ? intval($currentLot['quantity_produced']) : 0;
                                $newLotQty = $previousLotQty + $addedQty;
                                $this->warehouseModel->updateLotQuantity($poi_id, $lot, $addedQty, $_SESSION['user_id'], $poi['po_id'] ?? $po_id, intval($pcsPerCases[$i] ?? 0) ?: null);
                            }
                            $extraStsData = [
                                'shift' => trim($shifts[$i] ?? '') ?: null,
                                'reject_status' => trim($rejectStatuses[$i] ?? '') ?: null,
                                'sts_remarks' => trim($stsRemarks[$i] ?? '') ?: null,
                                'pcs_per_case' => intval($pcsPerCases[$i] ?? 0) ?: null,
                                'prepared_by_name' => $preparedByName,
                                'checked_by_name' => $checkedByName,
                                'received_by_name' => $receivedByName,
                            ];
                            $this->warehouseModel->updateItemProducedQuantity($poi_id, $addedQty, $_SESSION['user_id'], $lot, $itemDesc, $autoStsRef, $extraStsData);
                            $this->saveItemConversionIfNeeded($poi_id, intval($pcsPerCases[$i] ?? 0));
                            if ($lot && $lot !== '' && !empty($poi['item_id'])) {
                                $savedLotGroups[] = ['item_id' => intval($poi['item_id']), 'lot_number' => $lot];
                            }
                            $poLabel = $poi['customer_po_number'] ?? $poi['po_number'] ?? 'PO item #' . $poi_id;
                            $lotText = $lot ? ' for lot ' . $lot : '';
                            $lotQtyText = $lot ? ' (lot quantity ' . $previousLotQty . ' → ' . $newLotQty . ')' : '';
                            $description = 'Updated production quantity for ' . $poLabel . ': added ' . $addedQty . ' pcs' . $lotText . ' (previous ' . $previousProduced . ' → new ' . $newProduced . ')' . $lotQtyText . ' [' . $autoStsRef . ']';
                            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'production', $description, [
                                'previous_quantity' => $previousLotQty,
                                'added_quantity' => 0,
                                'new_quantity' => $previousLotQty,
                                'lot_number' => $lot,
                                'sts_ref' => $autoStsRef,
                            ], [
                                'previous_quantity' => $previousLotQty,
                                'added_quantity' => $addedQty,
                                'new_quantity' => $newLotQty,
                                'lot_number' => $lot,
                                'sts_ref' => $autoStsRef,
                            ], 'purchase_order_item', $poi_id);
                        }
                    }
                } else {
                    $poi_id = $_POST['poi_id'] ?? null;
                    $quantities = $_POST['added_quantity'] ?? [];
                    $lot_numbers = $_POST['lot_number'] ?? [];
                    if (!is_array($quantities)) $quantities = [$quantities];
                    if (!is_array($lot_numbers)) $lot_numbers = [$lot_numbers];
                    foreach ($quantities as $i => $qty) {
                        if ($poi_id && $qty > 0) {
                            $autoStsRef = 'STS-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
                            $nextNum++;
                            $lot = $lot_numbers[$i] ?? null;
                            $poi = $this->warehouseModel->getPurchaseOrderItemById($poi_id);
                            $itemDesc = $poi['item_description'] ?? null;
                            $previousProduced = intval($poi['produced_quantity'] ?? 0);
                            $addedQty = intval($qty ?? 0);
                            $newProduced = $previousProduced + $addedQty;
                            $previousLotQty = $previousProduced;
                            $newLotQty = $newProduced;
                            if ($lot && $lot !== '') {
                                $lotQtyStmt = \App\Core\BaseModel::getConnection()->prepare("SELECT quantity_produced FROM production_lots WHERE poi_id = :poi_id AND lot_number = :lot_number AND `is_removed` = 0 LIMIT 1");
                                $lotQtyStmt->execute(['poi_id' => $poi_id, 'lot_number' => $lot]);
                                $currentLot = $lotQtyStmt->fetch();
                                $previousLotQty = isset($currentLot['quantity_produced']) ? intval($currentLot['quantity_produced']) : 0;
                                $newLotQty = $previousLotQty + $addedQty;
                                $this->warehouseModel->updateLotQuantity($poi_id, $lot, $addedQty, $_SESSION['user_id'], $poi['po_id'] ?? $po_id, intval($pcsPerCases[$i] ?? 0) ?: null);
                            }
                            $extraStsData = [
                                'shift' => trim($shifts[$i] ?? '') ?: null,
                                'reject_status' => trim($rejectStatuses[$i] ?? '') ?: null,
                                'sts_remarks' => trim($stsRemarks[$i] ?? '') ?: null,
                                'pcs_per_case' => intval($pcsPerCases[$i] ?? 0) ?: null,
                                'prepared_by_name' => $preparedByName,
                                'checked_by_name' => $checkedByName,
                                'received_by_name' => $receivedByName,
                            ];
                            $this->warehouseModel->updateItemProducedQuantity($poi_id, $addedQty, $_SESSION['user_id'], $lot, $itemDesc, $autoStsRef, $extraStsData);
                            $this->saveItemConversionIfNeeded($poi_id, intval($pcsPerCases[$i] ?? 0));
                            if ($lot && $lot !== '' && !empty($poi['item_id'])) {
                                $savedLotGroups[] = ['item_id' => intval($poi['item_id']), 'lot_number' => $lot];
                            }
                            $poLabel = $poi['customer_po_number'] ?? $poi['po_number'] ?? 'PO item #' . $poi_id;
                            $lotText = $lot ? ' for lot ' . $lot : '';
                            $lotQtyText = $lot ? ' (lot quantity ' . $previousLotQty . ' → ' . $newLotQty . ')' : '';
                            $description = 'Updated production quantity for ' . $poLabel . ': added ' . $addedQty . ' pcs' . $lotText . ' (previous ' . $previousProduced . ' → new ' . $newProduced . ')' . $lotQtyText . ' [' . $autoStsRef . ']';
                            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'production', $description, [
                                'previous_quantity' => $previousLotQty,
                                'added_quantity' => 0,
                                'new_quantity' => $previousLotQty,
                                'lot_number' => $lot,
                                'sts_ref' => $autoStsRef,
                            ], [
                                'previous_quantity' => $previousLotQty,
                                'added_quantity' => $addedQty,
                                'new_quantity' => $newLotQty,
                                'lot_number' => $lot,
                                'sts_ref' => $autoStsRef,
                            ], 'purchase_order_item', $poi_id);
                        }
                    }
                }

                NotificationHelper::create('production', 'Production Updated', 'A batch of production quantities has been updated [' . ($autoStsRef ?? 'batch') . ']', 'warehouse', '?controller=warehouse&action=purchaseOrders', $_SESSION['user_id']);
                NotificationHelper::create('production', 'Production Updated', 'A batch of production quantities has been updated [' . ($autoStsRef ?? 'batch') . ']', 'admin', '?controller=admin&action=productionHistory', $_SESSION['user_id']);
                NotificationHelper::qcInspectionNeeded('New production entries', '', $_SESSION['user_id']);

                // Invariant guard: re-sync every touched lot group from production history
                $savedGroups = [];
                foreach ($savedLotGroups ?? [] as $g) {
                    $k = $g['item_id'] . '|' . $g['lot_number'];
                    if (!isset($savedGroups[$k])) $savedGroups[$k] = $g;
                }
                foreach ($savedGroups as $g) {
                    $this->warehouseModel->reconcileLotGroup($g['item_id'], $g['lot_number'], $conn);
                }

                $conn->commit();
                $_SESSION['success'] = 'Production quantity updated successfully';
                $from = $_POST['from'] ?? 'purchaseOrders';
                header('Location: ?controller=production&action=' . $from);
                exit;
            } catch (\PDOException $e) {
                $conn->rollBack();
                if ($e->errorInfo[1] == 23000 && $attempt < $maxRetries - 1) {
                    continue;
                }
                $_SESSION['error'] = 'Failed to update production: ' . $e->getMessage();
                $from = $_POST['from'] ?? 'purchaseOrders';
                header('Location: ?controller=production&action=' . $from);
                exit;
            } catch (\Exception $e) {
                $conn->rollBack();
                $_SESSION['error'] = 'Failed to update production: ' . $e->getMessage();
                $from = $_POST['from'] ?? 'purchaseOrders';
                header('Location: ?controller=production&action=' . $from);
                exit;
            }
            }
        }
    }

    public function getNextStsRef() {
        header('Content-Type: application/json');
        try {
            $conn = \App\Core\BaseModel::getConnection();
            $stmt = $conn->prepare("SELECT sts_ref FROM production_history WHERE sts_ref LIKE 'STS-%' ORDER BY history_id DESC LIMIT 1");
            $stmt->execute();
            $lastSts = $stmt->fetchColumn();
            if ($lastSts && preg_match('/STS-(\d+)/', $lastSts, $m)) {
                $nextNum = intval($m[1]) + 1;
            } else {
                $nextNum = 1;
            }
            echo json_encode(['sts_ref' => 'STS-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT)]);
        } catch (\Exception $e) {
            error_log('getNextStsRef error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get STS reference']);
        }
        exit;
    }

    public function fgInput() {
        $data['page_title'] = 'FG Input';
        $this->render('production/fg_input', $data);
    }

    public function lmrEntry() {
        $data['page_title'] = 'LMR Entry';
        $data['customers'] = $this->catalogModel->getCustomers();
        $data['items'] = $this->catalogModel->getLmrProductItems();
        $this->render('production/lmr_entry', $data);
    }

    public function getCustomerPOs() {
        header('Content-Type: application/json');
        $customerId = intval($_GET['customer_id'] ?? 0);
        if ($customerId <= 0) {
            echo json_encode([]);
            exit;
        }

        $pos = $this->warehouseModel->getOpenPOsByCustomer($customerId);
        $payload = [];
        foreach ($pos as $po) {
            $poNumber = trim((string) ($po['customer_po_number'] ?? $po['po_number'] ?? ''));
            if ($poNumber === '') {
                $poNumber = 'PO #' . ($po['po_id'] ?? '');
            }
            $payload[] = [
                'po_id' => $po['po_id'] ?? null,
                'po_number' => $poNumber,
                'po_date' => $po['customer_po_date'] ?? null,
                'label' => $poNumber . (isset($po['customer_po_date']) && $po['customer_po_date'] ? ' • ' . date('Y-m-d', strtotime($po['customer_po_date'])) : '')
            ];
        }

        echo json_encode($payload);
        exit;
    }

    public function getItemBomInfo() {
        header('Content-Type: application/json');
        $itemId = intval($_GET['item_id'] ?? 0);
        if ($itemId <= 0) {
            echo json_encode(['bom_code' => '', 'batch_qty' => '', 'batch_uom' => '', 'fill_volume' => '', 'uom' => '', 'batch_unit_divisor' => 1000, 'is_legacy_formula' => 0]);
            exit;
        }

        $bom = $this->warehouseModel->hasBOM($itemId);
        $fillVolume = $bom ? ($bom['fill_volume'] ?? $bom['batch_qty'] ?? '') : '';
        $uom = $bom ? ($bom['uom'] ?? $bom['batch_uom'] ?? '') : '';
        echo json_encode([
            'bom_code' => $bom ? ($bom['bom_code'] ?? '') : '',
            'batch_qty' => $fillVolume,
            'batch_uom' => $uom,
            'fill_volume' => $fillVolume,
            'uom' => $uom,
            'batch_unit_divisor' => $bom ? floatval($bom['batch_unit_divisor'] ?? 1000) : 1000,
            'is_legacy_formula' => $bom ? !empty($bom['is_legacy_formula']) : false,
        ]);
        exit;
    }

    public function searchItems() {
        header('Content-Type: application/json');
        try {
            $query = trim($_GET['q'] ?? '');
            if (strlen($query) < 1) {
                echo json_encode([]);
                exit;
            }
            $items = $this->catalogModel->searchFgItems($query);
            echo json_encode($items);
        } catch (\Exception $e) {
            error_log('searchItems error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to search items']);
        }
        exit;
    }

    public function saveFgInput() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=production&action=fgInput');
            exit;
        }
        $maxRetries = 3;
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $conn = \App\Core\BaseModel::getConnection();
            $conn->beginTransaction();
            try {
                $item_ids = $_POST['item_id'] ?? [];
                if (!is_array($item_ids)) $item_ids = [$item_ids];

                $lotNumbers = $_POST['lot_number'] ?? [];
                $quantities = $_POST['added_quantity'] ?? [];
                $pcsPerCases = $_POST['pcs_per_case'] ?? [];
                $shifts = $_POST['shift'] ?? [];
                $rejectStatuses = $_POST['reject_status'] ?? [];
                $stsRemarks = $_POST['sts_remarks'] ?? [];
                $preparedByName = trim($_POST['prepared_by_name'] ?? '') ?: null;
                $checkedByName = trim($_POST['checked_by_name'] ?? '') ?: null;
                $receivedByName = trim($_POST['received_by_name'] ?? '') ?: null;

                if (!is_array($lotNumbers)) $lotNumbers = [$lotNumbers];
                if (!is_array($quantities)) $quantities = [$quantities];
                if (!is_array($pcsPerCases)) $pcsPerCases = [$pcsPerCases];
                if (!is_array($shifts)) $shifts = [$shifts];
                if (!is_array($rejectStatuses)) $rejectStatuses = [$rejectStatuses];
                if (!is_array($stsRemarks)) $stsRemarks = [$stsRemarks];

                $itemCache = [];
                $savedCount = 0;
                $savedItemDescriptions = [];
                $savedHistoryIds = [];
                $savedLotGroups = [];

                $stmtLast = $conn->prepare("SELECT sts_ref FROM production_history WHERE sts_ref LIKE 'STS-%' ORDER BY history_id DESC LIMIT 1 FOR UPDATE");
                $stmtLast->execute();
                $lastSts = $stmtLast->fetchColumn();
                if ($lastSts && preg_match('/STS-(\d+)/', $lastSts, $m)) {
                    $nextNum = intval($m[1]) + 1;
                } else {
                    $nextNum = 1;
                }

                foreach ($lotNumbers as $i => $lotNumber) {
                    $lotNumber = trim($lotNumber ?? '');
                    $qty = intval($quantities[$i] ?? 0);
                    $item_id = $item_ids[$i] ?? null;
                    $shift = trim($shifts[$i] ?? '');

                    // Skip completely empty rows
                    if ($lotNumber === '' && $qty <= 0 && empty($item_id) && $shift === '') {
                        continue;
                    }

                    // Reject partially filled rows
                    $rowErrors = [];
                    if (empty($item_id)) $rowErrors[] = 'Item';
                    if ($lotNumber === '') $rowErrors[] = 'Lot Number';
                    if ($qty <= 0) $rowErrors[] = 'Quantity';
                    if ($shift === '') $rowErrors[] = 'Shift';

                    if (!empty($rowErrors)) {
                        throw new \RuntimeException('Row ' . ($i + 1) . ': Missing required fields (' . implode(', ', $rowErrors) . ')');
                    }

                    if (!isset($itemCache[$item_id])) {
                        $itemStmt = $conn->prepare("SELECT item_id, item_code, item_description, uom_conversion FROM items WHERE item_id = :item_id AND `remove` = 0 AND status = 1 AND item_type = 'FG'");
                        $itemStmt->execute(['item_id' => $item_id]);
                        $itemCache[$item_id] = $itemStmt->fetch();
                    }
                    $item = $itemCache[$item_id];
                    if (!$item) continue;

                    $savedItemDescriptions[] = $item['item_description'];

                    $autoStsRef = 'STS-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
                    $nextNum++;

                    $lotQtyStmt = $conn->prepare("SELECT COALESCE(SUM(added_quantity), 0) as total_qty FROM production_history WHERE item_id = :item_id AND lot_number = :lot_number AND is_removed = 0");
                    $lotQtyStmt->execute(['item_id' => $item_id, 'lot_number' => $lotNumber]);
                    $previousLotQty = intval($lotQtyStmt->fetchColumn());

                    $pcsVal = intval($pcsPerCases[$i] ?? 0);
                    if ($pcsVal <= 0) {
                        $pcsVal = intval($item['uom_conversion'] ?? 0);
                    }

                    try {
                        $lotId = $this->warehouseModel->upsertItemLot([
                            'item_id' => $item_id,
                            'lot_number' => $lotNumber,
                            'quantity_produced' => $qty,
                            'pcs_per_case' => $pcsVal > 0 ? $pcsVal : null,
                            'created_by' => $_SESSION['user_id']
                        ]);
                    } catch (\Exception $e) {
                        error_log('FG Input upsertItemLot error: ' . $e->getMessage());
                        throw $e;
                    }

                    $newLotQty = $previousLotQty + $qty;

                    $histPcs = $pcsVal > 0 ? $pcsVal : null;
                    $histShift = trim($shifts[$i] ?? '') ?: null;
                    $histReject = trim($rejectStatuses[$i] ?? '') ?: null;
                    $histRemarks = trim($stsRemarks[$i] ?? '') ?: null;

                    try {
                        $histStmt = $conn->prepare("INSERT INTO production_history (po_id, poi_id, item_id, lot_number, item_description, sts_ref, shift, reject_status, sts_remarks, pcs_per_case, prepared_by_name, checked_by_name, received_by_name, user_id, previous_quantity, added_quantity, new_quantity, date_created)
                            VALUES (NULL, NULL, :item_id, :lot_number, :item_description, :sts_ref, :shift, :reject_status, :sts_remarks, :pcs_per_case, :prepared_by_name, :checked_by_name, :received_by_name, :user_id, :previous_quantity, :added_quantity, :new_quantity, NOW())");
                        $histStmt->execute([
                                'item_id' => $item_id,
                                'lot_number' => $lotNumber,
                                'item_description' => $item['item_description'],
                                'sts_ref' => $autoStsRef,
                                'shift' => $histShift,
                                'reject_status' => $histReject,
                                'sts_remarks' => $histRemarks,
                                'pcs_per_case' => $histPcs,
                                'prepared_by_name' => $preparedByName,
                                'checked_by_name' => $checkedByName,
                                'received_by_name' => $receivedByName,
                                'user_id' => $_SESSION['user_id'],
                                'previous_quantity' => $previousLotQty,
                                'added_quantity' => $qty,
                                'new_quantity' => $newLotQty,
                            ]);
                        $savedHistoryIds[] = intval($conn->lastInsertId());
                        $savedLotGroups[] = ['item_id' => intval($item_id), 'lot_number' => $lotNumber];
                    } catch (\Exception $e) {
                        error_log('FG Input production_history INSERT error: ' . $e->getMessage() . ' | Query params: lot_number=' . $lotNumber . ', item_desc=' . ($item['item_description'] ?? 'null') . ', sts_ref=' . $autoStsRef . ', user_id=' . $_SESSION['user_id'] . ', added_qty=' . $qty);
                        throw $e;
                    }

                    $this->saveItemConversionIfNeeded(null, intval($pcsPerCases[$i] ?? 0), $item_id, true);
                    $savedCount++;
                }

                if ($savedCount === 0) {
                    $conn->rollBack();
                    $_SESSION['error'] = 'No valid lot entries to save.';
                    header('Location: ?controller=production&action=fgInput');
                    exit;
                }

                $uniqueDescriptions = array_unique($savedItemDescriptions);
                $descList = implode(', ', $uniqueDescriptions);
                NotificationHelper::create('production', 'FG Input', $savedCount . ' lot(s) of FG produced for ' . $descList, 'warehouse', '?controller=warehouse&action=deliveries', $_SESSION['user_id']);
                NotificationHelper::qcInspectionNeeded('New FG production: ' . $descList, '', $_SESSION['user_id']);

                // Invariant guard: re-sync every touched lot group from production history
                $savedGroups = [];
                foreach ($savedLotGroups ?? [] as $g) {
                    $k = $g['item_id'] . '|' . $g['lot_number'];
                    if (!isset($savedGroups[$k])) $savedGroups[$k] = $g;
                }
                foreach ($savedGroups as $g) {
                    $this->warehouseModel->reconcileLotGroup($g['item_id'], $g['lot_number'], $conn);
                }

                $conn->commit();
                $_SESSION['success'] = $savedCount . ' lot(s) of FG recorded successfully for ' . $descList . '.';
                header('Location: ?controller=production&action=fgInput');
                exit;
            } catch (\PDOException $e) {
                $conn->rollBack();
                if ($e->errorInfo[1] == 23000 && $attempt < $maxRetries - 1) {
                    continue;
                }
                $_SESSION['error'] = 'Failed to save FG: ' . $e->getMessage();
                header('Location: ?controller=production&action=fgInput');
                exit;
            } catch (\Exception $e) {
                $conn->rollBack();
                $_SESSION['error'] = 'Failed to save FG: ' . $e->getMessage();
                header('Location: ?controller=production&action=fgInput');
                exit;
            }
        }
    }

    public function undoLastFgEntry() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $historyIds = $input['history_ids'] ?? [];
        if (empty($historyIds) || !is_array($historyIds)) {
            http_response_code(400);
            echo json_encode(['error' => 'No history IDs provided']);
            exit;
        }
        try {
            $result = $this->warehouseModel->undoProductionHistory($historyIds, $_SESSION['user_id']);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Entry reversed successfully.']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Reversal window expired or entries not found.']);
            }
        } catch (\Exception $e) {
            error_log('undoLastFgEntry error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to reverse entry. Please try again.']);
        }
        exit;
    }

    public function history() {
        $search = $_GET['search'] ?? '';
        $filterCustomer = $_GET['filter_customer'] ?? '';
        $filterItem = $_GET['filter_item'] ?? '';
        $filterLot = $_GET['filter_lot'] ?? '';

        $hasFilter = $search || $filterCustomer || $filterItem || $filterLot;
        if ($hasFilter) {
            $filters = [];
            if ($search) $filters['search'] = $search;
            if ($filterCustomer) $filters['customer_name'] = $filterCustomer;
            if ($filterItem) $filters['item_description'] = $filterItem;
            if ($filterLot) $filters['lot_number'] = $filterLot;
            $allHistory = $this->warehouseModel->getProductionHistoryFiltered($filters);
        } else {
            $allHistory = $this->warehouseModel->getProductionHistory();
        }

        $allCustomers = array_values(array_unique(array_filter(array_column($allHistory, 'customer_name'))));
        $allItems = array_values(array_unique(array_filter(array_column($allHistory, 'item_description'))));
        $allLots = array_values(array_unique(array_filter(array_column($allHistory, 'lot_number'))));

        if ($hasFilter) {
            $pagination = ['items' => $allHistory, 'page' => 1, 'perPage' => count($allHistory), 'total' => count($allHistory), 'totalPages' => 1, 'hasNext' => false, 'hasPrev' => false];
        } else {
            $pagination = Pagination::paginate($allHistory, 10);
        }
        $data['history'] = $pagination['items'];

        $data['page'] = $pagination['page'];
        $data['totalPages'] = $pagination['totalPages'];
        $data['total'] = $pagination['total'];
        $data['search'] = $search;
        $data['filterCustomer'] = $filterCustomer;
        $data['filterItem'] = $filterItem;
        $data['filterLot'] = $filterLot;
        $data['allCustomers'] = $allCustomers;
        $data['allItems'] = $allItems;
        $data['allLots'] = $allLots;
        $data['reportsCount'] = $this->warehouseModel->getProductionReportsCount();
        $data['page_title'] = 'Production History';
        $this->render('history/index', $data);
    }

    public function reportHistory() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $history_id = $_POST['history_id'] ?? null;
                $reason = trim($_POST['reason'] ?? '');
                $report_type = $_POST['report_type'] ?? 'lot_number';
                if (!$history_id || empty($reason)) {
                    $_SESSION['error'] = 'Missing history ID or reason.';
                    header('Location: ?controller=production&action=history');
                    exit;
                }
                $conn = \App\Core\BaseModel::getConnection();
                $stmt = $conn->prepare("SELECT history_id, poi_id, po_id, lot_number FROM production_history WHERE history_id = :hid");
                $stmt->execute(['hid' => $history_id]);
                $history = $stmt->fetch();
                if ($history) {
                    $this->warehouseModel->createProductionReport(
                        $history['history_id'],
                        $history['poi_id'],
                        $history['po_id'],
                        $history['lot_number'],
                        $_SESSION['user_id'],
                        $reason,
                        $report_type
                    );
                    AuditModel::log($_SESSION['user_id'], 'CREATE', 'production', 'Reported production history entry #' . $history_id . ' with reason: ' . $reason, null, $_POST, 'production_history', null);
                    NotificationHelper::productionReported($history_id, $reason, $_SESSION['user_id']);
                    $_SESSION['success'] = 'Report submitted successfully.';
                } else {
                    $_SESSION['error'] = 'History record not found.';
                }
            } catch (\Exception $e) {
                error_log('reportHistory error: ' . $e->getMessage());
                $_SESSION['error'] = 'Failed to submit report';
            }
            header('Location: ?controller=production&action=history');
            exit;
        }
    }

    public function getPODetails() {
        header('Content-Type: application/json');
        try {
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing PO ID']);
                exit;
            }

            $po = $this->warehouseModel->getPurchaseOrderById($id);
            if (!$po) {
                http_response_code(404);
                echo json_encode(['error' => 'PO not found']);
                exit;
            }

            $po_items = $this->warehouseModel->getPurchaseOrderItems($id);
            foreach ($po_items as &$item) {
                $item['lots'] = $this->warehouseModel->getLotsByPOItem($item['poi_id']);
            }
            unset($item);

            echo json_encode(['po' => $po, 'po_items' => $po_items]);
        } catch (\Exception $e) {
            error_log('getPODetails error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load PO details']);
        }
        exit;
    }

    public function getLotsByPOItem() {
        header('Content-Type: application/json');
        try {
            $poiId = $_GET['poi_id'] ?? null;
            if (!$poiId) {
                echo json_encode([]);
                exit;
            }
            $conn = \App\Core\BaseModel::getConnection();
            $stmt = $conn->prepare("SELECT po_id FROM purchase_order_items WHERE poi_id = ?");
            $stmt->execute([$poiId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $poId = $row ? $row['po_id'] : null;
            $lots = $this->warehouseModel->getLotsByPOItem($poiId, $poId);
            echo json_encode($lots);
        } catch (\Exception $e) {
            error_log('getLotsByPOItem error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load lots']);
        }
        exit;
    }

    public function activityLogs() {
        try {
            $auditModel = new \App\Models\AuditModel();
            $filters = [
                'department' => $_SESSION['department'] ?? 'production',
                'user_id'    => $_GET['user_id'] ?? '',
                'module'     => $_GET['module'] ?? '',
                'log_action' => $_GET['log_action'] ?? '',
                'date_from'  => $_GET['date_from'] ?? '',
                'date_to'    => $_GET['date_to'] ?? '',
                'search'     => $_GET['search'] ?? '',
            ];
            foreach ($filters as $k => $v) { if ($v === '') unset($filters[$k]); }
            $logs = $auditModel->getLogs($filters, $_GET['page'] ?? 1, 20);
            $data['logs'] = $logs;
            $data['users'] = $auditModel->getAllUsers();
            $data['filters'] = $_GET;
            $data['logController'] = 'production';
            $data['departmentLocked'] = true;
            $data['hideDeptColumn'] = true;
            $data['stats'] = [
                'today_count' => \App\Models\AuditModel::getLogStats('production')['today_count'] ?? 0,
                'by_department' => [],
            ];
            $data['page_title'] = 'Activity Logs';
            $this->render('activity_logs/index', $data);
        } catch (\Exception $e) {
            error_log('activityLogs error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to load activity logs';
            header('Location: ?controller=production&action=dashboard');
            exit;
        }
    }

    public function printSTS() {
        try {
            $stsRef = trim($_GET['sts_ref'] ?? '');
            if (empty($stsRef)) {
                echo "<div class='container mt-5'><div class='alert alert-danger'>Missing STS reference.</div></div>";
                exit;
            }

            $conn = \App\Core\BaseModel::getConnection();
            $stmt = $conn->prepare("
                SELECT ph.*, 
                   po.customer_po_number, po.po_number,
                   c.customer_name, c.customer_code, c.customer_address, c.customer_tin,
                   u.full_name AS prepared_by_name
                FROM production_history ph
                LEFT JOIN purchase_orders po ON ph.po_id = po.po_id
                LEFT JOIN purchase_order_items poi ON ph.poi_id = poi.poi_id
                LEFT JOIN items i ON poi.item_id = i.item_id
                LEFT JOIN customers c ON po.customer_id = c.customer_id
                LEFT JOIN users u ON ph.user_id = u.user_id
                WHERE ph.sts_ref = :sts_ref
                ORDER BY ph.history_id ASC
            ");
            $stmt->execute(['sts_ref' => $stsRef]);
            $entries = $stmt->fetchAll();

            if (empty($entries)) {
                echo "<div class='container mt-5'><div class='alert alert-danger'>STS \"" . htmlspecialchars($stsRef) . "\" not found.</div></div>";
                exit;
            }

            $data = [
                'sts_ref' => $stsRef,
                'entries' => $entries,
                'po' => [
                    'po_number' => $entries[0]['po_number'] ?? '',
                    'customer_po_number' => $entries[0]['customer_po_number'] ?? '',
                    'customer_name' => $entries[0]['customer_name'] ?? '',
                    'customer_code' => $entries[0]['customer_code'] ?? '',
                    'customer_address' => $entries[0]['customer_address'] ?? '',
                    'customer_tin' => $entries[0]['customer_tin'] ?? '',
                ],
                'prepared_by' => $entries[0]['prepared_by_name'] ?? '',
                'date_created' => $entries[0]['date_created'] ?? '',
            ];

            extract($data);
            include __DIR__ . "/../views/production/print_sts.php";
        } catch (\Exception $e) {
            error_log('printSTS error: ' . $e->getMessage());
            echo "<div class='container mt-5'><div class='alert alert-danger'>Failed to load STS. Please try again.</div></div>";
        }
        exit;
    }

    private function render($view, $data = []) {
        $data['reportsCount'] = $this->warehouseModel->getProductionReportsCount();
        extract($data);
        ob_start();
        include __DIR__ . "/../views/{$view}.php";
        $content = ob_get_clean();
        include __DIR__ . "/../views/layouts/main.php";
    }

    private function saveItemConversionIfNeeded($poi_id, $pcs_per_case, $item_id = null, $always_update = false) {
        if ($pcs_per_case <= 0) return;
        $conn = \App\Core\BaseModel::getConnection();
        if ($item_id) {
            $stmt = $conn->prepare("SELECT item_id, uom_conversion FROM items WHERE item_id = :item_id AND `remove` = 0");
            $stmt->execute(['item_id' => $item_id]);
        } else {
            $stmt = $conn->prepare("SELECT poi.item_id, i.uom_conversion FROM purchase_order_items poi JOIN items i ON poi.item_id = i.item_id WHERE poi.poi_id = :poi_id AND i.`remove` = 0");
            $stmt->execute(['poi_id' => $poi_id]);
        }
        $row = $stmt->fetch();
        if (!$row) return;
        if ($always_update || empty($row['uom_conversion']) || $row['uom_conversion'] == 0) {
            $update = $conn->prepare("UPDATE items SET uom_conversion = :conv WHERE item_id = :id AND `remove` = 0");
            $update->execute(['conv' => $pcs_per_case, 'id' => $row['item_id']]);
        }
    }
}