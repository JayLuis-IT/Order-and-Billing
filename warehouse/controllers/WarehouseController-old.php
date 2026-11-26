<?php
namespace App\Controllers;

use App\Models\WarehouseModel;
use App\Models\AuditModel;
use App\Helpers\Pagination;
use App\Helpers\NotificationHelper;
use App\Helpers\XlsxExport;

class WarehouseController {
    private $warehouseModel;

    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?controller=auth&action=login');
            exit;
        }
        $action = $_GET['action'] ?? '';
        if ($action !== 'getPODetails' && $action !== 'getItemsByCustomer' && $action !== 'backloadDelivery' && $action !== 'getDeliveryLotsForBackload' && $action !== 'getLotsByPOItem' && $action !== 'getPOItemsForAssignment' && $action !== 'getActivePOsForAssignment' && $action !== 'getLotsForTransfer' && $action !== 'viewBackloads' && $action !== 'getPOsContainingItem' && $action !== 'getAvailableItemsForDelivery' && ($_SESSION['department'] ?? '') !== 'warehouse') {
            header('Location: ?controller=admin');
            exit;
        }
        $this->warehouseModel = new WarehouseModel();
    }

    public function index() {
        $data['page_title'] = 'Warehouse Dashboard';
        $data['customers'] = $this->warehouseModel->getCustomers();
        $data['items'] = $this->warehouseModel->getItems();
        $data['purchase_orders'] = $this->warehouseModel->getActivePOsForDashboard(5);
        $poIds = array_column($data['purchase_orders'], 'po_id');
        $data['po_items_map'] = $this->warehouseModel->getPurchaseOrderItemsByPOIds($poIds);

        $data['deliveries'] = $this->warehouseModel->getDeliveries();
        $this->render('dashboard', $data);
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
                $customer_id = $_POST['customer_id'];
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
                        $item['uom'] ?? 'PCS'
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
                $_SESSION['error'] = 'Failed to create purchase order: ' . $e->getMessage();
                header('Location: ?controller=warehouse&action=createPO');
                exit;
            }
        }
        $data['page_title'] = 'Create PO';
        $data['customers'] = $this->warehouseModel->getCustomers();
        $data['items'] = $this->warehouseModel->getItems();
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
                            $item['uom'] ?? 'PCS'
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
                // Cascade delete all related data for removed PO items
                $conn->prepare("DELETE FROM production_lots WHERE poi_id = :poi_id")
                    ->execute(['poi_id' => $poiId]);
                $conn->prepare("DELETE FROM production_history WHERE poi_id = :poi_id")
                    ->execute(['poi_id' => $poiId]);
                $conn->prepare("DELETE FROM production_reports WHERE poi_id = :poi_id")
                    ->execute(['poi_id' => $poiId]);
                $conn->prepare("DELETE FROM delivery_reports WHERE poi_id = :poi_id")
                    ->execute(['poi_id' => $poiId]);
                // Delete delivery_receipts for this PO's deliveries first (FK dependency)
                $conn->prepare("DELETE dr FROM delivery_receipts dr 
                    INNER JOIN deliveries d ON dr.delivery_id = d.delivery_id 
                    WHERE d.poi_id = :poi_id")
                    ->execute(['poi_id' => $poiId]);
                $conn->prepare("DELETE FROM deliveries WHERE poi_id = :poi_id")
                    ->execute(['poi_id' => $poiId]);
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
            // Always include items in new_values so diff works even when only header fields change
            if (!empty($oldItems)) {
                $newValues['items'] = array_map(function($item) {
                    return [
                        'poi_id' => $item['poi_id'],
                        'item_id' => $item['item_id'],
                        'quantity' => $item['quantity'],
                    ];
                }, array_values($oldItems));
            }
            if (!empty($itemChanges)) {
                $newValues['items'] = $itemChanges;
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
        $items = $this->warehouseModel->getItemsByCustomer($customer_id);
        echo json_encode($items);
        exit;
    }

    public function getPODetails() {
        header('Content-Type: application/json');
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
    }

    public function getLotsByPOItem() {
        header('Content-Type: application/json');
        $poiId = $_GET['poi_id'] ?? null;
        if (!$poiId) {
            echo json_encode([]);
            exit;
        }
        $lots = $this->warehouseModel->getLotsByPOItem($poiId);
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
        if (!empty($deliveryIds)) {
            $placeholders = implode(',', array_fill(0, count($deliveryIds), '?'));
            $conn = $this->warehouseModel::getConnection();
            $stmt = $conn->prepare("SELECT * FROM delivery_receipts WHERE delivery_id IN ($placeholders) AND `remove` = 0 ORDER BY date_created ASC");
            $stmt->execute($deliveryIds);
            foreach ($stmt->fetchAll() as $r) {
                $receiptsMap[$r['delivery_id']][] = $r;
            }
        }
        $data['receipts_map'] = $receiptsMap;

        $backloadsMap = [];
        if (!empty($deliveryIds)) {
            $placeholders = implode(',', array_fill(0, count($deliveryIds), '?'));
            $blStmt = $conn->prepare("SELECT * FROM backloads WHERE delivery_id IN ($placeholders) AND `remove` = 0 ORDER BY date_created ASC");
            $blStmt->execute($deliveryIds);
            foreach ($blStmt->fetchAll() as $bl) {
                $backloadsMap[$bl['delivery_id']][] = $bl;
            }
        }
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

        $allBackloads = $this->warehouseModel->getBackloads($filters);
        $pagination = Pagination::paginate($allBackloads, 15);

        $customers = $this->warehouseModel->getCustomers();

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

        $lots = $this->warehouseModel->getDeliveryLotsForBackload($delivery_id);
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

                $this->warehouseModel->createBackload([
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
            $isOverShipment = intval($_POST['is_over_shipment'] ?? 0) ? 1 : 0;
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
                            $item = $poiId ? $this->warehouseModel->getItemByPoiId($poiId) : $this->warehouseModel->getItemById($lot['item_id'] ?? null);
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
                    $item = $poiId ? $this->warehouseModel->getItemByPoiId($poiId) : $this->warehouseModel->getItemById($lot['item_id'] ?? null);
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
                $item = $this->warehouseModel->getItemByPoiId($poiId);
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

    private function render($view, $data = []) {
        extract($data);
        ob_start();
        include __DIR__ . "/../views/{$view}.php";
        $content = ob_get_clean();
        include __DIR__ . "/../views/layouts/main.php";
    }
}