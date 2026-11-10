<h4><i class="bi bi-stack me-2"></i>Lots</h4>
<p class="text-muted mb-4">Master list of all production lots and their Open / Closed status</p>

<div class="card data-card mb-4">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex gap-2 flex-wrap">
                <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="controller" value="production">
                    <input type="hidden" name="action" value="lots">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search item, job order or lot..." value="<?= htmlspecialchars($search ?? '') ?>" style="width: 300px;">
                    <select name="status" class="form-select form-select-sm filter-select" style="width: 140px" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="open" <?= ($status ?? '') === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="closed" <?= ($status ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                    <button class="btn btn-sm btn-primary" type="submit">Search</button>
                </form>
                <a href="?controller=production&action=lots" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Clear</a>
            </div>
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>Lots auto-close once total produced reaches the LMR quantity; the switch overrides manually.
            </small>
        </div>
    </div>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Item Code</th>
                    <th>Item</th>
                    <th>Lot Number</th>
                    <th>Job Order</th>
                    <th class="text-end">Target Qty</th>
                    <th class="text-end">Total Produced</th>
                    <th class="text-end">Delivered</th>
                    <th class="text-end">Available Balance</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lots as $lot): ?>
                <tr data-item-id="<?= (int) $lot['item_id'] ?>" data-lot="<?= htmlspecialchars($lot['lot_number'] ?? '') ?>">
                    <td><strong><?= htmlspecialchars($lot['item_code'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($lot['item_description'] ?? '-') ?></td>
                    <td><strong><?= htmlspecialchars($lot['lot_number'] ?? '-') ?></strong></td>
                    <td><?= !empty($lot['job_order']) ? htmlspecialchars($lot['job_order']) : '<span class="text-muted">-</span>' ?></td>
                    <td class="text-end"><?= $lot['target_qty'] !== null ? number_format((float) $lot['target_qty'], 2) : '<span class="text-muted">-</span>' ?></td>
                    <td class="text-end"><?= formatQty($lot['total_produced']) ?></td>
                    <td class="text-end"><?= formatQty($lot['quantity_delivered'] ?? 0) ?></td>
                    <td class="text-end fw-semibold"><?= formatQty($lot['available_balance'] ?? 0) ?></td>
                    <td class="text-center">
                        <span class="badge bg-success lot-open-badge">Open</span>
                        <span class="badge bg-danger lot-closed-badge <?= empty($lot['lot_closed']) ? 'd-none' : '' ?>">Closed<?= !empty($lot['manual_closed']) ? ' (manual)' : '' ?></span>
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block m-0">
                            <input type="checkbox" class="form-check-input lot-close-switch" <?= empty($lot['lot_closed']) ? '' : 'checked' ?>
                                   title="<?= empty($lot['lot_closed']) ? 'Closed lot — click to switch to Open' : 'Open lot — click to switch to Closed' ?>"
                                   onchange="toggleLotStatusRow(this)">
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lots)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No LMR lots yet — production lots appear here only when linked to a Released LMR with a Job Order</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleLotStatusRow(chk) {
    var row = chk.closest('tr');
    var itemId = row.getAttribute('data-item-id');
    var lot = row.getAttribute('data-lot');
    chk.disabled = true;

    fetch('?controller=production&action=toggleLotClosed', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: 'item_id=' + encodeURIComponent(itemId) + '&lot_number=' + encodeURIComponent(lot) + '&closed=' + (chk.checked ? 1 : 0)
    })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            chk.disabled = false;
            if (data && data.success) {
                var openBadge = row.querySelector('.lot-open-badge');
                var closedBadge = row.querySelector('.lot-closed-badge');
                if (chk.checked) {
                    if (openBadge) openBadge.classList.add('d-none');
                    if (closedBadge) closedBadge.classList.remove('d-none');
                } else {
                    if (openBadge) openBadge.classList.remove('d-none');
                    if (closedBadge) closedBadge.classList.add('d-none');
                }
                chk.title = chk.checked ? 'Closed lot — click to switch to Open' : 'Open lot — click to switch to Closed';
                return;
            }
            chk.checked = !chk.checked;
            Dlg.alert((data && data.error) || 'Failed to update lot status.');
        })
        .catch(function() {
            chk.checked = !chk.checked;
            chk.disabled = false;
            Dlg.alert('Network error. Lot status not updated.');
        });
}

var _lotsSearchTimer;
var lotsSearch = document.querySelector('input[name="search"]');
if (lotsSearch) {
    lotsSearch.addEventListener('input', function() {
        clearTimeout(_lotsSearchTimer);
        var form = this.closest('form');
        _lotsSearchTimer = setTimeout(function() { form.submit(); }, 500);
    });
}
</script>
