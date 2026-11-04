<h4><i class="bi bi-cart3 me-2"></i>Customer PO</h4>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap">
        <select id="filterCustomer" class="form-select form-select-sm filter-select" style="width:200px">
            <option value="">All Customers</option>
            <?php foreach (($allCustomers ?? []) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= ($filterCustomer ?? '') === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filterItem" class="form-select form-select-sm filter-select" style="width:200px">
            <option value="">All Items</option>
        </select>
        <input type="date" id="filterDate" class="form-control form-control-sm" style="width:160px" title="Filter by PO Date" value="<?= htmlspecialchars($filterDate ?? '') ?>">
        <select class="form-select form-select-sm filter-select" style="width:180px" onchange="location.href='?controller=admin&action=purchaseOrders&delivery_status=' + encodeURIComponent(this.value)">
            <option value="">All POs</option>
            <option value="open" <?= ($filterDeliveryStatus ?? '') === 'open' ? 'selected' : '' ?>>Open POs</option>
            <option value="closed" <?= ($filterDeliveryStatus ?? '') === 'closed' ? 'selected' : '' ?>>Closed POs</option>
        </select>
        <a href="?controller=admin&action=purchaseOrders" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Clear</a>
    </div>
    <div class="search-box" style="width: 300px;">
        <form method="GET" class="d-flex align-items-center">
            <input type="hidden" name="controller" value="admin">
            <input type="hidden" name="action" value="purchaseOrders">
            <input type="hidden" name="filter_customer" value="<?= htmlspecialchars($filterCustomer ?? '') ?>">
            <input type="hidden" name="filter_item" value="<?= htmlspecialchars($filterItem ?? '') ?>">
            <input type="hidden" name="filter_date" value="<?= htmlspecialchars($filterDate ?? '') ?>">
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
                       <th>Delivery Progress</th>
<th class="text-center action-col">Actions</th>
                </tr>
            </thead>
            <tbody id="poTableBody">
                <?php foreach ($allPOs as $po):
                    $items = $po_items_map[$po['po_id']] ?? [];
                ?>
                <tr>
                    <td><strong class="text-primary"><?= htmlspecialchars($po['customer_po_number']) ?></strong></td>
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
                                    $itemQty = intval($item['quantity'] ?? 0);
                                    $itemDelivered = intval($item['delivered_quantity'] ?? 0);
                                    $itemPercent = $itemQty > 0 ? min(100, round(($itemDelivered / $itemQty) * 100)) : 0;
                                    $statusLabel = $itemDelivered <= 0 ? 'Pending' : ($itemDelivered >= $itemQty ? 'Fully Delivered' : 'Partial (' . ($itemQty - $itemDelivered) . ' left)');
                                    $statusClass = $itemDelivered >= $itemQty ? 'bg-success' : ($itemDelivered > 0 ? 'bg-warning text-dark' : 'bg-secondary');
                                ?>
                                    <?= $idx > 0 ? '<hr class="my-1 border-secondary">' : '' ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:10px; min-width:50px;"><div class="progress-bar <?= $itemDelivered >= $itemQty ? 'bg-success' : 'bg-warning' ?>" style="width:<?= $itemPercent ?>%"></div></div>
                                        <small class="text-nowrap"><?= $itemDelivered ?>/<?= $itemQty ?> pcs</small>
                                        <span class="badge <?= $statusClass ?> text-nowrap"><?= $statusLabel ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                    <td class="text-center action-col">
                        <button type="button" class="btn btn-sm btn-outline-primary view-po-btn" data-po-id="<?= $po['po_id'] ?>">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allPOs)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No purchase orders found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<?php $pages = \App\Helpers\Pagination::getPageRange($page, $totalPages); ?>
