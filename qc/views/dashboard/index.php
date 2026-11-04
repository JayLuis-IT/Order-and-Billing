<h4><i class="bi bi-clipboard-check me-2"></i>QC Dashboard</h4>

<div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Quarantine Queue</h5>
        <span class="badge bg-warning text-dark"><?= count($pendingQcItems ?? []) ?> pending</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>PO Ref</th>
                        <th>Supplier</th>
                        <th>Item</th>
                        <th>Lot</th>
                        <th class="text-end">Received</th>
                        <th>Received Date</th>
                        <th>Remarks</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendingQcItems)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No goods currently awaiting QC approval.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pendingQcItems as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['po_ref'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($item['supplier_name'] ?? '-') ?></td>
                                <td><strong><?= htmlspecialchars($item['item_code'] ?? '-') ?></strong><br><small><?= htmlspecialchars($item['item_description'] ?? '-') ?></small></td>
                                <td><?= htmlspecialchars($item['lot_number'] ?? '-') ?></td>
                                <td class="text-end"><?= formatQty((float) ($item['received_qty'] ?? 0)) ?></td>
                                <td><?= !empty($item['received_date']) ? date('Y-m-d', strtotime($item['received_date'])) : '-' ?></td>
                                <td><?= !empty($item['remarks']) ? htmlspecialchars($item['remarks']) : '<span class="text-muted">-</span>' ?></td>
                                <td class="text-end">
                                    <button type="button"
                                            class="btn btn-sm btn-primary inspect-qc-btn"
                                            data-receiving-item-id="<?= (int) ($item['receiving_item_id'] ?? 0) ?>"
                                            data-po-ref="<?= htmlspecialchars($item['po_ref'] ?? '-') ?>"
                                            data-supplier="<?= htmlspecialchars($item['supplier_name'] ?? '-') ?>"
                                            data-item-code="<?= htmlspecialchars($item['item_code'] ?? '-') ?>"
                                            data-item-description="<?= htmlspecialchars($item['item_description'] ?? '-') ?>"
                                            data-lot-number="<?= htmlspecialchars($item['lot_number'] ?? '-') ?>"
                                            data-received-qty="<?= (float) ($item['received_qty'] ?? 0) ?>"
                                            data-received-date="<?= !empty($item['received_date']) ? date('Y-m-d', strtotime($item['received_date'])) : '' ?>"
                                            data-remarks="<?= htmlspecialchars($item['remarks'] ?? '') ?>">
                                        <i class="bi bi-clipboard-check me-1"></i>Inspect
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="bi bi-clipboard-data fs-2 text-primary"></i>
                </div>
                <h3 class="mb-0"><?= $totalInspection ?? 0 ?></h3>
                <small class="text-muted">Total for Inspection</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="bi bi-check-circle fs-2 text-success"></i>
                </div>
                <h3 class="mb-0"><?= $inspectedCount ?? 0 ?></h3>
                <small class="text-muted">Total Inspected</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="bi bi-hourglass-split fs-2 text-warning"></i>
                </div>
                <h3 class="mb-0"><?= $remainingCount ?? 0 ?></h3>
                <small class="text-muted">Remaining Inspection</small>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap">
        <form method="GET" id="qcFilterForm" class="d-flex gap-2 flex-wrap">
            <input type="hidden" name="controller" value="qc">
            <input type="hidden" name="action" value="index">
            <select name="filter_item" class="form-select form-select-sm filter-select" style="width:180px" onchange="this.form.submit()">
                <option value="">All Items</option>
                <?php foreach (($allItems ?? []) as $i): ?>
                    <option value="<?= htmlspecialchars($i) ?>" <?= ($filterItem ?? '') === $i ? 'selected' : '' ?>><?= htmlspecialchars($i) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_lot" class="form-select form-select-sm filter-select" style="width:160px" onchange="this.form.submit()">
                <option value="">All Lots</option>
                <?php foreach (($allLots ?? []) as $l): ?>
                    <option value="<?= htmlspecialchars($l) ?>" <?= ($filterLot ?? '') === $l ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="?controller=qc" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Clear</a>
    </div>
    <div class="search-box" style="width: 300px;">
        <form method="GET" class="d-flex align-items-center">
            <input type="hidden" name="controller" value="qc">
            <input type="hidden" name="action" value="index">
            <input type="hidden" name="filter_item" value="<?= htmlspecialchars($filterItem ?? '') ?>">
            <input type="hidden" name="filter_lot" value="<?= htmlspecialchars($filterLot ?? '') ?>">
            <i class="bi bi-search"></i>
            <input type="text" name="search" id="searchHistory" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search ?? '') ?>">
        </form>
    </div>
