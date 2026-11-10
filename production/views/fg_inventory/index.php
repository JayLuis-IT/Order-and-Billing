<h4><i class="bi bi-box-seam me-2"></i>FG Inventory</h4>
<p class="text-muted mb-3">Item-centric view of all produced finished goods and available stock.</p>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap">
    </div>
    <div class="search-box" style="width: 300px;">
        <form method="GET" class="d-flex align-items-center">
            <input type="hidden" name="controller" value="production">
            <input type="hidden" name="action" value="fgInventory">
            <i class="bi bi-search"></i>
            <input type="text" name="search" id="searchItem" class="form-control" placeholder="Search item name or code..." value="<?= htmlspecialchars($search ?? '') ?>">
        </form>
    </div>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="sortable" data-sort="item_code">Item Code <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="item_description">Item Description <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable text-end" data-sort="produced">Total Produced <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable text-end" data-sort="delivered">Total Delivered <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable text-end" data-sort="available">Available Stock <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable text-end" data-sort="lots">Total Lots <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable text-end" data-sort="cases">Total Cases <i class="bi bi-chevron-expand"></i></th>
                    <th class="text-center action-col">Actions</th>
                </tr>
            </thead>
            <tbody id="fgTableBody">
                <?php foreach ($inventory as $item):
                    $produced = intval($item['total_produced']);
                    $delivered = intval($item['total_delivered']);
                    $available = intval($item['available_stock']);
                    $totalLots = intval($item['total_lots']);
                    $conv = $item['uom_conversion'] ?? null;
                    $cases = $conv ? floor($available / $conv) : null;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($item['item_code']) ?></strong></td>
                    <td><?= htmlspecialchars($item['item_description']) ?></td>
                    <td class="text-end"><?= number_format($produced) ?></td>
                    <td class="text-end"><?= number_format($delivered) ?></td>
                    <td class="text-end">
                        <?php if ($available > 0): ?>
                            <span class="fw-bold"><?= number_format($available) ?></span>
                        <?php else: ?>
                            <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= $totalLots ?></td>
                    <td class="text-end"><?= $cases !== null ? number_format($cases) . ' cs' : '&ndash;' ?></td>
                    <td class="text-center action-col">
                        <button type="button" class="btn btn-sm btn-outline-primary view-lots-btn" data-item-id="<?= $item['item_id'] ?>" data-item-name="<?= htmlspecialchars($item['item_code'] . ' - ' . $item['item_description']) ?>" title="View Lots">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($inventory)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No finished goods in inventory</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<?php $pages = \App\Helpers\Pagination::getPageRange($page, $totalPages); ?>
<nav>
    <ul class="pagination justify-content-center mt-4">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=production&action=fgInventory&page=<?= $page - 1 ?>&search=<?= urlencode($search ?? '') ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="?controller=production&action=fgInventory&page=<?= $p ?>&search=<?= urlencode($search ?? '') ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=production&action=fgInventory&page=<?= $page + 1 ?>&search=<?= urlencode($search ?? '') ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<!-- View Lots Modal -->
