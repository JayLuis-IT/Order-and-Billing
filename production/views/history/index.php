<h4><i class="bi bi-clock-history me-2"></i>Production History</h4>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap">
        <form method="GET" id="historyFilterForm" class="d-flex gap-2 flex-wrap">
            <input type="hidden" name="controller" value="production">
            <input type="hidden" name="action" value="history">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search ?? '') ?>">
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
        <a href="?controller=production&action=history" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Clear</a>
    </div>
    <div class="search-box" style="width: 300px;">
        <form method="GET" class="d-flex align-items-center">
            <input type="hidden" name="controller" value="production">
            <input type="hidden" name="action" value="history">
            <input type="hidden" name="filter_item" value="<?= htmlspecialchars($filterItem ?? '') ?>">
            <input type="hidden" name="filter_lot" value="<?= htmlspecialchars($filterLot ?? '') ?>">
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
                    <th class="sortable" data-sort="shift">Shift <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="status">Status <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="user">Updated By <i class="bi bi-chevron-expand"></i></th>
                    <th class="action-col">Report</th>
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
                            <a href="?controller=production&action=printSTS&sts_ref=<?= urlencode($h['sts_ref']) ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-1" title="Print STS"><i class="bi bi-printer"></i></a>
                            <?php if (!empty($h['transfer_source_po'])): ?>
                                <br><small class="text-info" title="Excess transferred from this PO" style="font-style:italic"><i class="bi bi-arrow-left-right me-1"></i>Transferred from <?= htmlspecialchars($h['transfer_source_po']) ?></small>
                            <?php endif; ?>

                            <?php if (!empty($h['sts_remarks'])): ?>
                                <br><small class="text-muted" title="Remarks" style="font-style:italic"><?= htmlspecialchars($h['sts_remarks']) ?></small>
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
                        <?php if (!empty($h['pcs_per_case'])): ?>
                            <br><small class="text-muted" title="PCS per Case">/ <?= htmlspecialchars($h['pcs_per_case']) ?> cs</small>
                        <?php endif; ?>
                        <?php if (!empty($h['date_edited']) && $h['old_added_quantity'] !== null): ?>
                            <br><small class="text-muted">(old: +<?= $h['old_added_quantity'] ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= intval($h['computed_new_lot_qty'] ?? $h['new_quantity'] ?? 0) ?></strong></td>
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
                    <td class="action-col">
                        <?php
                        $canUndo = !empty($h['undo_available']) && (int)($h['user_id'] ?? 0) === (int)($_SESSION['user_id'] ?? 0);
                        ?>
                        <?php if ($canUndo): ?>
                            <div id="undoAction<?= (int)$h['history_id'] ?>">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="undoProductionEntry(<?= (int)$h['history_id'] ?>, this)" title="Undo this production entry">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                    <span class="undo-countdown" data-deadline="<?= (int)($h['undo_deadline'] ?? 0) ?>"></span>
                                </button>
                            </div>
                            <div id="reportAction<?= (int)$h['history_id'] ?>" class="d-none">
                                <?php if (!empty($h['report_id']) && $h['report_status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark" title="<?= htmlspecialchars($h['report_reason'] ?? '') ?>">Reported</span>
                                <?php elseif (!empty($h['lot_number'])): ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="openReportModal(<?= $h['history_id'] ?>, '<?= htmlspecialchars(addslashes($h['lot_number'] ?? ''), ENT_QUOTES) ?>', <?= $h['added_quantity'] ?>)">
                                        <i class="bi bi-flag"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php elseif (!empty($h['report_id']) && $h['report_status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark" title="<?= htmlspecialchars($h['report_reason'] ?? '') ?>">Reported</span>
                        <?php elseif (!empty($h['lot_number'])): ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="openReportModal(<?= $h['history_id'] ?>, '<?= htmlspecialchars(addslashes($h['lot_number'] ?? ''), ENT_QUOTES) ?>', <?= $h['added_quantity'] ?>)">
                                <i class="bi bi-flag"></i>
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

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?controller=production&action=reportHistory">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportModalTitle"><i class="bi bi-flag me-2"></i>Report Issue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="history_id" id="reportHistoryId">
                    <div class="mb-3">
                        <label class="form-label">Report Type <span class="text-danger">*</span></label>
                        <select name="report_type" id="reportType" class="form-select filter-select" required onchange="updateReportTitle()">
                            <option value="lot_number">Lot No.</option>
                            <option value="quantity">Quantity</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" id="reportDisplayLabel">Current Lot Number</label>
                        <input type="text" id="reportDisplayValue" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Report <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" id="reportReason" placeholder="Explain the issue..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-flag me-1"></i>Submit Report</button>
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
            <a class="page-link" href="?controller=production&action=history&page=<?= $page - 1 ?>&search=<?= urlencode($search ?? '') ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="?controller=production&action=history&page=<?= $p ?>&search=<?= urlencode($search ?? '') ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=production&action=history&page=<?= $page + 1 ?>&search=<?= urlencode($search ?? '') ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<script>
function openReportModal(historyId, lotNumber, addedQty) {
    document.getElementById('reportHistoryId').value = historyId;
    document.getElementById('reportDisplayValue').value = lotNumber;
    document.getElementById('reportType').value = 'lot_number';
    document.getElementById('reportReason').value = '';
    updateReportTitle();
    new bootstrap.Modal(document.getElementById('reportModal')).show();
}

function undoProductionEntry(historyId, button) {
    Dlg.confirm('Undo this entry? The quantity will be deducted from the production lot.').then(function(ok) {
        if (!ok) return;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        fetch('?controller=production&action=undoLastFgEntry', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ history_ids: [historyId] })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var row = button.closest('tr');
                if (row) row.remove();
                return;
            }
            Dlg.alert(data.error || 'Unable to undo this entry.');
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i><span class="undo-countdown"></span>';
        })
        .catch(function() {
            Dlg.alert('Network error. Please try again.');
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i><span class="undo-countdown"></span>';
        });
    });
}

document.querySelectorAll('.undo-countdown').forEach(function(countdown) {
    var action = countdown.closest('[id^="undoAction"]');
    var report = action ? document.getElementById(action.id.replace('undoAction', 'reportAction')) : null;
    var deadline = parseInt(countdown.dataset.deadline || '0', 10);
    var update = function() {
        var remaining = deadline - Math.floor(Date.now() / 1000);
        if (remaining <= 0) {
            if (action) action.classList.add('d-none');
            if (report) report.classList.remove('d-none');
            return false;
        }
        countdown.textContent = ' ' + Math.floor(remaining / 60) + ':' + String(remaining % 60).padStart(2, '0');
        return true;
    };
    if (update()) {
        var timer = setInterval(function() {
            if (!update()) clearInterval(timer);
        }, 1000);
    }
});

function updateReportTitle() {
    const type = document.getElementById('reportType').value;
    const title = document.getElementById('reportModalTitle');
    const label = document.getElementById('reportDisplayLabel');
    const display = document.getElementById('reportDisplayValue');
    const placeholder = document.getElementById('reportReason');
    if (type === 'quantity') {
        title.innerHTML = '<i class="bi bi-flag me-2"></i>Report Wrong Quantity';
        label.textContent = 'Current Quantity';
        placeholder.placeholder = 'Explain the quantity issue...';
    } else {
        title.innerHTML = '<i class="bi bi-flag me-2"></i>Report Wrong Lot Number';
        label.textContent = 'Current Lot Number';
        placeholder.placeholder = 'Explain why this lot number is wrong...';
    }
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
</script>