</div>

<!-- Table -->
<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="sortable" data-sort="date">Date <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="item_code">Item Code <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="item">Item <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="sts_ref">STS Ref <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="lot">Lot No. <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="added">Added Lot Qty <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="shift">Shift <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="status">Status <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="user">Updated By <i class="bi bi-chevron-expand"></i></th>
                    <th>QC Remark</th>
                    <th>QA Remark</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="historyTableBody">
                <?php foreach ($history as $h): ?>
                <tr>
                    <td>
                        <?= date('Y-m-d H:i', strtotime($h['date_created'])) ?>
                        <?php if (!empty($h['date_edited'])): ?>
                            <br><small class="text-info" title="Edited by <?= htmlspecialchars($h['edited_by_name'] ?? '') ?>">
                                <i class="bi bi-pencil-square"></i> Edited <?= date('m/d H:i', strtotime($h['date_edited'])) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($h['item_code'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($h['item_description'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($h['sts_ref'])): ?>
                            <strong><?= htmlspecialchars($h['sts_ref']) ?></strong>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($h['lot_number'])): ?>
                            <strong><?= htmlspecialchars($h['lot_number']) ?></strong>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="text-success">+<?= formatQty($h['added_quantity']) ?></span>
                        <?php if (!empty($h['pcs_per_case'])): ?>
                            <br><small class="text-muted" title="PCS per Case">/ <?= htmlspecialchars($h['pcs_per_case']) ?> cs</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($h['shift'])): ?>
                            <small><?= htmlspecialchars($h['shift']) ?></small>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($h['reject_status'])): ?>
                            <?php
                            $statusClass = 'secondary';
                            if ($h['reject_status'] === 'Good') $statusClass = 'success';
                            elseif ($h['reject_status'] === 'Reject') $statusClass = 'danger';
                            elseif ($h['reject_status'] === 'For Rework') $statusClass = 'warning text-dark';
                            ?>
                            <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($h['reject_status']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($h['full_name'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($h['qc_remark'])): ?>
                            <div>
                                <small><?= nl2br(htmlspecialchars($h['qc_remark'])) ?></small>
                                <br><small class="text-muted">
                                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($h['qc_inspector_name'] ?? '') ?>
                                    &middot; <?= date('m/d/Y g:i A', strtotime($h['qc_inspected_at'])) ?>
                                </small>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($h['qa_remark'])): ?>
                            <div>
                                <small><?= nl2br(htmlspecialchars($h['qa_remark'])) ?></small>
                                <br><small class="text-muted">
                                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($h['qa_inspector_name'] ?? '') ?>
                                    &middot; <?= date('m/d/Y g:i A', strtotime($h['qa_inspected_at'])) ?>
                                </small>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($h['qc_remark'])): ?>
                            <button class="btn btn-sm btn-outline-warning" onclick="openEditRemarkModal(<?= $h['history_id'] ?>, '<?= htmlspecialchars(addslashes($h['lot_number'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($h['item_description'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($h['qc_remark'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($h['qc_inspector_name'] ?? ''), ENT_QUOTES) ?>')">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-primary" onclick="openAddRemarkModal(<?= $h['history_id'] ?>, '<?= htmlspecialchars(addslashes($h['lot_number'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($h['item_description'] ?? ''), ENT_QUOTES) ?>')">
                                <i class="bi bi-pencil-square me-1"></i>Add Remark
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($history)): ?>
                <tr><td colspan="13" class="text-center text-muted py-4">No production history yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- QC Remark Modal -->
