<h4><i class="bi bi-clock-history me-2"></i>Production History</h4>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <?php if (($reportsCount ?? 0) > 0): ?>
            <?php if ($filterReports ?? false): ?>
                <a href="?controller=admin&action=productionHistory<?= ($filterItem ?? '') ? '&filter_item=' . urlencode($filterItem) : '' ?><?= ($filterLot ?? '') ? '&filter_lot=' . urlencode($filterLot) : '' ?><?= ($filterDateFrom ?? '') ? '&filter_date_from=' . urlencode($filterDateFrom) : '' ?><?= ($filterDateTo ?? '') ? '&filter_date_to=' . urlencode($filterDateTo) : '' ?><?= ($search ?? '') ? '&search=' . urlencode($search) : '' ?>"
                   class="btn btn-sm btn-warning fw-bold">
                    <i class="bi bi-flag-fill me-1"></i>Show All
                </a>
            <?php else: ?>
                <a href="?controller=admin&action=productionHistory&filter_reports=1<?= ($filterItem ?? '') ? '&filter_item=' . urlencode($filterItem) : '' ?><?= ($filterLot ?? '') ? '&filter_lot=' . urlencode($filterLot) : '' ?><?= ($filterDateFrom ?? '') ? '&filter_date_from=' . urlencode($filterDateFrom) : '' ?><?= ($filterDateTo ?? '') ? '&filter_date_to=' . urlencode($filterDateTo) : '' ?><?= ($search ?? '') ? '&search=' . urlencode($search) : '' ?>"
                   class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-flag-fill me-1"></i>Reports <span class="badge bg-danger ms-1"><?= $reportsCount ?></span>
                </a>
            <?php endif; ?>
        <?php endif; ?>
        <form method="GET" id="historyFilterForm" class="d-flex gap-2 flex-wrap">
            <input type="hidden" name="controller" value="admin">
            <input type="hidden" name="action" value="productionHistory">
            <select name="filter_item" class="form-select form-select-sm filter-select" style="width:170px" onchange="this.form.submit()">
                <option value="">All Items</option>
                <?php foreach (($allItems ?? []) as $i): ?>
                    <option value="<?= htmlspecialchars($i) ?>" <?= ($filterItem ?? '') === $i ? 'selected' : '' ?>><?= htmlspecialchars($i) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_lot" class="form-select form-select-sm filter-select" style="width:140px" onchange="this.form.submit()">
                <option value="">All Lots</option>
                <?php foreach (($allLots ?? []) as $l): ?>
                    <option value="<?= htmlspecialchars($l) ?>" <?= ($filterLot ?? '') === $l ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="filter_date_from" class="form-control form-control-sm" style="width:150px" value="<?= htmlspecialchars($filterDateFrom ?? '') ?>" placeholder="From" onchange="this.form.submit()">
            <input type="date" name="filter_date_to" class="form-control form-control-sm" style="width:150px" value="<?= htmlspecialchars($filterDateTo ?? '') ?>" placeholder="To" onchange="this.form.submit()">
        </form>
        <a href="?controller=admin&action=productionHistory" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Clear</a>
    </div>
    <div class="search-box" style="width: 300px;">
        <form method="GET" class="d-flex align-items-center">
            <input type="hidden" name="controller" value="admin">
            <input type="hidden" name="action" value="productionHistory">
            <input type="hidden" name="filter_item" value="<?= htmlspecialchars($filterItem ?? '') ?>">
            <input type="hidden" name="filter_lot" value="<?= htmlspecialchars($filterLot ?? '') ?>">
            <input type="hidden" name="filter_date_from" value="<?= htmlspecialchars($filterDateFrom ?? '') ?>">
            <input type="hidden" name="filter_date_to" value="<?= htmlspecialchars($filterDateTo ?? '') ?>">
            <?php if ($filterReports ?? false): ?>
            <input type="hidden" name="filter_reports" value="1">
            <?php endif; ?>
            <i class="bi bi-search"></i>
            <input type="text" name="search" id="searchHistory" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search ?? '') ?>">
        </form>
    </div>
