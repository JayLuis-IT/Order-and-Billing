<h4><i class="bi bi-truck me-2"></i>Delivered PO</h4>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <?php if (($reportedCount ?? 0) > 0): ?>
            <?php if ($filterReports ?? false): ?>
                <a href="?controller=admin&action=delivered<?= ($filterCustomer ?? '') ? '&filter_customer=' . urlencode($filterCustomer) : '' ?><?= ($filterItem ?? '') ? '&filter_item=' . urlencode($filterItem) : '' ?><?= ($filterDR ?? '') ? '&filter_dr=' . urlencode($filterDR) : '' ?><?= ($filterPo ?? '') ? '&filter_po=' . urlencode($filterPo) : '' ?><?= ($filterDeliveredBy ?? '') ? '&filter_delivered_by=' . urlencode($filterDeliveredBy) : '' ?><?= ($filterType ?? '') ? '&filter_type=' . urlencode($filterType) : '' ?><?= ($filterDate ?? '') ? '&filter_date=' . urlencode($filterDate) : '' ?><?= ($search ?? '') ? '&search=' . urlencode($search) : '' ?>"
                   class="btn btn-sm btn-warning fw-bold">
                    <i class="bi bi-flag-fill me-1"></i>Show All
                </a>
            <?php else: ?>
                <a href="?controller=admin&action=delivered&filter_reports=1<?= ($filterCustomer ?? '') ? '&filter_customer=' . urlencode($filterCustomer) : '' ?><?= ($filterItem ?? '') ? '&filter_item=' . urlencode($filterItem) : '' ?><?= ($filterDR ?? '') ? '&filter_dr=' . urlencode($filterDR) : '' ?><?= ($filterPo ?? '') ? '&filter_po=' . urlencode($filterPo) : '' ?><?= ($filterDeliveredBy ?? '') ? '&filter_delivered_by=' . urlencode($filterDeliveredBy) : '' ?><?= ($filterType ?? '') ? '&filter_type=' . urlencode($filterType) : '' ?><?= ($filterDate ?? '') ? '&filter_date=' . urlencode($filterDate) : '' ?><?= ($search ?? '') ? '&search=' . urlencode($search) : '' ?>"
                   class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-flag-fill me-1"></i>Reports <span class="badge bg-danger ms-1"><?= $reportedCount ?></span>
                </a>
            <?php endif; ?>
        <?php endif; ?>
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="hidden" name="controller" value="admin">
            <input type="hidden" name="action" value="delivered">
            <select name="filter_customer" class="form-select form-select-sm filter-select" style="width:170px" onchange="this.form.submit()">
                <option value="">All Customers</option>
                <?php foreach (($allCustomers ?? []) as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= ($filterCustomer ?? '') === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_item" class="form-select form-select-sm filter-select" style="width:170px" onchange="this.form.submit()">
                <option value="">All Items</option>
                <?php foreach (($allItems ?? []) as $i): ?>
                    <option value="<?= htmlspecialchars($i) ?>" <?= ($filterItem ?? '') === $i ? 'selected' : '' ?>><?= htmlspecialchars($i) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_dr" class="form-select form-select-sm filter-select" style="width:140px" onchange="this.form.submit()">
                <option value="">All DR Numbers</option>
                <?php foreach (($allDRs ?? []) as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= ($filterDR ?? '') === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_po" class="form-select form-select-sm filter-select" style="width:170px" onchange="this.form.submit()">
                <option value="">All PO Numbers</option>
                <?php foreach (($allPOs ?? []) as $p): ?>
                    <option value="<?= htmlspecialchars($p) ?>" <?= ($filterPo ?? '') === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_delivered_by" class="form-select form-select-sm filter-select" style="width:160px" onchange="this.form.submit()">
                <option value="">All Delivered By</option>
                <?php foreach (($allDeliveredBy ?? []) as $u): ?>
                    <option value="<?= htmlspecialchars($u) ?>" <?= ($filterDeliveredBy ?? '') === $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="filter_date" class="form-control form-control-sm" style="width:150px" value="<?= htmlspecialchars($filterDate ?? '') ?>" title="Filter by Delivery Date" onchange="this.form.submit()">
        </form>
        <a href="?controller=admin&action=delivered" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Clear</a>
    </div>
    <div class="search-box" style="width: 300px;">
        <form method="GET" class="d-flex align-items-center">
            <input type="hidden" name="controller" value="admin">
            <input type="hidden" name="action" value="delivered">
            <input type="hidden" name="filter_customer" value="<?= htmlspecialchars($filterCustomer ?? '') ?>">
            <input type="hidden" name="filter_item" value="<?= htmlspecialchars($filterItem ?? '') ?>">
            <input type="hidden" name="filter_dr" value="<?= htmlspecialchars($filterDR ?? '') ?>">
            <input type="hidden" name="filter_po" value="<?= htmlspecialchars($filterPo ?? '') ?>">
            <input type="hidden" name="filter_delivered_by" value="<?= htmlspecialchars($filterDeliveredBy ?? '') ?>">

            <input type="hidden" name="filter_date" value="<?= htmlspecialchars($filterDate ?? '') ?>">
            <?php if ($filterReports ?? false): ?>
            <input type="hidden" name="filter_reports" value="1">
            <?php endif; ?>
            <i class="bi bi-search"></i>
            <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search ?? '') ?>">
        </form>
    </div>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Customer</th>
                    <th>DR Number</th>
                    <th>SI Number</th>
                    <th>Item</th>
                    <th>Lot Number</th>
                    <th>Quantity</th>
                    <th>Delivery Date</th>
                    <th>Cases</th>
                    <th>Remarks</th>
                    <th>Report / Edit</th>
                    <th>Delivered By</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="deliveryTableBody">
                <?php if (!empty($deliveries)): ?>
                <?php foreach ($deliveries as $d):
                    $lotItems = json_decode($d['lot_items'] ?? '[]', true);
                    $hasLotItems = is_array($lotItems) && count($lotItems) > 0;
                    $isActive = ($d['active_status'] ?? 1) == 1;
                ?>
                <tr class="<?= $isActive ? '' : 'text-decoration-line-through' ?>">
                    <td><strong class="text-primary"><?= htmlspecialchars($d['customer_po_number']) ?></strong></td>
                    <td><?= htmlspecialchars($d['customer_name'] ?? '-') ?></td>
                    <td>
                        <?= htmlspecialchars($d['dr_number'] ?? '') ?: '<span class="text-muted">-</span>' ?>
                        <?php if (!empty($d['old_dr_number'])): ?>
                            <br><small style="color:#e6a800;font-weight:bold;">(old: <?= htmlspecialchars($d['old_dr_number']) ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($d['si_number'])): ?>
                            <strong class="text-success"><?= htmlspecialchars($d['si_number']) ?></strong>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($hasLotItems): ?>
                            <?php foreach ($lotItems as $idx => $li): ?>
                                <?= $idx > 0 ? '<hr class="my-1 border-secondary">' : '' ?>
                                <small><?= htmlspecialchars($li['item_description'] ?? '') ?></small>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <small><?= htmlspecialchars($d['item_code'] ?? '-') ?> - <?= htmlspecialchars($d['item_description'] ?? '') ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($hasLotItems): ?>
                            <?php foreach ($lotItems as $idx => $li): ?>
                                <?= $idx > 0 ? '<hr class="my-1 border-secondary">' : '' ?>
                                <small class="text-muted"><?= htmlspecialchars($li['lot_number'] ?? '-') ?></small>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <small class="text-muted"><?= htmlspecialchars($d['lot_number'] ?? '-') ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $oldQtyMap = json_decode($d['old_quantity'] ?? '{}', true);
                        if (!is_array($oldQtyMap)) $oldQtyMap = [];
                        ?>
                        <?php if ($hasLotItems): ?>
                            <?php foreach ($lotItems as $idx => $li): ?>
                                <?= $idx > 0 ? '<hr class="my-1 border-secondary">' : '' ?>
                                <?= formatQty($li['qty'] ?? 0) ?>
                                <?php if (isset($oldQtyMap[strval($li['lot_id'] ?? '')])): ?>
                                    <br><small style="color:#e6a800;font-weight:bold;">(old: <?= formatQty($oldQtyMap[strval($li['lot_id'])]) ?>)</small>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?= formatQty($d['delivery_quantity'] ?? 0) ?>
                            <?php if (!empty($oldQtyMap)): ?>
                                <?php $firstOld = reset($oldQtyMap); ?>
                                <br><small style="color:#e6a800;font-weight:bold;">(old: <?= $firstOld ?>)</small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= date('Y-m-d', strtotime($d['delivery_date'])) ?></td>
                    <td>
                        <?php
                        if ($hasLotItems) {
                            $grouped = [];
                            foreach ($lotItems as $li) {
                                $key = $li['item_description'] ?? $li['item_code'] ?? 'Item';
                                if (!isset($grouped[$key])) $grouped[$key] = ['qty' => 0, 'conv' => null, 'uom' => ''];
                                $grouped[$key]['qty'] += $li['qty'] ?? 0;
                                if (!empty($li['actual_uom_conversion'])) $grouped[$key]['conv'] = $li['actual_uom_conversion'];
                                elseif (empty($grouped[$key]['conv']) && !empty($li['uom_conversion'])) $grouped[$key]['conv'] = $li['uom_conversion'];
                                if (!empty($li['item_uom'])) $grouped[$key]['uom'] = $li['item_uom'];
                            }
                            $caseParts = [];
                            foreach ($grouped as $desc => $info) {
                                $c = $info['conv'];
                                $u = $info['uom'];
                                if ($c && $u !== 'CS') {
                                    $caseParts[] = htmlspecialchars($desc) . ': ' . floor($info['qty'] / $c) . ' CS';
                                }
                            }
                            echo !empty($caseParts) ? implode('<br>', $caseParts) : '<span class="text-muted">â€”</span>';
                        } else {
                            $conv = $d['actual_uom_conversion'] ?? $d['uom_conversion'] ?? null;
                            $itemUom = $d['item_uom'] ?? '';
                            $desc = $d['item_description'] ?? '';
                            if ($conv && $itemUom !== 'CS') {
                                echo htmlspecialchars($desc) . ': ' . floor(($d['delivery_quantity'] ?? 0) / $conv) . ' CS';
                            } else {
                                echo '<span class="text-muted">â€”</span>';
                            }
                        }
                        ?>
                    </td>
                    <td>
                        <?php if (!empty($d['remarks'])): ?>
                            <span><?= htmlspecialchars($d['remarks']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($d['report_remarks'])): ?>
                            <?php
                            $rmType = $d['remarks_type'] ?? '';
                            if ($rmType === 'edited') $rmStyle = 'color:#e6a800;font-weight:bold;';
                            elseif ($rmType === 'report') $rmStyle = 'color:red;font-weight:bold;';
                            else $rmStyle = '';
                            ?>
                            <span style="<?= $rmStyle ?>"><?= htmlspecialchars($d['report_remarks']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($d['delivered_by_name'] ?? '-') ?></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-primary view-po-btn"
                            data-po-id="<?= $d['po_id'] ?>"
                            data-delivery-id="<?= $d['delivery_id'] ?>"
                            data-dr="<?= htmlspecialchars($d['dr_number'] ?? '') ?>"
                            data-si="<?= htmlspecialchars($d['si_number'] ?? '') ?>"
                            data-lot-items="<?= htmlspecialchars($d['lot_items'] ?? '[]') ?>"
                            data-delivery-date="<?= date('Y-m-d', strtotime($d['delivery_date'])) ?>"
                            data-receipts="<?= htmlspecialchars(json_encode($receipts_map[$d['delivery_id']] ?? [])) ?>"
                            title="View PO Details">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning editDeliveryBtn"
                            data-delivery-id="<?= $d['delivery_id'] ?>"
                            data-dr="<?= htmlspecialchars($d['dr_number'] ?? '') ?>"
                            data-date="<?= date('Y-m-d', strtotime($d['delivery_date'])) ?>"
                            data-lot-items="<?= htmlspecialchars($d['lot_items'] ?? '[]') ?>"
                            data-po-id="<?= $d['po_id'] ?>"
                            data-plate="<?= htmlspecialchars($d['plate_number'] ?? '') ?>"
                            data-vehicle="<?= htmlspecialchars($d['vehicle_type'] ?? '') ?>"
                            data-logistic="<?= htmlspecialchars($d['logistic_provider'] ?? '') ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="?controller=admin&action=deleteDelivery&id=<?= $d['delivery_id'] ?>"
                           class="btn btn-sm btn-outline-danger"
                           data-confirm="Delete this delivery and roll back its quantities?"
                           title="Delete delivery and undo its impact">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr><td colspan="12" class="text-center text-muted py-4">No delivery records found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (($totalPages ?? 1) > 1): ?>
<?php
$pages = \App\Helpers\Pagination::getPageRange($page, $totalPages);
$pageParams = 'controller=admin&action=delivered';
foreach (['search', 'filter_customer', 'filter_item', 'filter_dr', 'filter_po', 'filter_delivered_by', 'filter_date', 'filter_reports'] as $p) {
    $val = $GLOBALS['_GET'][$p] ?? $$p ?? '';
    if ($val !== '') $pageParams .= '&' . $p . '=' . urlencode($val);
}
?>
<nav>
    <ul class="pagination justify-content-center mt-4">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= $pageParams ?>&page=<?= $page - 1 ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= $pageParams ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= $pageParams ?>&page=<?= $page + 1 ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<!-- Edit Delivery Modal -->
<div class="modal fade" id="editDeliveryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Delivery</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDeliveryForm">
                <div class="modal-body">
                    <input type="hidden" name="delivery_id" id="editDeliveryId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">DR Number</label>
                            <input type="text" name="dr_number" id="editDrNumber" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Delivery Date</label>
                            <input type="date" name="delivery_date" id="editDeliveryDate" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Purchase Order <span class="text-danger">*</span></label>
                            <select name="po_id" id="editPoSelect" class="form-select">
                                <option value="">-- Select Purchase Order --</option>
                                <?php foreach (($poEditOptions ?? []) as $po): ?>
                                    <option value="<?= (int)$po['po_id'] ?>"><?= htmlspecialchars($po['po_number'] ?: ($po['customer_po_number'] ?? ('PO #' . $po['po_id']))) ?> — <?= htmlspecialchars($po['customer_name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Plate No.</label>
                            <input type="text" name="plate_number" id="editPlateNumber" class="form-control" placeholder="Plate number">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Vehicle Type</label>
                            <input type="text" name="vehicle_type" id="editVehicleType" class="form-control" placeholder="Vehicle type">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Logistic Provider</label>
                            <input type="text" name="logistic_provider" id="editLogisticProvider" class="form-control" placeholder="Logistic provider">
                        </div>
                    </div>
                    <div id="editLotItemsRow" style="display:none;">
                        <label class="form-label fw-bold">Lot Items — Edit Item, Lot &amp; Quantity</label>
                        <div class="border rounded p-2" style="background:#f8f9fa;">
                            <div class="row mb-1">
                                <div class="col-md-4"><small class="text-muted fw-bold">Item</small></div>
                                <div class="col-md-4"><small class="text-muted fw-bold">Lot Number</small></div>
                                <div class="col-md-4"><small class="text-muted fw-bold">Quantity</small></div>
                            </div>
                            <div id="editLotItemsContainer"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View PO Details Modal -->
<div class="modal fade" id="viewPOModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>PO Details - <span id="viewPONumber"></span> <span id="viewSIBadge" class="badge bg-success ms-2" style="display:none;"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <p class="mb-1"><strong>Customer Code:</strong></p>
                        <p class="text-muted" id="viewCustomerCode">-</p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1"><strong>Customer Name:</strong></p>
                        <p class="text-muted" id="viewCustomerName">-</p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1"><strong>Customer TIN:</strong></p>
                        <p class="text-muted" id="viewCustomerTin">-</p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1"><strong>Terms:</strong></p>
                        <p class="text-muted" id="viewCustomerTerms">-</p>
                    </div>
                </div>
                <h5 class="mb-3">Items</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>Lot Number</th>
                                <th class="text-end">Delivered</th>
                                <th class="text-end">Cases</th>
                    <th>DR Number</th>
                    <th>SI Number</th>
                            </tr>
                        </thead>
                        <tbody id="viewPOItems"></tbody>
                    </table>
                </div>
                <hr>
                <h6 class="mb-2"><i class="bi bi-paperclip me-1"></i>DR Attachments</h6>
                <div id="viewDRPhotoSection">
                    <div id="viewDRPhotoContainer" class="d-flex flex-wrap gap-2"></div>
                </div>
                <hr>
                <h6 class="mb-2"><i class="bi bi-receipt me-1"></i>SI Attachments</h6>
                <div id="viewSISection">
                    <div id="viewSIContainer" class="d-flex flex-wrap gap-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-po-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const poId = this.getAttribute('data-po-id');
            const drNumber = this.getAttribute('data-dr') || '-';
            const siNumber = this.getAttribute('data-si') || '';
            var receipts = [];
            try { receipts = JSON.parse(this.getAttribute('data-receipts') || '[]'); } catch(e) {}
            const lotItemsRaw = this.getAttribute('data-lot-items') || '[]';
            const lotItemsRawArr = JSON.parse(lotItemsRaw);
            const mergedLI = {};
            lotItemsRawArr.forEach(function(item) {
                var key = (item.lot_number || '') + '||' + (item.item_code || '');
                if (mergedLI[key]) { mergedLI[key].qty += item.qty || 0; } else { mergedLI[key] = Object.assign({}, item); }
            });
            const lotItems = Object.values(mergedLI);
            const hasLotItems = Array.isArray(lotItems) && lotItems.length > 0;

            fetch('?controller=warehouse&action=getPODetails&id=' + poId)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    const po = data.po;

                    document.getElementById('viewPONumber').textContent = po.customer_po_number || '-';
                    var siBadge = document.getElementById('viewSIBadge');
                    if (siNumber) {
                        siBadge.textContent = siNumber;
                        siBadge.style.display = '';
                    } else {
                        siBadge.style.display = 'none';
                    }
                    document.getElementById('viewCustomerCode').textContent = po.customer_code || '-';
                    document.getElementById('viewCustomerName').textContent = po.customer_name || '-';
                    document.getElementById('viewCustomerTin').textContent = po.customer_tin || '-';
                    document.getElementById('viewCustomerTerms').textContent = (po.customer_terms || 0) + ' days';

                    const tbody = document.getElementById('viewPOItems');
                    tbody.innerHTML = '';

                    if (hasLotItems) {
                        lotItems.forEach(function(li) {
                            var qty = li.qty || 0;
                            var conv = li.actual_uom_conversion || li.uom_conversion || null;
                            var uom = li.item_uom || '';
                            var cases = (conv && uom !== 'CS') ? Math.floor(qty / conv) : 0;

                            var row = '<tr>' +
                                '<td>' + (li.item_code || '-') + '</td>' +
                                '<td>' + (li.item_description || '-') + '</td>' +
                                '<td>' + (li.lot_number || '-') + '</td>' +
                                '<td class="text-end">' + qty + ' pcs</td>' +
                                '<td class="text-end">' + (cases > 0 ? cases + ' CS' : '---') + '</td>' +
                                '<td><span class="badge bg-secondary">' + drNumber + '</span></td>' +
                                '<td>' + (siNumber ? '<span class="badge bg-success">' + siNumber + '</span>' : '<span class="text-muted">-</span>') + '</td>' +
                                '</tr>';
                            tbody.innerHTML += row;
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No lot items found for this delivery</td></tr>';
                    }

                    const modal = new bootstrap.Modal(document.getElementById('viewPOModal'));
                    modal.show();

                    var photoContainer = document.getElementById('viewDRPhotoContainer');
                    var siContainer = document.getElementById('viewSIContainer');
                    photoContainer.innerHTML = '';
                    siContainer.innerHTML = '';

                    var drReceipts = receipts.filter(function(r) { return (r.type || 'dr') === 'dr'; });
                    var siReceipts = receipts.filter(function(r) { return r.type === 'si'; });

                    if (drReceipts.length > 0) {
                        drReceipts.forEach(function(r) {
                            var path = (typeof URL_ROOT !== 'undefined' ? URL_ROOT : '/') + (r.file_path || '');
                            var wrapper = document.createElement('div');
                            wrapper.className = 'position-relative d-inline-block';
                            if (path.toLowerCase().endsWith('.pdf')) {
                                wrapper.innerHTML = '<a href="' + path + '" target="_blank" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>';
                            } else {
                                wrapper.innerHTML = '<a href="' + path + '" target="_blank"><img src="' + path + '" alt="DR Attachment" style="max-height:120px;border-radius:6px;border:1px solid #ddd;" onerror="this.parentElement.innerHTML=\'<span class=text-muted>File not found</span>\'"></a>';
                            }
                            photoContainer.appendChild(wrapper);
                        });
                    } else {
                        photoContainer.innerHTML = '<span class="text-muted">No DR attachments</span>';
                    }

                    if (siReceipts.length > 0) {
                        siReceipts.forEach(function(r) {
                            var path = (typeof URL_ROOT !== 'undefined' ? URL_ROOT : '/') + (r.file_path || '');
                            var wrapper = document.createElement('div');
                            wrapper.className = 'position-relative d-inline-block';
                            if (path.toLowerCase().endsWith('.pdf')) {
                                wrapper.innerHTML = '<a href="' + path + '" target="_blank" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>';
                            } else {
                                wrapper.innerHTML = '<a href="' + path + '" target="_blank"><img src="' + path + '" alt="SI Attachment" style="max-height:120px;border-radius:6px;border:1px solid #ddd;" onerror="this.parentElement.innerHTML=\'<span class=text-muted>File not found</span>\'"></a>';
                            }
                            siContainer.appendChild(wrapper);
                        });
                    } else {
                        siContainer.innerHTML = '<span class="text-muted">No SI attachments</span>';
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    Dlg.alert('Failed to load PO details: ' + error.message);
                });
        });
    });
});

