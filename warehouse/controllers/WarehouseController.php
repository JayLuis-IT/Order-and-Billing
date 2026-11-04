<?php
namespace App\Controllers;

use App\Models\WarehouseModel;
use App\Models\BackloadModel;
use App\Models\CatalogModel;
use App\Models\ItemImportModel;
use App\Models\AuditModel;
use App\Helpers\Pagination;
use App\Helpers\NotificationHelper;
use App\Helpers\SpreadsheetReader;
use App\Helpers\XlsxExport;
use App\Helpers\RoleHelper;

class WarehouseController {
    private $warehouseModel;
    private $backloadModel;
    private $catalogModel;
    private $itemImportModel;

    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?controller=auth&action=login');
            exit;
        }
        $action = $_GET['action'] ?? '';
        // index.php defaults the action to 'login'; missing/unknown action
        // dispatches to index() — mirror that for the permission gates.
        if ($action === '' || $action === 'login') {
            $action = 'index';
        }
        $dept = $_SESSION['department'] ?? '';
        $mrpAllowed = in_array($action, ['mrp', 'mrpPDF', 'saveMrpCalculation', 'getNextMrpRef', 'mrpSnapshotPrint']) && in_array($dept, ['warehouse', 'rnd', 'admin']);
        $apiActions = ['getPODetails', 'getItemsByCustomer', 'backloadDelivery', 'getDeliveryLotsForBackload',
            'getLotsByPOItem', 'getPOItemsForAssignment', 'getActivePOsForAssignment', 'getLotsForTransfer',
            'viewBackloads', 'getPOsContainingItem', 'getAvailableItemsForDelivery', 'searchItems',
            'mrpRunDetail', 'purchasingPo', 'receivingPo', 'lmrEntry', 'getCustomerPOs', 'getItemBomInfo', 'getItemBalances',
            'getPoFullDetails', 'getSuppliers', 'getItemBoms', 'getBomComponents', 'printPurchasingPo'];
        // Item Master List is viewable read-only by rnd/admin; only warehouse
        // roles can open it too (role gate below strips edit for all of them).
        $itemListReadOnly = $action === 'items' && in_array($dept, ['warehouse', 'rnd', 'admin']);
        // Item master editing (create/import/adjust SOH) is System Admin only.
        $itemManageAllowed = in_array($action, ['storeItem', 'adjustItemSoh', 'itemImportForm', 'itemImportPreview', 'itemImportConfirm'])
            && $dept === 'admin';
        // Edit modal (metadata-only corrections) is shared with QC for inspected records
        $poEditAllowed = $action === 'updatePurchasingPo' && in_array($dept, ['warehouse', 'qc']);
        if (!$itemListReadOnly && !$itemManageAllowed && !$mrpAllowed && !$poEditAllowed && !in_array($action, $apiActions) && $dept !== 'warehouse') {
            header('Location: ' . RoleHelper::homeUrl());
            exit;
        }
        // Role gate: warehouse users only reach actions allowed for their role.
        if ($dept === 'warehouse') {
            $role = RoleHelper::currentRole();
            if ($role === null) {
                header('Location: ?controller=auth&action=noRole');
                exit;
            }
            if (!RoleHelper::warehouseCan($role, $action)) {
                $_SESSION['error'] = 'You do not have access to that feature.';
                header('Location: ' . RoleHelper::homeUrl());
                exit;
            }
        }
        // QC cross-module access (receiving/purchasing PO views + MRP Check
        // run review) is qc_admin only.
        if ($dept === 'qc') {
            $qcRole = RoleHelper::currentRole();
            $qcWarehouseActions = ['receivingPo', 'purchasingPo', 'updatePurchasingPo', 'getPoFullDetails', 'getSuppliers', 'mrpRunDetail', 'printPurchasingPo'];
            if ($qcRole !== 'qc_admin' || !in_array($action, $qcWarehouseActions, true)) {
                if (!in_array($action, ['getPODetails', 'getItemsByCustomer', 'getCustomerPOs', 'getItemBomInfo',
                    'getItemBalances', 'searchItems', 'getItemBoms', 'getBomComponents', 'getLotsByPOItem'], true)) {
                    $_SESSION['error'] = 'You do not have access to that feature.';
                    header('Location: ' . RoleHelper::homeUrl());
                    exit;
                }
            }
        }
        $this->warehouseModel = new WarehouseModel();
        $this->backloadModel = new BackloadModel();
        $this->catalogModel = new CatalogModel();
        $this->itemImportModel = new ItemImportModel();
    }

    private function redirectToRoleHome(string $action): void {
        header('Location: ' . RoleHelper::homeUrl());
        exit;
    }

    private function enforceReadOnlyPurchasingPo(): void {
        // QC reaches updatePurchasingPo via its own inline dept check; every
        // other mutating purchasing-PO action is warehouse roles only.
        $role = ($_SESSION['department'] === 'warehouse') ? RoleHelper::currentRole() : null;
        if (!in_array($role, ['whse_admin', 'whse_procurement'], true)) {
            $_SESSION['error'] = 'Purchasing PO is read-only for this role.';
            $this->redirectToRoleHome('purchasingPo');
        }
    }

    private function enforceWarehouseReceivingAccess(): void {
        $role = ($_SESSION['department'] === 'warehouse') ? RoleHelper::currentRole() : ($_SESSION['department'] ?? '');
        if (!in_array($role, ['whse_admin', 'whse_officer'], true)) {
            $_SESSION['error'] = 'Only warehouse admin/officer users can receive purchasing shipments.';
            $this->redirectToRoleHome('receivingPo');
        }
    }

    public function index() {
        $role = (($_SESSION['department'] ?? '') === 'warehouse') ? RoleHelper::currentRole() : null;

        if ($role === 'whse_officer') {
            $data['page_title'] = 'Warehouse Officer Dashboard';
            $data['tabCounts'] = $this->warehouseModel->getReceivingTabCounts();
            $data['deliveries'] = $this->warehouseModel->getDeliveries();
            $data['deliveryReportsCount'] = $this->warehouseModel->getDeliveryReportsCount();
            $this->render('dashboards/officer', $data);
            return;
        }
        if ($role === 'whse_ppic') {
            $data['page_title'] = 'Warehouse PPIC Dashboard';
            $data['purchase_orders'] = $this->warehouseModel->getActivePOsForDashboard(5);
            $poIds = array_column($data['purchase_orders'], 'po_id');
            $data['po_items_map'] = $this->warehouseModel->getPurchaseOrderItemsByPOIds($poIds);
            $this->render('dashboards/ppic', $data);
            return;
        }
        if ($role === 'whse_procurement') {
            $data['page_title'] = 'Warehouse Procurement Dashboard';
            $data['procurementCounts'] = $this->warehouseModel->getProcurementStatusCounts();
            $this->render('dashboards/procurement', $data);
            return;
        }
        if ($role === 'whse_receiving') {
            $data['page_title'] = 'Warehouse Receiving Dashboard';
            $data['recentMovements'] = $this->warehouseModel->getStockMovements(10);
            $data['tabCounts'] = $this->warehouseModel->getReceivingTabCounts();
            $this->render('dashboards/receiving', $data);
            return;
        }

        $data['page_title'] = 'Warehouse Dashboard';
        $data['customers'] = $this->catalogModel->getCustomers();
        $data['items'] = $this->catalogModel->getItems();
        $data['purchase_orders'] = $this->warehouseModel->getActivePOsForDashboard(5);
        $poIds = array_column($data['purchase_orders'], 'po_id');
        $data['po_items_map'] = $this->warehouseModel->getPurchaseOrderItemsByPOIds($poIds);

        $data['deliveries'] = $this->warehouseModel->getDeliveries();
        $this->render('dashboard', $data);
    }

    public function lmrEntry() {
        $data['page_title'] = 'LMR Entry';
        $data['customers'] = $this->catalogModel->getCustomers();
        $data['items'] = [];
        $data['allItems'] = $this->catalogModel->getLmrProductItems();
        $lmrModel = new \App\Models\LmrModel();
        $data['boms'] = $lmrModel->getActiveBoms();
        $this->render('lmr_entry', $data);
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

    /**
     * Legacy-aware required quantity for one MRP component row.
     * Legacy: (orderQty / batch_qty) * dosage * wastage
     * New:    RM → ((orderQty * fill_volume) / divisor) * dosage% * wastage
     *         PM/SFG → orderQty * dosage * wastage  (direct unit rate, no /100)
     */
    private function computeMrpRequiredQty($orderQty, $poi, $comp) {
        $dosage = floatval($comp['dosage_rate'] ?? 0);
        $wastage = floatval($comp['wastage_allowance_pct'] ?? 0);
        $type = strtoupper($comp['item_type'] ?? $comp['category'] ?? '');

        if (!empty($poi['is_legacy_formula'])) {
            $batchQty = floatval($poi['fill_volume'] ?? $poi['batch_qty'] ?? 1) ?: 1;
            $base = ($orderQty / $batchQty) * $dosage;
        } elseif ($type === 'RM') {
            $fillVolume = floatval($poi['fill_volume'] ?? $poi['batch_qty'] ?? 0);
            $divisor = floatval($poi['batch_unit_divisor'] ?? 1000) ?: 1000;
            $base = (($orderQty * $fillVolume) / $divisor) * ($dosage / 100);
        } else {
            // PM/SFG: direct unit dosage per finished item
            $base = $orderQty * $dosage;
        }

        return $base * (1 + $wastage / 100);
    }

    /**
     * Display/meta keys for an MRP section (legacy + new keys side by side).
     */
    private function mrpBomMeta($poi) {
        $fillVolume = floatval($poi['fill_volume'] ?? $poi['batch_qty'] ?? 1) ?: 1;
        $uom = $poi['uom'] ?? $poi['batch_uom'] ?? 'PCS';
        $divisor = floatval($poi['batch_unit_divisor'] ?? 1000) ?: 1000;
        $isLegacy = !empty($poi['is_legacy_formula']);
        $targetQty = floatval($poi['quantity']);

        return [
            'fill_volume' => $fillVolume,
            'uom' => $uom,
            'batch_unit_divisor' => $divisor,
            'is_legacy_formula' => $isLegacy,
            'batch_qty' => $fillVolume,
            'batch_uom' => $uom,
            'batches_needed' => $isLegacy ? ($targetQty / $fillVolume) : 0,
            'bulk_batch' => $isLegacy ? 0 : (($targetQty * $fillVolume) / $divisor),
        ];
    }

    /**
     * Item-pool MRP required qty (new Customer+FG+Qty flow).
     * RM:    (target * fill_volume / divisor) * (dosage/100) * (1 + wastage/100)
     * PM/SFG: target * dosage * (1 + wastage/100)
     *   — dosage is a direct unit rate per finished item (e.g. 1.0 pc/unit),
     *   NOT a percentage and NOT scaled by fill volume / UOM divisor.
     */
    private function computeMrpPoolRequiredQty($targetQty, $bomMeta, $comp) {
        $dosage = floatval($comp['dosage_rate'] ?? 0);
        $wastage = floatval($comp['wastage_allowance_pct'] ?? 0);
        $type = strtoupper($comp['item_type'] ?? $comp['category'] ?? '');

        if ($type === 'RM') {
            $fillVolume = floatval($bomMeta['fill_volume'] ?? 0);
            $divisor = floatval($bomMeta['batch_unit_divisor'] ?? 1000) ?: 1000;
            $base = (($targetQty * $fillVolume) / $divisor) * ($dosage / 100);
        } else {
            // PM / SFG / FG: direct unit dosage (e.g. 1 pc label per unit)
            $base = $targetQty * $dosage;
        }

        return $base * (1 + $wastage / 100);
    }

    public function purchaseOrders() {
        $search = $_GET['search'] ?? '';
        $filterCustomer = $_GET['filter_customer'] ?? '';
        $filterItem = $_GET['filter_item'] ?? '';
        $filterDate = $_GET['filter_date'] ?? '';
        $filterDeliveryStatus = $_GET['delivery_status'] ?? '';

        $hasFilter = $search || $filterCustomer || $filterItem || $filterDate || $filterDeliveryStatus;
        if ($hasFilter) {
            $filters = [];
            if ($search) $filters['search'] = $search;
            if ($filterCustomer) $filters['customer_name'] = $filterCustomer;
            if ($filterItem) $filters['item_description'] = $filterItem;
            if ($filterDate) $filters['date'] = $filterDate;
            if ($filterDeliveryStatus) $filters['delivery_status'] = $filterDeliveryStatus;
            $allPOs = $this->warehouseModel->getPurchaseOrdersFiltered($filters);
            $allCustomers = array_values(array_unique(array_filter(array_column($allPOs, 'customer_name'))));
            $pagination = ['items' => $allPOs, 'page' => 1, 'perPage' => count($allPOs), 'total' => count($allPOs), 'totalPages' => 1, 'hasNext' => false, 'hasPrev' => false];
        } else {
            $allPOs = $this->warehouseModel->getPurchaseOrders();
            $allCustomers = array_values(array_unique(array_filter(array_column($allPOs, 'customer_name'))));
            $pagination = Pagination::paginate($allPOs, 10);
        }

        $poIds = array_column($pagination['items'], 'po_id');
        $data['purchase_orders'] = $pagination['items'];
        $data['po_items_map'] = $this->warehouseModel->getPurchaseOrderItemsByPOIds($poIds);

        $data['page'] = $pagination['page'];
        $data['totalPages'] = $pagination['totalPages'];
        $data['total'] = $pagination['total'];
        $data['search'] = $search;
        $data['filterCustomer'] = $filterCustomer;
        $data['filterItem'] = $filterItem;
        $data['filterDate'] = $filterDate;
        $data['filterDeliveryStatus'] = $filterDeliveryStatus;
        $data['allCustomers'] = $allCustomers;
        $data['page_title'] = 'Customer PO';
        $this->render('purchase_orders/index', $data);
    }

    public function createPO() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $customer_id = intval($_POST['customer_id'] ?? 0);
                if ($customer_id <= 0) {
                    throw new \RuntimeException('Please select a valid customer.');
                }

                $items = json_decode($_POST['items_json'] ?? '', true);
                if (!is_array($items) || empty($items)) {
                    throw new \RuntimeException('Please add at least one item to the purchase order.');
                }
                foreach ($items as $item) {
                    if (empty($item['item_id']) || !isset($item['quantity']) || intval($item['quantity']) <= 0) {
                        throw new \RuntimeException('Each item must have a valid item and quantity greater than zero.');
                    }
                }

                $production_type = $_POST['production_type'] ?? 'normal';
                $conn = \App\Core\BaseModel::getConnection();

                $po_id = $this->warehouseModel->createPurchaseOrder([
                    'customer_po_number' => $_POST['customer_po_number'],
                    'customer_po_date' => $_POST['customer_po_date'],
                    'customer_id' => $customer_id,
                    'requested_by' => $_SESSION['user_id'],
                    'customer_terms' => $_POST['customer_terms'] ?? 0,
                    'production_type' => $production_type
                ]);

                $items = json_decode($_POST['items_json'], true);
                foreach ($items as $item) {
                    $poi_id = $this->warehouseModel->createPurchaseOrderItem(
                        $po_id,
                        $item['item_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['uom'] ?? 'PCS',
                        $item['item_code'] ?? null
                    );

                }

                // Recalculate PO-level produced_quantity (once after all items)
                $conn->prepare("UPDATE purchase_orders SET produced_quantity = (
                    SELECT COALESCE(SUM(produced_quantity), 0) FROM purchase_order_items WHERE po_id = :po_id
                ) WHERE po_id = :po_id2")
                    ->execute(['po_id' => $po_id, 'po_id2' => $po_id]);

                $po = $this->warehouseModel->getPurchaseOrderById($po_id);
                $cleanData = [
                    'customer_po_number' => $_POST['customer_po_number'] ?? '',
                    'customer_po_date' => $_POST['customer_po_date'] ?? '',
                    'production_type' => $_POST['production_type'] ?? 'normal',
                ];
                $poLabel = $po['customer_po_number'] ?? $po['po_number'] ?? 'PO #' . $po_id;
                $customerLabel = $po['customer_name'] ?? 'customer #' . $customer_id;
                AuditModel::log($_SESSION['user_id'], 'CREATE', 'warehouse', 'Created purchase order ' . $poLabel . ' for ' . $customerLabel . ' (normal production)', null, $cleanData, 'purchase_order', $po_id);

                NotificationHelper::poCreated($poLabel, $customerLabel, $po_id, $_SESSION['user_id']);

                $_SESSION['success'] = 'Purchase Order ' . $_POST['customer_po_number'] . ' created successfully';
                header('Location: ?controller=warehouse&action=purchaseOrders');
                exit;
            } catch (\Exception $e) {
                error_log('createPO error: ' . $e->getMessage());
                $_SESSION['error'] = 'Failed to create purchase order. Please check your input and try again.';
                header('Location: ?controller=warehouse&action=createPO');
                exit;
            }
        }
        $data['page_title'] = 'Create PO';
        $data['customers'] = $this->catalogModel->getCustomers();
        $data['items'] = $this->catalogModel->getItems();
        $this->render('purchase_orders/create', $data);
    }

    public function editPO() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $po_id = $_POST['po_id'] ?? null;
            if (!$po_id) {
                $_SESSION['error'] = 'Invalid PO';
                header('Location: ?controller=warehouse&action=purchaseOrders');
                exit;
            }

            $conn = \App\Core\BaseModel::getConnection();
            $conn->beginTransaction();
            try {

            // Fetch OLD PO header data BEFORE any updates (for audit log)
            $oldPoStmt = $conn->prepare("SELECT customer_id, customer_po_number, customer_po_date, production_type FROM purchase_orders WHERE po_id = :po_id");
            $oldPoStmt->execute(['po_id' => $po_id]);
            $oldPoData = $oldPoStmt->fetch();

            $customer_id = $oldPoData['customer_id'] ?? 0;
            $production_type = $oldPoData['production_type'] ?? 'normal';

            if (isset($_POST['customer_po_date'])) {
                $stmt = $conn->prepare("UPDATE purchase_orders SET customer_po_number = :po_number, customer_po_date = :date, production_type = :type WHERE po_id = :po_id");
                $stmt->execute([
                    'po_number' => $_POST['customer_po_number'] ?? '',
                    'date' => $_POST['customer_po_date'],
                    'type' => $_POST['production_type'] ?? 'normal',
                    'po_id' => $po_id
                ]);
                $production_type = $_POST['production_type'] ?? $production_type;
            }

            $items = json_decode($_POST['items_json'], true);
            if (!empty($items)) {
                // Fetch current PO items before updating to capture quantity changes
                $oldItems = [];
                $stmt = $conn->prepare("SELECT poi_id, quantity, item_id FROM purchase_order_items WHERE po_id = :po_id");
                $stmt->execute(['po_id' => $po_id]);
                $currentItems = $stmt->fetchAll();
                foreach ($currentItems as $ci) {
                    $oldItems[$ci['poi_id']] = ['quantity' => $ci['quantity'], 'item_id' => $ci['item_id']];
                }

                $itemChanges = [];

                foreach ($items as $item) {
                    if (!empty($item['poi_id'])) {
                        // Fetch current item state before updating
                        $poiStmt = $conn->prepare("SELECT quantity, produced_quantity, item_id FROM purchase_order_items WHERE poi_id = :poi_id");
                        $poiStmt->execute(['poi_id' => $item['poi_id']]);
                        $currentItem = $poiStmt->fetch();

                        // Track quantity changes
                        if (isset($oldItems[$item['poi_id']])) {
                            $oldQty = (int)$oldItems[$item['poi_id']]['quantity'];
                            $newQty = (int)$item['quantity'];
                            if ($oldQty !== $newQty) {
                                $itemChanges[] = [
                                    'action' => 'quantity_changed',
                                    'poi_id' => $item['poi_id'],
                                    'item_id' => $oldItems[$item['poi_id']]['item_id'],
                                    'old_quantity' => $oldQty,
                                    'new_quantity' => $newQty,
                                ];
                            }
                        }

                        $this->warehouseModel->updatePurchaseOrderItem(
                            $item['poi_id'],
                            $item['quantity'],
                            $item['item_id'] ?? null,
                            $item['unit_price'] ?? null,
                            $item['uom'] ?? null
                        );
                    } else {
                        $poi_id = $this->warehouseModel->createPurchaseOrderItem(
                            $po_id,
                            $item['item_id'],
                            $item['quantity'],
                            $item['unit_price'],
                            $item['uom'] ?? 'PCS',
                            $item['item_code'] ?? null
                        );

                    }
                }
            }

            $this->warehouseModel->updatePOTotalQuantity($po_id);

            // Track items added and removed
            $newPoiIds = [];
            foreach ($items as $item) {
                if (!empty($item['poi_id'])) {
                    $newPoiIds[] = $item['poi_id'];
                }
            }
            $oldPoiIds = array_keys($oldItems);
            $addedPoiIds = array_diff($newPoiIds, $oldPoiIds);
            $removedPoiIds = array_diff($oldPoiIds, $newPoiIds);

            foreach ($addedPoiIds as $poiId) {
                $item = $this->warehouseModel->getPurchaseOrderItemById($poiId);
                if ($item) {
                    $itemChanges[] = [
                        'action' => 'item_added',
                        'poi_id' => $poiId,
                        'item_id' => $item['item_id'] ?? 0,
                        'quantity' => $item['quantity'] ?? 0,
                    ];
                }
            }
            foreach ($removedPoiIds as $poiId) {
                $itemChanges[] = [
                    'action' => 'item_removed',
                    'poi_id' => $poiId,
                    'item_id' => $oldItems[$poiId]['item_id'] ?? 0,
                    'old_quantity' => $oldItems[$poiId]['quantity'] ?? 0,
                ];
                // Cascade removal: soft-delete where supported, hard-delete where not
                // production_lots: has is_removed column
                $conn->prepare("UPDATE production_lots SET `is_removed` = 1 WHERE poi_id = :poi_id AND `is_removed` = 0")
                    ->execute(['poi_id' => $poiId]);
                // production_history: has is_removed column
                $conn->prepare("UPDATE production_history SET `is_removed` = 1 WHERE poi_id = :poi_id AND `is_removed` = 0")
                    ->execute(['poi_id' => $poiId]);
                // production_reports: no soft-delete column, hard delete
                $conn->prepare("DELETE FROM production_reports WHERE poi_id = :poi_id")
                    ->execute(['poi_id' => $poiId]);
                // delivery_reports: no soft-delete column, hard delete
                $conn->prepare("DELETE FROM delivery_reports WHERE poi_id = :poi_id")
                    ->execute(['poi_id' => $poiId]);
                // delivery_receipts: soft-delete via joined deliveries
                $conn->prepare("UPDATE delivery_receipts dr 
                    INNER JOIN deliveries d ON dr.delivery_id = d.delivery_id 
                    SET dr.`remove` = 1 
                    WHERE d.poi_id = :poi_id AND dr.`remove` = 0")
                    ->execute(['poi_id' => $poiId]);
                // deliveries: has remove column
                $conn->prepare("UPDATE deliveries SET `remove` = 1 WHERE poi_id = :poi_id AND `remove` = 0")
                    ->execute(['poi_id' => $poiId]);
                // purchase_order_items: no soft-delete column, hard delete (parent record)
                $conn->prepare("DELETE FROM purchase_order_items WHERE poi_id = :poi_id")
                    ->execute(['poi_id' => $poiId]);
            }

            // Recalculate PO-level quantities after deletes
            $conn->prepare("UPDATE purchase_orders SET produced_quantity = (
                SELECT COALESCE(SUM(produced_quantity), 0) FROM purchase_order_items WHERE po_id = :po_id
            ) WHERE po_id = :po_id2")
                ->execute(['po_id' => $po_id, 'po_id2' => $po_id]);
            $conn->prepare("UPDATE purchase_orders SET delivered_quantity = GREATEST(0,
                (SELECT COALESCE(SUM(d.delivery_quantity), 0) FROM deliveries d WHERE d.po_id = :po_id AND d.`remove` = 0)
                - COALESCE((SELECT SUM(b.quantity) FROM backloads b WHERE b.poi_id IN (SELECT poi_id FROM purchase_order_items WHERE po_id = :po_id3) AND b.`remove` = 0), 0)
            ) WHERE po_id = :po_id2")
                ->execute(['po_id' => $po_id, 'po_id2' => $po_id, 'po_id3' => $po_id]);

            $po = $this->warehouseModel->getPurchaseOrderById($po_id);
            $oldValues = [
                'customer_po_number' => $oldPoData['customer_po_number'] ?? '',
                'customer_po_date' => $oldPoData['customer_po_date'] ?? '',
                'production_type' => $oldPoData['production_type'] ?? 'normal',
            ];
            if (!empty($oldItems)) {
                $oldValues['items'] = array_map(function($item) {
                    return [
                        'poi_id' => $item['poi_id'],
                        'item_id' => $item['item_id'],
                        'quantity' => $item['quantity'],
                    ];
                }, array_values($oldItems));
            }
            $newValues = [
                'customer_po_number' => $_POST['customer_po_number'] ?? '',
                'customer_po_date' => $_POST['customer_po_date'] ?? '',
                'production_type' => $_POST['production_type'] ?? 'normal',
            ];
            // Build new items state from POST data (the actual submitted items)
            if (!empty($items)) {
                $newValues['items'] = array_map(function($item) {
                    return [
                        'poi_id' => $item['poi_id'] ?? null,
                        'item_id' => $item['item_id'] ?? null,
                        'quantity' => $item['quantity'] ?? 0,
                    ];
                }, array_values($items));
            }
            $poLabel = $po['customer_po_number'] ?? $po['po_number'] ?? 'PO #' . $po_id;
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'warehouse', 'Updated purchase order ' . $poLabel . ' with the latest header and item changes', $oldValues, $newValues, 'purchase_order', $po_id);

            $conn->commit();
            $_SESSION['success'] = 'Purchase Order updated successfully';
            header('Location: ?controller=warehouse&action=purchaseOrders');
            exit;

            } catch (\Exception $e) {
                $conn->rollBack();
                $_SESSION['error'] = 'Failed to update PO: ' . $e->getMessage();
                header('Location: ?controller=warehouse&action=purchaseOrders');
                exit;
            }
        }
    }

    public function viewPO() {
        $id = $_GET['id'] ?? null;
        $data['page_title'] = 'PO Details';
        $data['po'] = $this->warehouseModel->getPurchaseOrderById($id);
        $data['po_items'] = $this->warehouseModel->getPurchaseOrderItems($id);

        $this->render('purchase_orders/view', $data);
    }

    public function getItemsByCustomer() {
        header('Content-Type: application/json');
        $customer_id = $_GET['customer_id'] ?? null;
        if (!$customer_id) {
            echo json_encode([]);
            exit;
        }
        $items = $this->catalogModel->getItemsByCustomer($customer_id);
        echo json_encode($items);
        exit;
    }

    public function getPODetails() {
        header('Content-Type: application/json');
        try {
            $id = $_GET['id'] ?? null;
            $po = $this->warehouseModel->getPurchaseOrderById($id);
            $po_items = $this->warehouseModel->getPurchaseOrderItems($id);
        
        // Fetch all active deliveries for this PO and map them to their corresponding items
        $deliveries = $this->warehouseModel->getDeliveriesByPOId($id);
        $dr_map = [];
        $delivery_ids = [];
        foreach ($deliveries as $d) {
            $dr = $d['dr_number'] ?? '';
            $delivery_id = $d['delivery_id'] ?? null;
            if (empty($dr)) continue;
            
            if (!empty($d['poi_id'])) {
                $poi_id = $d['poi_id'];
                $qty = $d['delivery_quantity'] ?? 0;
                if (!isset($dr_map[$poi_id])) {
                    $dr_map[$poi_id] = [];
                }
                $dr_map[$poi_id][] = [
                    'dr_number' => $dr,
                    'qty' => $qty,
                    'delivery_date' => $d['delivery_date'],
                    'lot_number' => $d['lot_number'] ?? null,
                    'delivery_id' => $delivery_id
                ];
                if ($delivery_id) $delivery_ids[] = $delivery_id;
            }
            
            if (!empty($d['lot_items'])) {
                $lotItems = json_decode($d['lot_items'], true);
                if (is_array($lotItems)) {
                    foreach ($lotItems as $li) {
                        $poi_id = $li['poi_id'] ?? null;
                        $qty = $li['qty'] ?? 0;
                        if ($poi_id) {
                            if (!isset($dr_map[$poi_id])) {
                                $dr_map[$poi_id] = [];
                            }
                            $dr_map[$poi_id][] = [
                                'dr_number' => $dr,
                                'qty' => $qty,
                                'delivery_date' => $d['delivery_date'],
                                'lot_number' => $li['lot_number'] ?? null,
                                'delivery_id' => $delivery_id
                            ];
                            if ($delivery_id) $delivery_ids[] = $delivery_id;
                        }
                    }
                }
            }
        }
        
        // Fetch receipts for this PO and map by delivery_id
        $receipts = [];
        if (!empty($id)) {
            $allReceipts = $this->warehouseModel->getReceiptsByPOId($id);
            foreach ($allReceipts as $r) {
                $rid = $r['delivery_id'];
                if (!isset($receipts[$rid])) {
                    $receipts[$rid] = $r;
                }
            }
        }
        
        foreach ($po_items as &$item) {
            $poi_id = $item['poi_id'];
            $item['deliveries'] = $dr_map[$poi_id] ?? [];
            foreach ($item['deliveries'] as &$del) {
                $did = $del['delivery_id'] ?? null;
                $del['receipt'] = ($did && isset($receipts[$did])) ? $receipts[$did] : null;
            }
            unset($del);
            $item['backloaded'] = 0;
        }
        unset($item);

        if (!empty($po_items)) {
            $poiIds = array_column($po_items, 'poi_id');
            $placeholders = implode(',', array_fill(0, count($poiIds), '?'));
            $conn = \App\Core\BaseModel::getConnection();

            $lotConvMap = [];
            $lotPoiMap = [];
            foreach ($deliveries as $d) {
                $lotItems = json_decode($d['lot_items'] ?? '[]', true);
                if (!is_array($lotItems)) continue;
                foreach ($lotItems as $li) {
                    $lid = intval($li['lot_id'] ?? 0);
                    if ($lid && !isset($lotConvMap[$lid])) {
                        $lotConvMap[$lid] = [
                            'conv' => intval($li['actual_uom_conversion'] ?? $li['uom_conversion'] ?? 0),
                            'uom' => $li['item_uom'] ?? ''
                        ];
                        $lotPoiMap[$lid] = intval($li['poi_id'] ?? 0);
                    }
                }
            }

            $lotProdStmt = $conn->prepare("SELECT lot_id, poi_id, quantity_produced FROM production_lots WHERE poi_id IN ($placeholders) AND `is_removed` = 0");
            $lotProdStmt->execute($poiIds);
            $lotProduced = [];
            while ($lr = $lotProdStmt->fetch()) {
                $lid = intval($lr['lot_id']);
                $lotProduced[$lid] = intval($lr['quantity_produced']);
                if (!isset($lotPoiMap[$lid])) $lotPoiMap[$lid] = intval($lr['poi_id']);
            }

            $lotDelivered = [];
            $lotReturned = [];
            foreach ($deliveries as $d) {
                $lotItems = json_decode($d['lot_items'] ?? '[]', true);
                if (!is_array($lotItems)) continue;
                foreach ($lotItems as $li) {
                    $lid = intval($li['lot_id'] ?? 0);
                    if ($lid) {
                        $lotDelivered[$lid] = ($lotDelivered[$lid] ?? 0) + intval($li['qty'] ?? 0);
                        $lotReturned[$lid] = ($lotReturned[$lid] ?? 0) + intval($li['returned_qty'] ?? 0);
                    }
                }
            }

            $blStmt = $conn->prepare("SELECT lot_id, poi_id, quantity FROM backloads WHERE poi_id IN ($placeholders) AND `remove` = 0");
            $blStmt->execute($poiIds);

            $lotBackloaded = [];
            while ($bl = $blStmt->fetch()) {
                $lid = intval($bl['lot_id']);
                $lotBackloaded[$lid] = ($lotBackloaded[$lid] ?? 0) + intval($bl['quantity']);
            }

            $blCsMap = [];
            $balanceMap = [];
            foreach ($poiIds as $pid) {
                $totalBackloaded = 0;
                $totalActive = 0;
                foreach ($lotBackloaded as $lid => $blQty) {
                    if (($lotPoiMap[$lid] ?? null) != $pid) continue;
                    $totalBackloaded += $blQty;
                    $produced = $lotProduced[$lid] ?? 0;
                    $delivered = $lotDelivered[$lid] ?? 0;
                    $consumed = max(0, $delivered - $produced);
                    $active = max(0, $blQty - $consumed);
                    $totalActive += $active;
                }
                $convInfo = null;
                foreach ($lotBackloaded as $lid => $blQty) {
                    if (($lotPoiMap[$lid] ?? null) == $pid) {
                        $convInfo = $lotConvMap[$lid] ?? null;
                        break;
                    }
                }
                if ($convInfo && $convInfo['conv'] > 0 && $convInfo['uom'] !== 'CS') {
                    $blCsMap[$pid] = ($blCsMap[$pid] ?? 0) + floor($totalBackloaded / $convInfo['conv']);
                    $balanceMap[$pid] = ($balanceMap[$pid] ?? 0) + floor($totalActive / $convInfo['conv']);
                } else {
                    $blCsMap[$pid] = ($blCsMap[$pid] ?? 0) + $totalBackloaded;
                    $balanceMap[$pid] = ($balanceMap[$pid] ?? 0) + $totalActive;
                }
            }
            foreach ($po_items as &$item) {
                $item['backloaded'] = $blCsMap[$item['poi_id']] ?? 0;
                $item['backload_balance'] = $balanceMap[$item['poi_id']] ?? 0;
            }
            unset($item);
        }
        
        echo json_encode(['po' => $po, 'po_items' => $po_items]);
        exit;
        } catch (\Exception $e) {
            error_log('getPODetails error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load PO details']);
            exit;
        }
    }

    public function getLotsByPOItem() {
        header('Content-Type: application/json');
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
        exit;
    }

    public function deliveries() {
        $search = $_GET['search'] ?? '';
        $filterCustomer = $_GET['filter_customer'] ?? '';
        $filterItem = $_GET['filter_item'] ?? '';

        $hasFilter = $search || $filterCustomer || $filterItem;
        if ($hasFilter) {
            $filters = [];
            if ($search) $filters['search'] = $search;
            if ($filterCustomer) $filters['customer_name'] = $filterCustomer;
            if ($filterItem) $filters['item_description'] = $filterItem;
            $allDeliveries = $this->warehouseModel->getDeliveriesFiltered($filters);
            $allCustomers = array_values(array_unique(array_filter(array_column($allDeliveries, 'customer_name'))));
            $pagination = ['items' => $allDeliveries, 'page' => 1, 'perPage' => count($allDeliveries), 'total' => count($allDeliveries), 'totalPages' => 1, 'hasNext' => false, 'hasPrev' => false];
        } else {
            $allDeliveries = $this->warehouseModel->getDeliveries();
            $allCustomers = array_values(array_unique(array_filter(array_column($allDeliveries, 'customer_name'))));
            $pagination = Pagination::paginate($allDeliveries, 10);
        }

        $data['deliveries'] = $pagination['items'];
        $deliveryIds = array_column($pagination['items'], 'delivery_id');
        $receiptsMap = [];
        $backloadsMap = [];
        if (!empty($deliveryIds)) {
            $conn = $this->warehouseModel::getConnection();
            $placeholders = implode(',', array_fill(0, count($deliveryIds), '?'));
            $stmt = $conn->prepare("SELECT * FROM delivery_receipts WHERE delivery_id IN ($placeholders) AND `remove` = 0 ORDER BY date_created ASC");
            $stmt->execute($deliveryIds);
            foreach ($stmt->fetchAll() as $r) {
                $receiptsMap[$r['delivery_id']][] = $r;
            }

            $blStmt = $conn->prepare("SELECT * FROM backloads WHERE delivery_id IN ($placeholders) AND `remove` = 0 ORDER BY date_created ASC");
            $blStmt->execute($deliveryIds);
            foreach ($blStmt->fetchAll() as $bl) {
                $backloadsMap[$bl['delivery_id']][] = $bl;
            }
        }
        $data['receipts_map'] = $receiptsMap;
        $data['backloads_map'] = $backloadsMap;

        $data['page'] = $pagination['page'];
        $data['totalPages'] = $pagination['totalPages'];
        $data['total'] = $pagination['total'];
        $data['search'] = $search;
        $data['filterCustomer'] = $filterCustomer;
        $data['filterItem'] = $filterItem;
        $data['allCustomers'] = $allCustomers;
        $data['purchase_orders'] = array_values(array_filter($this->warehouseModel->getPurchaseOrders(), function($po) {
            return ($po['production_type'] ?? 'normal') === 'normal';
        }));
        $data['page_title'] = 'Deliveries';
        $this->render('deliveries/index', $data);
    }

    public function viewBackloads() {
        $search = $_GET['search'] ?? '';
        $filterCustomer = $_GET['filter_customer'] ?? '';

        $filters = [];
        if ($search) $filters['search'] = $search;
        if ($filterCustomer) $filters['customer_id'] = $filterCustomer;

        $allBackloads = $this->backloadModel->getBackloads($filters);
        $pagination = Pagination::paginate($allBackloads, 15);

        $customers = $this->catalogModel->getCustomers();

        $data['backloads'] = $pagination['items'];
        $data['page'] = $pagination['page'];
        $data['totalPages'] = $pagination['totalPages'];
        $data['total'] = $pagination['total'];
        $data['search'] = $search;
        $data['filterCustomer'] = $filterCustomer;
        $data['customers'] = $customers;
        $data['page_title'] = 'Backloads';
        $this->render('deliveries/backloads', $data);
    }

    public function readyToDeliver() {
        $search = $_GET['search'] ?? '';
        if ($search) {
            $allPOs = $this->warehouseModel->getPOsReadyToDeliverFiltered(['search' => $search]);
        } else {
            $allPOs = $this->warehouseModel->getPOsReadyToDeliver();
        }
        $pagination = Pagination::paginate($allPOs, 10);
        $data['purchase_orders'] = $pagination['items'];
        $poIds = array_column($pagination['items'], 'po_id');
        $data['po_items_map'] = $this->warehouseModel->getPurchaseOrderItemsByPOIds($poIds);

        $data['page'] = $pagination['page'];
        $data['totalPages'] = $pagination['totalPages'];
        $data['total'] = $pagination['total'];
        $data['search'] = $search;
        $data['page_title'] = 'Ready to Deliver';
        $this->render('purchase_orders/ready_to_deliver', $data);
    }

    public function deleteDRPhoto() {
        header('Content-Type: application/json');
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $receiptId = $_POST['receipt_id'] ?? null;
            if (!$receiptId) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing receipt_id']);
                exit;
            }
            $receipt = $this->warehouseModel->getReceiptById($receiptId);
            $this->warehouseModel->deleteDRPhoto($receiptId);
            AuditModel::log($_SESSION['user_id'], 'DELETE', 'warehouse', 'Deleted DR photo for receipt #' . $receiptId . ' from the delivery record', ['path' => $receipt['file_path'] ?? ''], null, 'delivery_photo', $receiptId);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            error_log('deleteDRPhoto error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete photo']);
        }
        exit;
    }

    public function getDeliveryLotsForBackload() {
        header('Content-Type: application/json');
        $delivery_id = $_GET['delivery_id'] ?? null;
        if (!$delivery_id) { echo json_encode([]); exit; }

        $lots = $this->backloadModel->getDeliveryLotsForBackload($delivery_id);
        echo json_encode($lots);
        exit;
    }

    public function backloadDelivery() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=warehouse&action=deliveries');
            exit;
        }
        try {
            $delivery_id = $_POST['delivery_id'] ?? null;
            if (!$delivery_id) {
                $_SESSION['error'] = 'Invalid delivery';
                header('Location: ?controller=warehouse&action=deliveries');
                exit;
            }

            $conn = \App\Core\BaseModel::getConnection();
            $stmt = $conn->prepare("SELECT d.delivery_id, d.po_id, d.dr_number, po.customer_po_number
                FROM deliveries d
                INNER JOIN purchase_orders po ON d.po_id = po.po_id
                WHERE d.delivery_id = :delivery_id AND d.`remove` = 0");
            $stmt->execute(['delivery_id' => $delivery_id]);
            $delivery = $stmt->fetch();
            if (!$delivery) {
                $_SESSION['error'] = 'Delivery not found';
                header('Location: ?controller=warehouse&action=deliveries');
                exit;
            }

            $lotIds = $_POST['lot_id'] ?? [];
            $quantities = $_POST['backload_qty'] ?? [];
            $casesArr = $_POST['backload_cases'] ?? [];
            $reasons = $_POST['backload_reason'] ?? [];

            $totalBackloaded = 0;
            foreach ($lotIds as $idx => $lotId) {
                $qty = intval($quantities[$idx] ?? 0);
                if ($qty <= 0 || empty($reasons[$idx])) continue;

                $lotId = intval($lotId);
                $lotStmt = $conn->prepare("SELECT poi_id, lot_number FROM production_lots WHERE lot_id = :lot_id");
                $lotStmt->execute(['lot_id' => $lotId]);
                $lot = $lotStmt->fetch();
                if (!$lot) continue;

                $poiId = $lot['poi_id'];

                $this->backloadModel->createBackload([
                    'delivery_id' => $delivery_id,
                    'po_id' => $delivery['po_id'],
                    'poi_id' => $poiId,
                    'lot_id' => $lotId,
                    'lot_number' => $lot['lot_number'],
                    'quantity' => $qty,
                    'cases' => intval($casesArr[$idx] ?? 0) ?: null,
                    'reason' => $reasons[$idx],
                    'backloaded_by' => $_SESSION['user_id'],
                    'backload_date' => date('Y-m-d')
                ]);
                $totalBackloaded += $qty;
            }

            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'warehouse', 'Backloaded ' . $totalBackloaded . ' units from delivery #' . $delivery_id, null, ['quantity' => $totalBackloaded], 'delivery', $delivery_id);

            NotificationHelper::backloadCreated($delivery['dr_number'], $delivery['customer_po_number'], $totalBackloaded, $_SESSION['user_id']);

            $_SESSION['success'] = 'Backload recorded successfully (' . $totalBackloaded . ' units)';
            header('Location: ?controller=warehouse&action=deliveries');
            exit;
        } catch (\Exception $e) {
            error_log('backloadDelivery error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to create backload: ' . $e->getMessage();
            header('Location: ?controller=warehouse&action=deliveries');
            exit;
        }
    }

    public function createMultipleDelivery() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=warehouse&action=deliveries');
            exit;
        }
        try {
            $po_id = !empty($_POST['po_id']) ? intval($_POST['po_id']) : null;
            $dr_number = trim($_POST['dr_number'] ?? '');
            $plate_number = trim($_POST['plate_number'] ?? '');
            $vehicle_type = trim($_POST['vehicle_type'] ?? '');
            $logistic_provider = trim($_POST['logistic_provider'] ?? '');
            $lotIdsRaw = $_POST['lot_ids'] ?? '';
            $delivery_date = $_POST['delivery_date'] ?? date('Y-m-d');
            $remarks = $_POST['remarks'] ?? '';
            $isOverShipment = 0;
            if (empty($dr_number) || empty($lotIdsRaw) || empty($plate_number) || empty($vehicle_type) || empty($logistic_provider)) {
                $_SESSION['error'] = 'Missing required fields for delivery.';
                header('Location: ?controller=warehouse&action=deliveries');
                exit;
            }
            $drCheck = $this->warehouseModel->checkDRNumber($dr_number);
            if ($drCheck['exists']) {
                $_SESSION['error'] = 'DR number "' . htmlspecialchars($dr_number) . '" already exists. Please use a unique DR number.';
                header('Location: ?controller=warehouse&action=deliveries');
                exit;
            }
            $pairs = explode(',', $lotIdsRaw);
            $lotItems = [];
            $totalQty = 0;
            $firstPoiId = null;
            $assignedPoiIds = [];
            foreach ($pairs as $pair) {
                $parts = explode(':', $pair);
                if (count($parts) < 2) continue;
                $lotId = intval($parts[0]);
                $deliveryQty = intval($parts[1]);
                $returnedQty = (count($parts) >= 3) ? intval($parts[2]) : 0;
                $actualConversion = (count($parts) >= 4 && !empty($parts[3]) && is_numeric($parts[3])) ? intval($parts[3]) : null;
                if ($lotId <= 0 || $deliveryQty <= 0) continue;
                if ($returnedQty < 0) $returnedQty = 0;
                if ($returnedQty > $deliveryQty) $returnedQty = $deliveryQty;
                $lot = $this->warehouseModel->getLotById($lotId);
                if (!$lot) continue;
                $poiId = $lot['poi_id'] ?? null;
                if ($po_id && !empty($lot['item_id'])) {
                    $conn = \App\Core\BaseModel::getConnection();
                    $poiSt = $conn->prepare("SELECT poi_id FROM purchase_order_items WHERE po_id = ? AND item_id = ? LIMIT 1");
                    $poiSt->execute([$po_id, $lot['item_id']]);
                    $resolvedPoiId = $poiSt->fetchColumn();
                    if (!$resolvedPoiId) {
                        $codeSt = $conn->prepare("SELECT item_code FROM items WHERE item_id = ?");
                        $codeSt->execute([$lot['item_id']]);
                        $itemCode = $codeSt->fetchColumn();
                        if ($itemCode) {
                            $poiSt2 = $conn->prepare("SELECT poi.poi_id FROM purchase_order_items poi JOIN items i ON poi.item_id = i.item_id WHERE poi.po_id = ? AND i.item_code = ? LIMIT 1");
                            $poiSt2->execute([$po_id, $itemCode]);
                            $resolvedPoiId = $poiSt2->fetchColumn();
                        }
                    }
                    if ($resolvedPoiId) {
                        $poiId = intval($resolvedPoiId);
                    }
                }
                if (!$firstPoiId) $firstPoiId = $poiId;
                if ($poiId) $assignedPoiIds[] = $poiId;

                if ($poiId) {
                    $siblings = $this->warehouseModel->getLotsByLotNumber($lot['lot_number'], $poiId);
                } else {
                    $siblings = [$lot];
                }

                if (count($siblings) > 1) {
                    $totalRemaining = 0;
                    $remMap = [];
                    foreach ($siblings as $sib) {
                        $rem = $this->warehouseModel->getLotRemaining($sib['lot_id']);
                        $remMap[$sib['lot_id']] = $rem;
                        $totalRemaining += $rem;
                    }
                    if ($deliveryQty > $totalRemaining) $deliveryQty = $totalRemaining;
                    if ($returnedQty > $deliveryQty) $returnedQty = $deliveryQty;
                    $toSplit = $deliveryQty;
                    $toSplitRet = $returnedQty;
                    $siblingCount = count($siblings);
                    foreach ($siblings as $idx => $sib) {
                        $sibId = $sib['lot_id'];
                        if ($idx === $siblingCount - 1) {
                            $sibQty = $toSplit;
                            $sibRet = $toSplitRet;
                        } else {
                            $sibQty = ($totalRemaining > 0) ? round($deliveryQty * $remMap[$sibId] / $totalRemaining) : 0;
                            $sibRet = ($deliveryQty > 0) ? round($returnedQty * $sibQty / $deliveryQty) : 0;
                            $toSplit -= $sibQty;
                            $toSplitRet -= $sibRet;
                        }
                        if ($sibQty > 0) {
                            $item = $poiId ? $this->catalogModel->getItemByPoiId($poiId) : $this->catalogModel->getItemById($lot['item_id'] ?? null);
                            $lotItems[] = [
                                'lot_id' => $sibId,
                                'poi_id' => $poiId,
                                'lot_number' => $sib['lot_number'] ?? '',
                                'item_code' => $item['item_code'] ?? '',
                                'item_description' => $item['item_description'] ?? '',
                                'qty' => $sibQty,
                                'returned_qty' => $sibRet,
                                'item_uom' => $item['item_uom'] ?? '',
                                'uom_conversion' => $item['uom_conversion'] ?? null,
                                'actual_uom_conversion' => $actualConversion,
                            ];
                            $totalQty += $sibQty;
                        }
                    }
                } else {
                    $remaining = $this->warehouseModel->getLotRemaining($lotId);
                    if ($deliveryQty > $remaining) $deliveryQty = $remaining;
                    if ($returnedQty > $deliveryQty) $returnedQty = $deliveryQty;
                    if ($deliveryQty <= 0) continue;
                    $item = $poiId ? $this->catalogModel->getItemByPoiId($poiId) : $this->catalogModel->getItemById($lot['item_id'] ?? null);
                    $lotItems[] = [
                        'lot_id' => $lotId,
                        'poi_id' => $poiId,
                        'lot_number' => $lot['lot_number'] ?? '',
                        'item_code' => $item['item_code'] ?? '',
                        'item_description' => $item['item_description'] ?? '',
                        'qty' => $deliveryQty,
                        'returned_qty' => $returnedQty,
                        'item_uom' => $item['item_uom'] ?? '',
                        'uom_conversion' => $item['uom_conversion'] ?? null,
                        'actual_uom_conversion' => $actualConversion,
                    ];
                    $totalQty += $deliveryQty;
                }
            }

            if ($po_id) {
                $perPoiDelivery = [];
                foreach ($lotItems as $li) {
                    $poiId = $li['poi_id'] ?? null;
                    if (!$poiId) continue;
                    $perPoiDelivery[$poiId] = ($perPoiDelivery[$poiId] ?? 0) + intval($li['qty']);
                }
                $conn = \App\Core\BaseModel::getConnection();
                foreach ($perPoiDelivery as $poiId => $requestedQty) {
                    $poiStmt = $conn->prepare("SELECT poi.quantity, poi.delivered_quantity
                            FROM purchase_order_items poi WHERE poi.poi_id = ?");
                    $poiStmt->execute([$poiId]);
                    $poiData = $poiStmt->fetch();
                    if ($poiData) {
                        $remaining = intval($poiData['quantity']) - intval($poiData['delivered_quantity']);
                        if ($requestedQty > $remaining) {
                            $_SESSION['error'] = "Delivery blocked: Requested quantity ({$requestedQty}) exceeds open PO balance ({$remaining}) for PO item #{$poiId}.";
                            header('Location: ?controller=warehouse&action=deliveries');
                            exit;
                        }
                    }
                }
            }

            if (empty($lotItems)) {
                $_SESSION['error'] = 'No valid lots selected for delivery.';
                header('Location: ?controller=warehouse&action=deliveries');
                exit;
            }
            $groupedLotItems = [];
            foreach ($lotItems as $li) {
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
            $deliveryId = $this->warehouseModel->createDelivery([
                'po_id' => $po_id,
                'poi_id' => $firstPoiId,
                'delivered_by' => $_SESSION['user_id'],
                'delivery_date' => $delivery_date,
                'delivery_quantity' => $totalQty,
                'dr_number' => $dr_number,
                'plate_number' => $plate_number,
                'vehicle_type' => $vehicle_type,
                'logistic_provider' => $logistic_provider,
                'lot_items' => json_encode($lotItems),
                'remarks' => $remarks,
                'is_over_shipment' => $isOverShipment
            ]);

            $uniquePoiIds = array_unique(array_filter($assignedPoiIds));
            $oldPoiIdsToRecalc = [];

            foreach ($lotItems as $li) {
                $lotId = $li['lot_id'] ?? null;
                $poiId = $li['poi_id'] ?? null;
                if (!$lotId || !$poiId) continue;
                $lot = $this->warehouseModel->getLotById($lotId);
                if ($lot) {
                    $oldLotPoiId = $lot['poi_id'] ? intval($lot['poi_id']) : null;
                    $newPoiId = intval($poiId);
                    if ($oldLotPoiId && $oldLotPoiId !== $newPoiId) {
                        $oldPoiIdsToRecalc[] = $oldLotPoiId;
                    }
                }
            }

            $allPoiIdsToRecalc = array_unique(array_merge($uniquePoiIds, $oldPoiIdsToRecalc));
            foreach ($allPoiIdsToRecalc as $recalcPoiId) {
                $this->warehouseModel->recalculateProducedQuantityFromDelivery($recalcPoiId);
            }

            $deliveryLabel = 'Delivery';
            if ($po_id) {
                $poDel = $this->warehouseModel->getPurchaseOrderById($po_id);
                $deliveryLabel = $poDel['customer_po_number'] ?? $poDel['po_number'] ?? 'PO #' . $po_id;
            }
            AuditModel::log($_SESSION['user_id'], 'CREATE', 'warehouse', 'Created delivery records for ' . $deliveryLabel . ($dr_number ? ' with DR ' . $dr_number : ''), null, ['lot_ids' => $_POST['lot_ids'] ?? []], 'delivery', null);

            NotificationHelper::deliveryCreated($deliveryLabel, $dr_number, $totalQty, $_SESSION['user_id']);
            NotificationHelper::siNumberNeeded($deliveryId, $deliveryLabel, $_SESSION['user_id']);

            $_SESSION['success'] = "Delivery recorded successfully for DR {$dr_number}.";
            header('Location: ?controller=warehouse&action=deliveries');
            exit;
        } catch (\Exception $e) {
            error_log('createMultipleDelivery error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to create delivery';
            header('Location: ?controller=warehouse&action=deliveries');
            exit;
        }
    }

    public function updateDRNumber() {
        header('Content-Type: application/json');
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $delivery_id = $_POST['delivery_id'] ?? null;
            $dr_number = trim($_POST['dr_number'] ?? '');
            if (!$delivery_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Delivery ID is required']);
                exit;
            }
            $this->warehouseModel->updateDRNumber($delivery_id, $dr_number);
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'warehouse', 'Updated DR number to ' . $dr_number . ' for delivery #' . $delivery_id, null, ['dr_number' => $dr_number], 'delivery', $delivery_id);
            echo json_encode(['success' => true, 'dr_number' => $dr_number]);
        } catch (\Exception $e) {
            error_log('updateDRNumber error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update DR number']);
        }
        exit;
    }

    public function getAvailableItemsForDelivery() {
        header('Content-Type: application/json');
        try {
            $po_id = !empty($_GET['po_id']) ? intval($_GET['po_id']) : null;
            $items = $this->warehouseModel->getAllAvailableItemsForDelivery($po_id);
            echo json_encode($items);
        } catch (\Exception $e) {
            error_log('getAvailableItemsForDelivery error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load items']);
        }
        exit;
    }

    public function getPOsContainingItem() {
        header('Content-Type: application/json');
        try {
            $item_id = $_GET['item_id'] ?? null;
            if (!$item_id) {
                echo json_encode([]);
                exit;
            }
            $pos = $this->warehouseModel->getPOsContainingItem($item_id);
            echo json_encode($pos);
        } catch (\Exception $e) {
            error_log('getPOsContainingItem error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load POs']);
        }
        exit;
    }

    public function getLotsForPrint() {
        header('Content-Type: application/json');
        try {
            $po_id = $_GET['po_id'] ?? null;
            if (!$po_id) {
                echo json_encode([]);
                exit;
            }
            $lots = $this->warehouseModel->getLotsByPOForPrint($po_id);
            echo json_encode($lots);
        } catch (\Exception $e) {
            error_log('getLotsForPrint error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load lots']);
        }
        exit;
    }

    public function checkDRNumber() {
        header('Content-Type: application/json');
        try {
            $dr_number = $_GET['dr_number'] ?? '';
            if (empty($dr_number)) {
                echo json_encode(['exists' => false]);
                exit;
            }
            $result = $this->warehouseModel->checkDRNumber($dr_number);
            echo json_encode($result);
        } catch (\Exception $e) {
            error_log('checkDRNumber error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to check DR number']);
        }
        exit;
    }

    public function reportDelivery() {
        header('Content-Type: application/json');
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $deliveryId = $_POST['delivery_id'] ?? null;
            $remarks = trim($_POST['remarks'] ?? '');
            $reportType = $_POST['report_type'] ?? 'dr_number';
            $lotId = $_POST['lot_id'] ?? null ? intval($_POST['lot_id']) : null;
            $poiId = $_POST['poi_id'] ?? null ? intval($_POST['poi_id']) : null;
            $poId = $_POST['po_id'] ?? null ? intval($_POST['po_id']) : null;
            $oldQuantity = $_POST['old_quantity'] ?? null ? intval($_POST['old_quantity']) : null;

            if (!$deliveryId || empty($remarks)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing delivery_id or remarks']);
                exit;
            }

            $this->warehouseModel->reportDelivery($deliveryId, $remarks);

            if ($poId) {
                $this->warehouseModel->createDeliveryReport(
                    $deliveryId, $poiId, $poId, $lotId, $oldQuantity,
                    $_SESSION['user_id'], $remarks, $reportType
                );
            }

            AuditModel::log($_SESSION['user_id'], 'CREATE', 'warehouse', 'Reported delivery issue for delivery #' . $deliveryId . ' (' . $reportType . ')', null, $_POST, 'delivery_report', $deliveryId);

            $deliveryRecord = $this->warehouseModel->getDeliveryById($deliveryId);
            $drLabel = $deliveryRecord['dr_number'] ?? ('#' . $deliveryId);
            NotificationHelper::deliveryReported($deliveryId, $drLabel, $remarks, $_SESSION['user_id']);

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            error_log('reportDelivery error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to submit report']);
        }
        exit;
    }

    public function uploadDRPhoto() {
        header('Content-Type: application/json');
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }

            $deliveryId = $_POST['delivery_id'] ?? null;
            $poId = $_POST['po_id'] ?? null;

            if (!$deliveryId || !$poId) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing delivery_id or po_id']);
                exit;
            }

            if (!isset($_FILES['dr_photo']) || $_FILES['dr_photo']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'Please select a file to upload']);
                exit;
            }

            $file = $_FILES['dr_photo'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

            if (!in_array($file['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP, PDF']);
                exit;
            }

            $maxSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode(['error' => 'File size must be less than 10MB']);
                exit;
            }

            $uploadDir = __DIR__ . '/../../uploads/receipts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'dr_photo_' . $deliveryId . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to upload file']);
                exit;
            }

            $this->warehouseModel->attachDRPhoto([
                'delivery_id' => $deliveryId,
                'po_id' => $poId,
                'file_name' => $file['name'],
                'file_path' => 'uploads/receipts/' . $fileName,
                'file_type' => $file['type'],
                'file_size' => $file['size'],
                'uploaded_by' => $_SESSION['user_id']
            ]);

            AuditModel::log($_SESSION['user_id'], 'CREATE', 'warehouse', 'Uploaded DR file for delivery #' . $deliveryId . ' as ' . $fileName, null, ['file' => $fileName], 'delivery_photo', $deliveryId);

            echo json_encode(['success' => true, 'file_path' => 'uploads/receipts/' . $fileName]);
        } catch (\Exception $e) {
            error_log('uploadDRPhoto error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to upload DR photo']);
        }
        exit;
    }

    public function printDR() {
        $data['purchase_orders'] = $this->warehouseModel->getPurchaseOrders();
        $data['page_title'] = 'Print Delivery Receipt';
        $selectedPoId = $_GET['po_id'] ?? null;
        $dr_number = $_GET['dr_number'] ?? '';
        $data['dr_number'] = $dr_number;
        $data['selected_po_id'] = $selectedPoId;
        $data['existing_lot_ids'] = [];
        $data['lots_by_item'] = [];

        if ($selectedPoId) {
            $data['lots_by_item'] = $this->warehouseModel->getLotsByPOForPrint($selectedPoId);
        }

        if (!empty($dr_number) && $selectedPoId) {
            $data['existing_lot_ids'] = $this->warehouseModel->getLotsByDRNumber($dr_number);
        }

        $this->render('deliveries/print_dr', $data);
    }

    public function printDRPreview() {
        $po_id = $_GET['po_id'] ?? null;
        $dr_number = $_GET['dr_number'] ?? '';
        if (!$dr_number) {
            header('Location: ?controller=warehouse&action=printDR');
            exit;
        }
        $dr_deliveries = $this->warehouseModel->getDeliveriesByDRNumber($dr_number);
        if (empty($dr_deliveries)) {
            echo "<div class='container mt-5'><div class='alert alert-danger'>Error: DR number \"" . htmlspecialchars($dr_number) . "\" not found.</div><a href='?controller=warehouse&action=deliveries' class='btn btn-secondary'>Back</a></div>";
            exit;
        }
        if (!$po_id && !empty($dr_deliveries[0]['po_id'])) {
            $po_id = $dr_deliveries[0]['po_id'];
        }
        $data['po'] = $po_id ? $this->warehouseModel->getPurchaseOrderById($po_id) : null;
        $data['dr_deliveries'] = $dr_deliveries;
        $data['dr_number'] = $dr_number;
        extract($data);
        include __DIR__ . "/../views/deliveries/print_dr_preview.php";
        exit;
    }

    public function saveDRNumberForLots() {
        header('Content-Type: application/json');
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $lotIds = $_POST['lot_ids'] ?? '';
            $dr_number = trim($_POST['dr_number'] ?? '');
            $po_id = $_POST['po_id'] ?? null;
            if (empty($lotIds) || empty($dr_number) || empty($po_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing parameters']);
                exit;
            }
            $lotIdArray = array_map('intval', explode(',', $lotIds));
            $lotIdArray = array_filter($lotIdArray);
            $lotItems = [];
            $totalQty = 0;
            $firstPoiId = null;
            foreach ($lotIdArray as $lotId) {
                $lot = $this->warehouseModel->getLotById($lotId);
                if (!$lot) continue;
                $remaining = $this->warehouseModel->getLotRemaining($lotId);
                if ($remaining <= 0) continue;
                $poiId = $lot['poi_id'] ?? null;
                $item = $this->catalogModel->getItemByPoiId($poiId);
                $lotItems[] = [
                    'lot_id' => $lotId,
                    'poi_id' => $poiId,
                    'lot_number' => $lot['lot_number'] ?? '',
                    'item_code' => $item['item_code'] ?? '',
                    'item_description' => $item['item_description'] ?? '',
                    'qty' => $remaining,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'item_uom' => $item['item_uom'] ?? '',
                    'uom_conversion' => $item['uom_conversion'] ?? null,
                    'actual_uom_conversion' => $lot['pcs_per_case'] ?? $item['uom_conversion'] ?? null,
                    'item_id' => $item['item_id'] ?? null,
                ];
                $totalQty += $remaining;
                if (!$firstPoiId) $firstPoiId = $poiId;
            }
            if (empty($lotItems)) {
                http_response_code(400);
                echo json_encode(['error' => 'No available lots found']);
                exit;
            }
            // Group lot_items by lot_number to merge same lots
            $groupedLotItems = [];
            foreach ($lotItems as $li) {
                $key = $li['lot_number'] ?? uniqid();
                if (!isset($groupedLotItems[$key])) {
                    $groupedLotItems[$key] = $li;
                    $groupedLotItems[$key]['qty'] = 0;
                }
                $groupedLotItems[$key]['qty'] += intval($li['qty'] ?? 0);
            }
            $lotItems = array_values($groupedLotItems);

            $conn = \App\Core\BaseModel::getConnection();
            $perPoiDelivery = [];
            foreach ($lotItems as $li) {
                $poiId = $li['poi_id'] ?? null;
                if (!$poiId) continue;
                $perPoiDelivery[$poiId] = ($perPoiDelivery[$poiId] ?? 0) + intval($li['qty']);
            }
            foreach ($perPoiDelivery as $poiId => $requestedQty) {
                $poiStmt = $conn->prepare("SELECT poi.quantity, poi.delivered_quantity
                        FROM purchase_order_items poi WHERE poi.poi_id = ?");
                $poiStmt->execute([$poiId]);
                $poiData = $poiStmt->fetch();
                if ($poiData) {
                    $remaining = intval($poiData['quantity']) - intval($poiData['delivered_quantity']);
                    if ($requestedQty > $remaining) {
                        http_response_code(400);
                        echo json_encode(['error' => "Delivery blocked: Requested quantity ({$requestedQty}) exceeds open PO balance ({$remaining}) for PO item #{$poiId}."]);
                        exit;
                    }
                }
            }

            $this->warehouseModel->createDelivery([
                'po_id' => $po_id,
                'poi_id' => $firstPoiId,
                'delivered_by' => $_SESSION['user_id'],
                'delivery_date' => date('Y-m-d'),
                'delivery_quantity' => $totalQty,
                'dr_number' => $dr_number,
                'lot_items' => json_encode($lotItems),
                'remarks' => ''
            ]);
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'warehouse', 'Saved DR number ' . $dr_number . ' for ' . count($lotIdArray) . ' lot(s) on PO #' . $po_id, null, ['dr_number' => $dr_number, 'lot_ids' => $lotIdArray], 'delivery', null);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            error_log('saveDRNumberForLots error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save DR number']);
        }
        exit;
    }

    public function activityLogs() {
        $auditModel = new \App\Models\AuditModel();
        $filters = [
            'department' => $_SESSION['department'] ?? 'warehouse',
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
        $data['logController'] = 'warehouse';
        $data['departmentLocked'] = true;
        $data['hideDeptColumn'] = true;
        $data['stats'] = [
            'today_count' => \App\Models\AuditModel::getLogStats('warehouse')['today_count'] ?? 0,
            'by_department' => [],
        ];
        $data['page_title'] = 'Activity Logs';
        $this->render('activity_logs/index', $data);
    }

    public function reports() {
        $customerId = $_GET['customer_id'] ?? null;
        $weekOffset = max(0, intval($_GET['week_offset'] ?? 0));

        $weeklyStats = $this->warehouseModel->getWeeklyDeliveryStats($customerId, $weekOffset);
        $customers = $this->warehouseModel->getCustomersWithDeliveries();

        $allStats = $this->warehouseModel->getWeeklyDeliveryStats(null, $weekOffset);
        $startYearWeek = !empty($allStats) ? intval($allStats[0]['year_week']) : 0;

        $weeklyDetails = [];
        foreach ($weeklyStats as $ws) {
            $yearWeek = $ws['year_week'];
            $weeklyDetails[$yearWeek] = $this->warehouseModel->getDeliveryDetailsForWeek($yearWeek, $customerId);
        }

        $poItemSummary = $this->warehouseModel->getPoItemSummary();

        $lotItems = $this->warehouseModel->getUniqueItemsForLots();
        $selectedLotItem = $_GET['lot_item_id'] ?? null;
        if ($selectedLotItem) {
            $lotData = $this->warehouseModel->getLotsByItem($selectedLotItem);
        } else {
            $lotData = $this->warehouseModel->getAllLotsStockOnHand();
        }

        $data = [
            'weeklyStats' => $weeklyStats,
            'customers' => $customers,
            'selectedCustomer' => $customerId,
            'weeklyDetails' => $weeklyDetails,
            'weekOffset' => $weekOffset,
            'startYearWeek' => $startYearWeek,
            'hasMoreWeeks' => count(array_filter($weeklyStats, fn($s) => intval($s['delivery_count']) > 0)) >= 12,
            'poItemSummary' => $poItemSummary,
            'lotItems' => $lotItems,
            'selectedLotItem' => $selectedLotItem,
            'lotData' => $lotData,
            'page_title' => 'Warehouse Reports'
        ];
        $this->render('reports/index', $data);
    }

    public function deliveryReport() {
        $filters = [
            'search'      => $_GET['search'] ?? '',
            'date_from'   => $_GET['date_from'] ?? '',
            'date_to'     => $_GET['date_to'] ?? '',
            'customer_id' => $_GET['customer_id'] ?? '',
        ];
        $deliveries = $this->warehouseModel->getDeliveryReportData($filters);
        $customers = $this->warehouseModel->getDeliveryReportCustomers();

        $data = [
            'deliveries'      => $deliveries,
            'customers'       => $customers,
            'filters'         => $filters,
            'page_title'      => 'Delivery Report'
        ];
        $this->render('reports/delivery_report', $data);
    }

    public function exportDeliveryReport() {
        $filters = [
            'search'      => $_GET['search'] ?? '',
            'date_from'   => $_GET['date_from'] ?? '',
            'date_to'     => $_GET['date_to'] ?? '',
            'customer_id' => $_GET['customer_id'] ?? '',
        ];
        $deliveries = $this->warehouseModel->getDeliveryReportData($filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="delivery_report_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['#', 'DR Number', 'SI Number', 'Customer', 'PO Number', 'Item Code', 'Item', 'Lot Number', 'Quantity', 'Cases', 'Plate No.', 'Vehicle', 'Logistic Provider', 'Type', 'Delivery Date', 'Delivered By', 'Remarks']);
        $rowNum = 0;
        foreach ($deliveries as $d) {
            $lotItems = json_decode($d['lot_items'] ?? '[]', true);
            if (!is_array($lotItems) || count($lotItems) === 0) {
                $lotItems = [[
                    'item_description' => $d['item_description'] ?? 'Unknown',
                    'lot_number' => $d['lot_number'] ?? '—',
                    'qty' => $d['delivery_quantity'] ?? 0,
                    'actual_uom_conversion' => $d['actual_uom_conversion'] ?? null,
                    'uom_conversion' => $d['uom_conversion'] ?? null,
                    'item_uom' => $d['item_uom'] ?? ''
                ]];
            }
            foreach ($lotItems as $li) {
                $rowNum++;
                $qty = intval($li['qty'] ?? 0);
                $conv = $li['actual_uom_conversion'] ?? $li['uom_conversion'] ?? null;
                $uom = $li['item_uom'] ?? '';
                $cases = ($conv && $uom !== 'CS') ? floor($qty / $conv) . ' CS' : '';
                $remarks = $d['report_remarks'] ?? $d['remarks'] ?? '';
                fputcsv($output, [
                    $rowNum,
                    $d['dr_number'] ?? '',
                    $d['si_number'] ?? '',
                    $d['customer_name'] ?? '',
                    $d['customer_po_number'] ?? '',
                    $li['item_code'] ?? '',
                    $li['item_description'] ?? '',
                    $li['lot_number'] ?? '',
                    $qty,
                    $cases,
                    $d['plate_number'] ?? '',
                    $d['vehicle_type'] ?? '',
                    $d['logistic_provider'] ?? '',
                    $d['production_type'] ?? '',
                    $d['delivery_date'] ?? '',
                    $d['delivered_by_name'] ?? '',
                    $remarks
                ]);
            }
        }

        fclose($output);
        exit;
    }

    public function exportReports() {
        $customerId = $_GET['customer_id'] ?? null;
        $weekOffset = max(0, intval($_GET['week_offset'] ?? 0));

        $allStats = $this->warehouseModel->getWeeklyDeliveryStats($customerId, $weekOffset);

        $poItemSummary = $this->warehouseModel->getPoItemSummary();

        $lotItems = $this->warehouseModel->getUniqueItemsForLots();
        $selectedLotItem = $_GET['lot_item_id'] ?? null;
        $allLotData = [];
        if ($selectedLotItem) {
            $allLotData = $this->warehouseModel->getLotsByItem($selectedLotItem);
        } else {
            foreach ($lotItems as $li) {
                $lots = $this->warehouseModel->getLotsByItem($li['item_id']);
                $allLotData = array_merge($allLotData, $lots);
            }
        }

        $filterSearch = $_GET['search'] ?? '';
        $filterPo = $_GET['po_filter'] ?? '';
        $filterItem = $_GET['item_filter'] ?? '';
        $filterStatus = $_GET['status_filter'] ?? '';

        $filteredItems = $poItemSummary;
        if ($filterSearch !== '') {
            $searchLower = strtolower($filterSearch);
            $filteredItems = array_filter($filteredItems, fn($i) =>
                str_contains(strtolower($i['customer_name'] ?? ''), $searchLower)
                || str_contains(strtolower($i['customer_po_number'] ?? ''), $searchLower)
                || str_contains(strtolower($i['item_description'] ?? ''), $searchLower)
            );
        }
        if ($filterPo !== '') {
            $filteredItems = array_filter($filteredItems, fn($i) => strtolower($i['customer_po_number']) === $filterPo);
        }
        if ($filterItem !== '') {
            $filteredItems = array_filter($filteredItems, fn($i) => strtolower($i['item_description']) === $filterItem);
        }
        if ($filterStatus !== '') {
            $filteredItems = array_filter($filteredItems, function($i) use ($filterStatus) {
                $p = intval($i['produced_quantity']);
                $d = intval($i['delivered_quantity']);
                $q = intval($i['po_qty']);
                if ($filterStatus === 'completed') return $d >= $q;
                if ($filterStatus === 'in-progress') return $p > 0 && ($p < $q || $d < $q);
                if ($filterStatus === 'pending') return $p === 0;
                return true;
            });
        }
        $filteredItems = array_values($filteredItems);

        $xlsx = new XlsxExport();

        $weekFrom = ($weekOffset * 12) + 1;

        $xlsx->addSheet('Weekly Delivery Graph');
        $xlsx->addRow(['WEEKLY DELIVERY GRAPH'], 1);
        $xlsx->addMerge('A', 1, 'E', 1);
        $weekHeaderRow = $xlsx->addRow(['Week', 'Year', 'Date Range', 'Deliveries', 'Cases'], 2);
        $xlsx->setAutoFilter('A', $weekHeaderRow, 'E', $weekHeaderRow);
        $weekNum = $weekFrom;
        foreach ($allStats as $ws) {
            $yw = $ws['year_week'];
            $year = intval(floor($yw / 100));
            $week = intval($yw % 100);
            $jan4 = mktime(0, 0, 0, 1, 4, $year);
            $dayOffset = ($week - 1) * 7 - date('w', $jan4) + 1;
            $mon = date('M d', mktime(0, 0, 0, 1, 4 + $dayOffset, $year));
            $sun = date('M d', mktime(0, 0, 0, 1, 4 + $dayOffset + 6, $year));
            $xlsx->addRow([$weekNum, $year, $mon . ' - ' . $sun, intval($ws['delivery_count']), intval($ws['total_cases'])]);
            $weekNum++;
        }
        $xlsx->autoFitColumns();

        $xlsx->addSheet('PO Item Summary');
        $xlsx->addRow(['PO ITEM SUMMARY'], 1);
        $xlsx->addMerge('A', 1, 'I', 1);
        $poHeaderRow = $xlsx->addRow(['Customer', 'PO Number', 'Item Code', 'Item', 'PO Qty', 'Produced', 'Delivered', 'Balance', 'Status'], 2);
        $xlsx->setAutoFilter('A', $poHeaderRow, 'I', $poHeaderRow);
        foreach ($filteredItems as $item) {
            $conv = intval($item['uom_conversion'] ?? 0);
            $ordered = intval($item['po_qty']);
            $produced = intval($item['produced_quantity']);
            $delivered = intval($item['delivered_quantity']);
            $balance = $ordered - $delivered;
            if ($conv > 0) {
                $ordered = floor($ordered / $conv);
                $produced = floor($produced / $conv);
                $delivered = floor($delivered / $conv);
                $balance = floor($balance / $conv);
            }
            if ($delivered >= $ordered) {
                $status = 'Completed';
            } elseif ($produced > 0) {
                $status = 'In Progress';
            } else {
                $status = 'Pending';
            }
            $xlsx->addRow([
                $item['customer_name'] ?? '',
                $item['customer_po_number'] ?? '',
                $item['item_code'] ?? '',
                $item['item_description'] ?? '',
                $ordered, $produced, $delivered, $balance, $status
            ]);
        }
        $xlsx->autoFitColumns();

        $xlsx->addSheet('Stock on Hand');
        $xlsx->addRow(['STOCK ON HAND'], 1);
        $xlsx->addMerge('A', 1, 'G', 1);
        $sohHeaderRow = $xlsx->addRow(['Customer', 'PO Number', 'Lot Number', 'Stock on Hand (cs)', 'Delivered (cs)', 'Expiration Date', 'Created By'], 2);
        $xlsx->setAutoFilter('A', $sohHeaderRow, 'G', $sohHeaderRow);
        foreach ($allLotData as $lot) {
            $stockOnHandPcs = max(0, $lot['quantity_produced'] - $lot['quantity_delivered']);
            $conv = intval($lot['uom_conversion'] ?? 0);
            $stockCs = $conv > 0 ? floor($stockOnHandPcs / $conv) : $stockOnHandPcs;
            $deliveredCs = $conv > 0 ? floor($lot['quantity_delivered'] / $conv) : $lot['quantity_delivered'];
            $stockLabel = $conv > 0 ? $stockCs . ' cs' : $stockOnHandPcs . ' pcs';
            $deliveredLabel = $conv > 0 ? $deliveredCs . ' cs' : $lot['quantity_delivered'] . ' pcs';
            $expiry = $lot['lot_date'] ? date('M Y', strtotime($lot['lot_date'] . ' +3 years')) : '-';
            $xlsx->addRow([
                $lot['customer_name'] ?? '',
                $lot['customer_po_number'] ?? '',
                $lot['lot_number'] ?? '',
                $stockLabel,
                $deliveredLabel,
                $expiry,
                $lot['created_by_name'] ?? '-'
            ]);
        }
        $xlsx->autoFitColumns();

        $xlsx->download('warehouse_reports_' . date('Y-m-d') . '.xlsx');
    }

    public function getPOItemsForAssignment() {
        header('Content-Type: application/json');
        $po_id = $_GET['po_id'] ?? null;
        if (!$po_id) {
            echo json_encode([]);
            exit;
        }
        $items = $this->warehouseModel->getPurchaseOrderItems($po_id);
        echo json_encode($items);
        exit;
    }

    public function getLotsForTransfer() {
        header('Content-Type: application/json');
        $poi_id = $_GET['poi_id'] ?? null;
        if (!$poi_id) {
            echo json_encode([]);
            exit;
        }
        $result = $this->warehouseModel->getAvailableLotsForTransfer($poi_id);
        echo json_encode($result);
        exit;
    }

    public function getActivePOsForAssignment() {
        header('Content-Type: application/json');
        $pos = $this->warehouseModel->getAllActivePOs();
        echo json_encode($pos);
        exit;
    }

    // ─── MRP Sheet ────────────────────────────────────────────────────────────

    public function mrp() {
        $customers = $this->warehouseModel->getActiveCustomersForMrp();
        $fgOptions = [];
        $fgHeader = null;
        $mrpSection = null;
        $customerId = isset($_GET['customer_id']) && $_GET['customer_id'] !== '' ? intval($_GET['customer_id']) : null;
        $fgItemId = isset($_GET['fg_item_id']) && $_GET['fg_item_id'] !== '' ? intval($_GET['fg_item_id']) : null;
        $targetQty = isset($_GET['target_qty']) && $_GET['target_qty'] !== '' ? floatval($_GET['target_qty']) : null;
        // Switch BOM: 0/absent = default formulation for this FG/customer.
        $selectedBomId = isset($_GET['bom_id']) && $_GET['bom_id'] !== '' ? intval($_GET['bom_id']) : 0;
        $calculate = !empty($_GET['calculate']);
        $noFgsForCustomer = false;

        // FG/SFG dropdown: filtered by customer when selected; else all FG/SFG with active BOMs
        $fgOptions = $this->warehouseModel->getFgsWithBomByCustomer($customerId);
        if (!empty($customerId) && empty($fgOptions)) {
            $noFgsForCustomer = true;
        }

        if ($calculate && $fgItemId && $targetQty !== null && $targetQty > 0) {
            $bom = $this->warehouseModel->getBomForFg($fgItemId, $selectedBomId ?: null, $customerId);
            if ($bom) {
                // Keep the resolved formulation in the URL so Calculate / Save /
                // Clear round-trip the same BOM (falls back to the default).
                $selectedBomId = (int) $bom['bom_id'];
                $components = $this->warehouseModel->getBOMComponentsWithStock([$bom['bom_id']]);
                $componentIds = array_map(function ($c) {
                    return $c['component_item_id'];
                }, $components);
                $pendingPoRrMap = $this->warehouseModel->getPendingPoRrForItems($componentIds);
                $meta = [
                    'fill_volume' => floatval($bom['fill_volume'] ?? 0),
                    'uom' => $bom['uom'] ?? '',
                    'batch_unit_divisor' => floatval($bom['batch_unit_divisor'] ?? 1000) ?: 1000,
                    'is_legacy_formula' => !empty($bom['is_legacy_formula']),
                ];
                $rows = [];
                foreach ($components as $comp) {
                    $required = $this->computeMrpPoolRequiredQty($targetQty, $meta, $comp);
                    $soh = floatval($comp['soh']);
                    $allocated = floatval($comp['allocated']);
                    // Available is unallocated physical SOH only
                    // (max(0, SOH - Allocated)). Lacking deducts that and
                    // nothing else — stock promised to prior MRP runs/MOs must
                    // not shrink what this run orders. Pending PO/RR stays an
                    // informational column.
                    $available = max(0, $soh - $allocated);
                    $pendingPoRr = floatval($pendingPoRrMap[$comp['component_item_id']] ?? 0);
                    $lacking = max(0, $required - $available);
                    $rows[] = [
                        'component_item_id' => $comp['component_item_id'],
                        'item_code' => $comp['item_code'],
                        'item_description' => $comp['item_description'],
                        'item_uom' => $comp['item_uom'],
                        'display_uom' => $comp['display_uom'] ?? $comp['item_uom'],
                        'item_type' => $comp['item_type'] ?? '',
                        'category' => $comp['item_type'] ?? '-',
                        'phase_code' => $comp['phase_code'] ?? '101',
                        'required_qty' => $required,
                        'soh' => $soh,
                        'allocated' => $allocated,
                        'pending_po_rr' => $pendingPoRr,
                        'available' => $available,
                        'lacking_qty' => $lacking,
                        'dosage_rate' => floatval($comp['dosage_rate'] ?? 0),
                        'wastage_allowance_pct' => floatval($comp['wastage_allowance_pct'] ?? 0),
                    ];
                }
                $fgHeader = [
                    'fg_item_id' => $bom['fg_item_id'],
                    'fg_code' => $bom['fg_code'],
                    'fg_name' => $bom['fg_name'],
                    'item_uom' => $bom['item_uom'],
                    'bom_code' => $bom['bom_code'],
                    'is_default' => !empty($bom['is_default']),
                    'fill_volume' => $meta['fill_volume'],
                    'uom' => $meta['uom'],
                    'batch_unit_divisor' => $meta['batch_unit_divisor'],
                    'is_legacy_formula' => $meta['is_legacy_formula'],
                    'target_qty' => $targetQty,
                ];
                $mrpSection = [
                    'fg_item_id' => $bom['fg_item_id'],
                    'fg_code' => $bom['fg_code'],
                    'fg_name' => $bom['fg_name'],
                    'target_qty' => $targetQty,
                    'item_uom' => $bom['item_uom'],
                    'fill_volume' => $meta['fill_volume'],
                    'uom' => $meta['uom'],
                    'batch_unit_divisor' => $meta['batch_unit_divisor'],
                    'is_legacy_formula' => $meta['is_legacy_formula'],
                    'components' => $rows,
                ];
            }
        }

        $this->render('mrp/preview', [
            'customers' => $customers,
            'fgOptions' => $fgOptions,
            'fgHeader' => $fgHeader,
            'mrpSection' => $mrpSection,
            'selectedCustomer' => $customerId,
            'selectedFg' => $fgItemId,
            'selectedBomId' => $selectedBomId ?: null,
            'targetQty' => $targetQty,
            'didCalculate' => $calculate,
            'noFgsForCustomer' => $noFgsForCustomer,
            // legacy/PO keys kept for header actions that still reference them
            'openPOs' => [],
            'poHeader' => null,
            'consolidated' => [],
            'selectedPO' => null,
            'hasExistingSnapshot' => false,
        ]);
    }

    /**
     * Queue lacking materials from an MRP calculation as Purchase Requests
     * (supplier_orders.status = 'requested') so Procurement can process them
     * directly from the Purchasing PO screen.
     */
    // JSON: every active BOM formulation for one FG/SFG. Powers the MRP Sheet
    // "Switch BOM" modal; the default formulation always comes back first.
    public function getItemBoms() {
        header('Content-Type: application/json');
        try {
            $fgItemId = intval($_GET['fg_item_id'] ?? 0);
            if ($fgItemId <= 0) {
                throw new \RuntimeException('Finished good is required.');
            }
            $customerId = intval($_GET['customer_id'] ?? 0);
            $boms = $this->warehouseModel->getItemBoms($fgItemId, $customerId ?: null);
            echo json_encode([
                'success' => true,
                'count' => count($boms),
                'boms' => $boms,
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // JSON: component rows of one BOM — the eye-button preview so users can
    // compare raw materials before switching formulations.
    public function getBomComponents() {
        header('Content-Type: application/json');
        try {
            $bomId = intval($_GET['bom_id'] ?? 0);
            if ($bomId <= 0) {
                throw new \RuntimeException('BOM is required.');
            }
            $components = $this->warehouseModel->getBOMComponentsWithStock([$bomId]);
            $out = [];
            foreach ($components as $c) {
                $out[] = [
                    'component_code' => $c['item_code'],
                    'description' => $c['item_description'],
                    'category' => $c['item_type'] ?? '-',
                    'phase_code' => $c['phase_code'] ?? '101',
                    'dosage_rate' => floatval($c['dosage_rate'] ?? 0),
                    'wastage_allowance_pct' => floatval($c['wastage_allowance_pct'] ?? 0),
                    'uom' => $c['display_uom'] ?? ($c['item_uom'] ?? ''),
                    'soh' => floatval($c['soh'] ?? 0),
                    'available' => floatval($c['available_stock'] ?? 0),
                ];
            }
            echo json_encode(['success' => true, 'components' => $out]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // JSON: next auto-generated MRP reference (MRP#YYYY-XXXX) for the prompt
    // modal. Preview only — saveMrpCalculationRun assigns the final unique ref.
    public function getNextMrpRef() {
        header('Content-Type: application/json');
        try {
            echo json_encode([
                'success' => true,
                'next_mrp_number' => $this->warehouseModel->generateNextMrpRef(),
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function saveMrpCalculation() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=warehouse&action=mrp');
            exit;
        }

        $customerId = intval($_POST['customer_id'] ?? 0);
        $fgItemId = intval($_POST['fg_item_id'] ?? 0);
        $targetQty = floatval($_POST['target_qty'] ?? 0);
        $bomId = intval($_POST['bom_id'] ?? 0);
        $postedLacking = json_decode($_POST['lacking_json'] ?? '[]', true);
        // Editable ALLOCATED column: [component_item_id => allocated] posted by
        // the sheet's live recalculation. Invalid or negative entries are
        // ignored so the live inventory value stays authoritative for that row.
        // Absent payload = legacy client = today's behavior (no overrides).
        $postedRows = json_decode($_POST['rows_json'] ?? '[]', true);
        $allocOverrides = [];
        if (is_array($postedRows)) {
            foreach ($postedRows as $postedRow) {
                if (!is_array($postedRow)) continue;
                $id = intval($postedRow['component_item_id'] ?? 0);
                if ($id > 0 && is_numeric($postedRow['allocated'] ?? null) && floatval($postedRow['allocated']) >= 0) {
                    $allocOverrides[$id] = floatval($postedRow['allocated']);
                }
            }
        }

        try {
            if ($fgItemId <= 0 || $targetQty <= 0) {
                throw new \RuntimeException('Select a finished good and enter a target quantity first.');
            }
            // Saving with zero lackings is allowed (pure planning snapshot:
            // run lands in MRP History, no purchase requests are queued).
            if ($postedLacking === null) {
                $postedLacking = [];
            }
            if (!is_array($postedLacking)) {
                throw new \RuntimeException('Invalid lacking payload.');
            }
            // The MRP reference is generated server-side (MRP#YYYY-XXXX) inside
            // the save transaction — no user input to validate here.

            $bom = $this->warehouseModel->getBomForFg($fgItemId, $bomId ?: null, $customerId ?: null);
            if (!$bom) {
                throw new \RuntimeException('No active BOM found for this finished good.');
            }

            $components = $this->warehouseModel->getBOMComponentsWithStock([$bom['bom_id']]);
            $meta = [
                'fill_volume' => floatval($bom['fill_volume'] ?? 0),
                'uom' => $bom['uom'] ?? '',
                'batch_unit_divisor' => floatval($bom['batch_unit_divisor'] ?? 1000) ?: 1000,
                'is_legacy_formula' => !empty($bom['is_legacy_formula']),
            ];

            // Recompute server-side: the posted payload only marks intent,
            // quantities are authoritative from here (same math as Calculate).
            // Every component is kept for the snapshot/history row; only the
            // lackings drive purchase requests.
            $componentIds = array_map(function ($c) {
                return $c['component_item_id'];
            }, $components);
            $pendingPoRrMap = $this->warehouseModel->getPendingPoRrForItems($componentIds);
            $rows = [];
            $recomputed = [];
            foreach ($components as $comp) {
                $required = $this->computeMrpPoolRequiredQty($targetQty, $meta, $comp);
                $itemId = intval($comp['component_item_id']);
                $soh = floatval($comp['soh']);
                // Planner's manual ALLOCATED override from the editable sheet
                // column (validated above); falls back to live inventory.
                $allocated = $allocOverrides[$itemId] ?? floatval($comp['allocated']);
                // Same netting as the Calculate screen: deduct only
                // unallocated physical SOH (never negative). Pending PO/RR is
                // informational and never reduces the lacking quantity.
                $available = max(0, $soh - $allocated);
                $pendingPoRr = floatval($pendingPoRrMap[$comp['component_item_id']] ?? 0);
                $lacking = max(0, $required - $available);
                $rows[] = [
                    'component_item_id' => $itemId,
                    'required' => $required,
                    'soh' => $soh,
                    'allocated' => $allocated,
                    'pending_po_rr' => $pendingPoRr,
                    'uom' => $comp['display_uom'] ?? $comp['item_uom'],
                ];
                if ($lacking > 0) {
                    $recomputed[$itemId] = [
                        'quantity' => $lacking,
                        'uom' => $comp['display_uom'] ?? $comp['item_uom'],
                    ];
                }
            }

            $postedIds = [];
            foreach ($postedLacking as $row) {
                $id = intval($row['component_item_id'] ?? 0);
                if ($id > 0) {
                    $postedIds[$id] = true;
                }
            }
            $targets = array_intersect_key($recomputed, $postedIds);
            if (empty($postedIds) === false && empty($targets)) {
                // The client posted lackings but recomputation found none
                // (stale page) — block with a clear message instead of
                // silently saving an empty requisition set.
                throw new \RuntimeException('Nothing lacking for this calculation — recalculate and try again.');
            }

            // Logs the run to MRP Snapshot History, commits stock to
            // inventory_balances.qty_allocated and queues the purchase requests.
            $result = $this->warehouseModel->saveMrpCalculationRun(
                $customerId,
                $fgItemId,
                $targetQty,
                $_SESSION['user_id'],
                $rows,
                $targets,
                null,
                $bom['bom_id']
            );

            $zeroLacking = !empty($result['zero_lacking']);
            if (!$zeroLacking) {
                $_SESSION['success'] = "MRP Run {$result['mrp_ref']} (#{$result['run_id']}) saved to MRP History as FOR APPROVAL — "
                    . "{$result['queued']} draft requisition(s) awaiting Warehouse Admin, then System Admin. "
                    . "Stock allocation and Pending PO / RR stay frozen until both approvals pass and the run is released to Procurement.";
            } else {
                $_SESSION['success'] = "MRP Run {$result['mrp_ref']} (#{$result['run_id']}) saved to MRP History as FOR APPROVAL — "
                    . "no lacking items, no purchase requests created. Awaiting Warehouse Admin, then System Admin review; "
                    . "stock is reserved only after the System Admin approval.";
            }

            try {
                NotificationHelper::create('po', 'MRP Run Awaiting WHSE Admin Approval',
                    "{$result['mrp_ref']} — saved by " . ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'user')
                    . ($zeroLacking
                        ? ' (no lacking items — no purchase requests). Needs Warehouse Admin, then System Admin approval.'
                        : " with {$result['queued']} draft requisition(s). Needs Warehouse Admin, then System Admin approval before reaching Procurement."),
                    'warehouse', '?controller=warehouse&action=mrpApproval', $_SESSION['user_id'] ?? null);
            } catch (\Exception $e) {
                error_log('mrpApproval notification error: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        $params = ['controller=warehouse', 'action=mrp'];
        if ($customerId > 0) $params[] = 'customer_id=' . $customerId;
        if ($fgItemId > 0) $params[] = 'fg_item_id=' . $fgItemId;
        if ($targetQty > 0) $params[] = 'target_qty=' . rawurlencode($targetQty);
        // Carry the formulation used (resolved BOM, else the posted choice) so
        // the redirect keeps showing the same results after a save or failure.
        $redirectBomId = (!empty($bom) && !empty($bom['bom_id'])) ? (int) $bom['bom_id'] : $bomId;
        if ($redirectBomId > 0) $params[] = 'bom_id=' . $redirectBomId;
        $params[] = 'calculate=1';
        header('Location: ?' . implode('&', $params));
        exit;
    }

    public function mrpPDF() {
        $customerId = $_GET['customer_id'] ?? null;
        $poId = $_GET['po_id'] ?? null;
        if (!$poId) {
            header('Location: ?controller=warehouse&action=mrp');
            exit;
        }

        $poHeader = $this->warehouseModel->getPurchaseOrderById($poId);
        $allPoItems = $this->warehouseModel->getPOItemsWithBOM($poId);

        $bomIds = [];
        $poiIds = [];
        foreach ($allPoItems as $poi) {
            if (!empty($poi['bom_id'])) $bomIds[] = $poi['bom_id'];
            $poiIds[] = $poi['poi_id'];
        }

        $allComponents = $this->warehouseModel->getBOMComponentsWithStock($bomIds);
        $componentsByBom = [];
        $allIngredientIds = [];
        foreach ($allComponents as $comp) {
            $componentsByBom[$comp['bom_id']][] = $comp;
            $allIngredientIds[$comp['component_item_id']] = true;
        }

        $pendingAllocations = $this->warehouseModel->getPendingAllocationsAcrossPOs(
            $poiIds,
            array_keys($allIngredientIds)
        );

        $pendingSupplierOrders = $this->warehouseModel->getPendingSupplierOrdersForItems(array_keys($allIngredientIds), $poId);

        $mrpSections = [];
        foreach ($allPoItems as $poi) {
            if (empty($poi['bom_id'])) continue;
            $meta = $this->mrpBomMeta($poi);
            $components = $componentsByBom[$poi['bom_id']] ?? [];
            $rows = [];
            foreach ($components as $comp) {
                $totalReqt = $this->computeMrpRequiredQty(floatval($poi['quantity']), $poi, $comp);
                $soh = floatval($comp['soh']);
                $allocated = floatval($pendingAllocations[$comp['component_item_id']] ?? 0);
                $supplierPending = floatval($pendingSupplierOrders[$comp['component_item_id']] ?? 0);
                $available = $soh - $allocated;
                $excess = $available - $totalReqt + $supplierPending;

                if ($totalReqt == 0) $remarks = 'NO NEED';
                elseif ($available <= 0 && $supplierPending == 0) $remarks = 'NO stock for next order mfg';
                elseif ($excess < 0) $remarks = 'LACKING';
                else $remarks = 'OK';

                $rows[] = [
                    'component_item_id' => $comp['component_item_id'],
                    'item_code' => $comp['item_code'],
                    'item_description' => $comp['item_description'],
                    'item_uom' => $comp['item_uom'],
                    'display_uom' => $comp['display_uom'] ?? $comp['item_uom'],
                    'phase_code' => $comp['phase_code'] ?? '101',
                    'total_reqt' => $totalReqt,
                    'soh' => $soh,
                    'allocated' => $allocated,
                    'pending' => $allocated + $supplierPending,
                    'supplier_pending' => $supplierPending,
                    'excess' => $excess,
                    'remarks' => $remarks,
                ];
            }
            $mrpSections[] = [
                'fg_code' => $poi['item_code'],
                'fg_name' => $poi['item_description'],
                'target_qty' => $poi['quantity'],
                'item_uom' => $poi['item_uom'],
                'batch_qty' => $meta['batch_qty'],
                'batch_uom' => $meta['batch_uom'],
                'batches_needed' => $meta['batches_needed'],
                'fill_volume' => $meta['fill_volume'],
                'uom' => $meta['uom'],
                'batch_unit_divisor' => $meta['batch_unit_divisor'],
                'is_legacy_formula' => $meta['is_legacy_formula'],
                'bulk_batch' => $meta['bulk_batch'],
                'components' => $rows,
            ];
        }

        $consolidatedMap = [];
        foreach ($mrpSections as $section) {
            foreach ($section['components'] as $row) {
                $key = $row['item_code'];
                if (!isset($consolidatedMap[$key])) {
                    $consolidatedMap[$key] = $row;
                } else {
                    $consolidatedMap[$key]['total_reqt'] += $row['total_reqt'];
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

        $data = [
            'po' => $poHeader,
            'mrpSections' => $mrpSections,
            'consolidated' => $consolidated,
            'userName' => $_SESSION['full_name'] ?? '',
            'userDept' => $_SESSION['department'] ?? '',
        ];
        extract($data);

        ob_start();
        include __DIR__ . "/../views/mrp/pdf_template.php";
        $html = ob_get_clean();

        require_once __DIR__ . '/../../public/vendor/dompdf/autoload.inc.php';
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $poNumber = $poHeader['customer_po_number'] ?? 'PO';
        $dompdf->stream("MRP_{$poNumber}.pdf", ['Attachment' => false]);
        exit;
    }

    /**
     * PDF of the FG-based MRP Sheet (the live calculation, not a saved run).
     * POST: customer_id, fg_item_id, target_qty, bom_id, rows_json (live
     * ALLOCATED overrides). Recomputes server-side with the same math as
     * Calculate/Save so the PDF always matches the sheet the user sees.
     */
    public function mrpSheetPDF() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=warehouse&action=mrp');
            exit;
        }

        $customerId = intval($_POST['customer_id'] ?? 0);
        $fgItemId = intval($_POST['fg_item_id'] ?? 0);
        $targetQty = floatval($_POST['target_qty'] ?? 0);
        $bomId = intval($_POST['bom_id'] ?? 0);
        $postedRows = json_decode($_POST['rows_json'] ?? '[]', true);
        $allocOverrides = [];
        if (is_array($postedRows)) {
            foreach ($postedRows as $postedRow) {
                if (!is_array($postedRow)) continue;
                $id = intval($postedRow['component_item_id'] ?? 0);
                if ($id > 0 && is_numeric($postedRow['allocated'] ?? null) && floatval($postedRow['allocated']) >= 0) {
                    $allocOverrides[$id] = floatval($postedRow['allocated']);
                }
            }
        }

        try {
            if ($fgItemId <= 0 || $targetQty <= 0) {
                throw new \RuntimeException('Nothing to print — select a finished good and target quantity first.');
            }
            $bom = $this->warehouseModel->getBomForFg($fgItemId, $bomId ?: null, $customerId ?: null);
            if (!$bom) {
                throw new \RuntimeException('No active BOM found for this finished good.');
            }

            $components = $this->warehouseModel->getBOMComponentsWithStock([$bom['bom_id']]);
            $meta = [
                'fill_volume' => floatval($bom['fill_volume'] ?? 0),
                'uom' => $bom['uom'] ?? '',
                'batch_unit_divisor' => floatval($bom['batch_unit_divisor'] ?? 1000) ?: 1000,
                'is_legacy_formula' => !empty($bom['is_legacy_formula']),
            ];
            $componentIds = array_map(function ($c) {
                return $c['component_item_id'];
            }, $components);
            $pendingPoRrMap = $this->warehouseModel->getPendingPoRrForItems($componentIds);

            $rows = [];
            foreach ($components as $comp) {
                $required = $this->computeMrpPoolRequiredQty($targetQty, $meta, $comp);
                $itemId = intval($comp['component_item_id']);
                $soh = floatval($comp['soh']);
                $allocated = $allocOverrides[$itemId] ?? floatval($comp['allocated']);
                $available = max(0, $soh - $allocated);
                $pendingPoRr = floatval($pendingPoRrMap[$itemId] ?? 0);
                $lacking = max(0, $required - $available);
                $rows[] = [
                    'component_item_id' => $itemId,
                    'item_code' => $comp['item_code'],
                    'item_description' => $comp['item_description'],
                    'item_uom' => $comp['item_uom'],
                    'display_uom' => $comp['display_uom'] ?? $comp['item_uom'],
                    'category' => $comp['item_type'] ?? '-',
                    'required_qty' => $required,
                    'soh' => $soh,
                    'allocated' => $allocated,
                    'pending_po_rr' => $pendingPoRr,
                    'available' => $available,
                    'lacking_qty' => $lacking,
                ];
            }

            $customerName = '';
            if ($customerId > 0) {
                $cStmt = \App\Core\BaseModel::getConnection()->prepare("SELECT customer_name FROM customers WHERE customer_id = :id");
                $cStmt->execute(['id' => $customerId]);
                $customerName = (string) $cStmt->fetchColumn();
            }

            $data = [
                'fgHeader' => [
                    'fg_code' => $bom['fg_code'],
                    'fg_name' => $bom['fg_name'],
                    'bom_code' => $bom['bom_code'],
                    'is_default' => !empty($bom['is_default']),
                    'fill_volume' => $meta['fill_volume'],
                    'uom' => $meta['uom'],
                    'batch_unit_divisor' => $meta['batch_unit_divisor'],
                    'is_legacy_formula' => $meta['is_legacy_formula'],
                    'target_qty' => $targetQty,
                ],
                'components' => $rows,
                'customerName' => $customerName,
                'userName' => $_SESSION['full_name'] ?? '',
                'userDept' => $_SESSION['department'] ?? '',
            ];
            extract($data);

            ob_start();
            include __DIR__ . "/../views/mrp/pdf_template_fg.php";
            $html = ob_get_clean();

            require_once __DIR__ . '/../../public/vendor/dompdf/autoload.inc.php';
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            $fgCode = preg_replace('/[^A-Za-z0-9_\-]/', '', $bom['fg_code'] ?? 'MRP');
            $dompdf->stream("MRP_{$fgCode}_" . date('Ymd') . ".pdf", ['Attachment' => false]);
            exit;
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: ?controller=warehouse&action=mrp');
            exit;
        }
    }

    // ─── Purchasing PO (renamed from Procurement PO) ────────────────────────────

    public function purchasingPo() {
        $filters = [
            'tab' => $_GET['tab'] ?? '',
            'status' => $_GET['status'] ?? '',
            'search' => $_GET['search'] ?? '',
        ];
        $orders = $this->warehouseModel->getPurchasingPoFiltered($filters);
        // Full line sets for every MRP source present in the filtered list —
        // the Source group's View/Process modals show the WHOLE run, even
        // when a status tab hides some of its lines.
        $runIds = array_values(array_unique(array_filter(array_map(function ($o) {
            return (int) ($o['mrp_run_id'] ?? 0);
        }, $orders))));
        $data['page_title'] = 'Purchasing PO';
        $data['orders'] = $orders;
        $data['filters'] = $filters;
        $data['tabCounts'] = $this->warehouseModel->getPurchasingTabCounts();
        $data['suppliers'] = $this->warehouseModel->getSuppliers();
        $data['mrpRunLines'] = $this->warehouseModel->getSupplierOrdersByRuns($runIds);
        $data['readOnly'] = (($_SESSION['department'] ?? '') !== 'warehouse');
        // MRP dual-approval gate, step 1: only flagged warehouse users see/use
        // the Approve/Reject buttons on pending_whse_approval rows.
        $data['isWarehouseApprover'] = $this->warehouseModel->isWarehouseApprover($_SESSION['user_id'] ?? null);
        $this->render('purchasingPo/index', $data);
        }

    // ─── MRP Approval (dedicated gate screen) ─────────────────────────────────
    // Warehouse Admin viewport: requisition drafts born from the MRP Sheet,
    // listable by any warehouse user, approvable only by flagged approvers.
    public function mrpApproval() {
        // Non-approver warehouse users track progress on MRP History instead —
        // the approval queue is only meaningful for WHSE Admins.
        if (($_SESSION['department'] ?? '') !== 'warehouse'
            || !$this->warehouseModel->isWarehouseApprover($_SESSION['user_id'] ?? null)) {
            if (($_SESSION['department'] ?? '') === 'warehouse') {
                $_SESSION['success'] = 'MRP requisitions awaiting approval are tracked on the MRP History screen.';
            }
            header('Location: ?controller=warehouse&action=mrpHistory');
            exit;
        }
        $runs = $this->warehouseModel->getMrpApprovalRuns('whse');
        $decisionRuns = $this->warehouseModel->getMrpWhseDecisionRuns(20);
        $data['page_title'] = 'MRP Approval';
        $data['runs'] = $runs;
        $data['decisionRuns'] = $decisionRuns;
        $data['isApprover'] = true;
        $data['gate'] = 'whse';
        $this->render('mrpApproval/index', $data);
    }

    // ─── MRP dual-approval gate, step 1: Warehouse Admin approve/reject ───────
    // Drafts are born as status 'pending_whse_approval'. A warehouse user
    // flagged is_approver moves them to 'pending_sysadmin_approval' or cancels
    // them. The System Admin (department='admin') completes step 2 on the
    // admin module's Purchasing PO screen.

    /** POST-required guard shared by the approve/reject actions. */
    private function enforceWarehouseApproverAccess(): void {
        if (($_SESSION['department'] ?? '') !== 'warehouse'
        || !$this->warehouseModel->isWarehouseApprover($_SESSION['user_id'] ?? null)) {
            $_SESSION['error'] = 'Only Warehouse Admins can approve or reject PO drafts at the WHSE Admin stage.';
            $this->redirectToRoleHome('purchasingPo');
        }
    }

    private function approvalAuditDescription(array $order, string $verb, string $stageLabel, string $extra = ''): string {
        return "MRP requisition {$verb} at the {$stageLabel} stage — order #{$order['supplier_order_id']} "
            . 'item ' . ($order['item_code'] ?? '') . ' qty ' . $order['quantity']
            . ($order['mrp_ref'] ? ' (' . $order['mrp_ref'] . ')' : '')
            . ($extra !== '' ? ' — ' . $extra : '');
    }

    /**
     * Shared POST handler for both controllers: validates the stage for the
     * acting role and applies the row-locked approve/reject, then fires the
     * cross-department notification. Returns without exiting so callers can
     * flash/redirect as usual; throws on refusal.
     */
    private function applyMrpApproval(bool $systemAdminStage, bool $approve, string $reason = ''): void {
        $id = intval($_POST['supplier_order_id'] ?? 0);
        if ($id <= 0) throw new \RuntimeException('Invalid requisition.');

        $order = $this->warehouseModel->getSupplierOrderStatus($id);
        if (!$order) throw new \RuntimeException('Requisition not found.');

        if ($systemAdminStage) {
            $current = 'pending_sysadmin_approval';
            // Final gate (two-gate flow): release straight to Procurement —
            // QC Admin no longer gates requisitions.
            $next = 'requested';
            $stageLabel = 'System Admin';
            $actor = ($_SESSION['department'] ?? '') === 'admin';
        } else {
            $current = 'pending_whse_approval';
            $next = 'pending_sysadmin_approval';
            $stageLabel = 'Warehouse Admin';
            $actor = (($_SESSION['department'] ?? '') === 'warehouse')
                && $this->warehouseModel->isWarehouseApprover($_SESSION['user_id'] ?? null);
        }
        if (!$actor) {
            throw new \RuntimeException('You are not authorized to approve at the ' . $stageLabel . ' stage.');
        }

        $approverLabel = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'unknown') . ' (' . ($_SESSION['department'] ?? '') . ')';
        if ($approve) {
            $this->warehouseModel->approveMrpRequisition($id, $current, $next, $stageLabel, $approverLabel);
            $_SESSION['success'] = $this->approvalAuditDescription($order, 'approved', $stageLabel)
                . ($systemAdminStage ? ' — released to Procurement.' : ' — now awaiting System Admin approval.');
            AuditModel::log($_SESSION['user_id'], 'APPROVE', 'warehouse',
                $this->approvalAuditDescription($order, 'approved', $stageLabel),
                ['status' => $order['status']], ['status' => $next], 'supplier_order', $id);
            if ($systemAdminStage) {
                NotificationHelper::create('po', 'MRP Requisition Released to Procurement',
                    (string) $order['mrp_ref'] . ' — requisition for ' . $order['item_code'] . ' passed System Admin approval; Procurement can create the PO.',
                    'warehouse', '?controller=warehouse&action=purchasingPo&tab=requested');
            } else {
                NotificationHelper::create('po', 'MRP Requisition Awaiting System Admin',
                    (string) $order['mrp_ref'] . ' — requisition for ' . $order['item_code'] . ' passed Warehouse Admin approval; System Admin approval required.',
                    'admin', '?controller=admin&action=approvals');
            }
        } else {
            $this->warehouseModel->rejectMrpRequisition($id, $current, $stageLabel, $reason, $approverLabel);
            $_SESSION['success'] = $this->approvalAuditDescription($order, 'rejected', $stageLabel, 'Reason: ' . ($reason !== '' ? $reason : 'not provided'));
            AuditModel::log($_SESSION['user_id'], 'REJECT', 'warehouse',
                $this->approvalAuditDescription($order, 'rejected', $stageLabel, 'Reason: ' . ($reason !== '' ? $reason : 'not provided')),
                ['status' => $order['status']], ['status' => 'cancelled'], 'supplier_order', $id);
            NotificationHelper::create('po', 'MRP Requisition Rejected',
                (string) $order['mrp_ref'] . ' — requisition for ' . $order['item_code'] . ' was rejected at the ' . $stageLabel . ' stage.'
                . ($reason !== '' ? ' Reason: ' . $reason : ''),
                'warehouse', '?controller=warehouse&action=mrpApproval');
        }
    }

    public function approveMrpDraft() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \RuntimeException('POST required.');
            }
            $this->enforceWarehouseApproverAccess();
            $this->applyMrpApproval(false, true);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=warehouse&action=mrpApproval');
        exit;
    }

    public function rejectMrpDraft() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \RuntimeException('POST required.');
            }
            $this->enforceWarehouseApproverAccess();
            $reason = trim($_POST['reject_reason'] ?? '');
            $this->applyMrpApproval(false, false, $reason);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=warehouse&action=mrpApproval');
        exit;
    }

    // ─── MRP run-level decision (per-run Approve All / Reject) ────────────────
    // One decision covers every open draft requisition of the saved run.

    private function decideMrpRunRequest(bool $approve): void {
        $runId = intval($_POST['mrp_run_id'] ?? 0);
        if ($runId <= 0) throw new \RuntimeException('Invalid MRP run.');
        $label = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'unknown') . ' (' . ($_SESSION['department'] ?? '') . ')';
        if ($approve) {
            $result = $this->warehouseModel->decideMrpRun($runId, 'pending_whse_approval', 'pending_sysadmin_approval', $label);
            $_SESSION['success'] = 'MRP Run ' . ($result['mrp_ref'] ?: ('#' . $runId)) . ' approved — '
                . (!empty($result['zero_lacking'])
                    ? 'no purchase requests (nothing lacking); forwarded to System Admin approval.'
                    : $result['moved'] . ' requisition(s) forwarded to System Admin approval.');
            AuditModel::log($_SESSION['user_id'], 'APPROVE', 'warehouse',
                "MRP run approved at the Warehouse Admin stage — run #{$runId} ({$result['mrp_ref']}), {$result['moved']} requisition(s) forwarded to System Admin",
                null, ['status' => 'pending_sysadmin_approval'], 'mrp_run', $runId);
            try {
                NotificationHelper::create('po', 'MRP Run Awaiting System Admin',
                    ($result['mrp_ref'] ?: ('MRP run #' . $runId)) . ' — '
                    . (!empty($result['zero_lacking'])
                        ? 'passed Warehouse Admin approval (no purchase requests — nothing lacking); System Admin decision required.'
                        : $result['moved'] . ' requisition(s) passed Warehouse Admin approval; System Admin decision required.'),
                    'admin', '?controller=admin&action=approvals');
            } catch (\Exception $e) {
                error_log('mrpApproval notification error: ' . $e->getMessage());
            }
        } else {
            $reason = trim($_POST['reject_reason'] ?? '');
            $result = $this->warehouseModel->decideMrpRun($runId, 'pending_whse_approval', 'cancelled', $label, $reason);
            $_SESSION['success'] = 'MRP Run ' . ($result['mrp_ref'] ?: ('#' . $runId)) . ' rejected — '
                . (!empty($result['zero_lacking'])
                    ? 'no purchase requests were involved (nothing lacking). (Reason: ' . ($reason !== '' ? $reason : 'not provided') . ').'
                    : $result['moved'] . ' requisition(s) cancelled (Reason: ' . ($reason !== '' ? $reason : 'not provided') . ').');
            AuditModel::log($_SESSION['user_id'], 'REJECT', 'warehouse',
                "MRP run rejected at the Warehouse Admin stage — run #{$runId} ({$result['mrp_ref']}), {$result['moved']} requisition(s) cancelled — Reason: "
                . ($reason !== '' ? $reason : 'not provided'),
                null, ['status' => 'cancelled'], 'mrp_run', $runId);
            try {
                NotificationHelper::create('po', 'MRP Run Rejected',
                    ($result['mrp_ref'] ?: ('MRP run #' . $runId)) . ' — requisitions were '
                    . $result['moved'] . ' removed; nothing was pushed to Procurement. Reason: '
                    . ($reason !== '' ? $reason : 'not provided'),
                    'warehouse', '?controller=warehouse&action=mrpApproval');
            } catch (\Exception $e) {
                error_log('mrpApproval notification error: ' . $e->getMessage());
            }
        }
    }

    public function approveMrpRunDraft() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \RuntimeException('POST required.');
            }
            $this->enforceWarehouseApproverAccess();
            $this->decideMrpRunRequest(true);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=warehouse&action=mrpApproval');
        exit;
    }

    public function rejectMrpRunDraft() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \RuntimeException('POST required.');
            }
            $this->enforceWarehouseApproverAccess();
            $this->decideMrpRunRequest(false);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=warehouse&action=mrpApproval');
        exit;
    }

    // JSON hook for the supplier dropdown — reads WarehouseModel::getSuppliers(),
    // so switching to the suppliers table later means editing only that method.
    public function getSuppliers() {
        header('Content-Type: application/json');
        try {
            echo json_encode(['success' => true, 'suppliers' => $this->warehouseModel->getSuppliers()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // JSON item search (code + description) for the Purchasing PO cart lines.
    // Non-empty q: LIKE search (code or description), 20 hits. Empty q: browse
    // mode — first 50 codes so the dropdown can list items on field focus.
    public function searchItems() {
        header('Content-Type: application/json');
        try {
            $query = trim($_GET['q'] ?? '');
            echo json_encode($this->catalogModel->searchItems($query));
        } catch (\Exception $e) {
            error_log('warehouse searchItems error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to search items']);
        }
        exit;
    }

    public function createPurchasingPo() {
        $this->enforceReadOnlyPurchasingPo();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=warehouse&action=purchasingPo');
            exit;
        }
        try {
            // Cart save: lines arrive as a JSON array (supplier + item per
            // line, so one PO may span several suppliers). Falls back to the
            // legacy single-item POST shape for safety.
            $lines = json_decode($_POST['lines'] ?? '[]', true);
            if (!is_array($lines) || !count($lines)) {
                $single = [
                    'supplier_name' => trim($_POST['supplier_name'] ?? ''),
                    'item_id' => intval($_POST['item_id'] ?? 0),
                    'quantity' => floatval($_POST['quantity'] ?? 0),
                    'unit_cost' => floatval($_POST['unit_cost'] ?? 0),
                ];
                $lines = ($single['item_id'] > 0) ? [$single] : [];
            }

            $clean = [];
            foreach ($lines as $i => $line) {
                $supplier = trim($line['supplier_name'] ?? '');
                $itemId = intval($line['item_id'] ?? 0);
                $quantity = floatval($line['quantity'] ?? 0);
                $unitCost = floatval($line['unit_cost'] ?? 0);
                if ($supplier === '' || $itemId <= 0 || $quantity <= 0) {
                    throw new \RuntimeException('Line ' . ($i + 1) . ': supplier, item, and quantity are required.');
                }
                $clean[] = [
                    'supplier_name' => $supplier,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ];
            }
            if (!count($clean)) {
                throw new \RuntimeException('Add at least one line (supplier, item, quantity) to the PO.');
            }

            $result = $this->warehouseModel->createManualPoGroup([
                'order_date' => $_POST['order_date'] ?: null,
                'expected_date' => $_POST['expected_date'] ?: null,
                'remarks' => trim($_POST['remarks'] ?? '') ?: null,
                'po_ref' => trim($_POST['po_ref'] ?? '') ?: null,
                'created_by' => $_SESSION['user_id'],
            ], $clean);

            $n = (int) $result['line_count'];
            $audit = 'Purchasing PO created - group #' . $result['po_group_id'] . ' - ' . $n
                . ' line(s) awaiting WHSE Admin approval.';
            $_SESSION['success'] = $audit;
            AuditModel::log($_SESSION['user_id'], 'CREATE', 'warehouse', $audit,
                null, ['status' => 'pending_whse_approval', 'po_group_id' => $result['po_group_id']],
                'supplier_orders', null);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=warehouse&action=purchasingPo');
        exit;
    }

    /**
     * WHSE Admin gate for manual (non-MRP) PO groups: forwards every line of
     * the group to System Admin approval (pending_admin_approval).
     */
    public function approveManualPo() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \RuntimeException('POST required.');
            }
            $this->enforceWarehouseApproverAccess();
            $id = intval($_POST['supplier_order_id'] ?? 0);
            if ($id <= 0) throw new \RuntimeException('Invalid PO.');
            $label = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'unknown') . ' (' . ($_SESSION['department'] ?? '') . ')';
            $result = $this->warehouseModel->approveManualPoGroup($id, $label);
            $audit = 'Purchasing PO group #' . $result['po_group_id']
                . ' approved at the WHSE Admin stage - ' . $result['moved']
                . ' line(s) now awaiting System Admin approval.';
            $_SESSION['success'] = $audit;
            AuditModel::log($_SESSION['user_id'], 'APPROVE', 'warehouse', $audit,
                ['status' => 'pending_whse_approval'], ['status' => 'pending_admin_approval'],
                'po_group', $result['po_group_id']);
            NotificationHelper::create('po', 'Purchasing PO Awaiting System Admin',
                'Group #' . $result['po_group_id'] . ' - ' . $result['moved']
                . ' PO line(s) passed WHSE Admin approval; System Admin approval required.',
                'admin', '?controller=admin&action=approvals', $_SESSION['user_id'] ?? null);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=warehouse&action=purchasingPo');
        exit;
    }

    /**
     * WHSE Admin reject for manual (non-MRP) PO groups: cancels every line
     * of the group with the rejection reason.
     */
    public function rejectManualPo() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \RuntimeException('POST required.');
            }
            $this->enforceWarehouseApproverAccess();
            $id = intval($_POST['supplier_order_id'] ?? 0);
            if ($id <= 0) throw new \RuntimeException('Invalid PO.');
            $reason = trim($_POST['reject_reason'] ?? '');
            if ($reason === '') {
                throw new \RuntimeException('A rejection reason is required.');
            }
            $label = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'unknown') . ' (' . ($_SESSION['department'] ?? '') . ')';
            $result = $this->warehouseModel->rejectManualPoGroup($id, $reason, $label);
            $audit = 'Purchasing PO group #' . $result['po_group_id']
                . ' rejected at the WHSE Admin stage - ' . $result['cancelled']
                . ' line(s) cancelled. Reason: ' . $reason;
            $_SESSION['success'] = $audit;
            AuditModel::log($_SESSION['user_id'], 'REJECT', 'warehouse', $audit,
                ['status' => 'pending_whse_approval'], ['status' => 'cancelled'],
                'po_group', $result['po_group_id']);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=warehouse&action=purchasingPo');
        exit;
    }

    /**
     * Printable Purchasing PO (standalone page + browser Print). Works for
     * grouped lines (whole group) and single rows (MRP / legacy / received).
     */
    public function printPurchasingPo() {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ?controller=warehouse&action=purchasingPo');
            exit;
        }
        $lines = $this->warehouseModel->getPoGroupForPrint($id);
        if (!$lines) {
            $_SESSION['error'] = 'Purchasing PO not found.';
            header('Location: ?controller=warehouse&action=purchasingPo');
            exit;
        }
        $first = $lines[0];
        $pageTitle = 'Purchasing Order' . ($first['po_ref'] ? ' — ' . $first['po_ref'] : ' — Group #' . ($first['po_group_id'] ?: $first['supplier_order_id']));
        include __DIR__ . "/../views/purchasingPo/print.php";
        exit;
    }

    public function processPurchasingPo() {
        $this->enforceReadOnlyPurchasingPo();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=warehouse&action=purchasingPo');
            exit;
        }
        try {
            $id = intval($_POST['supplier_order_id'] ?? 0);
            if ($id <= 0) throw new \RuntimeException('Invalid order.');

            $supplierName = trim($_POST['supplier_name'] ?? '');
            $quantity = floatval($_POST['quantity'] ?? 0);
            if (empty($supplierName) || $quantity <= 0) {
                throw new \RuntimeException('Supplier name and quantity are required.');
            }

            $this->warehouseModel->processPurchasingPo($id, [
                'supplier_name' => $supplierName,
                'quantity' => $quantity,
                'unit_cost' => floatval($_POST['unit_cost'] ?? 0),
                'order_date' => $_POST['order_date'] ?: null,
                'expected_date' => $_POST['expected_date'] ?: null,
                'remarks' => trim($_POST['remarks'] ?? '') ?: null,
                'po_ref' => trim($_POST['po_ref'] ?? '') ?: null,
                'po_id' => !empty($_POST['po_id']) ? intval($_POST['po_id']) : null,
            ]);

            $_SESSION['success'] = 'Purchasing PO processed — saved as a PO draft awaiting WHSE Admin approval, then System Admin approval. It becomes Active for receiving after both.';
            try {
                NotificationHelper::create('po', 'PO Draft Awaiting WHSE Admin Approval',
                    'Procurement saved PO draft ' . (trim($_POST['po_ref'] ?? '') ?: ('#' . $id))
                    . ' (' . $supplierName . ' — ' . formatQty($quantity) . ') which needs WHSE Admin approval first, then System Admin, before Receiving.',
                    'warehouse', '?controller=warehouse&action=purchasingPo&tab=approval', $_SESSION['user_id'] ?? null);
            } catch (\Exception $e) {
                error_log('poDraft notification error: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=warehouse&action=purchasingPo');
        exit;
    }

    /**
     * Group Process for an MRP source: one modal submit moves EVERY requested
     * line of the run to pending_admin_approval (System Admin), each line with
     * its own supplier/qty/cost and the shared header (dates + one PO REF).
     */
    public function processMrpGroupPo() {
        $this->enforceReadOnlyPurchasingPo();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=warehouse&action=purchasingPo');
            exit;
        }
        try {
            $runId = intval($_POST['mrp_run_id'] ?? 0);
            if ($runId <= 0) throw new \RuntimeException('Invalid MRP run.');

            $lines = json_decode($_POST['lines'] ?? '[]', true);
            if (!is_array($lines) || !count($lines)) {
                throw new \RuntimeException('Add at least one line (supplier, quantity) to process.');
            }
            $clean = [];
            foreach ($lines as $i => $line) {
                $id = intval($line['supplier_order_id'] ?? 0);
                $supplier = trim($line['supplier_name'] ?? '');
                $quantity = floatval($line['quantity'] ?? 0);
                if ($id <= 0 || $supplier === '' || $quantity <= 0) {
                    throw new \RuntimeException('Line ' . ($i + 1) . ': supplier and quantity are required.');
                }
                $clean[] = [
                    'supplier_order_id' => $id,
                    'supplier_name' => $supplier,
                    'quantity' => $quantity,
                    'unit_cost' => floatval($line['unit_cost'] ?? 0),
                ];
            }

            $moved = $this->warehouseModel->processMrpPoGroup($runId, [
                'order_date' => $_POST['order_date'] ?: null,
                'expected_date' => $_POST['expected_date'] ?: null,
                'po_ref' => trim($_POST['po_ref'] ?? '') ?: null,
            ], $clean);

            $audit = 'MRP source processed as a group - run #' . $runId . ' - ' . $moved
                . ' line(s) saved as pending_whse_approval drafts (WHSE Admin gate first).';
            $_SESSION['success'] = $moved . ' line(s) processed for MRP run #' . $runId
                . ' — saved as PO drafts awaiting WHSE Admin approval, then System Admin.';
            AuditModel::log($_SESSION['user_id'], 'PROCESS', 'warehouse', $audit,
                ['status' => 'requested'], ['status' => 'pending_whse_approval', 'mrp_run_id' => $runId],
                'mrp_run', $runId);
            try {
                NotificationHelper::create('po', 'MRP PO Drafts Awaiting WHSE Admin',
                    'MRP run #' . $runId . ' — ' . $moved
                    . ' PO draft line(s) saved by Procurement; WHSE Admin approval required first, then System Admin, before Receiving.',
                    'warehouse', '?controller=warehouse&action=purchasingPo&tab=approval', $_SESSION['user_id'] ?? null);
            } catch (\Exception $e) {
                error_log('mrpGroup notification error: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=warehouse&action=purchasingPo&tab=approval');
        exit;
    }

    public function batchProcessPurchasingPo() {
        $this->enforceReadOnlyPurchasingPo();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=warehouse&action=purchasingPo');
            exit;
        }
        try {
            $orderIds = array_filter(array_map('intval', explode(',', $_POST['order_ids'] ?? '')));
            if (empty($orderIds)) {
                throw new \RuntimeException('No orders selected.');
            }

            $supplierName = trim($_POST['supplier_name'] ?? '');
            if (empty($supplierName)) {
                throw new \RuntimeException('Supplier name is required.');
            }

            $result = $this->warehouseModel->batchProcessPurchasingPo($orderIds, [
                'supplier_name' => $supplierName,
                'unit_cost' => floatval($_POST['unit_cost'] ?? 0),
                'order_date' => $_POST['order_date'] ?: null,
                'expected_date' => $_POST['expected_date'] ?: null,
            ]);

          $_SESSION['success'] = count($orderIds) . ' requisition(s) consolidated into PO #'
            . $result['consolidated_id'] . ' (total qty '
            . formatQty((float) $result['total_qty']) . ') - PENDING WHSE ADMIN APPROVAL first, then System Admin, before it becomes Active for receiving.';
          try {
            NotificationHelper::create('po', 'PO Draft Awaiting WHSE Admin Approval',
              'Consolidated PO draft #' . $result['consolidated_id'] . ' (' . $supplierName . ' - '
              . formatQty((float) $result['total_qty']) . ') needs WHSE Admin approval first, then System Admin, before Receiving.',
              'warehouse', '?controller=warehouse&action=purchasingPo&tab=approval', $_SESSION['user_id'] ?? null);
          } catch (\Exception $e) {
            error_log('poDraft notification error: ' . $e->getMessage());
          }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=warehouse&action=purchasingPo');
        exit;
    }

    public function cancelPurchasingPo() {
        $this->enforceReadOnlyPurchasingPo();
        $id = intval($_GET['id'] ?? 0);
        try {
            if ($id <= 0) {
                throw new \RuntimeException('Invalid purchasing PO.');
            }
            $this->warehouseModel->cancelPurchasingPo($id);
            $_SESSION['success'] = 'Purchasing PO cancelled. Any pending QC inspection entries were cleared from the queue.';
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=warehouse&action=purchasingPo');
        exit;
    }

    /**
     * Edit Purchasing PO: GET returns the raw row as JSON for the edit modal;
     * POST validates and saves typo corrections (Qty, Supplier, Unit Cost,
     * dates, remarks). Only requested/pending rows with nothing received.
     */
    public function updatePurchasingPo() {
        // Edit is metadata-only (supplier/qty/cost/dates/remarks) and is
        // shared with QC for post-inspection corrections.
        $dept = $_SESSION['department'] ?? '';
        if (!in_array($dept, ['warehouse', 'qc'], true)) {
            $_SESSION['error'] = 'Editing purchasing PO records is not permitted for this role.';
            $this->redirectToRoleHome('purchasingPo');
        }
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            header('Content-Type: application/json');
            $row = $id > 0 ? $this->warehouseModel->getPurchasingPoForEdit($id) : false;
            if (!$row) {
                echo json_encode(['success' => false, 'error' => 'Purchasing PO not found.']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $row]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=warehouse&action=purchasingPo');
            exit;
        }

        header('Content-Type: application/json');
        try {
            $id = intval($_POST['supplier_order_id'] ?? ($id ?: 0));
            if ($id <= 0) {
                throw new \RuntimeException('Invalid purchasing PO.');
            }
            $data = [
                'supplier_name' => trim($_POST['supplier_name'] ?? ''),
                'quantity' => floatval($_POST['quantity'] ?? 0),
                'unit_cost' => floatval($_POST['unit_cost'] ?? 0),
                'order_date' => trim($_POST['order_date'] ?? ''),
                'expected_date' => trim($_POST['expected_date'] ?? ''),
                'remarks' => trim($_POST['remarks'] ?? ''),
            ];
            if ($data['supplier_name'] === '') {
                throw new \RuntimeException('Supplier name is required.');
            }
            if ($data['quantity'] <= 0) {
                throw new \RuntimeException('Quantity must be greater than zero.');
            }
            $this->warehouseModel->updatePurchasingPo($id, $data);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Edit a staged (For Inspection) receiving batch: received qty, lot number,
     * expiry date and remarks on the receiving_items row. GET returns JSON for
     * the staging edit modal; POST saves.
     */
    public function updateReceivingStaging() {
        $dept = $_SESSION['department'] ?? '';
        if (!in_array($dept, ['warehouse', 'qc'], true)) {
            echo json_encode(['success' => false, 'error' => 'Editing staged receipts is not permitted for this role.']);
            exit;
        }
        $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            header('Content-Type: application/json');
            $row = $id > 0 ? $this->warehouseModel->getReceivingStagingForEdit($id) : false;
            if (!$row) {
                echo json_encode(['success' => false, 'error' => 'No staged receipt awaiting inspection for this order.']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $row]);
            exit;
        }

        header('Content-Type: application/json');
        try {
            if ($id <= 0) {
                throw new \RuntimeException('Invalid staged receipt.');
            }
            $data = [
                'received_qty' => floatval($_POST['received_qty'] ?? 0),
                'lot_number' => trim($_POST['lot_number'] ?? ''),
                'expiry_date' => trim($_POST['expiry_date'] ?? ''),
                'remarks' => trim($_POST['remarks'] ?? ''),
            ];
            $this->warehouseModel->updateReceivingStaging($id, $data);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function deletePurchasingPo() {
        $this->enforceReadOnlyPurchasingPo();
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0 && $this->warehouseModel->deletePurchasingPo($id)) {
            $_SESSION['success'] = 'Purchasing PO deleted.';
        } else {
            $_SESSION['error'] = 'Failed to delete purchasing PO.';
        }
        header('Location: ?controller=warehouse&action=purchasingPo');
        exit;
    }

    // Read-only JSON payload for the "View Details" modal (Purchasing / Receiving).
    public function getPoFullDetails() {
        header('Content-Type: application/json');
        $id = intval($_GET['id'] ?? 0);
        $data = $id > 0 ? $this->warehouseModel->getPoFullDetails($id) : false;
        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Purchasing PO not found.']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    // ─── Receiving Purchasing PO ────────────────────────────────────────────────

    public function receivingPo() {
        $filters = [
            'tab' => $_GET['tab'] ?? '',
            'status' => $_GET['status'] ?? '',
            'supplier' => $_GET['supplier'] ?? '',
            'search' => $_GET['search'] ?? '',
        ];
        $orders = $this->warehouseModel->getReceivingPoFiltered($filters);
        $suppliers = $this->warehouseModel->getPurchasingPoSuppliers();

        $data['page_title'] = 'Receiving Purchasing PO';
        $data['orders'] = $orders;
        $data['filters'] = $filters;
        $data['suppliers'] = $suppliers;
        $data['tabCounts'] = $this->warehouseModel->getReceivingTabCounts();
        $data['readOnly'] = (($_SESSION['department'] ?? '') !== 'warehouse');
        $this->render('receivingPo/index', $data);
    }

    public function receivePurchasingPo() {
        $this->enforceWarehouseReceivingAccess();
        $id = intval($_GET['id'] ?? $_POST['supplier_order_id'] ?? 0);
        if ($id <= 0) {
            header('Location: ?controller=warehouse&action=receivingPo');
            exit;
        }
        $order = $this->warehouseModel->getPurchasingPoById($id);
        if (!$order) {
            $_SESSION['error'] = 'Purchasing PO not found.';
            header('Location: ?controller=warehouse&action=receivingPo');
            exit;
        }

        $orderStatus = strtolower(trim((string) ($order['status'] ?? '')));
        if ($orderStatus === 'requested') {
            $_SESSION['error'] = 'This purchasing order is still in Requested status. Purchasing must process it before warehouse receiving can proceed.';
            header('Location: ?controller=warehouse&action=receivingPo');
            exit;
        }

        // Receiving is allowed whenever a balance remains on the line — including
        // orders sitting in 'For Inspection' or 'partially_received' (multi-batch
        // partial receiving), and lines whose overage was approved by the System
        // Admin (excess beyond the ordered total may still come in). Only
        // deliberately-locked statuses and a fully exhausted receiving cap.
        $remainingBefore = floatval($order['quantity'] ?? 0) - floatval($order['received_qty'] ?? 0);
        $overageApproved = (string) ($order['overage_request_status'] ?? '') === 'approved';
        $overageLeft = $overageApproved
            ? floatval($order['overage_approved_qty'] ?? 0) - max(0, -1 * $remainingBefore)
            : 0.0;

        if (in_array($orderStatus, ['cancelled', 'rejected'])) {
            $_SESSION['error'] = 'Cannot receive a purchasing PO with status "' . ucfirst($order['status'] ?? 'Unknown') . '".';
            header('Location: ?controller=warehouse&action=receivingPo');
            exit;
        }

        if (($remainingBefore <= 0.0001 && $overageLeft <= 0.0001) || $orderStatus === 'received') {
            $_SESSION['error'] = 'This purchasing PO has already been fully received.';
            header('Location: ?controller=warehouse&action=receivingPo');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // One-shot receipt token (regenerated each time the modal opens):
            // a re-POST of an already-recorded receipt (double-click, back
            // button + resubmit) is dropped here instead of being applied twice.
            // A fresh modal open mints a new token, so a genuine second batch
            // while the first is still in QC proceeds normally.
            $receiptToken = trim($_POST['receipt_token'] ?? '');
            if ($receiptToken !== '' && isset($_SESSION['used_receipt_tokens'][$receiptToken])) {
                $_SESSION['success'] = 'This receipt was already recorded. No quantities were applied again.';
                header('Location: ?controller=warehouse&action=receivingPo');
                exit;
            }

            try {
                if ($receiptToken === '') {
                    throw new \RuntimeException('Missing receipt token — please close and reopen the Receive Shipment dialog.');
                }
                $receivedQty = floatval($_POST['received_qty'] ?? 0);
                if ($receivedQty <= 0) {
                    throw new \RuntimeException('Received quantity must be greater than zero.');
                }

                $lotNumber = trim($_POST['lot_number'] ?? '') ?: null;
                $expiryDate = $_POST['expiry_date'] ?: null;
                $receivedDate = $_POST['received_date'] ?: date('Y-m-d');
                $remarks = trim($_POST['remarks'] ?? '') ?: null;

                // Lot / Batch Number is required for RM, FG and SFG receipts;
                // PM / Supplies receive without a lot or expiry.
                $itemType = strtoupper(trim((string) ($order['item_type'] ?? '')));
                if (in_array($itemType, ['RM', 'FG', 'SFG'], true) && $lotNumber === null) {
                    throw new \RuntimeException('Lot / Batch Number is required for ' . $itemType . ' receipts.');
                }

                // Overage guardrail: exact / partial shipments pass straight
                // through to QC. Anything beyond the ordered total — unless the
                // excess is already covered by an approved overage — is blocked;
                // the attempt is logged as a pending System Admin overage
                // request and NOTHING is staged to QC until it is approved.
                $orderedQty = floatval($order['quantity'] ?? 0);
                $currentReceived = floatval($order['received_qty'] ?? 0);
                $hasApprovedOver = (string) ($order['overage_request_status'] ?? '') === 'approved';
                $approvedOver = $hasApprovedOver ? floatval($order['overage_approved_qty'] ?? 0) : 0.0;
                if ($currentReceived + $receivedQty > $orderedQty + $approvedOver + 0.0001) {
                    $overQty = round(($currentReceived + $receivedQty) - $orderedQty, 4);
                    $this->warehouseModel->requestOverageApproval($id, $overQty);
                    AuditModel::log($_SESSION['user_id'], 'CREATE', 'warehouse',
                        'Over-receipt submitted on order #' . $id . ' item ' . ($order['item_code'] ?? '')
                        . ' — exceeding ordered ' . formatQty($orderedQty) . ' by ' . formatQty($overQty)
                        . ' (' . ($order['overage_request_status'] === 'pending' ? 'request refreshed, still pending' : 'request now pending') . ' System Admin approval)',
                        null, ['overage_request_qty' => $overQty], 'supplier_order', $id);
                    NotificationHelper::create('po', 'Over-Receipt Approval Needed',
                        ($order['po_ref'] ?: ('SO #' . $id)) . ' — shipment of ' . formatQty($receivedQty)
                        . ' ' . ($order['item_code'] ?? '') . ' exceeds the ordered ' . formatQty($orderedQty)
                        . ' by ' . formatQty($overQty) . '. System Admin approval required before it can reach QC.',
                        'admin', '?controller=admin&action=receivingPo&tab=overage');
                    $_SESSION['error'] = 'Over-receipt of ' . formatQty($overQty)
                        . ' beyond the ordered ' . formatQty($orderedQty) . ' requires System Admin approval. '
                        . 'The approval request has been submitted — this receipt was NOT staged for QC. Retry once approved (In-Transit badge shows the approval state).';
                    header('Location: ?controller=warehouse&action=receivingPo');
                    exit;
                }

                $result = $this->warehouseModel->receivePurchasingPo($id, [
                    'received_qty' => $receivedQty,
                    'lot_number' => $lotNumber,
                    'expiry_date' => $expiryDate,
                    'received_date' => $receivedDate,
                    'remarks' => $remarks,
                    'received_by' => $_SESSION['user_id']
                ]);

                // Consume the token only once the receipt is actually recorded,
                // so a failed submission can be corrected and re-sent.
                $_SESSION['used_receipt_tokens'][$receiptToken] = time();
                if (count($_SESSION['used_receipt_tokens']) > 50) {
                    $_SESSION['used_receipt_tokens'] = array_slice($_SESSION['used_receipt_tokens'], -50, null, true);
                }

                $this->notifyProcurementOfReceipt($order, $receivedQty, $result['po_ref'] ?? null);
                try {
                    $poRef = $result['po_ref'] ?? ('SO #' . $id);
                    NotificationHelper::qcInspectionNeeded($poRef, $lotNumber ?: ($order['item_code'] ?? 'N/A'), $_SESSION['user_id'] ?? null);
                } catch (\Exception $e) {
                    error_log('qcInspectionNeeded error: ' . $e->getMessage());
                }

                $newStatus = $result['status'] ?? 'For Inspection';
                $_SESSION['success'] = $newStatus === 'For Inspection'
                    ? 'Shipment received and staged for QC inspection (Status: For Inspection).'
                    : 'Shipment received (Status: Partially Received). Remaining balance is still open for another batch; staged for QC inspection.';
                header('Location: ?controller=warehouse&action=receivingPo');
                exit;
            } catch (\Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }

        header('Location: ?controller=warehouse&action=receivingPo&receive=' . $id);
        exit;
    }

    private function notifyProcurementOfReceipt($order, $receivedQty, $poRef = null) {
        try {
            $poRef = $poRef ?: ('SO #' . $order['supplier_order_id']);
            $itemDesc = $order['item_description'] ?? '';
            $supplier = $order['supplier_name'] ?? 'Unknown Supplier';
            $newReceived = floatval($order['received_qty'] ?? 0) + floatval($receivedQty);
            $status = $newReceived >= floatval($order['quantity']) ? 'fully received' : 'partially received';

            $message = "Purchasing PO {$poRef} ({$itemDesc}) from {$supplier} has been {$status}. Received qty: " . formatQty($receivedQty);

            foreach (['admin', 'finance'] as $department) {
                NotificationHelper::create(
                    'receipt',
                    'Purchasing PO Received',
                    $message,
                    $department,
                    '?controller=warehouse&action=receivingPo',
                    $_SESSION['user_id'] ?? null
                );
            }
        } catch (\Exception $e) {
            error_log('notifyProcurementOfReceipt error: ' . $e->getMessage());
        }
    }

    // ─── MRP Snapshots ────────────────────────────────────────────────────────

    public function saveMrpSnapshot() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=warehouse&action=mrp');
            exit;
        }
        try {
            $poId = intval($_POST['po_id'] ?? 0);
            $customerId = intval($_POST['customer_id'] ?? 0);
            if ($poId <= 0 || $customerId <= 0) {
                throw new \RuntimeException('Invalid PO or Customer.');
            }

            $existingRun = $this->warehouseModel->getExistingMrpRunForPO($poId);
            if ($existingRun) {
                $linkedOrders = $this->warehouseModel->getLinkedSupplierOrdersForRun($existingRun['run_id']);
                $hasLockedOrders = false;
                foreach ($linkedOrders as $order) {
                    if (in_array($order['status'], ['pending', 'received', 'completed'])) {
                        $hasLockedOrders = true;
                        break;
                    }
                }
                if ($hasLockedOrders) {
                    $_SESSION['error'] = 'Cannot recalculate MRP. Procurement POs for this snapshot have already been processed. Delete the existing snapshot first.';
                    header("Location: ?controller=warehouse&action=mrp&customer_id={$customerId}&po_id={$poId}");
                    exit;
                }
                $this->warehouseModel->cancelLinkedSupplierOrdersForRun($existingRun['run_id']);
            }

            $allPoItems = $this->warehouseModel->getPOItemsWithBOM($poId);
            $bomIds = [];
            $poiIds = [];
            foreach ($allPoItems as $poi) {
                if (!empty($poi['bom_id'])) $bomIds[] = $poi['bom_id'];
                $poiIds[] = $poi['poi_id'];
            }

            $allComponents = $this->warehouseModel->getBOMComponentsWithStock($bomIds);
            $componentsByBom = [];
            $allIngredientIds = [];
            foreach ($allComponents as $comp) {
                $componentsByBom[$comp['bom_id']][] = $comp;
                $allIngredientIds[$comp['component_item_id']] = true;
            }

            $pendingAllocations = $this->warehouseModel->getPendingAllocationsAcrossPOs($poiIds, array_keys($allIngredientIds));
            $pendingSupplierOrders = $this->warehouseModel->getPendingSupplierOrdersForItems(array_keys($allIngredientIds), $poId);

            $sections = [];
            foreach ($allPoItems as $poi) {
                if (empty($poi['bom_id'])) continue;
                $components = $componentsByBom[$poi['bom_id']] ?? [];
                $rows = [];
                foreach ($components as $comp) {
                    $totalReqt = $this->computeMrpRequiredQty(floatval($poi['quantity']), $poi, $comp);
                    $soh = floatval($comp['soh']);
                    $allocated = floatval($pendingAllocations[$comp['component_item_id']] ?? 0);
                    $supplierPending = floatval($pendingSupplierOrders[$comp['component_item_id']] ?? 0);
                    $available = $soh - $allocated;
                    $excess = $available - $totalReqt + $supplierPending;

                    if ($totalReqt == 0) $remarks = 'NO NEED';
                    elseif ($available <= 0 && $supplierPending == 0) $remarks = 'NO stock for next order mfg';
                    elseif ($excess < 0) $remarks = 'LACKING';
                    elseif ($excess < ($available * 0.1)) $remarks = 'LOW STOCK';
                    else $remarks = 'OK';

                    $rows[] = [
                        'component_item_id' => $comp['component_item_id'],
                        'display_uom' => $comp['display_uom'] ?? $comp['item_uom'],
                        'total_reqt' => $totalReqt,
                        'soh' => $soh,
                        'allocated' => $allocated,
                        'pending' => $allocated + $supplierPending,
                        'excess' => $excess,
                        'remarks' => $remarks,
                    ];
                }
                $sections[] = [
                    'fg_item_id' => $poi['item_id'],
                    'components' => $rows,
                ];
            }

            $runId = $this->warehouseModel->saveMrpRun($poId, $customerId, $_SESSION['user_id'], $sections, []);

            $lackingItems = [];
            $lackingUom = [];
            foreach ($sections as $sec) {
                foreach ($sec['components'] as $comp) {
                    if ($comp['excess'] < 0) {
                        $itemId = $comp['component_item_id'];
                        $lackingItems[$itemId] = ($lackingItems[$itemId] ?? 0) + abs($comp['excess']);
                        if (!isset($lackingUom[$itemId])) {
                            $lackingUom[$itemId] = $comp['display_uom'] ?? null;
                        }
                    }
                }
            }

            if (!empty($lackingItems)) {
                $existingRequested = $this->warehouseModel->getExistingRequestedOrders(array_keys($lackingItems));
                foreach ($lackingItems as $itemId => $qty) {
                    if (isset($existingRequested[$itemId])) continue;
                    $this->warehouseModel->createSupplierOrder([
                        'supplier_name' => 'Pending Selection',
                        'item_id' => $itemId,
                        'quantity' => $qty,
                        'uom' => $lackingUom[$itemId] ?? null,
                        'unit_cost' => 0,
                        'order_date' => date('Y-m-d'),
                        'expected_date' => null,
                        'remarks' => 'Auto-generated from MRP Run #' . $runId,
                        'created_by' => $_SESSION['user_id'],
                        'status' => 'requested',
                        'po_id' => $poId,
                        'mrp_run_id' => $runId,
                        'mrp_ref' => 'MRP-#' . $runId,
                    ]);
                }
            }

            $_SESSION['success'] = "MRP snapshot saved (Run #{$runId}).";
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header("Location: ?controller=warehouse&action=mrp&customer_id={$customerId}&po_id={$poId}");
        exit;
    }

    public function mrpHistory() {
        $poId = intval($_GET['po_id'] ?? 0);
        $data['page_title'] = 'MRP Snapshot History';
        $data['selectedPO'] = $poId ?: null;

        if ($poId > 0) {
            $data['poHeader'] = $this->warehouseModel->getPurchaseOrderById($poId);
            $data['runs'] = $this->warehouseModel->getMrpRunsByPO($poId);
        } else {
            $data['poHeader'] = null;
            $data['runs'] = $this->warehouseModel->getAllMrpRuns();
        }

        $this->render('mrp/history', $data);
    }

    public function mrpSnapshotPDF() {
        $runId = intval($_GET['run_id'] ?? 0);
        if ($runId <= 0) {
            header('Location: ?controller=warehouse&action=mrpHistory');
            exit;
        }

        $snapshotData = $this->warehouseModel->getMrpRunSnapshotData($runId);
        if (!$snapshotData) {
            $_SESSION['error'] = 'MRP snapshot not found.';
            header('Location: ?controller=warehouse&action=mrpHistory');
            exit;
        }

        $data = $snapshotData;
        extract($data);

        ob_start();
        include __DIR__ . "/../views/mrp/pdf_template.php";
        $html = ob_get_clean();

        require_once __DIR__ . '/../../public/vendor/dompdf/autoload.inc.php';
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        // PO-based runs print with the PO number; FG-based runs (no po_id)
        // fall back to the MRP reference / FG code.
        $poNumber = $data['po']['customer_po_number'] ?? ('MRP-' . $runId);
        $poNumber = preg_replace('/[^A-Za-z0-9_\-]/', '', $poNumber) ?: ('MRP-' . $runId);
        $snapDate = date('Ymd', strtotime($data['snapshotDate']));
        $dompdf->stream("MRP_{$poNumber}_{$snapDate}.pdf", ['Attachment' => false]);
        exit;
    }

    /**
     * Printable snapshot view (History printer icon): standalone page with an
     * editable Remarks column — user edits remarks, then browser-prints.
     * Remarks are print-only; the frozen snapshot is never modified.
     */
    public function mrpSnapshotPrint() {
        $runId = intval($_GET['run_id'] ?? 0);
        if ($runId <= 0) {
            header('Location: ?controller=warehouse&action=mrpHistory');
            exit;
        }

        $snapshotData = $this->warehouseModel->getMrpRunSnapshotData($runId);
        if (!$snapshotData) {
            $_SESSION['error'] = 'MRP snapshot not found.';
            header('Location: ?controller=warehouse&action=mrpHistory');
            exit;
        }

        extract($snapshotData);
        include __DIR__ . "/../views/mrp/snapshot_print.php";
        exit;
    }

    public function mrpRunDetail() {
        $runId = intval($_GET['run_id'] ?? 0);
        if ($runId <= 0) {
            header('Location: ?controller=warehouse&action=mrp');
            exit;
        }
        // Values come straight from the frozen mrp_run_items columns (never live
        // inventory); formatQty() is applied here so the modal renders clean
        // quantities while the PDF/delete callers keep the raw numbers.
        $items = $this->warehouseModel->getMrpRunItems($runId);
        foreach ($items as &$item) {
            $item['total_reqt'] = formatQty($item['total_reqt'] ?? 0);
            $item['soh']        = formatQty($item['soh'] ?? 0);
            $item['allocated']  = formatQty($item['allocated'] ?? 0);
            $item['pending']    = formatQty($item['pending'] ?? 0);
            $item['excess']     = formatQty($item['excess'] ?? 0);
        }
        unset($item);
        header('Content-Type: application/json');
        echo json_encode($items);
        exit;
    }

    public function deleteMrpRun() {
        $runId = intval($_GET['run_id'] ?? 0);
        $poId = intval($_GET['po_id'] ?? 0);
        if ($runId > 0) {
            $result = $this->warehouseModel->deleteMrpRun($runId);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'] ?? 'MRP snapshot deleted.';
            } else {
                $_SESSION['error'] = $result['message'];
            }
        }
        $redirect = "Location: ?controller=warehouse&action=mrpHistory" . ($poId > 0 ? "&po_id={$poId}" : '');
        header($redirect);
        exit;
    }

    // ─── Backward Compatibility (Procurement PO → Purchasing PO) ────────────────

    public function supplierOrders() {
        header('Location: ?controller=warehouse&action=purchasingPo');
        exit;
    }

    public function createSupplierOrder() {
        header('Location: ?controller=warehouse&action=createPurchasingPo');
        exit;
    }

    public function receiveSupplierOrder() {
        header('Location: ?controller=warehouse&action=receivingPo');
        exit;
    }

    public function cancelSupplierOrder() {
        header('Location: ?controller=warehouse&action=cancelPurchasingPo&id=' . ($_GET['id'] ?? ''));
        exit;
    }

    public function deleteSupplierOrder() {
        header('Location: ?controller=warehouse&action=deletePurchasingPo&id=' . ($_GET['id'] ?? ''));
        exit;
    }

    public function processSupplierOrder() {
        header('Location: ?controller=warehouse&action=processPurchasingPo');
        exit;
    }

    public function batchProcessSupplierOrders() {
        header('Location: ?controller=warehouse&action=batchProcessPurchasingPo');
        exit;
    }

    // ─── Item Master List & ERIC Import ──────────────────────────────────────

    public function items() {
        $search = trim($_GET['search'] ?? '');
        $typeFilter = trim($_GET['item_type'] ?? '');
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;
        $perPage = 20;

        $filters = [];
        if ($search !== '') $filters['search'] = $search;
        if ($typeFilter !== '' && $typeFilter !== 'All Types') $filters['item_type'] = $typeFilter;

        $total = $this->itemImportModel->countWithStock($filters);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $data['items'] = $this->itemImportModel->getAllWithStock($filters, $perPage, $offset);
        $data['page'] = $page;
        $data['totalPages'] = $totalPages;
        $data['total'] = $total;
        $data['search'] = $search;
        $data['typeFilter'] = $typeFilter;
        $data['canEdit'] = (($_SESSION['department'] ?? '') === 'admin');
        $data['page_title'] = 'Item Master List';
        $this->render('items/index', $data);
    }

    public function storeItem() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=warehouse&action=items');
            exit;
        }
        if (($_SESSION['department'] ?? '') !== 'admin') {
            $_SESSION['error'] = 'Only System Admins can create master items.';
            header('Location: ?controller=warehouse&action=items');
            exit;
        }

        $code = trim($_POST['code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type = strtoupper(trim($_POST['type'] ?? ''));
        $uom = trim($_POST['uom'] ?? '');
        $soh = $_POST['soh'] ?? 0;

        if ($code === '' || $description === '' || $uom === '') {
            $_SESSION['error'] = 'Item code, description and UOM are required.';
            header('Location: ?controller=warehouse&action=items');
            exit;
        }
        if (!in_array($type, ['RM', 'PM', 'FG', 'SFG', 'SUPPLIES'], true)) {
            $_SESSION['error'] = 'Invalid item type.';
            header('Location: ?controller=warehouse&action=items');
            exit;
        }
        if (!is_numeric($soh) || (float)$soh < 0) {
            $_SESSION['error'] = 'Initial SOH must be a number greater than or equal to 0.';
            header('Location: ?controller=warehouse&action=items');
            exit;
        }
        $soh = round((float)$soh, 4);

        try {
            $itemId = $this->itemImportModel->createItem([
                'item_code' => $code,
                'item_description' => $description,
                'item_type' => $type,
                'item_uom' => $uom,
                'soh' => $soh
            ]);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: ?controller=warehouse&action=items');
            exit;
        }

        AuditModel::log($_SESSION['user_id'], 'CREATE', 'warehouse', "Item created: {$code} ({$type}, SOH {$soh})", null, [
            'item_code' => $code,
            'item_description' => $description,
            'item_type' => $type,
            'item_uom' => $uom,
            'soh' => $soh
        ], 'item', (int)$itemId);

        $_SESSION['success'] = 'Item created successfully!';
        header('Location: ?controller=warehouse&action=items');
        exit;
    }

    public function getItemBalances() {
        header('Content-Type: application/json');
        $itemId = (int)($_GET['item_id'] ?? 0);
        $item = $itemId > 0 ? $this->itemImportModel->getItemById($itemId) : null;
        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            exit;
        }

        $balances = $this->itemImportModel->getBalances($itemId);
        $totalSoh = 0.0;
        $totalAllocated = 0.0;
        foreach ($balances as $b) {
            $totalSoh += (float)$b['qty_on_hand'];
            $totalAllocated += (float)$b['qty_allocated'];
        }

        echo json_encode([
            'success' => true,
            'item' => $item,
            'balances' => $balances,
            'total_soh' => round($totalSoh, 4),
            'total_allocated' => round($totalAllocated, 4)
        ]);
        exit;
    }

    public function adjustItemSoh() {
        $backParams = ['controller' => 'warehouse', 'action' => 'items'];
        foreach (['back_search' => 'search', 'back_type' => 'item_type', 'back_page' => 'page'] as $postKey => $getParam) {
            $val = trim($_POST[$postKey] ?? '');
            if ($val !== '' && $val !== '0') {
                $backParams[$getParam] = $val;
            }
        }
        $back = '?' . http_build_query($backParams);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $back);
            exit;
        }
        if (($_SESSION['department'] ?? '') !== 'admin') {
            $_SESSION['error'] = 'Only System Admins can adjust SOH.';
            header('Location: ' . $back);
            exit;
        }

        $itemId = (int)($_POST['item_id'] ?? 0);
        $site = trim($_POST['site_code'] ?? '');
        $rawQty = trim($_POST['qty_on_hand'] ?? '');

        $item = $itemId > 0 ? $this->itemImportModel->getItemById($itemId) : null;
        if (!$item) {
            $_SESSION['error'] = 'Item not found.';
            header('Location: ' . $back);
            exit;
        }
        if (!preg_match('/^[A-Za-z0-9_\-]{1,20}$/', $site)) {
            $_SESSION['error'] = 'Invalid site code.';
            header('Location: ' . $back);
            exit;
        }
        if ($rawQty === '' || !is_numeric($rawQty) || (float)$rawQty < 0) {
            $_SESSION['error'] = 'New SOH must be a number greater than or equal to 0.';
            header('Location: ' . $back);
            exit;
        }
        $qty = round((float)$rawQty, 4);

        $oldSiteQty = null;
        $oldTotal = 0.0;
        $oldAllocated = 0.0;
        foreach ($this->itemImportModel->getBalances($itemId) as $b) {
            $oldTotal += (float)$b['qty_on_hand'];
            $oldAllocated += (float)$b['qty_allocated'];
            if (strcasecmp($b['site_code'], $site) === 0) {
                $oldSiteQty = (float)$b['qty_on_hand'];
            }
        }
        $oldSiteQty = $oldSiteQty ?? 0.0;

        $this->itemImportModel->setSoh($itemId, $site, $qty);
        $newTotal = round($oldTotal - $oldSiteQty + $qty, 4);

        AuditModel::log(
            $_SESSION['user_id'],
            'UPDATE',
            'warehouse',
            "SOH adjusted: {$item['item_code']} @ {$site} " . formatQty($oldSiteQty) . ' -> ' . formatQty($qty)
                . ' (total ' . formatQty($oldTotal) . ' -> ' . formatQty($newTotal) . ')',
            ['site_code' => $site, 'qty_on_hand' => $oldSiteQty, 'total_soh' => $oldTotal],
            ['site_code' => $site, 'qty_on_hand' => $qty, 'total_soh' => $newTotal],
            'item',
            $itemId
        );

        $_SESSION['success'] = "SOH updated for {$item['item_code']} @ {$site}: "
            . formatQty($oldSiteQty) . ' -> ' . formatQty($qty) . '.';
        header('Location: ' . $back);
        exit;
    }

    public function itemImportForm() {
        $data['page_title'] = 'Import ERIC Items';
        $this->render('items/import_form', $data);
    }

    public function itemImportPreview() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['import_file'])) {
            $_SESSION['error'] = 'No file uploaded.';
            header('Location: ?controller=warehouse&action=itemImportForm');
            exit;
        }

        $file = $_FILES['import_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'])) {
            $_SESSION['error'] = 'Only .csv and .xlsx files are supported.';
            header('Location: ?controller=warehouse&action=itemImportForm');
            exit;
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            $_SESSION['error'] = 'File size must be less than 10MB.';
            header('Location: ?controller=warehouse&action=itemImportForm');
            exit;
        }

        try {
            $tempPath = sys_get_temp_dir() . '/' . uniqid('item_') . '.' . $ext;
            move_uploaded_file($file['tmp_name'], $tempPath);
            $rows = SpreadsheetReader::read($tempPath);
            unlink($tempPath);
        } catch (\Exception $e) {
            if (isset($tempPath) && file_exists($tempPath)) unlink($tempPath);
            $_SESSION['error'] = 'Failed to read file: ' . $e->getMessage();
            header('Location: ?controller=warehouse&action=itemImportForm');
            exit;
        }

        if (empty($rows)) {
            error_log('ERIC Import: 0 rows parsed. File: ' . $file['name'] . ' | Extension: ' . $ext);
            $_SESSION['error'] = 'No data rows found in the file. Please check the file format and ensure Row 1 contains headers.';
            header('Location: ?controller=warehouse&action=itemImportForm');
            exit;
        }

        $preview = [];
        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            $errors = [];
            $itemCode = trim($row['c_item'] ?? '');
            $description = trim($row['c_desc'] ?? '');
            $cType = trim($row['c_type'] ?? '');
            $um = trim($row['um'] ?? '');
            $site = trim($row['site'] ?? '');
            $qtyOnHand = floatval($row['qty_on_hand'] ?? 0);
            $qtyForInspect = floatval($row['qty_for_inspect'] ?? 0);

            if ($itemCode === '') $errors[] = 'c_item is required';
            if ($description === '') $errors[] = 'c_desc is required';
            if ($cType === '') $errors[] = 'c_type is required';
            if ($um === '') $errors[] = 'um is required';
            if ($site === '') $site = \App\Core\BaseModel::DEFAULT_SITE_CODE;

            $itemType = $cType !== '' ? ItemImportModel::mapItemType($cType) : 'SUPPLIES';
            $existing = $itemCode !== '' ? $this->itemImportModel->getItemByCode($itemCode) : null;

            $preview[] = [
                'row' => $rowNum,
                'item_code' => $itemCode,
                'description' => $description,
                'item_type' => $itemType,
                'raw_c_type' => $cType,
                'uom' => $um,
                'site' => $site,
                'qty_on_hand' => $qtyOnHand,
                'qty_for_inspect' => $qtyForInspect,
                'status' => $existing ? 'update' : 'new',
                'errors' => $errors,
            ];
        }

        $_SESSION['import_preview_items'] = $preview;
        $data['preview'] = $preview;
        $data['newCount'] = count(array_filter($preview, fn($r) => $r['status'] === 'new' && empty($r['errors'])));
        $data['updateCount'] = count(array_filter($preview, fn($r) => $r['status'] === 'update' && empty($r['errors'])));
        $data['errorCount'] = count(array_filter($preview, fn($r) => !empty($r['errors'])));
        $data['page_title'] = 'Import ERIC Items - Preview';
        $this->render('items/import_preview', $data);
    }

    public function itemImportConfirm() {
        $preview = $_SESSION['import_preview_items'] ?? null;
        if (!$preview) {
            $_SESSION['error'] = 'No import data found. Please upload again.';
            header('Location: ?controller=warehouse&action=itemImportForm');
            exit;
        }
        unset($_SESSION['import_preview_items']);

        $conn = \App\Core\BaseModel::getConnection();
        $conn->beginTransaction();

        $created = 0;
        $updated = 0;
        $inventoryRows = 0;
        $errors = 0;

        try {
            foreach ($preview as $row) {
                if (!empty($row['errors'])) {
                    $errors++;
                    continue;
                }

                $itemId = $this->itemImportModel->upsertItem([
                    'item_code' => $row['item_code'],
                    'item_description' => $row['description'],
                    'item_type' => $row['item_type'],
                    'raw_c_type' => $row['raw_c_type'],
                    'item_uom' => $row['uom']
                ]);

                if ($row['qty_on_hand'] > 0 || $row['qty_for_inspect'] > 0) {
                    $this->itemImportModel->upsertInventoryBalance(
                        $itemId,
                        $row['site'],
                        $row['qty_on_hand'],
                        $row['qty_for_inspect']
                    );
                    $inventoryRows++;
                }
            }

            $conn->commit();

            $created = count(array_filter($preview, fn($r) => empty($r['errors']) && $r['status'] === 'new'));
            $updated = count(array_filter($preview, fn($r) => empty($r['errors']) && $r['status'] === 'update'));

            AuditModel::log($_SESSION['user_id'], 'IMPORT', 'warehouse', "ERIC item import: {$created} new, {$updated} updated, {$inventoryRows} inventory records, {$errors} errors", null, ['created' => $created, 'updated' => $updated, 'inventory' => $inventoryRows, 'errors' => $errors], 'item', null);
            $_SESSION['success'] = "Import complete: {$created} new items, {$updated} updated, {$inventoryRows} inventory records";
            header('Location: ?controller=warehouse&action=items');
            exit;
        } catch (\Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = 'Import failed: ' . $e->getMessage();
            header('Location: ?controller=warehouse&action=itemImportForm');
            exit;
        }
    }

    // ─── Stock In/Out (stock_movements ledger, receiving personnel) ────────────

    public function stockMovement() {
        $data['page_title'] = 'Stock In / Stock Out';
        $data['movements'] = $this->warehouseModel->getStockMovements(100);
        $data['poOptions'] = $this->warehouseModel->getStockInPoOptions();
        $data['stockOutItems'] = $this->catalogModel->getItems();
        $this->render('stock_movement/index', $data);
    }

    public function saveStockMovement() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=warehouse&action=stockMovement');
            exit;
        }

        $back = '?controller=warehouse&action=stockMovement';
        $type = strtoupper(trim($_POST['movement_type'] ?? ''));
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $site = trim($_POST['site_code'] ?? '001');
        $rawQty = trim($_POST['qty'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        try {
            if (!in_array($type, ['IN', 'OUT'], true)) {
                throw new \RuntimeException('Movement type must be IN or OUT.');
            }
            $item = $this->itemImportModel->getItemById($itemId);
            if (!$item) {
                throw new \RuntimeException('Item not found.');
            }
            if (!preg_match('/^[A-Za-z0-9_\-]{1,20}$/', $site)) {
                throw new \RuntimeException('Invalid site code.');
            }
            if ($rawQty === '' || !is_numeric($rawQty) || (float) $rawQty <= 0) {
                throw new \RuntimeException('Quantity must be a number greater than zero.');
            }
            $qty = round((float) $rawQty, 4);

            // Stock IN references a purchasing PO; Stock OUT uses a free-text reason.
            $referenceType = null;
            $referenceNo = null;
            if ($type === 'IN') {
                $poId = (int) ($_POST['supplier_order_id'] ?? 0);
                $poOption = null;
                foreach ($this->warehouseModel->getStockInPoOptions() as $opt) {
                    if ((int) $opt['supplier_order_id'] === $poId) {
                        $poOption = $opt;
                        break;
                    }
                }
                if (!$poOption) {
                    throw new \RuntimeException('Please select a purchasing PO reference for the stock IN.');
                }
                if ((int) $poOption['item_id'] !== $itemId) {
                    throw new \RuntimeException('The selected item does not match the chosen purchasing PO.');
                }
                $referenceType = 'PURCHASING_PO';
                $referenceNo = trim($poOption['po_ref'] ?: ('PO#' . $poOption['supplier_order_id']));
            } else {
                $referenceType = 'MANUAL';
                $referenceNo = null;
                if ($remarks === '') {
                    throw new \RuntimeException('A reason/remarks is required for stock OUT.');
                }
                $soh = $this->itemImportModel->getSoh($itemId, $site);
                if ($qty > $soh + 0.0001) {
                    throw new \RuntimeException('Stock OUT exceeds available SOH (' . formatQty($soh) . ' ' . $item['item_uom'] . ' @ ' . $site . ').');
                }
            }

            $conn = \App\Core\BaseModel::getConnection();
            $conn->beginTransaction();
            try {
                $this->warehouseModel->recordStockMovement([
                    'movement_type' => $type,
                    'item_id' => $itemId,
                    'site_code' => $site,
                    'qty' => $qty,
                    'reference_type' => $referenceType,
                    'reference_no' => $referenceNo,
                    'remarks' => $remarks !== '' ? $remarks : null,
                    'created_by' => $_SESSION['user_id'] ?? null,
                ]);
                $delta = $type === 'IN' ? $qty : -$qty;
                $this->itemImportModel->adjustSohDelta($itemId, $site, $delta);
                $conn->commit();
            } catch (\Exception $e) {
                $conn->rollBack();
                throw $e;
            }

            AuditModel::log(
                $_SESSION['user_id'],
                'UPDATE',
                'warehouse',
                "Stock {$type}: {$item['item_code']} " . formatQty($qty) . ' @ ' . $site
                    . ($referenceNo !== null ? " (ref {$referenceType} {$referenceNo})" : '')
                    . ($remarks !== '' ? " — {$remarks}" : ''),
                null,
                ['movement_type' => $type, 'qty' => $qty, 'site_code' => $site, 'reference_no' => $referenceNo],
                'item',
                $itemId
            );

            $_SESSION['success'] = "Stock {$type} recorded: {$item['item_code']} " . formatQty($qty) . ' @ ' . $site . '.';
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ' . $back);
        exit;
    }

    private function render($view, $data = []) {
        // Sidebar badge + approver visibility: only Warehouse Admins see the
        // MRP Approval nav item; count = runs awaiting WHSE approval.
        if (($_SESSION['department'] ?? '') === 'warehouse') {
            $role = RoleHelper::currentRole();
            $data['role'] = $role;
            $data['roleLabel'] = RoleHelper::label($role);
            $data['sideMenu'] = RoleHelper::warehouseMenu($role);
            try {
                $data['mrpApprovalCount'] = $this->warehouseModel->getMrpApprovalCount('whse');
                $data['isMrpApprover'] = $this->warehouseModel->isWarehouseApprover($_SESSION['user_id'] ?? null);
            } catch (\Exception $e) {
                $data['mrpApprovalCount'] = 0;
                $data['isMrpApprover'] = false;
            }
        }
        extract($data);
        ob_start();
        include __DIR__ . "/../views/{$view}.php";
        $content = ob_get_clean();
        // System Admin edits the Item Master List from the admin module — keep
        // them in the admin chrome instead of swapping to the warehouse sidebar.
        $adminItemViews = ['items/index', 'items/import_form', 'items/import_preview'];
        if (($_SESSION['department'] ?? '') === 'admin' && in_array($view, $adminItemViews, true)) {
            include BASE_PATH . 'admin/views/layouts/main.php';
            return;
        }
        include __DIR__ . "/../views/layouts/main.php";
    }
}