<div class="modal fade" id="viewLotsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Lots &ndash; <span id="lotItemName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Lot Number</th>
                                <th class="text-center">PCS/Case</th>
                                <th class="text-end">Qty Produced</th>
                                <th class="text-end">Delivered</th>
                                <th class="text-end">Available Balance</th>
                                <th>Date Created</th>
                                <th>Created By</th>
                                <th class="text-center">Lot Status</th>
                            </tr>
                        </thead>
                        <tbody id="lotTableBody">
                            <tr><td colspan="8" class="text-center text-muted py-3">Loading...</td></tr>
                        </tbody>
                        <tfoot id="lotTableFoot" style="display:none;">
                            <tr class="table-light fw-bold">
                                <td>Total</td>
                                <td></td>
                                <td class="text-end" id="lotTotalProduced">0</td>
                                <td class="text-end" id="lotTotalDelivered">0</td>
                                <td class="text-end" id="lotTotalAvailable">0</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-lots-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var itemId = this.getAttribute('data-item-id');
            var itemName = this.getAttribute('data-item-name');
            document.getElementById('lotItemName').textContent = itemName;
            var tbody = document.getElementById('lotTableBody');
            var foot = document.getElementById('lotTableFoot');
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Loading...</td></tr>';
            foot.style.display = 'none';
            window._lotItemId = itemId;

            fetch('?controller=production&action=getLotsForInventory&item_id=' + itemId)
                .then(function(r) { return r.json(); })
                .then(function(lots) {
                    tbody.innerHTML = '';
                    if (!lots || lots.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No lots found</td></tr>';
                        return;
                    }
                    var totalProduced = 0, totalDelivered = 0, totalAvailable = 0;
                    lots.forEach(function(lot) {
                        var produced = parseInt(lot.quantity_produced) || 0;
                        var delivered = parseInt(lot.quantity_delivered) || 0;
                        var available = parseInt(lot.available_balance) || 0;
                        totalProduced += produced;
                        totalDelivered += delivered;
                        totalAvailable += available;
                        var date = lot.lot_date || lot.date_created || '';
                        if (date.length > 10) date = date.substring(0, 10);

                        var statusHtml;
                        if (lot.lmr_target_qty !== null && lot.lmr_target_qty !== undefined && Number(lot.lmr_target_qty) > 0) {
                            var prodTarget = (lot.lmr_produced_qty || 0).toLocaleString() + '/' + Number(lot.lmr_target_qty).toLocaleString();
                            var checked = lot.lot_closed ? ' checked' : '';
                            var title = lot.manual_closed === null || lot.manual_closed === undefined
                                ? 'Auto-closed because produced reached the LMR target (' + prodTarget + '). Toggle to re-open.'
                                : (lot.manual_closed === 1 ? 'Manually closed. Toggle to re-open.' : 'Manually re-opened. Toggle to close.');
                            statusHtml = '<div class="form-check form-switch d-inline-block">' +
                                '<input type="checkbox" class="form-check-input lot-close-switch" data-lot="' +
                                encodeURIComponent(lot.lot_number || '') + '"' + checked +
                                ' title="' + title + '">' +
                                '<span class="lot-target-info small text-muted ms-1">' + prodTarget + '</span>' +
                                (lot.manual_closed === 1 ? ' <span class="badge bg-dark" title="Manually closed">manual</span>' : '') +
                                '</div>';
                        } else {
                            var checkedPlain = lot.lot_closed ? ' checked' : '';
                            statusHtml = '<div class="form-check form-switch d-inline-block">' +
                                '<input type="checkbox" class="form-check-input lot-close-switch" data-lot="' +
                                encodeURIComponent(lot.lot_number || '') + '"' + checkedPlain +
                                ' title="Lot closed by operator — toggle to change">' +
                                '</div>';
                        }

                        tbody.innerHTML += '<tr>' +
                            '<td><strong>' + (lot.lot_number || '-') + '</strong></td>' +
                            '<td class="text-center">' + (lot.pcs_per_case || '&ndash;') + '</td>' +
                            '<td class="text-end">' + produced.toLocaleString() + '</td>' +
                            '<td class="text-end">' + delivered.toLocaleString() + '</td>' +
                            '<td class="text-end">' + available.toLocaleString() + '</td>' +
                            '<td>' + date + '</td>' +
                            '<td>' + (lot.created_by_name || '&ndash;') + '</td>' +
                            '<td class="text-center">' + statusHtml + '</td>' +
                            '</tr>';
                    });
                    document.getElementById('lotTotalProduced').textContent = totalProduced.toLocaleString();
                    document.getElementById('lotTotalDelivered').textContent = totalDelivered.toLocaleString();
                    document.getElementById('lotTotalAvailable').textContent = totalAvailable.toLocaleString();
                    foot.style.display = '';
                    var modal = new bootstrap.Modal(document.getElementById('viewLotsModal'));
                    modal.show();
                })
                .catch(function() {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">Failed to load lots</td></tr>';
                });
        });
    });

    document.addEventListener('change', function(e) {
        if (!e.target.matches('.lot-close-switch')) return;
        var chk = e.target;
        var lot = decodeURIComponent(chk.getAttribute('data-lot') || '');
        var itemId = window._lotItemId;
        chk.disabled = true;
        fetch('?controller=production&action=toggleLotClosed', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: 'item_id=' + encodeURIComponent(itemId) + '&lot_number=' + encodeURIComponent(lot) + '&closed=' + (chk.checked ? 1 : 0)
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.success) {
                    chk.disabled = false;
                    var tr = chk.closest('tr');
                    if (tr) {
                        var lotCell = tr.querySelector('td');
                        lotCell.classList.toggle('text-muted', chk.checked);
                        var label = tr.querySelector('.badge');
                        if (label) { if (!chk.checked) { label.remove(); } }
                        if (chk.checked && !tr.querySelector('.badge.bg-dark')) {
                            lotCell.innerHTML += ' <span class="badge bg-dark" title="Manually closed">manual</span>';
                        }
                    }
                    return;
                }
                chk.checked = !chk.checked;
                chk.disabled = false;
                Dlg.alert((data && data.error) || 'Failed to update lot status.');
            })
            .catch(function() {
                chk.checked = !chk.checked;
                chk.disabled = false;
                Dlg.alert('Network error. Lot status not updated.');
            });
    });

    var _searchTimer;
    var searchInput = document.getElementById('searchItem');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(_searchTimer);
            var form = this.closest('form');
            _searchTimer = setTimeout(function() { form.submit(); }, 500);
        });
    }

    document.querySelectorAll('.sortable').forEach(function(th) {
        th.addEventListener('click', function() {
            var table = this.closest('table');
            var tbody = table.querySelector('tbody');
            var rows = Array.from(tbody.querySelectorAll('tr'));
            var col = this.cellIndex;
            var asc = !this.classList.contains('asc');

            table.querySelectorAll('.sortable').forEach(function(h) {
                h.classList.remove('asc', 'desc');
                h.querySelector('i').className = 'bi bi-chevron-expand';
            });

            this.classList.add(asc ? 'asc' : 'desc');
            this.querySelector('i').className = asc ? 'bi bi-chevron-up' : 'bi bi-chevron-down';

            rows.sort(function(a, b) {
                var aVal = a.cells[col].textContent.trim();
                var bVal = b.cells[col].textContent.trim();
                if (!isNaN(aVal) && !isNaN(bVal) && aVal !== '' && bVal !== '') {
                    aVal = parseFloat(aVal.replace(/,/g, ''));
                    bVal = parseFloat(bVal.replace(/,/g, ''));
                    return asc ? aVal - bVal : bVal - aVal;
                }
                return asc ? aVal.localeCompare(bVal, undefined, {numeric: true}) : bVal.localeCompare(aVal, undefined, {numeric: true});
            });

            rows.forEach(function(row) { tbody.appendChild(row); });
        });
    });
});
</script>
