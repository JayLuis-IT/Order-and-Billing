<?php
namespace App\Controllers;

use App\Models\QcModel;
use App\Models\WarehouseModel;
use App\Models\AuditModel;
use App\Helpers\Pagination;
use App\Helpers\RoleHelper;
use App\Helpers\NotificationHelper;

class QcController {
    private $qcModel;

    public function __construct() {
        $action = $_GET['action'] ?? '';
        // index.php defaults the action to 'login'; missing/unknown action
        // dispatches to index() — mirror that for the permission gates.
        if ($action === '' || $action === 'login') {
            $action = 'index';
        }
        $isApiAction = in_array($action, ['apiGetPending', 'apiInspect', 'getInspectionDetails'], true);

        if (!isset($_SESSION['user_id'])) {
            if ($isApiAction) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Session expired. Please log in again.']);
                exit;
            }
            header('Location: ?controller=auth&action=login');
            exit;
        }
        if (!$isApiAction && (($_SESSION['department'] ?? '') !== 'qc')) {
            header('Location: ' . RoleHelper::homeUrl());
            exit;
        }
        // Role gate: qc_admin = all; qc_inspector = delivery inspection only.
        if (($_SESSION['department'] ?? '') === 'qc') {
            $role = RoleHelper::currentRole();
            if ($role === null) {
                header('Location: ?controller=auth&action=noRole');
                exit;
            }
            if (!RoleHelper::qcCan($role, $action)) {
                if ($isApiAction) {
                    header('Content-Type: application/json');
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'You do not have access to that feature.']);
                    exit;
                }
                $_SESSION['error'] = 'You do not have access to that feature.';
                header('Location: ' . RoleHelper::homeUrl());
                exit;
            }
        }
        $this->qcModel = new QcModel();
    }

    public function index() {
        $role = RoleHelper::currentRole();

        if ($role === 'qc_inspector') {
            $counts = $this->qcModel->getInspectionCounts();
            $data = [
                'counts' => $counts,
                'recentHistory' => array_slice($this->qcModel->getProductionHistoryForQC(), 0, 10),
                'page_title' => 'QC Inspector Dashboard',
            ];
            $this->render('dashboard/inspector', $data);
            return;
        }

        $data = [
            'pendingQcItems' => $this->qcModel->getPendingQcItems(),
            'completedQcItems' => $this->qcModel->getCompletedQcItems(),
            'counts' => $this->qcModel->getInspectionCounts(),
            'page_title' => 'QC Admin Dashboard',
        ];
        $this->render('dashboard/admin', $data);
    }

    public function receivingInspection() {
        $pendingQcItems = $this->qcModel->getPendingQcItems();
        $completedQcItems = $this->qcModel->getCompletedQcItems();

        $data = [
            'pendingQcItems' => $pendingQcItems,
            'completedQcItems' => $completedQcItems,
            'page_title' => 'Receiving Inspection'
        ];

        $this->render('receiving_inspection', $data);
    }

    public function deliveryInspection() {
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
            $allHistory = $this->qcModel->getProductionHistoryForQC($filters);
        } else {
            $allHistory = $this->qcModel->getProductionHistoryForQC();
        }

        $allCustomers = array_values(array_unique(array_filter(array_column($allHistory, 'customer_name'))));
        $allItems = array_values(array_unique(array_filter(array_column($allHistory, 'item_description'))));
        $allLots = array_values(array_unique(array_filter(array_column($allHistory, 'lot_number'))));

        if ($hasFilter) {
            $pagination = ['items' => $allHistory, 'page' => 1, 'perPage' => count($allHistory), 'total' => count($allHistory), 'totalPages' => 1, 'hasNext' => false, 'hasPrev' => false];
        } else {
            $pagination = Pagination::paginate($allHistory, 15);
        }

        $counts = $this->qcModel->getInspectionCounts();

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
            'page_title' => 'For Delivery Inspection'
        ];

        $this->render('delivery_inspection', $data);
    }

    public function apiGetPending() {
        header('Content-Type: application/json');
        try {
            echo json_encode(['success' => true, 'items' => $this->qcModel->getPendingQcItems()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Read-only JSON payload for the "View Details" modal (QC Inspection Record).
    public function getInspectionDetails() {
        header('Content-Type: application/json');
        $id = intval($_GET['id'] ?? 0);
        try {
            $data = $id > 0 ? $this->qcModel->getInspectionDetail($id) : false;
            if (!$data) {
                echo json_encode(['success' => false, 'error' => 'Inspection record not found.']);
            } else {
                echo json_encode(['success' => true, 'data' => $data]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function apiInspect() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        try {
            $payload = $_POST;
            if (empty($_POST)) {
                $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            }
            $result = $this->qcModel->recordQcInspection($payload);
            echo json_encode(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function approveReceivingInspection($id = null) {
        $receivingItemId = (int) ($id ?? ($_POST['receiving_item_id'] ?? $_GET['id'] ?? 0));
        if ($receivingItemId <= 0) {
            $_SESSION['error'] = 'Receiving item not found.';
            header('Location: ?controller=qc&action=receivingInspection');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $decision = strtoupper((string) ($_POST['decision'] ?? 'PASSED'));
            $payload = [
                'receiving_item_id' => $receivingItemId,
                'decision' => $decision,
                'received_qty' => (float) ($_POST['received_qty'] ?? 0),
                'passed_qty' => (float) ($_POST['passed_qty'] ?? 0),
                'rejected_qty' => (float) ($_POST['rejected_qty'] ?? 0),
                'inspector_name' => trim((string) ($_POST['inspector_name'] ?? ($_SESSION['full_name'] ?? 'QC'))),
                'remarks' => trim((string) ($_POST['remarks'] ?? '')),
            ];

            try {
                $result = $this->qcModel->approveReceivingInspection($receivingItemId, $payload);
                $_SESSION['success'] = 'Receiving inspection ' . strtolower($decision) . ' and stock status updated.';
                header('Location: ?controller=qc&action=receivingInspection');
                exit;
            } catch (\Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header('Location: ?controller=qc&action=receivingInspection');
                exit;
            }
        }

        $receivingItem = $this->qcModel->getReceivingItemById($receivingItemId);
        if (!$receivingItem) {
            $_SESSION['error'] = 'Receiving item not found.';
            header('Location: ?controller=qc&action=receivingInspection');
            exit;
        }

        $data = [
            'page_title' => 'Approve Receiving Inspection',
            'receivingItem' => $receivingItem,
        ];
        $this->render('dashboard/index', $data);
    }

    public function updateRemark() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $historyId = $_POST['history_id'] ?? null;
            $remark = trim($_POST['qc_remark'] ?? '');
            $inspectorName = trim($_POST['qc_inspector_name'] ?? '');

            if (!$historyId || empty($remark) || empty($inspectorName)) {
                $_SESSION['error'] = 'Please fill in all required fields.';
                header('Location: ?controller=qc&action=deliveryInspection');
                exit;
            }

            $this->qcModel->updateQcRemark($historyId, $inspectorName, $remark);
            $_SESSION['success'] = 'QC remark saved successfully.';
            header('Location: ?controller=qc&action=deliveryInspection');
            exit;
        }
    }

    // ─── MRP Check — third approval gate (QC Admin) ──────────────────────────
    // Every MRP run passes WHSE Admin → System Admin → QC Admin. The QC gate
    // is per-item: each component is inspected via a Raw Material Analysis
    // Report (Approved / Rejected / Hold / Waived). When every item carries a
    // final disposition the run auto-completes — Approved/Waived requisitions
    // release to Procurement and stock is reserved for them; Rejected ones
    // are cancelled. qc_admin only — qc_inspector never reaches these.

    /** Shared WarehouseModel access for the MRP Check actions. */
    private function warehouseModel(): WarehouseModel {
        return new WarehouseModel();
    }

    /**
     * MRP Check retired — MRP runs release to Procurement at System Admin
     * approval (two-gate flow); raw material inspection now happens at
     * Receiving Inspection, before the RM is added to stock. The old page
     * (and its auto-complete sweep that released runs) is blocked so no
     * stale path can move requisitions.
     */
    public function mrpCheck() {
        $_SESSION['success'] = 'MRP Check has been retired — inspect raw material at Receiving Inspection '
            . '(QC approval there adds the stock). MRP runs no longer wait for QC.';
        header('Location: ?controller=qc&action=receivingInspection');
        exit;
    }

    /** JSON for the run items modal: frozen components + latest report state. (Retired with MRP Check.) */
    public function mrpRunQcItems() {
        header('Content-Type: application/json');
        http_response_code(410);
        echo json_encode(['success' => false, 'error' => 'MRP Check has been retired — inspect at Receiving Inspection.']);
        exit;
    }

    /**
     * Save one Raw Material Analysis Report (MRP context) — retired with MRP
     * Check. Blocked so the stale path can never release/cancel requisitions
     * or book stock behind the two-gate flow. (Receiving context saves through
     * saveReceivingInspection — unchanged.)
     */
    public function saveMrpInspection() {
        $_SESSION['success'] = 'MRP Check has been retired — inspect raw material at Receiving Inspection.';
        header('Location: ?controller=qc&action=receivingInspection');
        exit;
    }

    /** Save one Raw Material Analysis Report (Receiving context) + apply disposition. */
    public function saveReceivingInspection() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \RuntimeException('POST required.');
            }
            $receivingItemId = intval($_POST['receiving_item_id'] ?? 0);
            $disposition = strtoupper(trim((string) ($_POST['disposition'] ?? '')));
            $qtyUsed = (float) ($_POST['qty_used'] ?? 0);
            $reportId = $this->qcModel->saveAnalysisReport($this->inspectionPayloadFromPost('receiving'));
            $result = $this->qcModel->applyReceivingDisposition($receivingItemId, $disposition, trim((string) ($_POST['remarks'] ?? '')), $qtyUsed);
            AuditModel::log($_SESSION['user_id'], 'INSPECT', 'qc',
                "Receiving inspection — receiving item #{$receivingItemId}, report #{$reportId}, disposition {$disposition}",
                null, ['qc_status' => $result['decision'] ?? $disposition], 'receiving_item', $receivingItemId);

            if (($result['decision'] ?? '') === 'HOLD') {
                $_SESSION['success'] = 'Inspection saved — item placed ON HOLD and kept in the queue for re-inspection.';
            } else {
                $_SESSION['success'] = 'Receiving inspection ' . strtolower((string) ($result['decision'] ?? $disposition))
                    . ' — stock status updated.';
            }
            if (!empty($_POST['save_and_print'])) {
                header('Location: ?controller=qc&action=printAnalysisReport&id=' . $reportId);
                exit;
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=qc&action=receivingInspection');
        exit;
    }

    /** Printable Raw Material Analysis Report (works for both contexts). */
    public function printAnalysisReport() {
        $reportId = intval($_GET['id'] ?? 0);
        $report = $reportId > 0 ? $this->qcModel->getAnalysisReport($reportId) : false;
        if (!$report) {
            $_SESSION['error'] = 'Inspection report not found.';
            header('Location: ?controller=qc');
            exit;
        }
        $data['report'] = $report;
        $data['page_title'] = 'Raw Material Analysis Report';
        $this->render('analysis_report_print', $data);
    }

    /** JSON: one analysis report (Inspect prefill / retest). */
    public function getAnalysisReport() {
        header('Content-Type: application/json');
        $reportId = intval($_GET['id'] ?? 0);
        try {
            $report = $reportId > 0 ? $this->qcModel->getAnalysisReport($reportId) : false;
            if (!$report) {
                echo json_encode(['success' => false, 'error' => 'Report not found.']);
            } else {
                echo json_encode(['success' => true, 'data' => $report]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Packaging Material Inspection Report (QCF-007) ──────────────────────

    /** Save one PM Inspection Report (Receiving context) + apply disposition. */
    public function savePmInspection() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \RuntimeException('POST required.');
            }
            $receivingItemId = intval($_POST['receiving_item_id'] ?? 0);
            $disposition = strtoupper(trim((string) ($_POST['disposition'] ?? '')));

            $payload = [
                'receiving_item_id' => $receivingItemId,
                'pm_code' => trim((string) ($_POST['pm_code'] ?? '')),
                'pm_name' => trim((string) ($_POST['pm_name'] ?? '')),
                'supplier_name' => trim((string) ($_POST['supplier_name'] ?? '')),
                'rr_number' => trim((string) ($_POST['rr_number'] ?? '')),
                'date_delivered' => trim((string) ($_POST['date_delivered'] ?? '')),
                'date_tested' => trim((string) ($_POST['date_tested'] ?? '')),
                'quantity_delivered' => trim((string) ($_POST['quantity_delivered'] ?? '')),
                'sample_qty' => trim((string) ($_POST['sample_qty'] ?? '')),
                'result_dimensional' => trim((string) ($_POST['result_dimensional'] ?? '')),
                'total_reject_qty' => trim((string) ($_POST['total_reject_qty'] ?? '')),
                'total_reject_rate' => trim((string) ($_POST['total_reject_rate'] ?? '')),
                'disposition' => $disposition,
                'remarks' => trim((string) ($_POST['remarks'] ?? '')),
                'qty_used' => trim((string) ($_POST['qty_used'] ?? '')),
            ];

            // Dimensional rows: dim_name[], dim_specs[], dim_uom[], dim_samples[10][]
            $dimNames = (array) ($_POST['dim_name'] ?? []);
            $dimSpecs = (array) ($_POST['dim_specs'] ?? []);
            $dimUoms  = (array) ($_POST['dim_uom'] ?? []);
            $dimSamples = (array) ($_POST['dim_samples'] ?? []);
            $dimensional = [];
            foreach ($dimNames as $idx => $name) {
                $name = trim((string) $name);
                if ($name === '' && trim((string) ($dimSpecs[$idx] ?? '')) === '') {
                    continue;
                }
                $samples = [];
                for ($s = 1; $s <= 10; $s++) {
                    $samples[] = trim((string) ($dimSamples[$idx][$s] ?? ''));
                }
                $dimensional[] = [
                    'dimension' => $name,
                    'specifications' => trim((string) ($dimSpecs[$idx] ?? '')),
                    'uom' => trim((string) ($dimUoms[$idx] ?? '')),
                    'samples' => $samples,
                ];
            }
            $payload['dimensional_data'] = $dimensional;

            // Functional rows: func_test[] labels (fixed 6), func_result[], func_qty[]
            $funcResults = (array) ($_POST['func_result'] ?? []);
            $funcQtys = (array) ($_POST['func_qty'] ?? []);
            $funcTests = ['Pump/Spray Test', 'Leak Test', 'Drop test', 'Adhesion Test', 'Tape Test', 'Others'];
            $functional = [];
            foreach ($funcTests as $idx => $test) {
                $functional[] = [
                    'test' => $test,
                    'result' => trim((string) ($funcResults[$idx] ?? '')),
                    'qty' => trim((string) ($funcQtys[$idx] ?? '')),
                ];
            }
            $payload['functional_data'] = $functional;

            // Appearance rows: app_name[], app_result[], app_qty[]
            $appNames = (array) ($_POST['app_name'] ?? []);
            $appResults = (array) ($_POST['app_result'] ?? []);
            $appQtys = (array) ($_POST['app_qty'] ?? []);
            $appearance = [];
            foreach ($appNames as $idx => $name) {
                $name = trim((string) $name);
                $result = trim((string) ($appResults[$idx] ?? ''));
                $qty = trim((string) ($appQtys[$idx] ?? ''));
                if ($name === '' && $result === '' && $qty === '') {
                    continue;
                }
                $appearance[] = [
                    'defect_name' => $name,
                    'result' => $result,
                    'qty' => $qty,
                ];
            }
            $payload['appearance_data'] = $appearance;

            $reportId = $this->qcModel->savePmReport($payload);
            $qtyUsed = (float) ($_POST['qty_used'] ?? 0);
            $result = $this->qcModel->applyReceivingDisposition($receivingItemId, $disposition, $payload['remarks'], $qtyUsed);
            AuditModel::log($_SESSION['user_id'], 'INSPECT', 'qc',
                "PM receiving inspection — receiving item #{$receivingItemId}, report #{$reportId}, disposition {$disposition}",
                null, ['qc_status' => $result['decision'] ?? $disposition], 'receiving_item', $receivingItemId);

            if (($result['decision'] ?? '') === 'HOLD') {
                $_SESSION['success'] = 'PM inspection saved — item placed ON HOLD and kept in the queue for re-inspection.';
            } else {
                $_SESSION['success'] = 'PM inspection ' . strtolower((string) ($result['decision'] ?? $disposition))
                    . ' — stock status updated.';
            }
            if (!empty($_POST['save_and_print'])) {
                header('Location: ?controller=qc&action=printPmReport&id=' . $reportId);
                exit;
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: ?controller=qc&action=receivingInspection');
        exit;
    }

    /** Printable Packaging Material Inspection Report (QCF-007). */
    public function printPmReport() {
        $reportId = intval($_GET['id'] ?? 0);
        $report = $reportId > 0 ? $this->qcModel->getPmReport($reportId) : false;
        if (!$report) {
            $_SESSION['error'] = 'PM inspection report not found.';
            header('Location: ?controller=qc');
            exit;
        }
        $data['report'] = $report;
        $data['page_title'] = 'Packaging Material Inspection Report';
        $this->render('pm_report_print', $data);
    }

    /** JSON: one PM report (Inspect prefill / re-inspection). */
    public function getPmReport() {
        header('Content-Type: application/json');
        $reportId = intval($_GET['id'] ?? 0);
        try {
            $report = $reportId > 0 ? $this->qcModel->getPmReport($reportId) : false;
            if (!$report) {
                echo json_encode(['success' => false, 'error' => 'Report not found.']);
            } else {
                echo json_encode(['success' => true, 'data' => $report]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Map POST fields (shared report form) to saveAnalysisReport input.
     * $context: 'mrp' (run_id + component_item_id) or 'receiving' (receiving_item_id).
     */
    private function inspectionPayloadFromPost(string $context): array {
        $payload = [
            'rm_name' => trim((string) ($_POST['rm_name'] ?? '')),
            'supplier_name' => trim((string) ($_POST['supplier_name'] ?? '')),
            'rr_number' => trim((string) ($_POST['rr_number'] ?? '')),
            'lot_number' => trim((string) ($_POST['lot_number'] ?? '')),
            'old_expiry_date' => trim((string) ($_POST['old_expiry_date'] ?? '')),
            'date_delivered' => trim((string) ($_POST['date_delivered'] ?? '')),
            'date_tested' => trim((string) ($_POST['date_tested'] ?? '')),
            'expiry_date' => trim((string) ($_POST['expiry_date'] ?? '')),
            'delivery_type' => trim((string) ($_POST['delivery_type'] ?? 'new_delivery')),
            'disposition' => strtoupper(trim((string) ($_POST['disposition'] ?? ''))),
            'remarks' => trim((string) ($_POST['remarks'] ?? '')),
            'qty_used' => trim((string) ($_POST['qty_used'] ?? '')),
        ];
        foreach (['appearance', 'color', 'odor', 'texture', 'ph', 'viscosity', 'sg', 'solubility', 'alcohol', 'microbial'] as $param) {
            $payload[$param . '_spec'] = trim((string) ($_POST[$param . '_spec'] ?? ''));
            $payload[$param . '_result'] = trim((string) ($_POST[$param . '_result'] ?? ''));
        }
        if ($context === 'mrp') {
            $payload['run_id'] = intval($_POST['run_id'] ?? 0);
            $payload['component_item_id'] = intval($_POST['component_item_id'] ?? 0);
        } else {
            $payload['receiving_item_id'] = intval($_POST['receiving_item_id'] ?? 0);
        }
        return $payload;
    }

    private function render($view, $data = []) {
        if (($_SESSION['department'] ?? '') === 'qc') {
            $role = RoleHelper::currentRole();
            $data['role'] = $role;
            $data['roleLabel'] = RoleHelper::label($role);
            $data['sideMenu'] = RoleHelper::qcMenu($role);
        }
        extract($data);
        ob_start();
        include __DIR__ . "/../views/{$view}.php";
        $content = ob_get_clean();
        include __DIR__ . "/../views/layouts/main.php";
    }
}
