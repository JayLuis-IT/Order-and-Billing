<h4><i class="bi bi-cart3 me-2"></i>Customer PO</h4>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap">
        <select id="filterCustomer" class="form-select form-select-sm filter-select" style="width:200px">
            <option value="">All Customers</option>
        </select>
        <select id="filterItem" class="form-select form-select-sm filter-select" style="width:200px">
            <option value="">All Items</option>
        </select>
        <input type="date" id="filterDate" class="form-control form-control-sm" style="width:160px" title="Filter by PO Date">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearFilters"><i class="bi bi-x-circle me-1"></i>Clear</button>
    </div>
    <div class="search-box" style="width: 300px;">
        <form method="GET" class="d-flex align-items-center">
            <input type="hidden" name="controller" value="production">
            <input type="hidden" name="action" value="purchaseOrders">
            <i class="bi bi-search"></i>
            <input type="text" name="search" id="searchPO" class="form-control" placeholder="Search PO..." value="<?= htmlspecialchars($search ?? '') ?>">
        </form>
    </div>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="sortable" data-sort="po_number">PO Number <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="po_date">PO Date <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="customer">Customer <i class="bi bi-chevron-expand"></i></th>
                    <th>Item</th>
                    <th class="sortable" data-sort="progress">Produced PO QTY <i class="bi bi-chevron-expand"></i></th>
                    <th class="sortable" data-sort="delivered">Delivered PO QTY <i class="bi bi-chevron-expand"></i></th>
                    <th class="text-center action-col">Actions</th>
                </tr>
            </thead>
            <tbody id="poTableBody">
                <?php foreach ($purchase_orders as $po):
                    $items = $po_items_map[$po['po_id']] ?? [];
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($po['customer_po_number']) ?></strong></td>
                    <td><?= date('Y-m-d', strtotime($po['customer_po_date'])) ?></td>
                    <td><?= htmlspecialchars($po['customer_name'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $idx => $item): ?>
                                <?= $idx > 0 ? '<hr class="my-1 border-secondary">' : '' ?>
                                <div class="d-flex align-items-center" style="min-height: 20px;">
                                    <small><?= htmlspecialchars($item['item_description'] ?? '-') ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <small class="text-muted">-</small>
                        <?php endif; ?>
                    </td>
<td>
    <?php if (!empty($items)): ?>
        <?php foreach ($items as $idx => $item):
            $qty = $item['quantity'] ?? 0;
            $itemProduced = $item['produced_quantity'] ?? 0;
            $itemPercent = $qty > 0 ? round(($itemProduced / $qty) * 100) : 0;
        ?>
            <?= $idx > 0 ? '<hr class="my-1 border-secondary">' : '' ?>
            <div class="d-flex align-items-center flex-wrap gap-1" style="min-height: 20px;">
                <div class="progress flex-grow-1 me-2" style="height: 12px; width: 50px;">
                    <div class="progress-bar <?= $itemPercent >= 100 ? 'bg-success' : 'bg-warning' ?>" style="width: <?= min($itemPercent, 100) ?>%"></div>
                </div>
                <small class="text-muted text-nowrap"><?= $itemProduced ?>/<?= $qty ?> pcs</small>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <small class="text-muted">-</small>
    <?php endif; ?>
</td>
<td>
    <?php if (!empty($items)): ?>
        <?php foreach ($items as $idx => $item):
            $itemQty = $item['quantity'] ?? 0;
            $itemDelivered = $item['delivered_quantity'] ?? 0;
            $itemRemaining = max(0, $itemQty - $itemDelivered);
        ?>
            <?= $idx > 0 ? '<hr class="my-1 border-secondary">' : '' ?>
            <?php $conv = $item['uom_conversion'] ?? null; ?>
            <small class="text-nowrap"><?= $itemDelivered ?>/<?= $itemQty ?> pcs, <?= $conv ? floor($itemDelivered / $conv) . '/' . floor($itemQty / $conv) . ' cs' : '—/—' ?></small>
        <?php endforeach; ?>
    <?php else: ?>
        <small class="text-muted">-</small>
    <?php endif; ?>
</td>
                    <td class="text-center action-col">
                        <button type="button" class="btn btn-sm btn-primary view-po-btn" data-po-id="<?= $po['po_id'] ?>">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($purchase_orders)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No purchase orders found</td></tr>
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
            <a class="page-link" href="?controller=production&action=purchaseOrders&page=<?= $page - 1 ?>&search=<?= urlencode($search ?? '') ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="?controller=production&action=purchaseOrders&page=<?= $p ?>&search=<?= urlencode($search ?? '') ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=production&action=purchaseOrders&page=<?= $page + 1 ?>&search=<?= urlencode($search ?? '') ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<div class="modal fade" id="viewPOModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>PO Details - <span id="viewPONumber"></span></h5>
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
                                <th>UOM</th>
                                <th>Quantity</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody id="viewPOItems">
                        </tbody>
                    </table>
                </div>

                <hr>
                <h5 class="mb-3">Lot Tracker</h5>
                <div class="row mb-3 align-items-center">
                    <div class="col-md-4">
                        <label for="lotTrackerItem" class="form-label fw-bold">Select Item</label>
                        <select id="lotTrackerItem" class="form-select form-select-sm filter-select">
                            <option value="">-- Select Item --</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="lotTrackerTable">
                        <thead>
                            <tr>
                                <th>Lot No.</th>
                                <th class="text-end">Qty Delivered</th>
                            </tr>
                        </thead>
                        <tbody id="lotTrackerBody">
                            <tr><td colspan="2" class="text-center text-muted py-3">Select an item to view lots</td></tr>
                        </tbody>
                        <tfoot id="lotTrackerFoot" style="display:none;">
                            <tr class="table-light fw-bold">
                                <td>Total</td>
                                <td class="text-end" id="lotTrackerTotal">0</td>
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
    console.log('Production PO page loaded');
    
    document.querySelectorAll('.view-po-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const poId = this.getAttribute('data-po-id');
            console.log('View button clicked, poId:', poId);
            fetch('?controller=production&action=getPODetails&id=' + poId, { credentials: 'same-origin' })
                .then(function(response) {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error('HTTP error ' + response.status + ': ' + text);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    const po = data.po;
                    const items = data.po_items;
                    
                    document.getElementById('viewPONumber').textContent = po.customer_po_number || '-';
                    document.getElementById('viewCustomerCode').textContent = po.customer_code || '-';
                    document.getElementById('viewCustomerName').textContent = po.customer_name || '-';
                    document.getElementById('viewCustomerTin').textContent = po.customer_tin || '-';
                    document.getElementById('viewCustomerTerms').textContent = (po.customer_terms || 0) + ' days';
                    
                    const total = po.total_quantity || 0;
                    const produced = po.produced_quantity || 0;
                    
                    const tbody = document.getElementById('viewPOItems');
                    tbody.innerHTML = '';
                    if (items && items.length > 0) {
                        items.forEach(function(item, idx) {
                            const qty = item.quantity || 0;
                            const itemProduced = item.produced_quantity || 0;
                            const itemPercent = qty > 0 ? Math.round((itemProduced / qty) * 100) : 0;
                            const barClass = itemPercent >= 100 ? 'bg-success' : 'bg-warning';
                            const barWidth = Math.min(itemPercent, 100);
                            const lineTotal = item.quantity * item.unit_price;
                            const row = '<tr>' +
                                '<td>' + (item.item_code || '-') + '</td>' +
                                '<td>' + (item.item_description || '-') + '</td>' +
                                '<td>' + (item.item_uom || '-') + '</td>' +
                                '<td>' + qty + '</td>' +
                                '<td>' +
                                    '<div class="d-flex align-items-center flex-wrap gap-1">' +
                                        '<div class="progress flex-grow-1 me-2" style="height: 14px; width: 80px;">' +
                                            '<div class="progress-bar ' + barClass + '" style="width: ' + barWidth + '%"></div>' +
                                        '</div>' +
                                        '<small class="text-muted">' + itemProduced + '/' + qty + ' pcs</small>' +
                                    '</div>' +
                                '</td>' +
                                '</tr>';
                            tbody.innerHTML += row;
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No items found</td></tr>';
                    }

                    var lotSelect = document.getElementById('lotTrackerItem');
                    lotSelect.innerHTML = '<option value="">-- Select Item --</option>';
                    if (items && items.length > 0) {
                        items.forEach(function(item) {
                            lotSelect.innerHTML += '<option value="' + item.poi_id + '">' +
                                (item.item_code || '') + ' - ' + (item.item_description || '-') +
                                '</option>';
                        });
                    }
                    document.getElementById('lotTrackerBody').innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">Select an item to view lots</td></tr>';
                    document.getElementById('lotTrackerFoot').style.display = 'none';

                    const modal = new bootstrap.Modal(document.getElementById('viewPOModal'));
                    modal.show();
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    Dlg.alert('Failed to load PO details: ' + (error.message || 'Unknown error'));
                });
        });
    });

    document.getElementById('lotTrackerItem').addEventListener('change', function() {
        var poiId = this.value;
        var tbody = document.getElementById('lotTrackerBody');
        var foot = document.getElementById('lotTrackerFoot');
        var totalEl = document.getElementById('lotTrackerTotal');
        if (!poiId) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">Select an item to view lots</td></tr>';
            foot.style.display = 'none';
            return;
        }
        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">Loading...</td></tr>';
        foot.style.display = 'none';
        fetch('?controller=production&action=getLotsByPOItem&poi_id=' + poiId + '&_t=' + Date.now())
            .then(function(r) { return r.json(); })
            .then(function(lots) {
                tbody.innerHTML = '';
                if (lots && lots.length > 0) {
                    var total = 0;
                    lots.forEach(function(lot) {
                        var qty = parseInt(lot.quantity_delivered || lot.quantity_produced) || 0;
                        total += qty;
                        tbody.innerHTML += '<tr>' +
                            '<td><strong>' + (lot.lot_number || '-') + '</strong></td>' +
                            '<td class="text-end">' + qty.toLocaleString() + '</td>' +
                            '</tr>';
                    });
                    totalEl.textContent = total.toLocaleString();
                    foot.style.display = '';
                } else {
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">No lots created</td></tr>';
                    foot.style.display = 'none';
                }
            })
            .catch(function() {
                tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-3">Failed to load lots</td></tr>';
                foot.style.display = 'none';
            });
    });
});

