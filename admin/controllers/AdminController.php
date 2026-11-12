<?php
namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\ItemModel;
use App\Models\CustomerFinishedGoodModel;
use App\Models\WarehouseModel;
use App\Models\BackloadModel;
use App\Models\CatalogModel;
use App\Models\RawMaterialModel;
use App\Models\BomModel;
use App\Helpers\Pagination;
use App\Helpers\CsvExport;
use App\Helpers\XlsxExport;
use App\Models\AuditModel;
use App\Helpers\NotificationHelper;
use App\Helpers\SpreadsheetReader;

class AdminController {
    private $customerModel;
    private $itemModel;
    private $cfgModel;
    private $warehouseModel;
    private $backloadModel;
    private $catalogModel;
    private $rawMaterialModel;
    private $bomModel;

    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?controller=auth&action=login');
            exit;
        }
        $action = $_GET['action'] ?? '';
        // Stale/corrupted session (user_id set but department missing) would
        // ping-pong between here and homeUrl() forever — force a clean logout.
        if (($_SESSION['department'] ?? '') === '') {
            header('Location: ?controller=auth&action=logout');
            exit;
        }
        // The admin module is exclusive to the admin department; other
        // departments use their own modules and role landings.
        if (($_SESSION['department'] ?? '') !== 'admin') {
            header('Location: ' . \App\Helpers\RoleHelper::homeUrl());
            exit;
        }
        $rndOnlyActions = [
            'rawMaterials', 'rawMaterialCreate', 'rawMaterialUpdate',
            'rawMaterialDelete', 'rawMaterialToggleStatus',
            'rawMaterialImportPreview', 'rawMaterialImportConfirm', 'whereUsed',
            'boms', 'bomCreate', 'bomEdit', 'bomUpdate', 'bomDelete',
            'bomAddItem', 'bomUpdateItem', 'bomRemoveItem',
            'bomImportPreview', 'bomImportConfirm'
        ];
        if (in_array($action, $rndOnlyActions)) {
            $_SESSION['error'] = 'Raw Materials and BOM are managed by the R&D department.';
            header('Location: ?controller=admin');
            exit;
        }
        $this->customerModel = new CustomerModel();
        $this->itemModel = new ItemModel();
        $this->cfgModel = new CustomerFinishedGoodModel();
        $this->warehouseModel = new WarehouseModel();
        $this->backloadModel = new BackloadModel();
        $this->catalogModel = new CatalogModel();
        $this->rawMaterialModel = new RawMaterialModel();
        $this->bomModel = new BomModel();
    }

    public function index() {
        $data['customers'] = $this->customerModel->getAll(false);
        $data['items'] = $this->cfgModel->getAll(false);
        $allPOs = $this->warehouseModel->getPurchaseOrders();
        $data['allPOCount'] = count($allPOs);
        $data['purchase_orders'] = $this->warehouseModel->getActivePOsForDashboard(5);
        $poIds = array_column($data['purchase_orders'], 'po_id');
        $data['po_items_map'] = $this->warehouseModel->getPurchaseOrderItemsByPOIds($poIds);

        $data['deliveries'] = $this->warehouseModel->getDeliveries();
        $data['deliveryReportsCount'] = $this->warehouseModel->getDeliveryReportsCount();
        $data['reportsCount'] = $this->warehouseModel->getProductionReportsCount();
        $data['history'] = $this->warehouseModel->getProductionHistory();
        $data['page_title'] = 'Admin Dashboard';
        $this->render('dashboard', $data);
    }

    public function customers() {
        $search = $_GET['search'] ?? '';
        $filters = [];
        if ($search) $filters['search'] = $search;

        $hasFilters = ($search !== '');
        if ($hasFilters) {
            $allCustomers = $this->customerModel->getAllFiltered($filters);
            $data['customers'] = $allCustomers;
            $data['page'] = 1;
            $data['totalPages'] = 1;
            $data['total'] = count($allCustomers);
        } else {
            $allCustomers = $this->customerModel->getAll(false);
            $pagination = Pagination::paginate($allCustomers, 10);
            $data['customers'] = $pagination['items'];
            $data['page'] = $pagination['page'];
            $data['totalPages'] = $pagination['totalPages'];
            $data['total'] = $pagination['total'];
        }
        $data['search'] = $search;
        $data['deliveryReportsCount'] = $this->warehouseModel->getDeliveryReportsCount();
        $data['reportsCount'] = $this->warehouseModel->getProductionReportsCount();
        $data['page_title'] = 'Customer Management';
        $this->render('customers/index', $data);
    }

    public function customersExport() {
        $search = $_GET['search'] ?? '';
        $filters = [];
        if ($search) $filters['search'] = $search;
        $allCustomers = $this->customerModel->getAllFiltered($filters);

        $headers = ['Code', 'Name', 'Delivery Address', 'TIN', 'Terms'];
        $rows = [];
        foreach ($allCustomers as $c) {
            $terms = $c['customer_terms'] ?? '';
            $termsDisplay = $terms !== '' && $terms !== '0' ? $terms . (is_numeric($terms) ? ' days' : '') : '-';
            $rows[] = [
                $c['customer_code'],
                $c['customer_name'],
                $c['customer_address'] ?? '-',
                $c['customer_tin'] ?? '-',
                $termsDisplay
            ];
        }
        CsvExport::export('customers_' . date('Y-m-d') . '.csv', $headers, $rows);
    }

    public function customersPrint() {
        $search = $_GET['search'] ?? '';
        $filters = [];
        if ($search) $filters['search'] = $search;
        $allCustomers = $this->customerModel->getAllFiltered($filters);

        $data['customers'] = $allCustomers;
        $data['search'] = $search;
        $data['total'] = count($allCustomers);
        $data['pageTitle'] = 'Customer List';
        extract($data);
        include __DIR__ . "/../views/customers/print.php";
        exit;
    }

    public function customerCreate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $result = $this->customerModel->create($_POST);
                if ($result) {
                    AuditModel::log($_SESSION['user_id'], 'CREATE', 'admin', 'Created customer: ' . ($_POST['customer_name'] ?? ''), null, ['customer_name' => $_POST['customer_name'] ?? '', 'customer_code' => $_POST['customer_code'] ?? ''], 'customer', $result);
                    $_SESSION['success'] = 'Customer created successfully';
                    header('Location: ?controller=admin&action=customers');
                    exit;
                }
            } catch (\PDOException $e) {
                $_SESSION['error'] = $this->getDbErrorMessage($e, 'customer_code', 'Customer code');
                header('Location: ?controller=admin&action=customers');
                exit;
            }
        }
        $data['page_title'] = 'Add Customer';
        $this->render('customers/form', $data);
    }

    public function customerEdit() {
        $id = $_GET['id'] ?? null;
        $data['customer'] = $this->customerModel->getById($id);
        if (!$data['customer']) {
            $_SESSION['error'] = 'Customer not found';
            header('Location: ?controller=admin&action=customers');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $oldCustomer = $this->customerModel->getById($id);
                $result = $this->customerModel->update($id, $_POST);
                if ($result) {
                    AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin', 'Updated customer: ' . ($oldCustomer['customer_name'] ?? ''), $oldCustomer, ['customer_name' => $_POST['customer_name'] ?? '', 'customer_code' => $_POST['customer_code'] ?? ''], 'customer', $id);
                    $_SESSION['success'] = 'Customer updated successfully';
                    header('Location: ?controller=admin&action=customers');
                    exit;
                }
            } catch (\Exception $e) {
                error_log('customerEdit error: ' . $e->getMessage());
                $_SESSION['error'] = 'Failed to update customer: ' . $e->getMessage();
                header('Location: ?controller=admin&action=customers');
                exit;
            }
        }
        $data['page_title'] = 'Edit Customer';
        $this->render('customers/form', $data);
    }

    public function customerDelete() {
        $id = $_GET['id'] ?? null;
        try {
            $oldCustomer = $this->customerModel->getById($id);
            $this->customerModel->softDelete($id);
            AuditModel::log($_SESSION['user_id'], 'DELETE', 'admin', 'Deleted customer: ' . ($oldCustomer['customer_name'] ?? $id), $oldCustomer, null, 'customer', $id);
            $_SESSION['success'] = 'Customer deleted successfully';
        } catch (\Exception $e) {
            error_log('customerDelete error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to delete customer: ' . $e->getMessage();
        }
        header('Location: ?controller=admin&action=customers');
        exit;
    }

    public function items() {
        $search = $_GET['search'] ?? '';
        $customerFilter = $_GET['customer_id'] ?? '';
        $filters = [];
        if ($search) $filters['search'] = $search;
        if ($customerFilter) $filters['customer_id'] = $customerFilter;

        $hasFilters = ($search !== '' || $customerFilter !== '');
        if ($hasFilters) {
            $allItems = $this->cfgModel->getAllFiltered($filters);
            $data['items'] = $allItems;
            $data['page'] = 1;
            $data['totalPages'] = 1;
            $data['total'] = count($allItems);
        } else {
            $allItems = $this->cfgModel->getAll(false);
            $pagination = Pagination::paginate($allItems, 10);
            $data['items'] = $pagination['items'];
            $data['page'] = $pagination['page'];
            $data['totalPages'] = $pagination['totalPages'];
            $data['total'] = $pagination['total'];
        }
        $data['customers'] = $this->customerModel->getWithItems();
        $data['allCustomers'] = $this->customerModel->getAll(false);
        $data['search'] = $search;
        $data['customerFilter'] = $customerFilter;
        $data['page_title'] = 'Item Management';
        $this->render('items/index', $data);
    }

    public function itemsExport() {
        $search = $_GET['search'] ?? '';
        $customerFilter = $_GET['customer_id'] ?? '';
        $filters = [];
        if ($search) $filters['search'] = $search;
        if ($customerFilter) $filters['customer_id'] = $customerFilter;
        $allItems = $this->cfgModel->getAllFiltered($filters);

        $headers = ['Code', 'Description', 'Customer', 'UOM', 'Conversion'];
        $rows = [];
        foreach ($allItems as $item) {
            $rows[] = [
                $item['item_code'],
                $item['item_description'],
                $item['customer_name'] ?? '-',
                $item['item_uom'],
                $item['uom_conversion'] ?? '-'
            ];
        }
        CsvExport::export('items_' . date('Y-m-d') . '.csv', $headers, $rows);
    }

    public function itemsPrint() {
        $search = $_GET['search'] ?? '';
        $customerFilter = $_GET['customer_id'] ?? '';
        $filters = [];
        if ($search) $filters['search'] = $search;
        if ($customerFilter) $filters['customer_id'] = $customerFilter;
        $allItems = $this->cfgModel->getAllFiltered($filters);

        $data['items'] = $allItems;
        $data['search'] = $search;
        $data['customerFilter'] = $customerFilter;
        $data['total'] = count($allItems);
        $data['pageTitle'] = 'Item List';
        extract($data);
        include __DIR__ . "/../views/items/print.php";
        exit;
    }

    public function itemCreate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $result = $this->cfgModel->create($_POST);
                if ($result) {
                    AuditModel::log($_SESSION['user_id'], 'CREATE', 'admin', 'Created item: ' . ($_POST['item_description'] ?? ''), null, ['item_code' => $_POST['item_code'] ?? '', 'description' => $_POST['item_description'] ?? ''], 'item', $result);
                    $_SESSION['success'] = 'Item created successfully';
                    $search = $_POST['filter_search'] ?? '';
                    $customerFilter = $_POST['filter_customer_id'] ?? '';
                    $redirect = '?controller=admin&action=items';
                    if ($search !== '' || $customerFilter !== '') {
                        $redirect .= '&search=' . urlencode($search) . '&customer_id=' . urlencode($customerFilter);
                    }
                    header('Location: ' . $redirect);
                    exit;
                }
            } catch (\Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                $search = $_POST['filter_search'] ?? '';
                $customerFilter = $_POST['filter_customer_id'] ?? '';
                $redirect = '?controller=admin&action=items';
                if ($search !== '' || $customerFilter !== '') {
                    $redirect .= '&search=' . urlencode($search) . '&customer_id=' . urlencode($customerFilter);
                }
                header('Location: ' . $redirect);
                exit;
            }
        }
        $data['customers'] = $this->customerModel->getAll(false);
        $data['page_title'] = 'Add Item';
        $this->render('items/form', $data);
    }

    public function itemEdit() {
        $id = $_GET['id'] ?? null;
        $search = $_GET['search'] ?? '';
        $customerFilter = $_GET['customer_id'] ?? '';
        $data['item'] = $this->cfgModel->getById($id);
        if (!$data['item']) {
            $_SESSION['error'] = 'Item not found';
            $redirect = '?controller=admin&action=items';
            if ($search !== '' || $customerFilter !== '') {
                $redirect .= '&search=' . urlencode($search) . '&customer_id=' . urlencode($customerFilter);
            }
            header('Location: ' . $redirect);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $oldItem = $this->cfgModel->getById($id);
            $result = $this->cfgModel->update($id, $_POST);
            if ($result) {
                AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin', 'Updated item: ' . ($oldItem['item_description'] ?? ''), $oldItem, ['item_code' => $_POST['item_code'] ?? '', 'description' => $_POST['item_description'] ?? ''], 'item', $id);
                $_SESSION['success'] = 'Item updated successfully';
                $redirect = '?controller=admin&action=items';
                if ($search !== '' || $customerFilter !== '') {
                    $redirect .= '&search=' . urlencode($search) . '&customer_id=' . urlencode($customerFilter);
                }
                header('Location: ' . $redirect);
                exit;
            }
        }
        $data['customers'] = $this->customerModel->getAll(false);
        $data['page_title'] = 'Edit Item';
        $this->render('items/form', $data);
    }

    public function itemDelete() {
        $id = $_GET['id'] ?? null;
        try {
            $oldItem = $this->cfgModel->getById($id);
            $this->cfgModel->softDelete($id);
            AuditModel::log($_SESSION['user_id'], 'DELETE', 'admin', 'Deleted item: ' . ($oldItem['item_description'] ?? $id), $oldItem, null, 'item', $id);
            $_SESSION['success'] = 'Item deleted successfully';
        } catch (\Exception $e) {
            error_log('itemDelete error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to delete item: ' . $e->getMessage();
        }
        $search = $_GET['search'] ?? '';
        $customerFilter = $_GET['customer_id'] ?? '';
        $redirect = '?controller=admin&action=items';
        if ($search !== '' || $customerFilter !== '') {
            $redirect .= '&search=' . urlencode($search) . '&customer_id=' . urlencode($customerFilter);
        }
        header('Location: ' . $redirect);
        exit;
    }

    public function itemToggleStatus() {
        $id = $_GET['id'] ?? null;
        try {
            $oldItem = $this->cfgModel->getById($id);
            $this->cfgModel->toggleStatus($id);
            $newStatus = $oldItem['status'] ? 'Inactive' : 'Active';
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin', 'Toggled item status to ' . $newStatus . ': ' . ($oldItem['item_description'] ?? $id), $oldItem, null, 'item', $id);
            $_SESSION['success'] = 'Item status changed to ' . $newStatus;
        } catch (\Exception $e) {
            error_log('itemToggleStatus error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to update status: ' . $e->getMessage();
        }
        $redirect = '?controller=admin&action=items';
        $search = $_GET['search'] ?? '';
        $customerFilter = $_GET['customer_id'] ?? '';
        if ($search !== '' || $customerFilter !== '') {
            $redirect .= '&search=' . urlencode($search) . '&customer_id=' . urlencode($customerFilter);
        }
        header('Location: ' . $redirect);
        exit;
    }

    public function customerToggleStatus() {
        $id = $_GET['id'] ?? null;
        try {
            $oldCustomer = $this->customerModel->getById($id);
            $this->customerModel->toggleStatus($id);
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin', 'Toggled customer status: ' . ($oldCustomer['customer_name'] ?? $id), $oldCustomer, null, 'customer', $id);
            $_SESSION['success'] = 'Customer status updated';
        } catch (\Exception $e) {
            error_log('customerToggleStatus error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to update status: ' . $e->getMessage();
        }
        header('Location: ?controller=admin&action=customers');
        exit;
    }

    private function getDbErrorMessage(\PDOException $e, $indexName, $fieldLabel) {
        $errorCode = $e->errorInfo[1] ?? null;
        $errorMessage = $e->getMessage();
        if ($errorCode === 1062) {
            return "$fieldLabel already exists. Please try a new value.";
        }
        return "Database error: $errorMessage";
    }

    public function customerUpdate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $id = $_POST['customer_id'] ?? null;
                $oldCustomer = $this->customerModel->getById($id);
                $result = $this->customerModel->update($id, $_POST);
                if ($result) {
                    AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin', 'Updated customer (inline): ' . ($oldCustomer['customer_name'] ?? ''), $oldCustomer, ['customer_name' => $_POST['customer_name'] ?? '', 'customer_code' => $_POST['customer_code'] ?? ''], 'customer', $id);
                    $_SESSION['success'] = 'Customer updated successfully';
                }
            } catch (\Exception $e) {
                error_log('customerUpdate error: ' . $e->getMessage());
                $_SESSION['error'] = 'Failed to update customer: ' . $e->getMessage();
            }
        }
        header('Location: ?controller=admin&action=customers');
        exit;
    }

    public function itemUpdate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $id = $_POST['item_id'] ?? null;
                $oldItem = $this->cfgModel->getById($id);
                $result = $this->cfgModel->update($id, $_POST);
                if ($result) {
                    AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin', 'Updated item (inline): ' . ($oldItem['item_description'] ?? ''), $oldItem, ['item_code' => $_POST['item_code'] ?? '', 'description' => $_POST['item_description'] ?? ''], 'item', $id);
                    $_SESSION['success'] = 'Item updated successfully';
                }
            } catch (\Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }
        $search = $_POST['filter_search'] ?? '';
        $customerFilter = $_POST['filter_customer_id'] ?? '';
        $redirect = '?controller=admin&action=items';
        if ($search !== '' || $customerFilter !== '') {
            $redirect .= '&search=' . urlencode($search) . '&customer_id=' . urlencode($customerFilter);
        }
        header('Location: ' . $redirect);
        exit;
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
    $data['allPOs'] = $pagination['items'];
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
    $data['deliveryReportsCount'] = $this->warehouseModel->getDeliveryReportsCount();
    $data['reportsCount'] = $this->warehouseModel->getProductionReportsCount();
    $data['page_title'] = 'Customer PO';
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
    $filterReports = isset($_GET['filter_reports']) && $_GET['filter_reports'] === '1';

    $hasFilter = $search || $filterCustomer || $filterItem || $filterDR || $filterDate || $filterPo || $filterDeliveredBy || $filterType || $filterReports;
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
        if ($filterReports) $filters['has_reports'] = true;
        $allDeliveries = $this->warehouseModel->getDeliveriesFiltered($filters);
    } else {
        $allDeliveries = $this->warehouseModel->getDeliveries();
    }

    $reportedCount = 0;
    foreach ($allDeliveries as $d) {
        if (($d['remarks_type'] ?? '') === 'report') $reportedCount++;
    }

    usort($allDeliveries, function($a, $b) {
        $aReported = ($a['remarks_type'] ?? '') === 'report' ? 1 : 0;
        $bReported = ($b['remarks_type'] ?? '') === 'report' ? 1 : 0;
        if ($aReported !== $bReported) return $bReported - $aReported;
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
    $data['filterReports'] = $filterReports;
    $data['reportedCount'] = $reportedCount;
    $data['allCustomers'] = $allCustomers;
    $data['allItems'] = $allItems;
    $data['allDRs'] = $allDRs;
    $data['allPOs'] = $allPOs;
    $data['allDeliveredBy'] = $allDeliveredBy;
    $data['reportedCount'] = $this->warehouseModel->getReportedRemarksCount();
    $data['deliveryReportsCount'] = $this->warehouseModel->getDeliveryReportsCount();
    $data['poEditOptions'] = $this->warehouseModel->getPurchaseOrders();
    $data['itemEditOptions'] = $this->itemModel->getActiveItems();
    $data['page_title'] = 'Deliveries';
    $this->render('delivered', $data);
}

public function toggleDeliveryStatus() {
    header('Content-Type: application/json');
    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        $deliveryId = $_POST['delivery_id'] ?? null;
        if (!$deliveryId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing delivery_id']);
            exit;
        }
        $newStatus = $this->warehouseModel->toggleDeliveryStatus($deliveryId);
        AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin', 'Toggled delivery status #' . $deliveryId, null, ['active_status' => (int)$newStatus], 'delivery', $deliveryId);
        echo json_encode(['success' => true, 'active_status' => (int)$newStatus]);
    } catch (\Exception $e) {
        error_log('toggleDeliveryStatus error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to toggle delivery status']);
    }
    exit;
}