document.querySelectorAll('.editDeliveryBtn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('editDeliveryId').value = this.dataset.deliveryId;
        document.getElementById('editDrNumber').value = this.dataset.dr || '';
        document.getElementById('editDeliveryDate').value = this.dataset.date || '';
        document.getElementById('editPlateNumber').value = this.dataset.plate || '';
        document.getElementById('editVehicleType').value = this.dataset.vehicle || '';
        document.getElementById('editLogisticProvider').value = this.dataset.logistic || '';
        var poSelect = document.getElementById('editPoSelect');
        poSelect.value = this.dataset.poId || '';
        if (!poSelect.value && this.dataset.poId) {
            var poOpt = document.createElement('option');
            poOpt.value = this.dataset.poId;
            poOpt.textContent = 'PO #' + this.dataset.poId + ' (current)';
            poSelect.insertBefore(poOpt, poSelect.options[1] || null);
            poSelect.value = this.dataset.poId;
        }

        var deliveryId = this.dataset.deliveryId;
        var lotItemsRaw = JSON.parse(this.dataset.lotItems || '[]');
        var mergedED = {};
        lotItemsRaw.forEach(function(item) {
            var key = (item.lot_number || '') + '||' + (item.item_code || '');
            if (mergedED[key]) { mergedED[key].qty += item.qty || 0; } else { mergedED[key] = Object.assign({}, item); }
        });
        var lotItems = Object.values(mergedED);
        var container = document.getElementById('editLotItemsContainer');
        container.innerHTML = '';
        var itemEditOptions = <?= json_encode(array_map(function($i) {
            return ['item_id' => (int)$i['item_id'], 'item_code' => $i['item_code'], 'item_description' => $i['item_description']];
        }, $itemEditOptions ?? [])) ?>;
        function escapeAttr(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); }

        // Items shown in the edit rows are filtered to the selected PO selection
        function loadPoItems(poId, rowsCallback) {
            var poSel = document.getElementById('editPoSelect');
            var selectSource = [];
            if (poId) {
                fetch('?controller=admin&action=getItemsForDeliveryEdit&po_id=' + encodeURIComponent(poId))
                    .then(function(r) { return r.json(); })
                    .then(function(poItems) {
                        poSel.dataset.poItems = JSON.stringify(poItems || []);
                        if (rowsCallback) rowsCallback();
                    });
            } else {
                poSel.dataset.poItems = JSON.stringify(itemEditOptions);
                if (rowsCallback) rowsCallback();
            }
        }

        function populateItemSelect(rowEl) {
            var poSel = document.getElementById('editPoSelect');
            var source;
            try { source = JSON.parse(poSel.dataset.poItems || '[]'); } catch (e) { source = itemEditOptions; }
            if (!source || !source.length) source = itemEditOptions;
            var itemSel = rowEl.querySelector('.edit-lot-item');
            itemSel.innerHTML = '<option value="">-- Select item --</option>';
            var currentCode = rowEl.dataset.currentItemCode || rowEl.dataset.oldItemCode || '';
            source.forEach(function(it) {
                var opt = document.createElement('option');
                opt.value = it.item_id;
                opt.dataset.itemCode = it.item_code;
                opt.textContent = it.item_code + ' - ' + it.item_description;
                itemSel.appendChild(opt);
                if ((currentCode || '') === it.item_code) itemSel.value = it.item_id;
            });
            if (!itemSel.value && currentCode) {
                var opt = document.createElement('option');
                opt.value = '';
                opt.dataset.itemCode = currentCode;
                opt.textContent = currentCode + ' (current)';
                itemSel.appendChild(opt);
            }
        }

        function loadLotChoices(rowEl, itemId) {
            var lotSel = rowEl.querySelector('.edit-lot-number');
            var currentLot = lotSel.dataset.currentLot || '';
            if (!itemId) {
                lotSel.innerHTML = '<option value="">-- Select item first --</option>';
                return;
            }
            fetch('?controller=admin&action=getLotChoicesForDeliveryEdit&item_id=' + encodeURIComponent(itemId) + '&delivery_id=' + encodeURIComponent(deliveryId))
                .then(function(r) { return r.json(); })
                .then(function(lots) {
                    var html = '<option value="">-- Select lot --</option>';
                    (lots || []).forEach(function(l) {
                        // Only lots with available stock; the delivery's own current lot is
                        // always kept (its qty goes back on the shelf once replaced)
                        if ((l.available_quantity || 0) <= 0 && l.lot_number !== currentLot) return;
                        html += '<option value="' + l.lot_id + '" data-lot-number="' + escapeAttr(l.lot_number) + '">' +
                            escapeAttr(l.lot_number) + ' (Avail: ' + (l.available_quantity || 0).toLocaleString() + ')</option>';
                    });
                    // keep the currently-stored lot selectable even if no production row backs it
                    if (currentLot && !Array.prototype.some.call(lots, function(l) { return l.lot_number === currentLot; })) {
                        html += '<option value="0" data-lot-number="' + escapeAttr(currentLot) + '">' + escapeAttr(currentLot) + ' (current)</option>';
                    }
                    lotSel.innerHTML = html;
                    var match = Array.prototype.find.call(lotSel.options, function(o) { return o.dataset.lotNumber === currentLot; });
                    if (match) lotSel.value = match.value;
                });
        }

        if (lotItems.length > 0) {
            document.getElementById('editLotItemsRow').style.display = 'block';
            document.getElementById('editPoSelect').dataset.poItems = JSON.stringify(itemEditOptions);
            lotItems.forEach(function(li) {
                var row = document.createElement('div');
                row.className = 'row mb-2 align-items-center';
                row.dataset.oldLotNumber = li.lot_number || '';
                row.dataset.oldItemCode = li.item_code || '';
                row.dataset.currentItemCode = li.item_code || '';
                row.dataset.lotId = li.lot_id || '';
                row.dataset.poiId = li.poi_id || '';
                row.dataset.oldQty = li.qty || 0;
                row.innerHTML = '<div class="col-md-4">' +
                        '<select class="form-select form-select-sm edit-lot-item"><option value="">-- Select item --</option></select>' +
                    '</div>' +
                    '<div class="col-md-4">' +
                        '<select class="form-select form-select-sm edit-lot-number" data-current-lot="' + escapeAttr(li.lot_number) + '">' +
                            '<option value="">-- Select lot --</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="col-md-4">' +
                        '<input type="number" class="form-control form-control-sm edit-lot-qty" value="' + (li.qty || 0) + '" min="1">' +
                    '</div>';
                container.appendChild(row);

                populateItemSelect(row);
                var itemSel = row.querySelector('.edit-lot-item');
                itemSel.addEventListener('change', function() {
                    loadLotChoices(row, itemSel.value);
                });
                loadLotChoices(row, itemSel.value);
            });
            // Filter item options when the PO selection changes in the modal
            document.getElementById('editPoSelect').addEventListener('change', function() {
                var rows = document.querySelectorAll('#editLotItemsContainer .row');
                loadPoItems(this.value, function() {
                    rows.forEach(function(row) { populateItemSelect(row); });
                });
            });
            // Preload PO-scoped item list for the current PO on open
            loadPoItems(document.getElementById('editPoSelect').value, function() {
                document.querySelectorAll('#editLotItemsContainer .row').forEach(function(row) { populateItemSelect(row); });
            });
        } else {
            document.getElementById('editLotItemsRow').style.display = 'none';
        }

        var modal = new bootstrap.Modal(document.getElementById('editDeliveryModal'));
        modal.show();
    });
});