var _searchTimer;
document.getElementById('searchPO').addEventListener('input', function() {
    clearTimeout(_searchTimer);
    var form = this.closest('form');
    _searchTimer = setTimeout(function() { form.submit(); }, 500);
});

(function() {
    var s = document.getElementById('searchPO');
    if (s && s.value) { s.focus(); s.setSelectionRange(s.value.length, s.value.length); }
})();

function populateFilters() {
    const customers = new Set();
    const items = new Set();
    document.querySelectorAll('#poTableBody tr').forEach(row => {
        if (row.querySelector('td[colspan]')) return;
        const cust = row.cells[2] ? row.cells[2].textContent.trim() : '';
        if (cust) customers.add(cust);
        const itemCell = row.cells[3];
        if (itemCell) {
            const divs = itemCell.querySelectorAll('div');
            if (divs.length > 0) {
                divs.forEach(d => {
                    const t = d.textContent.trim();
                    if (t && t !== '-') items.add(t);
                });
            } else {
                itemCell.querySelectorAll('small').forEach(s => {
                    const t = s.textContent.trim();
                    if (t && t !== '-') items.add(t);
                });
            }
        }
    });
    const custSel = document.getElementById('filterCustomer');
    customers.forEach(c => { const o = document.createElement('option'); o.value = c; o.textContent = c; custSel.appendChild(o); });
    const itemSel = document.getElementById('filterItem');
    items.forEach(i => { const o = document.createElement('option'); o.value = i; o.textContent = i; itemSel.appendChild(o); });
}

