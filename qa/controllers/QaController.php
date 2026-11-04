<?php
namespace App\Controllers;

use App\Models\QaModel;
use App\Models\WarehouseModel;
use App\Models\AuditModel;
use App\Helpers\Pagination;

class QaController {
    private $qaModel;
    private $warehouseModel;

    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?controller=auth&action=login');
            exit;
        }
        if (($_SESSION['department'] ?? '') !== 'qa') {
            header('Location: ' . \App\Helpers\RoleHelper::homeUrl());
            exit;
        }
        $this->qaModel = new QaModel();
        $this->warehouseModel = new WarehouseModel();
    }

    public function index() {
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
            $allHistory = $this->qaModel->getProductionHistoryForQA($filters);
        } else {
            $allHistory = $this->qaModel->getProductionHistoryForQA();
        }

        $allCustomers = array_values(array_unique(array_filter(array_column($allHistory, 'customer_name'))));
        $allItems = array_values(array_unique(array_filter(array_column($allHistory, 'item_description'))));
        $allLots = array_values(array_unique(array_filter(array_column($allHistory, 'lot_number'))));

        if ($hasFilter) {
            $pagination = ['items' => $allHistory, 'page' => 1, 'perPage' => count($allHistory), 'total' => count($allHistory), 'totalPages' => 1, 'hasNext' => false, 'hasPrev' => false];
        } else {
            $pagination = Pagination::paginate($allHistory, 15);
        }

        $counts = $this->qaModel->getInspectionCounts();

        $data = [
            'history' => $pagination['items'],
            'page' => $pagination['page'],
            'totalPages' => $pagination['totalPages'],
            'total' => $pagination['total'],
            'search' => $search,
            'filterCustomer' => $filterCustomer,
            'filterItem' => $filterItem,
            'filterLot' => $filterLot,
            'allCustomers' => $allCustomers,
            'allItems' => $allItems,
            'allLots' => $allLots,
            'totalInspection' => $counts['total'],
            'inspectedCount' => $counts['inspected'],
            'remainingCount' => $counts['remaining'],
            'page_title' => 'QA Dashboard'
        ];
        $this->render('dashboard/index', $data);
    }

    public function updateRemark() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $historyId = $_POST['history_id'] ?? null;
            $remark = trim($_POST['qa_remark'] ?? '');
            $inspectorName = trim($_POST['qa_inspector_name'] ?? '');

            if (!$historyId || empty($remark) || empty($inspectorName)) {
                $_SESSION['error'] = 'Please fill in all required fields.';
                header('Location: ?controller=qa');
                exit;
            }

            $this->qaModel->updateQaRemark($historyId, $inspectorName, $remark);
            $_SESSION['success'] = 'QA remark saved successfully.';
            header('Location: ?controller=qa');
            exit;
        }
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
        $po_items_map = $this->warehouseModel->getPurchaseOrderItemsByPOIds($poIds);

        $data = [
            'allPOs' => $pagination['items'],
            'po_items_map' => $po_items_map,
            'page' => $pagination['page'],
            'totalPages' => $pagination['totalPages'],
            'total' => $pagination['total'],
            'search' => $search,
            'filterCustomer' => $filterCustomer,
            'filterItem' => $filterItem,
            'filterDate' => $filterDate,
            'filterDeliveryStatus' => $filterDeliveryStatus,
            'allCustomers' => $allCustomers,
            'page_title' => 'Customer PO'
        ];
        $this->render('purchase_orders/index', $data);
    }

    public function delivered() {
        $search = $_GET['search'] ?? '';
        $filterCustomer = $_GET['filter_customer'] ?? '';
        $filterItem = $_GET['filter_item'] ?? '';
        $filterDR = $_GET['filter_dr'] ?? '';
        $filterDate = $_GET['filter_date'] ?? '';
        $filterPo = $_GET['filter_po'] ?? '';
        $filterDeliveredBy = $_GET['filter_delivered_by'] ?? '';
        $filterType = $_GET['filter_type'] ?? '';

        $hasFilter = $search || $filterCustomer || $filterItem || $filterDR || $filterDate || $filterPo || $filterDeliveredBy || $filterType;
        if ($hasFilter) {
            $filters = [];
            if ($search) $filters['search'] = $search;
            if ($filterCustomer) $filters['customer_name'] = $filterCustomer;
            if ($filterItem) $filters['item_description'] = $filterItem;
            if ($filterDR) $filters['dr_number'] = $filterDR;
            if ($filterDate) $filters['delivery_date'] = $filterDate;
            if ($filterPo) $filters['po_number'] = $filterPo;
            if ($filterDeliveredBy) $filters['delivered_by'] = $filterDeliveredBy;
            if ($filterType) $filters['production_type'] = $filterType;
            $allDeliveries = $this->warehouseModel->getDeliveriesFiltered($filters);
        } else {
            $allDeliveries = $this->warehouseModel->getDeliveries();
        }

        usort($allDeliveries, function($a, $b) {
            return strtotime($b['date_created'] ?? '') - strtotime($a['date_created'] ?? '');
        });

        $allCustomers = array_values(array_unique(array_filter(array_column($allDeliveries, 'customer_name'))));
        $allItems = [];
        foreach ($allDeliveries as $d) {
            $lotItems = json_decode($d['lot_items'] ?? '[]', true);
            if (is_array($lotItems)) {
                foreach ($lotItems as $li) { if (!empty($li['item_description'])) $allItems[] = $li['item_description']; }
            } elseif (!empty($d['item_description'])) {
                $allItems[] = $d['item_description'];
            }
        }
        $allItems = array_values(array_unique($allItems));
        $allDRs = array_values(array_unique(array_filter(array_column($allDeliveries, 'dr_number'))));
        $allPOs = array_values(array_unique(array_filter(array_column($allDeliveries, 'customer_po_number'))));
        $allDeliveredBy = array_values(array_unique(array_filter(array_column($allDeliveries, 'delivered_by_name'))));

        if ($hasFilter) {
            $pagination = ['items' => $allDeliveries, 'page' => 1, 'perPage' => count($allDeliveries), 'total' => count($allDeliveries), 'totalPages' => 1, 'hasNext' => false, 'hasPrev' => false];
        } else {
            $pagination = Pagination::paginate($allDeliveries, 20);
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

        $data['page'] = $pagination['page'];
        $data['totalPages'] = $pagination['totalPages'];
        $data['total'] = $pagination['total'];
        $data['search'] = $search;
        $data['filterCustomer'] = $filterCustomer;
        $data['filterItem'] = $filterItem;
        $data['filterDR'] = $filterDR;
        $data['filterDate'] = $filterDate;
        $data['filterPo'] = $filterPo;
        $data['filterDeliveredBy'] = $filterDeliveredBy;
        $data['filterType'] = $filterType;
        $data['allCustomers'] = $allCustomers;
        $data['allItems'] = $allItems;
        $data['allDRs'] = $allDRs;
        $data['allPOs'] = $allPOs;
        $data['allDeliveredBy'] = $allDeliveredBy;
        $data['page_title'] = 'Deliveries';
        $this->render('delivered', $data);
    }

    public function reports() {
        $poItemSummary = $this->warehouseModel->getPoItemSummary();

        $data = [
            'poItemSummary' => $poItemSummary,
            'page_title' => 'PO Summary Report'
        ];
        $this->render('reports/index', $data);
    }

    private function render($view, $data = []) {
        extract($data);
        ob_start();
        include __DIR__ . "/../views/{$view}.php";
        $content = ob_get_clean();
        include __DIR__ . "/../views/layouts/main.php";
    }
}