document.getElementById('editDeliveryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);

    var lotChanges = [];
    var itemChanges = [];
    var rows = document.querySelectorAll('#editLotItemsContainer .row');
    rows.forEach(function(rowEl) {
        var itemSel = rowEl.querySelector('.edit-lot-item');
        var lotSel = rowEl.querySelector('.edit-lot-number');
        var qtyInput = rowEl.querySelector('.edit-lot-qty');
        if (!itemSel || !lotSel || !qtyInput) return;

        var oldQty = parseInt(rowEl.dataset.oldQty) || 0;
        var newQty = parseInt(qtyInput.value) || 0;
        var oldLotNumber = rowEl.dataset.oldLotNumber || '';
        var oldItemCode = rowEl.dataset.oldItemCode || '';
        var selectedLotId = lotSel.value;
        var selectedOpt = lotSel.options[lotSel.selectedIndex];
        var newLotNumber = selectedOpt ? (selectedOpt.dataset.lotNumber || '') : '';
        var selectedOptItem = itemSel.options[itemSel.selectedIndex];
        var newItemCode = selectedOptItem ? (selectedOptItem.dataset.itemCode || '') : '';

        var itemChanged = !!itemSel.value && oldItemCode !== newItemCode;
        var lotChanged = !!selectedLotId && oldLotNumber !== newLotNumber;

        if (itemChanged || lotChanged) {
            itemChanges.push({
                old_lot_number: oldLotNumber,
                old_item_code: oldItemCode,
                old_qty: oldQty,
                new_item_id: itemSel.value,
                new_lot_id: selectedLotId,
                new_qty: newQty
            });
        } else if (newQty !== oldQty) {
            lotChanges.push({
                lot_id: rowEl.dataset.lotId,
                poi_id: rowEl.dataset.poiId,
                old_qty: oldQty,
                new_qty: newQty
            });
        }
    });

    formData.append('lot_changes', JSON.stringify(lotChanges));
    formData.append('item_changes', JSON.stringify(itemChanges));

    fetch('?controller=admin&action=updateDelivery', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editDeliveryModal')).hide();
            Dlg.alert('Delivery updated successfully!');
            location.reload();
        } else {
            Dlg.alert('Error: ' + (data.error || 'Failed to update delivery'));
        }
    })
    .catch(function(err) {
        Dlg.alert('Error updating delivery: ' + err.message);
    });
});
</script>
