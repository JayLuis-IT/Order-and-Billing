<h4><i class="bi bi-graph-up me-2"></i>Warehouse Reports</h4>

<!-- Customer Filter & Week Navigation -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="hidden" name="controller" value="warehouse">
            <input type="hidden" name="action" value="reports">
            <input type="hidden" name="week_offset" value="<?= $weekOffset ?? 0 ?>">
            <select name="customer_id" class="form-select form-select-sm filter-select" style="width:220px" onchange="this.form.submit()">
                <option value="">All Customers</option>
                <?php foreach (($customers ?? []) as $c): ?>
                    <option value="<?= $c['customer_id'] ?>" <?= ($selectedCustomer ?? '') == $c['customer_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="?controller=warehouse&action=reports<?= ($selectedCustomer ?? '') ? '&customer_id=' . urlencode($selectedCustomer) : '' ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Clear</a>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <?php
        $offset = $weekOffset ?? 0;
        $weekFrom = ($offset * 12) + 1;
        $weekTo = $weekFrom + 11;
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $firstYw = intval($startYearWeek ?? 0);
        if ($firstYw > 0) {
            $lastYw = $firstYw;
            $yw = $firstYw;
            for ($i = 0; $i < 11; $i++) {
                $y = floor($yw / 100);
                $w = $yw % 100 + 1;
                if ($w > 52) { $y++; $w = 1; }
                $yw = $y * 100 + $w;
                $lastYw = $yw;
            }
            $firstDate = new DateTime();
            $firstDate->setISODate(floor($firstYw / 100), $firstYw % 100, 1);
            $lastDate = new DateTime();
            $lastDate->setISODate(floor($lastYw / 100), $lastYw % 100, 7);
            $dateRange = $monthNames[intval($firstDate->format('m')) - 1] . ' ' . $firstDate->format('d') . ' - ' . $monthNames[intval($lastDate->format('m')) - 1] . ' ' . $lastDate->format('d') . ', ' . $lastDate->format('Y');
        } else {
            $dateRange = '';
        }
        ?>
        <?php
        $extraParams = '';
        if (!empty($_GET['po_filter'])) $extraParams .= '&po_filter=' . urlencode($_GET['po_filter']);
        if (!empty($_GET['item_filter'])) $extraParams .= '&item_filter=' . urlencode($_GET['item_filter']);
        if (!empty($_GET['status_filter'])) $extraParams .= '&status_filter=' . urlencode($_GET['status_filter']);
        if (!empty($_GET['lot_item_id'])) $extraParams .= '&lot_item_id=' . urlencode($_GET['lot_item_id']);
        ?>
        <?php if (!empty($hasMoreWeeks)): ?>
        <a href="?controller=warehouse&action=reports&week_offset=<?= $offset + 1 ?><?= ($selectedCustomer ?? '') ? '&customer_id=' . urlencode($selectedCustomer) : '' ?><?= $extraParams ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-chevron-left me-1"></i>Older Weeks
        </a>
        <?php endif; ?>
        <span class="text-muted small">Weeks <?= $weekFrom ?>–<?= $weekTo ?> (<?= $dateRange ?>)</span>
        <?php if ($offset > 0): ?>
            <a href="?controller=warehouse&action=reports&week_offset=<?= $offset - 1 ?><?= ($selectedCustomer ?? '') ? '&customer_id=' . urlencode($selectedCustomer) : '' ?><?= $extraParams ?>" class="btn btn-sm btn-outline-primary">
                Newer Weeks<i class="bi bi-chevron-right ms-1"></i>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Weekly Delivery Graph -->
<div class="card data-card" style="cursor: pointer;">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Weekly Deliveries (Weeks <?= $weekFrom ?>–<?= $weekTo ?> &mdash; <?= $dateRange ?>) <small class="text-muted">— Click a bar to see details</small></h6>
        <a href="?<?= http_build_query(array_filter(['controller' => 'warehouse', 'action' => 'exportReports', 'customer_id' => $selectedCustomer ?? '', 'week_offset' => $weekOffset ?? 0, 'sheet' => 'weekly'])) ?>" class="btn btn-sm btn-success" onclick="event.stopPropagation();"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
    </div>
    <div class="card-body" style="height: 400px;">
        <canvas id="weeklyDeliveryChart"></canvas>
    </div>
</div>

<!-- Delivery Detail Modal -->
<div class="modal fade" id="deliveryDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalTitle"><i class="bi bi-truck me-2"></i>Delivery Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="deliveryDetailTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>DR #</th>
                                <th>Customer</th>
                                <th>PO Number</th>
                                <th>Cases</th>
                                <th>Total Qty</th>
                                <th>Delivered By</th>
                            </tr>
                        </thead>
                        <tbody id="deliveryDetailBody">
                        </tbody>
                    </table>
                </div>
                <div id="noDeliveriesMsg" class="text-center text-muted py-4 d-none">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">No deliveries for this week.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- PO Item Summary Section -->
<?php
$poItems = $poItemSummary ?? [];
$totalItems = count($poItems);
$completedCount = 0;
$inProgressCount = 0;
$pendingCount = 0;
$poNumbers = [];
$itemNames = [];
foreach ($poItems as $item) {
    $delivered = intval($item['delivered_quantity']);
    $ordered = intval($item['po_qty']);
    if ($delivered >= $ordered) {
        $completedCount++;
    } elseif ($delivered > 0) {
        $inProgressCount++;
    } else {
        $pendingCount++;
    }
    $poNumbers[$item['customer_po_number']] = $item['customer_po_number'];
    $itemNames[$item['item_description']] = $item['item_description'];
}
asort($poNumbers);
asort($itemNames);
$filterPo = $_GET['po_filter'] ?? '';
$filterItem = $_GET['item_filter'] ?? '';
$filterStatus = $_GET['status_filter'] ?? '';
$filterSearch = $_GET['search'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$poPage = max(1, intval($_GET['po_page'] ?? 1));
$poPageSize = 10;

$filteredItems = $poItems;
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
        $d = intval($i['delivered_quantity']);
        $q = intval($i['po_qty']);
        if ($filterStatus === 'completed') return $p >= $q && $d >= $q;
        if ($filterStatus === 'in-progress') return $d > 0 && $d < $q;
        if ($filterStatus === 'pending') return $d === 0;
        return true;
    });
}
if ($filterDateFrom !== '') {
    $filteredItems = array_filter($filteredItems, fn($i) => !empty($i['po_date']) && date('Y-m-d', strtotime($i['po_date'])) >= $filterDateFrom);
}
if ($filterDateTo !== '') {
    $filteredItems = array_filter($filteredItems, fn($i) => !empty($i['po_date']) && date('Y-m-d', strtotime($i['po_date'])) <= $filterDateTo);
}
$filteredItems = array_values($filteredItems);
$filteredTotal = count($filteredItems);
$filteredTotalPages = max(1, ceil($filteredTotal / $poPageSize));
if ($poPage > $filteredTotalPages) $poPage = $filteredTotalPages;
$poOffset = ($poPage - 1) * $poPageSize;
$pageItems = array_slice($filteredItems, $poOffset, $poPageSize);

