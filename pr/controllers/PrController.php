<?php
namespace App\Controllers;

use App\Models\PurchaseRequestModel;
use App\Models\ProcurementPrModel;
use App\Models\AuditModel;
use App\Models\WarehouseModel;
use App\Core\BaseModel;

class PrController {
    private $prModel;

    private const ALLOWED_SIGN_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'
    ];
    private const MAX_SIGN_SIZE = 10 * 1024 * 1024; // 10MB

    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?controller=auth&action=login');
            exit;
        }
        $this->prModel = new PurchaseRequestModel();
    }

    /** Requestor list — own department's PRs (creator sees all their own). */
    public function index() {
        $statusFilter = $_GET['status'] ?? '';
        $requests = $this->prModel->getRequests(null, $statusFilter !== '' ? $statusFilter : null);
        $this->render('index', [
            'page_title'   => 'Purchase Request',
            'requests'     => $requests,
            'statusFilter' => $statusFilter,
            'dept'         => $_SESSION['department'] ?? '',
            'today'        => date('Y-m-d'),
        ]);
    }

    /** Create a PR (POST from the Add Request modal). */
    public function create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=pr');
            exit;
        }
        $purpose = trim($_POST['purpose'] ?? '');
        if ($purpose === '') {
            $_SESSION['error'] = 'Purpose / Project Name is required.';
            header('Location: ?controller=pr');
            exit;
        }
        $items = $this->collectItems();
        if (!$items) {
            $_SESSION['error'] = 'Add at least one line item.';
            header('Location: ?controller=pr');
            exit;
        }
        $header = [
            'pr_number'       => PurchaseRequestModel::nextPrNumber(date('Y')),
            'department'      => trim($_POST['department'] ?? ($_SESSION['department'] ?? '')),
            'purpose'         => $purpose,
            'date_created'    => $_POST['date_created'] ?? date('Y-m-d'),
            'classification'  => in_array($_POST['classification'] ?? '', ['new', 'repeat_order'], true) ? $_POST['classification'] : 'new',
            'priority'        => in_array($_POST['priority'] ?? '', ['immediate', 'standard'], true) ? $_POST['priority'] : 'standard',
            'prepared_by'     => trim($_POST['prepared_by'] ?? '') ?: ($_SESSION['full_name'] ?? $_SESSION['username'] ?? ''),
            'checked_by'      => trim($_POST['checked_by'] ?? '') ?: null,
            'approved_by_name'=> trim($_POST['approved_by'] ?? '') ?: 'Ferdinand Ginete',
            'received_by'     => trim($_POST['received_by'] ?? '') ?: null,
            'notes'           => trim($_POST['notes'] ?? '') ?: null,
            'created_by'      => (int) $_SESSION['user_id'],
        ];
        try {
            $prId = $this->prModel->createRequest($header, $items);
            AuditModel::log($_SESSION['user_id'], 'CREATE', 'pr', 'Created Purchase Request ' . $header['pr_number'] . ' (' . $header['purpose'] . ') with ' . count($items) . ' line item(s)', null, ['pr_number' => $header['pr_number']], 'purchase_request', $prId);
            $_SESSION['success'] = 'Purchase Request ' . $header['pr_number'] . ' submitted. Print it for supervisor and President signatures.';
        } catch (\Throwable $e) {
            error_log('PR create error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to submit the purchase request.';
        }
        header('Location: ?controller=pr');
        exit;
    }

    /** JSON payload of a submitted PR for the Edit modal (creator only). */
    public function edit() {
        header('Content-Type: application/json');
        $pr = $this->prModel->getById((int) ($_GET['id'] ?? 0));
        if (!$pr || (int) $pr['created_by'] !== (int) $_SESSION['user_id'] || $pr['status'] !== 'submitted') {
            http_response_code(404);
            echo json_encode(['error' => 'Purchase request not found or no longer editable.']);
            exit;
        }
        echo json_encode(['pr' => $pr, 'items' => $this->prModel->getItems((int) $pr['pr_id'])]);
        exit;
    }

    /** Update a submitted PR (POST from the Edit modal). Creator only. */
    public function update() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=pr');
            exit;
        }
        $prId = (int) ($_POST['pr_id'] ?? 0);
        $pr = $this->prModel->getById($prId);
        if (!$pr || (int) $pr['created_by'] !== (int) $_SESSION['user_id'] || $pr['status'] !== 'submitted') {
            $_SESSION['error'] = 'Only your own submitted requests can be edited.';
            header('Location: ?controller=pr');
            exit;
        }
        $purpose = trim($_POST['purpose'] ?? '');
        if ($purpose === '') {
            $_SESSION['error'] = 'Purpose / Project Name is required.';
            header('Location: ?controller=pr');
            exit;
        }
        $items = $this->collectItems();
        if (!$items) {
            $_SESSION['error'] = 'Add at least one line item.';
            header('Location: ?controller=pr');
            exit;
        }
        // Department, pr_number, status and created_by never change on edit.
        $header = [
            'purpose'          => $purpose,
            'date_created'     => $_POST['date_created'] ?? date('Y-m-d'),
            'classification'   => in_array($_POST['classification'] ?? '', ['new', 'repeat_order'], true) ? $_POST['classification'] : 'new',
            'priority'         => in_array($_POST['priority'] ?? '', ['immediate', 'standard'], true) ? $_POST['priority'] : 'standard',
            'prepared_by'      => trim($_POST['prepared_by'] ?? '') ?: ($_SESSION['full_name'] ?? $_SESSION['username'] ?? ''),
            'checked_by'       => trim($_POST['checked_by'] ?? '') ?: null,
            'approved_by_name' => trim($_POST['approved_by'] ?? '') ?: 'Ferdinand Ginete',
            'received_by'      => trim($_POST['received_by'] ?? '') ?: null,
            'notes'            => trim($_POST['notes'] ?? '') ?: null,
        ];
        try {
            $this->prModel->updateRequest($prId, $header, $items);
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'pr', 'Edited Purchase Request ' . $pr['pr_number'] . ' (' . $header['purpose'] . ') with ' . count($items) . ' line item(s)', null, ['pr_number' => $pr['pr_number']], 'purchase_request', $prId);
            $_SESSION['success'] = 'Purchase Request ' . $pr['pr_number'] . ' updated.';
        } catch (\Throwable $e) {
            error_log('PR update error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to update the purchase request.';
        }
        header('Location: ?controller=pr');
        exit;
    }

    /** Normalize the dynamic line-item rows submitted by the PR form. */
    private function collectItems(): array {
        $items = [];
        // Browsers submit these as arrays (name="field[]"); normalize in case
        // a single scalar value slips through (e.g. hand-crafted requests).
        $names  = (array) ($_POST['item_name'] ?? []);
        $codes  = (array) ($_POST['item_code'] ?? []);
        $ids    = (array) ($_POST['item_id'] ?? []);
        $specs  = (array) ($_POST['specs'] ?? []);
        $dims   = (array) ($_POST['dimension'] ?? []);
        $qtys   = (array) ($_POST['qty'] ?? []);
        $uoms   = (array) ($_POST['uom'] ?? []);
        $sups   = (array) ($_POST['preferred_supplier'] ?? []);
        foreach ($names as $i => $name) {
            if (trim((string) $name) === '') {
                continue;
            }
            $qty = (float) str_replace(',', '', (string) ($qtys[$i] ?? 0));
            $items[] = [
                'item_id'            => (int) ($ids[$i] ?? 0) ?: null,
                'item_code'          => trim((string) ($codes[$i] ?? '')),
                'item_name'          => trim((string) $name),
                'specs'              => trim((string) ($specs[$i] ?? '')),
                'dimension'          => trim((string) ($dims[$i] ?? '')),
                'qty'                => $qty,
                'uom'                => trim((string) ($uoms[$i] ?? '')),
                'preferred_supplier' => trim((string) ($sups[$i] ?? '')),
            ];
        }
        return $items;
    }

    /** JSON autocomplete from the Item Master List (items table only).
     *  Non-warehouse departments may only pick SUPPLIES items; free-text
     *  entry still allows items outside the master list. */
    public function searchItems() {
        header('Content-Type: application/json');
        $query = trim($_GET['q'] ?? '');
        $like = "%{$query}%";
        // Warehouse users can pick any item type; every other department is
        // restricted to Supplies only.
        $suppliesOnly = ($_SESSION['department'] ?? '') !== 'warehouse';
        $where = $suppliesOnly ? " AND item_type = 'SUPPLIES'" : '';
        if ($query === '') {
            $sql = "SELECT item_id, item_code, item_description, item_uom
                    FROM items WHERE `remove` = 0 AND status = 1{$where}
                    ORDER BY item_code ASC LIMIT 20";
            $stmt = BaseModel::getConnection()->prepare($sql);
            $stmt->execute();
        } else {
            $sql = "SELECT item_id, item_code, item_description, item_uom
                    FROM items WHERE `remove` = 0 AND status = 1{$where}
                    AND (item_code LIKE :q1 OR item_description LIKE :q2)
                    ORDER BY item_code ASC LIMIT 20";
            $stmt = BaseModel::getConnection()->prepare($sql);
            $stmt->execute(['q1' => $like, 'q2' => $like]);
        }
        echo json_encode($stmt->fetchAll());
        exit;
    }

    /** Approve (POST): requires the signed hardcopy scan. Creator only. */
    public function approve() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=pr');
            exit;
        }
        $prId = (int) ($_POST['pr_id'] ?? 0);
        $pr = $this->prModel->getById($prId);
        if (!$pr) {
            $_SESSION['error'] = 'Purchase request not found.';
            header('Location: ?controller=pr');
            exit;
        }
        if ((int) $pr['created_by'] !== (int) $_SESSION['user_id']) {
            $_SESSION['error'] = 'Only the requestor who created this PR can approve it.';
            header('Location: ?controller=pr');
            exit;
        }
        if ($pr['status'] !== 'submitted') {
            $_SESSION['error'] = 'This purchase request is already approved.';
            header('Location: ?controller=pr');
            exit;
        }
        if (!isset($_FILES['signed_file']) || $_FILES['signed_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Attach the signed Purchase Request Form (scan/photo) to approve.';
            header('Location: ?controller=pr');
            exit;
        }
        $file = $_FILES['signed_file'];
        if (!in_array($file['type'], self::ALLOWED_SIGN_TYPES, true)) {
            $_SESSION['error'] = 'Invalid file type. Allowed: JPG, PNG, GIF, WebP, PDF.';
            header('Location: ?controller=pr');
            exit;
        }
        if ($file['size'] > self::MAX_SIGN_SIZE) {
            $_SESSION['error'] = 'File size must be less than 10MB.';
            header('Location: ?controller=pr');
            exit;
        }
        $uploadDir = __DIR__ . '/../../uploads/pr/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'signed_' . $prId . '_' . time() . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
            $_SESSION['error'] = 'Failed to save the attached file.';
            header('Location: ?controller=pr');
            exit;
        }
        $this->prModel->approveWithAttachment($prId, [
            'name' => $file['name'],
            'path' => 'uploads/pr/' . $fileName,
            'type' => $file['type'],
        ], (int) $_SESSION['user_id']);
        AuditModel::log($_SESSION['user_id'], 'UPDATE', 'pr', 'Approved Purchase Request ' . $pr['pr_number'] . ' with signed PRF attachment ' . $fileName, null, ['file' => $fileName], 'purchase_request', $prId);
        $_SESSION['success'] = 'Purchase Request ' . $pr['pr_number'] . ' approved and forwarded to Procurement.';
        header('Location: ?controller=pr');
        exit;
    }

    /** Standalone printable PRF (Cianan Corp. form, MPF-001-01). */
    public function print() {
        $prId = (int) ($_GET['id'] ?? 0);
        $pr = $this->prModel->getById($prId);
        if (!$pr) {
            $_SESSION['error'] = 'Purchase request not found.';
            header('Location: ?controller=pr');
            exit;
        }
        $items = $this->prModel->getItems($prId);
        extract(['pr' => $pr, 'items' => $items]);
        include __DIR__ . '/../views/print.php';
        exit;
    }

    /** Procurement queue — approved PRFs awaiting purchasing action. */
    public function procurement() {
        $role = $_SESSION['role'] ?? '';
        $department = $_SESSION['department'] ?? '';
        if (!$this->isProcurementAllowed()) {
            $_SESSION['error'] = 'You do not have access to that feature.';
            header('Location: ' . \App\Helpers\RoleHelper::homeUrl());
            exit;
        }
        $requests = $this->prModel->getRequests(null, 'approved');
        $prModel = new ProcurementPrModel();
        $prsByPrf = $prModel->getByPrfIds(array_map(function ($r) { return (int) $r['pr_id']; }, $requests));
        $this->render('procurement', [
            'page_title' => 'Purchase Requests — Procurement',
            'requests'   => $requests,
            'prsByPrf'   => $prsByPrf,
        ]);
    }

    /** Can the current user access Procurement PR actions? */
    private function isProcurementAllowed(): bool {
        $role = $_SESSION['role'] ?? '';
        $department = $_SESSION['department'] ?? '';
        return ($department === 'admin')
            || ($department === 'warehouse' && in_array($role, ['whse_procurement', 'whse_admin'], true));
    }

    /** JSON: approved PRF + items to prefill the Create PR modal. */
    public function prfData() {
        header('Content-Type: application/json');
        if (!$this->isProcurementAllowed()) {
            http_response_code(403);
            echo json_encode(['error' => 'You do not have access to Procurement PRs.']);
            exit;
        }
        $prf = $this->prModel->getById((int) ($_GET['id'] ?? 0));
        if (!$prf || $prf['status'] !== 'approved') {
            http_response_code(404);
            echo json_encode(['error' => 'Purchase request not found or not approved.']);
            exit;
        }
        $prModel = new ProcurementPrModel();
        echo json_encode([
            'prf'       => $prf,
            'items'     => $this->prModel->getItems((int) $prf['pr_id']),
            'pr_number' => ProcurementPrModel::nextPrNumber(),
            'sites'     => ProcurementPrModel::PO_SITES,
        ]);
        exit;
    }

    /** Create a formal PR from an approved PRF (POST from the Create PR modal). */
    public function createPr() {
        if (!$this->isProcurementAllowed()) {
            $_SESSION['error'] = 'You do not have access to that feature.';
            header('Location: ' . \App\Helpers\RoleHelper::homeUrl());
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?controller=pr&action=procurement');
            exit;
        }
        $prf = $this->prModel->getById((int) ($_POST['prf_id'] ?? 0));
        if (!$prf || $prf['status'] !== 'approved') {
            $_SESSION['error'] = 'The source purchase request is not approved.';
            header('Location: ?controller=pr&action=procurement');
            exit;
        }
        $sites = ProcurementPrModel::PO_SITES;
        $poSite = $_POST['po_site'] ?? '001';
        $shipTo = $_POST['ship_to'] ?? '001';
        if (!isset($sites[$poSite]) || !isset($sites[$shipTo])) {
            $_SESSION['error'] = 'Invalid PO Site / Ship To selection.';
            header('Location: ?controller=pr&action=procurement');
            exit;
        }
        $items = [];
        $descs   = (array) ($_POST['description'] ?? []);
        $qtys    = (array) ($_POST['qty'] ?? []);
        $units   = (array) ($_POST['unit'] ?? []);
        $accts   = (array) ($_POST['account_charged'] ?? []);
        $dates   = (array) ($_POST['date_required'] ?? []);
        $codes   = (array) ($_POST['item_code'] ?? []);
        foreach ($descs as $i => $desc) {
            if (trim((string) $desc) === '') {
                continue;
            }
            $qty = (float) str_replace(',', '', (string) ($qtys[$i] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            $items[] = [
                'description'     => trim((string) $desc),
                'qty'             => $qty,
                'unit'            => trim((string) ($units[$i] ?? '')),
                'account_charged' => trim((string) ($accts[$i] ?? '')),
                'date_required'   => trim((string) ($dates[$i] ?? '')),
                'item_code'       => trim((string) ($codes[$i] ?? '')),
            ];
        }
        if (!$items) {
            $_SESSION['error'] = 'Add at least one line item with a quantity.';
            header('Location: ?controller=pr&action=procurement');
            exit;
        }
        // Accept a manually edited PR number when it matches the PR###### format;
        // otherwise fall back to the global auto-increment sequence.
        $prNumber = strtoupper(trim($_POST['pr_number'] ?? ''));
        if (!preg_match('/^PR\d{1,6}$/', $prNumber)) {
            $prNumber = ProcurementPrModel::nextPrNumber();
        }
        $prModel = new ProcurementPrModel();
        $header = [
            'pr_number'     => $prNumber,
            'prf_id'        => (int) $prf['pr_id'],
            'po_site_code'  => $poSite,
            'ship_to_code'  => $shipTo,
            'priority'      => $prf['priority'] === 'immediate' ? 'immediate' : 'standard',
            'pr_date'       => $_POST['pr_date'] ?? date('Y-m-d'),
            'department'    => $prf['department'],
            'requisitioner' => $prf['prepared_by'],
            'purpose'       => $prf['purpose'],
            'created_by'    => (int) $_SESSION['user_id'],
        ];
        try {
            $prId = $prModel->createPr($header, $items);
            AuditModel::log($_SESSION['user_id'], 'CREATE', 'procurement_pr', 'Created Purchase Requisition ' . $header['pr_number'] . ' from PRF ' . $prf['pr_number'] . ' with ' . count($items) . ' line item(s)', null, ['pr_number' => $header['pr_number'], 'prf_number' => $prf['pr_number']], 'procurement_pr', $prId);
            $_SESSION['success'] = 'Purchase Requisition ' . $header['pr_number'] . ' created from ' . $prf['pr_number'] . '.';
        } catch (\Throwable $e) {
            error_log('PR createPr error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to create the purchase requisition.';
        }
        header('Location: ?controller=pr&action=procurement');
        exit;
    }

    /** Standalone printable Purchase Requisition (PR###### ERP format). */
    public function printPr() {
        if (!$this->isProcurementAllowed()) {
            $_SESSION['error'] = 'You do not have access to that feature.';
            header('Location: ' . \App\Helpers\RoleHelper::homeUrl());
            exit;
        }
        $prModel = new ProcurementPrModel();
        $pr = $prModel->getById((int) ($_GET['id'] ?? 0));
        if (!$pr) {
            $_SESSION['error'] = 'Purchase requisition not found.';
            header('Location: ?controller=pr&action=procurement');
            exit;
        }
        $items = $prModel->getItems((int) $pr['pr_id']);
        $prModel->markPrinted((int) $pr['pr_id']);
        $pr['printed'] = 1;
        $sites = ProcurementPrModel::PO_SITES;
        $poSite = $sites[$pr['po_site_code']] ?? reset($sites);
        $shipTo = $sites[$pr['ship_to_code']] ?? reset($sites);
        extract(['pr' => $pr, 'items' => $items, 'poSite' => $poSite, 'shipTo' => $shipTo,
                 'poSiteCode' => $pr['po_site_code'], 'shipToCode' => $pr['ship_to_code']]);
        include __DIR__ . '/../views/print_pr.php';
        exit;
    }

    /** Soft-delete own submitted PR. */
    public function delete() {
        $prId = (int) ($_GET['id'] ?? 0);
        $pr = $this->prModel->getById($prId);
        if ($pr && (int) $pr['created_by'] === (int) $_SESSION['user_id'] && $pr['status'] === 'submitted') {
            $this->prModel->softDelete($prId);
            AuditModel::log($_SESSION['user_id'], 'DELETE', 'pr', 'Deleted Purchase Request ' . $pr['pr_number'], null, null, 'purchase_request', $prId);
            $_SESSION['success'] = 'Purchase Request ' . $pr['pr_number'] . ' deleted.';
        } else {
            $_SESSION['error'] = 'Only your own submitted requests can be deleted.';
        }
        header('Location: ?controller=pr');
        exit;
    }

    /**
     * Render a PR view inside the logged-in user's own department layout so
     * the full role-based sidebar (Customer PO, Deliveries, Purchasing PO,
     * Approvals, ...) stays intact while on the Purchase Request page.
     * Every department layout already highlights Purchase Request as active
     * when ?controller=pr is requested.
     */
    private function render($view, $data = []) {
        $department = $_SESSION['department'] ?? '';
        switch ($department) {
            case 'admin':
                $layout = __DIR__ . '/../../admin/views/layouts/main.php';
                break;
            case 'production':
                $layout = __DIR__ . '/../../production/views/layouts/main.php';
                break;
            case 'finance':
                $layout = __DIR__ . '/../../finance/views/layouts/main.php';
                break;
            case 'qa':
                $layout = __DIR__ . '/../../qa/views/layouts/main.php';
                break;
            case 'rnd':
                $layout = __DIR__ . '/../../rnd/views/layouts/main.php';
                break;
            case 'qc':
                $layout = __DIR__ . '/../../qc/views/layouts/main.php';
                break;
            case 'warehouse':
                $layout = __DIR__ . '/../../warehouse/views/layouts/main.php';
                break;
            default:
                // Unknown/blank department: standard warehouse layout with
                // only the items a non-warehouse requestor is allowed to see.
                $layout = __DIR__ . '/../../warehouse/views/layouts/main.php';
                break;
        }
        if ($department === 'warehouse') {
            $role = \App\Helpers\RoleHelper::currentRole();
            $data['role'] = $role;
            $data['roleLabel'] = \App\Helpers\RoleHelper::label($role);
            $data['sideMenu'] = \App\Helpers\RoleHelper::warehouseMenu($role);
            try {
                $warehouseModel = new WarehouseModel();
                $data['mrpApprovalCount'] = $warehouseModel->getMrpApprovalCount('whse');
                $data['isMrpApprover'] = $warehouseModel->isWarehouseApprover($_SESSION['user_id'] ?? null);
            } catch (\Throwable $e) {
                $data['mrpApprovalCount'] = 0;
                $data['isMrpApprover'] = false;
            }
        } elseif ($department === '' || $department === null) {
            $data['sideMenu'] = \App\Helpers\RoleHelper::warehouseMenu(null);
        }
        extract($data);
        ob_start();
        include __DIR__ . "/../views/{$view}.php";
        $content = ob_get_clean();
        include $layout;
    }
}