public function deleteDelivery() {
    $deliveryId = $_GET['id'] ?? null;
    if (!$deliveryId) {
        $_SESSION['error'] = 'Missing delivery id';
        header('Location: ?controller=admin&action=delivered');
        exit;
    }

    try {
        $deleted = $this->warehouseModel->deleteDelivery($deliveryId);
        if ($deleted) {
            AuditModel::log($_SESSION['user_id'], 'DELETE', 'admin', 'Deleted delivery #' . $deliveryId, null, ['delivery_id' => $deliveryId], 'delivery', $deliveryId);
            $_SESSION['success'] = 'Delivery deleted and quantities were rolled back.';
        } else {
            $_SESSION['error'] = 'Delivery not found';
        }
    } catch (\Exception $e) {
        $_SESSION['error'] = 'Failed to delete delivery: ' . $e->getMessage();
    }

    header('Location: ?controller=admin&action=delivered');
    exit;
}

public function updateDelivery() {
    header('Content-Type: application/json');
    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        $deliveryId = $_POST['delivery_id'] ?? null;
        $drNumber = trim($_POST['dr_number'] ?? '');
        $deliveryDate = $_POST['delivery_date'] ?? '';
        $poId = intval($_POST['po_id'] ?? 0);
        $plateNumber = trim($_POST['plate_number'] ?? '');
        $vehicleType = trim($_POST['vehicle_type'] ?? '');
        $logisticProvider = trim($_POST['logistic_provider'] ?? '');
        $lotChangesJson = $_POST['lot_changes'] ?? '[]';

        if (!$deliveryId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing delivery_id']);
            exit;
        }

        $lotChanges = json_decode($lotChangesJson, true);
        if (!is_array($lotChanges)) $lotChanges = [];
        $itemChangesJson = $_POST['item_changes'] ?? '[]';
        $itemChanges = json_decode($itemChangesJson, true);
        if (!is_array($itemChanges)) $itemChanges = [];

        $result = $this->warehouseModel->updateDelivery($deliveryId, [
            'dr_number' => $drNumber,
            'delivery_date' => $deliveryDate,
            'po_id' => $poId,
            'plate_number' => $plateNumber,
            'vehicle_type' => $vehicleType,
            'logistic_provider' => $logisticProvider,
            'lot_changes' => $lotChanges,
            'item_changes' => $itemChanges
        ]);

        if (is_array($result) && isset($result['success']) && !$result['success']) {
            http_response_code(400);
            echo json_encode(['error' => $result['error']]);
        } else {
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin', 'Updated delivery #' . $deliveryId, null, [
                'dr_number' => $drNumber,
                'delivery_date' => $deliveryDate,
                'po_id' => $poId,
                'plate_number' => $plateNumber,
                'vehicle_type' => $vehicleType,
                'logistic_provider' => $logisticProvider
            ], 'delivery', $deliveryId);
            echo json_encode(['success' => true]);
        }
    } catch (\Exception $e) {
        error_log('updateDelivery error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update delivery']);
    }
    exit;
}

    public function getItemsForDeliveryEdit() {
        header('Content-Type: application/json');
        try {
            $poId = !empty($_GET['po_id']) ? intval($_GET['po_id']) : 0;
            if (!$poId) {
                echo json_encode([]);
                exit;
            }
            $pois = $this->warehouseModel->getPurchaseOrderItems($poId);
            $items = [];
            foreach ($pois as $poi) {
                $items[] = [
                    'item_id' => intval($poi['item_id']),
                    'item_code' => $poi['item_code'],
                    'item_description' => $poi['item_description'],
                ];
            }
            echo json_encode($items);
        } catch (\Exception $e) {
            error_log('getItemsForDeliveryEdit error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load PO items']);
        }
        exit;
    }

    public function getLotChoicesForDeliveryEdit() {
        header('Content-Type: application/json');
        try {
            $itemId = !empty($_GET['item_id']) ? intval($_GET['item_id']) : 0;
            $deliveryId = !empty($_GET['delivery_id']) ? intval($_GET['delivery_id']) : 0;
            if (!$itemId) {
                echo json_encode([]);
                exit;
            }
            echo json_encode($this->warehouseModel->getLotChoicesForDeliveryEdit($itemId, $deliveryId));
        } catch (\Exception $e) {
            error_log('getLotChoicesForDeliveryEdit error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load lots']);
        }
        exit;
    }