$baseUrl = '?controller=warehouse&action=reports';
if ($weekOffset ?? 0) $baseUrl .= '&week_offset=' . intval($weekOffset);
if ($filterSearch !== '') $baseUrl .= '&search=' . urlencode($filterSearch);
if ($filterPo !== '') $baseUrl .= '&po_filter=' . urlencode($filterPo);
if ($filterItem !== '') $baseUrl .= '&item_filter=' . urlencode($filterItem);
if ($filterStatus !== '') $baseUrl .= '&status_filter=' . urlencode($filterStatus);
if ($filterDateFrom !== '') $baseUrl .= '&date_from=' . urlencode($filterDateFrom);
if ($filterDateTo !== '') $baseUrl .= '&date_to=' . urlencode($filterDateTo);
if (!empty($_GET['lot_item_id'])) $baseUrl .= '&lot_item_id=' . urlencode($_GET['lot_item_id']);
?>

<div class="card data-card mt-4" id="po-item-summary">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>PO Item Summary</h6>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <?php
            $exportParams = http_build_query(array_filter([
                'controller' => 'warehouse',
                'action' => 'exportReports',
                'customer_id' => $selectedCustomer ?? '',
                'week_offset' => $weekOffset ?? 0,
                'search' => $filterSearch,
                'po_filter' => $filterPo,
                'item_filter' => $filterItem,
                'status_filter' => $filterStatus,
                'date_from' => $filterDateFrom,
                'date_to' => $filterDateTo,
                'lot_item_id' => $_GET['lot_item_id'] ?? '',
            ]));
            ?>
            <button type="button" class="btn btn-sm btn-success" id="exportPreviewBtn"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</button>
        <form method="GET" class="d-flex gap-2 flex-wrap" id="poFilterForm">
            <input type="hidden" name="controller" value="warehouse">
            <input type="hidden" name="action" value="reports">
            <?php if ($weekOffset ?? 0): ?>
                <input type="hidden" name="week_offset" value="<?= intval($weekOffset) ?>">
            <?php endif; ?>
            <?php if (!empty($_GET['lot_item_id'])): ?><input type="hidden" name="lot_item_id" value="<?= htmlspecialchars($_GET['lot_item_id']) ?>"><?php endif; ?>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search customer, PO, item..." value="<?= htmlspecialchars($filterSearch) ?>" style="width: 220px;">
            <select name="po_filter" class="form-select form-select-sm filter-select" id="poFilterSelect" style="width:200px;">
                <option value="">All PO Numbers</option>
                <?php foreach ($poNumbers as $po): ?>
                    <option value="<?= htmlspecialchars(strtolower($po)) ?>" <?= $filterPo === strtolower($po) ? 'selected' : '' ?>><?= htmlspecialchars($po) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="item_filter" class="form-select form-select-sm filter-select" id="itemFilterSelect" style="width:200px;">
                <option value="">All Items</option>
                <?php foreach ($itemNames as $itemName): ?>
                    <option value="<?= htmlspecialchars(strtolower($itemName)) ?>" <?= $filterItem === strtolower($itemName) ? 'selected' : '' ?>><?= htmlspecialchars($itemName) ?></option>
                <?php endforeach; ?>
            </select>
            <div style="position:relative; width:200px;">
                <input type="text" id="poDateRange" class="form-control form-control-sm" placeholder="Select Date Range" readonly style="width:100%; background-color:#fff; cursor:pointer;" title="Click to select PO date range" value="<?= htmlspecialchars(($filterDateFrom !== '' ? date('m/d/Y', strtotime($filterDateFrom)) : '') . ($filterDateTo !== '' ? ' - ' . date('m/d/Y', strtotime($filterDateTo)) : '')) ?>">
                <input type="date" name="date_from" id="poDateFrom" value="<?= htmlspecialchars($filterDateFrom) ?>" style="position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; pointer-events:none; border:0; z-index:-1;">
                <input type="date" name="date_to" id="poDateTo" value="<?= htmlspecialchars($filterDateTo) ?>" style="position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; pointer-events:none; border:0; z-index:-1;">
                <div id="poCalendarPopup" style="display:none; position:absolute; top:calc(100% + 4px); left:0; z-index:1060; background:#fff; border:1px solid #dee2e6; border-radius:8px; box-shadow:0 6px 20px rgba(0,0,0,.18); padding:10px; width:270px;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <button type="button" id="calPrev" class="btn btn-sm btn-light border-0"><i class="bi bi-chevron-left"></i></button>
                        <strong id="calMonthLabel" style="font-size:.9rem;"></strong>
                        <button type="button" id="calNext" class="btn btn-sm btn-light border-0"><i class="bi bi-chevron-right"></i></button>
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(7,1fr); text-align:center; font-size:.7rem; font-weight:600; color:#6c757d; margin-bottom:2px;">
                        <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                    </div>
                    <div id="calDays" style="display:grid; grid-template-columns:repeat(7,1fr); text-align:center; gap:2px;"></div>
                    <div id="calHint" class="text-center mt-2" style="font-size:.78rem; color:#8a6d00; background:#fff8dc; border-radius:6px; padding:4px 6px;">Select the starting date</div>
                </div>
            </div>
            <select name="status_filter" class="form-select form-select-sm filter-select" style="width: 160px;" onchange="document.getElementById('poFilterForm').submit()">
                <option value="">All Status</option>
                <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="in-progress" <?= $filterStatus === 'in-progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
            </select>
        </form>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card text-center border-primary">
                    <div class="card-body py-2">
                        <div class="text-primary fs-4 fw-bold"><?= $totalItems ?></div>
                        <small class="text-muted">Total Items</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-success">
                    <div class="card-body py-2">
                        <div class="text-success fs-4 fw-bold"><?= $completedCount ?></div>
                        <small class="text-muted">Completed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-warning">
                    <div class="card-body py-2">
                        <div class="text-warning fs-4 fw-bold"><?= $inProgressCount ?></div>
                        <small class="text-muted">In Progress</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-secondary">
                    <div class="card-body py-2">
                        <div class="text-secondary fs-4 fw-bold"><?= $pendingCount ?></div>
                        <small class="text-muted">Pending</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover" id="poItemSummaryTable">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>PO Number</th>
                        <th>PO Date</th>
                        <th>Item</th>
                        <th class="text-end">PO Qty</th>
                        <th class="text-end">Delivered</th>
                        <th class="text-end">Balance</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pageItems)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4"><i class="bi bi-inbox fs-4 d-block mb-2"></i>No PO items found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pageItems as $item):
                            $ordered = intval($item['po_qty']);
                            $delivered = intval($item['delivered_quantity']);
                            $balance = $ordered - $delivered;
                            $uomConv = intval($item['uom_conversion'] ?? 0);
                            if ($delivered >= $ordered) {
                                $status = 'Completed';
                                $badgeClass = 'bg-success';
                            } elseif ($delivered > 0) {
                                $status = 'In Progress';
                                $badgeClass = 'bg-warning text-dark';
                            } else {
                                $status = 'Pending';
                                $badgeClass = 'bg-secondary';
                            }
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($item['customer_name']) ?></td>
                                <td><?= htmlspecialchars($item['customer_po_number']) ?></td>
                                <td><?= !empty($item['po_date']) ? date('Y-m-d', strtotime($item['po_date'])) : '' ?></td>
                                <td><?= htmlspecialchars($item['item_description']) ?></td>
                                <td class="text-end"><?= number_format($ordered) ?><?php if ($uomConv > 0): ?> <small class="text-muted">/ <?= number_format(intval($ordered / $uomConv)) ?> cs</small><?php endif; ?></td>
                                <td class="text-end"><?= number_format($delivered) ?><?php if ($uomConv > 0): ?> <small class="text-muted">/ <?= number_format(intval($delivered / $uomConv)) ?> cs</small><?php endif; ?></td>
                                <td class="text-end"><?= number_format($balance) ?><?php if ($uomConv > 0): ?> <small class="text-muted">/ <?= number_format(intval($balance / $uomConv)) ?> cs</small><?php endif; ?></td>
                                <td class="text-center"><span class="badge <?= $badgeClass ?>"><?= $status ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($filteredTotalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="text-muted small">Showing <?= $poOffset + 1 ?>–<?= min($poOffset + $poPageSize, $filteredTotal) ?> of <?= $filteredTotal ?> items</span>
            <div class="d-flex gap-1">
                <?php
                $pageBaseUrl = $baseUrl . '&po_page=';
                ?>
                <a href="<?= $pageBaseUrl ?>1#po-item-summary" class="btn btn-sm btn-outline-secondary <?= $poPage === 1 ? 'disabled' : '' ?>"><i class="bi bi-chevron-double-left"></i></a>
                <a href="<?= $pageBaseUrl . ($poPage - 1) ?>#po-item-summary" class="btn btn-sm btn-outline-secondary <?= $poPage === 1 ? 'disabled' : '' ?>"><i class="bi bi-chevron-left"></i></a>
                <?php
                $range = [];
                for ($i = max(1, $poPage - 2); $i <= min($filteredTotalPages, $poPage + 2); $i++) $range[] = $i;
                if ($range[0] > 1) array_unshift($range, '...');
                if (end($range) < $filteredTotalPages) $range[] = '...';
                foreach ($range as $p):
                    if ($p === '...'):
                ?>
                    <span class="btn btn-sm btn-outline-secondary disabled">...</span>
                <?php else: ?>
                    <a href="<?= $pageBaseUrl . $p ?>#po-item-summary" class="btn btn-sm <?= $p === $poPage ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $p ?></a>
                <?php endif; endforeach; ?>
                <a href="<?= $pageBaseUrl . ($poPage + 1) ?>#po-item-summary" class="btn btn-sm btn-outline-secondary <?= $poPage === $filteredTotalPages ? 'disabled' : '' ?>"><i class="bi bi-chevron-right"></i></a>
                <a href="<?= $pageBaseUrl . $filteredTotalPages ?>#po-item-summary" class="btn btn-sm btn-outline-secondary <?= $poPage === $filteredTotalPages ? 'disabled' : '' ?>"><i class="bi bi-chevron-double-right"></i></a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$lotBaseUrl = '?controller=warehouse&action=reports';
if ($weekOffset ?? 0) $lotBaseUrl .= '&week_offset=' . intval($weekOffset);
if ($filterPo !== '') $lotBaseUrl .= '&po_filter=' . urlencode($filterPo);
if ($filterItem !== '') $lotBaseUrl .= '&item_filter=' . urlencode($filterItem);
if ($filterStatus !== '') $lotBaseUrl .= '&status_filter=' . urlencode($filterStatus);
$lotPage = max(1, intval($_GET['lot_page'] ?? 1));
$lotPageSize = 15;
$lotTotal = count($lotData);
$lotTotalPages = max(1, ceil($lotTotal / $lotPageSize));
if ($lotPage > $lotTotalPages) $lotPage = $lotTotalPages;
$lotOffset = ($lotPage - 1) * $lotPageSize;
$pageLotData = array_slice($lotData, $lotOffset, $lotPageSize);
?>

<div class="card data-card mt-4" id="stock-on-hand">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Stock on Hand</h6>
        <form method="GET" class="d-flex gap-2 align-items-center" id="lotFilterForm">
            <input type="hidden" name="controller" value="warehouse">
            <input type="hidden" name="action" value="reports">
            <?php if ($weekOffset ?? 0): ?>
                <input type="hidden" name="week_offset" value="<?= intval($weekOffset) ?>">
            <?php endif; ?>
            <?php if (!empty($_GET['po_filter'])): ?><input type="hidden" name="po_filter" value="<?= htmlspecialchars($_GET['po_filter']) ?>"><?php endif; ?>
            <?php if (!empty($_GET['item_filter'])): ?><input type="hidden" name="item_filter" value="<?= htmlspecialchars($_GET['item_filter']) ?>"><?php endif; ?>
            <?php if (!empty($_GET['status_filter'])): ?><input type="hidden" name="status_filter" value="<?= htmlspecialchars($_GET['status_filter']) ?>"><?php endif; ?>
            <label class="text-muted small mb-0">Select Item:</label>
            <select name="lot_item_id" class="form-select form-select-sm filter-select" id="lotItemSelect" style="width:350px;">
                <option value="">-- Choose an item --</option>
                <?php foreach ($lotItems as $li): ?>
                    <option value="<?= $li['item_id'] ?>" <?= ($selectedLotItem ?? '') == $li['item_id'] ? 'selected' : '' ?>><?= htmlspecialchars($li['item_description']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="?<?= http_build_query(array_filter(['controller' => 'warehouse', 'action' => 'exportReports', 'customer_id' => $selectedCustomer ?? '', 'sheet' => 'stock', 'lot_item_id' => $selectedLotItem ?? ''])) ?>" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
    </div>
    <div class="card-body">
        <?php if (empty($lotData)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                <p>No lots found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Lot Number</th>
                            <th class="text-end">Stock on Hand</th>
                            <th class="text-end">Delivered</th>
                            <th>Expiration Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageLotData as $lot):
                            $stockOnHandPcs = max(0, $lot['quantity_produced'] - $lot['quantity_delivered'] + ($lot['quantity_backloaded'] ?? 0));
                            $conv = intval($lot['uom_conversion'] ?? 0);
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($lot['item_description'] ?? $lot['item_code'] ?? '') ?></strong></td>
                                <td><strong><?= htmlspecialchars($lot['lot_number']) ?></strong></td>
                                <td class="text-end"><?php if ($conv > 0): ?><?= number_format(intval($stockOnHandPcs / $conv)) ?> cs<?php else: ?><?= number_format($stockOnHandPcs) ?> pcs<?php endif; ?></td>
                                <td class="text-end"><?php if ($conv > 0): ?><?= number_format(intval($lot['quantity_delivered'] / $conv)) ?> cs<?php else: ?><?= number_format($lot['quantity_delivered']) ?> pcs<?php endif; ?></td>
                                <td><?= $lot['lot_date'] ? date('M Y', strtotime($lot['lot_date'] . ' +3 years')) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($lotTotalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <span class="text-muted small">Showing <?= $lotOffset + 1 ?>–<?= min($lotOffset + $lotPageSize, $lotTotal) ?> of <?= $lotTotal ?> lots</span>
                <div class="d-flex gap-1">
                    <?php $lotPageBase = $lotBaseUrl . ($selectedLotItem ? '&lot_item_id=' . urlencode($selectedLotItem) : '') . '&lot_page='; ?>
                    <a href="<?= $lotPageBase ?>1#stock-on-hand" class="btn btn-sm btn-outline-secondary <?= $lotPage === 1 ? 'disabled' : '' ?>"><i class="bi bi-chevron-double-left"></i></a>
                    <a href="<?= $lotPageBase . ($lotPage - 1) ?>#stock-on-hand" class="btn btn-sm btn-outline-secondary <?= $lotPage === 1 ? 'disabled' : '' ?>"><i class="bi bi-chevron-left"></i></a>
                    <?php
                    $lotRange = [];
                    for ($i = max(1, $lotPage - 2); $i <= min($lotTotalPages, $lotPage + 2); $i++) $lotRange[] = $i;
                    if ($lotRange[0] > 1) array_unshift($lotRange, '...');
                    if (end($lotRange) < $lotTotalPages) $lotRange[] = '...';
                    foreach ($lotRange as $lp):
                        if ($lp === '...'):
                    ?>
                        <span class="btn btn-sm btn-outline-secondary disabled">...</span>
                    <?php else: ?>
                        <a href="<?= $lotPageBase . $lp ?>#stock-on-hand" class="btn btn-sm <?= $lp === $lotPage ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $lp ?></a>
                    <?php endif; endforeach; ?>
                    <a href="<?= $lotPageBase . ($lotPage + 1) ?>#stock-on-hand" class="btn btn-sm btn-outline-secondary <?= $lotPage === $lotTotalPages ? 'disabled' : '' ?>"><i class="bi bi-chevron-right"></i></a>
                    <a href="<?= $lotPageBase . $lotTotalPages ?>#stock-on-hand" class="btn btn-sm btn-outline-secondary <?= $lotPage === $lotTotalPages ? 'disabled' : '' ?>"><i class="bi bi-chevron-double-right"></i></a>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script src="public/js/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var poSel = document.getElementById('poFilterSelect');
    var itemSel = document.getElementById('itemFilterSelect');
    var lotSel = document.getElementById('lotItemSelect');
    if (poSel) poSel.addEventListener('change', function() {
        document.getElementById('poFilterForm').submit();
    });
    if (itemSel) itemSel.addEventListener('change', function() {
        document.getElementById('poFilterForm').submit();
    });
    if (lotSel) lotSel.addEventListener('change', function() {
        var f = document.getElementById('lotFilterForm');
        var p = new URLSearchParams(new FormData(f));
        p.set('controller', 'warehouse');
        p.set('action', 'reports');
        p.set('lot_item_id', lotSel.value);
        window.location = '?' + p.toString() + '#stock-on-hand';
    });

    var _poSearchTimer;
    var searchInput = document.querySelector('#poFilterForm input[name="search"]');
    if (searchInput) {
        if (sessionStorage.getItem('poItemSummaryScroll')) {
            window.scrollTo(0, parseInt(sessionStorage.getItem('poItemSummaryScroll')));
            sessionStorage.removeItem('poItemSummaryScroll');
            if (searchInput.value) { searchInput.focus(); var _len = searchInput.value.length; searchInput.setSelectionRange(_len, _len); }
        }
        searchInput.addEventListener('input', function() {
            clearTimeout(_poSearchTimer);
            _poSearchTimer = setTimeout(function() {
                sessionStorage.setItem('poItemSummaryScroll', window.scrollY);
                document.getElementById('poFilterForm').submit();
            }, 600);
        });
    }

    // Custom two-step date range calendar with yellow highlights
    var rangeInput = document.getElementById('poDateRange');
    var dateFrom = document.getElementById('poDateFrom');
    var dateTo = document.getElementById('poDateTo');
    var calPopup = document.getElementById('poCalendarPopup');
    var calDays = document.getElementById('calDays');
    var calLabel = document.getElementById('calMonthLabel');
    var calHint = document.getElementById('calHint');
    var picking = 'from';
    var pickStart = null;
    var pickEnd = null;
    var viewYear, viewMonth;
    var monthNamesCal = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    var defaultPlaceholder = rangeInput.getAttribute('placeholder');

    function pad2(n) { return (n < 10 ? '0' : '') + n; }
    function ymd(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }

    function updateRangeDisplay() {
        var from = dateFrom.value ? new Date(dateFrom.value + 'T00:00:00').toLocaleDateString('en-US') : '';
        var to = dateTo.value ? new Date(dateTo.value + 'T00:00:00').toLocaleDateString('en-US') : '';
        rangeInput.value = (from || to) ? (from + (to ? ' - ' + to : '')) : '';
        rangeInput.placeholder = defaultPlaceholder;
    }

    function renderCalendar() {
        calLabel.textContent = monthNamesCal[viewMonth] + ' ' + viewYear;
        calHint.textContent = picking === 'from' ? 'Select the starting date' : 'Now select the ending date';
        var firstDow = new Date(viewYear, viewMonth, 1).getDay();
        var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
        var todayYmd = ymd(new Date());
        var html = '';
        for (var i = 0; i < firstDow; i++) html += '<span></span>';
        for (var d = 1; d <= daysInMonth; d++) {
            var cur = viewYear + '-' + pad2(viewMonth + 1) + '-' + pad2(d);
            var style = 'cursor:pointer; border-radius:6px; padding:4px 0; font-size:.8rem;';
            var isStart = pickStart === cur;
            var isEnd = pickStart !== null && pickEnd === cur;
            if (pickStart && cur < pickStart) {
                style += ' color:#ced4da; cursor:not-allowed;';
            } else {
                style += ' color:#212529;';
            }
            if (isStart || isEnd) {
                style += ' background:#f6c344; font-weight:700;';
            } else if (pickStart && pickEnd && cur > pickStart && cur < pickEnd) {
                style += ' background:#fff3b0;';
            } else if (cur === todayYmd) {
                style += ' border:1px solid #f6c344;';
            }
            html += '<span class="cal-day" data-date="' + cur + '" style="' + style + '">' + d + '</span>';
        }
        calDays.innerHTML = html;
        calDays.querySelectorAll('.cal-day').forEach(function(el) {
            el.addEventListener('click', function() { selectDate(this.getAttribute('data-date')); });
        });
    }

    function selectDate(dateStr) {
        if (picking === 'from') {
            pickStart = dateStr;
            pickEnd = null;
            dateFrom.value = dateStr;
            dateTo.value = '';
            picking = 'to';
            var d = new Date(dateStr + 'T00:00:00');
            viewYear = d.getFullYear();
            viewMonth = d.getMonth();
            renderCalendar();
        } else {
            if (pickStart && dateStr < pickStart) return;
            pickEnd = dateStr;
            dateTo.value = dateStr;
            picking = 'from';
            calPopup.style.display = 'none';
            updateRangeDisplay();
            document.getElementById('poFilterForm').submit();
        }
    }

    rangeInput.addEventListener('click', function(e) {
        e.stopPropagation();
        if (calPopup.style.display === 'none') {
            picking = 'from';
            pickStart = null;
            pickEnd = null;
            dateFrom.value = '';
            dateTo.value = '';
            rangeInput.value = '';
            rangeInput.placeholder = 'Select the starting date';
            var now = new Date();
            viewYear = now.getFullYear();
            viewMonth = now.getMonth();
            calPopup.style.display = '';
            renderCalendar();
        } else {
            calPopup.style.display = 'none';
        }
    });

    document.getElementById('calPrev').addEventListener('click', function(e) {
        e.stopPropagation();
        viewMonth--;
        if (viewMonth < 0) { viewMonth = 11; viewYear--; }
        renderCalendar();
    });
    document.getElementById('calNext').addEventListener('click', function(e) {
        e.stopPropagation();
        viewMonth++;
        if (viewMonth > 11) { viewMonth = 0; viewYear++; }
        renderCalendar();
    });
    calPopup.addEventListener('click', function(e) { e.stopPropagation(); });
    document.addEventListener('click', function(e) {
        if (!calPopup.contains(e.target) && e.target !== rangeInput) calPopup.style.display = 'none';
    });

    var rawStats = <?= json_encode(array_values($weeklyStats ?? [])) ?>;
    var weeklyDetails = <?= json_encode($weeklyDetails ?? []) ?>;

    var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    function getWeekDateRange(yearWeek) {
        var y = Math.floor(yearWeek / 100);
        var w = yearWeek % 100;
        var jan4 = new Date(y, 0, 4);
        var dayOffset = (w - 1) * 7 - jan4.getDay() + 1;
        var mon = new Date(y, 0, 4 + dayOffset);
        var sun = new Date(mon);
        sun.setDate(mon.getDate() + 6);
        return monthNames[mon.getMonth()] + ' ' + mon.getDate() + ' - ' + monthNames[sun.getMonth()] + ' ' + sun.getDate();
    }

    var chartLabels = [];
    var chartDeliveryData = [];
    var chartCasesData = [];
    var yearWeekKeys = [];
    var weekDates = [];

    var statsMap = {};
    var casesMap = {};
    rawStats.forEach(function(s) {
        statsMap[parseInt(s.year_week)] = parseInt(s.delivery_count);
        casesMap[parseInt(s.year_week)] = parseInt(s.total_cases);
    });

    function nextYearWeek(yw) {
        var y = Math.floor(yw / 100);
        var w = yw % 100;
        w++;
        if (w > 52) { y++; w = 1; }
        return y * 100 + w;
    }

    var firstYw = <?= $startYearWeek ?? 0 ?>;
    var weekFrom = <?= ($weekOffset ?? 0) * 12 + 1 ?>;
    for (var i = 0; i < 12; i++) {
        var yw = firstYw;
        for (var j = 0; j < i; j++) { yw = nextYearWeek(yw); }
        chartLabels.push('Week ' + (weekFrom + i));
        chartDeliveryData.push(statsMap[yw] || 0);
        chartCasesData.push(casesMap[yw] || 0);
        yearWeekKeys.push(String(yw));
        weekDates.push(getWeekDateRange(yw));
    }

    function showDeliveryDetails(dataIndex) {
        var yearWeek = yearWeekKeys[dataIndex];
        var deliveries = weeklyDetails[yearWeek] || [];
        var weekNum = weekFrom + dataIndex;
        var dateRange = weekDates[dataIndex];

        document.getElementById('detailModalTitle').innerHTML = '<i class="bi bi-truck me-2"></i>Week ' + weekNum + ' (' + dateRange + ') &mdash; ' + chartDeliveryData[dataIndex] + ' deliveries, ' + chartCasesData[dataIndex] + ' cases';

        var tbody = document.getElementById('deliveryDetailBody');
        var noMsg = document.getElementById('noDeliveriesMsg');

        if (deliveries.length === 0) {
            tbody.innerHTML = '';
            noMsg.classList.remove('d-none');
            tbody.closest('table').classList.add('d-none');
        } else {
            noMsg.classList.add('d-none');
            tbody.closest('table').classList.remove('d-none');
            var html = '';
            deliveries.forEach(function(d) {
                var date = new Date(d.delivery_date);
                var dateStr = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
                html += '<tr>';
                html += '<td>' + dateStr + '</td>';
                html += '<td>' + (d.dr_number || '-') + '</td>';
                html += '<td>' + (d.customer_name || '-') + '</td>';
                html += '<td>' + (d.customer_po_number || '-') + '</td>';
                html += '<td>' + d.cases_delivered + '</td>';
                html += '<td>' + d.delivery_quantity.toLocaleString() + ' ' + (d.item_uom || '') + '</td>';
                html += '<td>' + (d.delivered_by_name || '-') + '</td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        }

        new bootstrap.Modal(document.getElementById('deliveryDetailModal')).show();
    }

    var ctx = document.getElementById('weeklyDeliveryChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Deliveries & Cases',
                data: chartDeliveryData,
                backgroundColor: 'rgba(74, 144, 217, 0.7)',
                borderColor: 'rgba(74, 144, 217, 1)',
                borderWidth: 1,
                borderRadius: 4,
                hoverBackgroundColor: 'rgba(74, 144, 217, 1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onClick: function(evt, elements) {
                if (elements.length > 0) {
                    showDeliveryDetails(elements[0].index);
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function(items) {
                            return items[0].label;
                        },
                        afterTitle: function(items) {
                            var idx = items[0].dataIndex;
                            var d = chartDeliveryData[idx];
                            var c = chartCasesData[idx];
                            return d + ' ' + (d === 1 ? 'delivery' : 'deliveries') + ' | ' + c + ' ' + (c === 1 ? 'case' : 'cases');
                        },
                        label: function() {
                            return '';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    },
                    title: {
                        display: true,
                        text: 'Number of Deliveries'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Week'
                    }
                }
            }
        }
    });
});
</script>

<!-- Export Preview Modal -->
<div class="modal fade" id="exportPreviewModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-excel me-2"></i>Export PO Item Summary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted">Preview (first 100 rows)</small>
                    <small class="text-muted" id="exportPreviewCount"></small>
                </div>
                <div class="table-responsive border rounded" style="max-height: 450px; overflow: auto;">
                    <table class="table table-sm table-bordered mb-0" id="exportPreviewTable">
                        <thead class="table-light" style="position: sticky; top: 0;">
                            <tr id="exportPreviewHead"></tr>
                        </thead>
                        <tbody id="exportPreviewBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="exportDownloadBtn"><i class="bi bi-download me-1"></i>Download Excel</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var exportData = <?= json_encode(array_map(function($i) {
        return [
            'customer' => $i['customer_name'] ?? '',
            'po_number' => $i['customer_po_number'] ?? '',
            'po_date' => $i['po_date'] ? date('Y-m-d', strtotime($i['po_date'])) : '',
            'item' => $i['item_description'] ?? '',
            'po_qty' => intval($i['po_qty']),
            'delivered_quantity' => intval($i['delivered_quantity']),
            'uom_conversion' => intval($i['uom_conversion'] ?? 0),
            'manually_closed' => intval($i['manually_closed'] ?? 0),
            'po_manually_closed' => intval($i['po_manually_closed'] ?? 0),
        ];
    }, $filteredItems)); ?>;
    var exportBaseParams = '<?= $exportParams ?>';
    var exportDateFrom = '<?= htmlspecialchars($filterDateFrom) ?>';
    var exportDateTo = '<?= htmlspecialchars($filterDateTo) ?>';
    var cols = ['customer', 'po_number', 'po_date', 'item', 'order_qty', 'delivered_qty', 'balance_qty', 'status'];
    var simpleKeys = ['customer', 'po_number', 'po_date', 'item', 'status'];
    var pairKeys = ['order_qty', 'delivered_qty', 'balance_qty'];
    var colLabels = { customer: 'Customer', po_number: 'PO#', po_date: 'PO Date', item: 'Item Name', order_qty: 'Order Qty', delivered_qty: 'Delivered Qty', balance_qty: 'Balance Qty', status: 'Status' };

    function statusOf(item) {
        if (item.delivered_quantity >= item.po_qty) return 'Closed';
        if (item.manually_closed === 1 || item.po_manually_closed === 1) return 'Manually Closed';
        if (item.delivered_quantity > 0) return 'Partial';
        return 'Pending';
    }

    function cellValues(item) {
        var conv = item.uom_conversion;
        var values = {
            customer: item.customer,
            po_number: item.po_number,
            po_date: item.po_date,
            item: item.item,
            status: statusOf(item)
        };
        pairKeys.forEach(function(k) {
            var pcs = k === 'order_qty' ? item.po_qty : (k === 'delivered_qty' ? item.delivered_quantity : Math.max(0, item.po_qty - item.delivered_quantity));
            var cs = conv > 0 ? Math.floor(pcs / conv) : pcs;
            values[k] = [pcs, cs];
        });
        var cells = [];
        cols.forEach(function(c) {
            if (simpleKeys.indexOf(c) >= 0) {
                cells.push({ col: c, value: String(values[c]) });
            } else {
                cells.push({ col: c, value: values[c][0] + ' pcs' });
                cells.push({ col: c, value: values[c][1] + ' cs' });
            }
        });
        return cells;
    }

    function renderPreview() {
        var head = document.getElementById('exportPreviewHead');
        var body = document.getElementById('exportPreviewBody');
        head.innerHTML = '<tr>' + cols.map(function(c) {
            return simpleKeys.indexOf(c) >= 0
                ? '<th>' + colLabels[c] + '</th>'
                : '<th colspan="2" class="text-center">' + colLabels[c] + '</th>';
        }).join('') + '</tr>';

        var data = exportData.slice().sort(function(a, b) {
            return String(a.customer).localeCompare(String(b.customer));
        });
        document.getElementById('exportPreviewCount').textContent = data.length + ' rows';
        var shown = data.slice(0, 100);
        function esc(v) { var d = document.createElement('div'); d.textContent = v; return d.innerHTML; }
        body.innerHTML = shown.map(function(item) {
            var vals = cellValues(item);
            return '<tr>' + vals.map(function(cell) {
                var isBoundaryDate = cell.col === 'po_date' && ((exportDateFrom !== '' && item.po_date === exportDateFrom) || (exportDateTo !== '' && item.po_date === exportDateTo));
                return '<td' + (isBoundaryDate ? ' style="background-color:#fff3b0;"' : '') + '>' + esc(cell.value) + '</td>';
            }).join('') + '</tr>';
        }).join('');
    }

    document.getElementById('exportPreviewBtn').addEventListener('click', function() {
        var modal = new bootstrap.Modal(document.getElementById('exportPreviewModal'));
        modal.show();
    });
    document.getElementById('exportPreviewModal').addEventListener('shown.bs.modal', renderPreview);

    document.getElementById('exportDownloadBtn').addEventListener('click', function() {
        var params = new URLSearchParams(exportBaseParams);
        params.set('sheet', 'po_items');
        window.location.href = 'index.php?' + params.toString();
    });
});
</script>

