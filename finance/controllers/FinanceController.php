<?php
namespace App\Controllers;

use App\Models\FinanceModel;
use App\Models\PriceListModel;
use App\Models\GlAccountModel;
use App\Models\AuditModel;
use App\Helpers\Pagination;
use App\Helpers\CsvExport;
use App\Helpers\SpreadsheetReader;
use App\Helpers\NotificationHelper;

class FinanceController {
    private $financeModel;
    private $priceListModel;
    private $glAccountModel;
    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?controller=auth&action=login');
            exit;
        }
        $action = $_GET['action'] ?? '';
        $allowedActions = ['getPODetails', 'getDeliveryDetails', 'getUnpricedItemsByCustomer'];
        if (!in_array($action, $allowedActions) && ($_SESSION['department'] ?? '') !== 'finance') {
            header('Location: ' . \App\Helpers\RoleHelper::homeUrl());
            exit;
        }
        $this->financeModel = new FinanceModel();
        $this->priceListModel = new PriceListModel();
        $this->glAccountModel = new GlAccountModel();
    }

    public function index() {
        $data['page_title'] = 'Finance Dashboard';
        $data['stats'] = $this->financeModel->getFinanceStats();
        $data['recent_deliveries'] = array_slice($this->financeModel->getAllDeliveries(), 0, 10);

        $this->render('dashboard', $data);
    }

    public function purchaseOrders() {
        $search = $_GET['search'] ?? '';
        $filterCustomer = $_GET['filter_customer'] ?? '';
        $filterItem = $_GET['filter_item'] ?? '';
        $filterDate = $_GET['filter_date'] ?? '';

        $hasFilter = $search || $filterCustomer || $filterItem || $filterDate;
        if ($hasFilter) {
            $filters = [];
            if ($search) $filters['search'] = $search;
            if ($filterCustomer) $filters['customer_name'] = $filterCustomer;
            if ($filterItem) $filters['item_description'] = $filterItem;
            if ($filterDate) $filters['date'] = $filterDate;
            $allPOs = $this->financeModel->getAllPurchaseOrdersFiltered($filters);
            $allCustomers = array_values(array_unique(array_filter(array_column($allPOs, 'customer_name'))));
            $pagination = ['items' => $allPOs, 'page' => 1, 'perPage' => count($allPOs), 'total' => count($allPOs), 'totalPages' => 1, 'hasNext' => false, 'hasPrev' => false];
        } else {
            $allPOs = $this->financeModel->getAllPurchaseOrders();
            $allCustomers = array_values(array_unique(array_filter(array_column($allPOs, 'customer_name'))));
            $pagination = Pagination::paginate($allPOs, 10);
        }

        $data['purchase_orders'] = $pagination['items'];
        $data['page'] = $pagination['page'];
        $data['totalPages'] = $pagination['totalPages'];
        $data['total'] = $pagination['total'];
        $data['search'] = $search;
        $data['filterCustomer'] = $filterCustomer;
        $data['filterItem'] = $filterItem;
        $data['filterDate'] = $filterDate;
        $data['allCustomers'] = $allCustomers;
        $data['po_items_map'] = $this->financeModel->getAllPurchaseOrderItems();

        $data['page_title'] = 'Customer PO';
        $this->render('purchase_orders/index', $data);
    }

    public function readyToDeliver() {
        $search = $_GET['search'] ?? '';
        if ($search) {
            $readyPOs = $this->financeModel->getPOsReadyToDeliverFiltered(['search' => $search]);
        } else {
            $readyPOs = $this->financeModel->getPOsReadyToDeliver();
        }
        $pagination = Pagination::paginate($readyPOs, 10);
        $data['purchase_orders'] = $pagination['items'];
        $data['page'] = $pagination['page'];
        $data['totalPages'] = $pagination['totalPages'];
        $data['total'] = $pagination['total'];
        $data['search'] = $search;
        $data['po_items_map'] = $this->financeModel->getAllPurchaseOrderItems();

        $data['page_title'] = 'Ready to Deliver';
        $this->render('purchase_orders/ready_to_deliver', $data);
    }

    public function deliveries() {
        $search = $_GET['search'] ?? '';
        $filterCustomer = $_GET['filter_customer'] ?? '';
        $filterItem = $_GET['filter_item'] ?? '';
        $filterSI = $_GET['filter_si'] ?? '';

        $hasFilter = $search || $filterCustomer || $filterItem || $filterSI;
        if ($hasFilter) {
            $filters = [];
            if ($search) $filters['search'] = $search;
            if ($filterCustomer) $filters['customer_name'] = $filterCustomer;
            if ($filterItem) $filters['item_description'] = $filterItem;
            if ($filterSI) $filters['si_number'] = $filterSI;
            $allDeliveries = $this->financeModel->getAllDeliveriesFiltered($filters);
            $allCustomers = array_values(array_unique(array_filter(array_column($allDeliveries, 'customer_name'))));
            $allSINumbers = array_values(array_unique(array_filter(array_column($allDeliveries, 'si_number'))));
            $pagination = ['items' => $allDeliveries, 'page' => 1, 'perPage' => count($allDeliveries), 'total' => count($allDeliveries), 'totalPages' => 1, 'hasNext' => false, 'hasPrev' => false];
        } else {
            $allDeliveries = $this->financeModel->getAllDeliveries();
            $allCustomers = array_values(array_unique(array_filter(array_column($allDeliveries, 'customer_name'))));
            $allSINumbers = array_values(array_unique(array_filter(array_column($allDeliveries, 'si_number'))));
            $pagination = Pagination::paginate($allDeliveries, 10);
        }

        $data['deliveries'] = $pagination['items'];
        $data['page'] = $pagination['page'];
        $data['totalPages'] = $pagination['totalPages'];
        $data['total'] = $pagination['total'];
        $data['search'] = $search;
        $data['filterCustomer'] = $filterCustomer;
        $data['filterItem'] = $filterItem;
        $data['filterSI'] = $filterSI;
        $data['allCustomers'] = $allCustomers;
        $data['allSINumbers'] = $allSINumbers;

        $data['page_title'] = 'Sales Invoice';
        $this->render('deliveries/index', $data);
    }

    public function viewDelivery() {
        $id = $_GET['id'] ?? null;
        $delivery = $this->financeModel->getDeliveryById($id);

        if (!$delivery) {
            $_SESSION['error'] = 'Delivery not found';
            header('Location: ?controller=finance&action=deliveries');
            exit;
        }

        $data['delivery'] = $delivery;
        $allReceipts = $this->financeModel->getReceiptsByDelivery($id);
        $data['dr_receipts'] = array_filter($allReceipts, fn($r) => ($r['type'] ?? 'dr') === 'dr');
        $data['si_receipts'] = array_filter($allReceipts, fn($r) => ($r['type'] ?? 'dr') === 'si');
        $data['poi_item'] = $this->financeModel->getDeliveryPoiItem($delivery['poi_id'] ?? 0);
        $data['price_list'] = !empty($data['poi_item']['item_id']) ? $this->priceListModel->getByItemId($data['poi_item']['item_id']) : null;
        $siNumber = $delivery['si_number'] ?? null;
        $data['page_title'] = $siNumber ? 'Sales Invoice ' . $siNumber : 'Sales Invoice';
        $this->render('deliveries/view', $data);
    }

    public function saveSINumber() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $delivery_id = $_POST['delivery_id'] ?? null;
                $si_number = trim($_POST['si_number'] ?? '');

                if (!$delivery_id) {
                    $_SESSION['error'] = 'Missing delivery ID';
                    header('Location: ?controller=finance&action=deliveries');
                    exit;
                }

                if (empty($si_number)) {
                    $_SESSION['error'] = 'SI number is required';
                    header("Location: ?controller=finance&action=viewDelivery&id={$delivery_id}");
                    exit;
                }

                $financeModel = new \App\Models\FinanceModel();

                if ($financeModel->isSINumberTaken($si_number, $delivery_id)) {
                    $_SESSION['error'] = 'SI number "' . $si_number . '" is already used on another delivery';
                    header("Location: ?controller=finance&action=viewDelivery&id={$delivery_id}");
                    exit;
                }

                $financeModel->saveSINumber($delivery_id, $si_number);

                AuditModel::log($_SESSION['user_id'], 'UPDATE', 'finance', 'Set SI number ' . $si_number . ' on delivery #' . $delivery_id, null, ['si_number' => $si_number], 'delivery', $delivery_id);

                $_SESSION['success'] = 'SI number ' . $si_number . ' saved successfully';
                header("Location: ?controller=finance&action=viewDelivery&id={$delivery_id}");
                exit;
            } catch (\Exception $e) {
                error_log('saveSINumber error: ' . $e->getMessage());
                $_SESSION['error'] = 'Failed to save SI number';
                header("Location: ?controller=finance&action=deliveries");
                exit;
            }
        }
    }

    public function uploadReceipt() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $delivery_id = $_POST['delivery_id'] ?? null;
                $po_id = $_POST['po_id'] ?? null;

                if (!$delivery_id || !$po_id) {
                    $_SESSION['error'] = 'Invalid delivery or PO ID';
                    header('Location: ?controller=finance&action=deliveries');
                    exit;
                }

                if (!isset($_FILES['receipt_file']) || $_FILES['receipt_file']['error'] !== UPLOAD_ERR_OK) {
                    $_SESSION['error'] = 'Please select a file to upload';
                    header("Location: ?controller=finance&action=viewDelivery&id={$delivery_id}");
                    exit;
                }

                $file = $_FILES['receipt_file'];
                $allowedTypes = [
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ];

                if (!in_array($file['type'], $allowedTypes)) {
                    $_SESSION['error'] = 'Invalid file type. Allowed: JPG, PNG, GIF, WebP, PDF, DOC, DOCX';
                    header("Location: ?controller=finance&action=viewDelivery&id={$delivery_id}");
                    exit;
                }

                $maxSize = 10 * 1024 * 1024;
                if ($file['size'] > $maxSize) {
                    $_SESSION['error'] = 'File size must be less than 10MB';
                    header("Location: ?controller=finance&action=viewDelivery&id={$delivery_id}");
                    exit;
                }

                $uploadDir = __DIR__ . '/../../uploads/receipts/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = 'receipt_' . $delivery_id . '_' . time() . '.' . $extension;
                $filePath = $uploadDir . $fileName;

                if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                    $_SESSION['error'] = 'Failed to upload file';
                    header("Location: ?controller=finance&action=viewDelivery&id={$delivery_id}");
                    exit;
                }

                $this->financeModel->attachReceipt([
                    'delivery_id' => $delivery_id,
                    'po_id' => $po_id,
                    'file_name' => $file['name'],
                    'file_path' => 'uploads/receipts/' . $fileName,
                    'file_type' => $file['type'],
                    'file_size' => $file['size'],
                    'type' => 'si',
                    'uploaded_by' => $_SESSION['user_id']
                ]);
                $poFinance = (new \App\Models\FinanceModel())->getPurchaseOrderById($po_id);
                AuditModel::log($_SESSION['user_id'], 'CREATE', 'finance', 'Uploaded sales invoice attachment ' . $fileName . ' for ' . ($poFinance['customer_po_number'] ?? $poFinance['po_number'] ?? 'PO #' . $po_id) . ' on delivery #' . $delivery_id, null, ['file' => $fileName], 'receipt', $po_id);

                $_SESSION['success'] = 'Sales invoice attachment uploaded successfully';
                header("Location: ?controller=finance&action=viewDelivery&id={$delivery_id}");
                exit;
            } catch (\Exception $e) {
                error_log('uploadReceipt error: ' . $e->getMessage());
                $_SESSION['error'] = 'Failed to save receipt to database';
                header("Location: ?controller=finance&action=viewDelivery&id={$_POST['delivery_id']}");
                exit;
            }
        }
    }

    public function deleteReceipt() {
        $receipt_id = $_GET['id'] ?? null;
        $delivery_id = $_GET['delivery_id'] ?? null;

        try {
            if ($receipt_id) {
                $receipt = $this->financeModel->getReceiptById($receipt_id);
                if ($receipt && file_exists(__DIR__ . '/../../' . $receipt['file_path'])) {
                    unlink(__DIR__ . '/../../' . $receipt['file_path']);
                }
                $this->financeModel->deleteReceipt($receipt_id);
                AuditModel::log($_SESSION['user_id'], 'DELETE', 'finance', 'Deleted delivery receipt ' . basename($receipt['file_path'] ?? '') . ' from delivery #' . $delivery_id, ['path' => $receipt['file_path'] ?? ''], null, 'receipt', $receipt_id);
                $_SESSION['success'] = 'Delivery receipt deleted';
            }
        } catch (\Exception $e) {
            error_log('deleteReceipt error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to delete delivery receipt';
        }

        header("Location: ?controller=finance&action=viewDelivery&id={$delivery_id}");
        exit;
    }

    public function getPODetails() {
        header('Content-Type: application/json');
        try {
            $id = $_GET['id'] ?? null;
            $po = $this->financeModel->getPurchaseOrderById($id);
            $po_items = $this->financeModel->getPurchaseOrderItems($id);
            $deliveries = $this->financeModel->getDeliveriesByPOWithItems($id);
            $receipts = $this->financeModel->getReceiptsByPO($id);
            echo json_encode([
                'po' => $po, 
                'po_items' => $po_items,
                'deliveries' => $deliveries,
                'receipts' => $receipts
            ]);
        } catch (\Exception $e) {
            error_log('getPODetails error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load PO details']);
        }
        exit;
    }

    public function getDeliveryDetails() {
        header('Content-Type: application/json');
        try {
            $id = $_GET['id'] ?? null;
            $delivery = $this->financeModel->getDeliveryById($id);
            $receipts = $this->financeModel->getReceiptsByDelivery($id);
            echo json_encode(['delivery' => $delivery, 'receipts' => $receipts]);
        } catch (\Exception $e) {
            error_log('getDeliveryDetails error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load delivery details']);
        }
        exit;
    }

    public function priceList() {
        $search = $_GET['search'] ?? '';
        $filterStatus = $_GET['status'] ?? '';
        $filterCustomer = $_GET['customer'] ?? '';

        $hasFilters = ($search !== '' || $filterStatus !== '' || $filterCustomer !== '');
        if ($hasFilters) {
            $filters = [];
            if ($search) $filters['search'] = $search;
            if ($filterStatus !== '') $filters['status'] = $filterStatus;
            if ($filterCustomer !== '') $filters['customer_name'] = $filterCustomer;
            $allItems = $this->priceListModel->getAllFiltered($filters);
            $data['price_items'] = $allItems;
            $data['page'] = 1;
            $data['perPage'] = count($allItems);
            $data['totalPages'] = 1;
            $data['total'] = count($allItems);
            $data['hasNext'] = false;
            $data['hasPrev'] = false;
        } else {
            $allItems = $this->priceListModel->getAll();
            $pagination = Pagination::paginate($allItems, 10);
            $data['price_items'] = $pagination['items'];
            $data['page'] = $pagination['page'];
            $data['perPage'] = $pagination['perPage'];
            $data['totalPages'] = $pagination['totalPages'];
            $data['total'] = $pagination['total'];
            $data['hasNext'] = $pagination['hasNext'];
            $data['hasPrev'] = $pagination['hasPrev'];
        }
        $data['search'] = $search;
        $data['filterStatus'] = $filterStatus;
        $data['filterCustomer'] = $filterCustomer;
        $data['all_items'] = $this->priceListModel->getUnpricedActiveItems();
        $data['customers'] = $this->priceListModel->getActiveCustomers();
        $data['page_title'] = 'Price List';
        $this->render('price_list', $data);
    }

    private function getFilteredPriceItems() {
        $search = $_GET['search'] ?? '';
        $filterStatus = $_GET['status'] ?? '';
        $filterCustomer = $_GET['customer'] ?? '';

        $filters = [];
        if ($search) $filters['search'] = $search;
        if ($filterStatus !== '') $filters['status'] = $filterStatus;
        if ($filterCustomer !== '') $filters['customer_name'] = $filterCustomer;
        return $this->priceListModel->getAllFiltered($filters);
    }

    public function priceListExport() {
        $items = $this->getFilteredPriceItems();
        $headers = ['Product Name', 'Customer', 'Item Code', 'Net/Size', 'Price/Piece', 'Price/Pack', 'Price/Case', 'VAT Type'];
        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                $item['product_name'],
                $item['customer_name'] ?? '-',
                $item['item_code'] ?? '-',
                $item['net_size'] ?? '-',
                number_format($item['price_per_piece'] ?? 0, 2),
                number_format($item['price_per_pack'], 2),
                number_format($item['price_per_case'], 2),
                $item['vat_type'] === 'vat' ? 'VAT' : 'Non-VAT'
            ];
        }
        CsvExport::export('price_list_' . date('Y-m-d') . '.csv', $headers, $rows);
    }

    public function priceListPrint() {
        $data['items'] = $this->getFilteredPriceItems();
        $data['search'] = $_GET['search'] ?? '';
        $data['filterStatus'] = $_GET['status'] ?? '';
        $data['filterCustomer'] = $_GET['customer'] ?? '';
        $data['total'] = count($data['items']);
        $data['pageTitle'] = 'Price List';
        extract($data);
        include __DIR__ . "/../views/price_list/print.php";
        exit;
    }

    public function priceListCreate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->priceListModel->create([
                    'item_id' => $_POST['item_id'] ?? null,
                    'product_name' => $_POST['product_name'] ?? '',
                    'net_size' => $_POST['net_size'] ?? null,
                    'price_per_pack' => $_POST['price_per_pack'] ?? 0,
                    'price_per_case' => $_POST['price_per_case'] ?? 0,
                    'price_per_piece' => $_POST['price_per_piece'] ?? 0,
                    'vat_type' => $_POST['vat_type'] ?? 'vat'
                ]);
                $productLabel = trim((string)($_POST['product_name'] ?? '')) ?: 'price list item';
                $itemCodeLabel = trim((string)($_POST['item_code'] ?? '')) ?: 'unknown item';
                AuditModel::log($_SESSION['user_id'], 'CREATE', 'finance', 'Created price list entry for ' . $productLabel . ' (' . $itemCodeLabel . ')', null, ['item_name' => $_POST['item_name'] ?? '', 'item_code' => $_POST['item_code'] ?? '', 'price_per_piece' => $_POST['price_per_piece'] ?? 0, 'vat_type' => $_POST['vat_type'] ?? 'vat'], 'price_list', null);
                $_SESSION['success'] = 'Price list item added successfully';
            } catch (\Exception $e) {
                error_log('priceListCreate error: ' . $e->getMessage());
                $_SESSION['error'] = 'Failed to create price list item';
            }
        }
        header('Location: ?controller=finance&action=priceList');
        exit;
    }

    public function priceListUpdate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $id = $_POST['price_list_id'] ?? null;
                if ($id) {
                    $this->priceListModel->update($id, [
                        'item_id' => $_POST['item_id'] ?? null,
                        'product_name' => $_POST['product_name'] ?? '',
                        'net_size' => $_POST['net_size'] ?? null,
                        'price_per_pack' => $_POST['price_per_pack'] ?? 0,
                        'price_per_case' => $_POST['price_per_case'] ?? 0,
                        'price_per_piece' => $_POST['price_per_piece'] ?? 0,
                        'vat_type' => $_POST['vat_type'] ?? 'vat'
                    ]);
                    $productLabel = trim((string)($_POST['product_name'] ?? '')) ?: 'price list item';
                    AuditModel::log($_SESSION['user_id'], 'UPDATE', 'finance', 'Updated price list entry for ' . $productLabel, null, $_POST, 'price_list', $id);
                    $_SESSION['success'] = 'Price list item updated successfully';
                }
            } catch (\Exception $e) {
                error_log('priceListUpdate error: ' . $e->getMessage());
                $_SESSION['error'] = 'Failed to update price list item';
            }
        }
        header('Location: ?controller=finance&action=priceList');
        exit;
    }

    public function priceListToggle() {
        try {
            $id = $_GET['id'] ?? null;
            if ($id) {
                $this->priceListModel->toggleStatus($id);
                AuditModel::log($_SESSION['user_id'], 'UPDATE', 'finance', 'Toggled price list status for entry #' . $id, null, ['price_list_id' => $id], 'price_list', $id);
                $_SESSION['success'] = 'Status updated successfully';
            }
        } catch (\Exception $e) {
            error_log('priceListToggle error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to update status';
        }
        header('Location: ?controller=finance&action=priceList');
        exit;
    }

    // ─── GL Account master ───────────────────────────────────────────────────

    /** Redirect target that keeps the GL Account list's search/page. */
    private function glAccountsBackUrl(): string {
        $base = '?controller=finance&action=glAccounts';
        $qs = [];
        foreach (['search', 'page'] as $key) {
            $val = trim($_GET[$key] ?? '');
            if ($val !== '' && $val !== '0') {
                $qs[$key] = $val;
            }
        }
        return $qs ? $base . '&' . http_build_query($qs) : $base;
    }

    public function glAccounts() {
        $search = $_GET['search'] ?? '';
        $filters = [];
        if ($search) $filters['search'] = $search;

        $hasFilters = ($search !== '');
        if ($hasFilters) {
            $data['gl_accounts'] = $this->glAccountModel->getAllFiltered($filters);
            $data['page'] = 1;
            $data['totalPages'] = 1;
            $data['total'] = count($data['gl_accounts']);
        } else {
            $all = $this->glAccountModel->getAll(false);
            $pagination = Pagination::paginate($all, 15);
            $data['gl_accounts'] = $pagination['items'];
            $data['page'] = $pagination['page'];
            $data['totalPages'] = $pagination['totalPages'];
            $data['total'] = $pagination['total'];
        }
        $data['search'] = $search;
        $data['page_title'] = 'GL Account';
        $this->render('gl_accounts/index', $data);
    }

    public function glAccountCreate() {
        $back = $this->glAccountsBackUrl();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $back);
            exit;
        }

        $code = trim($_POST['gl_account_code'] ?? '');
        $description = trim($_POST['gl_account_description'] ?? '');
        $reportIncluded = trim($_POST['report_included'] ?? '') ?: null;
        $grouping = trim($_POST['grouping_financial_report'] ?? '') ?: null;

        if ($code === '' || $description === '') {
            $_SESSION['error'] = 'GL Account Code and Description are required.';
            header('Location: ' . $back);
            exit;
        }
        if ($this->glAccountModel->getByCode($code)) {
            $_SESSION['error'] = 'GL Account Code already exists.';
            header('Location: ' . $back);
            exit;
        }

        try {
            $id = $this->glAccountModel->create([
                'gl_account_code'           => $code,
                'gl_account_description'    => $description,
                'report_included'           => $reportIncluded,
                'grouping_financial_report' => $grouping
            ]);
            AuditModel::log($_SESSION['user_id'], 'CREATE', 'finance', 'Created GL Account ' . $code . ' — ' . $description, null, [
                'gl_account_code' => $code,
                'gl_account_description' => $description,
                'report_included' => $reportIncluded,
                'grouping_financial_report' => $grouping
            ], 'gl_account', $id);
            $_SESSION['success'] = 'GL Account ' . $code . ' created successfully.';
        } catch (\PDOException $e) {
            error_log('glAccountCreate error: ' . $e->getMessage());
            $_SESSION['error'] = 'GL Account Code already exists.';
        }

        header('Location: ' . $back);
        exit;
    }

    public function glAccountUpdate() {
        $back = $this->glAccountsBackUrl();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $back);
            exit;
        }

        $id = (int)($_POST['gl_account_id'] ?? 0);
        $existing = $id > 0 ? $this->glAccountModel->getById($id) : null;
        if (!$existing) {
            $_SESSION['error'] = 'GL Account not found.';
            header('Location: ' . $back);
            exit;
        }

        $code = trim($_POST['gl_account_code'] ?? '');
        $description = trim($_POST['gl_account_description'] ?? '');
        $reportIncluded = trim($_POST['report_included'] ?? '') ?: null;
        $grouping = trim($_POST['grouping_financial_report'] ?? '') ?: null;

        if ($code === '' || $description === '') {
            $_SESSION['error'] = 'GL Account Code and Description are required.';
            header('Location: ' . $back);
            exit;
        }
        $byCode = $this->glAccountModel->getByCode($code);
        if ($byCode && (int)$byCode['gl_account_id'] !== $id) {
            $_SESSION['error'] = 'GL Account Code already exists.';
            header('Location: ' . $back);
            exit;
        }

        try {
            $this->glAccountModel->update($id, [
                'gl_account_code'           => $code,
                'gl_account_description'    => $description,
                'report_included'           => $reportIncluded,
                'grouping_financial_report' => $grouping
            ]);
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'finance', 'Updated GL Account ' . $code, $existing, [
                'gl_account_code' => $code,
                'gl_account_description' => $description,
                'report_included' => $reportIncluded,
                'grouping_financial_report' => $grouping
            ], 'gl_account', $id);
            $_SESSION['success'] = 'GL Account ' . $code . ' updated successfully.';
        } catch (\PDOException $e) {
            error_log('glAccountUpdate error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to update GL Account.';
        }

        header('Location: ' . $back);
        exit;
    }

    public function glAccountToggleStatus() {
        $back = $this->glAccountsBackUrl();
        $id = (int)($_GET['id'] ?? 0);
        $account = $id > 0 ? $this->glAccountModel->getById($id) : null;
        if (!$account) {
            $_SESSION['error'] = 'GL Account not found.';
            header('Location: ' . $back);
            exit;
        }

        try {
            $this->glAccountModel->toggleStatus($id);
            $newStatus = $account['status'] ? 'Inactive' : 'Active';
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'finance', 'Toggled GL Account ' . $account['gl_account_code'] . ' status to ' . $newStatus, $account, null, 'gl_account', $id);
            $_SESSION['success'] = 'GL Account ' . $account['gl_account_code'] . ' is now ' . $newStatus . '.';
        } catch (\Exception $e) {
            error_log('glAccountToggleStatus error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to update GL Account status.';
        }

        header('Location: ' . $back);
        exit;
    }

    // ─── GL Account bulk import (CSV / XLSX) ─────────────────────────────────

    public function glAccountImportForm() {
        $data['page_title'] = 'Import GL Accounts';
        $this->render('gl_accounts/import_form', $data);
    }

    /** CSV template: 4 headers + sample rows from the reference sheet. */
    public function glAccountImportTemplate() {
        CsvExport::export('gl_account_template.csv',
            ['GL Account Code', 'GL Account Description', 'Report Included', 'Grouping in Financial Report'],
            [
                ['101101000000', 'CIB - BPI Collections 5774', 'Balance Sheet', 'Cash and Banks'],
                ['101102000000', 'CIB - BPI Disbursement 6638', 'Balance Sheet', 'Cash and Banks'],
            ]);
    }

    public function glAccountImportPreview() {
        $back = '?controller=finance&action=glAccountImportForm';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['import_file'])) {
            $_SESSION['error'] = 'No file uploaded.';
            header('Location: ' . $back);
            exit;
        }

        $file = $_FILES['import_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'])) {
            $_SESSION['error'] = 'Only .csv and .xlsx files are supported.';
            header('Location: ' . $back);
            exit;
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            $_SESSION['error'] = 'File size must be less than 10MB.';
            header('Location: ' . $back);
            exit;
        }

        try {
            $tempPath = sys_get_temp_dir() . '/' . uniqid('gl_') . '.' . $ext;
            move_uploaded_file($file['tmp_name'], $tempPath);
            $rows = SpreadsheetReader::read($tempPath);
            unlink($tempPath);
        } catch (\Exception $e) {
            if (isset($tempPath) && file_exists($tempPath)) unlink($tempPath);
            $_SESSION['error'] = 'Failed to read file: ' . $e->getMessage();
            header('Location: ' . $back);
            exit;
        }

        if (empty($rows)) {
            $_SESSION['error'] = 'No data rows found. Row 1 must contain the column headers.';
            header('Location: ' . $back);
            exit;
        }

        // Header matching is normalized: case/spaces/underscores ignored.
        $norm = function ($h) {
            return strtolower(str_replace([' ', '_', '-'], '', (string) $h));
        };

        $preview = [];
        $seenCodes = [];
        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            $nrow = [];
            foreach ($row as $k => $v) {
                $nrow[$norm($k)] = $v;
            }

            $code = trim((string) ($nrow['glaccountcode'] ?? $nrow['code'] ?? ''));
            $description = trim((string) ($nrow['glaccountdescription'] ?? $nrow['description'] ?? ''));
            $report = trim((string) ($nrow['reportincluded'] ?? $nrow['report'] ?? ''));
            $grouping = trim((string) ($nrow['groupinginfinancialreport'] ?? $nrow['grouping'] ?? ''));

            $errors = [];
            if ($code === '') {
                $errors[] = 'GL Account Code is required';
            } elseif (strlen($code) > 30) {
                $errors[] = 'Code exceeds 30 characters';
            }
            if ($description === '') {
                $errors[] = 'GL Account Description is required';
            }
            if ($code !== '') {
                $key = strtolower($code);
                if (isset($seenCodes[$key])) {
                    $errors[] = 'Duplicate code in file (first seen on row ' . $seenCodes[$key] . ')';
                } else {
                    $seenCodes[$key] = $rowNum;
                }
            }

            $status = 'new';
            if (!$errors && $this->glAccountModel->getByCode($code)) {
                $status = 'skipped';
            }

            $preview[] = [
                'row'                     => $rowNum,
                'code'                    => $code,
                'description'             => $description,
                'report_included'         => $report,
                'grouping_financial_report'=> $grouping,
                'status'                  => $status,
                'errors'                  => $errors,
            ];
        }

        $_SESSION['gl_import_preview'] = $preview;
        $data['preview'] = $preview;
        $data['newCount'] = count(array_filter($preview, fn($r) => $r['status'] === 'new' && empty($r['errors'])));
        $data['skippedCount'] = count(array_filter($preview, fn($r) => $r['status'] === 'skipped' && empty($r['errors'])));
        $data['errorCount'] = count(array_filter($preview, fn($r) => !empty($r['errors'])));
        $data['page_title'] = 'Import GL Accounts - Preview';
        $this->render('gl_accounts/import_preview', $data);
    }

    public function glAccountImportConfirm() {
        $backForm = '?controller=finance&action=glAccountImportForm';
        $preview = $_SESSION['gl_import_preview'] ?? null;
        if (!$preview) {
            $_SESSION['error'] = 'No import data found. Please upload again.';
            header('Location: ' . $backForm);
            exit;
        }
        unset($_SESSION['gl_import_preview']);

        $errorCount = count(array_filter($preview, fn($r) => !empty($r['errors'])));
        if ($errorCount > 0) {
            $_SESSION['error'] = 'Fix the validation errors and upload the file again.';
            header('Location: ' . $backForm);
            exit;
        }

        $conn = \App\Core\BaseModel::getConnection();
        $conn->beginTransaction();
        $created = 0;
        $skipped = 0;
        try {
            foreach ($preview as $row) {
                if ($row['status'] === 'skipped') {
                    $skipped++;
                    continue;
                }
                $this->glAccountModel->create([
                    'gl_account_code'           => $row['code'],
                    'gl_account_description'    => $row['description'],
                    'report_included'           => $row['report_included'] !== '' ? $row['report_included'] : null,
                    'grouping_financial_report' => $row['grouping_financial_report'] !== '' ? $row['grouping_financial_report'] : null,
                ]);
                $created++;
            }
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            error_log('glAccountImportConfirm error: ' . $e->getMessage());
            $_SESSION['error'] = 'Import failed: ' . $e->getMessage();
            header('Location: ' . $backForm);
            exit;
        }

        AuditModel::log($_SESSION['user_id'], 'IMPORT', 'finance',
            "Imported GL Accounts: {$created} created, {$skipped} skipped",
            null,
            ['created' => $created, 'skipped' => $skipped],
            'gl_account', null);
        $_SESSION['success'] = "GL Account import complete: {$created} created, {$skipped} skipped (existing codes were left unchanged).";
        header('Location: ?controller=finance&action=glAccounts');
        exit;
    }

    public function getUnpricedItemsByCustomer() {
        header('Content-Type: application/json');
        try {
            $customer_id = $_GET['customer_id'] ?? null;
            if (!$customer_id) {
                echo json_encode([]);
                exit;
            }
            $items = $this->priceListModel->getUnpricedItemsByCustomer($customer_id);
            echo json_encode($items);
        } catch (\Exception $e) {
            error_log('getUnpricedItemsByCustomer error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load items']);
        }
        exit;
    }

    public function printSalesInvoice() {
        $id = $_GET['id'] ?? null;
        $delivery = $this->financeModel->getDeliveryById($id);

        $priceList = null;
        if (!empty($delivery['poi_item_id'])) {
            $priceList = $this->priceListModel->getByItemId($delivery['poi_item_id']);
        }

        $lotItems = json_decode($delivery['lot_items'] ?? '[]', true);
        if (!is_array($lotItems)) $lotItems = [];

        // Group lot_items by lot_number to avoid duplicate rows
        $groupedLotItems = [];
        foreach ($lotItems as $li) {
            $key = $li['lot_number'] ?? uniqid();
            if (!isset($groupedLotItems[$key])) {
                $groupedLotItems[$key] = $li;
                $groupedLotItems[$key]['qty'] = 0;
            }
            $groupedLotItems[$key]['qty'] += intval($li['qty'] ?? 0);
        }

        $items = [];
        $grandTotalQty = 0;
        $grandTotalAmount = 0;
        $vatType = $priceList['vat_type'] ?? 'non_vat';

        foreach ($groupedLotItems as $li) {
            $liQty = $li['qty'];
            $itemPrice = $priceList['price_per_piece'] ?? 0;
            $itemAmount = round($liQty * $itemPrice, 2);
            $conv = $li['actual_uom_conversion'] ?? $li['uom_conversion'] ?? null;
            $uom = $li['item_uom'] ?? '';
            $cases = ($conv && $uom !== 'CS') ? floor($liQty / $conv) : 0;

            $items[] = [
                'item_description' => $li['item_description'] ?? '',
                'item_uom' => $li['item_uom'] ?? '',
                'lot_number' => $li['lot_number'] ?? '',
                'qty' => $liQty,
                'cases' => $cases,
                'price' => $itemPrice,
                'amount' => $itemAmount,
            ];

            $grandTotalQty += $liQty;
            $grandTotalAmount += $itemAmount;
        }

        if (empty($items) && $delivery) {
            $qty = $delivery['delivery_quantity'] ?? 0;
            $price = $priceList['price_per_piece'] ?? 0;
            $amount = round($qty * $price, 2);
            $conv = $delivery['actual_uom_conversion'] ?? $delivery['uom_conversion'] ?? null;
            $itemUom = $delivery['item_uom'] ?? '';
            $cases = ($conv && $itemUom !== 'CS') ? floor($qty / $conv) : 0;
            $items[] = [
                'item_description' => $delivery['item_description'] ?? '',
                'item_uom' => $delivery['item_uom'] ?? '',
                'lot_number' => $delivery['lot_number'] ?? '',
                'qty' => $qty,
                'cases' => $cases,
                'price' => $price,
                'amount' => $amount,
            ];
            $grandTotalQty = $qty;
            $grandTotalAmount = $amount;
        }

        if ($vatType === 'vat') {
            $subtotal = round($grandTotalAmount / 1.12, 2);
            $vat = round($grandTotalAmount - $subtotal, 2);
        } else {
            $subtotal = $grandTotalAmount;
            $vat = 0;
        }

        $data = [
            'delivery' => $delivery,
            'date' => !empty($delivery['delivery_date']) ? date('j-M-Y', strtotime($delivery['delivery_date'])) : '',
            'customer_name' => $delivery['customer_name'] ?? '',
            'customer_tin' => $delivery['customer_tin'] ?? '',
            'customer_address' => $delivery['customer_address'] ?? '',
            'customer_terms' => ($delivery['customer_terms'] ?? 0) . ' DAYS',
            'customer_code' => $delivery['customer_code'] ?? '',
            'po_number' => $delivery['customer_po_number'] ?? '',
            'dr_number' => $delivery['dr_number'] ?? '',
            'items' => $items,
            'subtotal' => $subtotal,
            'vat' => $vat,
            'vatType' => $vatType,
            'grand_total' => $grandTotalAmount,
            'vatable_sales' => $vatType === 'vat' ? $subtotal : 0,
            'vat_amount' => $vat,
            'zero_rated_sales' => 0,
            'vat_exempt_sales' => $vatType !== 'vat' ? $grandTotalAmount : 0,
        ];

        extract($data);
        include __DIR__ . "/../views/deliveries/print.php";
        exit;
    }

    public function printSalesInvoiceWD() {
        $id = $_GET['id'] ?? null;
        $delivery = $this->financeModel->getDeliveryById($id);

        $priceList = null;
        if (!empty($delivery['poi_item_id'])) {
            $priceList = $this->priceListModel->getByItemId($delivery['poi_item_id']);
        }

        $lotItems = json_decode($delivery['lot_items'] ?? '[]', true);
        if (!is_array($lotItems)) $lotItems = [];

        // Group lot_items by lot_number to avoid duplicate rows
        $groupedLotItems = [];
        foreach ($lotItems as $li) {
            $key = $li['lot_number'] ?? uniqid();
            if (!isset($groupedLotItems[$key])) {
                $groupedLotItems[$key] = $li;
                $groupedLotItems[$key]['qty'] = 0;
            }
            $groupedLotItems[$key]['qty'] += intval($li['qty'] ?? 0);
        }

        $items = [];
        $grandTotalQty = 0;
        $grandTotalAmount = 0;
        $vatType = $priceList['vat_type'] ?? 'non_vat';

        foreach ($groupedLotItems as $li) {
            $liQty = $li['qty'];
            $itemPrice = $priceList['price_per_piece'] ?? 0;
            $itemAmount = round($liQty * $itemPrice, 2);
            $conv = $li['actual_uom_conversion'] ?? $li['uom_conversion'] ?? null;
            $uom = $li['item_uom'] ?? '';
            $cases = ($conv && $uom !== 'CS') ? floor($liQty / $conv) : 0;

            $items[] = [
                'item_description' => $li['item_description'] ?? '',
                'item_uom' => $li['item_uom'] ?? '',
                'lot_number' => $li['lot_number'] ?? '',
                'qty' => $liQty,
                'cases' => $cases,
                'price' => $itemPrice,
                'amount' => $itemAmount,
            ];

            $grandTotalQty += $liQty;
            $grandTotalAmount += $itemAmount;
        }

        if (empty($items) && $delivery) {
            $qty = $delivery['delivery_quantity'] ?? 0;
            $price = $priceList['price_per_piece'] ?? 0;
            $amount = round($qty * $price, 2);
            $conv = $delivery['actual_uom_conversion'] ?? $delivery['uom_conversion'] ?? null;
            $itemUom = $delivery['item_uom'] ?? '';
            $cases = ($conv && $itemUom !== 'CS') ? floor($qty / $conv) : 0;
            $items[] = [
                'item_description' => $delivery['item_description'] ?? '',
                'item_uom' => $delivery['item_uom'] ?? '',
                'lot_number' => $delivery['lot_number'] ?? '',
                'qty' => $qty,
                'cases' => $cases,
                'price' => $price,
                'amount' => $amount,
            ];
            $grandTotalQty = $qty;
            $grandTotalAmount = $amount;
        }

        if ($vatType === 'vat') {
            $subtotal = round($grandTotalAmount / 1.12, 2);
            $vat = round($grandTotalAmount - $subtotal, 2);
        } else {
            $subtotal = $grandTotalAmount;
            $vat = 0;
        }

        $data = [
            'delivery' => $delivery,
            'date' => !empty($delivery['delivery_date']) ? date('j-M-Y', strtotime($delivery['delivery_date'])) : '',
            'customer_name' => $delivery['customer_name'] ?? '',
            'customer_tin' => $delivery['customer_tin'] ?? '',
            'customer_address' => $delivery['customer_address'] ?? '',
            'customer_terms' => ($delivery['customer_terms'] ?? 0) . ' DAYS',
            'customer_code' => $delivery['customer_code'] ?? '',
            'po_number' => $delivery['customer_po_number'] ?? '',
            'dr_number' => $delivery['dr_number'] ?? '',
            'items' => $items,
            'subtotal' => $subtotal,
            'vat' => $vat,
            'vatType' => $vatType,
            'grand_total' => $grandTotalAmount,
            'vatable_sales' => $vatType === 'vat' ? $subtotal : 0,
            'vat_amount' => $vat,
            'zero_rated_sales' => 0,
            'vat_exempt_sales' => $vatType !== 'vat' ? $grandTotalAmount : 0,
        ];

        extract($data);
        include __DIR__ . "/../views/deliveries/print_wd.php";
        exit;
    }

    public function activityLogs() {
        try {
            $auditModel = new \App\Models\AuditModel();
            $filters = [
                'department' => $_SESSION['department'] ?? 'finance',
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
            $data['logController'] = 'finance';
            $data['departmentLocked'] = true;
            $data['hideDeptColumn'] = true;
            $data['stats'] = [
                'today_count' => \App\Models\AuditModel::getLogStats('finance')['today_count'] ?? 0,
                'by_department' => [],
            ];
            $data['page_title'] = 'Activity Logs';
            $this->render('activity_logs/index', $data);
        } catch (\Exception $e) {
            error_log('activityLogs error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to load activity logs';
            header('Location: ?controller=finance&action=dashboard');
            exit;
        }
    }

    private function render($view, $data = []) {
        extract($data);
        ob_start();
        include __DIR__ . "/../views/{$view}.php";
        $content = ob_get_clean();
        include __DIR__ . "/../views/layouts/main.php";
    }
}