public function resolveDeliveryReport() {
    header('Content-Type: application/json');
    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        $reportId = $_POST['report_id'] ?? null;
        $newQuantity = $_POST['new_quantity'] ?? null;
        $newDrNumber = trim($_POST['new_dr_number'] ?? '');

        if (!$reportId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing report_id']);
            exit;
        }

        $result = $this->warehouseModel->resolveDeliveryReport(
            $reportId,
            $newQuantity !== null ? intval($newQuantity) : null,
            $_SESSION['user_id'],
            $newDrNumber ?: null
        );
        if ($result) {
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin', 'Resolved delivery report #' . $reportId, null, ['new_quantity' => $newQuantity, 'new_dr_number' => $newDrNumber], 'delivery_report', $reportId);
            NotificationHelper::deliveryReportResolved($reportId, $_SESSION['user_id']);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Report not found']);
        }
    } catch (\Exception $e) {
        error_log('resolveDeliveryReport error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to resolve report']);
    }
    exit;
}

public function getDeliveryReports() {
    header('Content-Type: application/json');
    $deliveryId = $_GET['delivery_id'] ?? null;
    if (!$deliveryId) {
        echo json_encode([]);
        exit;
    }
    $reports = $this->warehouseModel->getDeliveryReportsByDeliveryId($deliveryId);
    echo json_encode($reports);
    exit;
}

public function productionHistory() {
    $search = $_GET['search'] ?? '';
    $filterCustomer = $_GET['filter_customer'] ?? '';
    $filterItem = $_GET['filter_item'] ?? '';
    $filterLot = $_GET['filter_lot'] ?? '';
    $filterPo = $_GET['filter_po'] ?? '';
    $filterDateFrom = $_GET['filter_date_from'] ?? '';
    $filterDateTo = $_GET['filter_date_to'] ?? '';
    $filterReports = isset($_GET['filter_reports']) && $_GET['filter_reports'] === '1';

    $hasFilter = $search || $filterCustomer || $filterItem || $filterLot || $filterPo || $filterDateFrom || $filterDateTo || $filterReports;
    if ($hasFilter) {
        $filters = [];
        if ($search) $filters['search'] = $search;
        if ($filterCustomer) $filters['customer_name'] = $filterCustomer;
        if ($filterItem) $filters['item_description'] = $filterItem;
        if ($filterLot) $filters['lot_number'] = $filterLot;
        if ($filterPo) $filters['po_number'] = $filterPo;
        if ($filterDateFrom) $filters['date_from'] = $filterDateFrom;
        if ($filterDateTo) $filters['date_to'] = $filterDateTo;
        if ($filterReports) $filters['has_reports'] = true;
        $allHistory = $this->warehouseModel->getProductionHistoryFiltered($filters);
    } else {
        $allHistory = $this->warehouseModel->getProductionHistory();
    }

    $reportsCount = 0;
    foreach ($allHistory as $h) {
        if (($h['report_status'] ?? '') === 'pending') $reportsCount++;
    }

    usort($allHistory, function($a, $b) {
        $aReported = ($a['report_status'] ?? '') === 'pending' ? 1 : 0;
        $bReported = ($b['report_status'] ?? '') === 'pending' ? 1 : 0;
        if ($aReported !== $bReported) return $bReported - $aReported;
        return strtotime($b['date_created'] ?? '') - strtotime($a['date_created'] ?? '');
    });

    $allCustomers = array_values(array_unique(array_filter(array_column($allHistory, 'customer_name'))));
    $allItems = array_values(array_unique(array_filter(array_column($allHistory, 'item_description'))));
    $allLots = array_values(array_unique(array_filter(array_column($allHistory, 'lot_number'))));
    $allPos = array_values(array_unique(array_filter(array_column($allHistory, 'customer_po_number'))));

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
    $data['filterPo'] = $filterPo;
    $data['filterDateFrom'] = $filterDateFrom;
    $data['filterDateTo'] = $filterDateTo;
    $data['filterReports'] = $filterReports;
    $data['allCustomers'] = $allCustomers;
    $data['allItems'] = $allItems;
    $data['allLots'] = $allLots;
    $data['allPos'] = $allPos;
    $data['reportsCount'] = $reportsCount;
    $data['deliveryReportsCount'] = $this->warehouseModel->getDeliveryReportsCount();
    $data['itemList'] = $this->itemModel->getActiveItems();
    $data['page_title'] = 'Production History';
    $this->render('production_history/index', $data);
}

public function editHistoryRecord() {
    header('Content-Type: application/json');
    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        $history_id = $_POST['history_id'] ?? null;
        $new_added_quantity = intval($_POST['new_added_quantity'] ?? 0);
        $new_lot = trim($_POST['new_lot_number'] ?? '');
        $new_item_id = intval($_POST['new_item_id'] ?? 0) ?: null;
        if (!$history_id || $new_added_quantity <= 0 || empty($new_lot)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        $before = $this->warehouseModel->getProductionHistoryById($history_id);
        $result = $this->warehouseModel->editHistoryRecord($history_id, $new_added_quantity, $new_lot, $_SESSION['user_id'], $new_item_id);
        if ($result) {
            $after = $this->warehouseModel->getProductionHistoryById($history_id);
            $oldValues = $before ? [
                'added_quantity' => $before['added_quantity'] ?? null,
                'new_quantity' => $before['new_quantity'] ?? null,
                'lot_number' => $before['lot_number'] ?? null,
                'item_id' => $before['item_id'] ?? null,
                'item_description' => $before['item_description'] ?? null,
            ] : null;
            $newValues = $after ? [
                'added_quantity' => $after['added_quantity'] ?? null,
                'new_quantity' => $after['new_quantity'] ?? null,
                'lot_number' => $after['lot_number'] ?? null,
                'item_id' => $after['item_id'] ?? null,
                'item_description' => $after['item_description'] ?? null,
            ] : null;
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin', 'Updated production record for history #' . $history_id, $oldValues, $newValues, 'production_history', $history_id);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'History record not found']);
        }
    } catch (\Exception $e) {
        error_log('editHistoryRecord error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update history record']);
    }
    exit;
}