function applyFilters() {
    const custFilter = document.getElementById('filterCustomer').value.toLowerCase();
    const itemFilter = document.getElementById('filterItem').value.toLowerCase();
    const dateFilter = document.getElementById('filterDate').value;
    document.querySelectorAll('#poTableBody tr').forEach(row => {
        if (row.querySelector('td[colspan]')) { row.style.display = ''; return; }
        const cust = row.cells[2] ? row.cells[2].textContent.trim().toLowerCase() : '';
        const itemText = row.cells[3] ? row.cells[3].textContent.trim().toLowerCase() : '';
        const poDate = row.cells[1] ? row.cells[1].textContent.trim() : '';
        let show = true;
        if (custFilter && !cust.includes(custFilter)) show = false;
        if (itemFilter && !itemText.includes(itemFilter)) show = false;
        if (dateFilter && poDate !== dateFilter) show = false;
        row.style.display = show ? '' : 'none';
    });
}

document.getElementById('filterCustomer').addEventListener('change', applyFilters);
document.getElementById('filterItem').addEventListener('change', applyFilters);
document.getElementById('filterDate').addEventListener('change', applyFilters);
document.getElementById('clearFilters').addEventListener('click', function() {
    document.getElementById('filterCustomer').value = '';
    document.getElementById('filterItem').value = '';
    document.getElementById('filterDate').value = '';
    document.getElementById('searchPO').value = '';
    var form = document.querySelector('#searchPO').closest('form');
    if (form) form.submit();
    else applyFilters();
});

document.addEventListener('DOMContentLoaded', populateFilters);

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