<?php
namespace App\Controllers;

use App\Models\ItemModel;
use App\Models\WarehouseModel;
use App\Models\BomModel;
use App\Models\ItemImportModel;
use App\Models\SupplierModel;
use App\Helpers\Pagination;
use App\Models\AuditModel;
use App\Helpers\SpreadsheetReader;

class RndController {
    private $itemModel;
    private $warehouseModel;
    private $bomModel;
    private $itemImportModel;
    private $supplierModel;

    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?controller=auth&action=login');
            exit;
        }
        if (($_SESSION['department'] ?? '') !== 'rnd') {
            $_SESSION['error'] = 'Unauthorized access';
            header('Location: ' . \App\Helpers\RoleHelper::homeUrl());
            exit;
        }
        $this->itemModel = new ItemModel();
        $this->warehouseModel = new WarehouseModel();
        $this->bomModel = new BomModel();
        $this->itemImportModel = new ItemImportModel();
        $this->supplierModel = new SupplierModel();
    }

    public function index() {
        $this->boms();
    }

    // ─── Raw Materials Management ─────────────────────────────────────────────

    // ─── Raw Materials (Deprecated — use Warehouse Items module) ─────────────

    public function rawMaterials() {
        $_SESSION['error'] = 'Items (RM PM FG) are now managed under Warehouse.';
        header('Location: ?controller=rnd&action=items');
        exit;
    }

    public function rawMaterialCreate() { $this->rawMaterials(); }
    public function rawMaterialUpdate() { $this->rawMaterials(); }
    public function rawMaterialDelete() { $this->rawMaterials(); }
    public function rawMaterialToggleStatus() { $this->rawMaterials(); }
    public function rawMaterialImportPreview() { $this->rawMaterials(); }
    public function rawMaterialImportConfirm() { $this->rawMaterials(); }

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
        $conn = \App\Core\BaseModel::getConnection();
        $stmt = $conn->prepare("SELECT item_id, item_code, item_description, item_uom, item_type
            FROM items WHERE item_type IN ('RM','PM','SFG') AND status = 1 AND `remove` = 0
            ORDER BY item_code ASC");
        $stmt->execute();
        $data['allIngredients'] = $stmt->fetchAll();
        $data['customers'] = $this->warehouseModel->getCustomers();
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
                if (!ctype_digit($customerIdInput) || !$this->warehouseModel->getCustomerById((int)$customerIdInput)) {
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

                $componentItemIds = $_POST['component_item_id'] ?? [];
                $componentDosages = $_POST['component_dosage'] ?? [];
                $componentWastages = $_POST['component_wastage'] ?? [];
                $componentPhases = $_POST['component_phase_code'] ?? [];
                $componentUoms = $_POST['component_uom'] ?? [];

                $items = [];
                if (!empty($componentItemIds)) {
                    foreach ($componentItemIds as $idx => $itemId) {
                        $itemId = intval($itemId);
                        $dosage = floatval($componentDosages[$idx] ?? 0);
                        $wastage = floatval($componentWastages[$idx] ?? 0);
                        $phaseCode = trim($componentPhases[$idx] ?? '');
                        $componentUom = mb_substr(trim($componentUoms[$idx] ?? ''), 0, 50);
                        if ($itemId > 0 && $dosage > 0) {
                            if ($phaseCode === '') {
                                throw new \Exception("Phase / Comp Code is required for every component.");
                            }
                            $items[] = [
                                'item_id' => $itemId,
                                'dosage_rate' => $dosage,
                                'wastage_allowance_pct' => $wastage,
                                'phase_code' => $phaseCode,
                                'uom' => $componentUom
                            ];
                        }
                    }
                }

                if (empty($items)) {
                    throw new \Exception("Please add at least one component with a valid dosage.");
                }

                $bomId = $this->bomModel->create($fgItemId, $bomCode, $fillVolume, $uom ?: 'PCS', $batchUnitDivisor, false, $customerId, 'active');
                $this->bomModel->replaceBomItems($bomId, $items);

                AuditModel::log($_SESSION['user_id'], 'CREATE', 'rnd',
                    'Created BOM for item #' . $fgItemId . ' with ' . count($items) . ' components',
                    null, ['fg_item_id' => $fgItemId, 'bom_code' => $bomCode, 'customer_id' => $customerId, 'fill_volume' => $fillVolume, 'uom' => $uom, 'batch_unit_divisor' => $batchUnitDivisor, 'components' => count($items)],
                    'bom', $bomId);

                $_SESSION['success'] = 'BOM created with ' . count($items) . ' components';
                header('Location: ?controller=rnd&action=boms');
                exit;
            } catch (\Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header('Location: ?controller=rnd&action=boms');
                exit;
            }
        }
        header('Location: ?controller=rnd&action=boms');
        exit;
    }

    public function bomEdit() {
        $id = $_GET['id'] ?? null;
        $data['bom'] = $this->bomModel->getById($id);
        if (!$data['bom']) {
            $_SESSION['error'] = 'BOM not found';
            header('Location: ?controller=rnd&action=boms');
            exit;
        }
        $data['bomItems'] = $this->bomModel->getItemsByBomId($id);
        $data['allItems'] = $this->itemModel->getAll(false);
        $conn = \App\Core\BaseModel::getConnection();
        $stmt = $conn->prepare("SELECT item_id, item_code, item_description, item_uom, item_type
            FROM items WHERE item_type IN ('RM','PM','SFG') AND status = 1 AND `remove` = 0
            ORDER BY item_code ASC");
        $stmt->execute();
        $data['allIngredients'] = $stmt->fetchAll();
        $data['customers'] = $this->warehouseModel->getCustomers();
        $data['page_title'] = 'Edit BOM - ' . ($data['bom']['fg_name'] ?? '');
        $this->render('boms/edit', $data);
    }

    public function bomUpdate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $id = $_POST['bom_id'] ?? null;
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
                if (!ctype_digit($customerIdInput) || !$this->warehouseModel->getCustomerById((int)$customerIdInput)) {
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
                AuditModel::log($_SESSION['user_id'], 'UPDATE', 'rnd', 'Updated BOM #' . $id, null, ['fg_item_id' => $fgItemId, 'bom_code' => $bomCode, 'customer_id' => $customerId, 'fill_volume' => $fillVolume, 'uom' => $uom, 'batch_unit_divisor' => $batchUnitDivisor], 'bom', $id);
                $_SESSION['success'] = 'BOM updated';
            } catch (\Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }
        header('Location: ?controller=rnd&action=bomEdit&id=' . ($_POST['bom_id'] ?? ''));
        exit;
    }

    public function bomDelete() {
        $id = $_GET['id'] ?? null;
        try {
            $old = $this->bomModel->getById($id);
            $this->bomModel->delete($id);
            AuditModel::log($_SESSION['user_id'], 'DELETE', 'rnd', 'Deleted BOM: ' . ($old['fg_name'] ?? $id), $old, null, 'bom', $id);
            $_SESSION['success'] = 'BOM deleted';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete BOM: ' . $e->getMessage();
        }
        header('Location: ?controller=rnd&action=boms');
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
            $phaseCode = trim($_POST['phase_code'] ?? '');
            $uom = mb_substr(trim($_POST['uom'] ?? ''), 0, 50);

            if (!$bomId || !$itemId) {
                http_response_code(400);
                echo json_encode(['error' => 'BOM ID and ingredient item are required']);
                exit;
            }
            if ($phaseCode === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Phase / Comp Code is required']);
                exit;
            }

            $result = $this->bomModel->addItem($bomId, $itemId, $dosageRate, $wastagePct, $phaseCode, $uom);
            AuditModel::log($_SESSION['user_id'], 'CREATE', 'rnd', 'Added ingredient to BOM #' . $bomId, null, ['item_id' => $itemId, 'dosage_rate' => $dosageRate, 'phase_code' => $phaseCode, 'uom' => $uom], 'bom_item', $result);
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
            $phaseCode = trim($_POST['phase_code'] ?? '');
            $uom = mb_substr(trim($_POST['uom'] ?? ''), 0, 50);

            if (!$bomItemId || !$itemId) {
                http_response_code(400);
                echo json_encode(['error' => 'BOM item ID and ingredient item are required']);
                exit;
            }
            if ($phaseCode === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Phase / Comp Code is required']);
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

    // ─── Bulk Import: BOM Components ─────────────────────────────────────────────

    public function bomImportPreview() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['import_file'])) {
            $_SESSION['error'] = 'No file uploaded.';
            header('Location: ?controller=rnd&action=boms');
            exit;
        }

        $file = $_FILES['import_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'])) {
            $_SESSION['error'] = 'Only .csv and .xlsx files are supported.';
            header('Location: ?controller=rnd&action=boms');
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            $_SESSION['error'] = 'File size must be less than 5MB.';
            header('Location: ?controller=rnd&action=boms');
            exit;
        }

        try {
            $tempPath = sys_get_temp_dir() . '/' . uniqid('bom_') . '.' . $ext;
            move_uploaded_file($file['tmp_name'], $tempPath);
            $rows = SpreadsheetReader::read($tempPath);
            unlink($tempPath);
        } catch (\Exception $e) {
            if (isset($tempPath) && file_exists($tempPath)) unlink($tempPath);
            $_SESSION['error'] = 'Failed to read file: ' . $e->getMessage();
            header('Location: ?controller=rnd&action=boms');
            exit;
        }

        if (empty($rows)) {
            error_log('BOM Import: 0 rows. File: ' . $file['name'] . ' | Ext: ' . $ext);
            $_SESSION['error'] = 'No data rows found. Ensure Row 1 has headers.';
            header('Location: ?controller=rnd&action=boms');
            exit;
        }
        error_log('BOM Import: Headers: ' . json_encode(array_keys($rows[0])) . ' | Rows: ' . count($rows));

        $conn = \App\Core\BaseModel::getConnection();
        $preview = [];

        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            $errors = [];
            $fgCode = trim($row['fg_item_code'] ?? '');
            $bomCode = trim($row['bom_code'] ?? '');
            $rmCode = trim($row['rm_code'] ?? '');
            $dosage = floatval($row['dosage_rate'] ?? 0);
            $wastage = floatval($row['wastage_pct'] ?? 0);
            $phaseCode = trim($row['phase_code'] ?? '');
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
            if ($rmCode !== '') {
                $stmt = $conn->prepare("SELECT item_id, item_code, item_description FROM items WHERE item_code = :code AND item_type IN ('RM','PM','SFG') AND `remove` = 0");
                $stmt->execute(['code' => $rmCode]);
                $rmItem = $stmt->fetch();
                if (!$rmItem) $errors[] = 'Ingredient item not found: ' . htmlspecialchars($rmCode);
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
                'item_id' => $rmItem['item_id'] ?? null,
                'item_name' => $rmItem['item_description'] ?? '',
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
        $data['page_title'] = 'Import BOM Components - Preview';
        $this->render('boms/import_preview', $data);
    }

    public function bomImportConfirm() {
        $preview = $_SESSION['import_preview_bom'] ?? null;
        if (!$preview) {
            $_SESSION['error'] = 'No import data found. Please upload again.';
            header('Location: ?controller=rnd&action=boms');
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

        AuditModel::log($_SESSION['user_id'], 'IMPORT', 'rnd', "Bulk imported BOMs: {$bomsCreated} created, {$bomsUpdated} updated, {$lineCount} line items, {$errors} errors", null, ['boms_created' => $bomsCreated, 'boms_updated' => $bomsUpdated, 'line_items' => $lineCount, 'errors' => $errors], 'bom', null);
        $_SESSION['success'] = "BOM import complete: {$bomsCreated} created, {$bomsUpdated} updated, {$lineCount} line items imported";
        header('Location: ?controller=rnd&action=boms');
        exit;
    }

    // --- Items (RM PM FG) moved to Warehouse module ---

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
        $data['canEdit'] = false;
        $data['canEditItem'] = true;
        $data['itemsController'] = 'rnd';
        // Supplier dropdown for the edit modal (all non-deleted; inactive
        // labeled in the view so an existing link is never silently dropped).
        $data['suppliers'] = $this->supplierModel->getAll(false);
        $data['page_title'] = 'Item Master List';
        $this->renderWarehouse('items/index', $data);
    }

    public function itemUpdate() {
        $back = '?controller=rnd&action=items';
        $qs = [];
        foreach (['back_search' => 'search', 'back_type' => 'item_type', 'back_page' => 'page'] as $get => $out) {
            $val = trim($_GET[$get] ?? '');
            if ($val !== '' && $val !== '0') {
                $qs[$out] = $val;
            }
        }
        if ($qs) $back .= '&' . http_build_query($qs);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $back);
            exit;
        }

        $itemId = (int)($_POST['item_id'] ?? 0);
        $description = trim($_POST['item_description'] ?? '');
        $type = strtoupper(trim($_POST['item_type'] ?? ''));
        $uom = trim($_POST['item_uom'] ?? '');

        $item = $itemId > 0 ? $this->itemImportModel->getItemById($itemId) : null;
        if (!$item) {
            $_SESSION['error'] = 'Item not found.';
            header('Location: ' . $back);
            exit;
        }
        if ($description === '' || $uom === '') {
            $_SESSION['error'] = 'Description and UOM are required.';
            header('Location: ' . $back);
            exit;
        }
        if (!in_array($type, ['RM', 'PM', 'FG', 'SFG', 'SUPPLIES'], true)) {
            $_SESSION['error'] = 'Invalid item type.';
            header('Location: ' . $back);
            exit;
        }

        $supplierRaw = trim($_POST['supplier_id'] ?? '');
        $supplierId = $supplierRaw === '' ? null : (int)$supplierRaw;
        // Passthrough of the stored primary supplier is accepted even if that
        // supplier was soft-deleted after it was linked (the hidden input in
        // the edit modal just carries the existing value back).
        if ($supplierId !== null && $supplierId !== (int)($item['supplier_id'] ?? 0) && !$this->supplierModel->getById($supplierId)) {
            $_SESSION['error'] = 'Supplier not found.';
            header('Location: ' . $back);
            exit;
        }

        $priceRaw = trim($_POST['item_amount'] ?? '');
        if ($priceRaw === '') {
            $price = 0.0;
        } elseif (!is_numeric($priceRaw) || (float)$priceRaw < 0) {
            $_SESSION['error'] = 'Price must be a number greater than or equal to 0.';
            header('Location: ' . $back);
            exit;
        } else {
            $price = round((float)$priceRaw, 2);
        }

        $this->itemImportModel->updateItem($itemId, [
            'item_description' => $description,
            'item_type' => $type,
            'item_uom' => $uom,
            'supplier_id' => $supplierId,
            'item_amount' => $price
        ]);

        AuditModel::log(
            $_SESSION['user_id'],
            'UPDATE',
            'rnd',
            "Item updated: {$item['item_code']}",
            ['item_description' => $item['item_description'], 'item_type' => $item['item_type'], 'item_uom' => $item['item_uom'],
             'supplier_id' => $item['supplier_id'] ?? null, 'item_amount' => $item['item_amount'] ?? 0],
            ['item_description' => $description, 'item_type' => $type, 'item_uom' => $uom,
             'supplier_id' => $supplierId, 'item_amount' => $price],
            'item',
            $itemId
        );

        $_SESSION['success'] = "Item {$item['item_code']} updated successfully.";
        header('Location: ' . $back);
        exit;
    }

    // ─── Item price history (multi-supplier, dated) — AJAX for the edit modal ─

    /** JSON payload behind the edit modal's Suppliers & Prices panes. */
    public function getItemSuppliersPrices() {
        header('Content-Type: application/json');
        $id = (int)($_GET['item_id'] ?? 0);
        $item = $id > 0 ? $this->itemImportModel->getItemById($id) : null;
        if (!$item) {
            echo json_encode(['success' => false, 'error' => 'Item not found.']);
            exit;
        }

        $rows = $this->itemImportModel->getPricesForItem($id);
        $bySupplier = [];
        foreach ($rows as $p) {   // ordered: supplier ASC, date DESC, id DESC
            $bySupplier[(int)$p['supplier_id']][] = $p;
        }

        $suppliers = [];
        foreach ($bySupplier as $sid => $list) {
            $prices = [];
            $prev = null;
            $all = [];
            foreach ($list as $r) {
                $val = (float)$r['price'];
                $all[] = $val;
                $prices[] = [
                    'id' => (int)$r['id'],
                    'price' => $val,
                    'effective_date' => $r['effective_date'],
                    'remarks' => $r['remarks'],
                    'delta' => $prev === null ? null : round($val - $prev, 2)
                ];
                $prev = $val;
            }
            $suppliers[] = [
                'supplier_id' => $sid,
                'supplier_name' => $list[0]['supplier_name'],
                'status' => (int)$list[0]['supplier_status'],
                'count' => count($list),
                'latest' => $prices[0]['price'],
                'latest_date' => $prices[0]['effective_date'],
                'delta' => $prices[0]['delta'],
                'min' => min($all),
                'max' => max($all),
                'prices' => $prices
            ];
        }

        echo json_encode([
            'success' => true,
            'item' => $item,
            'active_suppliers' => $this->supplierModel->getAll(true),
            'suppliers' => $suppliers
        ]);
        exit;
    }

    public function itemPriceAdd() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request.']);
            exit;
        }
        $itemId = (int)($_POST['item_id'] ?? 0);
        $item = $itemId > 0 ? $this->itemImportModel->getItemById($itemId) : null;
        if (!$item) {
            echo json_encode(['success' => false, 'error' => 'Item not found.']);
            exit;
        }

        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $priceRaw = trim($_POST['price'] ?? '');
        // Date is optional in the modal (field removed) — stamp today server-side.
        $date = trim($_POST['effective_date'] ?? '') ?: date('Y-m-d');
        $remarks = trim($_POST['remarks'] ?? '') ?: null;

        if ($supplierId <= 0 || !$this->supplierModel->getById($supplierId)) {
            echo json_encode(['success' => false, 'error' => 'Please select a valid supplier.']);
            exit;
        }
        if ($priceRaw === '' || !is_numeric($priceRaw) || (float)$priceRaw < 0) {
            echo json_encode(['success' => false, 'error' => 'Price must be a number greater than or equal to 0.']);
            exit;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            echo json_encode(['success' => false, 'error' => 'Invalid effective date.']);
            exit;
        }

        $price = round((float)$priceRaw, 2);
        $priceId = $this->itemImportModel->addPrice([
            'item_id' => $itemId,
            'supplier_id' => $supplierId,
            'price' => $price,
            'effective_date' => $date,
            'remarks' => $remarks,
            'created_by' => $_SESSION['user_id']
        ]);
        $this->itemImportModel->syncItemAmount($itemId);

        AuditModel::log($_SESSION['user_id'], 'CREATE', 'rnd',
            "Price added for {$item['item_code']}: ₱" . number_format($price, 2) . " @ {$date}",
            null,
            ['supplier_id' => $supplierId, 'price' => $price, 'effective_date' => $date, 'remarks' => $remarks],
            'item_price', $priceId);

        echo json_encode(['success' => true, 'message' => 'Price entry added.']);
        exit;
    }

    public function itemPriceDelete() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request.']);
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);

        $affectedItemId = $id > 0 ? $this->itemImportModel->deletePrice($id) : null;
        if ($affectedItemId === null) {
            echo json_encode(['success' => false, 'error' => 'Price entry not found.']);
            exit;
        }
        $this->itemImportModel->syncItemAmount($affectedItemId);

        AuditModel::log($_SESSION['user_id'], 'DELETE', 'rnd',
            'Deleted price entry #' . $id . ' (item #' . $affectedItemId . ')',
            ['id' => $id, 'item_id' => $affectedItemId], null,
            'item_price', $id);

        echo json_encode(['success' => true, 'message' => 'Price entry deleted.']);
        exit;
    }

    /** Delete Supplier: drop every price row this supplier has for the item. */
    public function itemSupplierRemove() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request.']);
            exit;
        }
        $itemId = (int)($_POST['item_id'] ?? 0);
        $supplierId = (int)($_POST['supplier_id'] ?? 0);

        $item = $itemId > 0 ? $this->itemImportModel->getItemById($itemId) : null;
        $supplier = $supplierId > 0 ? $this->supplierModel->getById($supplierId) : null;
        if (!$item || !$supplier) {
            echo json_encode(['success' => false, 'error' => 'Item or supplier not found.']);
            exit;
        }

        $affected = $this->itemImportModel->deletePricesForSupplier($itemId, $supplierId);
        if ($affected === null) {
            echo json_encode(['success' => false, 'error' => 'No prices found for that supplier.']);
            exit;
        }
        $this->itemImportModel->syncItemAmount($itemId);

        AuditModel::log($_SESSION['user_id'], 'DELETE', 'rnd',
            "Supplier removed from {$item['item_code']}: " . $supplier['supplier_name'] . ' (all price entries)',
            ['item_id' => $itemId, 'supplier_id' => $supplierId], null,
            'item_price', $itemId);

        echo json_encode(['success' => true, 'message' => 'Supplier removed from this item.']);
        exit;
    }

    public function itemImportForm() {
        $_SESSION['error'] = 'Item management has moved to the Warehouse module.';
        header('Location: ?controller=warehouse&action=items');
        exit;
    }

    public function itemImportPreview() { $this->itemImportForm(); }
    public function itemImportConfirm() { $this->itemImportForm(); }

    // ─── Suppliers (Supplier Management) ─────────────────────────────────────

    /** Redirect target that keeps the supplier list's search/page. The add/edit
     *  forms carry these as a query string on their action URL. */
    private function suppliersBackUrl(string $base): string {
        $qs = [];
        foreach (['search', 'page'] as $key) {
            $val = trim($_GET[$key] ?? '');
            if ($val !== '' && $val !== '0') {
                $qs[$key] = $val;
            }
        }
        return $qs ? $base . '&' . http_build_query($qs) : $base;
    }

    public function suppliers() {
        $search = $_GET['search'] ?? '';
        $filters = [];
        if ($search) $filters['search'] = $search;

        $hasFilters = ($search !== '');
        if ($hasFilters) {
            $data['suppliers'] = $this->supplierModel->getAllFiltered($filters);
            $data['page'] = 1;
            $data['totalPages'] = 1;
            $data['total'] = count($data['suppliers']);
        } else {
            $all = $this->supplierModel->getAll(false);
            $pagination = Pagination::paginate($all, 15);
            $data['suppliers'] = $pagination['items'];
            $data['page'] = $pagination['page'];
            $data['totalPages'] = $pagination['totalPages'];
            $data['total'] = $pagination['total'];
        }
        $data['search'] = $search;
        $data['supController'] = 'rnd';
        $data['page_title'] = 'Supplier Management';
        $this->renderAdmin('suppliers/index', $data);
    }

    public function supplierCreate() {
        $back = $this->suppliersBackUrl('?controller=rnd&action=suppliers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $back);
            exit;
        }

        $code = trim($_POST['supplier_code'] ?? '');
        $name = trim($_POST['supplier_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($code === '' || $name === '') {
            $_SESSION['error'] = 'Supplier code and name are required.';
            header('Location: ' . $back);
            exit;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Invalid email address.';
            header('Location: ' . $back);
            exit;
        }
        if ($this->supplierModel->getByCode($code)) {
            $_SESSION['error'] = 'Supplier code already exists.';
            header('Location: ' . $back);
            exit;
        }

        try {
            $id = $this->supplierModel->create([
                'supplier_code' => $code,
                'supplier_name' => $name,
                'contact_person' => trim($_POST['contact_person'] ?? '') ?: null,
                'phone' => trim($_POST['phone'] ?? '') ?: null,
                'email' => $email ?: null,
                'address' => trim($_POST['address'] ?? '') ?: null
            ]);
            AuditModel::log($_SESSION['user_id'], 'CREATE', 'rnd', 'Created supplier: ' . $name, null, [
                'supplier_code' => $code,
                'supplier_name' => $name
            ], 'supplier', (int)$id);
            $_SESSION['success'] = 'Supplier created successfully';
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Supplier code already exists.';
        }

        header('Location: ' . $back);
        exit;
    }

    public function supplierUpdate() {
        $back = $this->suppliersBackUrl('?controller=rnd&action=suppliers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $back);
            exit;
        }

        $id = (int)($_POST['supplier_id'] ?? 0);
        $existing = $id > 0 ? $this->supplierModel->getById($id) : null;
        if (!$existing) {
            $_SESSION['error'] = 'Supplier not found.';
            header('Location: ' . $back);
            exit;
        }

        $code = trim($_POST['supplier_code'] ?? '');
        $name = trim($_POST['supplier_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($code === '' || $name === '') {
            $_SESSION['error'] = 'Supplier code and name are required.';
            header('Location: ' . $back);
            exit;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Invalid email address.';
            header('Location: ' . $back);
            exit;
        }
        $byCode = $this->supplierModel->getByCode($code);
        if ($byCode && (int)$byCode['supplier_id'] !== $id) {
            $_SESSION['error'] = 'Supplier code already exists.';
            header('Location: ' . $back);
            exit;
        }

        try {
            $this->supplierModel->update($id, [
                'supplier_code' => $code,
                'supplier_name' => $name,
                'contact_person' => trim($_POST['contact_person'] ?? '') ?: null,
                'phone' => trim($_POST['phone'] ?? '') ?: null,
                'email' => $email ?: null,
                'address' => trim($_POST['address'] ?? '') ?: null
            ]);
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'rnd', 'Updated supplier: ' . $existing['supplier_name'], $existing, [
                'supplier_code' => $code,
                'supplier_name' => $name
            ], 'supplier', $id);
            $_SESSION['success'] = 'Supplier updated successfully';
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Supplier code already exists.';
        }

        header('Location: ' . $back);
        exit;
    }

    public function supplierToggleStatus() {
        $back = $this->suppliersBackUrl('?controller=rnd&action=suppliers');
        $id = (int)($_GET['id'] ?? 0);
        $supplier = $id > 0 ? $this->supplierModel->getById($id) : null;
        if (!$supplier) {
            $_SESSION['error'] = 'Supplier not found.';
            header('Location: ' . $back);
            exit;
        }

        try {
            $this->supplierModel->toggleStatus($id);
            $newStatus = $supplier['status'] ? 'Inactive' : 'Active';
            AuditModel::log($_SESSION['user_id'], 'UPDATE', 'rnd', 'Toggled supplier status to ' . $newStatus . ': ' . $supplier['supplier_name'], $supplier, null, 'supplier', $id);
            $_SESSION['success'] = 'Supplier status changed to ' . $newStatus;
        } catch (\Exception $e) {
            error_log('rnd supplierToggleStatus error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to update supplier status.';
        }

        header('Location: ' . $back);
        exit;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function getDbErrorMessage(\PDOException $e, $indexName, $fieldLabel) {
        $errorCode = $e->errorInfo[1] ?? null;
        $errorMessage = $e->getMessage();
        if ($errorCode === 1062) {
            return "$fieldLabel already exists. Please try a new value.";
        }
        return "Database error: $errorMessage";
    }

    private function render($view, $data = []) {
        extract($data);
        ob_start();
        include __DIR__ . "/../views/{$view}.php";
        $content = ob_get_clean();
        include __DIR__ . "/../views/layouts/main.php";
    }

    // Renders a view owned by another module (e.g. the shared Item Master
    // List) inside the R&D layout.
    private function renderWarehouse($view, $data = []) {
        extract($data);
        ob_start();
        include BASE_PATH . "warehouse/views/{$view}.php";
        $content = ob_get_clean();
        include __DIR__ . "/../views/layouts/main.php";
    }

    // Same, for views owned by the admin module (e.g. Supplier Management).
    private function renderAdmin($view, $data = []) {
        extract($data);
        ob_start();
        include BASE_PATH . "admin/views/{$view}.php";
        $content = ob_get_clean();
        include __DIR__ . "/../views/layouts/main.php";
    }
}