public function deleteProductionHistory() {
    $historyId = $_GET['id'] ?? null;
    if (!$historyId) {
        $_SESSION['error'] = 'Missing history id';
        header('Location: ?controller=admin&action=productionHistory');
        exit;
    }

    try {
        $deleted = $this->warehouseModel->deleteProductionHistory($historyId);
        if ($deleted) {
            $stsRef = $deleted['sts_ref'] ?? null;
            AuditModel::log($_SESSION['user_id'], 'DELETE', 'admin',
                'Deleted production history #' . $historyId . ($stsRef ? ' (' . $stsRef . ')' : ''),
                null,
                [
                    'history_id' => $historyId,
                    'sts_ref' => $stsRef,
                    'item_id' => $deleted['item_id'] ?? null,
                    'lot_number' => $deleted['lot_number'] ?? null,
                    'added_quantity' => $deleted['added_quantity'] ?? null,
                ],
                'production_history', $historyId);
            $_SESSION['success'] = 'Production history deleted, lot quantity rolled back'
                . ($stsRef ? ' and STS ' . $stsRef . ' released' : '') . '.';
        } else {
            $_SESSION['error'] = 'Production history not found';
        }
    } catch (\Exception $e) {
        $_SESSION['error'] = 'Failed to delete production history: ' . $e->getMessage();
    }

    header('Location: ?controller=admin&action=productionHistory');
    exit;
}

    public function activityLogs() {
        $auditModel = new AuditModel();
        $filters = [
            'user_id'    => $_GET['user_id'] ?? '',
            'department' => $_GET['department'] ?? '',
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
        $data['logController'] = 'admin';
        $data['departmentLocked'] = false;
        $data['hideDeptColumn'] = false;
        $data['stats'] = [
            'today_count' => AuditModel::getLogStats()['today_count'] ?? 0,
            'by_department' => [],
        ];
        $data['page_title'] = 'Activity Logs';
        $this->render('activity_logs/index', $data);
    }

    // ─── Users & Approver toggle (MRP dual-approval gate) ──────────────────────

    public function users() {
        $auditModel = new AuditModel();
        $data['users'] = $auditModel->getAllUsersWithApprover();
        $data['roleOptions'] = \App\Helpers\RoleHelper::roles();
        $data['departmentRoles'] = \App\Helpers\RoleHelper::DEPARTMENT_ROLES;
        $data['page_title'] = 'Users & Roles';
        $this->render('users/index', $data);
    }

    /** Assign/clear a user's role (admin-only; department must match the role family). */
    public function updateUserRole() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=admin&action=users');
            exit;
        }
        $userId = intval($_POST['user_id'] ?? 0);
        $role = trim($_POST['role'] ?? '');
        try {
            if ($userId <= 0) throw new \RuntimeException('Invalid user.');
            if ($userId === ($_SESSION['user_id'] ?? null)) {
                throw new \RuntimeException('You cannot change your own role.');
            }
            if ($role !== '' && !isset(\App\Helpers\RoleHelper::ROLES[$role])) {
                throw new \RuntimeException('Invalid role.');
            }
            $conn = \App\Core\BaseModel::getConnection();
            $stmt = $conn->prepare("SELECT department, username FROM users WHERE user_id = :id AND `remove` = 0");
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$user) throw new \RuntimeException('User not found.');
            // Role must belong to the user's department family.
            if ($role !== '') {
                $allowed = \App\Helpers\RoleHelper::rolesForDepartment($user['department']);
                if (!in_array($role, $allowed, true)) {
                    throw new \RuntimeException("Role '{$role}' does not belong to department '" . $user['department'] . "'.");
                }
            }
            $result = (new AuditModel())->setUserRole($userId, $role);
            $label = $role !== '' ? \App\Helpers\RoleHelper::label($role) : '(none)';
            $_SESSION['success'] = "Role for '{$user['username']}' set to {$label}.";
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin',
                "Set role of user '{$user['username']}' (department={$user['department']}) to " . ($role ?: 'NULL'),
                ['role' => $result['old_role'] ?? null],
                ['role' => $role !== '' ? $role : null],
                'user', $userId);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=admin&action=users');
        exit;
    }

    public function toggleApprover() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=admin&action=users');
            exit;
        }
        $userId = intval($_POST['user_id'] ?? 0);
        $grant = ($_POST['grant'] ?? '') === '1';
        try {
            if ($userId <= 0) throw new \RuntimeException('Invalid user.');
            if ($userId === ($_SESSION['user_id'] ?? null)) {
                throw new \RuntimeException('You cannot change your own approver flag.');
            }
            $result = (new AuditModel())->setApproverFlag($userId, $grant);
            if ($result === null) throw new \RuntimeException('User not found.');
            if ($grant) {
                $_SESSION['success'] = "Warehouse Admin approver flag granted to '{$result['username']}' (department set to warehouse).";
                AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin',
                    "Granted Warehouse Admin approver flag to user '{$result['username']}' (was department={$result['department']})",
                    ['is_approver' => 0, 'department' => $result['department']],
                    ['is_approver' => 1, 'department' => 'warehouse'],
                    'user', $userId);
            } else {
                $_SESSION['success'] = "Approver flag revoked from '{$result['username']}'.";
                AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin',
                    "Revoked Warehouse Admin approver flag from user '{$result['username']}'",
                    ['is_approver' => 1], ['is_approver' => 0], 'user', $userId);
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=admin&action=users');
        exit;
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
            'page_title' => 'Reports'
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

        $xlsx->download('admin_reports_' . date('Y-m-d') . '.xlsx');
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
        $this->render('backloads/index', $data);
    }

    // ─── Raw Materials Management ─────────────────────────────────────────────

    public function rawMaterials() {
        $search = $_GET['search'] ?? '';
        $categoryFilter = $_GET['category'] ?? '';
        $filters = [];
        if ($search) $filters['search'] = $search;
        if ($categoryFilter) $filters['category'] = $categoryFilter;

        $hasFilters = ($search !== '' || $categoryFilter !== '');
        if ($hasFilters) {
            $all = $this->rawMaterialModel->getAllFiltered($filters);
            $data['rawMaterials'] = $all;
            $data['page'] = 1;
            $data['totalPages'] = 1;
            $data['total'] = count($all);
        } else {
            $all = $this->rawMaterialModel->getAll(false);
            $pagination = Pagination::paginate($all, 15);
            $data['rawMaterials'] = $pagination['items'];
            $data['page'] = $pagination['page'];
            $data['totalPages'] = $pagination['totalPages'];
            $data['total'] = $pagination['total'];
        }
        $data['search'] = $search;
        $data['categoryFilter'] = $categoryFilter;
        $data['deliveryReportsCount'] = $this->warehouseModel->getDeliveryReportsCount();
        $data['reportsCount'] = $this->warehouseModel->getProductionReportsCount();
        $data['page_title'] = 'Raw Materials & Packaging';
        $this->render('raw_materials/index', $data);
    }

    public function rawMaterialCreate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $result = $this->rawMaterialModel->create($_POST);
                if ($result) {
                    AuditModel::log($_SESSION['user_id'], 'CREATE', 'admin', 'Created raw material: ' . ($_POST['trade_name'] ?? ''), null, ['item_code' => $_POST['item_code'] ?? '', 'trade_name' => $_POST['trade_name'] ?? ''], 'raw_material', $result);
                    $_SESSION['success'] = 'Raw material created successfully';
                    header('Location: ?controller=admin&action=rawMaterials');
                    exit;
                }
            } catch (\PDOException $e) {
                $_SESSION['error'] = $this->getDbErrorMessage($e, 'item_code', 'Item code');
                header('Location: ?controller=admin&action=rawMaterials');
                exit;
            } catch (\Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header('Location: ?controller=admin&action=rawMaterials');
                exit;
            }
        }
        header('Location: ?controller=admin&action=rawMaterials');
        exit;
    }

    public function rawMaterialUpdate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $id = $_POST['raw_material_id'] ?? null;
                $old = $this->rawMaterialModel->getById($id);
                $result = $this->rawMaterialModel->update($id, $_POST);
                if ($result) {
                    AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin', 'Updated raw material: ' . ($old['trade_name'] ?? ''), $old, ['item_code' => $_POST['item_code'] ?? '', 'trade_name' => $_POST['trade_name'] ?? ''], 'raw_material', $id);
                    $_SESSION['success'] = 'Raw material updated successfully';
                }
            } catch (\PDOException $e) {
                $_SESSION['error'] = $this->getDbErrorMessage($e, 'item_code', 'Item code');
            } catch (\Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }
        header('Location: ?controller=admin&action=rawMaterials');
        exit;
    }

    public function rawMaterialDelete() {
        $id = $_GET['id'] ?? null;
        try {
            $old = $this->rawMaterialModel->getById($id);
            $this->rawMaterialModel->softDelete($id);
            AuditModel::log($_SESSION['user_id'], 'DELETE', 'admin', 'Deactivated raw material: ' . ($old['trade_name'] ?? $id), $old, null, 'raw_material', $id);
            $_SESSION['success'] = 'Raw material deactivated';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to deactivate: ' . $e->getMessage();
        }
        header('Location: ?controller=admin&action=rawMaterials');
        exit;
    }

    public function rawMaterialToggleStatus() {
        $id = $_GET['id'] ?? null;
        try {
            $old = $this->rawMaterialModel->getById($id);
            $this->rawMaterialModel->toggleStatus($id);
            $newStatus = $old['is_active'] ? 'Inactive' : 'Active';
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin', 'Toggled raw material status to ' . $newStatus . ': ' . ($old['trade_name'] ?? $id), $old, null, 'raw_material', $id);
            $_SESSION['success'] = 'Raw material status changed to ' . $newStatus;
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update status: ' . $e->getMessage();
        }
        header('Location: ?controller=admin&action=rawMaterials');
        exit;
    }

    // ─── BOM Management ──────────────────────────────────────────────────────

    public function boms() {
        $search = $_GET['search'] ?? '';
        $filters = [];
        if ($search) $filters['search'] = $search;

        $hasFilters = ($search !== '');
        if ($hasFilters) {
            $all = $this->bomModel->getAllFiltered($filters);
            $data['boms'] = $all;
            $data['page'] = 1;
            $data['totalPages'] = 1;
            $data['total'] = count($all);
        } else {
            $all = $this->bomModel->getAll();
            $pagination = Pagination::paginate($all, 15);
            $data['boms'] = $pagination['items'];
            $data['page'] = $pagination['page'];
            $data['totalPages'] = $pagination['totalPages'];
            $data['total'] = $pagination['total'];
        }
        $data['search'] = $search;
        $data['allItems'] = $this->itemModel->getAll(false);
        // Active BOM count per FG/SFG — drives the "Existing BOM" badge in the
        // Create New BOM dropdown (complete, unlike a page slice of $boms).
        $data['itemBomCounts'] = $this->bomModel->getItemBomCounts();
        $data['deliveryReportsCount'] = $this->warehouseModel->getDeliveryReportsCount();
        $data['reportsCount'] = $this->warehouseModel->getProductionReportsCount();
        $data['customers'] = $this->customerModel->getAll();
        $data['page_title'] = 'BOM Components';
        $this->render('boms/index', $data);
    }

    // JSON: primary (default) active BOM of an FG/SFG + its components, used by
    // the Create New BOM modal to pre-fill header fields and ingredient rows
    // when the selected item already has a formulation.
    public function getPrimaryBomDetails() {
        header('Content-Type: application/json');
        try {
            $fgItemId = intval($_GET['fg_item_id'] ?? 0);
            if ($fgItemId <= 0) {
                throw new \Exception('Finished good is required.');
            }
            $details = $this->bomModel->getPrimaryBomDetails($fgItemId);
            if (!$details) {
                echo json_encode(['success' => false, 'error' => 'No active BOM found for this item.']);
                exit;
            }
            echo json_encode(['success' => true] + $details);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function bomCreate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $fgItemId = $_POST['fg_item_id'] ?? null;
                $bomCode = trim($_POST['bom_code'] ?? '');
                $fillVolume = floatval($_POST['fill_volume'] ?? 0);
                $uom = trim($_POST['uom'] ?? 'PCS');
                $batchUnitDivisor = floatval($_POST['batch_unit_divisor'] ?? 1000);
                $customerIdInput = trim($_POST['customer_id'] ?? '');
                if (!$fgItemId) {
                    throw new \Exception("Finished good item is required.");
                }
                if ($bomCode === '') {
                    throw new \Exception("BOM Code is required.");
                }
                if ($customerIdInput === '') {
                    throw new \Exception("Customer is required.");
                }
                $customer = ctype_digit($customerIdInput) ? $this->customerModel->getById((int)$customerIdInput) : false;
                if (!$customer || (int)$customer['status'] !== 1) {
                    throw new \Exception("Please select a valid customer.");
                }
                $customerId = (int)$customerIdInput;
                if ($fillVolume <= 0) {
                    throw new \Exception("Fill volume must be greater than 0.");
                }
                if ($batchUnitDivisor <= 0) {
                    $batchUnitDivisor = 1000;
                }
                // Multi-BOM: several formulations per FG are allowed; only the
                // BOM code itself must stay unique for that finished good.
                if ($this->bomModel->bomCodeExistsForItem($fgItemId, $bomCode)) {
                    throw new \Exception("BOM code \"{$bomCode}\" already exists for this finished good. Use a different BOM code.");
                }
                $result = $this->bomModel->create($fgItemId, $bomCode, $fillVolume, $uom ?: 'PCS', $batchUnitDivisor, false, $customerId, 'active');
                if ($result) {
                    AuditModel::log($_SESSION['user_id'], 'CREATE', 'admin', 'Created BOM for item #' . $fgItemId, null, ['fg_item_id' => $fgItemId, 'bom_code' => $bomCode, 'customer_id' => $customerId, 'fill_volume' => $fillVolume, 'uom' => $uom, 'batch_unit_divisor' => $batchUnitDivisor], 'bom', $result);
                    $_SESSION['success'] = 'BOM created successfully';
                    header('Location: ?controller=admin&action=bomEdit&id=' . $result);
                    exit;
                }
            } catch (\Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header('Location: ?controller=admin&action=boms');
                exit;
            }
        }
        header('Location: ?controller=admin&action=boms');
        exit;
    }

    public function bomEdit() {
        $id = $_GET['id'] ?? null;
        $data['bom'] = $this->bomModel->getById($id);
        if (!$data['bom']) {
            $_SESSION['error'] = 'BOM not found';
            header('Location: ?controller=admin&action=boms');
            exit;
        }
        $data['bomItems'] = $this->bomModel->getItemsByBomId($id);
        $data['allItems'] = $this->itemModel->getAll(false);
        $conn = \App\Core\BaseModel::getConnection();
        $stmt = $conn->prepare("SELECT item_id, item_code, item_description, item_uom
            FROM items WHERE item_type IN ('RM','PM','SFG') AND status = 1 AND `remove` = 0
            ORDER BY item_code ASC");
        $stmt->execute();
        $data['allIngredients'] = $stmt->fetchAll();
        $data['deliveryReportsCount'] = $this->warehouseModel->getDeliveryReportsCount();
        $data['reportsCount'] = $this->warehouseModel->getProductionReportsCount();
        $data['customers'] = $this->customerModel->getAll();
        $data['page_title'] = 'Edit BOM - ' . ($data['bom']['fg_name'] ?? '');
        $this->render('boms/edit', $data);
    }

    public function bomUpdate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $id = $_POST['bom_id'] ?? null;
                $fgItemId = $_POST['fg_item_id'] ?? null;
                $bomCode = trim($_POST['bom_code'] ?? '');
                $existing = $this->bomModel->getById($id);
                if (!$existing) {
                    throw new \Exception("BOM not found.");
                }
                $fillVolume = floatval($_POST['fill_volume'] ?? ($existing['fill_volume'] ?? 0));
                $uom = trim($_POST['uom'] ?? ($existing['uom'] ?? 'PCS'));
                $batchUnitDivisor = floatval($_POST['batch_unit_divisor'] ?? ($existing['batch_unit_divisor'] ?? 1000));
                if (!$fgItemId) {
                    throw new \Exception("Finished good item is required.");
                }
                if ($bomCode === '') {
                    throw new \Exception("BOM Code is required.");
                }
                $customerIdInput = trim($_POST['customer_id'] ?? '');
                if ($customerIdInput === '') {
                    throw new \Exception("Customer is required.");
                }
                $customer = ctype_digit($customerIdInput) ? $this->customerModel->getById((int)$customerIdInput) : false;
                if (!$customer || (int)$customer['status'] !== 1) {
                    throw new \Exception("Please select a valid customer.");
                }
                $customerId = (int)$customerIdInput;
                if ($fillVolume <= 0) {
                    throw new \Exception("Fill volume must be greater than 0.");
                }
                if ($batchUnitDivisor <= 0) {
                    $batchUnitDivisor = 1000;
                }
                $this->bomModel->update($id, $fgItemId, $bomCode, $fillVolume, $uom ?: 'PCS', $batchUnitDivisor, true, $customerId, 'active');
                AuditModel::log($_SESSION['user_id'], 'UPDATE', 'admin', 'Updated BOM #' . $id, null, ['fg_item_id' => $fgItemId, 'bom_code' => $bomCode, 'customer_id' => $customerId, 'fill_volume' => $fillVolume, 'uom' => $uom, 'batch_unit_divisor' => $batchUnitDivisor], 'bom', $id);
                $_SESSION['success'] = 'BOM updated';
            } catch (\Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }
        header('Location: ?controller=admin&action=bomEdit&id=' . ($_POST['bom_id'] ?? ''));
        exit;
    }

    public function bomDelete() {
        $id = $_GET['id'] ?? null;
        try {
            $old = $this->bomModel->getById($id);
            $this->bomModel->delete($id);
            AuditModel::log($_SESSION['user_id'], 'DELETE', 'admin', 'Deleted BOM: ' . ($old['fg_name'] ?? $id), $old, null, 'bom', $id);
            $_SESSION['success'] = 'BOM deleted';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete BOM: ' . $e->getMessage();
        }
        header('Location: ?controller=admin&action=boms');
        exit;
    }

    public function bomAddItem() {
        header('Content-Type: application/json');
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $bomId = $_POST['bom_id'] ?? null;
            $itemId = $_POST['item_id'] ?? null;
            $dosageRate = $_POST['dosage_rate'] ?? 0;
            $wastagePct = $_POST['wastage_allowance_pct'] ?? 0;
            $phaseCode = trim($_POST['phase_code'] ?? '') ?: '101';
            $uom = mb_substr(trim($_POST['uom'] ?? ''), 0, 50);

            if (!$bomId || !$itemId) {
                http_response_code(400);
                echo json_encode(['error' => 'BOM ID and ingredient item are required']);
                exit;
            }

            $result = $this->bomModel->addItem($bomId, $itemId, $dosageRate, $wastagePct, $phaseCode, $uom);
            AuditModel::log($_SESSION['user_id'], 'CREATE', 'admin', 'Added ingredient to BOM #' . $bomId, null, ['item_id' => $itemId, 'dosage_rate' => $dosageRate, 'phase_code' => $phaseCode, 'uom' => $uom], 'bom_item', $result);
            echo json_encode(['success' => true, 'id' => $result]);
        } catch (\Exception $e) {
            error_log('bomAddItem error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add item: ' . $e->getMessage()]);
        }
        exit;
    }

    public function bomUpdateItem() {
        header('Content-Type: application/json');
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $bomItemId = $_POST['bom_item_id'] ?? null;
            $itemId = $_POST['item_id'] ?? null;
            $dosageRate = $_POST['dosage_rate'] ?? 0;
            $wastagePct = $_POST['wastage_allowance_pct'] ?? 0;
            $phaseCode = trim($_POST['phase_code'] ?? '') ?: '101';
            $uom = mb_substr(trim($_POST['uom'] ?? ''), 0, 50);

            if (!$bomItemId || !$itemId) {
                http_response_code(400);
                echo json_encode(['error' => 'BOM item ID and ingredient item are required']);
                exit;
            }

            $result = $this->bomModel->updateItem($bomItemId, $itemId, $dosageRate, $wastagePct, $phaseCode, $uom);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            error_log('bomUpdateItem error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update item']);
        }
        exit;
    }

    public function bomRemoveItem() {
        header('Content-Type: application/json');
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $itemId = $_POST['item_id'] ?? null;
            if (!$itemId) {
                http_response_code(400);
                echo json_encode(['error' => 'Item ID required']);
                exit;
            }
            $this->bomModel->removeItem($itemId);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            error_log('bomRemoveItem error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to remove item']);
        }
        exit;
    }

    public function whereUsed() {
        header('Content-Type: application/json');
        $itemId = $_GET['item_id'] ?? null;
        if (!$itemId) {
            echo json_encode([]);
            exit;
        }
        $results = $this->bomModel->getBomsByItem($itemId);
        echo json_encode($results);
        exit;
    }

    // ─── Bulk Import: Raw Materials ────────────────────────────────────────────

    public function rawMaterialImportPreview() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['import_file'])) {
            $_SESSION['error'] = 'No file uploaded.';
            header('Location: ?controller=admin&action=rawMaterials');
            exit;
        }

        $file = $_FILES['import_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'])) {
            $_SESSION['error'] = 'Only .csv and .xlsx files are supported.';
            header('Location: ?controller=admin&action=rawMaterials');
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            $_SESSION['error'] = 'File size must be less than 5MB.';
            header('Location: ?controller=admin&action=rawMaterials');
            exit;
        }

        try {
            $rows = SpreadsheetReader::read($file['tmp_name']);
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to read file: ' . $e->getMessage();
            header('Location: ?controller=admin&action=rawMaterials');
            exit;
        }

        $validCategories = ['raw_material', 'packaging'];
        $validUoms = ['Kg', 'g', 'L', 'mL', 'pcs', 'm', 'roll'];
        $preview = [];

        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            $errors = [];
            $itemCode = trim($row['ITEM_CODE'] ?? '');
            $tradeName = trim($row['TRADE_NAME'] ?? '');
            $category = strtolower(trim($row['CATEGORY'] ?? 'raw_material'));
            $uom = trim($row['UOM'] ?? 'Kg');
            $stock = floatval($row['STOCK_ON_HAND'] ?? 0);
            $reorder = floatval($row['REORDER_LEVEL'] ?? 0);

            if ($itemCode === '') $errors[] = 'ITEM_CODE is required';
            if ($tradeName === '') $errors[] = 'TRADE_NAME is required';
            if (!in_array($category, $validCategories)) $errors[] = 'Invalid CATEGORY: ' . htmlspecialchars($category);
            if (!in_array($uom, $validUoms)) $errors[] = 'Invalid UOM: ' . htmlspecialchars($uom);

            $existing = $itemCode !== '' ? $this->rawMaterialModel->getByCode($itemCode) : null;
            $status = $existing ? 'update' : 'new';

            $preview[] = [
                'row' => $rowNum,
                'item_code' => $itemCode,
                'trade_name' => $tradeName,
                'category' => $category,
                'uom' => $uom,
                'stock_on_hand' => $stock,
                'reorder_level' => $reorder,
                'status' => $status,
                'errors' => $errors,
            ];
        }

        $_SESSION['import_preview_rm'] = $preview;
        $data['preview'] = $preview;
        $data['newCount'] = count(array_filter($preview, fn($r) => $r['status'] === 'new' && empty($r['errors'])));
        $data['updateCount'] = count(array_filter($preview, fn($r) => $r['status'] === 'update' && empty($r['errors'])));
        $data['errorCount'] = count(array_filter($preview, fn($r) => !empty($r['errors'])));
        $data['deliveryReportsCount'] = $this->warehouseModel->getDeliveryReportsCount();
        $data['reportsCount'] = $this->warehouseModel->getProductionReportsCount();
        $data['page_title'] = 'Import Raw Materials - Preview';
        $this->render('raw_materials/import_preview', $data);
    }

    public function rawMaterialImportConfirm() {
        $preview = $_SESSION['import_preview_rm'] ?? null;
        if (!$preview) {
            $_SESSION['error'] = 'No import data found. Please upload again.';
            header('Location: ?controller=admin&action=rawMaterials');
            exit;
        }
        unset($_SESSION['import_preview_rm']);

        $imported = 0;
        $updated = 0;
        $errors = 0;

        foreach ($preview as $row) {
            if (!empty($row['errors'])) {
                $errors++;
                continue;
            }
            $data = [
                'item_code' => $row['item_code'],
                'trade_name' => $row['trade_name'],
                'category' => $row['category'],
                'uom' => $row['uom'],
                'stock_on_hand' => $row['stock_on_hand'],
                'reorder_level' => $row['reorder_level'],
                'is_active' => 1
            ];
            try {
                $existing = $this->rawMaterialModel->getByCode($row['item_code']);
                $this->rawMaterialModel->upsertByCode($data);
                if ($existing) {
                    $updated++;
                } else {
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors++;
            }
        }

        AuditModel::log($_SESSION['user_id'], 'IMPORT', 'admin', "Bulk imported raw materials: {$imported} new, {$updated} updated, {$errors} errors", null, ['imported' => $imported, 'updated' => $updated, 'errors' => $errors], 'raw_material', null);
        $_SESSION['success'] = "Import complete: {$imported} new, {$updated} updated, {$errors} errors";
        header('Location: ?controller=admin&action=rawMaterials');
        exit;
    }

    // ─── Bulk Import: BOM Components ─────────────────────────────────────────────

    public function bomImportPreview() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['import_file'])) {
            $_SESSION['error'] = 'No file uploaded.';
            header('Location: ?controller=admin&action=boms');
            exit;
        }

        $file = $_FILES['import_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'])) {
            $_SESSION['error'] = 'Only .csv and .xlsx files are supported.';
            header('Location: ?controller=admin&action=boms');
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            $_SESSION['error'] = 'File size must be less than 5MB.';
            header('Location: ?controller=admin&action=boms');
            exit;
        }

        try {
            $rows = SpreadsheetReader::read($file['tmp_name']);
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to read file: ' . $e->getMessage();
            header('Location: ?controller=admin&action=boms');
            exit;
        }

        $conn = self::getConnection();
        $preview = [];

        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            $errors = [];
            $fgCode = trim($row['FG_ITEM_CODE'] ?? '');
            $bomCode = trim($row['BOM_CODE'] ?? '');
            $rmCode = trim($row['RM_CODE'] ?? '');
            $dosage = floatval($row['DOSAGE_RATE'] ?? 0);
            $wastage = floatval($row['WASTAGE_PCT'] ?? 0);
            $phaseCode = trim($row['PHASE_CODE'] ?? '');
            if ($phaseCode === '') $phaseCode = '101';

            if ($fgCode === '') $errors[] = 'FG_ITEM_CODE is required';
            if ($bomCode === '') $errors[] = 'BOM_CODE is required';
            if ($rmCode === '') $errors[] = 'RM_CODE is required';
            if ($dosage <= 0) $errors[] = 'DOSAGE_RATE must be > 0';

            $fgItem = null;
            if ($fgCode !== '') {
                $stmt = $conn->prepare("SELECT item_id, item_code, item_description FROM items WHERE item_code = :code AND `remove` = 0");
                $stmt->execute(['code' => $fgCode]);
                $fgItem = $stmt->fetch();
                if (!$fgItem) $errors[] = 'FG item not found: ' . htmlspecialchars($fgCode);
            }

            $rmItem = null;
            $ingredientItemId = null;
            if ($rmCode !== '') {
                $rmItem = $this->rawMaterialModel->getByCode($rmCode);
                if (!$rmItem) $errors[] = 'Raw material not found: ' . htmlspecialchars($rmCode);
                $stmt = $conn->prepare("SELECT item_id FROM items WHERE item_code = :code AND `remove` = 0");
                $stmt->execute(['code' => $rmCode]);
                $itemRow = $stmt->fetch();
                if ($itemRow) {
                    $ingredientItemId = $itemRow['item_id'];
                } else {
                    $errors[] = 'Ingredient not found in Items master: ' . htmlspecialchars($rmCode);
                }
            }

            $existingBom = null;
            if ($fgItem) {
                $existingBom = $this->bomModel->getBomForItem($fgItem['item_id']);
            }

            $preview[] = [
                'row' => $rowNum,
                'fg_item_code' => $fgCode,
                'fg_item_id' => $fgItem['item_id'] ?? null,
                'fg_name' => $fgItem['item_description'] ?? '',
                'bom_code' => $bomCode,
                'rm_code' => $rmCode,
                'rm_id' => $rmItem['id'] ?? null,
                'item_id' => $ingredientItemId,
                'rm_name' => $rmItem['trade_name'] ?? '',
                'dosage_rate' => $dosage,
                'wastage_pct' => $wastage,
                'phase_code' => $phaseCode,
                'existing_bom_id' => $existingBom['id'] ?? null,
                'status' => $existingBom ? 'update' : 'new',
                'errors' => $errors,
            ];
        }

        $_SESSION['import_preview_bom'] = $preview;
        $validRows = array_filter($preview, fn($r) => empty($r['errors']));
        $fgGroups = [];
        foreach ($validRows as $r) {
            $fgGroups[$r['fg_item_code']] = true;
        }
        $data['preview'] = $preview;
        $data['fgCount'] = count($fgGroups);
        $data['lineCount'] = count($validRows);
        $data['errorCount'] = count(array_filter($preview, fn($r) => !empty($r['errors'])));
        $data['deliveryReportsCount'] = $this->warehouseModel->getDeliveryReportsCount();
        $data['reportsCount'] = $this->warehouseModel->getProductionReportsCount();
        $data['page_title'] = 'Import BOM Components - Preview';
        $this->render('boms/import_preview', $data);
    }

    public function bomImportConfirm() {
        $preview = $_SESSION['import_preview_bom'] ?? null;
        if (!$preview) {
            $_SESSION['error'] = 'No import data found. Please upload again.';
            header('Location: ?controller=admin&action=boms');
            exit;
        }
        unset($_SESSION['import_preview_bom']);

        $validRows = array_filter($preview, fn($r) => empty($r['errors']));
        $fgGroups = [];
        foreach ($validRows as $r) {
            $fgGroups[$r['fg_item_code']][] = $r;
        }

        $bomsCreated = 0;
        $bomsUpdated = 0;
        $lineCount = 0;
        $errors = count(array_filter($preview, fn($r) => !empty($r['errors'])));

        foreach ($fgGroups as $fgCode => $group) {
            $first = $group[0];
            try {
                $bomId = $first['existing_bom_id'];
                if ($bomId) {
                    // Preserve existing header basis + legacy flag on import
                    $existing = $this->bomModel->getById($bomId);
                    $this->bomModel->update(
                        $bomId,
                        $first['fg_item_id'],
                        $first['bom_code'] ?: ($existing['bom_code'] ?? ''),
                        floatval($existing['fill_volume'] ?? 1.0),
                        $existing['uom'] ?? 'PCS',
                        floatval($existing['batch_unit_divisor'] ?? 1000),
                        false
                    );
                    $bomsUpdated++;
                } else {
                    // Imported recipes are not fill-volume based → legacy formula
                    $bomId = $this->bomModel->create($first['fg_item_id'], $first['bom_code'], 1.0, 'PCS', 1000, true);
                    $bomsCreated++;
                }

                $items = [];
                foreach ($group as $r) {
                    if ($r['item_id']) {
                        $items[] = [
                            'item_id' => $r['item_id'],
                            'dosage_rate' => $r['dosage_rate'],
                            'wastage_allowance_pct' => $r['wastage_pct'],
                            'phase_code' => $r['phase_code'] ?? '101'
                        ];
                        $lineCount++;
                    }
                }
                $this->bomModel->replaceBomItems($bomId, $items);
            } catch (\Exception $e) {
                $errors++;
            }
        }

        AuditModel::log($_SESSION['user_id'], 'IMPORT', 'admin', "Bulk imported BOMs: {$bomsCreated} created, {$bomsUpdated} updated, {$lineCount} line items, {$errors} errors", null, ['boms_created' => $bomsCreated, 'boms_updated' => $bomsUpdated, 'line_items' => $lineCount, 'errors' => $errors], 'bom', null);
        $_SESSION['success'] = "BOM import complete: {$bomsCreated} created, {$bomsUpdated} updated, {$lineCount} line items imported";
        header('Location: ?controller=admin&action=boms');
        exit;
    }

    // ─── Purchasing PO / Receiving PO (View Only for Admin) ────────────────────

    public function purchasingPo() {
        $filters = [
            'tab' => $_GET['tab'] ?? '',
            'status' => $_GET['status'] ?? '',
            'search' => $_GET['search'] ?? '',
        ];
        $orders = $this->warehouseModel->getPurchasingPoFiltered($filters);
        $data['page_title'] = 'Purchasing PO (View Only)';
        $data['orders'] = $orders;
        $data['filters'] = $filters;
        $data['tabCounts'] = $this->warehouseModel->getPurchasingTabCounts();
        $data['readOnly'] = true;
        $this->render('purchasingPo/index', $data);
    }

    // ─── MRP dual-approval gate, step 2: System Admin approve/reject ──────────
    // Rows arriving here have already passed Warehouse Admin approval
    // (status 'pending_sysadmin_approval'). Approve releases them to the
    // Purchasing PO module as 'requested'; reject cancels them with reason.

    private function applySysAdminMrpApproval(bool $approve, string $reason = ''): void {
        $id = intval($_POST['supplier_order_id'] ?? 0);
        if ($id <= 0) throw new \RuntimeException('Invalid requisition.');

        $order = $this->warehouseModel->getSupplierOrderStatus($id);
        if (!$order) throw new \RuntimeException('Requisition not found.');

        $stageLabel = 'System Admin';
        $label = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'unknown') . ' (' . ($_SESSION['department'] ?? '') . ')';
        $audit = "MRP requisition approved at the {$stageLabel} stage — order #{$order['supplier_order_id']} item "
            . ($order['item_code'] ?? '') . ' qty ' . $order['quantity']
            . ($order['mrp_ref'] ? ' (' . $order['mrp_ref'] . ')' : '');
        $rejectAudit = "MRP requisition rejected at the {$stageLabel} stage — order #{$order['supplier_order_id']} item "
            . ($order['item_code'] ?? '') . ' qty ' . $order['quantity']
            . ($order['mrp_ref'] ? ' (' . $order['mrp_ref'] . ')' : '')
            . ' — Reason: ' . ($reason !== '' ? $reason : 'not provided');
        if ($approve) {
            // Final run gate (two-gate flow): release straight to Procurement —
            // QC Admin no longer gates requisitions; raw material inspection
            // happens at Receiving.
            $this->warehouseModel->approveMrpRequisition($id, 'pending_sysadmin_approval', 'requested', $stageLabel, $label);
            $_SESSION['success'] = $audit . ' — released to Procurement.';
            AuditModel::log($_SESSION['user_id'], 'APPROVE', 'warehouse', $audit,
                ['status' => $order['status']], ['status' => 'requested'], 'supplier_order', $id);
            NotificationHelper::create('po', 'MRP Requisition Released to Procurement',
                (string) $order['mrp_ref'] . ' — requisition for ' . $order['item_code'] . ' passed System Admin approval; Procurement can create the PO.',
                'warehouse', '?controller=warehouse&action=purchasingPo&tab=requested');
        } else {
            $this->warehouseModel->rejectMrpRequisition($id, 'pending_sysadmin_approval', $stageLabel, $reason, $label);
            $_SESSION['success'] = $rejectAudit;
            AuditModel::log($_SESSION['user_id'], 'REJECT', 'warehouse', $rejectAudit,
                ['status' => $order['status']], ['status' => 'cancelled'], 'supplier_order', $id);
            NotificationHelper::create('po', 'MRP Requisition Rejected',
                (string) $order['mrp_ref'] . ' — requisition for ' . $order['item_code'] . ' was rejected at the ' . $stageLabel . ' stage.'
                . ($reason !== '' ? ' Reason: ' . $reason : ''),
                'warehouse', '?controller=warehouse&action=purchasingPo&tab=approval');
        }
    }

    // ─── Admin Approvals hub (System Admin) ───────────────────────────────────
    // One screen for everything awaiting a System Admin decision, not just MRP:
    //   1. MRP requisitions passed to the System Admin gate (per run)
    //   2. Purchasing PO drafts awaiting admin approval (pending_admin_approval)
    //   3. Over-receipt requests awaiting approval (receiving guardrails)
    // Each future approval-bearing module plugs in as another section here.

    public function approvals() {
        try {
            $mrpRuns = $this->warehouseModel->getMrpApprovalRuns('sysadmin');
        } catch (\Exception $e) {
            $mrpRuns = [];
        }
        try {
            $poDrafts = $this->warehouseModel->getPurchasingPoFiltered(['status' => 'pending_admin_approval']);
        } catch (\Exception $e) {
            $poDrafts = [];
        }
        try {
            $overageRequests = $this->warehouseModel->getPendingOverageApprovals();
        } catch (\Exception $e) {
            $overageRequests = [];
        }
        // Decision history — MRP runs the System Admin decided, shown as
        // run-level tracking (same table + review modal as the warehouse side).
        try {
            $decisionRuns = $this->warehouseModel->getMrpSysadminDecisionRuns(20);
        } catch (\Exception $e) {
            $decisionRuns = [];
        }
        // Everything else (PO drafts, over-receipts, ...) stays a plain log list.
        try {
            $decisionHistory = (new AuditModel())->getRecentApprovalHistory('admin', 25, 'mrp_run');
        } catch (\Exception $e) {
            $decisionHistory = [];
        }
        $data['page_title'] = 'Approvals';
        $data['mrpRuns'] = $mrpRuns;
        $data['poDrafts'] = $poDrafts;
        $data['decisionRuns'] = $decisionRuns;
        $data['overageRequests'] = $overageRequests;
        $data['decisionHistory'] = $decisionHistory;
        $data['isApprover'] = (($_SESSION['department'] ?? '') === 'admin');
        $data['pendingCount'] = count($mrpRuns) + count($poDrafts) + count($overageRequests);
        $this->render('approvals/index', $data);
    }

    /** Legacy alias — the MRP-queue tab folded into the Approvals hub. */
    public function mrpApproval() {
        $this->approvals();
    }

    // ─── MRP run-level decision (per-run Approve All / Reject, System Admin) ──

  private function decideMrpRunRequest(bool $approve): void {
  $runId = intval($_POST['mrp_run_id'] ?? 0);
  if ($runId <= 0) throw new \RuntimeException('Invalid MRP run.');
  $label = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'unknown') . ' (' . ($_SESSION['department'] ?? '') . ')';
  $audit = "MRP run decided at the System Admin stage - run #{$runId}";
  if ($approve) {
  // Two-gate flow: System Admin is the FINAL run gate — requisition runs
  // release straight to Procurement; zero-lacking runs complete as approved.
  // (QC Admin no longer gates runs — raw material inspection happens at Receiving.)
  $runStatus = $this->warehouseModel->getMrpRunApprovalStatus($runId);
  $zeroLacking = !empty($runStatus);
  $targetStatus = $zeroLacking ? 'approved' : 'requested';
  $result = $this->warehouseModel->decideMrpRun($runId, 'pending_sysadmin_approval', $targetStatus, $label);
  $_SESSION['success'] = 'MRP Run ' . ($result['mrp_ref'] ?: ('#' . $runId)) . ' approved - '
  . (!empty($result['zero_lacking'])
  ? 'no purchase requests (nothing lacking); run completed.'
  : $result['moved'] . ' requisition(s) released to Procurement.');
  AuditModel::log($_SESSION['user_id'], 'APPROVE', 'warehouse', $audit
  . ' (' . $result['mrp_ref'] . '), ' . $result['moved'] . ' requisition(s) released to Procurement',
  null, ['status' => 'requested'], 'mrp_run', $runId);
  NotificationHelper::create('po', 'MRP Run Released to Procurement',
  ($result['mrp_ref'] ?: ('MRP run #' . $runId)) . ' - passed System Admin approval ('
  . (!empty($result['zero_lacking'])
  ? 'no purchase requests - nothing lacking'
  : $result['moved'] . ' requisition(s)')
  . '); Procurement can now create the PO. Raw material inspection happens at Receiving.',
  'warehouse', '?controller=warehouse&action=purchasingPo&tab=requested');
  } else {
            $reason = trim($_POST['reject_reason'] ?? '');
            $result = $this->warehouseModel->decideMrpRun($runId, 'pending_sysadmin_approval', 'cancelled', $label, $reason);
            $_SESSION['success'] = 'MRP Run ' . ($result['mrp_ref'] ?: ('#' . $runId)) . ' rejected — '
                . (!empty($result['zero_lacking'])
                    ? 'no purchase requests were involved (nothing lacking).'
                    : $result['moved'] . ' requisition(s) cancelled.');
            AuditModel::log($_SESSION['user_id'], 'REJECT', 'warehouse', $audit
                . ' (' . $result['mrp_ref'] . '), ' . $result['moved'] . ' requisition(s) cancelled — Reason: '
                . ($reason !== '' ? $reason : 'not provided'),
                null, ['status' => 'cancelled'], 'mrp_run', $runId);
            NotificationHelper::create('po', 'MRP Run Rejected',
                ($result['mrp_ref'] ?: ('MRP run #' . $runId)) . ' — ' . $result['moved']
                . ' requisition(s) were rejected by System Admin. Reason: '
                . ($reason !== '' ? $reason : 'not provided'),
                'warehouse', '?controller=warehouse&action=mrpApproval');
        }
    }

    public function approveMrpRunDraft() {
        try {
            if (($_SESSION['department'] ?? '') !== 'admin') {
                $_SESSION['error'] = 'Only System Admins can complete this approval.';
            } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $_SESSION['error'] = 'POST required.';
            } else {
                $this->decideMrpRunRequest(true);
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=admin&action=approvals');
        exit;
    }

    public function rejectMrpRunDraft() {
        try {
            if (($_SESSION['department'] ?? '') !== 'admin') {
                $_SESSION['error'] = 'Only System Admins can complete this approval.';
            } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $_SESSION['error'] = 'POST required.';
            } else {
                $this->decideMrpRunRequest(false);
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=admin&action=approvals');
        exit;
    }

    public function approveMrpDraft() {
        try {
            if (($_SESSION['department'] ?? '') !== 'admin') {
                $_SESSION['error'] = 'Only System Admins can complete the second approval stage.';
            } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $_SESSION['error'] = 'POST required.';
            } else {
                $this->applySysAdminMrpApproval(true);
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=admin&action=approvals');
        exit;
    }

    public function rejectMrpDraft() {
        try {
            if (($_SESSION['department'] ?? '') !== 'admin') {
                $_SESSION['error'] = 'Only System Admins can complete the second approval stage.';
            } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $_SESSION['error'] = 'POST required.';
            } else {
                $this->applySysAdminMrpApproval(false, trim($_POST['reject_reason'] ?? ''));
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=admin&action=approvals');
        exit;
    }

    public function receivingPo() {
        $filters = [
            'tab' => $_GET['tab'] ?? '',
            'status' => $_GET['status'] ?? '',
            'supplier' => $_GET['supplier'] ?? '',
            'search' => $_GET['search'] ?? '',
        ];
        $orders = $this->warehouseModel->getReceivingPoFiltered($filters);
        $overageRequests = $this->warehouseModel->getPendingOverageApprovals();
        $suppliers = $this->warehouseModel->getPurchasingPoSuppliers();

        $data['page_title'] = 'Receiving Purchasing PO';
        $data['orders'] = $orders;
        $data['filters'] = $filters;
        $data['suppliers'] = $suppliers;
        $data['overageRequests'] = $overageRequests;
        $data['tabCounts'] = $this->warehouseModel->getReceivingTabCounts() + ['overage' => count($overageRequests)];
        $data['readOnly'] = false;
        $this->render('receivingPo/index', $data);
    }

    // ─── Purchasing PO Admin Approval Gate, step 1 of 2 (only approver here) ──
    // Procurement-processed POs arrive as drafts (pending_admin_approval).
    // System Admin approval activates them for Receiving; no Warehouse Admin
    // step exists on this gate.

    public function approvePoDraft() {
        try {
            if (($_SESSION['department'] ?? '') !== 'admin') {
                $_SESSION['error'] = 'Only System Admins can approve processed PO drafts.';
            } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $_SESSION['error'] = 'POST required.';
            } else {
                $id = intval($_POST['supplier_order_id'] ?? 0);
                $order = $id > 0 ? $this->warehouseModel->getSupplierOrderStatus($id) : null;
                if (!$order) throw new \RuntimeException('Purchasing PO not found.');
                $label = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'unknown') . ' (' . ($_SESSION['department'] ?? '') . ')';
                $this->warehouseModel->approvePurchaseOrderDraft($id, $label);
                $audit = 'Processed PO draft approved — order #' . $id . ' item ' . ($order['item_code'] ?? '')
                    . ' qty ' . $order['quantity'] . ($order['mrp_ref'] ? ' (' . $order['mrp_ref'] . ')' : '')
                    . ' — now Active for receiving.';
                $_SESSION['success'] = $audit;
                AuditModel::log($_SESSION['user_id'], 'APPROVE', 'warehouse', $audit,
                    ['status' => $order['status']], ['status' => 'pending'], 'supplier_order', $id);
                NotificationHelper::create('po', 'PO Approved for Receiving',
                    ($order['po_ref'] ?: ('SO #' . $id)) . ' — PO for ' . $order['item_code'] . ' qty '
                    . $order['quantity'] . ' passed System Admin approval and is now Active for receiving.',
                    'warehouse', '?controller=warehouse&action=receivingPo');
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=admin&action=approvals');
        exit;
    }

    public function rejectPoDraft() {
        try {
            if (($_SESSION['department'] ?? '') !== 'admin') {
                $_SESSION['error'] = 'Only System Admins can reject processed PO drafts.';
            } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $_SESSION['error'] = 'POST required.';
            } else {
                $id = intval($_POST['supplier_order_id'] ?? 0);
                $order = $id > 0 ? $this->warehouseModel->getSupplierOrderStatus($id) : null;
                if (!$order) throw new \RuntimeException('Purchasing PO not found.');
                $reason = trim($_POST['reject_reason'] ?? '');
                $label = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'unknown') . ' (' . ($_SESSION['department'] ?? '') . ')';
                $this->warehouseModel->rejectPurchaseOrderDraft($id, $reason, $label);
                $audit = 'Processed PO draft rejected — order #' . $id . ' item ' . ($order['item_code'] ?? '')
                    . ' qty ' . $order['quantity'] . ' — Reason: ' . ($reason !== '' ? $reason : 'not provided');
                $_SESSION['success'] = $audit . ' (PO cancelled).';
                AuditModel::log($_SESSION['user_id'], 'REJECT', 'warehouse', $audit,
                    ['status' => $order['status']], ['status' => 'cancelled'], 'supplier_order', $id);
                NotificationHelper::create('po', 'PO Draft Rejected',
                    ($order['po_ref'] ?: ('SO #' . $id)) . ' — PO draft for ' . $order['item_code']
                    . ' was rejected by System Admin.' . ($reason !== '' ? ' Reason: ' . $reason : ''),
                    'warehouse', '?controller=warehouse&action=purchasingPo&tab=approval');
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=admin&action=approvals');
        exit;
    }

    // ─── Group-level PO draft decision (one decision per source/MRP run) ──────

    /** JSON line details for a recorded approval-group decision (history review). */
    public function groupLineDetails() {
        header('Content-Type: application/json');
        try {
            if (($_SESSION['department'] ?? '') !== 'admin') {
                throw new \RuntimeException('Only System Admins can review these lines.');
            }
            $ids = array_values(array_filter(array_map('intval', explode(',', $_GET['ids'] ?? ''))));
            $out = [];
            foreach ($ids as $id) {
                $o = $this->warehouseModel->getSupplierOrderStatus($id);
                if (!$o) continue;
                $out[] = [
                    'id' => $id,
                    'po' => $o['po_ref'] ?: '-',
                    'mrp' => $o['mrp_ref'] ?? '',
                    'supplier' => $o['supplier_name'] ?? '',
                    'item' => $o['item_code'] ?? '',
                    'desc' => $o['item_description'] ?? '',
                    'qty' => $o['quantity'] ?? 0,
                    'uom' => $o['uom'] ?? '',
                    'date' => !empty($o['order_date']) ? date('m/d/Y', strtotime($o['order_date'])) : '-',
                    'status' => $o['status'] ?? '',
                ];
            }
            echo json_encode($out);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /** CSV of supplier order ids on the hidden group form. */
    private function poGroupIds(): array {
        $ids = array_values(array_filter(array_map('intval', explode(',', $_POST['supplier_order_ids'] ?? ''))));
        if (!count($ids)) throw new \RuntimeException('This group has no PO lines.');
        return $ids;
    }

    public function approvePoGroup() {
        try {
            if (($_SESSION['department'] ?? '') !== 'admin') {
                $_SESSION['error'] = 'Only System Admins can approve processed PO drafts.';
            } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $_SESSION['error'] = 'POST required.';
            } else {
                $ids = $this->poGroupIds();
                $groupRef = trim($_POST['group_ref'] ?? '') ?: ('#' . implode('/#', $ids));
                $label = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'unknown') . ' (' . ($_SESSION['department'] ?? '') . ')';
                $moved = 0;
                $fail = [];
                $statesBefore = [];
                foreach ($ids as $id) {
                    $order = $this->warehouseModel->getSupplierOrderStatus($id);
                    if (!$order) { $fail[] = $id; continue; }
                    try {
                        $statesBefore[] = ['supplier_order_id' => $id, 'status' => $order['status']];
                        $this->warehouseModel->approvePurchaseOrderDraft($id, $label);
                        $moved++;
                    } catch (\Exception $inner) {
                        $fail[] = $id;
                    }
                }
                if (!$moved) throw new \RuntimeException('No PO lines in this group could be approved — they may already be decided.');
                $audit = 'PO draft group decided at the System Admin stage — ' . $groupRef . ' — '
                    . $moved . ' PO line(s) activated for Receiving';
                if (count($fail)) $audit .= ' (' . count($fail) . ' line(s) skipped — already decided)';
                $_SESSION['success'] = 'Approved ' . $moved . ' PO line(s) in ' . $groupRef . ' — active for receiving.'
                    . (count($fail) ? ' ' . count($fail) . ' skipped (already decided).' : '');
                AuditModel::log($_SESSION['user_id'], 'APPROVE', 'warehouse', $audit,
                    $statesBefore ? ['lines' => $statesBefore] : null,
                    ['status' => 'pending'], 'po_draft_group', null);
                NotificationHelper::create('po', 'PO Drafts Approved for Receiving',
                    $groupRef . ' — ' . $moved . ' PO line(s) passed System Admin approval and are now Active for receiving.',
                    'warehouse', '?controller=warehouse&action=receivingPo');
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=admin&action=approvals');
        exit;
    }

    public function rejectPoGroup() {
        try {
            if (($_SESSION['department'] ?? '') !== 'admin') {
                $_SESSION['error'] = 'Only System Admins can reject processed PO drafts.';
            } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $_SESSION['error'] = 'POST required.';
            } else {
                $ids = $this->poGroupIds();
                $reason = trim($_POST['reject_reason'] ?? '');
                if ($reason === '') throw new \RuntimeException('A rejection reason is required.');
                $groupRef = trim($_POST['group_ref'] ?? '') ?: ('#' . implode('/#', $ids));
                $label = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'unknown') . ' (' . ($_SESSION['department'] ?? '') . ')';
                $moved = 0;
                $fail = [];
                $statesBefore = [];
                foreach ($ids as $id) {
                    $order = $this->warehouseModel->getSupplierOrderStatus($id);
                    if (!$order) { $fail[] = $id; continue; }
                    try {
                        $statesBefore[] = ['supplier_order_id' => $id, 'status' => $order['status']];
                        $this->warehouseModel->rejectPurchaseOrderDraft($id, $reason, $label);
                        $moved++;
                    } catch (\Exception $inner) {
                        $fail[] = $id;
                    }
                }
                if (!$moved) throw new \RuntimeException('No PO lines in this group could be rejected — they may already be decided.');
                $audit = 'PO draft group decided at the System Admin stage — ' . $groupRef . ' — '
                    . $moved . ' PO line(s) cancelled — Reason: ' . $reason;
                if (count($fail)) $audit .= ' (' . count($fail) . ' line(s) skipped — already decided)';
                $_SESSION['success'] = 'Rejected ' . $moved . ' PO line(s) in ' . $groupRef . ' (cancelled).'
                    . (count($fail) ? ' ' . count($fail) . ' skipped (already decided).' : '');
                AuditModel::log($_SESSION['user_id'], 'REJECT', 'warehouse', $audit,
                    $statesBefore ? ['lines' => $statesBefore] : null,
                    ['status' => 'cancelled'], 'po_draft_group', null);
                NotificationHelper::create('po', 'PO Draft Group Rejected',
                    $groupRef . ' — ' . $moved . ' PO line(s) were rejected by the System Admin. Reason: ' . $reason,
                    'warehouse', '?controller=warehouse&action=purchasingPo&tab=approval');
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=admin&action=approvals');
        exit;
    }

    // ─── Receiving overage approval gate (System Admin) ───────────────────────

    public function approveOverageRequest() {
        try {
            if (($_SESSION['department'] ?? '') !== 'admin') {
                $_SESSION['error'] = 'Only System Admins can approve over-receipts.';
            } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $_SESSION['error'] = 'POST required.';
            } else {
                $id = intval($_POST['supplier_order_id'] ?? 0);
                $label = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'unknown') . ' (' . ($_SESSION['department'] ?? '') . ')';
                $overQty = $this->warehouseModel->approveOverageRequest($id, $label);
                $order = $this->warehouseModel->getSupplierOrderStatus($id);
                $audit = 'Over-receipt approved — order #' . $id . ' item ' . ($order['item_code'] ?? '')
                    . ' may receive ' . formatQty($overQty) . ' beyond the ordered ' . formatQty($order['quantity'] ?? 0)
                    . ' before QC.';
                $_SESSION['success'] = $audit;
                AuditModel::log($_SESSION['user_id'], 'APPROVE', 'warehouse', $audit,
                    ['overage_request_status' => 'pending'], ['overage_request_status' => 'approved', 'overage_approved_qty' => $overQty],
                    'supplier_order', $id);
                NotificationHelper::create('po', 'Over-Receipt Approved',
                    ($order['po_ref'] ?: ('SO #' . $id)) . ' — over-receipt of ' . formatQty($overQty)
                    . ' beyond ordered approved. Warehouse can now submit the shipment to QC within the approved cap.',
                    'warehouse', '?controller=warehouse&action=receivingPo');
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=admin&action=receivingPo&tab=overage');
        exit;
    }

    public function rejectOverageRequest() {
        try {
            if (($_SESSION['department'] ?? '') !== 'admin') {
                $_SESSION['error'] = 'Only System Admins can reject over-receipt requests.';
            } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $_SESSION['error'] = 'POST required.';
            } else {
                $id = intval($_POST['supplier_order_id'] ?? 0);
                $reason = trim($_POST['reject_reason'] ?? '');
                $label = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'unknown') . ' (' . ($_SESSION['department'] ?? '') . ')';
                $overQty = $this->warehouseModel->rejectOverageRequest($id, $reason, $label);
                $order = $this->warehouseModel->getSupplierOrderStatus($id);
                $audit = 'Over-receipt request rejected — order #' . $id . ' item ' . ($order['item_code'] ?? '')
                    . ' requested +' . formatQty($overQty) . ' beyond ordered — Reason: '
                    . ($reason !== '' ? $reason : 'not provided');
                $_SESSION['success'] = $audit;
                AuditModel::log($_SESSION['user_id'], 'REJECT', 'warehouse', $audit,
                    ['overage_request_status' => 'pending'], ['overage_request_status' => 'rejected'],
                    'supplier_order', $id);
                NotificationHelper::create('po', 'Over-Receipt Request Rejected',
                    ($order['po_ref'] ?: ('SO #' . $id)) . ' — over-receipt request (' . formatQty($overQty)
                    . ' beyond ordered) was rejected.' . ($reason !== '' ? ' Reason: ' . $reason : '')
                    . ' Only the ordered quantity remains receivable.',
                    'warehouse', '?controller=warehouse&action=receivingPo');
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=admin&action=receivingPo&tab=overage');
        exit;
    }

    // ─── Group-level over-receipt decision (one decision per source/MRP run) ───

    private function overageGroupDecision(bool $approve): void {
        try {
            if (($_SESSION['department'] ?? '') !== 'admin') {
                $_SESSION['error'] = 'Only System Admins can decide over-receipt requests.';
            } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $_SESSION['error'] = 'POST required.';
            } else {
                $ids = $this->poGroupIds();
                $reason = '';
                if (!$approve) {
                    $reason = trim($_POST['reject_reason'] ?? '');
                    if ($reason === '') throw new \RuntimeException('A rejection reason is required.');
                }
                $groupRef = trim($_POST['group_ref'] ?? '') ?: ('Over-receipt request #' . implode('/#', $ids));
                $label = ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'unknown') . ' (' . ($_SESSION['department'] ?? '') . ')';
                $moved = 0;
                $fail = [];
                $statesBefore = [];
                $qtySummary = [];
                foreach ($ids as $id) {
                    $order = $this->warehouseModel->getSupplierOrderStatus($id);
                    if (!$order) { $fail[] = $id; continue; }
                    try {
                        if ($approve) {
                            $overQty = $this->warehouseModel->approveOverageRequest($id, $label);
                        } else {
                            $overQty = $this->warehouseModel->rejectOverageRequest($id, $reason, $label);
                        }
                        $statesBefore[] = ['supplier_order_id' => $id, 'overage_request_status' => 'pending'];
                        $qtySummary[] = ($order['item_code'] ?? '#' . $id) . ' +' . formatQty($overQty);
                        $moved++;
                    } catch (\Exception $inner) {
                        $fail[] = $id;
                    }
                }
                if (!$moved) throw new \RuntimeException('No over-receipt requests in this group could be decided — they may already be resolved.');
                $action = $approve ? 'approved' : 'rejected';
                $audit = 'Over-receipt request group ' . $action . ' — ' . $groupRef . ' — '
                    . $moved . ' line(s) — ' . implode(', ', $qtySummary)
                    . (!$approve ? ' — Reason: ' . $reason : '');
                if (count($fail)) $audit .= ' (' . count($fail) . ' line(s) skipped — already resolved)';
                $_SESSION['success'] = ucfirst($action) . ' ' . $moved . ' over-receipt request(s) in ' . $groupRef . '.'
                    . (count($fail) ? ' ' . count($fail) . ' skipped (already resolved).' : '');
                AuditModel::log($_SESSION['user_id'], $approve ? 'APPROVE' : 'REJECT', 'warehouse', $audit,
                    $statesBefore ? ['lines' => $statesBefore] : null,
                    ['overage_request_status' => $approve ? 'approved' : 'rejected'],
                    'overage_group', null);
                NotificationHelper::create('po', $approve ? 'Over-Receipt Requests Approved' : 'Over-Receipt Requests Rejected',
                    $groupRef . ' — ' . $moved . ' over-receipt request(s) ' . $action . ' by the System Admin.'
                    . (!$approve ? ' Reason: ' . $reason : ''),
                    'warehouse', '?controller=warehouse&action=receivingPo');
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=admin&action=approvals');
        exit;
    }

    public function approveOverageGroup() {
        $this->overageGroupDecision(true);
    }

    public function rejectOverageGroup() {
        $this->overageGroupDecision(false);
    }

    private function render($view, $data = []) {
        $data['reportedCount'] = $this->warehouseModel->getReportedRemarksCount();
        $data['reportsCount'] = $this->warehouseModel->getProductionReportsCount();
        // Sidebar badge: everything awaiting a System Admin decision (MRP gate,
        // PO drafts, over-receipt requests — see WarehouseModel::getAdminApprovalsCount).
        if (($_SESSION['department'] ?? '') === 'admin') {
            try {
                $data['approvalsCount'] = $this->warehouseModel->getAdminApprovalsCount();
            } catch (\Exception $e) {
                $data['approvalsCount'] = 0;
            }
        }
        extract($data);
        ob_start();
        include __DIR__ . "/../views/{$view}.php";
        $content = ob_get_clean();
        include __DIR__ . "/../views/layouts/main.php";
    }
}