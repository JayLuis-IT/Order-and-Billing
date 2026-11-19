<?php
namespace App\Controllers;

use App\Models\PurchaseRequestModel;
use App\Models\ProcurementPrModel;
use App\Models\GlAccountModel;
use App\Models\SupplierModel;
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

    /** Requestor list — own PRs; procurement roles + System Admin see all. */
    public function index() {
        $statusFilter = $_GET['status'] ?? '';
        $seeAll = $this->isProcurementAllowed();
        $requests = $this->prModel->getRequests(
            $seeAll ? null : (int) ($_SESSION['user_id'] ?? 0),
            $statusFilter !== '' ? $statusFilter : null
        );
        $this->render('index', [
            'page_title'   => 'Purchase Request',
            'requests'     => $requests,
            'statusFilter' => $statusFilter,
            'seeAll'       => $seeAll,
            'canProcess'   => $this->isProcurementAllowed(),
            'suppliers'    => (new SupplierModel())->getAll(true),
            // Formal PRs for In Process rows — the printer opens printPr for them.
            'prsByPrf'     => (new ProcurementPrModel())->getByPrfIds(array_map(
                fn($r) => (int) $r['pr_id'],
                array_filter($requests, fn($r) => $r['status'] === 'in_process')
            )),
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
            // Empty-safe: layout modal-reset blanks the hidden field on close,
            // so '' must fall back to the session department, not win over it.
            'department'      => trim($_POST['department'] ?? '') ?: ($_SESSION['department'] ?? ''),
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
        if (!$pr || (int) $pr['created_by'] !== (int) $_SESSION['user_id'] || $pr['status'] !== 'pending') {
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
        if (!$pr || (int) $pr['created_by'] !== (int) $_SESSION['user_id'] || $pr['status'] !== 'pending') {
            $_SESSION['error'] = 'Only your own pending requests can be edited.';
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
        // Warehouse users and procurement viewers (Process modal) can pick any
        // item type; ordinary requestors are restricted to Supplies only.
        $suppliesOnly = ($_SESSION['department'] ?? '') !== 'warehouse' && !$this->isProcurementAllowed();
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

    /** JSON autocomplete of active GL accounts for the Account Charged field
     *  on the Process modal — procurement viewers only. */
    public function searchGlAccounts() {
        header('Content-Type: application/json');
        if (!$this->isProcurementAllowed()) {
            http_response_code(403);
            echo json_encode(['error' => 'You do not have access to that feature.']);
            exit;
        }
        $query = trim($_GET['q'] ?? '');
        $like = "%{$query}%";
        $sql = "SELECT gl_account_code, gl_account_description
                FROM gl_accounts
                WHERE `remove` = 0 AND status = 1
                  AND (gl_account_code LIKE :q1 OR gl_account_description LIKE :q2)
                ORDER BY gl_account_code ASC
                LIMIT 20";
        $stmt = BaseModel::getConnection()->prepare($sql);
        $stmt->execute(['q1' => $like, 'q2' => $like]);
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
        extract(['pr' => $pr, 'items' => $items, 'quotes' => $this->prModel->getSuppliersForPr($prId)]);
        include __DIR__ . '/../views/print.php';
        exit;
    }

    /** Procurement queue — every status: pending (add suppliers+prices),
     *  submitted (awaiting requestor signatures), approved (create the PR),
     *  in_process (formal PR exists). */
    public function procurement() {
        $role = $_SESSION['role'] ?? '';
        $department = $_SESSION['department'] ?? '';
        if (!$this->isProcurementAllowed()) {
            $_SESSION['error'] = 'You do not have access to that feature.';
            header('Location: ' . \App\Helpers\RoleHelper::homeUrl());
            exit;
        }
        $requests = $this->prModel->getRequests(null, ['pending', 'submitted', 'approved', 'in_process']);
        $prModel = new ProcurementPrModel();
        $prsByPrf = $prModel->getByPrfIds(array_map(function ($r) { return (int) $r['pr_id']; }, $requests));
        $this->render('procurement', [
            'page_title'   => 'Purchase Requests — Procurement',
            'requests'     => $requests,
            'prsByPrf'     => $prsByPrf,
            'suppliers'    => (new SupplierModel())->getAll(true),
        ]);
    }

    /** Supplier & price entry (procurement): pending -> submitted. The quotes
     *  are saved on the request, which returns to the requestor to print,
     *  sign and approve. Strictly pending — submitted is locked. */
    public function supplierSubmit() {
        if (!$this->isProcurementAllowed()) {
            $_SESSION['error'] = 'You do not have access to that feature.';
            header('Location: ' . \App\Helpers\RoleHelper::homeUrl());
            exit;
        }
        $back = (($_POST['back'] ?? 'queue') === 'list')
            ? '?controller=pr'
            : '?controller=pr&action=procurement';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $back);
            exit;
        }

        $prId = (int) ($_POST['prf_id'] ?? 0);
        $pr = $prId > 0 ? $this->prModel->getById($prId) : null;
        if (!$pr) {
            $_SESSION['error'] = 'Purchase request not found.';
            header('Location: ' . $back);
            exit;
        }
        if ($pr['status'] !== 'pending') {
            $_SESSION['error'] = 'Only pending requests can be quoted. This request is already with the requestor.';
            header('Location: ' . $back);
            exit;
        }

        $supplierModel = new SupplierModel();
        $priIds = (array) ($_POST['pri_id'] ?? []);
        $supplierIds = (array) ($_POST['supplier_id'] ?? []);
        $prices = (array) ($_POST['price'] ?? []);

        // Every line item of this request must end up quoted.
        $itemIds = [];
        foreach ($this->prModel->getItems($prId) as $it) {
            $itemIds[(int) $it['pri_id']] = true;
        }
        if (!$itemIds) {
            $_SESSION['error'] = 'This request has no line items.';
            header('Location: ' . $back);
            exit;
        }

        $rows = [];
        $quoted = [];
        foreach ($supplierIds as $i => $sid) {
            $sid = (int) $sid;
            $priceRaw = trim((string) ($prices[$i] ?? ''));
            $priId = (int) ($priIds[$i] ?? 0);
            if ($sid <= 0 && $priceRaw === '') {
                continue; // untouched draft row
            }
            if (!isset($itemIds[$priId])) {
                $_SESSION['error'] = 'Invalid line item on one of the supplier rows.';
                header('Location: ' . $back);
                exit;
            }
            if ($sid <= 0) {
                $_SESSION['error'] = 'Select a supplier for every priced row.';
                header('Location: ' . $back);
                exit;
            }
            $supplier = $supplierModel->getById($sid);
            if (!$supplier || !$supplier['status']) {
                $_SESSION['error'] = 'One of the selected suppliers is invalid or inactive.';
                header('Location: ' . $back);
                exit;
            }
            if ($priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw < 0) {
                $_SESSION['error'] = 'A price greater than or equal to 0 is required for ' . $supplier['supplier_name'] . '.';
                header('Location: ' . $back);
                exit;
            }
            $rows[] = [
                'pri_id'      => $priId,
                'supplier_id' => $sid,
                'price'       => (float) $priceRaw,
            ];
            $quoted[$priId] = true;
        }

        $unquoted = 0;
        foreach (array_keys($itemIds) as $pid) {
            if (empty($quoted[$pid])) {
                $unquoted++;
            }
        }
        if ($unquoted > 0) {
            $_SESSION['error'] = 'Add at least one supplier and price for every requested item — ' . $unquoted . ' item(s) still unquoted.';
            header('Location: ' . $back);
            exit;
        }

        try {
            $this->prModel->replaceSuppliers($prId, $rows, (int) $_SESSION['user_id']);
            if (!$this->prModel->markSubmitted($prId)) {
                $_SESSION['error'] = 'This request is no longer pending.';
                header('Location: ' . $back);
                exit;
            }
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'pr',
                'Added ' . count($rows) . ' supplier quote(s) across ' . count($quoted) . ' item(s) to ' . $pr['pr_number'] . ' and returned it to the requestor (pending -> submitted)',
                ['status' => 'pending'],
                ['status' => 'submitted', 'suppliers' => array_map(fn($r) => ['pri_id' => $r['pri_id'], 'supplier_id' => $r['supplier_id'], 'price' => $r['price']], $rows)],
                'purchase_request', $prId);
            $_SESSION['success'] = $pr['pr_number'] . ' updated with ' . count($rows) . ' supplier quote(s) across ' . count($quoted) . ' item(s) — sent back to the requestor to print, sign and approve.';
        } catch (\Throwable $e) {
            error_log('supplierSubmit error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to save supplier quotes.';
        }
        header('Location: ' . $back);
        exit;
    }

    /**
     * Process (procurement): create the formal PR###### from the PRF and
     * flip the PRF approved -> in_process in one action (merged the former
     * createPr flow). Modal POST with prefilled line items.
     */
    public function process() {
        if (!$this->isProcurementAllowed()) {
            $_SESSION['error'] = 'You do not have access to that feature.';
            header('Location: ' . \App\Helpers\RoleHelper::homeUrl());
            exit;
        }
        $back = (($_POST['back'] ?? 'queue') === 'list')
            ? '?controller=pr'
            : '?controller=pr&action=procurement';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $back);
            exit;
        }

        $prfId = (int) ($_POST['prf_id'] ?? 0);
        $prf = $prfId > 0 ? $this->prModel->getById($prfId) : null;
        if (!$prf) {
            $_SESSION['error'] = 'Purchase request not found.';
            header('Location: ' . $back);
            exit;
        }
        if ($prf['status'] === 'in_process') {
            $_SESSION['error'] = 'This purchase request is already being processed.';
            header('Location: ' . $back);
            exit;
        }
        if ($prf['status'] !== 'approved') {
            $_SESSION['error'] = 'Only approved purchase requests can be processed.';
            header('Location: ' . $back);
            exit;
        }

        $prModel = new ProcurementPrModel();
        if ($prModel->getByPrfIds([$prfId])) {
            $_SESSION['error'] = 'A Purchase Requisition already exists for this PRF.';
            header('Location: ' . $back);
            exit;
        }

        $sites = ProcurementPrModel::PO_SITES;
        $poSite = $_POST['po_site'] ?? '001';
        $shipTo = $_POST['ship_to'] ?? '001';
        if (!isset($sites[$poSite]) || !isset($sites[$shipTo])) {
            $_SESSION['error'] = 'Invalid PO Site / Ship To selection.';
            header('Location: ' . $back);
            exit;
        }

        $descs = (array) ($_POST['description'] ?? []);
        $qtys  = (array) ($_POST['qty'] ?? []);
        $units = (array) ($_POST['unit'] ?? []);
        $accts = (array) ($_POST['account_charged'] ?? []);
        $acdescs = (array) ($_POST['account_desc'] ?? []);
        $dates = (array) ($_POST['date_required'] ?? []);
        $codes = (array) ($_POST['item_code'] ?? []);
        $items = [];
        foreach ($descs as $i => $desc) {
            if (trim((string) $desc) === '') {
                continue;
            }
            $qty = (float) str_replace(',', '', (string) ($qtys[$i] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            $items[] = [
                'description'        => trim((string) $desc),
                'qty'                => $qty,
                'unit'               => trim((string) ($units[$i] ?? '')),
                'account_charged'    => trim((string) ($accts[$i] ?? '')),
                'account_description'=> trim((string) ($acdescs[$i] ?? '')),
                'date_required'      => trim((string) ($dates[$i] ?? '')),
                'item_code'          => trim((string) ($codes[$i] ?? '')),
            ];
        }
        if (!$items) {
            $_SESSION['error'] = 'Add at least one line item with a quantity.';
            header('Location: ' . $back);
            exit;
        }

        // PR number is always system generated — any posted value is ignored.
        $prNumber = ProcurementPrModel::nextPrNumber();
        $header = [
            'pr_number'     => $prNumber,
            'prf_id'        => $prfId,
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
            $newPrId = $prModel->createPr($header, $items);
            $flipped = $this->prModel->markInProcess($prfId);
            if (!$flipped) {
                error_log('process: PR created but PRF status flip failed for prf #' . $prfId);
            }
            AuditModel::log($_SESSION['user_id'], 'CREATE', 'procurement_pr', 'Created Purchase Requisition ' . $header['pr_number'] . ' from PRF ' . $prf['pr_number'] . ' with ' . count($items) . ' line item(s)', null, ['pr_number' => $header['pr_number'], 'prf_number' => $prf['pr_number']], 'procurement_pr', $newPrId);
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'pr', 'Marked Purchase Request ' . $prf['pr_number'] . ' as In Process', ['status' => 'approved'], ['status' => 'in_process'], 'purchase_request', $prfId);
            $_SESSION['success'] = 'Purchase Requisition ' . $header['pr_number'] . ' created from ' . $prf['pr_number'] . ' — the request is now In Process.';
        } catch (\Throwable $e) {
            error_log('PR process error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to process the purchase request.';
        }
        header('Location: ' . $back);
        exit;
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
        if (!$prf || !in_array($prf['status'], ['pending', 'submitted', 'approved', 'in_process'], true)) {
            http_response_code(404);
            echo json_encode(['error' => 'Purchase request not found.']);
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

    /** Standalone printable Purchase Requisition (PR###### ERP format). */
    public function printPr() {
        $prModel = new ProcurementPrModel();
        $pr = $prModel->getById((int) ($_GET['id'] ?? 0));
        if (!$pr) {
            $_SESSION['error'] = 'Purchase requisition not found.';
            header('Location: ?controller=pr');
            exit;
        }
        // Procurement/admin, or the requestor who created the source PRF —
        // the Purchase Request list's printer opens this for In Process rows.
        $allowed = $this->isProcurementAllowed();
        if (!$allowed && !empty($pr['prf_id'])) {
            $prf = $this->prModel->getById((int) $pr['prf_id']);
            $allowed = $prf && (int) $prf['created_by'] === (int) $_SESSION['user_id'];
        }
        if (!$allowed) {
            $_SESSION['error'] = 'You do not have access to that feature.';
            header('Location: ' . \App\Helpers\RoleHelper::homeUrl());
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
        if ($pr && (int) $pr['created_by'] === (int) $_SESSION['user_id'] && $pr['status'] === 'pending') {
            $this->prModel->softDelete($prId);
            AuditModel::log($_SESSION['user_id'], 'DELETE', 'pr', 'Deleted Purchase Request ' . $pr['pr_number'], null, null, 'purchase_request', $prId);
            $_SESSION['success'] = 'Purchase Request ' . $pr['pr_number'] . ' deleted.';
        } else {
            $_SESSION['error'] = 'Only your own pending requests can be deleted.';
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
