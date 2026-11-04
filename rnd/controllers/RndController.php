<?php
namespace App\Controllers;

use App\Models\ItemModel;
use App\Models\WarehouseModel;
use App\Models\BomModel;
use App\Helpers\Pagination;
use App\Models\AuditModel;
use App\Helpers\SpreadsheetReader;

class RndController {
    private $itemModel;
    private $warehouseModel;
    private $bomModel;

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
    }

    public function index() {
        $this->boms();
    }

    // ─── Raw Materials Management ─────────────────────────────────────────────

    // ─── Raw Materials (Deprecated — use Warehouse Items module) ─────────────

    public function rawMaterials() {
        $_SESSION['error'] = 'Items (RM PM FG) are now managed under Warehouse.';
        header('Location: ?controller=warehouse&action=items');
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
        header('Location: ?controller=warehouse&action=items');
        exit;
    }

    public function itemImportForm() {
        $_SESSION['error'] = 'Item management has moved to the Warehouse module.';
        header('Location: ?controller=warehouse&action=items');
        exit;
    }

    public function itemImportPreview() { $this->itemImportForm(); }
    public function itemImportConfirm() { $this->itemImportForm(); }

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
}