<div class="modal fade" id="remarkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?controller=qc&action=updateRemark">
                <div class="modal-header">
                    <h5 class="modal-title" id="remarkModalTitle"><i class="bi bi-clipboard-check me-2"></i>QC Inspection Remark</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="history_id" id="remarkHistoryId">
                    <div class="mb-3">
                        <label class="form-label">Lot Number</label>
                        <input type="text" id="remarkLotDisplay" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <input type="text" id="remarkItemDisplay" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Inspected By <span class="text-danger">*</span></label>
                        <input type="text" name="qc_inspector_name" id="remarkInspectorName" class="form-control" placeholder="Enter your name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">QC Remark <span class="text-danger">*</span></label>
                        <textarea name="qc_remark" id="remarkText" class="form-control" rows="3" placeholder="Enter inspection remark..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="remarkSubmitBtn"><i class="bi bi-check-lg me-1"></i>Submit Remark</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- QC Inspection Modal -->
<div class="modal fade" id="qcInspectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="qcInspectionForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>QC Inspection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="receiving_item_id" id="qcReceivingItemId">
                    <input type="hidden" name="received_qty" id="qcReceivedQtyHidden">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">PO Ref</label>
                            <input type="text" id="qcPoRef" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Supplier</label>
                            <input type="text" id="qcSupplier" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Item</label>
                            <input type="text" id="qcItem" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lot / Batch</label>
                            <input type="text" id="qcLot" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Received Qty</label>
                            <input type="number" id="qcReceivedQty" class="form-control" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Passed Qty <span class="text-danger">*</span></label>
                            <input type="number" name="passed_qty" id="qcPassedQty" class="form-control" min="0" step="0.0001" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rejected Qty <span class="text-danger">*</span></label>
                            <input type="number" name="rejected_qty" id="qcRejectedQty" class="form-control" min="0" step="0.0001" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Decision <span class="text-danger">*</span></label>
                            <select name="decision" id="qcDecision" class="form-select filter-select" required>
                                <option value="">Select decision</option>
                                <option value="PASSED">Passed</option>
                                <option value="REJECTED">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Inspector Name <span class="text-danger">*</span></label>
                            <input type="text" name="inspector_name" id="qcInspectorName" class="form-control" value="<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Inspection Date</label>
                        <input type="text" id="qcReceivedDate" class="form-control" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" id="qcRemark" class="form-control" rows="3" placeholder="Optional notes for the QC decision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Submit QC Decision</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<?php $pages = \App\Helpers\Pagination::getPageRange($page, $totalPages); ?>
<nav>
    <ul class="pagination justify-content-center mt-4">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=qc&page=<?= $page - 1 ?>&search=<?= urlencode($search ?? '') ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="?controller=qc&page=<?= $p ?>&search=<?= urlencode($search ?? '') ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=qc&page=<?= $page + 1 ?>&search=<?= urlencode($search ?? '') ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<script>