</div>

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
                    <th class="sortable" data-sort="prev">Previous Lot Qty <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="added">Added Lot Qty <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="new">New Lot Qty <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="totalpo">Total PO Qty <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="user">Updated By <i class="bi bi-chevron-expand"></i></th>
                    <th>QC Remark</th>
                    <th>QA Remark</th>
                    <th>Report</th>
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
                            <?= htmlspecialchars($h['sts_ref']) ?>
                            <?php if (!empty($h['transfer_source_po'])): ?>
                                <br><small class="text-info" title="Excess transferred from this PO" style="font-style:italic"><i class="bi bi-arrow-left-right me-1"></i>Transferred from <?= htmlspecialchars($h['transfer_source_po']) ?></small>
                            <?php endif; ?>

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
                        <?php if (!empty($h['date_edited']) && !empty($h['old_lot_number'])): ?>
                            <br><small class="text-muted">(old: <?= htmlspecialchars($h['old_lot_number']) ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= intval($h['computed_prev_lot_qty'] ?? $h['previous_quantity'] ?? 0) ?>
                    </td>
                    <td>
                        <span class="text-success">+<?= formatQty($h['added_quantity']) ?></span>
                        <?php if (!empty($h['date_edited']) && $h['old_added_quantity'] !== null): ?>
                            <br><small class="text-muted">(old: +<?= $h['old_added_quantity'] ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= intval($h['computed_new_lot_qty'] ?? $h['new_quantity'] ?? 0) ?></strong></td>
                    <td><?= formatQty($h['computed_po_qty'] ?? 0) ?></td>
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
                        <?php
                        $hasReport = !empty($h['report_id']);
                        $reportPending = $hasReport && $h['report_status'] === 'pending';
                        $reportResolved = $hasReport && $h['report_status'] === 'resolved';
                        $reportType = $h['report_type'] ?? 'lot_number';
                        $typeLabel = $reportType === 'quantity' ? 'Quantity' : 'Lot No.';
                        ?>
                        <?php if ($reportPending): ?>
                            <span style="color:red;font-weight:bold;" title="<?= htmlspecialchars($h['report_reason'] ?? '') ?>">
                                <?= $typeLabel ?>: <?= htmlspecialchars($h['report_reason'] ?? '') ?>
                            </span>
                        <?php elseif ($reportResolved): ?>
                            <span style="color:#e6a800;font-weight:bold;" title="Resolved">
                                <?= $typeLabel ?>: <?= htmlspecialchars($h['report_reason'] ?? '') ?> &rarr; Resolved
                            </span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= $h['history_id'] ?>, '<?= htmlspecialchars(addslashes($h['lot_number'] ?? ''), ENT_QUOTES) ?>', <?= $h['added_quantity'] ?>, <?= (int) ($h['poi_id'] ?? 0) ?>, '<?= htmlspecialchars(addslashes($h['item_code'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($h['item_description'] ?? ''), ENT_QUOTES) ?>')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="?controller=admin&action=deleteProductionHistory&id=<?= $h['history_id'] ?>"
                           class="btn btn-sm btn-outline-danger"
                           data-confirm="Delete this production entry and roll back its quantity?"
                           title="Delete this entry and undo its impact">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($history)): ?>
                <tr><td colspan="15" class="text-center text-muted py-4">No production history yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Record Modal -->