<?php $paginationParams = http_build_query(array_filter(['controller'=>'admin','action'=>'purchaseOrders','search'=>$search??'','filter_customer'=>$filterCustomer??'','filter_item'=>$filterItem??'','filter_date'=>$filterDate??''])); ?>
<?php $paginationBase = '?' . $paginationParams . (strpos($paginationParams, '&') !== false ? '&' : '') . 'page='; ?>
<nav>
    <ul class="pagination justify-content-center mt-4">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $paginationBase ?><?= $page - 1 ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= $paginationBase ?><?= $p ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $paginationBase ?><?= $page + 1 ?>">Next &raquo;</a>
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
    var _currentPOItems = [];

    function loadLots(poiId) {
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
        fetch('?controller=admin&action=getLotsByPOItem&poi_id=' + poiId + '&_t=' + Date.now())
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
    }

    document.querySelectorAll('.view-po-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const poId = this.getAttribute('data-po-id');
            fetch('?controller=warehouse&action=getPODetails&id=' + poId)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    const po = data.po;
                    const items = data.po_items;
                    _currentPOItems = items || [];

                    document.getElementById('viewPONumber').textContent = po.customer_po_number || '-';
                    document.getElementById('viewCustomerCode').textContent = po.customer_code || '-';
                    document.getElementById('viewCustomerName').textContent = po.customer_name || '-';
                    document.getElementById('viewCustomerTin').textContent = po.customer_tin || '-';
                    document.getElementById('viewCustomerTerms').textContent = (po.customer_terms || 0) + ' days';

                    var tbody = document.getElementById('viewPOItems');
                    tbody.innerHTML = '';
                    if (items && items.length > 0) {
                        items.forEach(function(item) {
                            var qty = item.quantity || 0;
                            var itemDelivered = item.delivered_quantity || 0;
                            var itemPercent = qty > 0 ? Math.min(100, Math.round((itemDelivered / qty) * 100)) : 0;
                            var barClass = itemPercent >= 100 ? 'bg-success' : 'bg-warning';
                            var barWidth = Math.min(itemPercent, 100);
                            tbody.innerHTML += '<tr>' +
                                '<td>' + (item.item_code || '-') + '</td>' +
                                '<td>' + (item.item_description || '-') + '</td>' +
                                '<td>' + (item.item_uom || '-') + '</td>' +
                                '<td>' + qty + '</td>' +
                                '<td>' +
                                    '<div class="d-flex align-items-center">' +
                                        '<div class="progress flex-grow-1 me-2" style="height: 14px; width: 80px;">' +
                                            '<div class="progress-bar ' + barClass + '" style="width: ' + barWidth + '%"></div>' +
                                        '</div>' +
                                        '<small class="text-muted">' + itemDelivered + '/' + qty + ' pcs</small>' +
                                    '</div>' +
                                '</td>' +
                                '</tr>';
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

                    var modal = new bootstrap.Modal(document.getElementById('viewPOModal'));
                    modal.show();
                })
                .catch(function(error) {
                    Dlg.alert('Failed to load PO details: ' + error.message);
                });
        });
    });

    document.getElementById('lotTrackerItem').addEventListener('change', function() {
        loadLots(this.value);
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

function applyServerFilters() {
    var params = new URLSearchParams();
    params.set('controller', 'admin');
    params.set('action', 'purchaseOrders');
    var s = document.getElementById('searchPO');
    if (s && s.value) params.set('search', s.value);
    var c = document.getElementById('filterCustomer');
    if (c && c.value) params.set('filter_customer', c.value);
    var i = document.getElementById('filterItem');
    if (i && i.value) params.set('filter_item', i.value);
    var d = document.getElementById('filterDate');
    if (d && d.value) params.set('filter_date', d.value);
    window.location.href = '?' + params.toString();
}

function populateItemFilter() {
    const items = new Set();
    document.querySelectorAll('#poTableBody tr').forEach(row => {
        if (row.querySelector('td[colspan]')) return;
        const itemCell = row.cells[3];
        if (itemCell) {
            itemCell.querySelectorAll('small').forEach(s => {
                const t = s.textContent.trim();
                if (t && t !== '-') items.add(t);
            });
        }
    });
    const itemSel = document.getElementById('filterItem');
    items.forEach(i => { const o = document.createElement('option'); o.value = i; o.textContent = i; itemSel.appendChild(o); });
    var currentFilterItem = '<?= addslashes($filterItem ?? '') ?>';
    if (currentFilterItem && itemSel) { for (var j = 0; j < itemSel.options.length; j++) { if (itemSel.options[j].value === currentFilterItem) { itemSel.selectedIndex = j; break; } } }
}

document.getElementById('filterCustomer').addEventListener('change', applyServerFilters);
document.getElementById('filterItem').addEventListener('change', applyServerFilters);
document.getElementById('filterDate').addEventListener('change', applyServerFilters);

document.addEventListener('DOMContentLoaded', populateItemFilter);

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