function openAddRemarkModal(historyId, lotNumber, itemDescription) {
    document.getElementById('remarkHistoryId').value = historyId;
    document.getElementById('remarkLotDisplay').value = lotNumber || '-';
    document.getElementById('remarkItemDisplay').value = itemDescription || '-';
    document.getElementById('remarkInspectorName').value = '';
    document.getElementById('remarkText').value = '';
    document.getElementById('remarkModalTitle').innerHTML = '<i class="bi bi-clipboard-check me-2"></i>QC Inspection Remark';
    document.getElementById('remarkSubmitBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i>Submit Remark';
    new bootstrap.Modal(document.getElementById('remarkModal')).show();
}

function openEditRemarkModal(historyId, lotNumber, itemDescription, existingRemark, inspectorName) {
    document.getElementById('remarkHistoryId').value = historyId;
    document.getElementById('remarkLotDisplay').value = lotNumber || '-';
    document.getElementById('remarkItemDisplay').value = itemDescription || '-';
    document.getElementById('remarkInspectorName').value = inspectorName || '';
    document.getElementById('remarkText').value = existingRemark || '';
    document.getElementById('remarkModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit QC Remark';
    document.getElementById('remarkSubmitBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i>Update Remark';
    new bootstrap.Modal(document.getElementById('remarkModal')).show();
}

var _searchTimer;
document.getElementById('searchHistory').addEventListener('input', function() {
    clearTimeout(_searchTimer);
    var form = this.closest('form');
    _searchTimer = setTimeout(function() { form.submit(); }, 500);
});

(function() {
    var s = document.getElementById('searchHistory');
    if (s && s.value) { s.focus(); s.setSelectionRange(s.value.length, s.value.length); }
})();

document.querySelectorAll('.sortable').forEach(th => {
    th.addEventListener('click', function() {
        const table = this.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const col = this.cellIndex;
        const asc = !this.classList.contains('asc');
        
        table.querySelectorAll('.sortable').forEach(h => {
            h.classList.remove('asc', 'desc');
            h.querySelector('i').className = 'bi bi-chevron-expand';
        });
        
        this.classList.add(asc ? 'asc' : 'desc');
        this.querySelector('i').className = asc ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        
        rows.sort((a, b) => {
            let aVal = a.cells[col].textContent.trim();
            let bVal = b.cells[col].textContent.trim();
            if (!isNaN(aVal) && !isNaN(bVal)) {
                aVal = parseFloat(aVal);
                bVal = parseFloat(bVal);
            }
            return asc ? aVal.localeCompare(bVal, undefined, {numeric: true}) : bVal.localeCompare(aVal, undefined, {numeric: true});
        });
        
        rows.forEach(row => tbody.appendChild(row));
    });
});

function openQcInspectionModal(button) {
    const receivedQty = parseFloat(button.dataset.receivedQty || 0);
    document.getElementById('qcReceivingItemId').value = button.dataset.receivingItemId || '';
    document.getElementById('qcPoRef').value = button.dataset.poRef || '-';
    document.getElementById('qcSupplier').value = button.dataset.supplier || '-';
    document.getElementById('qcItem').value = (button.dataset.itemCode || '-') + ' - ' + (button.dataset.itemDescription || '-');
    document.getElementById('qcLot').value = button.dataset.lotNumber || '-';
    document.getElementById('qcReceivedQty').value = receivedQty.toFixed(4);
    document.getElementById('qcReceivedQtyHidden').value = receivedQty.toFixed(4);
    document.getElementById('qcPassedQty').value = receivedQty.toFixed(4);
    document.getElementById('qcRejectedQty').value = '0';
    document.getElementById('qcDecision').value = 'PASSED';
    document.getElementById('qcRemark').value = button.dataset.remarks || '';
    document.getElementById('qcReceivedDate').value = button.dataset.receivedDate || '';
    new bootstrap.Modal(document.getElementById('qcInspectionModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.inspect-qc-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            openQcInspectionModal(this);
        });
    });

    const form = document.getElementById('qcInspectionForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();

            const submitBtn = form.querySelector('button[type=submit]');
            if (submitBtn && submitBtn.disabled) return;

            const receivedQty = parseFloat(document.getElementById('qcReceivedQty').value || 0);
            const passedQty = parseFloat(document.getElementById('qcPassedQty').value || 0);
            const rejectedQty = parseFloat(document.getElementById('qcRejectedQty').value || 0);
            const decision = document.getElementById('qcDecision').value;

            if (!decision) {
                Dlg.alert('Please choose a QC decision.');
                return;
            }
            if (Math.abs((passedQty + rejectedQty) - receivedQty) > 0.0001) {
                Dlg.alert('Passed Qty + Rejected Qty must equal Received Qty.');
                return;
            }
            if (decision === 'PASSED' && passedQty <= 0) {
                Dlg.alert('Passed quantity must be greater than zero.');
                return;
            }
            if (decision === 'REJECTED' && rejectedQty <= 0) {
                Dlg.alert('Rejected quantity must be greater than zero.');
                return;
            }

            // Prevent accidental double-clicks from recording the inspection twice.
            if (submitBtn) submitBtn.disabled = true;

            const payload = new FormData(form);
            fetch('?controller=qc&action=apiInspect', {
                method: 'POST',
                body: payload,
                credentials: 'same-origin'
            }).then(function(response) {
                return response.json().then(function(data) {
                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Failed to submit QC inspection.');
                    }
                    return data;
                });
            }).then(function() {
                Dlg.alert('QC decision submitted successfully.');
                window.location.reload();
            }).catch(function(error) {
                if (submitBtn) submitBtn.disabled = false;
                Dlg.alert(error.message || 'Unable to submit QC decision.');
            });
        });
    }
});
</script>
<?php require BASE_PATH . 'app/views/shared/table_enhancer.php'; ?>