<div class="modal fade" id="editRecordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Production Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editHistoryId">
                <input type="hidden" id="editCurrentPoi">
                <div class="mb-3">
                    <label class="form-label">Current Item</label>
                    <input type="text" id="editCurrentItem" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Item (Code / Description)</label>
                    <select id="editNewItem" class="form-select">
                        <option value="">No change — keep current item</option>
                        <?php foreach (($editItemOptions ?? []) as $opt): ?>
                        <option value="<?= (int) $opt['poi_id'] ?>">
                            <?= htmlspecialchars($opt['item_code'] . ' — ' . $opt['item_description']) ?>
                            (PO <?= htmlspecialchars($opt['po_number'] ?: $opt['po_id']) ?> — <?= htmlspecialchars($opt['customer_name']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Changing the item moves the produced quantity (and the lot) to that item's PO line — it becomes deliverable there immediately. Stock on Hand is updated accordingly.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Current Lot Number</label>
                    <input type="text" id="editCurrentLot" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Lot Number <span class="text-danger">*</span></label>
                    <input type="text" id="editNewLot" class="form-control" placeholder="Enter correct lot number" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Current Quantity</label>
                    <input type="text" id="editCurrentQty" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Quantity <span class="text-danger">*</span></label>
                    <input type="number" id="editNewQty" class="form-control" min="1" required>
                </div>
                <div class="alert alert-info py-2 mb-0">
                    <small><i class="bi bi-info-circle me-1"></i>Changing quantity will update production progress. Changing the item also re-syncs Deliveries availability.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="previewEditRecord()"><i class="bi bi-eye me-1"></i>Preview</button>
            </div>
        </div>
    </div>
</div>

<!-- Production Edit Preview Modal -->
<div class="modal fade" id="editPreviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Confirm Production Edit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Please review the changes before saving.</p>
                <table class="table table-bordered mb-0">
                    <tr><th style="width:40%">History ID</th><td id="prevHistId"></td></tr>
                    <tr><th>Current Item</th><td id="prevCurrentItem"></td></tr>
                    <tr><th>New Item</th><td id="prevNewItem"></td></tr>
                    <tr><th>Previous Lot</th><td id="prevOldLot"></td></tr>
                    <tr><th>New Lot</th><td id="prevNewLot"></td></tr>
                    <tr><th>Previous Lot Qty</th><td id="prevOldQty"></td></tr>
                     <tr><th>New Lot Qty</th><td id="prevNewQty"></td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmEditBtn"><i class="bi bi-check-lg me-1"></i>Confirm & Save</button>
            </div>
        </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<?php
$pages = \App\Helpers\Pagination::getPageRange($page, $totalPages);
$pageParams = 'controller=admin&action=productionHistory';
foreach (['search', 'filter_item', 'filter_lot', 'filter_date_from', 'filter_date_to', 'filter_reports'] as $p) {
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

<script>
function openEditModal(historyId, currentLot, currentQty, currentPoi, itemCode, itemDesc) {
    document.getElementById('editHistoryId').value = historyId;
    document.getElementById('editCurrentLot').value = currentLot;
    document.getElementById('editNewLot').value = currentLot;
    document.getElementById('editCurrentQty').value = currentQty;
    document.getElementById('editNewQty').value = currentQty;
    document.getElementById('editCurrentPoi').value = currentPoi || 0;
    document.getElementById('editCurrentItem').value = (itemCode || '') + (itemDesc ? ' — ' + itemDesc : '');
    document.getElementById('editNewItem').value = '';
    new bootstrap.Modal(document.getElementById('editRecordModal')).show();
}

function previewEditRecord() {
    var historyId = document.getElementById('editHistoryId').value;
    var oldLot = document.getElementById('editCurrentLot').value;
    var newLot = document.getElementById('editNewLot').value.trim();
    var oldQty = document.getElementById('editCurrentQty').value;
    var newQty = parseInt(document.getElementById('editNewQty').value);
    var currentItem = document.getElementById('editCurrentItem').value;
    var newItemSelect = document.getElementById('editNewItem');
    var newPoi = newItemSelect.value;

    if (!newLot) { Dlg.alert('Please enter a lot number.'); return; }
    if (!newQty || newQty <= 0) { Dlg.alert('Please enter a valid quantity.'); return; }
    if (newPoi && String(newPoi) === String(document.getElementById('editCurrentPoi').value)) {
        newPoi = ''; // same item — treat as no change
    }

    document.getElementById('prevHistId').textContent = historyId;
    document.getElementById('prevCurrentItem').textContent = currentItem || '-';
    if (newPoi) {
        var label = newItemSelect.options[newItemSelect.selectedIndex].textContent;
        document.getElementById('prevNewItem').innerHTML = '<span class="text-primary fw-bold">' + label + '</span>' +
            '<div class="small text-muted">Lot + produced qty move to this PO line; SOH moves from the old item to the new item.</div>';
    } else {
        document.getElementById('prevNewItem').textContent = '-';
    }
    document.getElementById('prevOldLot').textContent = oldLot;
    document.getElementById('prevNewLot').textContent = newLot;
    document.getElementById('prevOldQty').textContent = oldQty;
    document.getElementById('prevNewQty').textContent = newQty;

    bootstrap.Modal.getInstance(document.getElementById('editRecordModal')).hide();
    new bootstrap.Modal(document.getElementById('editPreviewModal')).show();
}

function saveEditRecord() {
    var historyId = document.getElementById('editHistoryId').value;
    var newLot = document.getElementById('editNewLot').value.trim();
    var newQty = parseInt(document.getElementById('editNewQty').value);
    var newPoi = document.getElementById('editNewItem').value || '';

    fetch('?controller=admin&action=editHistoryRecord', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'history_id=' + encodeURIComponent(historyId) +
              '&new_lot_number=' + encodeURIComponent(newLot) +
              '&new_added_quantity=' + encodeURIComponent(newQty) +
              '&new_poi_id=' + encodeURIComponent(newPoi)
    }).then(function(r) { return r.json(); }).then(function(data) {
        bootstrap.Modal.getInstance(document.getElementById('editPreviewModal')).hide();
        if (data.success) {
            location.reload();
        } else {
            Dlg.alert(data.error || data.message || 'Failed to update record.');
        }
    }).catch(function() { Dlg.alert('An error occurred.'); });
}

document.getElementById('confirmEditBtn').addEventListener('click', saveEditRecord);

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
</script>
